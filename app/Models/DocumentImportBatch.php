<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mode',
        'status',
        'summary',
        'file_path',
    ];

    protected $casts = [
        'summary' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DocumentImportRow::class, 'batch_id')->orderBy('row_number');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isStaging(): bool
    {
        return $this->status === 'staging';
    }

    public function hasErrors(): bool
    {
        return $this->rows()->where('status', 'error')->exists();
    }
}
