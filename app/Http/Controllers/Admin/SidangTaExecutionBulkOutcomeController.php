<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SidangTaExecutionOutcomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SidangTaExecutionBulkOutcomeController extends Controller
{
    public function __invoke(Request $request, SidangTaExecutionOutcomeService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:passed,failed'],
            'participants' => ['required', 'array', 'min:1', 'max:15'],
            'participants.*' => ['required', 'integer', 'min:0'],
        ], [
            'status.in' => 'Pilih Lulus atau Gagal.',
            'participants.required' => 'Pilih minimal satu peserta.',
            'participants.max' => 'Pilih maksimal 15 peserta dalam satu penyimpanan.',
        ]);

        Validator::make(['participants' => array_keys($data['participants'])], [
            'participants.*' => ['uuid', 'distinct:ignore_case'],
        ])->validate();

        $versions = array_map('intval', array_change_key_case($data['participants'], CASE_LOWER));
        $count = $service->setMany($request->user(), $versions, $data['status']);

        return back()->with('success', 'Hasil sidang disimpan untuk '.$count.' peserta.');
    }
}
