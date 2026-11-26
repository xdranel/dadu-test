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

    private function candidates(SidangTaExecutionSetting $setting, ?SidangTaSchedule $context, array $slot, bool $importedLab): array
    {
        $date = $slot['date'];
        $start = W::minutes($slot['start_time']);
        $end = W::minutes($slot['end_time']);
        app(SidangTaExecutionWindowService::class)->assertStaffSlot($setting, $date, $slot['start_time'], $slot['end_time']);
        $rooms = $this->roomsForSearch($context, $slot, $importedLab);
        $availability = app(RoomAvailabilityService::class);
        if (! $availability->coverage($date, $date)['ready']) {
            return [];
        }
        $booking = $context?->booking;
        $except = $booking && in_array($booking->status, ['pending', 'approved'], true) ? $booking->id : null;
        $busy = $except ? $availability->rangeExcept($date, $date, $except) : $availability->forRange($date, $date);

        return $rooms->filter(fn (Room $room) => ! collect($busy[$date][$room->id] ?? [])
            ->contains(fn ($interval) => $interval['start'] < $end && $interval['end'] > $start))
            ->sortBy(fn (Room $room) => [
                ! preg_match('/\bGEDUNG[\s-]+A\b/i', $room->building ?? ''),
                $room->id !== (int) $context?->preferred_room_id,
                $room->room_code,
                $room->id,
            ])
            ->map(fn (Room $room) => [
                'date' => $date, 'room_id' => $room->id, 'room_code' => $room->room_code, 'room_name' => $room->name,
                'start_time' => W::time($start), 'end_time' => W::time($end), 'duration_minutes' => $end - $start,
            ])->values()->all();
    }

    private function roomsForSearch(?SidangTaSchedule $context, array $slot, bool $importedLab): Collection
    {
        if (! $importedLab) {
            return Room::bookableClassrooms()->get();
        }

        $room = Room::where('label', 'LAB')->where('status', 'Dapat Dipakai')->find($context?->preferred_room_id);
        if (! $room || $context->planning_source !== 'import' || $context->preferred_date?->toDateString() !== $slot['date']
            || substr((string) $context->preferred_start_time, 0, 5) !== $slot['start_time']
            || substr((string) $context->preferred_end_time, 0, 5) !== $slot['end_time']) {
            throw ValidationException::withMessages(['room_id' => 'Ruang LAB harus dapat dipakai dan menggunakan tanggal serta jam dari impor Excel.']);
        }

        return collect([$room]);
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
        foreach ($this->candidates($s, $c, $slot, $importedLab) as $i => $candidate) {
            $options[] = ['candidate' => $candidate, 'recommended' => $i === 0,
                'token' => Crypt::encryptString(json_encode(['stamp' => $stamp, 'candidate' => $candidate, 'imported_lab' => $importedLab,
                    'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR))];
        }

        return ['options' => $options, 'slot' => $slot, 'context_version' => $c?->lock_version ?? 0,
            'reason' => $options ? null : $this->emptySearchReason($date, $importedLab)];
    }

    private function emptySearchReason(string $date, bool $importedLab): string
    {
        $reasons = [];
        $rooms = Room::bookableClassrooms();
        if (! $importedLab && ! $rooms->exists()) {
            $reasons[] = 'Tidak ada ruang berlabel KELAS dan berstatus Dapat Dipakai. Periksa master ruang pada Data Akademik → Ruangan.';
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
            : 'Tidak ada ruang kelas yang tersedia pada rentang jam yang dipilih.');
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
            $available = in_array($candidate, $this->candidates($s, $c, $slot, $importedLab), true);
            abort_unless($available, 409, 'Slot sudah berubah atau terpakai. Cari jadwal kembali.');
            $activeBooking = $c->booking && in_array($c->booking->status, ['pending', 'approved'], true);
            if ($c->was_confirmed || $activeBooking) {
                $c = app(SidangTaBookingAdministration::class)->reschedule($actor, $r, $slot + ['room_id' => $candidate['room_id']], $c->lock_version, 'Penyesuaian plotting oleh admin.', allowImportedLab: $importedLab, allowElapsedBooking: true);
                if ($c->booking->status === 'pending') {
                    app(RoomBookingTransitionService::class)->decide($actor, $c->booking, 'approved', 'Jadwal ditetapkan admin.', allowImportedLab: $importedLab);
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
            if ($candidate['date'].' '.$candidate['end_time'] <= now('Asia/Jakarta')->format('Y-m-d H:i:s')) {
                $c->fill(['state' => 'completed', 'active_student_number' => null])->save();
            }
            app(SidangTaExecutionInfoService::class)->capture($r, refreshSchedule: true, preserveParticipant: true);
            $c->executionLogs()->create(['action' => 'scheduled', 'notes' => 'Jadwal ditetapkan admin.',
                'data' => $candidate, 'performed_by' => $actor->id, 'performed_at' => now()]);

            return $c->fresh(['booking.room', 'booking.occurrences']);
        });
    }
}
