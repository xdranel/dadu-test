<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoomBookingRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'cancelled_at' => 'datetime'];

    public function scopeAwaitingUse(Builder $query): Builder
    {
        $now = now('Asia/Jakarta');
        $endsLater = fn (Builder $q, string $dateColumn) => $q
            ->whereDate($dateColumn, '>', $now->toDateString())
            ->orWhere(fn (Builder $sameDay) => $sameDay->whereDate($dateColumn, $now->toDateString())
                ->whereTime('end_time', '>', $now->toTimeString()));

        return $query->where(fn (Builder $q) => $q
            ->whereHas('occurrences', fn (Builder $occurrences) => $endsLater($occurrences, 'usage_date'))
            ->orWhere(fn (Builder $legacy) => $legacy->doesntHave('occurrences')
                ->where(fn (Builder $dates) => $endsLater($dates, 'end_date'))));
    }

    public function scopeLastUseInMonth(Builder $query, string $first, string $last): Builder
    {
        $lastOccurrence = RoomBookingOccurrence::query()->selectRaw('max(usage_date)')
            ->whereColumn('request_id', 'room_booking_requests.id');

        return $query->whereRaw('date(coalesce(('.$lastOccurrence->toSql().'), room_booking_requests.end_date)) between ? and ?', [$first, $last]);
    }

    public function registration()
    {
        return $this->belongsTo(SidangTaRegistration::class, 'sidang_ta_registration_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function occurrences()
    {
        return $this->hasMany(RoomBookingOccurrence::class, 'request_id')->orderBy('usage_date');
    }

    public function histories()
    {
        return $this->hasMany(RoomBookingHistory::class, 'request_id')->latest();
    }
}
