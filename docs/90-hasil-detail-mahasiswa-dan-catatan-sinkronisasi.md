# 90 — Hasil detail mahasiswa dan catatan sinkronisasi

## 1. IT Panel → Sinkronisasi

Kolom Keterangan sekarang menampilkan **Lihat catatan** untuk impor yang memiliki kesalahan/peringatan atau record dilewati. Tombol membuka modal yang dapat digulir. Impor selesai tanpa catatan menampilkan **Selesai**.

Catatan impor unggahan baru memuat NIM, nama bila tersedia, record mulai1, path JSON, baris fisik awal record di berkas, dan penjelasan masalah. Contoh tampilannya: `NIM 1202200001 · Mahasiswa Sintetis` lalu `Record 3 · $.data[2] · Baris JSON 24`. Baris fisik adalah awal objek mahasiswa, bukan klaim lokasi persis field yang salah. Pada JSON satu baris, baris fisik memang1; path dan nomor record tetap membedakannya. Jika NIM tidak ada, nama/record/path membantu menemukan data.

Seluruh catatan terstruktur disimpan, termasuk sesudah catatan ke10. Pesan kompatibilitas lama dibatasi sesuai kapasitas TEXT; detail lengkap tersimpan pada kolom JSON baru. Halaman daftar tidak mengambil seluruh payload catatan20 log; detail dimuat saat tombol dipilih. Endpoint memakai hak akses `sync_read`, dan isi modal ditulis sebagai teks, sehingga markup dari unggahan tidak dijalankan.

Field opsional yang tidak dikirim pada impor parsial tetap bukan kesalahan. Kegagalan per-menu yang memang dilaporkan sumber juga ditampilkan, termasuk jika kelompok datanya absen, tanpa menampilkan token/pesan teknis mentah sumber. Catatan tidak mengubah aturan mana data yang boleh diimpor atau dipertahankan.

Log lama tetap terbaca. Data yang sebelumnya sudah dipotong tidak dapat dipulihkan, dan baris fisik log lama tidak dapat dihitung tanpa berkas aslinya. Log lama dengan record dilewati tetapi tanpa rincian menampilkan penjelasan bahwa rincian belum tersedia. Sinkronisasi endpoint/background yang sudah ada memakai fallback ini; penambahan lokasi berkas berlaku pada unggahan JSON yang melewati controller impor.

## 2. Data Akademik → Mahasiswa → View

- Profil dan kartu yang sebelumnya dinyatakan benar dipertahankan.
- Rencana Studi & Nilai tetap satu kartu berisi empat bagian buka/tutup dengan chevron: Mata Kuliah Semester Ini (KRS Aktif), History Nilai (KRS Aktif), Mata Kuliah Semua Semester, dan Histori Nilai. KRS aktif terbuka awalnya.
- History Nilai (KRS Aktif) menampilkan semua nilai tersimpan untuk kode mata kuliah yang ada pada KRS aktif, sesuai konfirmasi pengguna. Nilai mengulang dari tahun lain tidak dibuang. Ini tidak mengklaim seluruh nilai tersebut diperoleh pada periode aktif.
- EPRT memakai pembaca fakta dan riwayat yang sama dengan panel Sidang TA: jenis tes, skor, tanggal tes/kedaluwarsa bila tersedia, dan status berlaku. Skor ringkasan tidak mengambil nilai kedaluwarsa yang lebih tinggi jika sumber sudah memberi informasi masa berlaku.
- TAK memakai total resmi yang sama dengan panel Sidang TA, menampilkan riwayat TAK yang memang tersimpan, dan memisahkan kegiatan lokal. Nilai0 tetap0; null tampil Belum tersedia. Impor total TAK saja tidak dianggap menyediakan rincian kegiatan atau riwayat perubahan skor.
- Kehadiran tetap membaca hasil impor yang sudah tersedia. Jika belum ada data, tidak ditampilkan ringkasan0% yang seolah-olah menyatakan mahasiswa tidak hadir. Integrasi format baru menunggu contoh sumber.
- Tab menjadi **Data Yudisium & Tugas Akhir**. BPP menampilkan semua status per periode yang sudah tersimpan; data lama tanpa periode ditandai Periode belum tersedia. Ini riwayat status per periode, bukan rincian transaksi yang belum diberikan sumber.
- SKPI tampil sebagai status keseluruhan, Status1–3, dan approval status dengan badge. JSON mentah dihapus dari kartu. Status yang belum tersedia tidak diubah menjadi penolakan.
- Kartu **Persetujuan Sidang iGracias** menampilkan status resmi, misalnya APPROVED. Pembaca ini tidak mengubah kelayakan atau snapshot pengajuan Sidang TA yang sudah dikirim.

## 3. Contoh JSON yang masih perlu diminta

**Yang diperlukan berikutnya: contoh ekspor Kehadiran per mata kuliah.** Contoh boleh memakai NIM/nama sintetis, tetapi pertahankan nama field dan bentuk asli ekspornya.

Minta agar contoh memuat:

1. Identitas mahasiswa: NIM dan nama, serta apakah satu mahasiswa bisa muncul beberapa kali.
2. Periode akademik: tahun ajaran dan ganjil/genap, dibedakan dari semester studi mahasiswa.
3. Kode/nama mata kuliah dan kelas.
4. Jumlah hadir, jumlah pertemuan, dan/atau persentase bila memang diberikan sumber.
5. Arti nilai0, null/kosong, serta kondisi data tidak berhasil diambil. Jika ada izin/sakit/alpa, sertakan contoh field aslinya.
6. Minimal contoh mahasiswa dengan kehadiran terisi, hadir0 dari pertemuan yang memang ada, dan data yang belum tersedia. Bila memungkinkan sertakan mata kuliah sama pada dua periode.

Format lama yang sudah diterima menu Jadwal/Kehadiran Mahasiswa memakai `nim`/`student_number`, `kode_matakuliah`/`course_code`, `nama_matakuliah`/`course_name`, `kelas`, `jumlah_hadir`/`attended_count`, `jumlah_pertemuan`/`meetings_count`, `tahun_akademik`/`academic_year`, dan `semester`. Daftar ini referensi pemetaan yang ada, bukan permintaan agar tim sumber mengubah struktur ekspornya.

Tambahan **hanya jika ingin melengkapi rincian yang belum tersedia**:

- Riwayat/kegiatan TAK iGracias: contoh yang sekarang hanya memberi `total_tak`. Untuk rincian kegiatan perlu nama kegiatan, jenis/peran, poin, tanggal, dan periode bila ada; untuk riwayat skor perlu tanggal tes/perubahan dan masa berlaku bila tersedia. Total resmi sudah dapat ditampilkan tanpa JSON tambahan.
- Nilai/transkrip per mata kuliah: bila ingin mengisi histori melalui JSON, minta NIM, kode/nama MK, SKS, nilai, tahun/periode, dan contoh MK mengulang. KRS resmi yang tersedia berisi daftar pengambilan MK, bukan nilai per MK; tabel histori tetap membaca nilai yang sudah tersimpan dari jalur lama.

JSON KRS, BPP, EPRT, SKPI, dan total TAK yang sudah diberikan cukup untuk perubahan tampilan pada pekerjaan ini.

## 4. Migration dan penerapan

Migration tambahan bersifat aditif:

```text
database/migrations/2026_11_10_000001_add_issue_details_to_sync_logs_table.php
```

Migration menambahkan `sync_logs.issue_details` nullable JSON. Log lama tidak dihapus. Database aplikasi belum dimigrasikan oleh proses implementasi ini.

Development lokal:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan migrate
npm run build
```

Jalankan migration sebelum memakai halaman Sinkronisasi dengan kode baru karena query daftar log sudah membaca kolom tambahan. Tidak diperlukan migrate:fresh atau seed ulang. Build development asli juga diperiksa setelah penyalinan.

Docker dadu-test: setelah commit/push dan kode tersedia pada main, ikuti [dokumen78 bagian4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker). Alur tersebut melakukan backup, migration biasa dari image baru, build/recreate app/web, lalu menghidupkan app/scheduler/worker. Pastikan migration baru berstatus Ran. Proses ini tidak melakukan commit/push/deployment dan produksi tetap ditunda.

## 5. Rescan dan salinan kedua repo

Berkas lingkup perubahan:

- `app/Http/Controllers/StudentController.php`
- `resources/views/students/show.blade.php`
- `app/Services/StudentAcademicDetail.php`
- `resources/views/students/partials/study-records.blade.php`
- `resources/views/students/partials/language.blade.php`
- `resources/views/students/partials/tak.blade.php`
- `resources/views/students/partials/bpp.blade.php`
- `resources/views/students/partials/skpi.blade.php`
- `resources/views/students/partials/status-badge.blade.php`
- `tests/Feature/StudentAcademicDetailTest.php`
- `app/Http/Controllers/SyncController.php`
- `app/Models/SyncLog.php`
- `routes/web.php`
- `resources/views/sync/index.blade.php`
- `app/Support/JsonSourceLocations.php`
- `app/Support/SyncImportIssues.php`
- `resources/views/sync/issues-modal.blade.php`
- `database/migrations/2026_11_10_000001_add_issue_details_to_sync_logs_table.php`
- `tests/Unit/JsonSourceLocationsTest.php`
- `tests/Feature/SyncImportDetailsTest.php`
- `docs/88-rancangan-detail-mahasiswa-dan-catatan-sinkronisasi.md`
- `docs/89-rencana-implementasi-detail-mahasiswa-dan-sinkronisasi.md`
- `docs/90-hasil-detail-mahasiswa-dan-catatan-sinkronisasi.md`

Rescan sebelum integrasi menemukan perubahan yang sudah ada pada branch `admin` di development asli: dokumen publik, antrean, dashboard, permission/sidebar, seeder role, dan route terkait. Perubahan itu tidak disalin atau ditimpa oleh pekerjaan ini. `routes/web.php` di kedua repo sengaja tetap berbeda: hanya dua baris route `sync.logs.details` disisipkan ke masing-masing versi. Berkas lingkup lainnya disamakan dengan workspace teruji. Konfigurasi lingkungan, fixture pribadi, serta perubahan `.gitignore`, `package-lock.json`, dan `phpunit.xml` tetap dipertahankan.

Integrasi selesai pada23 berkas per repo:22 berkas identik dengan workspace teruji; route pada development asli mempertahankan perubahan branch admin dan hanya mendapat endpoint tambahan. Backup sebelum penyalinan: `/tmp/dadu90-integration-8e5ey34w`. Database aplikasi tidak diubah.

## 6. Command copy ulang development → repo Docker laptop

Salinan pada proses ini sudah dilakukan. Jika perlu mengulangi setelah pekerjaan ini, command berikut membuat backup dan menyalin hanya berkas lingkup90. Route tidak disalin utuh agar perubahan dokumen publik/antrean dari branch development tidak ikut menimpa repo Docker. Periksa perubahan lokal baru sebelum memakai command ulang.

```bash
python3 <<'DADU_COPY90'
from pathlib import Path
import shutil, tempfile
source = Path('/home/xdranel/Code/Project/php-laravel/dadu')
target = Path('/home/xdranel/Code/Project/secret/dadu-test')
files = [
    'app/Http/Controllers/StudentController.php',
    'resources/views/students/show.blade.php',
    'app/Services/StudentAcademicDetail.php',
    'resources/views/students/partials/study-records.blade.php',
    'resources/views/students/partials/language.blade.php',
    'resources/views/students/partials/tak.blade.php',
    'resources/views/students/partials/bpp.blade.php',
    'resources/views/students/partials/skpi.blade.php',
    'resources/views/students/partials/status-badge.blade.php',
    'tests/Feature/StudentAcademicDetailTest.php',
    'app/Http/Controllers/SyncController.php',
    'app/Models/SyncLog.php',
    'resources/views/sync/index.blade.php',
    'app/Support/JsonSourceLocations.php',
    'app/Support/SyncImportIssues.php',
    'resources/views/sync/issues-modal.blade.php',
    'database/migrations/2026_11_10_000001_add_issue_details_to_sync_logs_table.php',
    'tests/Unit/JsonSourceLocationsTest.php',
    'tests/Feature/SyncImportDetailsTest.php',
    'docs/88-rancangan-detail-mahasiswa-dan-catatan-sinkronisasi.md',
    'docs/89-rencana-implementasi-detail-mahasiswa-dan-sinkronisasi.md',
    'docs/90-hasil-detail-mahasiswa-dan-catatan-sinkronisasi.md',
]
route = target / 'routes/web.php'
route_text = route.read_text()
addition = """        Route::get('sync/logs/{syncLog}/details', [SyncController::class, 'logDetails'])
            ->middleware('permission:sync_read')->name('sync.logs.details');
"""
anchor = "        Route::get('sync/status', [SyncController::class, 'status'])"
if "name('sync.logs.details')" not in route_text:
    if route_text.count(anchor) != 1:
        raise SystemExit('Berhenti: lokasi route berbeda; periksa sebelum copy.')
    route_text = route_text.replace(anchor, addition + anchor, 1)
elif addition not in route_text:
    raise SystemExit('Berhenti: route detail sudah ada dengan isi berbeda; periksa manual.')
for relative in files:
    if not (source / relative).is_file():
        raise SystemExit('Berkas sumber tidak ditemukan: ' + relative)
backup = Path(tempfile.mkdtemp(prefix='dadu-copy90-'))
for relative in files + ['routes/web.php']:
    destination = target / relative
    if destination.exists():
        saved = backup / relative
        saved.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(destination, saved)
for relative in files:
    destination = target / relative
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source / relative, destination)
    assert destination.read_bytes() == (source / relative).read_bytes(), relative
route.write_text(route_text)
print('Copy selesai; backup:', backup)
DADU_COPY90
```

## 7. Verifikasi

- Sebelum implementasi, tes mereproduksi data EPRT resmi yang tidak terbaca, empat bagian studi yang belum ada, hilangnya detail setelah catatan10, serta kolom/route/modal detail yang belum tersedia.
- Gate detail mahasiswa, log baru, parser lokasi, dan regresi impor akademik/KRS: **94 tes lulus, 689 assertion**. Tambahan regresi parser terakhir juga lulus; seluruh **9 tes parser/identitas lulus**.
- Suite lengkap: **878 lulus, 43 gagal, 7.767 assertion**. Semua43 signature kegagalan sama dengan baseline development yang diambil sebelum integrasi; tidak ada kegagalan baru. Keduanya dibandingkan pada database `dadu_testing` dengan timezone UTC seperti konfigurasi development saat ini. Gate terkait sebelumnya juga lulus dengan Asia/Jakarta.
- Kegagalan baseline: `Auth/PasswordResetTest` (3), `Auth/PasswordUpdateTest` (1), `ExamScheduleTest` (4), `GraduationRegistrationTest` (8), `PublicDocumentTest` (2), `QueueHistoryAndLogTest` (12), `QueueTicketTest` (4), `SyncTest` (8), dan `SidangTaHistoryTest` (1). Tes terakhir mengharapkan08:06WIB meskipun fixture dibuat memakai timezone UTC sehingga formatter menghasilkan15:06WIB; ini sudah terjadi sebelum perubahan90 dan bukan perbaikan pada lingkup ini.
- Sesudah integrasi, **23 tes baru lulus pada development asli dengan197 assertion**, termasuk route detail yang digabung tanpa menimpa perubahan branch admin. Rescan akhir mengonfirmasi hanya perbedaan branch yang sudah ada sebelumnya yang tersisa.
- Seluruh **22 tes JavaScript lulus**, build Vite berhasil, **11 berkas PHP non-Blade lulus lint**, sintaks JavaScript modal valid, dan seluruh Blade terkompilasi.
- Review independen pada kedua modul selesai. Temuan TAK null dan approval SKPI null diperbaiki dengan regresi. Pemetaan lokasi root object dengan kolom `data` skalar juga diperbaiki setelah regresi membuktikan path keliru.

Verifikasi memakai request/render Blade/DOM, tes parser dan JavaScript, serta build. Belum dilakukan pemeriksaan browser interaktif atau runtime image server. Tidak dilakukan impor data aplikasi, reset/seed, maupun perubahan SSO/produksi.
