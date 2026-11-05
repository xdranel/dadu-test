<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $rooms = Room::search($request->search)
            ->filterBuilding($request->gedung)
            ->filterStatus($request->status)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $gedungList = Room::select('building')
            ->distinct()
            ->orderBy('building')
            ->pluck('building');

        return view('rooms.index', compact('rooms', 'gedungList'));
    }

    public function export(Request $request)
    {
        $data = Room::search($request->search)
            ->filterBuilding($request->gedung)
            ->filterStatus($request->status)
            ->latest()
            ->get();

        $filename = 'room-' . now()->format('Y-m-d') . '.xlsx';

        return (new FastExcel($data))->download($filename, function ($r) {
            return [
                'Room Code' => $r->room_code,
                'Name'      => $r->name,
                'Building'  => $r->building,
                'Capacity'  => $r->capacity,
                'Status'    => $r->status,
            ];
        });
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $imported = 0;
        $skipped  = 0;

        try {
            (new FastExcel)->import($request->file('file'), function ($row) use (&$imported, &$skipped) {
                $normalizedRow = [];
                foreach ($row as $key => $value) {
                    $normalizedKey = str_replace(' ', '_', strtolower(trim($key)));
                    $normalizedRow[$normalizedKey] = $value;
                }

                $code     = $normalizedRow['room_code'] ?? $normalizedRow['kode_ruang'] ?? null;
                $name     = $normalizedRow['name'] ?? $normalizedRow['nama_ruang'] ?? null;
                $building = $normalizedRow['building'] ?? $normalizedRow['lokasi_gedung'] ?? '-';
                $capacity = $normalizedRow['capacity'] ?? $normalizedRow['kapasitas'] ?? 0;

                if (empty($code) || empty($name)) return null;

                $statusInput = strtolower(trim($normalizedRow['status'] ?? ''));
                $statusDb = 'Dapat Dipakai';
                if (in_array($statusInput, ['tidak dapat dipakai', 'tidak aktif', 'non aktif', 'non-aktif', 'nonaktif', 'tidakaktif', 'rusak', '0', 'inactive', 'off'])) {
                    $statusDb = 'Tidak Dapat Dipakai';
                }

                Room::updateOrCreate(
                    ['room_code' => strtoupper($code)],
                    [
                        'name'      => $name,
                        'building'  => $building,
                        'capacity'  => is_numeric($capacity) ? $capacity : 0,
                        'status'    => $statusDb,
                    ]
                );

                $imported++;
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }

        $message = "Import selesai: {$imported} ruangan berhasil ditambahkan/diperbarui.";

        return redirect()->route('rooms.index')->with('success', $message);
    }

    public function create()
    {
        return view('rooms.create');
    }

    public function store(StoreRoomRequest $request)
    {
        Room::create($request->validated());

        return redirect()->route('rooms.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Room $room)
    {
        return view('rooms.edit', compact('room'));
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        $room->update($request->validated());

        return redirect()->route('rooms.index')
            ->with('success', "Data {$room->name} berhasil diperbarui.");
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()->route('rooms.index')
            ->with('success', 'Ruangan berhasil dihapus.');
    }
}
