<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SidangTaFormGuideRequest;
use App\Models\SidangTaFormGuide;
use App\Services\SidangTaFormGuideService;
use App\Support\SidangTaFormGuideSlots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SidangTaFormGuideController extends Controller
{
    public function index()
    {
        return view('sidang_ta_guides.index', ['guides' => SidangTaFormGuide::orderBy('slot')->orderBy('sort_order')->orderBy('id')->paginate(25),
            'slots' => SidangTaFormGuideSlots::labels()]);
    }

    public function create()
    {
        return view('sidang_ta_guides.form', ['guide' => new SidangTaFormGuide(['is_active' => true, 'sort_order' => 0]),
            'slots' => SidangTaFormGuideSlots::labels()]);
    }

    public function store(SidangTaFormGuideRequest $request, SidangTaFormGuideService $service)
    {
        $service->save(new SidangTaFormGuide, $request->safe()->except('file'), $request->file('file'));

        return redirect()->route('admin.sidang-ta-guides.index')->with('success', 'Panduan berhasil ditambahkan.');
    }

    public function edit(SidangTaFormGuide $guide)
    {
        return view('sidang_ta_guides.form', ['guide' => $guide, 'slots' => SidangTaFormGuideSlots::labels()]);
    }

    public function update(SidangTaFormGuideRequest $request, SidangTaFormGuide $guide, SidangTaFormGuideService $service)
    {
        $service->save($guide, $request->safe()->except('file'), $request->file('file'));

        return redirect()->route('admin.sidang-ta-guides.index')->with('success', 'Panduan berhasil diperbarui.');
    }

    public function destroy(SidangTaFormGuide $guide, SidangTaFormGuideService $service)
    {
        $service->delete($guide);

        return redirect()->route('admin.sidang-ta-guides.index')->with('success', 'Panduan berhasil dihapus.');
    }

    public function media(Request $request, SidangTaFormGuide $guide)
    {
        $academic = $request->user()->hasRole('Akademik');
        abort_unless($academic || $request->user()->canAny(['sidang_ta_registration_read', 'sidang_ta_registration_review']), 403);
        abort_unless($academic || $guide->is_active, 404);
        abort_unless($guide->file_path && Storage::disk('local')->exists($guide->file_path), 404);

        return Storage::disk('local')->response($guide->file_path, $guide->file_name, [
            'Content-Type' => $guide->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
