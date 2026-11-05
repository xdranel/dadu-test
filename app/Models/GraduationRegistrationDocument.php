<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduationRegistrationDocument extends Model
{
    protected $fillable = [
        'graduation_registration_id',
        'document_type',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'status',
        'notes',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    // --- Relationships ---

    public function registration()
    {
        return $this->belongsTo(GraduationRegistration::class, 'graduation_registration_id');
    }

    // --- Accessors ---

    public function getDocumentTypeLabelAttribute(): string
    {
        return GraduationRegistration::DOCUMENT_TYPES[$this->document_type] ?? ucfirst(str_replace('_', ' ', $this->document_type));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'Menunggu',
            'verified' => 'Terverifikasi',
            'rejected' => 'Ditolak',
            default    => ucfirst($this->status),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'badge-warning',
            'verified' => 'badge-success',
            'rejected' => 'badge-danger',
            default    => 'badge-info',
        };
    }

    /**
     * Human-readable file size.
     */
    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) return '-';

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 1) . ' ' . $units[$unit];
    }
}
