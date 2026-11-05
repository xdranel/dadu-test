# 33 — Koreksi empat test S1 yang gagal

Status: pengguna melaporkan **4 failed, 27 passed** pada suite fokus. Manual test belum dilakukan dan file belum disalin ke dadu-test. Pemeriksaan ini membaca implementasi terbaru pada `/home/xdranel/Code/Project/php-laravel/dadu/`; workspace masih baseline sebelumnya. Asisten hanya mengubah dokumentasi.

## 1. Penyebab yang sudah ditemukan

| Test gagal | Penyebab pada source asli | Koreksi |
| --- | --- | --- |
| `html_save_stays_on_current_step_and_continue_requires_saved_complete_stage` | Counter Blade menghasilkan `1dari 6 dokumen tersimpan.` | Tambahkan spasi sebelum `dari` |
| `g1_missing_saved_file_keeps_step_incomplete_and_preserves_other_saved_files` | Counter yang sama menghasilkan `5dari 6 dokumen tersimpan.` | Perbaikan Blade yang sama |
| `g1_biodata_error_is_attached_to_radio_group` | Test lama masih ada bersama test pengganti; mengharapkan field omitted menghasilkan error, padahal S1 mempertahankan pilihan tersimpan | Hapus blok test lama yang sudah digantikan |
| `g1_missing_saved_file_shows_its_inline_error_and_preserves_other_saved_files` | Test lama masih mengharapkan save ditolak ketika ada file wajib hilang, padahal S1 menyimpan progres dan mengunci lanjut | Hapus blok test lama yang sudah digantikan |

Source Requirements dan DocumentService sesuai snippet 32 setelah perbedaan whitespace diabaikan. Tidak ada alasan dari empat kegagalan ini untuk mengembalikan validasi save menjadi wajib lengkap.

## 2. Perbaiki counter pada wizard

Edit file existing berikut di project asli; tidak perlu command generator:

```text
resources/views/sidang_ta_registrations/wizard.blade.php
```

Pada section `data-step-progress`, baris saat ini:

```blade
{{ $stepProgress['stored_documents'] }}dari {{ $stepProgress['required_documents'] }} dokumen tersimpan.
```

Ganti dengan berikut. Pertahankan spasi literal di antara `}}` dan `dari`:

```blade
<p>{{ $stepProgress['stored_documents'] }} dari {{ $stepProgress['required_documents'] }} dokumen tersimpan.</p>
```

Blok ini menggantikan elemen `<p>` counter beserta isinya; jangan membungkusnya lagi dengan `<p>` tambahan. Pertahankan kondisi `@if` di sekelilingnya. Assertion test tetap memeriksa teks dengan spasi karena itulah tampilan yang diinginkan.

## 3. Buang dua blok test lama yang sudah digantikan

Edit file existing:

```text
tests/Feature/SidangTaWizardUiTest.php
```

Hapus **seluruh blok `test(..., function () { ... });`** untuk kedua nama ini saja:

```text
g1_biodata_error_is_attached_to_radio_group
g1_missing_saved_file_shows_its_inline_error_and_preserves_other_saved_files
```

Kedua blok penggantinya sudah ditemukan di file yang sama dan harus tetap ada:

```text
g1_invalid_biodata_error_is_attached_to_radio_group
g1_missing_saved_file_keeps_step_incomplete_and_preserves_other_saved_files
```

Kode lengkap dua pengganti sudah tersedia di [32 bagian 9](32-checkpoint-s1-simpan-draft-parsial.md#9-empat-test-lama-yang-kebijakannya-berubah). Tidak perlu menambahkannya lagi. Jangan menghapus fixture, helper, test tanggal invalid, maupun test pengamanan lainnya. Ini menghapus duplikasi kontrak lama yang memang diganti oleh S1, bukan menghilangkan pengujian perilaku yang masih diperlukan.

Hasil akhir jumlah definisi test pada ketiga file fokus:

| File | Jumlah |
| --- | ---: |
| `SidangTaPartialDraftTest.php` | 12 |
| `SidangTaDraftUploadTest.php` | 8 |
| `SidangTaWizardUiTest.php` | 9 |
| Total | **29** |

Sebelum koreksi, file WizardUi memiliki 11 definisi; itu sebabnya output fokus berjumlah 31 test. Setelah dua blok lama dihapus, targetnya **29 passed**, bukan 31. Target suite TA keseluruhan tetap **110** sebagaimana panduan 32.

## 4. Jalankan ulang

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaWizardUiTest.php
```

Setelah fokus lulus, jalankan suite lengkap, Node/build, dan checklist browser pada **32 bagian 10–11**. Baru setelah semuanya lulus, salin file implementasi/test S1 sesuai **32 bagian 1** ke dadu-test untuk rescan. Jangan menyalin `.env`, `phpunit.xml` sementara, cache, atau database. S2/petugas belum dimulai.

## 5. Bukti pemeriksaan asisten dan batasnya

Counter asli direproduksi dengan kompilasi Blade dan eksekusi di memori, tanpa bootstrap aplikasi/database: count 1 dan 5 menghasilkan `1dari`/`5dari` dan gagal mencocokkan assertion. Counter koreksi menghasilkan `1 dari`/`5 dari` dan cocok. Keberadaan kedua test lama sekaligus kedua pengganti diverifikasi langsung pada source asli.

Ini membuktikan penyebab pada markup dan duplikasi kontrak test. Suite Feature PHP dan manual browser belum dijalankan oleh asisten; hasil setelah koreksi tetap menunggu pengguna. Source aplikasi kedua folder dipindai sebelum dan sesudah penulisan dokumentasi dan tidak diubah oleh asisten.
