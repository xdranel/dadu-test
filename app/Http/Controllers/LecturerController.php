<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLecturerRequest;
use App\Http\Requests\UpdateLecturerRequest;
use App\Models\Lecturer;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;

class LecturerController extends Controller
{
    public function index(Request $request)
    {
        $lecturers = Lecturer::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('nip_ypt', 'like', "%{$s}%")
                ->orWhere('nidn', 'like', "%{$s}%")
                ->orWhere('lecturer_code', 'like', "%{$s}%")
            )
            ->when($request->prodi, fn ($q, $h) => $q->where('study_program', $h)
            )
            ->when($request->status_kepegawaian, fn ($q, $s) => $q->where('employment_status', $s)
            )
            // Filter "hanya data tidak lengkap" — aktif saat ?incomplete=1
            ->when($request->boolean('incomplete'), fn ($q) => $q->incomplete()
            )
            ->completeFirst()  // data lengkap di atas, tidak lengkap di bawah
            ->paginate(15)
            ->withQueryString();

        $prodiList = Lecturer::select('study_program')
            ->whereNotNull('study_program')
            ->distinct()
            ->orderBy('study_program')
            ->pluck('study_program');

        $statusList = Lecturer::select('employment_status')
            ->whereNotNull('employment_status')
            ->distinct()
            ->orderBy('employment_status')
            ->pluck('employment_status');

        // Hitung total dosen dengan data tidak lengkap (untuk banner peringatan)
        $incompleteCount = Lecturer::incomplete()->count();

        return view('lecturers.index', compact('lecturers', 'prodiList', 'statusList', 'incompleteCount'));
    }

    public function export(Request $request)
    {
        $data = Lecturer::query()
            ->when($request->id, fn ($q, $id) => $q->where('id', $id)
            )
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('nip_ypt', 'like', "%{$s}%")
                ->orWhere('nidn', 'like', "%{$s}%")
                ->orWhere('lecturer_code', 'like', "%{$s}%")
            )
            ->when($request->prodi, fn ($q, $h) => $q->where('study_program', $h)
            )
            ->when($request->status_kepegawaian, fn ($q, $s) => $q->where('employment_status', $s)
            )
            ->latest()
            ->get();

        $filename = 'lecturer-'.now()->format('Y-m-d').'.xlsx';

        return (new FastExcel($data))->download($filename, function ($d) {
            return [
                'NAMA' => $d->name,
                'Status Aktif' => $d->active_status,
                'PRODI' => $d->study_program,
                'NIP YPT' => $d->nip_ypt,
                'NIDN' => $d->nidn,
                'NUPTK' => $d->nuptk,
                'STATUS Kepegawaian' => $d->employment_status,
                'GELAR DEPAN' => $d->front_title,
                'GELAR BELAKANG' => $d->back_title,
                'JFA' => $d->jfa,
                'BIDANG KEILMUAN' => $d->academic_interest,
                'KODE DOSEN' => $d->lecturer_code,
                'Email Domain' => $d->email_domain,
                'No HP' => $d->phone,
                'COE' => $d->coe,
                'KK' => $d->kk,
            ];
        });
    }

    public function downloadTemplate()
    {
        $template = collect([[
            'NAMA' => 'Budi Dummy',
            'Status Aktif' => 'Aktif',
            'PRODI' => 'Informatika',
            'NIP YPT' => '123456',
            'NIDN' => '0012345678',
            'NUPTK' => '987654321',
            'STATUS Kepegawaian' => 'Tetap',
            'GELAR DEPAN' => 'Dr.',
            'GELAR BELAKANG' => 'S.Kom., M.T.',
            'JFA' => 'Lektor',
            'BIDANG KEILMUAN' => 'Kecerdasan Buatan',
            'KODE DOSEN' => 'BDI',
            'Email Domain' => 'budi@telkomuniversity.ac.id',
            'No HP' => '081234567890',
            'COE' => 'Artificial Intelligence',
            'KK' => 'Data Science',
        ]]);

        return (new FastExcel($template))->download(
            'template-import-dosen.xlsx',
            fn ($row) => $row
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        try {
            (new FastExcel)->import($request->file('file'), function ($row) use (&$imported, &$updated, &$skipped) {

                $clean = function ($val) {
                    if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d');
                    if (is_float($val) && floor($val) == $val) $val = (int) $val;
                    if (is_int($val)) $val = (string) $val; // NIP/NUPTK/No HP numerik dari Excel

                    if (is_string($val)) {
                        $val = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $val);
                        $val = str_replace("\xC2\xA0", '', $val);
                        $val = trim($val);
                    }

                    return $val === '' ? null : $val;
                };

                // Normalisasi header: huruf besar, spasi/underscore diseragamkan
                $norm = fn ($k) => strtoupper(trim(preg_replace('/[\s_]+/', ' ', (string) $k)));
                $map = [];
                foreach ($row as $k => $v) { $map[$norm($k)] = $v; }

                // Ambil nilai berdasarkan daftar alias header (toleran format header)
                $get = function (array $aliases) use ($map, $norm, $clean) {
                    foreach ($aliases as $a) {
                        $key = $norm($a);
                        if (array_key_exists($key, $map) && $clean($map[$key]) !== null) return $clean($map[$key]);
                    }
                    // fallback: header yang diawali alias (mis. "BIDANG KEILMUAN JAFA TERAKHIR LLDIKTI")
                    foreach ($aliases as $a) {
                        $key = $norm($a);
                        foreach ($map as $h => $v) {
                            if (str_starts_with($h, $key . ' ') && $clean($v) !== null) return $clean($v);
                        }
                    }
                    return null;
                };

                // No HP: pastikan diawali 0 (Excel sering menghapus 0 di depan)
                $normalizePhone = function ($p) {
                    if ($p === null) return null;
                    $digits = preg_replace('/[^\d+]/', '', $p);
                    if ($digits === '') return null;
                    if (str_starts_with($digits, '+62')) return '0' . substr($digits, 3);
                    if (str_starts_with($digits, '62'))  return '0' . substr($digits, 2);
                    if (str_starts_with($digits, '8'))   return '0' . $digits;
                    return $digits;
                };

                $nidn   = $get(['NIDN']);
                $kode   = $get(['Kode Dosen Baru', 'KODE DOSEN', 'Kode Dosen']);
                $nama   = $get(['NAMA']);
                $nipYpt = $get(['NIP YPT']);
                $nuptk  = $get(['NUPTK']);

                // Syarat baris valid: nama terisi dan minimal salah satu identitas terisi (NIDN, NIP YPT, NUPTK, atau Kode Dosen)
                if (empty($nama) || (empty($nidn) && empty($nipYpt) && empty($nuptk) && empty($kode))) return null;

                $matches = Lecturer::query()->where(function ($q) use ($nidn, $nipYpt, $nuptk, $kode) {
                    if ($nidn) $q->where('nidn', $nidn);
                    if ($nipYpt) $q->orWhere('nip_ypt', $nipYpt);
                    if ($nuptk) $q->orWhere('nuptk', $nuptk);
                    if ($kode) $q->orWhere('lecturer_code', $kode);
                })->limit(2)->get();

                if ($matches->count() > 1) {
                    $skipped++;
                    return null;
                }

                $existing = $matches->first();

                // Kode dosen unik: jika sudah dipakai dosen lain, jangan diubah/ditambah agar tidak error
                if ($kode && Lecturer::where('lecturer_code', $kode)
                        ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                        ->exists()) {
                    $kode = null;
                }

                if (!$existing && empty($kode)) return null; // dosen baru wajib punya kode dosen

                $payload = [
                    'name'              => $nama,
                    'active_status'     => $get(['Status Aktif', 'STATUS', 'Status']),
                    'study_program'     => $get(['PRODI', 'Program Studi']),
                    'nip_ypt'           => $nipYpt,
                    'nidn'              => $nidn,
                    'nuptk'             => $nuptk,
                    'employment_status' => $get(['STATUS Kepegawaian', 'Status Kepegawaian']),
                    'front_title'       => $get(['GELAR DEPAN']),
                    'back_title'        => $get(['GELAR BELAKANG']),
                    'jfa'               => $get(['JFA']),
                    'academic_interest' => $get(['BIDANG KEILMUAN']),
                    'lecturer_code'     => $kode,
                    'email_domain'      => $get(['Email Domain Telkom University', 'Email Domain', 'Email']),
                    'phone'             => $normalizePhone($get(['No HP', 'HP', 'No Telepon'])),
                    'coe'               => $get(['COE']),
                    'kk'                => $get(['KK']),
                ];

                if ($existing) {
                    // Update: hanya kolom yang terisi di file (sel kosong tidak menimpa data lama)
                    $changes = array_filter($payload, fn ($v) => $v !== null && $v !== '');
                    $existing->fill($changes);

                    if ($existing->isDirty()) {
                        $existing->save();
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    return null;
                }

                Lecturer::create($payload);
                $imported++;
            });
        } catch (\Exception $e) {
            return back()->with('error', 'File tidak valid: '.$e->getMessage());
        }

        return redirect()->route('lecturers.index')
            ->with('success', "Import selesai: {$imported} ditambahkan, {$updated} diperbarui, {$skipped} tanpa perubahan.");
    }

    public function create()
    {
        return view('lecturers.create');
    }

    public function store(StoreLecturerRequest $request)
    {
        Lecturer::create($request->validated());

        return redirect()->route('lecturers.index')
            ->with('success', 'Data dosen berhasil ditambahkan.');
    }

    public function show(Lecturer $lecturer)
    {
        return view('lecturers.show', compact('lecturer'));
    }

    public function edit(Lecturer $lecturer)
    {
        return view('lecturers.edit', compact('lecturer'));
    }

    public function update(UpdateLecturerRequest $request, Lecturer $lecturer)
    {
        $lecturer->update($request->validated());

        return redirect()->route('lecturers.index')
            ->with('success', "Data {$lecturer->name} berhasil diperbarui.");
    }

    public function destroy(Lecturer $lecturer)
    {
        $lecturer->delete();

        return redirect()->route('lecturers.index')
            ->with('success', 'Data dosen berhasil dihapus.');
    }
}
