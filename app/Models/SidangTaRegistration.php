<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaRegistration extends Model
{
    public const DOCUMENT_TYPES = \App\Support\SidangTaMeetRequirements::LABELS;

    public const DOCUMENT_DESCRIPTIONS = [
        'ta_data_capture' => 'Ambil dari menu TA/PA di iGracias.',
        'online_supervision_capture' => 'Tampilkan bimbingan online yang sudah disetujui.',
        'graduation_payment_proof' => 'Unggah bukti pembayaran wisuda dalam format PDF.',
        'ta_supervisor_sk' => 'Unggah SK pembimbing TA yang masih aktif dalam format PDF.',
        'thesis_draft' => 'PDF tanpa tanda tangan; logo dan tahun mengikuti template.',
        'eprt_waiver_letter' => 'Lengkapi Surat Permakluman EPRT lalu unggah dalam format PDF.',
        'diploma_statement' => 'Dokumen bermaterai fisik dan ditandatangani basah; unggah PDF.',
        'diploma_biodata_form' => 'Formulir biodata dummy ijazah; unggah PDF.',
        'pdm_submission_proof' => 'Unggah PDF bukti pengajuan Perubahan Data Mahasiswa.',
    ];

    protected $fillable = [
        'requirements_version',
        'sidang_ta_period_id', 'student_number', 'student_name', 'study_program',
        'student_phone', 'parent_phone', 'graduation_payment_date', 'pdm_submission_date',
        'declaration_accepted_at', 'declaration_version',
        'diploma_biodata_accurate', 'academic_snapshot', 'status',
        'submitted_by', 'reviewed_by', 'submitted_at', 'reviewed_at', 'review_notes',
    ];

    protected $attributes = [
        'requirements_version' => 2,
    ];

    protected $casts = [
        'requirements_version' => 'integer',
        'academic_snapshot' => 'array',
        'graduation_payment_date' => 'date',
        'pdm_submission_date' => 'date',
        'declaration_accepted_at' => 'datetime',
        'declaration_version' => 'integer',
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
