<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSidangTaPublicId
{
    public static function bootHasSidangTaPublicId(): void
    {
        static::creating(function (Model $model) {
            $model->public_id = (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field === null && is_scalar($value) && ctype_digit((string) $value)) {
            // Keep already-issued links and open forms usable across deployment.
            // Controllers still authorize the resolved record before it is returned.
            request()->attributes->set('sidang_ta_legacy_url', true);

            return $query->where($this->getKeyName(), $value);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }
}
