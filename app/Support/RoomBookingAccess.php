<?php

namespace App\Support;

use App\Models\User;

final class RoomBookingAccess
{
    /** Eligibility only; each action must still check its own permission. */
    public static function canBorrow(User $user): bool
    {
        if ($user->hasAnyRole(['Akademik', 'admin'])) {
            return true;
        }

        return $user->hasRole('Pelaksana') && ($user->isSsoAccount()
            || (app()->environment(['local', 'testing']) && $user->isManualAccount()));
    }
}
