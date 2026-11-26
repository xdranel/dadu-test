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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
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

    private function candidates(SidangTaRegistration $registration, SidangTaExecutionSetting $setting, ?SidangTaSchedule $context, array $slot, bool $importedLab): array
    {
        $date = $slot['date'];
        $start = W::minutes($slot['start_time']);
        $end = W::minutes($slot['end_time']);
        app(SidangTaExecutionWindowService::class)->assertStaffSlot($setting, $date, $slot['start_time'], $slot['end_time']);
        $taType = $context?->taType() ?? 'Individu';
        $rooms = $this->roomsForSearch($context, $slot, $importedLab, $taType);
        $availability = app(RoomAvailabilityService::class);
        if (! $availability->coverage($date, $date)['ready']) {
            return [];
        }
        return app(SidangTaSessionAvailability::class)->freeRooms($rooms, $registration, $taType, $slot, $context?->booking)
            ->sortBy(fn (Room $room) => [
                ! preg_match('/\bGEDUNG[\s-]+A\b/i', $room->building ?? ''),
                $room->id !== (int) $context?->preferred_room_id,
                $room->room_code,
                $room->id,
            ])
            ->map(fn (Room $room) => $this->candidate($room, $date, $start, $end))->values()->all();
    }

    private function candidate(Room $room, string $date, int $start, int $end): array
    {
        return [
            'date' => $date, 'room_id' => $room->id, 'room_code' => $room->room_code, 'room_name' => $room->name,
            'start_time' => W::time($start), 'end_time' => W::time($end), 'duration_minutes' => $end - $start,
        ];
    }

    private function roomsForSearch(?SidangTaSchedule $context, array $slot, bool $importedLab, string $taType): Collection
    {
        $room = $this->importedLab($context, $slot);
        if ($importedLab) {
            if (! $room) {
                throw ValidationException::withMessages(['room_id' => 'Ruang LAB harus dapat dipakai dan menggunakan tanggal serta jam dari impor Excel.']);
            }

            return collect([$room]);
        }

        $rooms = app(SidangTaSessionAvailability::class)->eligibleRooms($taType);

        return $room && $taType === 'Individu' ? $rooms->push($room) : $rooms;
    }

    public function importedLab(?SidangTaSchedule $context, array $slot): ?Room
    {
        if ($context?->planning_source !== 'import' || ! $context->preferred_room_id
            || $context->preferred_date?->toDateString() !== $slot['date']
            || substr((string) $context->preferred_start_time, 0, 5) !== $slot['start_time']
            || substr((string) $context->preferred_end_time, 0, 5) !== $slot['end_time']) {
            return null;
        }

        return Room::where('label', 'LAB')->where('status', 'Dapat Dipakai')->find($context->preferred_room_id);
    }

    public function plottingRooms(User $actor, SidangTaRegistration $registration, array $slot): array
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        $r = $registration->fresh(['period.executionSetting', 'roomSchedule.booking.occurrences']);
        $context = $r->roomSchedule;
        $booking = $context?->booking;
        $this->assertReady($r, $context, $r->period->executionSetting);
        if ($booking?->status === 'pending' || ($booking?->status === 'approved' && $booking->occurrences->count() !== 1)) {
            throw ValidationException::withMessages(['schedule' => 'Selesaikan peminjaman yang tertaut sebelum mengedit plotting.']);
        }

        $date = $slot['date'];
        app(SidangTaExecutionWindowService::class)->assertStaffSlot($r->period->executionSetting, $date, $slot['start_time'], $slot['end_time']);
        $availability = app(RoomAvailabilityService::class);
        if (! $availability->coverage($date, $date)['ready']) {
            return ['rooms' => [], 'message' => 'Sumber jadwal semester belum lengkap untuk tanggal ini.'];
        }
        $taType = $slot['ta_type'] ?? $context?->taType() ?? 'Individu';
        $rooms = $this->roomsForSearch($context, $slot, false, $taType)->sortBy('room_code');
        $available = app(SidangTaSessionAvailability::class)->freeRooms($rooms, $r, $taType, $slot, $booking)
            ->map(fn (Room $room) => $room->only(['id', 'room_code', 'building', 'capacity']))->values()->all();

        return ['rooms' => $available, 'message' => $available ? null : 'Tidak ada ruang yang tersedia pada tanggal dan jam ini.'];
    }

    private function stamp(User $actor, SidangTaRegistration $r, ?SidangTaSchedule $c, SidangTaExecutionSetting $s, string $date): array
    {
        return ['actor' => $actor->id, 'registration' => $r->id, 'registration_version' => $r->lock_version,
            'registration_updated_at' => $r->getRawOriginal('updated_at'), 'context_version' => $c?->lock_version ?? 0,
            'settings_version' => $s->lock_version, 'settings_updated_at' => $s->getRawOriginal('updated_at'),
            'source' => app(RoomAvailabilityService::class)->coverage($date, $date)['source_versions']];
    }

    public function preview(User $actor, SidangTaRegistration $registration, string $date, string $startTime, string $endTime, bool $importedLab = false): array
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        $r = $registration->fresh(['period.executionSetting', 'roomSchedule.booking']);
        $c = $r->roomSchedule;
        $s = $r->period->executionSetting;
        $this->assertReady($r, $c, $s);
        $slot = ['date' => $date, 'start_time' => $startTime, 'end_time' => $endTime];
        $stamp = $this->stamp($actor, $r, $c, $s, $date);
        $options = [];
        foreach ($this->candidates($r, $s, $c, $slot, $importedLab) as $i => $candidate) {
            $options[] = ['candidate' => $candidate, 'recommended' => $i === 0,
                'token' => Crypt::encryptString(json_encode(['stamp' => $stamp, 'candidate' => $candidate, 'imported_lab' => $importedLab,
                    'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR))];
        }

        return ['options' => $options, 'slot' => $slot, 'context_version' => $c?->lock_version ?? 0,
            'reason' => $options ? null : $this->emptySearchReason($slot, $c, $importedLab)];
    }

    private function emptySearchReason(array $slot, ?SidangTaSchedule $context, bool $importedLab): string
    {
        $date = $slot['date'];
        $reasons = [];
        $taType = $context?->taType() ?? 'Individu';
        if (! $importedLab && $this->roomsForSearch($context, $slot, false, $taType)->isEmpty()) {
            if ($taType === 'Kelompok') {
                $reasons[] = 'Tidak ada ruang KELAS atau LAB yang berstatus Dapat Dipakai.';
            } else {
                $reasons[] = 'Tidak ada ruang berlabel KELAS dan berstatus Dapat Dipakai. Periksa master ruang pada Data Akademik → Ruangan.';
            }
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

        return $reasons ? implode(' ', $reasons) : ($importedLab
            ? 'LAB dari Excel sudah terpakai pada rentang jam yang dipilih.'
            : ($taType === 'Kelompok' || $this->importedLab($context, $slot) ? 'Tidak ada ruang yang tersedia pada rentang jam yang dipilih.' : 'Tidak ada ruang kelas yang tersedia pada rentang jam yang dipilih.'));
    }

    public function confirm(User $actor, SidangTaRegistration $registration, string $token): SidangTaSchedule
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        try {
            $data = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data['stamp'] ?? null) || ! is_array($data['candidate'] ?? null)
                || ! is_bool($data['imported_lab'] ?? null) || ! is_int($data['expires'] ?? null) || $data['expires'] < now()->timestamp) {
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
            $slot = ['date' => $candidate['date'], 'start_time' => substr($candidate['start_time'], 0, 5), 'end_time' => substr($candidate['end_time'], 0, 5)];
            $importedLab = $data['imported_lab'];
            $available = in_array($candidate, $this->candidates($r, $s, $c, $slot, $importedLab), true);
            abort_unless($available, 409, 'Slot sudah berubah atau terpakai. Cari jadwal kembali.');
            $activeBooking = $c->booking && in_array($c->booking->status, ['pending', 'approved'], true);
            $rescheduling = $c->was_confirmed || $activeBooking;
            if ($rescheduling) {
                $allowLab = $c->taType() === 'Kelompok' || $this->importedLab($c, $slot)?->id === $candidate['room_id'];
                $c = app(SidangTaBookingAdministration::class)->reschedule($actor, $r, $slot + ['room_id' => $candidate['room_id']], $c->lock_version, 'Penyesuaian plotting oleh admin.', allowImportedLab: $allowLab, allowElapsedBooking: true);
                if ($c->booking->status === 'pending') {
                    app(RoomBookingTransitionService::class)->decide($actor, $c->booking, 'approved', 'Jadwal ditetapkan admin.', allowImportedLab: $allowLab);
                    $c = $c->fresh();
                }
            } else {
                $this->createBooking($actor, $r, $c, $candidate, 'Jadwal ditetapkan admin.');
            }
            if ($candidate['date'].' '.$candidate['end_time'] <= now('Asia/Jakarta')->format('Y-m-d H:i:s')) {
                $c->fill(['state' => 'completed', 'active_student_number' => null])->save();
            }
            app(SidangTaExecutionInfoService::class)->capture($r, refreshSchedule: true, preserveParticipant: true);
            $c->executionLogs()->create(['action' => $rescheduling ? 'rescheduled' : 'scheduled', 'notes' => $rescheduling ? 'Jadwal diperbarui admin.' : 'Jadwal ditetapkan admin.',
                'data' => $candidate, 'performed_by' => $actor->id, 'performed_at' => now()]);

            return $c->fresh(['booking.room', 'booking.occurrences']);
        });
    }

    public function assignImported(User $actor, SidangTaRegistration $lockedReg, SidangTaSchedule $lockedContext, ?SidangTaExecutionSetting $setting, Room $room, array $slot): void
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        $this->assertReady($lockedReg, $lockedContext, $setting);
        if ($lockedContext->booking || $lockedContext->was_confirmed) {
            throw ValidationException::withMessages(['schedule' => 'Jadwal yang sudah ditetapkan dipertahankan.']);
        }
        $date = $slot['date'];
        app(SidangTaExecutionWindowService::class)->assertStaffSlot($setting, $date, $slot['start_time'], $slot['end_time']);
        if (! app(RoomAvailabilityService::class)->coverage($date, $date)['ready']) {
            throw ValidationException::withMessages(['room_id' => 'Sumber jadwal semester belum lengkap untuk tanggal ini.']);
        }
        if (! $room->isBookableClassroom() && ! $this->importedLab($lockedContext, $slot)?->is($room)) {
            throw ValidationException::withMessages(['room_id' => 'Ruang dari Excel tidak dapat dipakai untuk jadwal ini.']);
        }
        if (app(SidangTaSessionAvailability::class)->freeRooms(collect([$room]), $lockedReg, $lockedContext->taType(), $slot)->isEmpty()) {
            throw ValidationException::withMessages(['room_id' => 'Ruang '.$room->room_code.' bentrok atau tidak tersedia pada waktu tersebut.']);
        }

        $candidate = $this->candidate($room, $date, W::minutes($slot['start_time']), W::minutes($slot['end_time']));
        $note = 'Jadwal ditetapkan dari impor plotting.';
        $this->createBooking($actor, $lockedReg, $lockedContext, $candidate, $note);
        app(SidangTaExecutionInfoService::class)->capture($lockedReg, refreshSchedule: true);
        $lockedContext->executionLogs()->create(['action' => 'scheduled', 'notes' => $note, 'data' => $candidate,
            'performed_by' => $actor->id, 'performed_at' => now()]);
    }

    private function createBooking(User $actor, SidangTaRegistration $registration, SidangTaSchedule $context, array $candidate, string $note): void
    {
        $future = $candidate['date'].' '.$candidate['end_time'] > now('Asia/Jakarta')->format('Y-m-d H:i:s');
        if ($future) {
            app(SidangTaBookingService::class)->assertNoOtherActive($registration, $context);
        }
        $booking = RoomBookingRequest::create(['user_id' => $actor->id, 'room_id' => $candidate['room_id'],
            'type' => 'single', 'start_date' => $candidate['date'], 'end_date' => $candidate['date'],
            'start_time' => $candidate['start_time'], 'end_time' => $candidate['end_time'],
            'purpose' => 'Sidang TA '.$registration->student_number, 'participants' => 1, 'status' => 'approved',
            'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $registration->id]);
        $booking->occurrences()->create(['usage_date' => $candidate['date'],
            'start_time' => $candidate['start_time'], 'end_time' => $candidate['end_time']]);
        app(RoomBookingTransitionService::class)->record($actor, $booking, 'pending', $note, []);
        $context->fill(['room_booking_request_id' => $booking->id, 'active_student_number' => $future ? $registration->student_number : null,
            'mode' => 'automatic', 'state' => $future ? 'confirmed' : 'completed', 'was_confirmed' => true, 'lock_version' => $context->lock_version + 1])->save();
    }
}
