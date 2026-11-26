<?php

namespace App\Services;

use App\Models\Lecturer;
use App\Models\SidangTaRegistration;
use App\Models\StudentFinalProject;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class SidangTaExecutionInfoService
{
    public const RESULTS = ['pending' => 'Belum ada hasil', 'passed' => 'Lulus', 'failed' => 'Gagal'];

    public function forRegistration(SidangTaRegistration $reg, bool $historical = false, bool $refreshSchedule = false): array
    {
        $context = $reg->roomSchedule()->with(['booking.occurrences', 'booking.room'])->first();
        $result = $context?->result_status ?? 'pending';
        $booking = $context?->booking;
        $occurrence = $booking?->occurrences->first();
        $finished = in_array($context?->state, ['completed', 'cancelled', 'rejected'], true)
            || ($occurrence && $occurrence->usage_date->toDateString().' '.$occurrence->end_time <= now()->format('Y-m-d H:i:s'));
        if (! $refreshSchedule && ($historical || $finished) && is_array($context?->details_snapshot) && $context->details_snapshot !== []) {
            return array_replace(['ta_type' => $context->taType()], $context->details_snapshot, ['result_status' => $result, 'result_label' => self::RESULTS[$result] ?? self::RESULTS['pending'], 'scheduling_state' => $context->state]);
        }
        $participant = $context?->participant_data ?? [];
        $academic = $reg->academic_snapshot ?? [];
        $project = StudentFinalProject::where('student_number', $reg->student_number)->first();
        $advisor = $this->lecturer($participant['advisor_1_code'] ?? $project?->advisor_1 ?? $academic['advisor_1'] ?? null);
        $confirmed = (in_array($context?->state, ['confirmed', 'completed'], true) || ($historical && $context?->was_confirmed)) && $booking?->status === 'approved' && $occurrence !== null;
        $date = $confirmed ? $occurrence->usage_date->format('Y-m-d') : $context?->preferred_date?->format('Y-m-d');

        return [
            'period' => $reg->period?->name,
            'student_number' => (string) $reg->student_number,
            'student_name' => $participant['student_name'] ?? $reg->student_name,
            'study_program' => $reg->study_program,
            'registration_status' => $reg->status,
            'expertise_group' => $participant['expertise_group'] ?? $advisor?->kk ?? $academic['advisor_1_kk'] ?? null,
            'title' => $participant['title'] ?? $project?->thesis_title_id ?? $project?->thesis_title_en ?? $academic['ta_title'] ?? $academic['ta_title_en'] ?? null,
            'advisor_1_code' => $participant['advisor_1_code'] ?? $advisor?->lecturer_code ?? $project?->advisor_1 ?? $academic['advisor_1'] ?? null,
            'advisor_2_code' => $participant['advisor_2_code'] ?? $this->code($project?->advisor_2 ?? $academic['advisor_2'] ?? null),
            'examiner_1_code' => $participant['examiner_1_code'] ?? $this->code($project?->examiner_1 ?? $academic['examiner_1'] ?? null),
            'examiner_2_code' => $participant['examiner_2_code'] ?? $this->code($project?->examiner_2 ?? $academic['examiner_2'] ?? null),
            'ta_type' => $context?->taType() ?? 'Individu',
            'weekday' => $date ? Carbon::parse($date)->locale('id')->translatedFormat('l') : null,
            'date' => $date,
            'start_time' => $this->time($confirmed ? $occurrence->start_time : $context?->preferred_start_time),
            'end_time' => $this->time($confirmed ? $occurrence->end_time : $context?->preferred_end_time),
            'room' => $confirmed ? $booking->room?->room_code : null,
            'room_id' => $confirmed ? $booking->room_id : null,
            'is_confirmed' => $confirmed,
            'result_status' => $result,
            'result_label' => self::RESULTS[$result] ?? self::RESULTS['pending'],
            'scheduling_state' => $context?->state ?? 'unassigned',
        ];
    }

    public function capture(SidangTaRegistration $reg, bool $refreshSchedule = false, bool $preserveParticipant = false): void
    {
        $context = $reg->roomSchedule()->first();
        if ($context) {
            $snapshot = $this->forRegistration($reg, $context->state === 'completed', $refreshSchedule);
            if ($preserveParticipant) {
                $snapshot = array_replace($snapshot, Arr::only($context->details_snapshot ?? [], [
                    'period', 'student_number', 'student_name', 'study_program', 'registration_status',
                    'expertise_group', 'title', 'advisor_1_code', 'advisor_2_code',
                ]));
            }
            $context->update(['details_snapshot' => $snapshot]);
        }
    }

    private function time(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    private function lecturer(?string $reference): ?Lecturer
    {
        if (! filled($reference)) {
            return null;
        }
        $byCode = Lecturer::where('lecturer_code', trim($reference))->get();
        if ($byCode->count() === 1) {
            return $byCode->first();
        }
        if ($byCode->isNotEmpty()) {
            return null;
        }
        $byName = Lecturer::where('name', trim($reference))->get();

        return $byName->count() === 1 ? $byName->first() : null;
    }

    private function code(?string $reference): ?string
    {
        return $this->lecturer($reference)?->lecturer_code ?? $reference;
    }
}
