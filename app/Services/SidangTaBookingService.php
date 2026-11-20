<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Models\SidangTaExecutionSetting;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaSchedule;
use App\Models\User;
use App\Support\RoomTimeWindows as W;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SidangTaBookingService
{
    public function latestApprovedFor(string $studentNumber): ?SidangTaRegistration
    {
        return SidangTaRegistration::where('student_number', $studentNumber)->where('status', 'approved')->orderByDesc('submitted_at')->orderByDesc('id')->first();
    }

    public function isLatestApproved(SidangTaRegistration $registration): bool
    {
        return $this->latestApprovedFor($registration->student_number)?->id === $registration->id;
    }

    private function eligible(SidangTaRegistration $r, ?SidangTaSchedule $c): void
    {
        if ($r->status !== 'approved' || ! $this->isLatestApproved($r)) {
            throw ValidationException::withMessages(['schedule' => 'Pendaftaran Sidang TA belum disetujui.']);
        }
        if ($c && ($c->mode !== 'automatic' || $c->was_confirmed || $c->room_booking_request_id)) {
            throw ValidationException::withMessages(['schedule' => 'Jadwal sudah ditangani. Hubungi SSC untuk perubahan.']);
        }
    }

    public function candidate(SidangTaExecutionSetting $s, string $date, ?int $exceptBookingId = null): ?array
    {
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        $d = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Jakarta');
        if ($d->format('Y-m-d') !== $date || $date < today('Asia/Jakarta')->toDateString() || $date < $s->start_date->toDateString() || $date > $s->end_date->toDateString() || ! in_array($d->dayOfWeekIso, array_map('intval', $s->allowed_weekdays), true)) {
            throw ValidationException::withMessages(['date' => 'Pilih tanggal dalam jadwal pelaksanaan yang masih tersedia.']);
        }
        $availability = app(RoomAvailabilityService::class);
        if (! $availability->coverage($date, $date)['ready']) {
            return null;
        }
        $busy = $exceptBookingId ? $availability->rangeExcept($date, $date, $exceptBookingId) : $availability->forRange($date, $date);
        $notBefore = $date === today('Asia/Jakarta')->toDateString() ? W::minutes(now('Asia/Jakarta')->format('H:i:s'), true) : 0;
        $candidates = [];
        foreach (Room::bookableClassrooms()->orderBy('id')->get() as $room) {
            $slot = W::firstFit($busy[$date][$room->id] ?? [], app(SidangTaExecutionWindowService::class)->windows($s), $s->duration_minutes, $notBefore);
            if ($slot) {
                $candidates[] = ['date' => $date, 'room_id' => $room->id, 'room_code' => $room->room_code, 'room_name' => $room->name, 'start_time' => W::time($slot['start']), 'end_time' => W::time($slot['end']), 'duration_minutes' => $s->duration_minutes];
            }
        }
        usort($candidates, fn ($a, $b) => [$a['start_time'], $a['room_code'], $a['room_id']] <=> [$b['start_time'], $b['room_code'], $b['room_id']]);

        return $candidates[0] ?? null;
    }

    private function stamp(SidangTaRegistration $r, ?SidangTaSchedule $c, SidangTaExecutionSetting $s, string $date, User $actor): array
    {
        return ['user_id' => $actor->id, 'registration_id' => $r->id, 'student_number' => $r->student_number, 'registration_version' => $r->lock_version, 'registration_updated_at' => $r->getRawOriginal('updated_at'), 'context_version' => $c?->lock_version ?? 0, 'setting_version' => $s->lock_version, 'source' => app(RoomAvailabilityService::class)->coverage($date, $date)['source_versions']];
    }

    public function preview(User $actor, SidangTaRegistration $registration, string $date): array
    {
        app(SidangTaBookingAccess::class)->authorizeStudent($actor, $registration, true);
        $r = $registration->fresh();
        $c = $r->roomSchedule;
        $this->eligible($r, $c);
        $s = $r->period->executionSetting;
        if (! $s) {
            throw ValidationException::withMessages(['date' => 'Jadwal pelaksanaan belum ditetapkan. Hubungi SSC.']);
        }
        $candidate = $this->candidate($s, $date);
        $identity = app(SidangTaBookingIdentity::class)->forRegistration($r);
        $stamp = $this->stamp($r, $c, $s, $date, $actor);

        return ['identity' => $identity, 'candidate' => $candidate, 'preview_token' => $candidate ? Crypt::encryptString(json_encode(['stamp' => $stamp, 'candidate' => $candidate, 'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR)) : null, 'context_version' => $c?->lock_version ?? 0, 'reason' => $candidate ? null : 'Tidak ada ruang kelas tersedia atau sumber jadwal semester belum lengkap.'];
    }

    public function confirm(User $actor, SidangTaRegistration $registration, string $previewToken): SidangTaSchedule
    {
        app(SidangTaBookingAccess::class)->authorizeStudent($actor, $registration, true);
        try {
            $data = json_decode(Crypt::decryptString($previewToken), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data['stamp'] ?? null) || ! is_array($data['candidate'] ?? null) || ! is_int($data['expires'] ?? null) || $data['expires'] < now()->timestamp) {
                throw new \RuntimeException;
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['preview_token' => 'Pratinjau tidak valid atau kedaluwarsa. Cari jadwal kembali.']);
        }

        return app(SidangTaBookingLock::class)->run($registration->id, [], function ($r, $c, $s) use ($actor, $data) {
            app(SidangTaBookingAccess::class)->authorizeStudent($actor, $r, true);
            abort_unless($s && $r->status === 'approved' && $c->mode === 'automatic' && ! $c->was_confirmed && ! $c->room_booking_request_id, 409, 'Konteks berubah. Muat ulang dan cari jadwal kembali.');
            abort_unless($this->isLatestApproved($r), 409, 'Pengajuan terbaru berubah. Muat ulang dan gunakan pengajuan terbaru.');
            $date = $data['candidate']['date'];
            $actual = $this->stamp($r, $c->wasRecentlyCreated ? null : $c, $s, $date, $actor);
            abort_unless($actual == $data['stamp'], 409, 'Sumber atau pengaturan telah berubah. Cari jadwal kembali.');
            $candidate = $this->candidate($s, $date);
            abort_unless($candidate && $candidate === $data['candidate'], 409, 'Jadwal berubah atau telah diambil. Cari jadwal kembali.');
            $this->assertNoOtherActive($r, $c);
            $b = RoomBookingRequest::create(['user_id' => $actor->id, 'room_id' => $candidate['room_id'], 'type' => 'single', 'start_date' => $date, 'end_date' => $date, 'start_time' => $candidate['start_time'], 'end_time' => $candidate['end_time'], 'purpose' => 'Sidang TA pengajuan #'.$r->id, 'participants' => 1, 'status' => 'approved', 'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $r->id]);
            $b->occurrences()->create(['usage_date' => $date, 'start_time' => $candidate['start_time'], 'end_time' => $candidate['end_time']]);
            app(RoomBookingTransitionService::class)->record($actor, $b, 'pending', 'Konfirmasi jadwal Sidang TA otomatis.', []);
            $c->fill(['room_booking_request_id' => $b->id, 'active_student_number' => $r->student_number, 'was_confirmed' => true, 'state' => 'confirmed', 'lock_version' => $c->lock_version + 1])->save();

            return $c->fresh('booking.occurrences');
        });
    }

    public function assertNoOtherActive(SidangTaRegistration $r, SidangTaSchedule $context): void
    {
        $others = SidangTaSchedule::with('booking.occurrences')->where('student_number', $r->student_number)->whereKeyNot($context->id)->orderBy('id')->lockForUpdate()->get();
        foreach ($others as $other) {
            if (! $other->active_student_number) {
                continue;
            }
            $booking = $other->booking;
            $last = $booking?->occurrences->sortByDesc(fn ($o) => $o->usage_date->toDateString().' '.$o->end_time)->first();
            if ($last && $last->usage_date->toDateString().' '.$last->end_time < now()->format('Y-m-d H:i:s')) {
                $other->update(['active_student_number' => null, 'state' => 'completed', 'lock_version' => $other->lock_version + 1]);

                continue;
            }
            throw ValidationException::withMessages(['schedule' => 'Mahasiswa sudah memiliki jadwal/konteks aktif lain. Hubungi SSC.']);
        }
    }
}
