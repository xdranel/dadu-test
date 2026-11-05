# 41 — Koreksi Cache-Control ekspor dan tombol hijau

## 1. Penyebab test gagal

Test `p2b_private_attachment_is_deleted_after_send_without_mutating_registration` memang harus memastikan attachment privat dan no-store. Pada panduan40, saya melewatkan perilaku Laravel ResponseFactory::download: factory membangun BinaryFileResponse dengan parameter public=true. Constructor Symfony lalu memanggil setPublic(), sehingga header awal `private, no-store` berubah menjadi **`no-store, public`**.

Ini masalah pada respons produksi, bukan alasan menghapus assertion. Koreksinya memanggil **setPrivate() setelah download() membangun respons**. Probe terhadap vendor aktual mereproduksi hasil sebelum dan sesudah:

```text
Sebelum: no-store, public
Sesudah: no-store, private
```

Source terbaru di project asli masih sesuai blok40 yang belum memakai setPrivate. Pemeriksaan dilakukan baca saja; semua perbaikan asisten tetap hanya di docs. Belum ada konfirmasi P2b tersalin/lulus setelah koreksi.

## 2. Koreksi service

Edit file existing di project asli:

```text
app/Services/SidangTaReviewExport.php
```

Di method download(), ganti blok return response()->download(...) yang berada setelah clearstatcache dengan berikut. Perubahan utama hanya tambahan setPrivate(); pertahankan seluruh mapper/tempfile/try-catch lainnya:

```php
return response()->download($path, 'Sidang_TA_'.now()->format('Y-m-d_H-i-s').'.xlsx', [
    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Cache-Control' => 'private, no-store',
    'X-Content-Type-Options' => 'nosniff',
])->setPrivate()->deleteFileAfterSend(true);
```

Tidak mengubah permission, query, isi workbook atau perilaku hapus file setelah send.

## 3. Tombol Ekspor Excel hijau

Edit resources/views/sidang_ta_registrations/admin/index.blade.php. Ganti seluruh blok div flex yang memuat tombol Ekspor Excel dan Kelola Periode pada header dengan berikut. Jangan mengganti header judul atau bagian filter/tabel:

```blade
<div class="flex flex-wrap items-center gap-2">
    @if($canReview)
        @if($counts['total'] > 0)
            <a data-ta-export href="{{ route('admin.sidang-ta-registrations.export', $filters) }}" class="btn btn-success">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.7" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 0v6h6M8 12l5 6m0-6l-5 6"/></svg>
                Ekspor Excel
            </a>
        @else
            <button data-ta-export-empty type="button" disabled class="btn btn-success cursor-not-allowed opacity-50" title="Tidak ada pengajuan untuk diekspor">Ekspor Excel</button>
        @endif
    @endif
    @can('sidang_ta_period_read')
        <a data-manage-ta-periods href="{{ route('admin.sidang-ta-periods.index') }}" class="btn btn-secondary">Kelola Periode</a>
    @endcan
</div>
```

btn-success sudah tersedia pada resources/css/app.css: latar emerald-600, teks/ikon putih, hover emerald-700. Tombol hasil kosong tetap hijau tetapi redup dan disabled. Tidak perlu menambah CSS global atau dependency.

## 4. Test pengganti lengkap

Pada tests/Feature/SidangTaReviewExportTest.php, ganti **blok test dengan nama yang sama**, jangan menambahkan duplikat. Assertion private/no-store tetap, ditambah pemeriksaan bahwa directive public tidak tersisa:

```php
test('p2b_private_attachment_is_deleted_after_send_without_mutating_registration', function () {
    $registration = ($this->makeRegistration)();
    [$response, , $path] = ($this->exportRows)();
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    expect($response->headers->hasCacheControlDirective('public'))->toBeFalse();
    expect($response->baseResponse->shouldDeleteFileAfterSend())->toBeTrue();
    ob_start();
    try { $response->baseResponse->sendContent(); $bytes = ob_get_contents(); } finally { ob_end_clean(); }
    expect($bytes)->toStartWith('PK');
    expect(is_file($path))->toBeFalse();
    expect($registration->fresh()->status)->toBe('submitted');
    expect($registration->fresh()->lock_version)->toBe(1);
    expect($registration->logs()->count())->toBe(0);
});
```

Tidak perlu generator test baru. Jumlah test file Export tetap10, fokus tetap36, suite tetap162. Tampilan hijau cukup diverifikasi browser karena ini penggunaan kelas existing; tidak menambah test yang hanya menyalin nama kelas CSS.

## 5. Jalankan ulang

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaReviewExportTest.php --filter=p2b_private_attachment_is_deleted_after_send_without_mutating_registration
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaReviewExportTest.php tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaStaffNavigationTest.php
```

Target1test terarah lalu **36 fokus lulus**. Sesudahnya jalankan suite162/Node12/build dan manual pada40 bagian7.

Manual tambahan: tombol ekspor hijau dengan ikon putih; hasil kosong tetap disabled/redup; file XLSX dapat dibuka dan berisi filter yang benar. Pada Network DevTools respons unduh memuat private dan no-store, tanpa public. Tidak perlu mengubah setting PHP/Podman atau menghapus file aplikasi.

Setelah semuanya lulus, salin lima file P2b pada40 bagian2 ke dadu-test, termasuk tiga file yang dikoreksi di sini: exportService, indexadmin dan ExportTest. Minta rescan sebelum P3.

## 6. Verifikasi asisten

Probe baca vendor dan ResponseFactory aktual dengan file sementara /tmp mereproduksi kegagalan asli lalu memverifikasi private/no-store tanpa public setelah setPrivate(). Tidak memakai bootstrap aplikasi/DB. Source kedua folder dipindai sebelum/sesudah penulisan dan tidak diubah. Test Feature PHP, build dan manual setelah koreksi masih harus dijalankan pengguna.

Referensi Context7: [Symfony8 HttpFoundation cache methods](https://github.com/symfony/symfony-docs/blob/8.0/components/http_foundation.rst), khusus setPrivate/setPublic; constructor BinaryFileResponse dan ResponseFactory::download juga diperiksa langsung pada vendor lokal.
