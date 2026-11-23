<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Support\WeeklyRoomScheduleNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $listQuery = $this->listQuery($request->query());
        $rooms = Room::search($request->search)
            ->filterBuilding($request->gedung)
            ->filterStatus($request->status)
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $gedungList = Room::select('building')
            ->distinct()
            ->orderBy('building')
            ->pluck('building');

        return view('rooms.index', compact('rooms', 'gedungList', 'listQuery'));
    }

    public function export(Request $request)
    {
        $data = Room::search($request->search)
            ->filterBuilding($request->gedung)
            ->filterStatus($request->status)
            ->latest()
            ->get();

        $filename = 'room-'.now()->format('Y-m-d').'.xlsx';

        return (new FastExcel($data))->download($filename, function ($r) {
            return [
                'Room Code' => $r->room_code,
                'Name' => $r->name,
                'Building' => $r->building,
                'Capacity' => $r->capacity,
                'Label' => $r->label,
                'Status' => $r->status,
            ];
        });
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        try {
            $rows = (new FastExcel)->import($request->file('file'));
            $imported = DB::transaction(function () use ($rows) {
                AcademicPeriod::orderBy('id')->lockForUpdate()->get(['id']);
                $roomKey = WeeklyRoomScheduleNormalizer::roomKey(...);
                $rooms = Room::orderBy('id')->lockForUpdate()->get()->groupBy(fn (Room $room) => $roomKey($room->room_code));
                $count = 0;
                foreach ($rows as $row) {
                    $normalized = [];
                    foreach ($row as $key => $value) {
                        $normalized[str_replace(' ', '_', strtolower(trim($key)))] = trim((string) $value);
                    }

                    $code = strtoupper($normalized['room_code'] ?? $normalized['kode_ruang'] ?? '');
                    if ($code === '') {
                        continue;
                    }
                    $key = $roomKey($code);
                    $matches = $rooms->get($key);
                    if ($key === '' || ($matches && $matches->count() > 1)) {
                        throw new \InvalidArgumentException("Kode ruang {$code} tidak valid atau ambigu pada master ruangan.");
                    }

                    $label = strtoupper($normalized['label'] ?? '');
                    if ($label !== '' && ! in_array($label, Room::LABELS, true)) {
                        throw new \InvalidArgumentException("Label ruang {$code} harus KELAS, LAB, atau kosong.");
                    }

                    $room = $matches?->first() ?? new Room(['name' => '', 'building' => '', 'capacity' => 0]);
                    $status = strtolower($normalized['status'] ?? '');
                    $inactive = in_array($status, ['tidak dapat dipakai', 'tidak aktif', 'non aktif', 'non-aktif', 'nonaktif', 'tidakaktif', 'rusak', '0', 'inactive', 'off'], true);
                    $capacity = $normalized['capacity'] ?? $normalized['kapasitas'] ?? '';
                    $room->fill(array_filter([
                        'room_code' => $code,
                        'name' => $normalized['name'] ?? $normalized['nama_ruang'] ?? '',
                        'building' => $normalized['building'] ?? $normalized['lokasi_gedung'] ?? '',
                        'capacity' => is_numeric($capacity) ? (int) $capacity : '',
                        'label' => $label,
                        'status' => $status === '' ? '' : ($inactive ? 'Tidak Dapat Dipakai' : 'Dapat Dipakai'),
                    ], static fn ($value) => $value !== ''))->save();
                    $rooms->put($key, collect([$room]));
                    $count++;
                }

                return $count;
            }, 3);
        } catch (\Exception $e) {
            return back()->with('error', 'Error: '.$e->getMessage());
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
        $data = $request->validated();
        $data['name'] ??= $data['room_code'];
        Room::create($data);

        return redirect()->route('rooms.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Request $request, Room $room)
    {
        $listQuery = $this->listQuery($request->query('list'));

        return view('rooms.edit', compact('room', 'listQuery'));
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        $data = $request->validated();
        if (! isset($data['name'])) {
            unset($data['name']);
        }
        $room->update($data);

        return redirect()->route('rooms.index', $this->listQuery($request->query('list')))
            ->with('success', "Data {$room->room_code} berhasil diperbarui.");
    }

    private function listQuery(mixed $query): array
    {
        if (! is_array($query)) {
            return [];
        }

        return array_filter(
            array_intersect_key($query, array_flip(['page', 'search', 'gedung', 'status'])),
            fn ($value) => is_string($value) && $value !== '',
        );
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()->route('rooms.index')
            ->with('success', 'Ruangan berhasil dihapus.');
    }
}
