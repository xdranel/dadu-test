<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'row_number',
        'original_name',
        'data',
        'status',
        'messages',
        'document_id',
        'applied_at',
    ];

    protected $casts = [
        'data'       => 'array',
        'messages'   => 'array',
        'applied_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocumentImportBatch::class, 'batch_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
