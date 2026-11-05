<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeAppealPeriod;
use Illuminate\Http\Request;

class GradeAppealPeriodController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'open_at'     => 'required|date',
            'close_at'    => 'required|date|after:open_at',
            'description' => 'nullable|string',
        ]);

        $isActive = $request->boolean('is_active');

        $period = GradeAppealPeriod::create([
            'name'        => $request->name,
            'open_at'     => $request->open_at,
            'close_at'    => $request->close_at,
            'description' => $request->description,
            'is_active'   => $isActive,
        ]);

        if ($isActive) {
            GradeAppealPeriod::where('id', '!=', $period->id)->update(['is_active' => false]);
        }

        return redirect()->back()->with('success', 'Periode pengajuan banding nilai berhasil ditambahkan.');
    }

    public function update(Request $request, GradeAppealPeriod $period)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'open_at'     => 'required|date',
            'close_at'    => 'required|date|after:open_at',
            'description' => 'nullable|string',
        ]);

        $isActive = $request->boolean('is_active');

        $period->update([
            'name'        => $request->name,
            'open_at'     => $request->open_at,
            'close_at'    => $request->close_at,
            'description' => $request->description,
            'is_active'   => $isActive,
        ]);

        if ($isActive) {
            GradeAppealPeriod::where('id', '!=', $period->id)->update(['is_active' => false]);
        }

        return redirect()->back()->with('success', 'Periode pengajuan banding nilai berhasil diperbarui.');
    }

    public function setActive(GradeAppealPeriod $period)
    {
        GradeAppealPeriod::query()->update(['is_active' => false]);
        $period->update(['is_active' => true]);

        return redirect()->back()->with('success', "Periode '{$period->name}' berhasil diaktifkan.");
    }

    public function destroy(GradeAppealPeriod $period)
    {
        $period->delete();
        return redirect()->back()->with('success', 'Periode pengajuan banding nilai berhasil dihapus.');
    }
}
