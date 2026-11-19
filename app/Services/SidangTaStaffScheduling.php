<?php

namespace App\Services;

use App\Models\AcademicPeriod;
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

class SidangTaStaffScheduling
{
    private function assertReady(SidangTaRegistration $r, ?SidangTaSchedule $c, ?SidangTaExecutionSetting $s): void
    {
        if ($r->status !== 'approved' || (! $c?->was_confirmed && ! app(SidangTaBookingService::class)->isLatestApproved($r))) {
            throw ValidationException::withMessages(['schedule' => 'Gunakan pengajuan terbaru yang sudah disetujui.']);
        }
        if (! $s || ! $s->is_active) {
            throw ValidationException::withMessages(['date' => 'Jadwal pelaksanaan belum aktif. Atur atau aktifkan pelaksanaan dahulu.']);
        }
        if ($c && $c->result_status !== 'pending') {
            throw ValidationException::withMessages(['schedule' => 'Batalkan hasil sidang dahulu sebelum mengubah jadwal.']);
        }
    }

    private function searchWindow(SidangTaExecutionSetting $s): SidangTaExecutionSetting
    {
        $window = clone $s;
        // Official 90-minute slots include free lunch-time rooms. Existing bookings are unchanged.
        $window->forceFill(['duration_minutes' => 90, 'break_start_time' => null, 'break_end_time' => null]);

        return $window;
    }

    private function candidates(SidangTaExecutionSetting $s, ?SidangTaSchedule $c, string $date): array
    {
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Jakarta');
        if ($date < today('Asia/Jakarta')->toDateString() || $date < $s->start_date->toDateString()
            || $date > $s->end_date->toDateString() || ! in_array($day->dayOfWeekIso, array_map('intval', $s->allowed_weekdays), true)) {
            throw ValidationException::withMessages(['date' => 'Pilih tanggal yang tersedia dalam jadwal pelaksanaan aktif.']);
        }
        $availability = app(RoomAvailabilityService::class);
        if (! $availability->coverage($date, $date)['ready']) {
            return [];
        }
        $booking = $c?->booking;
        $except = $booking && in_array($booking->status, ['pending', 'approved'], true) ? $booking->id : null;
        $busy = $except ? $availability->rangeExcept($date, $date, $except) : $availability->forRange($date, $date);
        $start = W::minutes($s->start_time);
        $end = W::minutes($s->end_time);
        $notBefore = $date === today('Asia/Jakarta')->toDateString() ? W::minutes(now('Asia/Jakarta')->format('H:i:s'), true) + 1 : 0;
        $preferred = $c?->preferred_date?->format('Y-m-d') === $date ? $c?->preferred_start_time : null;
        $slots = [];
        foreach (Room::where('is_classroom', true)->where('status', 'Dapat Dipakai')->orderBy('room_code')->orderBy('id')->get() as $room) {
            $occupied = $busy[$date][$room->id] ?? [];
            $seeds = array_merge(range($start, $end, 30), array_column($occupied, 'end'), [$start, $notBefore]);
            if ($preferred) {
                $seeds[] = W::minutes($preferred);
            }
            foreach (array_unique($seeds) as $minute) {
                if ($minute < max($start, $notBefore) || $minute + 90 > $end) {
                    continue;
                }
                if (collect($occupied)->contains(fn ($b) => $b['start'] < $minute + 90 && $b['end'] > $minute)) {
                    continue;
                }
                $slots[] = ['date' => $date, 'room_id' => $room->id, 'room_code' => $room->room_code,
                    'room_name' => $room->name, 'start_time' => W::time($minute), 'end_time' => W::time($minute + 90), 'duration_minutes' => 90];
            }
        }
        $score = static function ($slot) use ($c, $preferred) {
            $sameTime = $preferred && substr($slot['start_time'], 0, 5) === substr($preferred, 0, 5);
            $sameRoom = $c?->preferred_room_id && (int) $c->preferred_room_id === $slot['room_id'];

            return $sameTime && $sameRoom ? 0 : ($sameTime ? 1 : ($sameRoom ? 2 : 3));
        };
        usort($slots, fn ($a, $b) => [$score($a), $a['start_time'], $a['room_code'], $a['room_id']] <=> [$score($b), $b['start_time'], $b['room_code'], $b['room_id']]);

        return $slots;
    }

    private function stamp(User $actor, SidangTaRegistration $r, ?SidangTaSchedule $c, SidangTaExecutionSetting $s, string $date): array
    {
        return ['actor' => $actor->id, 'registration' => $r->id, 'registration_version' => $r->lock_version,
            'registration_updated_at' => $r->getRawOriginal('updated_at'), 'context_version' => $c?->lock_version ?? 0,
            'settings_version' => $s->lock_version, 'settings_updated_at' => $s->getRawOriginal('updated_at'),
            'source' => app(RoomAvailabilityService::class)->coverage($date, $date)['source_versions']];
    }

    public function preview(User $actor, SidangTaRegistration $registration, string $date): array
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        $r = $registration->fresh(['period.executionSetting', 'roomSchedule.booking']);
        $c = $r->roomSchedule;
        $s = $r->period->executionSetting;
        $this->assertReady($r, $c, $s);
        if ($c?->booking && in_array($c->booking->status, ['pending', 'approved'], true)
            && $c->booking->occurrences->contains(fn ($o) => $o->usage_date->toDateString().' '.$o->start_time <= now('Asia/Jakarta')->format('Y-m-d H:i:s'))) {
            throw ValidationException::withMessages(['schedule' => 'Sidang sudah dimulai. Jadwal historis dipertahankan.']);
        }
        $stamp = $this->stamp($actor, $r, $c, $s, $date);
        $options = [];
        foreach ($this->candidates($s, $c, $date) as $i => $candidate) {
            $options[] = ['candidate' => $candidate, 'recommended' => $i === 0,
                'token' => Crypt::encryptString(json_encode(['stamp' => $stamp, 'candidate' => $candidate,
                    'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR))];
        }

        return ['options' => $options, 'context_version' => $c?->lock_version ?? 0,
            'reason' => $options ? null : $this->emptySearchReason($date)];
    }

    private function emptySearchReason(string $date): string
    {
        $reasons = [];
        $rooms = Room::where('status', 'Dapat Dipakai');
        if (! (clone $rooms)->where('is_classroom', true)->exists()) {
            $reasons[] = $rooms->exists()
                ? 'Belum ada ruang kelas aktif untuk Sidang TA. Buka Data Akademik → Ruangan → Edit, lalu pilih Klasifikasi ruang: Ruang kelas untuk ruangan yang memang kelas. Impor master XLSX saja belum menetapkan klasifikasi ini.'
                : 'Tidak ada ruangan berstatus Dapat Dipakai. Periksa status dan Klasifikasi ruang pada Data Akademik → Ruangan.';
        }
        if (! app(RoomAvailabilityService::class)->coverage($date, $date)['ready']) {
            $periods = AcademicPeriod::with('roomSource')->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)->get();
            if ($periods->isEmpty()) {
                $reasons[] = 'Tanggal '.$date.' belum tercakup periode akademik terdaftar. Periksa tanggal semester sumber jadwal.';
            } else {
                $labels = $periods->filter(fn ($p) => ! $p->roomSource?->is_complete || $p->roomSource?->scope !== 'semester')
                    ->map(fn ($p) => $p->label ?: $p->year.' '.($p->semester === 1 ? 'Ganjil' : 'Genap'))->implode(', ');
                $reasons[] = 'Data sumber jadwal semester '.$labels.' belum lengkap untuk '.$date.'. Impor snapshot mingguan lengkap di IT Panel → Sinkronisasi → Jadwal Ruangan iGracias; periksa hasil impor dan pemetaan alias ruangan. Jadwal tanggal tertentu belum menjadi sumber semester.';
            }
        }

        return $reasons ? implode(' ', $reasons)
            : 'Tidak ada slot kosong 90 menit pada tanggal ini dalam jam pelaksanaan. Ruang kelas aktif sudah terpakai atau celah waktunya tidak cukup; periksa jadwal kuliah dan peminjaman pending/disetujui, atau pilih tanggal lain.';
    }

    public function confirm(User $actor, SidangTaRegistration $registration, string $token): SidangTaSchedule
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        try {
            $data = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data['stamp'] ?? null) || ! is_array($data['candidate'] ?? null)
                || ! is_int($data['expires'] ?? null) || $data['expires'] < now()->timestamp) {
                throw new \RuntimeException;
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['preview_token' => 'Pilihan jadwal tidak valid atau kedaluwarsa. Cari kembali.']);
        }

        return app(SidangTaBookingLock::class)->run($registration->id, [], function ($r, $c, $s) use ($actor, $data) {
            $this->assertReady($r, $c, $s);
            $candidate = $data['candidate'];
            $actual = $this->stamp($actor, $r, $c->wasRecentlyCreated ? null : $c, $s, $candidate['date']);
            abort_unless($actual == $data['stamp'], 409, 'Pengajuan, sumber atau pengaturan berubah. Cari jadwal kembali.');
            $available = collect($this->candidates($s, $c, $candidate['date']))->contains(fn ($option) => $option === $candidate);
            abort_unless($available, 409, 'Slot sudah berubah atau terpakai. Cari jadwal kembali.');
            $activeBooking = $c->booking && in_array($c->booking->status, ['pending', 'approved'], true);
            if ($c->was_confirmed || $activeBooking) {
                $slot = ['date' => $candidate['date'], 'room_id' => $candidate['room_id'],
                    'start_time' => substr($candidate['start_time'], 0, 5), 'end_time' => substr($candidate['end_time'], 0, 5)];
                $c = app(SidangTaBookingAdministration::class)->reschedule($actor, $r, $slot, $c->lock_version, 'Penyesuaian plotting oleh admin.');
                if ($c->booking->status === 'pending') {
                    app(RoomBookingTransitionService::class)->decide($actor, $c->booking, 'approved', 'Jadwal ditetapkan admin.');
                    $c = $c->fresh();
                }
            } else {
                app(SidangTaBookingService::class)->assertNoOtherActive($r, $c);
                $booking = RoomBookingRequest::create(['user_id' => $actor->id, 'room_id' => $candidate['room_id'],
                    'type' => 'single', 'start_date' => $candidate['date'], 'end_date' => $candidate['date'],
                    'start_time' => $candidate['start_time'], 'end_time' => $candidate['end_time'],
                    'purpose' => 'Sidang TA '.$r->student_number, 'participants' => 1, 'status' => 'approved',
                    'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $r->id]);
                $booking->occurrences()->create(['usage_date' => $candidate['date'],
                    'start_time' => $candidate['start_time'], 'end_time' => $candidate['end_time']]);
                app(RoomBookingTransitionService::class)->record($actor, $booking, 'pending', 'Jadwal ditetapkan admin.', []);
                $c->fill(['room_booking_request_id' => $booking->id, 'active_student_number' => $r->student_number,
                    'mode' => 'automatic', 'state' => 'confirmed', 'was_confirmed' => true, 'lock_version' => $c->lock_version + 1])->save();
            }
            app(SidangTaExecutionInfoService::class)->capture($r);
            $c->executionLogs()->create(['action' => 'scheduled', 'notes' => 'Jadwal ditetapkan admin.',
                'data' => $candidate, 'performed_by' => $actor->id, 'performed_at' => now()]);

            return $c->fresh(['booking.room', 'booking.occurrences']);
        });
    }
}
