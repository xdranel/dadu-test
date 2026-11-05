<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Http\Requests\StoreLecturerRequest;
use App\Http\Requests\UpdateLecturerRequest;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;

class LecturerController extends Controller
{
    public function index(Request $request)
    {
        $lecturers = Lecturer::query()
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('nip_ypt', 'like', "%{$s}%")
                  ->orWhere('nidn', 'like', "%{$s}%")
                  ->orWhere('lecturer_code', 'like', "%{$s}%")
            )
            ->when($request->prodi, fn($q, $h) =>
                $q->where('study_program', $h)
            )
            ->when($request->status_kepegawaian, fn($q, $s) =>
                $q->where('employment_status', $s)
            )
            // Filter "hanya data tidak lengkap" — aktif saat ?incomplete=1
            ->when($request->boolean('incomplete'), fn($q) =>
                $q->incomplete()
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
            ->when($request->id, fn($q, $id) =>
                $q->where('id', $id)
            )
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('nip_ypt', 'like', "%{$s}%")
                  ->orWhere('nidn', 'like', "%{$s}%")
                  ->orWhere('lecturer_code', 'like', "%{$s}%")
            )
            ->when($request->prodi, fn($q, $h) =>
                $q->where('study_program', $h)
            )
            ->when($request->status_kepegawaian, fn($q, $s) =>
                $q->where('employment_status', $s)
            )
            ->latest()
            ->get();

        $filename = 'lecturer-' . now()->format('Y-m-d') . '.xlsx';

        return (new FastExcel($data))->download($filename, function ($d) {
            return [
                'NAMA'                => $d->name,
                'Status Aktif'        => $d->active_status,
                'PRODI'               => $d->study_program,
                'NIP YPT'             => $d->nip_ypt,
                'NIDN'                => $d->nidn,
                'NUPTK'               => $d->nuptk,
                'STATUS Kepegawaian'  => $d->employment_status,
                'GELAR DEPAN'         => $d->front_title,
                'GELAR BELAKANG'      => $d->back_title,
                'JFA'                 => $d->jfa,
                'BIDANG KEILMUAN'     => $d->academic_interest,
                'KODE DOSEN'          => $d->lecturer_code,
                'Email Domain'        => $d->email_domain,
            ];
        });
    }

    public function downloadTemplate()
    {
        $template = collect([[
            'NAMA'               => 'Budi Dummy',
            'Status Aktif'       => 'Aktif',
            'PRODI'              => 'Informatika',
            'NIP YPT'            => '123456',
            'NIDN'               => '0012345678',
            'NUPTK'              => '987654321',
            'STATUS Kepegawaian' => 'Tetap',
            'GELAR DEPAN'        => 'Dr.',
            'GELAR BELAKANG'     => 'S.Kom., M.T.',
            'JFA'                => 'Lektor',
            'BIDANG KEILMUAN'    => 'Kecerdasan Buatan',
            'KODE DOSEN'         => 'BDI',
            'Email Domain'       => 'budi@telkomuniversity.ac.id',
        ]]);

        return (new FastExcel($template))->download(
            'template-import-dosen.xlsx',
            fn($row) => $row
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $imported = 0;
        $skipped  = 0;

        try {
            (new FastExcel)->import($request->file('file'), function ($row) use (&$imported, &$skipped) {
                
                $clean = function($val) {
                    if (is_string($val)) {
                        $val = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $val);
                        $val = str_replace("\xC2\xA0", '', $val);
                        $val = trim($val);
                    }
                    return $val === '' ? null : $val;
                };
                
                $nidn = $clean($row['NIDN'] ?? $row['nidn'] ?? null);
                $kode = $clean($row['KODE DOSEN'] ?? $row['kode_dosen'] ?? null);
                $nama = $clean($row['NAMA'] ?? $row['nama'] ?? null);

                if (empty($nama) || (empty($nidn) && empty($kode))) return null;

                $query = Lecturer::query();
                if ($nidn) {
                    $query->where('nidn', $nidn);
                } else {
                    $query->where('lecturer_code', $kode);
                }

                if ($query->exists()) {
                    $skipped++;
                    return null;
                }

                Lecturer::create([
                    'name'              => $nama,
                    'active_status'     => $clean($row['Status Aktif'] ?? $row['status_aktif'] ?? null),
                    'study_program'     => $clean($row['PRODI'] ?? $row['prodi'] ?? null),
                    'nip_ypt'           => $clean($row['NIP YPT'] ?? $row['nip_ypt'] ?? null),
                    'nidn'              => $nidn,
                    'nuptk'             => $clean($row['NUPTK'] ?? $row['nuptk'] ?? null),
                    'employment_status' => $clean($row['STATUS Kepegawaian'] ?? $row['status_kepegawaian'] ?? null),
                    'front_title'       => $clean($row['GELAR DEPAN'] ?? $row['gelar_depan'] ?? null),
                    'back_title'        => $clean($row['GELAR BELAKANG'] ?? $row['gelar_belakang'] ?? null),
                    'jfa'               => $clean($row['JFA'] ?? $row['jfa'] ?? null),
                    'academic_interest' => $clean($row['BIDANG KEILMUAN'] ?? $row['bidang_keilmuan'] ?? null),
                    'lecturer_code'     => $kode,
                    'email_domain'      => $clean($row['Email Domain'] ?? $row['email_domain'] ?? null),
                ]);

                $imported++;
            });
        } catch (\Exception $e) {
            return back()->with('error', 'File tidak valid: ' . $e->getMessage());
        }

        return redirect()->route('lecturers.index')
            ->with('success', "Import selesai: {$imported} berhasil, {$skipped} dilewati.");
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
