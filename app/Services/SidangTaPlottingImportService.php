<?php

namespace App\Services;

use App\Models\Room;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class SidangTaPlottingImportService
{
    public const HEADERS = ['Periode Sidang TA', 'NIM', 'Nama Mahasiswa', 'Program Studi', 'Status Pengajuan', 'Kelompok Keahlian', 'Judul TA', 'Kode Dosen Pembimbing 1', 'Kode Dosen Pembimbing 2', 'Hari', 'Tanggal', 'Jam Mulai', 'Jam Selesai', "Ruang\n(diisi oleh AKD)", 'Kode Penguji 1', 'Kode Penguji 2'];

    public function import(User $actor, SidangTaPeriod $period, string $path): array
    {
        app(SidangTaBookingAccess::class)->authorizeStaff($actor);
        if (! is_file($path) || ! is_readable($path)) {
            $this->invalid('File plotting tidak dapat dibaca.');
        }
        $reader = new Reader;
        $rows = [];
        $opened = false;
        $hasSheet = false;
        try {
            $reader->open($path);
            $opened = true;
            foreach ($reader->getSheetIterator() as $sheet) {
                $hasSheet = true;
                $number = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $number++;
                    // Duplicate detection needs the whole sheet; bound memory before retaining rows.
                    if ($number > 5002) {
                        $this->invalid('Maksimal 5000 baris plotting per file; pisahkan menjadi beberapa file.');
                    }
                    $cells = $row->getCells();
                    $values = array_map(fn ($cell) => $cell->getValue(), $cells);
                    if ($number === 1) {
                        if (($values[0] ?? null) !== 'Peserta TA' || ($values[9] ?? null) !== 'Plotting Jadwal') {
                            $this->invalid('Gunakan template dua baris header plotting.');
                        }

                        continue;
                    }
                    if ($number === 2) {
                        if (count($values) !== 16) {
                            $this->invalid('Template plotting harus memiliki 16 kolom.');
                        }
                        foreach (self::HEADERS as $index => $header) {
                            if ($this->normalize((string) ($values[$index] ?? '')) !== $this->normalize($header)) {
                                $this->invalid('Header plotting tidak sesuai pada kolom '.($index + 1).'.');
                            }
                        }

                        continue;
                    }
                    if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                        continue;
                    }
                    $unsafe = false;
                    foreach ($cells as $cell) {
                        $value = $cell->getValue();
                        if ($cell instanceof FormulaCell || (is_string($value) && preg_match('/^[=+@]/u', ltrim($value)))) {
                            $unsafe = true;
                        }
                    }
                    $rows[] = ['row' => $number, 'values' => array_pad($values, 16, null), 'unsafe' => $unsafe, 'wide' => count($values) > 16];
                }
                if ($number < 2) {
                    $this->invalid('Dua baris header plotting diperlukan.');
                }
                break;
            }
            if (! $hasSheet) {
                $this->invalid('Worksheet plotting tidak tersedia.');
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['file' => 'File XLSX plotting tidak valid atau tidak dapat dibaca.']);
        } finally {
            if ($opened) {
                $reader->close();
            }
        }
        $result = ['processed' => [], 'skipped' => [], 'errors' => []];
        $groups = [];
        foreach ($rows as $row) {
            $groups[$this->text($row['values'][1])][] = $row;
        }
        foreach ($groups as $nim => $entries) {
            $conflicting = count(array_unique(array_map(fn ($entry) => serialize($entry['values']), $entries))) > 1;
            foreach ($entries as $offset => $entry) {
                $report = ['row' => $entry['row'], 'nim' => (string) $nim, 'student_number' => (string) $nim];
                if ($conflicting) {
                    $result['errors'][] = $report + ['message' => 'NIM duplikat memiliki plotting berbeda.'];

                    continue;
                }
                if ($offset > 0) {
                    $result['skipped'][] = $report + ['message' => 'Baris duplikat identik dilewati.'];

                    continue;
                }
                try {
                    if ($entry['unsafe'] || $entry['wide']) {
                        $this->invalid('Formula atau jumlah kolom plotting tidak aman.');
                    }
                    $values = $entry['values'];
                    if ($nim === '' || ! preg_match('/^\d+$/D', (string) $nim)) {
                        $this->invalid('NIM tidak valid; gunakan teks untuk mempertahankan angka nol di awal.');
                    }
                    if ($this->normalize($this->text($values[0])) !== $this->normalize($period->name)) {
                        $this->invalid('Periode pada baris tidak sesuai periode yang dipilih.');
                    }
                    $registration = SidangTaRegistration::where('sidang_ta_period_id', $period->id)->where('student_number', (string) $nim)->where('status', 'approved')->first();
                    if (! $registration) {
                        $result['skipped'][] = $report + ['message' => 'Pengajuan disetujui untuk NIM dan periode ini belum tersedia.'];

                        continue;
                    }
                    $date = $this->date($values[10]);
                    $start = $this->time($values[11]);
                    $end = $this->time($values[12]);
                    if (strtotime($end) - strtotime($start) !== 90 * 60) {
                        $this->invalid('Durasi plotting harus 90 menit.');
                    }
                    $participant = [];
                    foreach ([2 => 'student_name', 5 => 'expertise_group', 6 => 'title', 7 => 'advisor_1_code', 8 => 'advisor_2_code', 14 => 'examiner_1_code', 15 => 'examiner_2_code'] as $index => $key) {
                        $value = $this->text($values[$index]);
                        if ($value !== '') {
                            $participant[$key] = $value;
                        }
                    }
                    $roomReference = $this->text($values[13]);
                    $outcome = app(SidangTaBookingLock::class)->run($registration->id, [], function ($reg, $context, $setting) use ($actor, $period, $participant, $date, $start, $end, $roomReference) {
                        if ($reg->sidang_ta_period_id !== $period->id || $reg->status !== 'approved') {
                            $this->invalid('Pengajuan atau periode berubah; impor ulang.');
                        }
                        if ($context->was_confirmed || $context->booking || ! empty($context->details_snapshot)) {
                            return 'Jadwal yang sudah ditetapkan dipertahankan.';
                        }
                        if ($setting && ($date < $setting->start_date->format('Y-m-d') || $date > $setting->end_date->format('Y-m-d'))) {
                            $this->invalid('Tanggal plotting berada di luar rentang pelaksanaan periode.');
                        }
                        $room = null;
                        if ($roomReference !== '') {
                            $matches = Room::where('room_code', $roomReference)->get();
                            if ($matches->isEmpty()) {
                                $matches = Room::where('name', $roomReference)->get();
                            }
                            $room = $matches->count() === 1 ? $matches->first() : null;
                            if (! $room?->isBookableClassroom()) {
                                $this->invalid('Ruang plotting harus ruang kelas aktif yang valid.');
                            }
                        }
                        // MySQL may reorder JSON object keys. Compare typed values before Eloquent
                        // serializes JSON so an unchanged import cannot stale a preview token.
                        $previousParticipant = $context->participant_data ?? [];
                        $nextParticipant = $participant;
                        ksort($previousParticipant);
                        ksort($nextParticipant);
                        $unchanged = $previousParticipant === $nextParticipant
                            && $context->planning_source === 'import'
                            && $context->preferred_date?->format('Y-m-d') === $date
                            && $context->preferred_start_time === $start.':00'
                            && $context->preferred_end_time === $end.':00'
                            && ($context->preferred_room_id === null ? null : (int) $context->preferred_room_id) === $room?->id;
                        if ($unchanged) {
                            return 'Plotting identik sudah tersimpan.';
                        }
                        $before = $context->getOriginal();
                        $context->fill(['participant_data' => $participant, 'planning_source' => 'import', 'preferred_date' => $date, 'preferred_start_time' => $start.':00', 'preferred_end_time' => $end.':00', 'preferred_room_id' => $room?->id]);
                        $context->lock_version++;
                        $context->save();
                        $context->executionLogs()->create(['action' => 'plotting_imported', 'from_status' => $context->state, 'to_status' => $context->state, 'notes' => 'Impor preferensi plotting; menunggu Tetapkan Jadwal.', 'data' => ['previous' => array_intersect_key($before, array_flip(['participant_data', 'planning_source', 'preferred_date', 'preferred_start_time', 'preferred_end_time', 'preferred_room_id']))], 'performed_by' => $actor->id, 'performed_at' => now()]);

                        return null;
                    });
                    $result[$outcome ? 'skipped' : 'processed'][] = $report + ['message' => $outcome ?? 'Preferensi plotting disimpan; menunggu Tetapkan Jadwal.'];
                } catch (ValidationException $e) {
                    $result['errors'][] = $report + ['message' => collect($e->errors())->flatten()->implode(' ')];
                }
            }
        }

        return $result;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    private function date(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_int($value) || is_float($value)) {
            if (! is_finite((float) $value) || $value < 1 || $value > 2958465) {
                $this->invalid('Tanggal serial Excel tidak valid.');
            }

            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) floor($value).' days')->format('Y-m-d');
        }
        $value = $this->text($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }
        $this->invalid('Tanggal plotting tidak valid.');
    }

    private function time(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            if ($value->format('s') !== '00') {
                $this->invalid('Jam plotting harus tepat sampai menit.');
            }

            return $value->format('H:i');
        }
        if (is_int($value) || is_float($value)) {
            if (! is_finite((float) $value) || $value < 0 || $value >= 1) {
                $this->invalid('Jam serial Excel tidak valid.');
            }
            $seconds = (int) round($value * 86400);
            if ($seconds >= 86400 || $seconds % 60 !== 0) {
                $this->invalid('Jam plotting harus tepat sampai menit.');
            }

            return sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
        }
        $value = str_replace('.', ':', $this->text($value));
        if (preg_match('/^(\d{1,2}):(\d{2})(?::00)?$/D', $value, $matches) && (int) $matches[1] < 24 && (int) $matches[2] < 60) {
            return sprintf('%02d:%02d', $matches[1], $matches[2]);
        }
        $this->invalid('Jam plotting tidak valid.');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
