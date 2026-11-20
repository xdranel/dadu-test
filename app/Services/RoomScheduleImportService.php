<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Models\RoomBookingRequest;
use App\Models\RoomScheduleSource;
use App\Models\RoomWeeklyPattern;
use App\Models\User;
use App\Support\RoomTimeWindows;
use App\Support\WeeklyRoomScheduleNormalizer;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class RoomScheduleImportService
{
    private const NON_ACADEMIC_ROOM_KEYS = [
        'APDEVLAB', 'BEELAB', 'BESTLAB', 'CAMLAB', 'COSPILAB', 'CPROGLAB', 'CYCLDLAB',
        'DATAILAB', 'DIGITALLAB', 'EMLAB', 'MANSYSLAB', 'PORSCELAB', 'QBILAB', 'SELAB',
        'SWENGLAB', 'TCONLAB', 'KTTAULA',
    ];

    /**
     * Validate the complete iGracias payload before replacing the currently
     * active schedule. A partially valid import must never create false availability.
     *
     * @return array{imported:int, created_rooms?:int, errors:array<int, string>, successful:bool, mode?:string, excluded?:int}
     */
    public function import(array $payload, User $actor, string $filename): array
    {
        if (WeeklyRoomScheduleNormalizer::supports($payload)) {
            return $this->importWeekly($payload, $actor, $filename);
        }
        // iGracias exports the named-column format under data[]. Keep accepting
        // the legacy DataTables aaData format for existing semester files.
        $rows = $payload['data'] ?? $payload['aaData'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            return $this->recordFailure($actor, $filename, ['File harus berisi data berupa daftar jadwal JSON yang tidak kosong.']);
        }
        if (isset($payload['source']) && mb_strtolower(trim((string) $payload['source'])) !== 'igracias') {
            return $this->recordFailure($actor, $filename, ['Properti source harus bernilai iGracias.']);
        }

        $normalize = static fn (string $value): string => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value)));
        // iGracias room labels sometimes differ from the canonical room master.
        // KTT A-1.07 is intentionally not aliased: it is a separate room.
        $roomAliases = [
            'KTT107' => ['code' => '1.07', 'name' => 'KELAS 1.07'],
            'KTTB107' => ['code' => 'B-1.07', 'name' => 'KELAS B-1.07'],
        ];
        $roomLookup = [];
        foreach (Room::query()->get() as $room) {
            foreach ([$room->room_code, $room->name] as $identifier) {
                $key = $normalize((string) $identifier);
                if ($key !== '') {
                    $roomLookup[$key] = $room;
                }
            }
        }

        $schedules = [];
        $unknownRooms = [];
        $errors = [];
        foreach ($rows as $offset => $row) {
            $line = $offset + 1;
            if (! is_array($row)) {
                $errors[] = "Baris {$line}: format baris tidak valid.";

                continue;
            }

            if (array_is_list($row)) {
                $roomName = trim((string) ($row[3] ?? ''));
                $start = trim((string) ($row[2] ?? ''));
                $end = trim((string) ($row[10] ?? ''));
                $dateRaw = trim((string) ($row[12] ?? ''));
                $description = $row[5] ?? null;
                $lecturerCode = trim((string) ($row[7] ?? $row[4] ?? ''));
            } else {
                $roomName = trim((string) ($row['room_code'] ?? $row['ruangan'] ?? $row['room'] ?? ''));
                $start = trim((string) ($row['start_time'] ?? $row['jam_mulai'] ?? ''));
                $end = trim((string) ($row['end_time'] ?? $row['jam_selesai'] ?? ''));
                $dateRaw = trim((string) ($row['date'] ?? $row['tanggal'] ?? ''));
                $description = $row['description'] ?? $row['nama_mk'] ?? $row['mata_kuliah'] ?? null;
                $lecturerCode = trim((string) ($row['kode_dosen'] ?? $row['lecturer_code'] ?? $row['dosen'] ?? ''));
            }

            $roomKey = $normalize($roomName);
            $alias = $roomAliases[$roomKey] ?? null;
            $room = $roomLookup[$roomKey] ?? null;
            if ($alias) {
                $room = $roomLookup[$normalize($alias['code'])]
                    ?? $roomLookup[$normalize($alias['name'])]
                    ?? null;
            }
            $date = $this->parseDate($dateRaw);
            $startTime = $this->parseTime($start);
            $endTime = $this->parseTime($end);
            $rowErrors = [];

            if ($roomName === '') {
                $rowErrors[] = 'nama ruangan kosong';
            }
            if (! $date) {
                $rowErrors[] = "tanggal '{$dateRaw}' tidak valid (format DD-MM-YYYY)";
            }
            if (! $startTime) {
                $rowErrors[] = "jam mulai '{$start}' tidak valid";
            }
            if (! $endTime) {
                $rowErrors[] = "jam selesai '{$end}' tidak valid";
            }
            if ($startTime && $endTime && $startTime >= $endTime) {
                $rowErrors[] = 'jam selesai harus setelah jam mulai';
            }

            if ($rowErrors) {
                $errors[] = "Baris {$line}: ".implode('; ', $rowErrors).'.';

                continue;
            }

            $schedules[] = [
                'room_id' => $room?->id,
                'room_name' => $roomName,
                'room_alias' => $alias,
                'usage_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'description' => is_scalar($description) ? (string) $description : null,
                'lecturer_code' => $lecturerCode !== '' ? $lecturerCode : null,
                'source' => 'igracias',
            ];
            if (! $room) {
                $unknownRooms[$roomKey] = ['name' => $roomName, 'alias' => $alias];
            }
        }

        if ($errors || count($schedules) !== count($rows)) {
            return $this->recordFailure($actor, $filename, $errors ?: ['Tidak ada jadwal valid yang dapat diimport.']);
        }

        $createdRooms = 0;
        $syncRoomStatuses = $this->isCompleteScheduleExport($payload, count($rows));
        try {
            DB::transaction(function () use (&$schedules, $unknownRooms, $normalize, $actor, $filename, &$createdRooms, $syncRoomStatuses) {
                AcademicPeriod::orderBy('id')->lockForUpdate()->get();
                // Lock the room master rows so booking checks and this replacement serialize.
                Room::query()->orderBy('id')->lockForUpdate()->get(['id']);
                $roomIds = [];
                $knownRooms = Room::query()->get();
                foreach ($unknownRooms as $key => $roomInfo) {
                    $roomName = $roomInfo['name'];
                    $alias = $roomInfo['alias'];
                    $existing = $knownRooms->first(function ($room) use ($normalize, $key) {
                        return $normalize((string) $room->room_code) === $key || $normalize((string) $room->name) === $key;
                    });
                    if (! $existing && $alias) {
                        $existing = $knownRooms->first(function ($room) use ($normalize, $alias) {
                            return in_array($normalize((string) $room->room_code), [$normalize($alias['code']), $normalize($alias['name'])], true)
                                || in_array($normalize((string) $room->name), [$normalize($alias['code']), $normalize($alias['name'])], true);
                        });
                    }
                    if ($existing) {
                        $roomIds[$key] = $existing->id;

                        continue;
                    }

                    $canonicalName = $alias['name'] ?? $roomName;
                    $canonicalCode = $alias['code'] ?? null;
                    $baseCode = $canonicalCode ?? mb_substr(preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($roomName)), 0, 15);
                    if ($baseCode === '') {
                        $baseCode = 'ROOM';
                    }
                    $code = $baseCode;
                    $suffix = 1;
                    while (Room::where('room_code', $code)->exists() && ! $alias) {
                        $tail = (string) $suffix++;
                        $code = mb_substr($baseCode, 0, 20 - mb_strlen($tail)).$tail;
                    }
                    $room = Room::create([
                        'room_code' => $code,
                        'name' => mb_substr($canonicalName, 0, 255),
                        'building' => 'Belum ditentukan',
                        'capacity' => 0,
                        'status' => 'Dapat Dipakai',
                    ]);
                    $roomIds[$key] = $room->id;
                    $knownRooms->push($room);
                    $createdRooms++;
                }
                foreach ($schedules as &$schedule) {
                    if (! $schedule['room_id']) {
                        $schedule['room_id'] = $roomIds[$normalize($schedule['room_name'])];
                    }
                    unset($schedule['room_name']);
                    unset($schedule['room_alias']);
                }
                unset($schedule);
                $dates = array_column($schedules, 'usage_date');
                $existing = DB::table('room_booking_occurrences as o')->join('room_booking_requests as b', 'b.id', '=', 'o.request_id')->whereIn('b.status', ['pending', 'approved'])->whereDate('o.usage_date', '>=', min($dates))->whereDate('o.usage_date', '<=', max($dates))->get(['b.room_id', 'o.usage_date', 'o.start_time', 'o.end_time']);
                foreach ($schedules as $schedule) {
                    foreach ($existing as $booking) {
                        if ((int) $booking->room_id === (int) $schedule['room_id'] && substr($booking->usage_date, 0, 10) === $schedule['usage_date'] && RoomTimeWindows::minutes($schedule['start_time']) < RoomTimeWindows::minutes($booking->end_time, true) && RoomTimeWindows::minutes($schedule['end_time'], true) > RoomTimeWindows::minutes($booking->start_time)) {
                            throw new InvalidArgumentException('Jadwal tanggalan bentrok dengan peminjaman aktif pada '.$schedule['usage_date'].'. Data lama dipertahankan.');
                        }
                    }
                }
                DB::table('room_schedules')->where('source', 'igracias')->delete();
                foreach (array_chunk($schedules, 500) as $chunk) {
                    $now = now();
                    DB::table('room_schedules')->insert(array_map(static fn (array $schedule) => $schedule + [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk));
                }
                if ($syncRoomStatuses) {
                    $activeRoomIds = collect($schedules)->pluck('room_id')->unique()->values()->all();
                    Room::query()->update(['status' => 'Tidak Dapat Dipakai']);
                    if ($activeRoomIds) {
                        Room::query()->whereIn('id', $activeRoomIds)->update(['status' => 'Dapat Dipakai']);
                    }
                }
                DB::table('room_import_logs')->insert([
                    'user_id' => $actor->id,
                    'filename' => $filename,
                    'rows_imported' => count($schedules),
                    'errors' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }, 3);
        } catch (InvalidArgumentException $e) {
            return $this->recordFailure($actor, $filename, [$e->getMessage()]);
        }

        return ['imported' => count($schedules), 'created_rooms' => $createdRooms, 'errors' => [], 'successful' => true];
    }

    private function importWeekly(array $payload, User $actor, string $filename): array
    {
        try {
            $tag = $payload['filter_jadwal_perkuliahan']['schoolYear'] ?? '';
            if (! is_string($tag)) {
                throw new InvalidArgumentException('Periode sumber belum valid.');
            }
            $key = WeeklyRoomScheduleNormalizer::period($tag);
            $period = AcademicPeriod::where($key)->first();
            if (! $period) {
                throw new InvalidArgumentException('Periode jadwal belum terdaftar; buat/periksa periode akademik dahulu.');
            }
            $normalized = (new WeeklyRoomScheduleNormalizer)->normalize($payload, ['id' => $period->id, 'year' => $period->year, 'semester' => $period->semester, 'start_date' => $period->start_date->toDateString(), 'end_date' => $period->end_date->toDateString()]);
            $result = DB::transaction(function () use ($normalized, $period, $actor, $filename) {
                $locked = AcademicPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
                if ($locked->getRawOriginal() !== $period->getRawOriginal()) {
                    throw new InvalidArgumentException('Periode berubah; muat ulang dan impor ulang.');
                }
                $rooms = Room::orderBy('id')->lockForUpdate()->get();
                $roomKey = WeeklyRoomScheduleNormalizer::roomKey(...);
                $duplicateCodes = $this->conflictingLegacyRoomCodes($rooms, $period);
                if ($duplicateCodes->isNotEmpty()) {
                    $examples = $duplicateCodes->take(5)->implode(', ');
                    throw new InvalidArgumentException("Duplikat ruang fisik pada kode lama dan kode KTT ({$examples}). Periksa label, jadwal dan peminjaman pada kode lama sebelum impor ulang; jadwal lama dipertahankan.");
                }

                $lookup = [];
                foreach ($rooms as $room) {
                    foreach ([$room->room_code, $room->name] as $value) {
                        $key = $roomKey($value);
                        if ($key !== '') {
                            $lookup[$key][$room->id] = true;
                        }
                    }
                }
                foreach (DB::table('room_schedule_aliases')->get() as $alias) {
                    $lookup[$alias->alias_key][$alias->room_id] = true;
                }
                $patterns = [];
                $unknown = [];
                $excludedLabels = [];
                $excludedCount = 0;
                foreach ($normalized['patterns'] as $p) {
                    $label = $p['source_room_label'];
                    $key = $roomKey($label);
                    $candidates = array_keys($lookup[$key] ?? []);
                    if ($candidates === [] && in_array($key, self::NON_ACADEMIC_ROOM_KEYS, true)) {
                        $excludedLabels[$label] = true;
                        $excludedCount++;

                        continue;
                    }
                    if (count($candidates) !== 1) {
                        $unknown[$label] = true;

                        continue;
                    }
                    $p['room_id'] = $candidates[0];
                    $p['academic_period_id'] = $period->id;
                    $p['source_identity'] = hash('sha256', json_encode([$p['room_id'], $p['weekday'], $p['start_time'], $p['end_time'], $p['lecturer_code'], $p['description']], JSON_THROW_ON_ERROR));
                    $patterns[$p['source_identity']] = $p;
                }
                if ($unknown) {
                    throw new InvalidArgumentException('Pemetaan alias ruang belum pasti: '.implode(', ', array_keys($unknown)).'. Periksa kecocokan kode atau nama ruang dengan iGracias di Data Akademik → Ruangan; jadwal lama dipertahankan.');
                }
                if ($patterns === []) {
                    throw new InvalidArgumentException('Tidak ada pola jadwal yang terpetakan ke master ruang Akademik; jadwal lama dipertahankan.');
                }
                $conflicts = app(RoomAvailabilityService::class)->conflictingBookings(array_values($patterns), $period->start_date->toDateString(), $period->end_date->toDateString());
                if ($conflicts) {
                    throw new InvalidArgumentException('Snapshot bentrok dengan peminjaman: '.implode('; ', array_slice($conflicts, 0, 20)));
                }
                RoomWeeklyPattern::where('academic_period_id', $period->id)->delete();
                foreach ($patterns as $p) {
                    RoomWeeklyPattern::create($p);
                }
                $source = RoomScheduleSource::firstOrNew(['academic_period_id' => $period->id]);
                $source->fill(['is_complete' => true, 'scope' => 'semester', 'fingerprint' => $normalized['fingerprint'], 'version' => ($source->exists ? $source->version : 0) + 1, 'metadata' => [
                    'filename' => $filename, 'source_week' => $normalized['source_week'],
                    'excluded_patterns' => $excludedCount, 'excluded_room_labels' => array_keys($excludedLabels),
                ]])->save();
                DB::table('room_import_logs')->insert(['user_id' => $actor->id, 'filename' => $filename, 'rows_imported' => count($patterns), 'errors' => '[]', 'created_at' => now(), 'updated_at' => now()]);

                return ['imported' => count($patterns), 'excluded' => $excludedCount];
            }, 3);

            return $result + ['created_rooms' => 0, 'errors' => [], 'successful' => true, 'mode' => 'weekly'];
        } catch (InvalidArgumentException $e) {
            return $this->recordFailure($actor, $filename, [$e->getMessage()]);
        }
    }

    private function conflictingLegacyRoomCodes(EloquentCollection $rooms, AcademicPeriod $period): Collection
    {
        $roomKey = WeeklyRoomScheduleNormalizer::roomKey(...);
        $roomsByCode = $rooms->keyBy(fn (Room $room) => $roomKey($room->room_code));
        $legacyRooms = $rooms->filter(function (Room $room) use ($roomKey, $roomsByCode) {
            $replacement = $roomsByCode->get('KTT'.$roomKey($room->room_code));

            return $replacement && $roomKey($room->name) === $roomKey($replacement->name)
                && $roomKey($room->building) === $roomKey($replacement->building);
        });
        if ($legacyRooms->isEmpty()) {
            return collect();
        }

        $dates = [max(today('Asia/Jakarta')->toDateString(), $period->start_date->toDateString()), $period->end_date->toDateString()];
        $scheduled = DB::table('room_schedules')->whereIn('room_id', $legacyRooms->modelKeys())
            ->whereBetween('usage_date', $dates)->pluck('room_id');
        $booked = RoomBookingRequest::whereIn('room_id', $legacyRooms->modelKeys())->whereIn('status', ['pending', 'approved'])
            ->whereHas('occurrences', fn ($query) => $query->whereBetween('usage_date', $dates))->pluck('room_id');

        return $legacyRooms->filter(fn (Room $room) => $room->isBookableClassroom()
            || $scheduled->contains($room->id) || $booked->contains($room->id))->pluck('room_code');
    }

    private function isCompleteScheduleExport(array $payload, int $rowCount): bool
    {
        if (! isset($payload['total'], $payload['selected_menus'], $payload['filter_jadwal_perkuliahan'])
            || (int) $payload['total'] !== $rowCount
            || ! is_array($payload['selected_menus'])
            || ! in_array('jadwal_perkuliahan', $payload['selected_menus'], true)
            || ! is_array($payload['filter_jadwal_perkuliahan'])) {
            return false;
        }

        foreach ($payload['filter_jadwal_perkuliahan'] as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function recordFailure(User $actor, string $filename, array $errors): array
    {
        DB::table('room_import_logs')->insert([
            'user_id' => $actor->id,
            'filename' => $filename,
            'rows_imported' => 0,
            'errors' => json_encode(array_values($errors), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['imported' => 0, 'errors' => array_values($errors), 'successful' => false];
    }

    private function parseDate(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);
        $issues = DateTimeImmutable::getLastErrors();
        if (! $date || ($issues && ($issues['warning_count'] > 0 || $issues['error_count'] > 0)) || $date->format('d-m-Y') !== $value) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function parseTime(string $value): ?string
    {
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            return null;
        }

        return substr($value, 0, 8).(strlen($value) === 5 ? ':00' : '');
    }
}
