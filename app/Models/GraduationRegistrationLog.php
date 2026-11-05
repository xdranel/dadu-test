<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduationRegistrationLog extends Model
{
    protected $fillable = [
        'graduation_registration_id',
        'action',
        'from_status',
        'to_status',
        'notes',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    // --- Relationships ---

    public function registration()
    {
        return $this->belongsTo(GraduationRegistration::class, 'graduation_registration_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // --- Accessors ---

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created'            => 'Dibuat',
            'updated'            => 'Diperbarui',
            'submitted'          => 'Diajukan',
            'approved'           => 'Disetujui',
            'rejected'           => 'Ditolak',
            'revision_requested' => 'Diminta Revisi',
            'resubmitted'        => 'Diajukan Ulang',
            default              => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            'created'            => 'text-slate-400',
            'updated'            => 'text-blue-400',
            'submitted'          => 'text-sky-500',
            'approved'           => 'text-emerald-500',
            'rejected'           => 'text-rose-500',
            'revision_requested' => 'text-amber-500',
            'resubmitted'        => 'text-sky-500',
            default              => 'text-slate-400',
        };
    }
}
