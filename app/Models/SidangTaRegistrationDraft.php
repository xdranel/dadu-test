<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidangTaRegistrationDraft extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'requirements_version',
        'owner_user_id', 'student_number', 'sidang_ta_period_id',
        'source_registration_id', 'source_version', 'payload', 'version',
        'reviewed_version', 'status', 'submitted_registration_id',
    ];

    protected $attributes = [
        'requirements_version' => 1,
        'payload' => '{}', 'version' => 1, 'status' => self::STATUS_ACTIVE,
    ];

    protected $casts = [
        'requirements_version' => 'integer',
        'payload' => 'array', 'version' => 'integer',
        'source_version' => 'integer', 'reviewed_version' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SidangTaPeriod::class, 'sidang_ta_period_id');
    }

    public function sourceRegistration(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistration::class, 'source_registration_id');
    }

    public function submittedRegistration(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistration::class, 'submitted_registration_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SidangTaRegistrationDraftDocument::class, 'draft_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_user_id', $user->getKey());
    }
}
