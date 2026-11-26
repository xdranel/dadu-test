<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Models\SidangTaExecutionSetting;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaSchedule;
use App\Models\User;
use App\Support\RoomTimeWindows as W;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SidangTaBookingAdministration
{
    private function context(User $actor, SidangTaRegistration $reg, int $version, string $reason, \Closure $action, bool $plotting = false): SidangTaSchedule
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        if (trim($reason) === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Isi alasan perubahan, maksimal 2000 karakter.']);
        }

        return app(SidangTaBookingLock::class)->run($reg->id, [], function ($r, $c, $s) use ($actor, $version, $reason, $action, $plotting) {
            abort_unless(($c->wasRecentlyCreated ? 0 : $c->lock_version) === $version, 409, 'Jadwal berubah. Muat ulang.');
            if ($r->status !== 'approved') {
                throw ValidationException::withMessages(['schedule' => 'Pengajuan belum disetujui.']);
            }
            if ($c->result_status !== 'pending') {
                throw ValidationException::withMessages(['schedule' => 'Batalkan hasil sidang dahulu sebelum mengubah jadwal.']);
            }
            $result = $action($r, $c, $s);
            if ($result->lock_version === $version) {
                $result->increment('lock_version');
            }
            if ($result->booking?->status === 'approved' && in_array($result->state, ['confirmed', 'completed'], true)) {
                app(SidangTaExecutionInfoService::class)->capture($r, refreshSchedule: true, preserveParticipant: true);
            }
            $r->logAction('schedule_updated', $r->status, $r->status, $plotting ? 'Data plotting jadwal diperbarui.' : $reason, $actor->id);

            return $result->fresh('booking.occurrences', 'booking.room');
        });
    }

    public function markLecturer(User $actor, SidangTaRegistration $registration, int $version, string $reason): SidangTaSchedule
    {
        return $this->context($actor, $registration, $version, $reason, function ($r, $c) {
            if ($c->booking && in_array($c->booking->status, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages(['schedule' => 'Selesaikan/cancel booking aktif terlebih dahulu melalui SSC.']);
            }
            app(SidangTaBookingService::class)->assertNoOtherActive($r, $c);
            $c->fill(['mode' => 'lecturer', 'state' => 'awaiting_lecturer', 'active_student_number' => $r->student_number, 'lock_version' => $c->lock_version + 1])->save();

            return $c;
        });
    }

    public function linkLecturerBooking(User $actor, SidangTaRegistration $registration, RoomBookingRequest $booking, int $version, string $reason): SidangTaSchedule
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor, $booking);

        return $this->context($actor, $registration, $version, $reason, function ($r, $c, $s) use ($actor, $booking, $reason) {
            if ($c->mode !== 'lecturer' || ($c->booking && in_array($c->booking->status, ['pending', 'approved'], true))) {
                throw ValidationException::withMessages(['schedule' => 'Tandai mode dosen terlebih dahulu dan selesaikan booking aktif.']);
            }
            $b = RoomBookingRequest::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $b->setRelation('occurrences', $b->occurrences()->lockForUpdate()->get());
            if ($b->booking_origin !== 'ordinary' || $b->sidang_ta_registration_id || SidangTaSchedule::where('room_booking_request_id', $b->id)->exists() || ! in_array($b->status, ['pending', 'approved'], true) || $b->type !== 'single' || $b->occurrences->count() !== 1) {
                throw ValidationException::withMessages(['booking_id' => 'Pilih peminjaman dosen aktif, sekali pakai, satu tanggal, dan belum tertaut.']);
            }
            $o = $b->occurrences->first();
            $this->validateSlot($s, ['date' => $o->usage_date->toDateString(), 'room_id' => $b->room_id, 'start_time' => substr($o->start_time, 0, 5), 'end_time' => substr($o->end_time, 0, 5)], $b->id);
            app(SidangTaBookingService::class)->assertNoOtherActive($r, $c);
            $before = $b->getRawOriginal();
            $b->sidang_ta_registration_id = $r->id;
            $b->save();
            $c->fill(['room_booking_request_id' => $b->id, 'active_student_number' => $r->student_number, 'was_confirmed' => $c->was_confirmed || $b->status === 'approved', 'state' => $b->status === 'approved' ? 'confirmed' : 'pending_lecturer', 'lock_version' => $c->lock_version + 1])->save();
            app(RoomBookingTransitionService::class)->record($actor, $b, $b->status, $reason, $before);

            return $c->fresh();
        });
    }

    private function validateSlot(?SidangTaExecutionSetting $s, array $slot, ?int $except = null, bool $staff = false, bool $allowImportedLab = false): array
    {
        $v = Validator::make($slot, ['date' => ['required', 'date_format:Y-m-d'], 'room_id' => ['required', 'integer', 'exists:rooms,id'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time']])->validate();
        $room = Room::findOrFail($v['room_id']);
        $usableLab = $allowImportedLab && $room->label === 'LAB' && $room->status === 'Dapat Dipakai';
        if (! $s || ! $s->is_active || (! $room->isBookableClassroom() && ! $usableLab)) {
            throw ValidationException::withMessages(['room_id' => 'Pilih ruang yang dapat dipakai dan pastikan jadwal pelaksanaan aktif.']);
        }
        $window = app(SidangTaExecutionWindowService::class);
        if ($staff) {
            $window->assertStaffSlot($s, $v['date'], $v['start_time'], $v['end_time']);
        } else {
            $window->assertSlot($s, $v['date'], $v['start_time'], $v['end_time']);
            if (now('Asia/Jakarta')->format('Y-m-d H:i:s') >= $v['date'].' '.$v['start_time'].':00') {
                throw ValidationException::withMessages(['date' => 'Pilih waktu pelaksanaan yang belum dimulai.']);
            }
        }
        $service = app(RoomAvailabilityService::class);
        if (! $service->coverage($v['date'], $v['date'])['ready']) {
            throw ValidationException::withMessages(['date' => 'Sumber semester belum lengkap.']);
        }
        foreach ($service->busyIntervals($room->id, $v['date'], $except) as $busy) {
            if ($busy['start'] < W::minutes($v['end_time']) && $busy['end'] > W::minutes($v['start_time'])) {
                throw ValidationException::withMessages(['room_id' => 'Ruangan bentrok pada waktu tersebut.']);
            }
        }

        return $v;
    }

    public function reschedule(User $actor, SidangTaRegistration $registration, array $slot, int $version, string $reason, bool $allowImportedLab = false, bool $allowElapsedBooking = false): SidangTaSchedule
    {
        return $this->context($actor, $registration, $version, $reason, function ($r, $c, $s) use ($actor, $slot, $reason, $allowImportedLab, $allowElapsedBooking) {
            $b = $c->booking;
            if (! $b || ! in_array($b->status, ['pending', 'approved'], true)) {
                if (! $c->was_confirmed) {
                    throw ValidationException::withMessages(['schedule' => 'Tidak ada booking untuk dipindahkan.']);
                }
                $v = $this->validateSlot($s, $slot, staff: true, allowImportedLab: $allowImportedLab);
                app(SidangTaBookingService::class)->assertNoOtherActive($r, $c);
                $replacement = RoomBookingRequest::create(['user_id' => $actor->id, 'room_id' => $v['room_id'], 'type' => 'single', 'start_date' => $v['date'], 'end_date' => $v['date'], 'start_time' => $v['start_time'], 'end_time' => $v['end_time'], 'purpose' => 'Pengganti Sidang TA #'.$r->id, 'participants' => 1, 'status' => 'approved', 'booking_origin' => 'sidang_ta', 'sidang_ta_registration_id' => $r->id]);
                $replacement->occurrences()->create(['usage_date' => $v['date'], 'start_time' => $v['start_time'], 'end_time' => $v['end_time']]);
                $c->fill(['room_booking_request_id' => $replacement->id, 'active_student_number' => $r->student_number, 'state' => 'confirmed', 'lock_version' => $c->lock_version + 1])->save();
                app(RoomBookingTransitionService::class)->record($actor, $replacement, 'pending', $reason, ['replaces_booking_id' => $b?->id]);

                return $c->fresh();
            }
            app(SidangTaBookingAccess::class)->authorizeStaff($actor, $b);
            if (! $allowElapsedBooking) {
                $this->futureBooking($b);
            } elseif ($slot['date'].' '.$slot['end_time'].':00' > now('Asia/Jakarta')->format('Y-m-d H:i:s')) {
                app(SidangTaBookingService::class)->assertNoOtherActive($r, $c);
            }
            $this->moveBooking($actor, $b, $s, $slot, $reason, $allowImportedLab);

            return $c->fresh();
        });
    }

    public function editPlotting(User $actor, SidangTaRegistration $registration, array $data): SidangTaSchedule
    {
        $reason = trim($data['reason'] ?? '');

        return $this->context($actor, $registration, (int) $data['context_version'], $reason ?: 'Data plotting jadwal diperbarui.', function ($r, $c, $s) use ($actor, $data, $reason) {
            if (! $s?->is_active) {
                throw ValidationException::withMessages(['date' => 'Aktifkan jadwal pelaksanaan sebelum mengedit plotting.']);
            }
            $booking = $c->booking;
            if ($booking?->status === 'pending' || ($booking?->status === 'approved' && $booking->occurrences->count() !== 1)) {
                throw ValidationException::withMessages(['schedule' => 'Selesaikan peminjaman yang tertaut sebelum mengedit plotting.']);
            }
            $confirmed = $booking?->status === 'approved';
            if (! $confirmed && ! app(SidangTaBookingService::class)->isLatestApproved($r)) {
                throw ValidationException::withMessages(['schedule' => 'Gunakan pengajuan terbaru yang sudah disetujui.']);
            }
            $occurrence = $confirmed ? $booking->occurrences->first() : null;
            $slot = ['date' => $data['date'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'room_id' => isset($data['room_id']) ? (int) $data['room_id'] : null];
            $preferred = ['date' => $c->preferred_date?->toDateString(), 'start_time' => substr((string) $c->preferred_start_time, 0, 5), 'end_time' => substr((string) $c->preferred_end_time, 0, 5), 'room_id' => $c->preferred_room_id];
            $current = $confirmed ? ['date' => $occurrence->usage_date->toDateString(), 'start_time' => substr($occurrence->start_time, 0, 5), 'end_time' => substr($occurrence->end_time, 0, 5), 'room_id' => $booking->room_id] : $preferred;

            if ($slot !== $current || ! $confirmed) {
                app(SidangTaExecutionWindowService::class)->assertStaffSlot($s, $slot['date'], $slot['start_time'], $slot['end_time']);
                $room = $slot['room_id'] ? Room::findOrFail($slot['room_id']) : null;
                $importedLab = $c->planning_source === 'import' && $slot === $preferred && $room?->label === 'LAB' && $room->status === 'Dapat Dipakai';
                if (($confirmed || $room) && ! $room?->isBookableClassroom() && ! $importedLab) {
                    throw ValidationException::withMessages(['room_id' => 'Pilih ruang kelas yang dapat dipakai. Penetapan LAB mengikuti tanggal dan jam dari impor Excel.']);
                }
                if ($confirmed) {
                    if ($reason === '') {
                        throw ValidationException::withMessages(['reason' => 'Isi alasan perubahan tanggal, jam, atau ruang.']);
                    }
                    app(SidangTaBookingAccess::class)->authorizeStaff($actor, $booking);
                    if ($slot['date'].' '.$slot['end_time'].':00' > now('Asia/Jakarta')->format('Y-m-d H:i:s')) {
                        app(SidangTaBookingService::class)->assertNoOtherActive($r, $c);
                    }
                    $this->moveBooking($actor, $booking, $s, $slot, $reason, $importedLab);
                    $c = $c->fresh();
                }
            }

            $participant = array_replace($c->participant_data ?? [], [
                'examiner_1_code' => $data['examiner_1_code'] ?? '',
                'examiner_2_code' => $data['examiner_2_code'] ?? '',
            ]);
            $c->fill(['participant_data' => $participant, 'preferred_date' => $slot['date'], 'preferred_start_time' => $slot['start_time'].':00',
                'preferred_end_time' => $slot['end_time'].':00', 'preferred_room_id' => $slot['room_id'],
                'planning_source' => $slot === $preferred ? $c->planning_source : 'system'])->save();
            $c->executionLogs()->create(['action' => 'plotting_updated', 'from_status' => $c->state, 'to_status' => $c->state,
                'notes' => $reason ?: null, 'data' => ['previous' => $current, 'updated' => $slot, 'examiner_1_code' => $participant['examiner_1_code'], 'examiner_2_code' => $participant['examiner_2_code']],
                'performed_by' => $actor->id, 'performed_at' => now()]);

            return $c;
        }, plotting: true);
    }

    private function moveBooking(User $actor, RoomBookingRequest $booking, ?SidangTaExecutionSetting $setting, array $slot, string $reason, bool $allowImportedLab = false): void
    {
        $v = $this->validateSlot($setting, $slot, $booking->id, staff: true, allowImportedLab: $allowImportedLab);
        $before = $booking->getRawOriginal() + ['occurrences' => $booking->occurrences->toArray()];
        $booking->fill(['room_id' => $v['room_id'], 'start_date' => $v['date'], 'end_date' => $v['date'], 'start_time' => $v['start_time'], 'end_time' => $v['end_time']])->save();
        $booking->occurrences()->delete();
        $booking->occurrences()->create(['usage_date' => $v['date'], 'start_time' => $v['start_time'], 'end_time' => $v['end_time']]);
        $booking->unsetRelation('occurrences');
        app(RoomBookingTransitionService::class)->record($actor, $booking, $booking->status, $reason, $before);
    }

    private function futureBooking(RoomBookingRequest $b): void
    {
        $o = $b->occurrences->sortBy(fn ($v) => $v->usage_date->toDateString().' '.$v->start_time)->first();
        if (! $o || $o->usage_date->toDateString().' '.$o->start_time <= now()->format('Y-m-d H:i:s')) {
            throw ValidationException::withMessages(['schedule' => 'Pelaksanaan sudah dimulai. Jadwal historis dipertahankan.']);
        }
    }

    public function cancel(User $actor, SidangTaRegistration $registration, int $version, string $reason): SidangTaSchedule
    {
        return $this->context($actor, $registration, $version, $reason, function ($r, $c) use ($actor, $reason) {
            $b = $c->booking;
            if (! $b || ! in_array($b->status, ['pending', 'approved'], true)) {
                if ($c->mode === 'lecturer' && $c->active_student_number) {
                    $c->update(['active_student_number' => null, 'state' => 'cancelled', 'lock_version' => $c->lock_version + 1]);

                    return $c;
                }throw ValidationException::withMessages(['schedule' => 'Tidak ada booking aktif.']);
            }
            app(SidangTaBookingAccess::class)->authorizeStaff($actor, $b);
            $this->futureBooking($b);
            $before = $b->getRawOriginal();
            $from = $b->status;
            $b->fill(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_by' => $actor->id, 'cancelled_at' => now()])->save();
            app(RoomBookingTransitionService::class)->record($actor, $b, $from, $reason, $before);

            return $c->fresh();
        });
    }
}
