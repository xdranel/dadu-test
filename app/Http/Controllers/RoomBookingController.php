<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Models\RoomBookingHistory;
use App\Models\RoomBookingRequest as Booking;
use App\Models\RoomScheduleSource;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use App\Services\RoomBookingTransitionService;
use App\Support\RoomBookingAccess;
use App\Support\RoomBookingIdentity;
use App\Support\RoomFloorPlan;
use App\Support\RoomTimeWindows;
use App\Support\WeeklyRoomScheduleNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomBookingController extends Controller
{
    private function isReviewer(User $user): bool
    {
        return $user->hasRole('Akademik') || $user->can('room_booking_review');
    }

    private function canBorrow(User $user): bool
    {
        return RoomBookingAccess::canBorrow($user);
    }

    private function hasSuccessfulImport(): bool
    {
        return RoomScheduleSource::where('is_complete', true)->exists();
    }

    private function validateBookingDateRange(string $startDate, string $endDate): ?string
    {
        $today = now('Asia/Jakarta')->toDateString();
        if ($startDate < $today) {
            return 'Tanggal peminjaman tidak boleh di masa lampau.';
        }

        $activePeriod = AcademicPeriod::current();
        if ($activePeriod && $activePeriod->start_date && $activePeriod->end_date) {
            $periodStart = $activePeriod->start_date->toDateString();
            $periodEnd = $activePeriod->end_date->toDateString();
            if ($startDate < $periodStart || $endDate > $periodEnd) {
                return "Peminjaman hanya dapat diajukan dalam rentang semester aktif berjalan ({$activePeriod->label}: {$periodStart} s.d. {$periodEnd}).";
            }
        }

        return null;
    }

    private function computeFreeSlots(array $bookedSlots, int $windowStartMin = 390, int $windowEndMin = 1290, int $minGapMin = 60): array
    {
        $intervals = [];
        foreach ($bookedSlots as $slot) {
            $from = is_array($slot) ? ($slot['start'] ?? $slot[0] ?? null) : null;
            $to = is_array($slot) ? ($slot['end'] ?? $slot[1] ?? null) : null;
            if (! $from || ! $to) {
                continue;
            }
            $clippedStart = max(RoomTimeWindows::minutes($from), $windowStartMin);
            $clippedEnd = min(RoomTimeWindows::minutes($to, true), $windowEndMin);
            if ($clippedStart < $clippedEnd) {
                $intervals[] = [$clippedStart, $clippedEnd];
            }
        }

        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($intervals as [$s, $e]) {
            if (empty($merged)) {
                $merged[] = [$s, $e];
            } else {
                $last = count($merged) - 1;
                if ($s <= $merged[$last][1]) {
                    $merged[$last][1] = max($merged[$last][1], $e);
                } else {
                    $merged[] = [$s, $e];
                }
            }
        }

        $freeSlots = [];
        $cursor = $windowStartMin;
        foreach ($merged as [$s, $e]) {
            if ($s - $cursor >= $minGapMin) {
                $freeSlots[] = [
                    'start' => sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60),
                    'end' => sprintf('%02d:%02d', intdiv($s, 60), $s % 60),
                ];
            }
            $cursor = max($cursor, $e);
        }
        if ($windowEndMin - $cursor >= $minGapMin) {
            $freeSlots[] = [
                'start' => sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60),
                'end' => sprintf('%02d:%02d', intdiv($windowEndMin, 60), $windowEndMin % 60),
            ];
        }

        return $freeSlots;
    }

    private function hasFreeHour(array $intervals): bool
    {
        return RoomTimeWindows::firstFit($intervals, [['start' => 390, 'end' => 1290]], 60) !== null;
    }

    private function availabilityCalendar(array $busy, array $missingDates, iterable $rooms): array
    {
        $calendar = [];
        foreach ($busy as $date => $byRoom) {
            if (in_array($date, $missingDates, true)) {
                $calendar[$date] = 'unknown';

                continue;
            }
            $available = false;
            foreach ($rooms as $room) {
                if ($this->hasFreeHour($byRoom[$room->id] ?? [])) {
                    $available = true;
                    break;
                }
            }
            $calendar[$date] = $available ? 'available' : 'unavailable';
        }

        return $calendar;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($this->isReviewer($user) && ! $this->canBorrow($user)) {
            return redirect()->route('admin.room-bookings.review');
        }

        abort_unless($this->canBorrow($user) && $user->can('room_booking_read'), 403);

        $bookings = Booking::with(['room', 'user', 'occurrences'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('room-bookings.index', compact('bookings'));
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        if ($this->isReviewer($user) && ! $this->canBorrow($user)) {
            return redirect()->route('admin.room-bookings.review');
        }

        abort_unless($this->canBorrow($user) && $user->can('room_booking_read'), 403);
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m,Y-m-d'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:50'],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', Rule::in(['1', '2'])],
        ]);
        $today = now('Asia/Jakarta')->toDateString();
        $requestedDate = $filters['date'] ?? null;
        $monthKey = substr($filters['month'] ?? $requestedDate ?? $today, 0, 7);
        $month = Carbon::createFromFormat('!Y-m', $monthKey, 'Asia/Jakarta');
        $selectedDate = match (true) {
            $requestedDate !== null && str_starts_with($requestedDate, $monthKey.'-') => $requestedDate,
            str_starts_with($today, $monthKey.'-') => $today,
            default => $month->toDateString(),
        };

        $start = $month->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();
        $availability = app(RoomAvailabilityService::class);
        $busy = $availability->forRange($start, $end);
        $missingDates = $availability->coverage($start, $end)['missing_dates'];
        $plans = collect(RoomFloorPlan::arrange(Room::where('label', Room::LABEL_CLASSROOM)->get()));
        $rooms = $plans->flatMap(fn (array $plan) => array_column($plan['tiles'], 'room'));
        $calendar = $this->availabilityCalendar($busy, $missingDates, $rooms->filter(fn (Room $room) => $room->isBookableClassroom()));
        $buildings = $rooms->pluck('building')->filter()->unique()->sort()->values();
        $floors = $plans->pluck('floor')->unique()->sort()->values();

        $roomFilters = array_filter($request->only('search', 'building', 'floor'), fn ($value) => filled($value));
        $roomKey = WeeklyRoomScheduleNormalizer::roomKey(...);
        $search = $roomKey($roomFilters['search'] ?? '');
        $plans = $plans->filter(fn (array $plan) => ! isset($roomFilters['floor']) || $plan['floor'] === (string) $roomFilters['floor'])
            ->map(function (array $plan) use ($roomFilters, $roomKey, $search) {
                $plan['tiles'] = array_filter($plan['tiles'], fn (array $tile) => str_contains($roomKey($tile['room']->room_code), $search)
                    && (! isset($roomFilters['building']) || $tile['room']->building === $roomFilters['building']));

                return $plan;
            })->filter(fn (array $plan) => $plan['tiles'] !== []);
        $booked = $this->bookingsOnDate($selectedDate)->distinct()->pluck('req.room_id')->flip();
        $sourceReady = ! in_array($selectedDate, $missingDates, true);
        $roomStates = $rooms->mapWithKeys(fn (Room $room) => [$room->id => match (true) {
            ! $room->isBookableClassroom() => 'inactive',
            $sourceReady && ! $this->hasFreeHour($busy[$selectedDate][$room->id] ?? []) => 'full',
            $booked->has($room->id) => 'booked',
            isset($busy[$selectedDate][$room->id]) => 'teaching',
            ! $sourceReady => 'unknown',
            default => 'available',
        }]);

        return view('room-bookings.dashboard', compact(
            'calendar', 'month', 'selectedDate', 'today', 'roomFilters', 'buildings', 'floors', 'plans', 'roomStates',
        ));
    }

    public function create(Request $request)
    {
        abort_unless($this->canBorrow($request->user()) && $request->user()->can('room_booking_create'), 403);
        $rooms = Room::bookableClassrooms()->orderBy('name')->get();
        $hasImported = $this->hasSuccessfulImport();

        return view('room-bookings.create', compact('rooms', 'hasImported'));
    }

    public function checkAvailability(Request $request)
    {
        abort_unless($this->canBorrow($request->user()) && $request->user()->can('room_booking_create'), 403);
        if (! $this->hasSuccessfulImport()) {
            return response()->json(['message' => 'Jadwal iGracias belum disinkronisasi.'], 422);
        }
        $data = $request->validate([
            'start_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:start_at'],
        ]);
        $startAt = Carbon::createFromFormat('Y-m-d\\TH:i', $data['start_at']);
        $endAt = Carbon::createFromFormat('Y-m-d\\TH:i', $data['end_at']);
        $data = ['start_date' => $startAt->toDateString(), 'end_date' => $endAt->toDateString(), 'start_time' => $startAt->format('H:i'), 'end_time' => $endAt->format('H:i')];
        if ($dateError = $this->validateBookingDateRange($data['start_date'], $data['end_date'])) {
            return response()->json(['message' => $dateError], 422);
        }
        if ($data['start_time'] < '06:30' || $data['end_time'] > '21:30' || $data['start_time'] >= $data['end_time']) {
            return response()->json(['message' => 'Jam penggunaan harus berada pada rentang 06.30–21.30 WIB.'], 422);
        }
        if (! app(RoomAvailabilityService::class)->coverage($data['start_date'], $data['end_date'])['ready']) {
            throw ValidationException::withMessages(['availability' => 'Sumber jadwal semester belum lengkap untuk tanggal tersebut.']);
        }
        $dates = [];
        for ($day = Carbon::parse($data['start_date']); $day->lte(Carbon::parse($data['end_date'])); $day->addDay()) {
            $dates[] = $day->toDateString();
        }
        $rooms = Room::bookableClassrooms()->orderBy('name')->get();
        $busy = app(RoomAvailabilityService::class)->forRange($data['start_date'], $data['end_date']);
        $available = $rooms->filter(function ($room) use ($dates, $data, $busy) {
            foreach ($dates as $date) {
                foreach ($busy[$date][$room->id] ?? [] as $slot) {
                    if ($slot['start'] < RoomTimeWindows::minutes($data['end_time'], true) && $slot['end'] > RoomTimeWindows::minutes($data['start_time'])) {
                        return false;
                    }
                }
            }

            return true;
        })->values()->map(fn ($room) => ['id' => $room->id, 'name' => $room->name, 'code' => $room->room_code, 'building' => $room->building, 'capacity' => $room->capacity]);

        return response()->json(['rooms' => $available]);
    }

    /** Half-open interval comparison: touching endpoints are allowed. */
    private function conflicts(int $roomId, string $date, string $start, string $end, ?int $except = null): array
    {
        foreach (app(RoomAvailabilityService::class)->busyIntervals($roomId, $date, $except) as $slot) {
            if ($slot['start'] < RoomTimeWindows::minutes($end, true) && $slot['end'] > RoomTimeWindows::minutes($start)) {
                return ['Pemakaian ruang bentrok pada '.$date];
            }
        }

        return [];
    }

    public function store(Request $request)
    {
        abort_unless($this->canBorrow($request->user()) && $request->user()->can('room_booking_create'), 403);
        if (! $this->hasSuccessfulImport()) {
            return back()->withInput()->withErrors([
                'availability' => 'Jadwal iGracias belum berhasil disinkronisasi. Pengajuan belum dapat dikirim.',
            ]);
        }

        $input = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'start_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:start_at'],
            'purpose' => ['required', 'string', 'max:255'],
        ]);
        $startAt = Carbon::createFromFormat('Y-m-d\\TH:i', $input['start_at']);
        $endAt = Carbon::createFromFormat('Y-m-d\\TH:i', $input['end_at']);
        $data = [
            'type' => $startAt->isSameDay($endAt) ? 'single' : 'recurring',
            'room_id' => $input['room_id'], 'start_date' => $startAt->toDateString(), 'end_date' => $endAt->toDateString(),
            'start_time' => $startAt->format('H:i'), 'end_time' => $endAt->format('H:i'),
            'purpose' => $input['purpose'], 'participants' => 0,
        ];
        if ($dateError = $this->validateBookingDateRange($data['start_date'], $data['end_date'])) {
            return back()->withInput()->withErrors(['start_at' => $dateError]);
        }
        if ($data['start_time'] < '06:30' || $data['end_time'] > '21:30' || $data['start_time'] >= $data['end_time']) {
            return back()->withInput()->withErrors(['start_time' => 'Jam peminjaman harus berada pada rentang 06.30–21.30 WIB.']);
        }

        if (! app(RoomAvailabilityService::class)->coverage($data['start_date'], $data['end_date'])['ready']) {
            throw ValidationException::withMessages(['availability' => 'Sumber jadwal semester belum lengkap untuk tanggal tersebut.']);
        }
        $dates = [];
        for ($day = Carbon::parse($data['start_date']); $day->lte(Carbon::parse($data['end_date'])); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        DB::transaction(function () use ($data, $dates, $request) {
            AcademicPeriod::orderBy('id')->lockForUpdate()->get();
            if (! app(RoomAvailabilityService::class)->coverage($data['start_date'], $data['end_date'])['ready']) {
                throw ValidationException::withMessages(['availability' => 'Sumber jadwal berubah. Muat ulang.']);
            }
            // Lock the room as the serialization point for all bookings and imports for it.
            $room = Room::query()->whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            abort_unless($room->isBookableClassroom(), 422, 'Peminjaman memerlukan ruang berlabel KELAS dan berstatus Dapat Dipakai.');

            $busy = app(RoomAvailabilityService::class)->forRange($data['start_date'], $data['end_date']);
            foreach ($dates as $date) {
                foreach ($busy[$date][$room->id] ?? [] as $slot) {
                    if ($slot['start'] < RoomTimeWindows::minutes($data['end_time'], true) && $slot['end'] > RoomTimeWindows::minutes($data['start_time'])) {
                        throw ValidationException::withMessages(['availability' => 'Pemakaian ruang bentrok pada '.$date]);
                    }
                }
            }

            $booking = Booking::create($data + ['user_id' => $request->user()->id, 'status' => 'pending']);
            foreach ($dates as $date) {
                $booking->occurrences()->create([
                    'usage_date' => $date,
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                ]);
            }
            RoomBookingHistory::create([
                'request_id' => $booking->id,
                'actor_id' => $request->user()->id,
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'Pengajuan dibuat',
            ]);
        });

        return redirect()->route('room-bookings.index')->with('success', 'Pengajuan berhasil dikirim dan menunggu persetujuan.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id && $this->canBorrow($request->user()) && $request->user()->can('room_booking_update'), 403);
        abort_unless($booking->status === 'pending', 422, 'Hanya pengajuan yang menunggu persetujuan yang dapat dibatalkan.');
        app(RoomBookingTransitionService::class)->cancel($request->user(), $booking, 'Dibatalkan oleh peminjam');

        return back()->with('success', 'Pengajuan dibatalkan.');
    }

    public function review(Request $request, RoomBookingIdentity $identity)
    {
        abort_unless($this->isReviewer($request->user()), 403);
        $data = $request->validate(['room_code' => ['nullable', 'string', 'max:50']]);
        $roomCode = $data['room_code'] ?? '';
        $bookings = $this->reviewBookings($roomCode)->awaitingUse()->paginate(20)->appends($data);

        return view('room-bookings.review', compact('bookings', 'identity', 'roomCode'));
    }

    public function reviewHistory(Request $request, RoomBookingIdentity $identity)
    {
        abort_unless($this->isReviewer($request->user()), 403);
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'room_code' => ['nullable', 'string', 'max:50'],
        ]);
        $roomCode = $data['room_code'] ?? '';
        $current = now('Asia/Jakarta')->startOfMonth();
        $month = $data['month'] ?? $current->format('Y-m');
        $earliestDate = Booking::min('start_date');
        $periodStart = AcademicPeriod::current()?->start_date?->toDateString();
        $first = Carbon::parse(min($earliestDate ?? $current->toDateString(), $periodStart ?? $current->toDateString()), 'Asia/Jakarta')->startOfMonth();
        $months = [];
        for ($cursor = $current->copy(); $cursor->gte($first); $cursor->subMonthNoOverflow()) {
            $months[$cursor->format('Y-m')] = $cursor->locale('id')->translatedFormat('F Y');
        }
        abort_unless(isset($months[$month]), 404);
        $selected = Carbon::createFromFormat('!Y-m', $month, 'Asia/Jakarta');
        $bookings = $this->reviewBookings($roomCode)
            ->whereNot(fn ($query) => $query->awaitingUse())
            ->lastUseInMonth($selected->toDateString(), $selected->copy()->endOfMonth()->toDateString())
            ->paginate(20)->appends(['month' => $month] + $data);

        return view('room-bookings.review', compact('bookings', 'identity', 'month', 'months', 'roomCode'));
    }

    private function reviewBookings(string $roomCode): EloquentBuilder
    {
        $query = Booking::with(['room', 'user.unit', 'occurrences'])->latest()->orderByDesc('id');
        if ($roomCode === '') {
            return $query;
        }

        $key = WeeklyRoomScheduleNormalizer::roomKey(...);
        $search = $key($roomCode);
        $rooms = $search === '' ? [] : Room::pluck('room_code', 'id')
            ->filter(fn (string $code) => str_contains($key($code), $search))->keys();

        return $query->whereIn('room_id', $rooms);
    }

    public function decide(Request $request, Booking $booking)
    {
        abort_unless($this->isReviewer($request->user()), 403);
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'note' => ['nullable', 'string', 'max:1000']]);
        app(RoomBookingTransitionService::class)->decide($request->user(), $booking, $data['decision'], $data['note'] ?? null);

        return back()->with('success', 'Status pengajuan diperbarui.');
    }

    public function adminCancel(Request $request, Booking $booking)
    {
        abort_unless($this->isReviewer($request->user()), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        abort_unless($booking->status === 'approved', 422, 'Hanya peminjaman yang disetujui yang dapat dibatalkan admin.');
        app(RoomBookingTransitionService::class)->cancel($request->user(), $booking, $data['reason']);

        return back()->with('success', 'Peminjaman dibatalkan.');
    }

    private function writeStatus(Booking $booking, string $status, User $actor, ?string $note): void
    {
        $from = $booking->status;
        $booking->update(['status' => $status, 'admin_note' => $note]);
        RoomBookingHistory::create([
            'request_id' => $booking->id,
            'actor_id' => $actor->id,
            'from_status' => $from,
            'to_status' => $status,
            'note' => $note,
        ]);
    }

    private function bookingsOnDate(string $date): Builder
    {
        return DB::table('room_booking_occurrences as o')
            ->join('room_booking_requests as req', 'req.id', '=', 'o.request_id')
            ->whereDate('o.usage_date', $date)
            ->whereIn('req.status', ['pending', 'approved']);
    }

    public function calendarDayDetail(Request $request, RoomBookingIdentity $identity): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'room_id' => ['nullable', 'integer', 'min:1']]);
        $date = $data['date'];
        $selectedRoom = isset($data['room_id']) ? Room::where('label', Room::LABEL_CLASSROOM)->findOrFail($data['room_id']) : null;

        // Jadwal iGracias pada tanggal tersebut
        $schedules = collect(app(RoomAvailabilityService::class)->teachingOccurrences($date, $selectedRoom?->id));

        // Peminjaman internal (pending / approved)
        $bookings = $this->bookingsOnDate($date)
            ->when($selectedRoom, fn ($query) => $query->where('req.room_id', $selectedRoom->id))
            ->join('rooms as r', 'r.id', '=', 'req.room_id')
            ->join('users as u', 'u.id', '=', 'req.user_id')
            ->leftJoin('units', 'units.id', '=', 'u.unit_id')
            ->orderBy('o.start_time')
            ->get([
                'r.id as room_id',
                'r.name as room_name',
                'r.room_code',
                'u.name as user_name',
                'u.email',
                'u.employee_id',
                'units.name as unit_name',
                'o.start_time',
                'o.end_time',
                'req.purpose',
                'req.status',
            ]);

        $formattedBookings = [];
        foreach ($bookings as $b) {
            $lecturer = $identity->lecturer($b->email, $b->employee_id);
            $code = trim($lecturer?->lecturer_code ?? '');
            $code = $code !== '' && $code !== '-' ? $code : null;
            $formattedBookings[] = [
                'room_id' => $b->room_id,
                'room_name' => $b->room_name,
                'room_code' => $b->room_code,
                'user_name' => $lecturer ? $code : $b->user_name,
                'lecturer_code' => $code,
                'is_lecturer' => $lecturer !== null,
                'unit_name' => $lecturer ? null : $b->unit_name,
                'start_time' => substr($b->start_time, 0, 5),
                'end_time' => substr($b->end_time, 0, 5),
                'purpose' => $b->purpose,
                'status' => $b->status,
                'source' => 'internal',
            ];
        }

        $formattedSchedules = [];
        foreach ($schedules as $s) {
            $formattedSchedules[] = [
                'room_id' => $s->room_id,
                'room_name' => $s->room_name,
                'room_code' => $s->room_code,
                'user_name' => $s->lecturer_code ?: 'Dosen Perkuliahan',
                'lecturer_code' => $s->lecturer_code ?: '-',
                'program_study' => $identity->programForCode($s->lecturer_code),
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'purpose' => $s->description ?? 'Jadwal Perkuliahan',
                'status' => 'terjadwal',
                'source' => 'igracias',
            ];
        }

        // Kumpulkan semua slot terpakai per room
        $roomSlots = [];
        foreach ($schedules as $s) {
            $roomSlots[$s->room_id][] = ['start' => $s->start_time, 'end' => $s->end_time];
        }
        foreach ($bookings as $b) {
            $roomSlots[$b->room_id][] = ['start' => $b->start_time, 'end' => $b->end_time];
        }

        // Hitung slot yang masih tersedia (≥ 1 jam) per ruangan
        $sourceReady = app(RoomAvailabilityService::class)->coverage($date, $date)['ready'];
        $rooms = $sourceReady
            ? Room::bookableClassrooms()->when($selectedRoom, fn ($query) => $query->whereKey($selectedRoom->id))->orderBy('name')->get()
            : collect();
        $availableSlots = [];
        foreach ($rooms as $room) {
            $free = $this->computeFreeSlots($roomSlots[$room->id] ?? [], 390, 1290, 60);
            $availableSlots[] = [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'room_code' => $room->room_code,
                'capacity' => $room->capacity,
                'building' => $room->building,
                'free_slots' => array_map(fn ($f) => "{$f['start']} - {$f['end']} WIB", $free),
            ];
        }

        return response()->json([
            'date' => $date,
            'source_ready' => $sourceReady,
            'schedules' => $formattedSchedules,
            'bookings' => $formattedBookings,
            'available_slots' => $availableSlots,
        ] + ($selectedRoom ? ['room' => $selectedRoom->only(['room_code', 'building', 'capacity', 'status'])] : []));
    }
}
