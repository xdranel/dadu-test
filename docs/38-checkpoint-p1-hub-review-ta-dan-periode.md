# 38 — P1 petugas: satu pintu Review Sidang TA dan kelola periode

**Goal:** satu menu Review Sidang TA menuju hub; pengelola periode dapat masuk tanpa mendapat akses pengajuan mahasiswa. Halaman periode mempunyai tombol kembali dan kontrol sesuai permission.

**Architecture:** sidebar admin mendukung permissions_any secara eksplisit dan active beberapa pola. Route hub memakai OR review/period_read; query serta tabel pengajuan hanya dijalankan reviewer. Backend periode/multi-periode tetap digunakan.

**Tech stack:** Laravel13, Spatie Permission existing, Blade/Tailwind/Alpine, Pest, MySQL Podman. Tidak ada migrasi/dependency atau permission baru.

**Spec:** [31 bagian5](31-rancangan-draft-parsial-dan-review-petugas-ta.md#5-satu-pintu-review-sidang-ta-dan-pengaturan-periode), telah disetujui pengguna. Bagian student selesai pada [37](37-penutupan-student-setelah-commit.md), commit1414aa6.

## 1. Batas P1 dan hasil scan

Source TA/sidebar yang relevan kedua folder sama sebelum penulisan. Student126 tetap baseline. Ada tiga lokasi penanda active pada sidebar admin yang harus menangani array pola. Validator sidebar juga harus memahami permission OR agar konfigurasi baru tetap dapat diperiksa.

| Izin akun | Hub Review TA | Pengajuan/detail/keputusan | Kelola periode |
| --- | --- | --- | --- |
| registration_review saja | Ya | Ya | Tidak |
| period_read saja | Ya | Tidak; query/tabel pengajuan tidak dijalankan | Ya, baca saja kecuali ada create/update |
| Keduanya | Ya | Ya | Ya sesuai create/update |
| Tidak keduanya | Ditolak; menu tersembunyi | Tidak | Tidak |

Nama permission lengkap tetap sidang_ta_registration_review dan sidang_ta_period_read. Tidak memberikan izin otomatis atau mengunci berdasarkan nama role Petugas. Role aktif dan role switch existing dipertahankan.

Untuk akun pengelola periode, berikan period_read lalu tambah period_create/period_update sesuai tugasnya melalui pengaturan role. Memiliki create/update saja tidak otomatis memberi izin membaca halaman atau menampilkan menu. Permission delete yang sudah ada tetap bekerja pada route existing, tetapi P1 tidak menambahkan tombol hapus.

P1 belum mengubah tabel menjadi matriks ikon, statistik/filter lanjutan, ekspor atau verifikasi dokumen. Itu P2a/P2b/P3. Tombol keputusan existing masih mengikuti perilaku sebelum P3; jangan menganggap verifikasi per dokumen sudah tersedia.

## 2. File dan urutan

Edit existing:

```text
config/sidebar.php
resources/views/components/sidebar-admin.blade.php
app/Console/Commands/ValidateSidebarConfig.php
routes/web.php
app/Http/Controllers/SidangTaRegistrationController.php
resources/views/sidang_ta_registrations/admin/index.blade.php
resources/views/sidang_ta_registrations/admin/periods.blade.php
```

Buat satu test baru:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:test SidangTaStaffNavigationTest --pest
```

- [ ] Isi test lengkap bagian9 dahulu dan jalankan fokus bagian10 sebelum mengubah aplikasi. Kegagalan akses/menu/permission baru memang diharapkan; kegagalan koneksi bukan regresi yang dimaksud.
- [ ] Terapkan bagian3–8. File existing diedit, jangan memakai generator dengan --force.
- [ ] Jalankan fokus, suite student+P1, regresi permission/role, Node/build dan manual.
- [ ] Salin seluruh tujuh file existing beserta test baru setelah lulus, lalu minta rescan sebelum P2a.

## 3. Satu item sidebar

Pada config/sidebar.php, ganti dua item Review Sidang TA dan Periode Sidang TA dalam grup akademik menjadi **satu item** berikut:

```php
[
    'label' => 'Review Sidang TA',
    'route' => 'admin.sidang-ta-registrations.index',
    'active' => ['admin.sidang-ta-registrations.*', 'admin.sidang-ta-periods.*'],
    'permissions_any' => ['sidang_ta_registration_review', 'sidang_ta_period_read'],
],
```

Jangan sisakan permission review tunggal pada item baru, atau item Periode Sidang TA terpisah. Item lain tetap. Tambahkan penjelasan pada komentar struktur config: active boleh string/array pada renderer admin; permissions_any adalah array OR nonkosong khusus renderer admin, diperiksa lebih dahulu daripada permission/roles lama. Item mahasiswa tidak berubah dan flat renderer tidak ditambah perilaku baru pada P1.

## 4. Renderer admin: OR dan active

Pada resources/views/components/sidebar-admin.blade.php, ganti **blok @php pertama saja**, sampai @endphp sebelum komentar Standalone items, dengan berikut:

```blade
@php
    $user = auth()->user();
    $filterItems = function (array $items) use ($user) {
        return array_values(array_filter($items, function ($item) use ($user) {
            $perm = $item['permission'] ?? null;
            $roles = $item['roles'] ?? null;
            $route = $item['route'] ?? null;
            if ($route && ! \Illuminate\Support\Facades\Route::has($route)) return false;
            if (array_key_exists('permissions_any', $item)) {
                $any = $item['permissions_any'];
                return is_array($any) && $any !== [] && $user->canAny($any);
            }
            if ($perm && $user->can($perm)) return true;
            if ($roles && $user->hasAnyRole($roles)) return true;
            return is_null($perm) && is_null($roles);
        }));
    };
    $standaloneItems = $filterItems($standalone ?? []);
    $visibleGroups = [];
    foreach ($groups as $key => $group) {
        $items = $filterItems($group['items'] ?? []);
        if (count($items) > 0) {
            $group['items'] = $items;
            $visibleGroups[$key] = $group;
        }
    }
    $activeGroupKey = null;
    foreach ($visibleGroups as $k => $g) {
        foreach ($g['items'] as $it) {
            if (request()->routeIs(...(array) ($it['active'] ?? $it['route']))) {
                $activeGroupKey = $k;
                break 2;
            }
        }
    }
@endphp
```

Key permissions_any yang tidak cocok/kosong langsung false; tidak jatuh ke fallback item publik. Untuk item tanpa key baru, semantik permission/roles lama tetap OR seperti sebelumnya.

Masih pada file yang sama, terdapat **dua** blok `$isActive` lama (standalone dan item grup). Ganti masing-masing ternary lama dengan:

```php
$isActive = request()->routeIs(...(array) ($item['active'] ?? $item['route']));
```

Dengan blok awal dan dua penggantian ini, ketiga lokasi sudah mendukung array. Jangan hanya mengubah warna link tetapi melupakan auto-open accordion.

Pada **kedua anchor** yang dimulai `<a href="{{ route($item['route']) }}"`, tambahkan atribut kondisi berikut sebelum class, tetap dalam opening tag anchor:

```blade
@if($isActive) aria-current="page" @endif
```

Contoh bentuk opening tag:

```blade
<a href="{{ route($item['route']) }}"
   @if($isActive) aria-current="page" @endif
   class="...kelas existing tetap...">
```

Contoh terakhir hanya menunjukkan letak atribut; **jangan mengganti kelas existing dengan teks placeholder**. Icon, badge, markup dan Alpine accordion lainnya tidak diubah.

## 5. Validator sidebar

Pada app/Console/Commands/ValidateSidebarConfig.php, ganti seluruh closure `$check = function (...) { ... };` di dalam handle dengan berikut. Bagian loop config dan pelaporan error setelahnya tetap:

```php
$check = function (array $item) use (&$errors, &$checked, $permissions): void {
    $checked++;
    $label = $item['label'] ?? '(tanpa label)';
    $route = $item['route'] ?? null;
    if ($route && ! Route::has($route)) {
        $errors[] = "Route tidak terdaftar: '{$route}' (item: {$label})";
    }
    $perm = $item['permission'] ?? null;
    if ($perm && ! in_array($perm, $permissions, true)) {
        $errors[] = "Permission tidak terdaftar: '{$perm}' (item: {$label})";
    }
    if (array_key_exists('permissions_any', $item)) {
        $any = $item['permissions_any'];
        if (! is_array($any) || $any === []) {
            $errors[] = "permissions_any harus berupa array nonkosong (item: {$label})";
        } else {
            foreach ($any as $name) {
                if (! is_string($name) || trim($name) === '') {
                    $errors[] = "permissions_any harus berisi nama permission (item: {$label})";
                } elseif (! in_array($name, $permissions, true)) {
                    $errors[] = "Permission tidak terdaftar: '{$name}' (item: {$label})";
                }
            }
        }
    }
};
```

Tidak ada seeder baru; seluruh permission TA sudah ada pada instalasi yang digunakan. Command sidebar:validate memeriksa seluruh config terhadap database yang sedang dipakai, bukan otomatis menambah permission.

## 6. Route hub dan controller

Di routes/web.php, ganti **hanya** grup review-only TA yang berisi tiga route adminIndex/adminShow/review dengan berikut. Grup route periode setelahnya tetap beserta middleware create/update/delete masing-masing:

```php
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('sidang-ta-registrations', [SidangTaRegistrationController::class, 'adminIndex'])
        ->middleware('permission:sidang_ta_registration_review|sidang_ta_period_read')
        ->name('sidang-ta-registrations.index');
    Route::get('sidang-ta-registrations/{sidang_ta_registration}', [SidangTaRegistrationController::class, 'adminShow'])
        ->middleware('permission:sidang_ta_registration_review')
        ->name('sidang-ta-registrations.show');
    Route::post('sidang-ta-registrations/{sidang_ta_registration}/review', [SidangTaRegistrationController::class, 'review'])
        ->middleware('permission:sidang_ta_registration_review')
        ->name('sidang-ta-registrations.review');
});
```

**Hapus pembungkus middleware review-only lama** saat mengganti grup. Menambahkan OR di child sementara parent tetap review-only tidak akan memberi akses period-only. Tidak ada route nama sama yang ditambahkan dua kali.

Ganti hanya method adminIndex di SidangTaRegistrationController dengan:

```php
public function adminIndex(Request $request)
{
    $canReview = $request->user()->can('sidang_ta_registration_review');
    $periods = SidangTaPeriod::latest()->get();
    $registrations = null;
    if ($canReview) {
        $query = SidangTaRegistration::with('period')->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('period')) $query->where('sidang_ta_period_id', $request->period);
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn ($q) => $q->where('student_name', 'like', "%{$term}%")
                ->orWhere('student_number', 'like', "%{$term}%"));
        }
        $registrations = $query->paginate(15)->withQueryString();
    }
    return view('sidang_ta_registrations.admin.index', compact('canReview', 'registrations', 'periods'));
}
```

Period-only tidak menjalankan query pengajuan, bukan sekadar menyembunyikan tabel di Blade. Tidak mengubah method keputusan, preview, store/update/delete periode ataupun finalisasi.

## 7. Hub view lengkap

Ganti seluruh resources/views/sidang_ta_registrations/admin/index.blade.php:

```blade
<x-app-layout>
    <x-slot name="header">Review Pendaftaran Sidang TA</x-slot>
    <div data-ta-review-hub class="space-y-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-800">Review Sidang TA</h2>
                <p class="mt-1 text-sm text-slate-500">Pendaftaran sidang tugas akhir dan pengaturan periode.</p>
            </div>
            @can('sidang_ta_period_read')
                <a data-manage-ta-periods href="{{ route('admin.sidang-ta-periods.index') }}" class="btn btn-secondary">Kelola Periode</a>
            @endcan
        </header>
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($canReview)
            <div data-ta-review-data class="space-y-5">
                <form method="GET" action="{{ route('admin.sidang-ta-registrations.index') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
                    <input class="campus-input" name="search" value="{{ request('search') }}" aria-label="Cari nama atau NIM" placeholder="Cari nama atau NIM">
                    <select class="campus-select" name="period" aria-label="Periode">
                        <option value="">Semua periode</option>
                        @foreach($periods as $period)
                            <option value="{{ $period->id }}" @selected(request('period') == $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                    <select class="campus-select" name="status" aria-label="Status pengajuan">
                        <option value="">Semua status</option>
                        @foreach(['submitted' => 'Menunggu', 'needs_review' => 'Perlu revisi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>
                                <th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Periode</th>
                                <th class="px-4 py-3">Dikirim</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Aksi</th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($registrations as $registration)
                                    <tr>
                                        <td class="px-4 py-4"><p class="font-semibold text-slate-800">{{ $registration->student_name }}</p><p class="text-xs text-slate-500">{{ $registration->student_number }} · {{ $registration->study_program }}</p></td>
                                        <td class="px-4 py-4">{{ $registration->period?->name }}</td>
                                        <td class="px-4 py-4">{{ $registration->submitted_at?->translatedFormat('d M Y H:i') }}</td>
                                        <td class="px-4 py-4"><x-status-badge :status="$registration->status" :label="match($registration->status) {'submitted' => 'Menunggu', 'needs_review' => 'Perlu revisi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', default => ucfirst($registration->status)}" /></td>
                                        <td class="px-4 py-4 text-right"><a class="btn btn-secondary text-xs" href="{{ route('admin.sidang-ta-registrations.show', $registration) }}">Tinjau</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada pengajuan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 px-4 py-3">{{ $registrations->links() }}</div>
                </div>
            </div>
        @else
            <section data-ta-period-only class="campus-card space-y-2 p-5">
                <h3 class="font-bold text-slate-800">Pengaturan periode pendaftaran</h3>
                <p class="text-sm leading-relaxed text-slate-500">Gunakan Kelola Periode untuk melihat jadwal pendaftaran sidang tugas akhir.</p>
            </section>
        @endif
    </div>
</x-app-layout>
```

## 8. Periode view lengkap

Ganti seluruh resources/views/sidang_ta_registrations/admin/periods.blade.php. Semua form tetap di dalam x-app-layout; tidak ada include sebelum layout.

```blade
<x-app-layout>
    <x-slot name="header">Pengaturan Periode Sidang TA</x-slot>
    @php
        $at = now();
    @endphp
    <div class="mx-auto max-w-5xl space-y-5">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="max-w-2xl">
                <h2 class="text-xl font-bold text-slate-800">Periode & Tenggat Pendaftaran</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">Beberapa periode dapat aktif bersamaan. Mahasiswa memilih periode aktif yang sedang berada dalam rentang pendaftaran.</p>
            </div>
            <a data-back-to-ta-review href="{{ route('admin.sidang-ta-registrations.index') }}" class="btn btn-secondary">Kembali ke Review Sidang TA</a>
        </header>
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Periksa data periode:</p>
                <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @can('sidang_ta_period_create')
            <form data-ta-period-create method="POST" action="{{ route('admin.sidang-ta-periods.store') }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2">
                @csrf
                <h3 class="font-bold md:col-span-2">Tambah periode</h3>
                <label class="campus-label">Nama periode <input class="campus-input mt-1" name="name" value="{{ old('name') }}" required maxlength="255"></label>
                <label class="campus-label">Keterangan <input class="campus-input mt-1" name="description" value="{{ old('description') }}" maxlength="2000"></label>
                <label class="campus-label">Dibuka <input class="campus-input mt-1" type="datetime-local" name="open_at" value="{{ old('open_at') }}" required></label>
                <label class="campus-label">Tenggat <input class="campus-input mt-1" type="datetime-local" name="close_at" value="{{ old('close_at') }}" required></label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active'))> Aktifkan setelah dibuat</label>
                <div class="md:col-span-2"><button type="submit" class="btn btn-primary">Simpan periode</button></div>
            </form>
        @endcan
        <div class="space-y-4">
            @forelse($periods as $period)
                @php
                    $state = match (true) {
                        !$period->is_active => 'inactive',
                        $at->lt($period->open_at) => 'upcoming',
                        $at->gt($period->close_at) => 'closed',
                        default => 'open',
                    };
                    $stateLabel = ['inactive' => 'Tidak aktif', 'upcoming' => 'Belum dibuka', 'closed' => 'Ditutup', 'open' => 'Sedang terbuka'][$state];
                    $stateClass = ['inactive' => 'bg-slate-100 text-slate-700', 'upcoming' => 'bg-sky-100 text-sky-800', 'closed' => 'bg-rose-100 text-rose-800', 'open' => 'bg-emerald-100 text-emerald-800'][$state];
                @endphp
                <article data-ta-period-id="{{ $period->id }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0"><h3 class="break-words font-bold text-slate-800">{{ $period->name }}</h3><p class="mt-1 break-words text-xs text-slate-500">{{ $period->description ?: 'Tidak ada keterangan.' }}</p></div>
                        <span data-period-state="{{ $state }}" class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $stateClass }}">{{ $stateLabel }}</span>
                    </header>
                    @can('sidang_ta_period_update')
                        <form data-ta-period-update method="POST" action="{{ route('admin.sidang-ta-periods.update', $period) }}" class="grid gap-3 md:grid-cols-2">
                            @csrf @method('PUT')
                            <label class="campus-label">Nama periode <input class="campus-input mt-1" name="name" value="{{ $period->name }}" required maxlength="255"></label>
                            <label class="campus-label">Keterangan <input class="campus-input mt-1" name="description" value="{{ $period->description }}" maxlength="2000"></label>
                            <label class="campus-label">Dibuka <input class="campus-input mt-1" type="datetime-local" name="open_at" value="{{ $period->open_at->format('Y-m-d\TH:i') }}" required></label>
                            <label class="campus-label">Tenggat <input class="campus-input mt-1" type="datetime-local" name="close_at" value="{{ $period->close_at->format('Y-m-d\TH:i') }}" required></label>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($period->is_active)> Periode aktif</label>
                            <div class="md:col-span-2"><button type="submit" class="btn btn-primary">Simpan perubahan</button></div>
                        </form>
                    @else
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs text-slate-500">Dibuka</dt><dd class="mt-1 font-medium text-slate-800">{{ $period->open_at->format('d/m/Y H:i') }}</dd></div>
                            <div><dt class="text-xs text-slate-500">Tenggat</dt><dd class="mt-1 font-medium text-slate-800">{{ $period->close_at->format('d/m/Y H:i') }}</dd></div>
                            <div><dt class="text-xs text-slate-500">Aktivasi</dt><dd class="mt-1 font-medium text-slate-800">{{ $period->is_active ? 'Aktif' : 'Tidak aktif' }}</dd></div>
                        </dl>
                    @endcan
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Belum ada periode pendaftaran.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
```

Status aktivasi dan waktu dibedakan; tepat pada open_at/close_at masih terbuka sesuai scope model. Date input memakai format datetime-local tanpa backslash literal. P1 tidak menambahkan tombol hapus atau mengganti validasi create/update di controller; route aktivasi/hapus existing tetap terlindungi permission masing-masing.

## 9. Test P1 lengkap

Isi seluruh tests/Feature/SidangTaStaffNavigationTest.php:

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Services\SidebarBadgeService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    foreach (['sidang_ta_registration_review', 'sidang_ta_registration_read',
        'sidang_ta_period_read', 'sidang_ta_period_create', 'sidang_ta_period_update',
        'sidang_ta_period_delete', 'student_read', 'lecturer_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->makeActor = function (array $permissions = []) {
        $id = (string) Str::uuid();
        $user = User::factory()->create(['username' => 'p1-'.$id, 'email' => $id.'@staff.test',
            'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
        if ($permissions !== []) $user->givePermissionTo($permissions);
        return $user;
    };
    $this->period = SidangTaPeriod::create(['name' => 'Periode P1', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => 'P1-PRIVATE-0001',
        'student_name' => 'Mahasiswa Rahasia P1', 'study_program' => 'Informatika',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        'status' => 'submitted', 'submitted_at' => now(),
    ])->fresh();
    $this->periodData = ['name' => 'Periode Baru P1', 'is_active' => 1,
        'open_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'close_at' => now()->addDays(2)->format('Y-m-d H:i:s')];
    $this->sidebarFor = function (string $html, string $route) {
        return (new Crawler($html))->filter('aside nav a')->reduce(
            fn (Crawler $node) => $node->attr('href') === route($route)
        );
    };
    $this->validatorConfig = function ($any) {
        config(['sidebar' => ['mahasiswa' => [], 'admin_standalone' => [], 'admin' => [
            'p1' => ['label' => 'P1', 'items' => [[
                'label' => 'P1 OR', 'route' => 'admin.sidang-ta-registrations.index',
                'permissions_any' => $any,
            ]]],
        ]]]);
    };
});

afterEach(function () {
    $this->travelBack();
});

test('p1_combined_permissions_show_one_sidebar_entry_and_manage_link', function () {
    $user = ($this->makeActor)(['sidang_ta_registration_review', 'sidang_ta_period_read']);
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    $html = $response->getContent();
    $crawler = new Crawler($html);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->count())->toBe(1);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-periods.index')->count())->toBe(0);
    expect($crawler->filter('[data-manage-ta-periods]')->attr('href'))->toBe(route('admin.sidang-ta-periods.index'));
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(1);
    $response->assertSee('Mahasiswa Rahasia P1');
});

test('p1_reviewer_without_period_read_keeps_review_but_cannot_manage_periods', function () {
    $user = ($this->makeActor)(['sidang_ta_registration_review']);
    $html = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertOk()->getContent();
    $crawler = new Crawler($html);
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(1);
    expect($crawler->filter('[data-manage-ta-periods]')->count())->toBe(0);
    $this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertOk();
    $this->get(route('admin.sidang-ta-periods.index'))->assertForbidden();
});

test('p1_period_reader_hub_does_not_query_or_expose_registration_data', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registrations')) $queries[] = $event->sql;
    });
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index', ['search' => 'P1']))
        ->assertOk()->assertViewHas('canReview', false)->assertViewHas('registrations', fn ($value) => $value === null)
        ->assertDontSee('Mahasiswa Rahasia P1')->assertDontSee('P1-PRIVATE-0001');
    expect($queries)->toBe([]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-review-data]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-period-only]')->count())->toBe(1);
    expect($crawler->filter('[data-manage-ta-periods]')->count())->toBe(1);
    $this->get(route('admin.sidang-ta-registrations.show', $this->registration))->assertForbidden();
    $this->post(route('admin.sidang-ta-registrations.review', $this->registration), [
        'action' => 'approved', 'registration_version' => $this->registration->lock_version,
    ])->assertForbidden();
    expect($this->registration->fresh()->status)->toBe('submitted');
    expect($user->fresh()->can('sidang_ta_registration_review'))->toBeFalse();
});

test('p1_user_without_either_permission_has_no_hub_or_menu', function () {
    $user = ($this->makeActor)();
    $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('admin.sidang-ta-periods.index'))->assertForbidden();
    $html = $this->get(route('dashboard'))->assertOk()->getContent();
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->count())->toBe(0);
});

test('p1_period_page_keeps_review_menu_active_and_has_fixed_back_link', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $html = $this->actingAs($user)->get(route('admin.sidang-ta-periods.index'))->assertOk()->getContent();
    $crawler = new Crawler($html);
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-registrations.index')->attr('aria-current'))->toBe('page');
    expect(($this->sidebarFor)($html, 'admin.sidang-ta-periods.index')->count())->toBe(0);
    expect($crawler->filter('[data-back-to-ta-review]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index'));
    $groupKey = null;
    foreach (config('sidebar.admin') as $key => $group) {
        foreach ($group['items'] ?? [] as $item) {
            if (($item['route'] ?? null) === 'admin.sidang-ta-registrations.index') $groupKey = $key;
        }
    }
    expect($groupKey)->not->toBeNull();
    expect($crawler->filter('aside nav [x-data]')->attr('x-data'))->toContain($groupKey);
});

test('p1_period_reader_sees_all_time_states_without_write_forms', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read']);
    $cases = [[$this->period, 'open']];
    foreach ([['inactive', false, now()->subDay(), now()->addDay()],
        ['upcoming', true, now()->addHour(), now()->addDay()],
        ['closed', true, now()->subDays(2), now()->subDay()],
        ['open', true, now(), now()->addDay()],
        ['open', true, now()->subDay(), now()]] as [$state, $active, $open, $close]) {
        $cases[] = [SidangTaPeriod::create(['name' => 'State '.$state, 'is_active' => $active,
            'open_at' => $open, 'close_at' => $close]), $state];
    }
    $crawler = new Crawler($this->actingAs($user)->get(route('admin.sidang-ta-periods.index'))->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(0);
    expect($crawler->filter('form[data-ta-period-update]')->count())->toBe(0);
    foreach ($cases as [$period, $state]) {
        expect($crawler->filter('[data-ta-period-id="'.$period->id.'"] [data-period-state]')->attr('data-period-state'))->toBe($state);
    }
    $this->post(route('admin.sidang-ta-periods.store'), $this->periodData)->assertForbidden();
    $this->put(route('admin.sidang-ta-periods.update', $this->period), $this->periodData)->assertForbidden();
    $this->post(route('admin.sidang-ta-periods.activate', $this->period))->assertForbidden();
    $this->delete(route('admin.sidang-ta-periods.destroy', $this->period))->assertForbidden();
});

test('p1_creator_can_create_second_active_period_without_update_controls', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read', 'sidang_ta_period_create']);
    $url = route('admin.sidang-ta-periods.index');
    $crawler = new Crawler($this->actingAs($user)->get($url)->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(1);
    expect($crawler->filter('form[data-ta-period-update]')->count())->toBe(0);
    $this->from($url)->post(route('admin.sidang-ta-periods.store'), $this->periodData)
        ->assertRedirect($url)->assertSessionHasNoErrors();
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect(SidangTaPeriod::where('name', 'Periode Baru P1')->firstOrFail()->is_active)->toBeTrue();
    $this->put(route('admin.sidang-ta-periods.update', $this->period), $this->periodData)->assertForbidden();
});

test('p1_updater_has_correct_datetime_inputs_and_keeps_other_period_active', function () {
    $user = ($this->makeActor)(['sidang_ta_period_read', 'sidang_ta_period_update']);
    $url = route('admin.sidang-ta-periods.index');
    $crawler = new Crawler($this->actingAs($user)->get($url)->assertOk()->getContent());
    expect($crawler->filter('form[data-ta-period-create]')->count())->toBe(0);
    $input = $crawler->filter('[data-ta-period-id="'.$this->period->id.'"] input[name="open_at"]');
    expect($input->attr('value'))->toBe($this->period->open_at->format('Y-m-d\TH:i'));
    $second = SidangTaPeriod::create(['name' => 'Update P1', 'is_active' => false,
        'open_at' => now()->addHour(), 'close_at' => now()->addDay()]);
    $this->from($url)->post(route('admin.sidang-ta-periods.activate', $second))->assertRedirect($url);
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->is_active)->toBeTrue();
    $this->from($url)->put(route('admin.sidang-ta-periods.update', $second), $this->periodData)
        ->assertRedirect($url)->assertSessionHasNoErrors();
    expect($this->period->fresh()->is_active)->toBeTrue();
    expect($second->fresh()->name)->toBe('Periode Baru P1');
    $this->post(route('admin.sidang-ta-periods.store'), $this->periodData)->assertForbidden();
    $this->delete(route('admin.sidang-ta-periods.destroy', $second))->assertForbidden();
});

test('p1_sidebar_or_is_fail_closed_and_preserves_legacy_permission_role_items', function () {
    $user = ($this->makeActor)(['student_read']);
    $role = Role::findOrCreate('P1Legacy', 'web');
    $user->assignRole($role);
    $this->actingAs($user);
    $groups = ['test' => ['label' => 'P1 Group', 'icon' => [], 'items' => [
        ['label' => 'P1 Public', 'route' => 'dashboard'],
        ['label' => 'P1 Permission', 'route' => 'dashboard', 'permission' => 'student_read'],
        ['label' => 'P1 Denied', 'route' => 'dashboard', 'permission' => 'lecturer_read'],
        ['label' => 'P1 Role', 'route' => 'dashboard', 'roles' => ['P1Legacy']],
        ['label' => 'P1 Empty OR', 'route' => 'dashboard', 'permissions_any' => []],
        ['label' => 'P1 Unmatched OR', 'route' => 'dashboard', 'permissions_any' => ['sidang_ta_registration_review'], 'roles' => ['P1Legacy']],
        ['label' => 'P1 Missing Route', 'route' => 'p1.route.does.not.exist'],
    ]]];
    $html = view('components.sidebar-admin', ['groups' => $groups, 'standalone' => [],
        'badgeService' => app(SidebarBadgeService::class)])->render();
    expect($html)->toContain('P1 Public')->toContain('P1 Permission')->toContain('P1 Role')
        ->not->toContain('P1 Denied')->not->toContain('P1 Empty OR')->not->toContain('P1 Unmatched OR')
        ->not->toContain('P1 Missing Route');
});

test('p1_sidebar_validator_accepts_registered_or_permissions', function () {
    ($this->validatorConfig)(['sidang_ta_registration_review', 'sidang_ta_period_read']);
    $this->artisan('sidebar:validate')->assertExitCode(0);
});

test('p1_sidebar_validator_rejects_unknown_empty_and_invalid_or_permissions', function () {
    ($this->validatorConfig)(['p1_unknown_permission']);
    $this->artisan('sidebar:validate')->expectsOutputToContain('Permission tidak terdaftar')->assertExitCode(1);
    foreach ([[], 'sidang_ta_period_read', [null], ['']] as $invalid) {
        ($this->validatorConfig)($invalid);
        $this->artisan('sidebar:validate')->expectsOutputToContain('permissions_any')->assertExitCode(1);
    }
});

test('p1_role_switch_revokes_staff_hub_access_while_student_role_is_active', function () {
    $staff = Role::findOrCreate('P1Reviewer', 'web');
    $staff->givePermissionTo('sidang_ta_registration_review');
    $student = Role::findOrCreate('mahasiswa', 'web');
    $student->givePermissionTo('sidang_ta_registration_read');
    $user = ($this->makeActor)();
    $user->assignRole([$staff, $student]);
    $this->actingAs($user)->withSession(['active_role' => 'P1Reviewer'])
        ->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    $this->post(route('role.switch'), ['role' => 'mahasiswa'])->assertRedirect(route('dashboard'));
    $this->get(route('admin.sidang-ta-registrations.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Review Sidang TA');
    $this->post(route('role.switch'), ['role' => 'P1Reviewer'])->assertRedirect(route('dashboard'));
    $this->get(route('admin.sidang-ta-registrations.index'))->assertOk();
});

```

## 10. Command verifikasi

Di project asli:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaStaffNavigationTest.php tests/Feature/SidangTaPeriodTest.php
```

Target fokus **21 passed** =12P1+9Period. Suite lengkap student+P1:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaStudentPolishTest.php tests/Feature/SidangTaDocumentPreviewTest.php tests/Feature/SidangTaStaffNavigationTest.php
```

Target **138 passed**. Karena renderer sidebar dipakai lintas modul, jalankan juga regresi existing:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/PermissionManagementTest.php tests/Feature/RoleSwitchTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
npm run build
```

Target tambahan **28 PHP** (24PermissionManagement+4RoleSwitch), Node12, build berhasil. Tidak ada file test existing yang perlu dihapus atau diganti pada P1.

Untuk instalasi aplikasi lokal yang sudah memiliki seeder permission lengkap, periksa config sidebar:

```bash
php artisan sidebar:validate
```

Command terakhir hanya membaca permission database pada koneksi aplikasi saat dijalankan. Jangan mengharapkan validasi seluruh config berhasil pada schema test kosong sesudah RefreshDatabase; test command P1 memakai config minimal yang memang diuji. Tidak perlu reseed/reset database aplikasi untuk menjalankan P1.

## 11. Manual test P1

- [ ] Login akun dummy, pilih role petugas. Sidebar hanya memiliki Review Sidang TA, tanpa item Periode Sidang TA terpisah.
- [ ] Role yang punya review+period_read melihat tabel dan Kelola Periode. Klik Kelola: halaman periode terbuka, menu Review TA tetap aktif dan accordion terbuka. Tombol Kembali menuju hub tetap.
- [ ] Akun/role period_read saja dapat masuk hub dan periode; hub tidak berisi mahasiswa/NIM/filter/tabel, dan direct URL detail/keputusan ditolak.
- [ ] Akun review saja melihat pengajuan tetapi tidak mempunyai tombol Kelola; direct URL periode ditolak. Akun tanpa keduanya tidak melihat menu dan route ditolak.
- [ ] Read-only periode tidak mempunyai Tambah atau Simpan perubahan. Create hanya menampilkan form Tambah; update menampilkan form edit. Date input berisi tanggal/jam yang benar.
- [ ] Buat/aktifkan periode kedua ketika periode pertama masih aktif; keduanya tetap aktif. Status Tidak aktif/Belum dibuka/Sedang terbuka/Ditutup sesuai aktivasi dan waktu.
- [ ] Ganti ke role mahasiswa: hub petugas tidak dapat dibuka, sidebar kembali ke menu mahasiswa, alur pendaftaran student tetap bekerja. Ganti kembali petugas: akses pulih sesuai izin role aktif.
- [ ] Periksa menu modul lain (misalnya Yudisium, dokumen, pengaturan role) tetap sesuai izin masing-masing. Tampilan desktop/mobile serta accordion tetap bekerja.

Pengaturan role tetap melalui superadmin. P1 tidak memodifikasi permission akun dummy/role nyata lewat seeder dan tidak menjadikan nama role sebagai otorisasi.

## 12. Salin dan tahap berikutnya

Setelah fokus/suite/regresi/manual lulus, salin tujuh file existing pada bagian2 dan satu test baru ke dadu-test. Jangan memindahkan .env, phpunit.xml lokal, database, cache atau dependencies. Minta rescan P1 sebelum P2a.

Student tetap selesai pada commit1414aa6; P1 adalah perubahan petugas terpisah. Commit/push petugas dilakukan pengguna ketika checkpoint yang diinginkan sudah lolos; asisten tidak menjalankannya. Konfigurasi Podman lokal yang masih unstaged tidak ikut ditambahkan tanpa sengaja.

## 13. Hasil pemeriksaan panduan

Enam blok PHP lulus pemeriksaan sintaks; fragment array item diperiksa dalam konteks array config dan method dalam konteks class. Lima blok Blade lulus kompilasi di memori tanpa component tags dan hasil PHP lulus sintaks. Blade periode memakai blok @php lengkap untuk waktu awal; bentuk inline sebelum blok @php berikutnya sempat menyebabkan kegagalan compile saat pemeriksaan dan sudah dikoreksi sebelum panduan diserahkan.

Probe render di memori dengan Auth/Gate/session/URL/route stub memeriksa OR sidebar, array kosong tidak tampil, role legacy tetap bekerja, active dua pola/aria, form periode sesuai read/create/update, empat status waktu dan kedua boundary inklusif, format datetime-local, serta hub period-only tanpa tabel. Probe lulus tanpa bootstrap aplikasi atau koneksi DB. Waktu fixture test dibuat tepat pada awal detik agar sesuai presisi DATETIME database; reviewer independen memverifikasi API role/permission, guard route dan fixture, tanpa blocker tersisa. Dua belas test Node existing juga dijalankan dan lulus.

Source kedua folder dipindai sebelum/sesudah penulisan dan tidak diubah. Suite Feature PHP, command sidebar:validate terhadap DB aplikasi, build dan manual P1 belum dijalankan karena kode masih berupa panduan; hasil126 student tidak menjadi bukti138 P1 lulus.

Referensi Context7: [Laravel13 authorization](https://github.com/laravel/docs/blob/13.x/authorization.md), [named request routes](https://github.com/laravel/docs/blob/13.x/requests.md). canAny dan signature routeIs variadic juga diperiksa pada vendor lokal; middleware permission tetap pola Spatie yang sudah dipakai di aplikasi.
