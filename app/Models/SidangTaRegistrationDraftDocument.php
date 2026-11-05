<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidangTaRegistrationDraftDocument extends Model
{
    protected $fillable = [
        'draft_id', 'document_type', 'file_name', 'file_path',
        'file_size', 'file_type', 'source_document_id',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistrationDraft::class, 'draft_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistrationDocument::class, 'source_document_id');
    }
}
