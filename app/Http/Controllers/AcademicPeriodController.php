<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Room;
use App\Models\RoomBookingOccurrence;
use App\Models\SidangTaExecutionSetting;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicPeriodController extends Controller
{
    public function index()
    {
        return view('academic_periods.index', ['periods' => AcademicPeriod::latestPeriod()->get()]);
    }

    public function store(Request $request)
    {
        $values = $this->validated($request);
        AcademicPeriod::create($values + ['is_active' => false]);

        return redirect()->route('academic_periods.index')->with('success', 'Periode akademik ditambahkan. Pilih Aktifkan jika periode ini sedang berjalan.');
    }

    public function update(Request $request, AcademicPeriod $academicPeriod)
    {
        $values = $this->validated($request, $academicPeriod);
        DB::transaction(function () use ($academicPeriod, $values) {
            $period = AcademicPeriod::whereKey($academicPeriod->id)->lockForUpdate()->firstOrFail();
            if (($period->year !== $values['year'] || $period->semester !== (int) $values['semester'])
                && ($period->studentCourses()->exists() || $period->courses()->exists()
                    || $period->studentActivities()->exists() || $period->studentEprts()->exists()
                    || $period->roomPatterns()->exists() || SidangTaExecutionSetting::where('academic_period_id', $period->id)->exists())) {
                throw ValidationException::withMessages(['year' => 'Tahun/term periode yang telah dipakai tidak dapat diubah. Buat periode yang benar tanpa mengubah riwayat.']);
            }
            Room::orderBy('id')->lockForUpdate()->get();
            $settings = SidangTaExecutionSetting::where('academic_period_id', $period->id)->get();
            if ($settings->contains(fn ($s) => $s->start_date->toDateString() < $values['start_date'] || $s->end_date->toDateString() > $values['end_date'])) {
                throw ValidationException::withMessages(['end_date' => 'Tanggal periode harus mencakup jadwal pelaksanaan Sidang TA yang tersimpan.']);
            }
            $outside = RoomBookingOccurrence::whereHas('request', fn ($q) => $q->whereIn('status', ['pending', 'approved']))->whereDate('usage_date', '>=', max(today()->toDateString(), $period->start_date->toDateString()))->whereDate('usage_date', '<=', $period->end_date->toDateString())->where(fn ($q) => $q->whereDate('usage_date', '<', $values['start_date'])->orWhereDate('usage_date', '>', $values['end_date']))->exists();
            if ($outside) {
                throw ValidationException::withMessages(['end_date' => 'Perubahan tanggal menghilangkan cakupan peminjaman aktif.']);
            }
            $conflicts = app(RoomAvailabilityService::class)->conflictingBookings($period->roomPatterns()->get()->toArray(), $values['start_date'], $values['end_date']);
            if ($conflicts) {
                throw ValidationException::withMessages(['end_date' => 'Perubahan tanggal menimbulkan bentrok pola mingguan dengan peminjaman aktif.']);
            }
            $period->update($values);
        }, 3);

        return redirect()->route('academic_periods.index')->with('success', 'Periode akademik diperbarui.');
    }

    public function activate(AcademicPeriod $academicPeriod)
    {
        DB::transaction(function () use ($academicPeriod) {
            // Every activation locks the same nonempty set in the same order.
            $periods = AcademicPeriod::orderBy('id')->lockForUpdate()->get();
            abort_unless($periods->contains('id', $academicPeriod->id), 404);
            AcademicPeriod::where('is_active', true)->whereKeyNot($academicPeriod->id)->update(['is_active' => false]);
            AcademicPeriod::whereKey($academicPeriod->id)->update(['is_active' => true]);
        }, 3);

        return redirect()->route('academic_periods.index')->with('success', 'Periode akademik aktif telah dipilih. KRS lama yang belum tertaut tetap perlu ditinjau melalui impor ulang.');
    }

    private function validated(Request $request, ?AcademicPeriod $period = null): array
    {
        return $request->validate([
            'year' => ['required', 'string', 'regex:/^(20\d{2})\/(20\d{2})$/D',
                function ($attribute, $value, $fail) {
                    $parts = explode('/', $value);
                    if (count($parts) !== 2 || (int) $parts[1] !== (int) $parts[0] + 1) {
                        $fail('Tahun akademik harus dua tahun berurutan.');
                    }
                }, Rule::unique('academic_periods', 'year')->where(fn ($q) => $q->where('semester', $request->input('semester')))->ignore($period)],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            'label' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
    }
}
