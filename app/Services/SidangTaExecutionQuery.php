<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SidangTaExecutionQuery
{
    public const STATUSES = ['unassigned' => 'Belum dijadwalkan', 'scheduled' => 'Terjadwal', 'passed' => 'Lulus', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan'];

    public function filters(Request $request, bool $scoped = false): array
    {
        $input = $request->only('search', 'period', 'status', 'study_program');
        if ($scoped) {
            unset($input['study_program']);
        }
        $values = validator($input, ['search' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'uuid', 'exists:sidang_ta_periods,public_id'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'study_program' => ['nullable', 'string', 'max:255']])->validate();

        return array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $values), fn ($v) => filled($v));
    }

    public function query(array $filters, ?array $scope = null): Builder
    {
        $q = SidangTaRegistration::query()->where(fn ($q) => $q->where('status', 'approved')->orWhereHas('roomSchedule'));
        if ($scope !== null) {
            $column = $q->getConnection()->getDriverName() === 'mysql' ? DB::raw('CAST(study_program AS BINARY)') : 'study_program';
            $q->whereIn($column, $scope);
        }
        if (isset($filters['search'])) {
            $q->where(fn ($q) => $q->where('student_name', 'like', '%'.$filters['search'].'%')->orWhere('student_number', 'like', '%'.$filters['search'].'%'));
        }
        if (isset($filters['period'])) {
            $q->whereHas('period', fn ($q) => $q->where('public_id', $filters['period']));
        }
        if (isset($filters['study_program'])) {
            $q->where('study_program', $filters['study_program']);
        }
        if (isset($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'unassigned') {
                $q->where(fn ($q) => $q->whereDoesntHave('roomSchedule')->orWhereHas('roomSchedule', fn ($q) => $q->whereNotIn('state', ['confirmed', 'completed', 'cancelled', 'rejected'])->where(fn ($q) => $q->whereNull('result_status')->orWhere('result_status', 'pending'))));
            } elseif ($status === 'cancelled') {
                $q->whereHas('roomSchedule', fn ($q) => $q->whereIn('state', ['cancelled', 'rejected']));
            } elseif ($status === 'scheduled') {
                $q->whereHas('roomSchedule', fn ($q) => $q->whereIn('state', ['confirmed', 'completed'])->where(fn ($q) => $q->whereNull('result_status')->orWhere('result_status', 'pending')));
            } else {
                $q->whereHas('roomSchedule', fn ($q) => $q->where('result_status', $status)->whereNotIn('state', ['cancelled', 'rejected']));
            }
        }

        return $q;
    }

    public function counts(array $filters, ?array $scope = null): array
    {
        $counts = ['total' => $this->query($filters, $scope)->count()];
        foreach (self::STATUSES as $status => $label) {
            $counts[$status] = isset($filters['status']) && $filters['status'] !== $status ? 0 : $this->query(array_merge($filters, ['status' => $status]), $scope)->count();
        }

        return $counts;
    }
}
