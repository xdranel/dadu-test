<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaRegistration extends Model
{
    public const DOCUMENT_TYPES = [
        'level_2_result_capture' => 'Capture Hasil Sidang Tingkat 2',
        'igracias_approval_capture' => 'Capture Persetujuan Sidang di iGracias',
        'ta_progress_capture' => 'Capture Progress Status TA',
        'ta_data_capture' => 'Capture Data TA (Menu TA/PA)',
        'online_supervision_capture' => 'Capture Bimbingan Online yang Disetujui',
        'skpi_tak_capture' => 'Capture Status SKPI & TAK',
        'graduation_payment_proof' => 'Bukti Pembayaran Wisuda',
        'bpp_payment_proof' => 'Bukti Bayar BPP Semester Berjalan',
        'ta_supervisor_sk' => 'SK Pembimbing TA Aktif',
        'eprt_lac_certificate' => 'Sertifikat EPrT LAC (minimal 450)',
        'thesis_draft' => 'Draft Buku TA (PDF)',
        'transcript_khs' => 'KHS (PDF, SKS lulus minimal 120)',
        'ta_course_ksm' => 'KSM yang mengambil Mata Kuliah Tugas Akhir',
        'diploma_statement' => 'Surat Pernyataan Biodata Ijazah',
        'diploma_biodata_form' => 'Formulir Biodata Ijazah',
        'pdm_submission_proof' => 'Bukti Pengajuan PDM',
    ];

    public const DOCUMENT_DESCRIPTIONS = [
        'level_2_result_capture' => 'Pastikan status lulus terlihat; flow mensyaratkan IPK minimal 2,00 dan SKS lulus minimal 120.',
        'igracias_approval_capture' => 'Capture harus memperlihatkan persetujuan sidang di iGracias.',
        'ta_progress_capture' => 'Pastikan status TA 1–5 dan tanggal terlihat pada capture.',
        'ta_data_capture' => 'Ambil dari menu TA/PA di iGracias.',
        'online_supervision_capture' => 'Tampilkan bimbingan online yang sudah disetujui.',
        'skpi_tak_capture' => 'Pastikan status SKPI dan TAK terlihat.',
        'bpp_payment_proof' => 'Untuk BPP semester berjalan dengan status sudah lunas.',
        'eprt_lac_certificate' => 'Nilai minimal 450; unggah PDF.',
        'thesis_draft' => 'PDF tanpa tanda tangan; logo dan tahun mengikuti template.',
        'transcript_khs' => 'PDF dengan SKS lulus minimal 120.',
        'ta_course_ksm' => 'KSM perlu memperlihatkan pengambilan mata kuliah Tugas Akhir.',
        'diploma_statement' => 'Dokumen bermaterai fisik dan ditandatangani basah; unggah PDF.',
        'diploma_biodata_form' => 'Formulir biodata dummy ijazah; unggah PDF.',
        'pdm_submission_proof' => 'Unggah PDF bukti pengajuan Perubahan Data Mahasiswa.',
    ];

    protected $fillable = [
        'requirements_version',
        'sidang_ta_period_id', 'student_number', 'student_name', 'study_program',
        'student_phone', 'parent_phone', 'graduation_payment_date',
        'diploma_biodata_accurate', 'academic_snapshot', 'status',
        'submitted_by', 'reviewed_by', 'submitted_at', 'reviewed_at', 'review_notes',
    ];

    protected $attributes = [
        'requirements_version' => 1,
    ];

    protected $casts = [
        'requirements_version' => 'integer',
        'academic_snapshot' => 'array',
        'graduation_payment_date' => 'date',
        'diploma_biodata_accurate' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function period()
    {
        return $this->belongsTo(SidangTaPeriod::class, 'sidang_ta_period_id');
    }

    public function documents()
    {
        return $this->hasMany(SidangTaRegistrationDocument::class, 'sidang_ta_registration_id');
    }

    public function logs()
    {
        return $this->hasMany(SidangTaRegistrationLog::class, 'sidang_ta_registration_id')->latest('performed_at');
    }

    public function logAction(string $action, ?string $from, ?string $to, ?string $notes, ?int $userId): void
    {
        $this->logs()->create([
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'performed_by' => $userId,
            'performed_at' => now(),
        ]);
    }
}
