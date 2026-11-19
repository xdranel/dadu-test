<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use App\Models\SidangTaPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SidangTaReviewQuery
{
    public const STATUSES = [
        'submitted' => 'Menunggu peninjauan', 'needs_review' => 'Perlu revisi',
        'approved' => 'Disetujui', 'rejected' => 'Ditolak',
    ];

    public function filters(Request $request): array
    {
        $input = $request->all();
        if (isset($input['period']) && is_string($input['period']) && Str::isUuid($input['period'])) {
            $input['period'] = SidangTaPeriod::where('public_id', $input['period'])->value('id') ?? $input['period'];
        }
        $values = Validator::make($input, [
            'search' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'integer', 'exists:sidang_ta_periods,id'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'study_program' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ])->validate();
        unset($values['page']);
        foreach (['search', 'study_program'] as $key) {
            if (isset($values[$key])) $values[$key] = trim($values[$key]);
        }
        if (isset($values['period'])) $values['period'] = (int) $values['period'];
        return array_filter($values, fn ($value) => $value !== null && $value !== '');
    }

    public function urlFilters(array $filters): array
    {
        if (isset($filters['period'])) {
            $filters['period'] = SidangTaPeriod::whereKey($filters['period'])->value('public_id');
        }

        return $filters;
    }

    public function query(array $filters, ?array $programScope = null): Builder
    {
        $query = SidangTaRegistration::query();
        if ($programScope !== null) {
            $column = $query->getConnection()->getDriverName() === 'mysql'
                ? DB::raw('CAST(study_program AS BINARY)') : 'study_program';
            $query->whereIn($column, $programScope);
        }
        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(fn (Builder $q) => $q->where('student_name', 'like', "%{$term}%")
                ->orWhere('student_number', 'like', "%{$term}%"));
        }
        if (isset($filters['period'])) $query->where('sidang_ta_period_id', $filters['period']);
        if (isset($filters['status'])) $query->where('status', $filters['status']);
        if (isset($filters['study_program'])) $query->where('study_program', $filters['study_program']);
        return $query;
    }

    public function counts(array $filters, ?array $programScope = null): array
    {
        $rows = $this->query($filters, $programScope)->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $counts = ['total' => 0] + array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($rows as $status => $count) {
            $counts[$status] = (int) $count;
            $counts['total'] += (int) $count;
        }
        return $counts;
    }
}
