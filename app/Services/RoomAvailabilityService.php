<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\RoomWeeklyPattern;
use App\Support\RoomTimeWindows;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomAvailabilityService
{
    private function dates(string $from, string $until): array
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'Asia/Jakarta');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $until, 'Asia/Jakarta');
            if ($start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $until || $start->gt($end) || $start->diffInDays($end) > 365) {
                throw new \InvalidArgumentException;
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['date' => 'Tanggal/rentang tidak valid; maksimal 366 hari.']);
        }
        $dates = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $dates[$d->toDateString()] = $d;
        }

        return $dates;
    }

    public function coverage(string $from, string $until): array
    {
        $dates = $this->dates($from, $until);
        $periods = AcademicPeriod::with('roomSource')->whereDate('start_date', '<=', $until)->whereDate('end_date', '>=', $from)->get();
        $missing = [];
        $versions = [];
        foreach ($periods as $p) {
            $versions[$p->id] = ['version' => $p->roomSource?->version, 'fingerprint' => $p->roomSource?->fingerprint, 'start_date' => $p->start_date->toDateString(), 'end_date' => $p->end_date->toDateString()];
        }
        foreach ($dates as $key => $d) {
            $covering = $periods->filter(fn ($p) => $key >= $p->start_date->toDateString() && $key <= $p->end_date->toDateString());
            if ($covering->isEmpty() || $covering->contains(fn ($p) => ! $p->roomSource?->is_complete || $p->roomSource?->scope !== 'semester')) {
                $missing[] = $key;
            }
        }
        ksort($versions);

        return ['ready' => $missing === [], 'missing_dates' => $missing, 'source_versions' => $versions];
    }

    public function missingCoverageDates(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $missing = $this->coverage(min($dates), max($dates))['missing_dates'];

        return array_values(array_intersect($missing, $dates));
    }

    public function busyIntervals(int $roomId, string $date, ?int $exceptBookingId = null): array
    {
        return $this->intervals($date, $date, $exceptBookingId)[$date][$roomId] ?? [];
    }

    public function rangeExcept(string $from, string $until, int $bookingId): array
    {
        return $this->intervals($from, $until, $bookingId);
    }

    public function forRange(string $from, string $until): array
    {
        return $this->intervals($from, $until);
    }

    public function freeRooms(Collection $rooms, string $date, string $from, string $until, ?int $exceptBookingId = null): Collection
    {
        $busyByRoom = $this->intervals($date, $date, $exceptBookingId)[$date];
        $start = RoomTimeWindows::minutes($from);
        $end = RoomTimeWindows::minutes($until, true);

        return $rooms->reject(fn ($room) => collect($busyByRoom[$room->id] ?? [])
            ->contains(fn ($period) => $period['start'] < $end && $period['end'] > $start));
    }

    public function teachingOccurrences(string $date, ?int $roomId = null): array
    {
        $days = $this->dates($date, $date);
        $weekday = $days[$date]->dayOfWeekIso;
        $weekly = DB::table('room_weekly_patterns as p')->join('academic_periods as a', 'a.id', '=', 'p.academic_period_id')->join('rooms as r', 'r.id', '=', 'p.room_id')
            ->where('p.weekday', $weekday)->whereDate('a.start_date', '<=', $date)->whereDate('a.end_date', '>=', $date)
            ->when($roomId !== null, fn ($query) => $query->where('p.room_id', $roomId))
            ->get(['r.id as room_id', 'r.name as room_name', 'r.room_code', 'p.start_time', 'p.end_time', 'p.description', 'p.lecturer_code']);
        $dated = DB::table('room_schedules as p')->join('rooms as r', 'r.id', '=', 'p.room_id')->whereDate('p.usage_date', $date)
            ->when($roomId !== null, fn ($query) => $query->where('p.room_id', $roomId))
            ->get(['r.id as room_id', 'r.name as room_name', 'r.room_code', 'p.start_time', 'p.end_time', 'p.description', 'p.lecturer_code']);

        return $weekly->concat($dated)->unique(fn ($s) => json_encode([$s->room_id, $s->start_time, $s->end_time, $s->description, $s->lecturer_code]))->values()->all();
    }

    public function conflictingBookings(array $patterns, string $from, string $until): array
    {
        $this->dates($from, $until);
        $out = [];
        $bookings = DB::table('room_booking_occurrences as o')->join('room_booking_requests as r', 'r.id', '=', 'o.request_id')
            ->whereIn('r.status', ['pending', 'approved'])->whereDate('o.usage_date', '>=', $from)->whereDate('o.usage_date', '<=', $until)
            ->get(['r.id', 'r.room_id', 'o.usage_date', 'o.start_time', 'o.end_time']);
        foreach ($bookings as $b) {
            $date = CarbonImmutable::parse($b->usage_date, 'Asia/Jakarta');
            foreach ($patterns as $p) {
                if ((int) $p['room_id'] === (int) $b->room_id && (int) $p['weekday'] === $date->dayOfWeekIso && RoomTimeWindows::minutes($p['start_time']) < RoomTimeWindows::minutes($b->end_time, true) && RoomTimeWindows::minutes($p['end_time'], true) > RoomTimeWindows::minutes($b->start_time)) {
                    $out[$b->id.'|'.$date->toDateString()] = 'peminjaman #'.$b->id.' ruang #'.$b->room_id.' '.$date->toDateString();
                    break;
                }
            }
        }

        return array_values($out);
    }

    private function intervals(string $from, string $until, ?int $exceptBookingId = null): array
    {
        $dates = $this->dates($from, $until);
        $out = array_fill_keys(array_keys($dates), []);
        $append = static function (array &$day, int $room, string $start, string $end): void {
            $day[$room][] = ['start' => RoomTimeWindows::minutes($start), 'end' => RoomTimeWindows::minutes($end, true)];
        };
        $patterns = RoomWeeklyPattern::with('academicPeriod')->whereHas('academicPeriod', fn ($q) => $q->whereDate('start_date', '<=', $until)->whereDate('end_date', '>=', $from))->get();
        foreach ($patterns as $p) {
            $period = $p->academicPeriod;
            foreach ($dates as $key => $date) {
                if ($date->dayOfWeekIso === $p->weekday && $key >= $period->start_date->toDateString() && $key <= $period->end_date->toDateString()) {
                    $append($out[$key], $p->room_id, $p->start_time, $p->end_time);
                }
            }
        }
        foreach (DB::table('room_schedules')->whereDate('usage_date', '>=', $from)->whereDate('usage_date', '<=', $until)->get() as $s) {
            $key = substr($s->usage_date, 0, 10);
            $append($out[$key], $s->room_id, $s->start_time, $s->end_time);
        }
        $internal = DB::table('room_booking_occurrences as o')->join('room_booking_requests as r', 'r.id', '=', 'o.request_id')
            ->whereIn('r.status', ['pending', 'approved'])->whereDate('o.usage_date', '>=', $from)->whereDate('o.usage_date', '<=', $until)
            ->when($exceptBookingId, fn ($q) => $q->where('r.id', '!=', $exceptBookingId))->get(['o.usage_date', 'r.room_id', 'o.start_time', 'o.end_time']);
        foreach ($internal as $s) {
            $key = substr($s->usage_date,0,10);
            $append($out[$key],$s->room_id,$s->start_time,$s->end_time);
        }
        foreach ($out as &$rooms) {
            foreach ($rooms as &$slots) {
                $slots = RoomTimeWindows::merge($slots);
            }unset($slots);
        }unset($rooms);

        return $out;
    }
}
