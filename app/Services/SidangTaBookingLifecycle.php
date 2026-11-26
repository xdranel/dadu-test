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
        $booked = in_array($lockedBooking->status, ['pending', 'approved'], true);
        if (! $booked && ($context->result_status ?? 'pending') !== 'pending') {
            throw ValidationException::withMessages(['schedule' => 'Batalkan hasil sidang dahulu sebelum membatalkan jadwal.']);
        }
        $occurrences = $lockedBooking->occurrences;
        $completed = $lockedBooking->status === 'approved' && $occurrences->isNotEmpty()
            && $occurrences->every(fn ($o) => $o->usage_date->toDateString().' '.$o->end_time <= now('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $context->fill(['state' => match ($lockedBooking->status) {
            'pending' => 'pending_lecturer','approved' => $completed ? 'completed' : 'confirmed','rejected' => 'rejected',default => 'cancelled'
        }, 'active_student_number' => $booked && ! $completed ? $context->student_number : null, 'was_confirmed' => $context->was_confirmed || $lockedBooking->status === 'approved', 'lock_version' => $context->lock_version + 1])->save();
        $lockedBooking->registration->logAction('schedule_updated', $lockedBooking->status, $lockedBooking->status, $note ?: 'Status peminjaman jadwal sidang diperbarui.', $actor->id);
    }

    public function approvalWithdrawalBlocker(SidangTaRegistration $registration): ?string
    {
        $booking = $registration->roomSchedule?->booking;
        if (! $booking || ! in_array($booking->status, ['pending', 'approved'], true)) {
            return null;
        }
        $first = $booking->occurrences->sortBy(fn ($o) => $o->usage_date->toDateString().' '.$o->start_time)->first();

        return $first && $first->usage_date->toDateString().' '.$first->start_time <= now()->format('Y-m-d H:i:s') ? 'Pelaksanaan sidang telah dimulai.' : null;
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
            throw ValidationException::withMessages(['action' => 'Persetujuan pendaftaran tidak dapat dibatalkan setelah sidang dimulai.']);
        }
        $before = $booking->getRawOriginal();
        $from = $booking->status;
        $note = 'Booking dibatalkan karena persetujuan Sidang TA dibatalkan.';
        $booking->fill(['status' => 'cancelled', 'cancellation_reason' => $note, 'cancelled_by' => $actor->id, 'cancelled_at' => now()])->save();
        app(RoomBookingTransitionService::class)->record($actor, $booking, $from, $note, $before);
    }
}
