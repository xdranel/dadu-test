<?php

namespace App\Services;

use App\Http\Requests\SaveSidangTaExecutionRequest;
use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Models\SidangTaExecutionSetting;
use App\Models\SidangTaPeriod;
use App\Models\User;
use App\Support\RoomTimeWindows as W;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SidangTaExecutionWindowService
{
    public function windows(SidangTaExecutionSetting $s): array
    {
        $start = W::minutes($s->start_time);
        $end = W::minutes($s->end_time);

        return $s->duration_minutes !== 90 && $s->break_start_time ? [['start' => $start, 'end' => W::minutes($s->break_start_time)], ['start' => W::minutes($s->break_end_time), 'end' => $end]] : [['start' => $start, 'end' => $end]];
    }

    public function assertSlot(SidangTaExecutionSetting $s, string $date, string $start, string $end, bool $enforceDuration = true): void
    {
        $d = CarbonImmutable::parse($date, 'Asia/Jakarta');
        $a = W::minutes($start);
        $b = W::minutes($end, true);
        $ok = $date >= $s->start_date->toDateString() && $date <= $s->end_date->toDateString() && in_array($d->dayOfWeekIso, array_map('intval', $s->allowed_weekdays), true) && $b > $a;
        $fits = false;
        foreach ($this->windows($s) as $w) {
            if ($a >= $w['start'] && $b <= $w['end']) {
                $fits = true;
            }
        }
        if (! $ok || ! $fits || ($enforceDuration && $b - $a !== $s->duration_minutes)) {
            throw ValidationException::withMessages(['schedule' => 'Jadwal di luar tanggal/hari/jam pelaksanaan atau melewati istirahat/durasi.']);
        }
    }

    public function save(User $actor, SidangTaPeriod $period, array $values, int $version): SidangTaExecutionSetting
    {
        abort_unless($actor->can('sidang_ta_period_update'), 403);
        $v = Validator::make($values, SaveSidangTaExecutionRequest::windowRules())->validate();

        return DB::transaction(function () use ($period, $v, $version) {
            SidangTaPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $old = SidangTaExecutionSetting::where('sidang_ta_period_id', $period->id)->lockForUpdate()->first();
            abort_unless(($old?->lock_version ?? 0) === $version, 409, 'Pengaturan telah berubah. Muat ulang.');
            $periods = AcademicPeriod::orderBy('id')->lockForUpdate()->get();
            $academic = $periods->firstWhere('id', (int) $v['academic_period_id']);
            $candidate = new SidangTaExecutionSetting($v);
            if ($v['start_date'] < $academic->start_date->toDateString() || $v['end_date'] > $academic->end_date->toDateString() || CarbonImmutable::parse($v['start_date'])->diffInDays($v['end_date']) > 365) {
                throw ValidationException::withMessages(['end_date' => 'Tanggal pelaksanaan harus berada dalam semester sumber, maksimal 366 hari.']);
            }
            if ($candidate->break_start_time && (W::minutes($candidate->break_start_time) < W::minutes($candidate->start_time) || W::minutes($candidate->break_end_time) > W::minutes($candidate->end_time))) {
                throw ValidationException::withMessages(['break_start_time' => 'Istirahat harus berada dalam jam pelaksanaan.']);
            }
            if (! collect($this->windows($candidate))->contains(fn ($w) => $candidate->duration_minutes <= $w['end'] - $w['start'])) {
                throw ValidationException::withMessages(['duration_minutes' => 'Durasi harus muat tanpa melewati istirahat.']);
            }
            Room::orderBy('id')->lockForUpdate()->get();
            $bookings = RoomBookingRequest::with('occurrences')->whereIn('status', ['pending', 'approved'])->whereHas('registration', fn ($q) => $q->where('sidang_ta_period_id', $period->id))->get();
            foreach ($bookings as $b) {
                foreach ($b->occurrences as $o) {
                    if ($o->usage_date->toDateString().' '.$o->end_time >= now()->format('Y-m-d H:i:s')) {
                        $this->assertSlot($candidate, $o->usage_date->toDateString(), $o->start_time, $o->end_time, false);
                    }
                }
            }
            $s = $old ?? new SidangTaExecutionSetting(['sidang_ta_period_id' => $period->id]);
            $s->fill($v);
            $s->lock_version = $version + 1;
            $s->save();

            return $s;
        }, 3);
    }
}
