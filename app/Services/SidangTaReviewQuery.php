<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SidangTaReviewQuery
{
    public const STATUSES = [
        'submitted' => 'Menunggu peninjauan', 'needs_review' => 'Perlu revisi',
        'approved' => 'Disetujui', 'rejected' => 'Ditolak',
    ];

    public function filters(Request $request): array
    {
        $values = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'integer', 'exists:sidang_ta_periods,id'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'study_program' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        unset($values['page']);
        foreach (['search', 'study_program'] as $key) {
            if (isset($values[$key])) $values[$key] = trim($values[$key]);
        }
        if (isset($values['period'])) $values['period'] = (int) $values['period'];
        return array_filter($values, fn ($value) => $value !== null && $value !== '');
    }

    public function query(array $filters): Builder
    {
        $query = SidangTaRegistration::query();
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

    public function counts(array $filters): array
    {
        $rows = $this->query($filters)->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $counts = ['total' => 0] + array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($rows as $status => $count) {
            $counts[$status] = (int) $count;
            $counts['total'] += (int) $count;
        }
        return $counts;
    }
}
