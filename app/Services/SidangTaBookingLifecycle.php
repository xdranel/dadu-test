<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaSchedule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SidangTaBookingLifecycle
{
    public function afterRoomStatusChanged(RoomBookingRequest $lockedBooking, User $actor, string $fromStatus, ?string $note): void
    {
        if (! $lockedBooking->sidang_ta_registration_id) {
            return;
        }
        $context = SidangTaSchedule::where('sidang_ta_registration_id', $lockedBooking->sidang_ta_registration_id)->first();
        if (! $context || $context->room_booking_request_id !== $lockedBooking->id) {
            return;
        }
        $active = in_array($lockedBooking->status, ['pending', 'approved'], true);
        if (! $active && ($context->result_status ?? 'pending') !== 'pending') {
            throw ValidationException::withMessages(['schedule' => 'Batalkan hasil sidang dahulu sebelum membatalkan jadwal.']);
        }
        $context->fill(['state' => match ($lockedBooking->status) {
            'pending' => 'pending_lecturer','approved' => 'confirmed','rejected' => 'rejected',default => 'cancelled'
        }, 'active_student_number' => $active ? $context->student_number : null, 'was_confirmed' => $context->was_confirmed || $lockedBooking->status === 'approved', 'lock_version' => $context->lock_version + 1])->save();
        $lockedBooking->registration->logAction('schedule_updated', $lockedBooking->status, $lockedBooking->status, $note ?: 'Status peminjaman jadwal sidang diperbarui.', $actor->id);
    }

    public function approvalWithdrawalBlocker(SidangTaRegistration $registration): ?string
    {
        $booking = $registration->roomSchedule?->booking;
        if (! $booking || ! in_array($booking->status, ['pending', 'approved'], true)) {
            return null;
        }
        $first = $booking->occurrences->sortBy(fn ($o) => $o->usage_date->toDateString().' '.$o->start_time)->first();

        return $first && $first->usage_date->toDateString().' '.$first->start_time <= now()->format('Y-m-d H:i:s') ? 'Pelaksanaan sidang telah dimulai. Hubungi SSC untuk pemeriksaan jadwal.' : null;
    }

    public function cancelOnApprovalWithdrawal(SidangTaRegistration $lockedRegistration, User $actor): void
    {
        $context = $lockedRegistration->roomSchedule()->lockForUpdate()->first();
        if (! $context) {
            return;
        }
        if (! $context->room_booking_request_id) {
            if ($context->active_student_number !== null) {
                $context->update(['active_student_number' => null, 'state' => 'cancelled', 'lock_version' => $context->lock_version + 1]);
                $lockedRegistration->logAction('schedule_updated', 'approved', 'approved', 'Penjadwalan melalui dosen dibatalkan karena persetujuan TA dibatalkan.', $actor->id);
            }

            return;
        }
        AcademicPeriod::orderBy('id')->lockForUpdate()->get();
        Room::orderBy('id')->lockForUpdate()->get();
        $booking = $context->booking()->lockForUpdate()->firstOrFail();
        $booking->setRelation('occurrences', $booking->occurrences()->lockForUpdate()->get());
        if (! in_array($booking->status, ['pending', 'approved'], true)) {
            return;
        }
        $first = $booking->occurrences->sortBy(fn ($o) => $o->usage_date->toDateString().' '.$o->start_time)->first();
        if ($first && $first->usage_date->toDateString().' '.$first->start_time <= now()->format('Y-m-d H:i:s')) {
            throw ValidationException::withMessages(['action' => 'Sidang telah dimulai. Hubungi SSC untuk pemeriksaan jadwal sebelum pembatalan persetujuan.']);
        }
        $before = $booking->getRawOriginal();
        $from = $booking->status;
        $note = 'Booking dibatalkan karena persetujuan Sidang TA dibatalkan.';
        $booking->fill(['status' => 'cancelled', 'cancellation_reason' => $note, 'cancelled_by' => $actor->id, 'cancelled_at' => now()])->save();
        app(RoomBookingTransitionService::class)->record($actor, $booking, $from, $note, $before);
    }
}
