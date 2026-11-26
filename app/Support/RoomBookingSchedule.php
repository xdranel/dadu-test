<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class RoomBookingSchedule
{
    public const CONSECUTIVE = 'consecutive';

    public const RECURRING = 'recurring';

    private function __construct(
        public string $mode,
        public array $dates,
        public string $startTime,
        public string $endTime,
    ) {}

    public static function rules(): array
    {
        return [
            'booking_mode' => ['nullable', 'in:consecutive,recurring'],
            'start_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'weekdays' => ['exclude_unless:booking_mode,recurring', 'required', 'array', 'list', 'min:1', 'max:7'],
            'weekdays.*' => ['exclude_unless:booking_mode,recurring', 'required', 'integer', 'between:1,7', 'distinct'],
        ];
    }

    public static function messages(): array
    {
        return [
            'booking_mode.in' => 'Pilih jenis peminjaman Berurutan atau Berulang.',
            'start_at.required' => 'Isi tanggal dan waktu mulai.',
            'start_at.date_format' => 'Tanggal atau waktu mulai tidak valid.',
            'end_at.required' => 'Isi tanggal dan waktu selesai.',
            'end_at.date_format' => 'Tanggal atau waktu selesai tidak valid.',
            'weekdays.required' => 'Pilih minimal satu hari untuk peminjaman berulang.',
            'weekdays.min' => 'Pilih minimal satu hari untuk peminjaman berulang.',
            'weekdays.array' => 'Pilih hari dari Senin sampai Minggu.',
            'weekdays.list' => 'Pilih hari dari Senin sampai Minggu.',
            'weekdays.max' => 'Pilih maksimal tujuh hari dalam seminggu.',
            'weekdays.*.integer' => 'Pilih hari dari Senin sampai Minggu.',
            'weekdays.*.required' => 'Pilih hari dari Senin sampai Minggu.',
            'weekdays.*.between' => 'Pilih hari dari Senin sampai Minggu.',
            'weekdays.*.distinct' => 'Setiap hari hanya boleh dipilih satu kali.',
        ];
    }

    public static function fromValidated(array $input): self
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $input['start_at'], 'Asia/Jakarta');
        $end = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $input['end_at'], 'Asia/Jakarta');
        $firstDay = $start->startOfDay();
        $lastDay = $end->startOfDay();
        if ($firstDay->lt(CarbonImmutable::today('Asia/Jakarta'))) {
            throw ValidationException::withMessages(['start_at' => 'Tanggal peminjaman tidak boleh di masa lampau.']);
        }
        if ($lastDay->lt($firstDay)) {
            throw ValidationException::withMessages(['end_at' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.']);
        }
        if ($firstDay->diffInDays($lastDay) >= 366) {
            throw ValidationException::withMessages(['end_at' => 'Rentang peminjaman maksimal 366 hari termasuk tanggal mulai dan selesai.']);
        }

        $startTime = $start->format('H:i');
        $endTime = $end->format('H:i');
        if ($startTime < '06:30' || $endTime > '21:30' || $startTime >= $endTime) {
            throw ValidationException::withMessages(['start_time' => 'Waktu selesai harus lebih lambat dari waktu mulai dalam rentang 06.30–21.30 WIB.']);
        }

        $mode = $input['booking_mode'] ?? self::CONSECUTIVE;
        $weekdays = array_map('intval', $input['weekdays'] ?? []);
        $dates = [];
        for ($day = $firstDay; $day->lte($lastDay); $day = $day->addDay()) {
            if ($mode === self::CONSECUTIVE || in_array($day->dayOfWeekIso, $weekdays, true)) {
                $dates[] = $day->toDateString();
            }
        }
        if ($dates === []) {
            throw ValidationException::withMessages(['weekdays' => 'Hari yang dipilih tidak ada dalam rentang tanggal tersebut.']);
        }

        return new self($mode, $dates, $startTime, $endTime);
    }

    public function firstDate(): string
    {
        return $this->dates[0];
    }

    public function lastDate(): string
    {
        return $this->dates[array_key_last($this->dates)];
    }

    public function firstConflict(array $busy, int $roomId): ?string
    {
        $start = RoomTimeWindows::minutes($this->startTime);
        $end = RoomTimeWindows::minutes($this->endTime);
        foreach ($this->dates as $date) {
            foreach ($busy[$date][$roomId] ?? [] as $slot) {
                if ($slot['start'] < $end && $slot['end'] > $start) {
                    return $date;
                }
            }
        }

        return null;
    }
}
