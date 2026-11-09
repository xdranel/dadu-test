# 83 — Rescan tugasAkhir, izin CRUD panduan, dan storage produksi

## 1. Rescan branch sebelum perbaikan

Tanggal 9 November2026. Development asli berada pada branch `tugasAkhir`, HEAD `a286c37`, sesudah merge main. Repo Docker laptop berada pada HEAD `cffa273`.

Perbandingan SHA-256 menemukan **639 berkas publik identik**. Empat perbedaan yang tetap dipertahankan: `.env.example`, `.gitignore`, `phpunit.xml`, dan README fixture. Tidak ditemukan kode aplikasi yang belum tersalin akibat perpindahan branch/merge. Folder privat tetap mengikuti hasil82. Storage runtime, environment aktual, vendor/node_modules, dan hasil build tidak disinkronkan.

## 2. Bug403 Administrator — penyebab dan perbaikan

Route/sidebar sudah memakai permission `sidang_ta_form_guide_read/create/update/delete`, tetapi `SidangTaFormGuideRequest::authorize()` masih memeriksa role `Akademik` saja. Akun Administrator yang diberi CRUD dapat membuka halaman/form, lalu ditolak ketika POST/PUT/PATCH sebelum controller dijalankan. Ini menjelaskan penolakan untuk link maupun gambar/PDF di semua lingkungan.

Request sekarang memeriksa permission sesuai aksi:

| Method | Permission |
| --- | --- |
| POST | `sidang_ta_form_guide_create` |
| PUT/PATCH | `sidang_ta_form_guide_update` |

Pemeriksaan menggunakan `user()->can(...)` agar tetap mengikuti izin pada Pengaturan Role dan bypass SuperAdmin/Akademik yang sudah ada pada `Gate::before`. Read/delete tetap diperiksa oleh middleware route. Akun tanpa permission tetap403; create tidak memberikan update/delete dan sebaliknya.

## 3. Bug upload SuperAdmin produksi

Tangkapan layar menunjukkan `League\Flysystem\UnableToCreateDirectory` saat membuat:

```text
/home/dadu-akd/apps/dadu/storage/app/sidang-ta/form-guides
```

Upload sudah melewati otorisasi dan mencapai operasi filesystem. Disk `local` proyek memakai root `storage/app`, sehingga path itu sesuai konfigurasi. Penyebab spesifik di server belum terverifikasi: bisa ownership/izin traversal/ACL, direktori privat milik akun CLI berbeda, mount read-only, kapasitas/inode/quota, atau kebijakan OS. Produksi diduga memakai PHP-FPM langsung, tetapi konfigurasi pool belum tersedia.

Initializer panduan juga memakai service yang sama. Salah satu hipotesis yang perlu diperiksa: initializer dijalankan sebagai deploy/root, lalu folder privat0700 tidak dapat ditelusuri oleh user worker FPM yang berbeda. Ini hipotesis, bukan hasil pemeriksaan produksi.

Perbaikan aplikasi menangkap kegagalan Flysystem dan hasil tulis `false`, mencatat detail teknis ke log, lalu menampilkan error field `file`:

> Berkas panduan gagal disimpan. Hubungi administrator untuk memeriksa penyimpanan server.

Data baru tidak dibuat ketika gagal. Saat penggantian gagal, record dan berkas lama tetap dipertahankan. Respons JSON422; pengiriman form browser kembali dengan pesan validasi. Perbaikan pesan ini **tidak memberikan izin filesystem**—upload produksi tetap memerlukan penanganan kondisi server yang terbukti bermasalah.

## 4. Pemeriksaan produksi untuk lead dev

Perintah berikut baca saja. Identifikasi worker untuk pool aplikasi ini, bukan proses master FPM milik root.

```bash
ps -eo pid,ppid,euser,egroup,args | awk '/[p]hp-fpm/'
namei -l /home/dadu-akd/apps/dadu/storage/app/sidang-ta/form-guides
findmnt -T /home/dadu-akd/apps/dadu/storage/app
df -h /home/dadu-akd/apps/dadu/storage/app
df -i /home/dadu-akd/apps/dadu/storage/app
```

Setelah mendapatkan PID worker yang benar, ganti placeholder berikut. Lead yang mempunyai akses server dapat membaca UID/GID/grup dan namespace worker:

```bash
worker_pid=GANTI_PID_WORKER
sudo awk '/^(Name|Uid|Gid|Groups):/' "/proc/$worker_pid/status"
sudo readlink -f "/proc/$worker_pid/root"
sudo readlink -f "/proc/$worker_pid/exe"
sudo getfacl -p \
  /home /home/dadu-akd /home/dadu-akd/apps \
  /home/dadu-akd/apps/dadu /home/dadu-akd/apps/dadu/storage \
  /home/dadu-akd/apps/dadu/storage/app \
  /home/dadu-akd/apps/dadu/storage/app/sidang-ta \
  /home/dadu-akd/apps/dadu/storage/app/sidang-ta/form-guides
```

Folder paling akhir mungkin memang belum ada; periksa juga parent terdekat yang sudah ada. Jika worker berada dalam chroot/container, cocokkan path dan mount dari namespace worker. Jika inode/kapasitas habis atau mount read-only, tangani kondisi itu. Bila mode/ACL tidak menjelaskan kegagalan, periksa quota dan penolakan SELinux/AppArmor yang berlaku pada host.

### Perbaikan terbatas bila ownership folder panduan terbukti salah

Blok ini untuk lead dev setelah user/group pool dan masalah ownership dipastikan. Ganti kedua placeholder. User runtime produksi tidak diasumsikan `www-data`.

```bash
bash <<'FIX_GUIDE_OWNERSHIP83'
set -euo pipefail
runtime_user=GANTI_USER_POOL_FPM
runtime_group=GANTI_GROUP_POOL_FPM
if [[ "$runtime_user" == GANTI_USER_POOL_FPM || "$runtime_group" == GANTI_GROUP_POOL_FPM ]]; then
  printf 'Isi user dan group pool FPM yang sudah terkonfirmasi.\n' >&2
  exit 1
fi
app_path=/home/dadu-akd/apps/dadu
id "$runtime_user"
getent group "$runtime_group"
# Parent harus sudah dapat ditelusuri oleh akun runtime.
sudo -u "$runtime_user" -g "$runtime_group" test -x "$app_path/storage/app"
sudo install -d -o "$runtime_user" -g "$runtime_group" -m 0700 -- \
  "$app_path/storage/app/sidang-ta" \
  "$app_path/storage/app/sidang-ta/form-guides"
sudo -u "$runtime_user" -g "$runtime_group" test -x "$app_path/storage/app/sidang-ta/form-guides"
sudo -u "$runtime_user" -g "$runtime_group" test -w "$app_path/storage/app/sidang-ta/form-guides"
FIX_GUIDE_OWNERSHIP83
```

Jika pemeriksaan parent gagal, lead perlu memperbaiki traversal/ACL pada parent yang benar-benar menahan akses. Bila deployment dan runtime sengaja memakai akun berbeda, tetapkan ACL/shared group terarah sesuai pola deployment; jangan memakai blok ownership tunggal tanpa menilai pola tersebut. Berkas panduan lama dapat memerlukan pemeriksaan akses tersendiri. Untuk menjalankan initializer di masa depan, gunakan identitas dan environment runtime yang sesuai agar direktori privat tidak kembali dimiliki akun berbeda.

Tes `sudo -u ... test` adalah pemeriksaan akun lewat CLI; hasilnya tidak membuktikan sandbox/chroot atau hardening FPM identik. Setelah kondisi host diperbaiki, lead tetap perlu menguji upload gambar dan PDF melalui web.

## 5. Berkas perubahan dan penerapan

| Berkas | Perubahan |
| --- | --- |
| `app/Http/Requests/SidangTaFormGuideRequest.php` | Otorisasi berdasarkan permission create/update |
| `app/Services/SidangTaFormGuideService.php` | Error storage menjadi validasi file, dengan logging dan rollback |
| `tests/Feature/SidangTaFormGuideTest.php` | Regresi role berizin, pemisahan permission, dan kegagalan penyimpanan |

Ketiga berkas teruji sudah disalin ke development asli pada branch `tugasAkhir` dan repo Docker laptop. Hash tujuan diperiksa sebelum penyalinan; hasil akhir diverifikasi identik dengan workspace teruji. Panduan83 tersedia pada `docs/` kedua repo. Konfigurasi khusus lingkungan dan data aplikasi dipertahankan. Server Docker/produksi belum diterapkan dari proses ini.

Tidak ada migration baru. Tidak diperlukan fresh, seed akun, atau pemberian semua permission secara otomatis. Administrator cukup mempunyai permission yang memang diaktifkan pada Pengaturan Role.

Docker dadu-test: setelah commit/push, gunakan [panduan78 bagian4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker). Produksi non-Docker: lead menerapkan versi kode yang sudah direview melalui pipeline yang biasa dipakai, menangani cache/opcache atau reload service FPM sesuai konfigurasi aktual, dan melakukan pemeriksaan filesystem bagian4. Proses ini tidak melakukan deploy atau perubahan server produksi.

## 6. Tes dan batas verifikasi

- Sebelum perbaikan:11 tes reproduksi gagal sesuai laporan—5 penolakan403 dan6 kegagalan storage500.
- Setelah perbaikan: **46 tes panduan/Pengaturan Role lulus, 433 assertion**.
- Tes mencakup Administrator berizin yang membuat/mengubah link, PNG, PDF; PUT/PATCH; akun tanpa izin; create/update yang terpisah; rollback dan pemeliharaan berkas lama.
- Error produksi direplikasi deterministik pada batas filesystem dengan `UnableToCreateDirectory`, `UnableToWriteFile`, dan hasil `false` untuk create/replacement. Tes tidak mengganti permission folder data lokal atau mengubah server.
- Review independen tidak menemukan masalah material. Penyebab filesystem spesifik produksi masih memerlukan hasil pemeriksaan host.

Baseline terbaru sesudah merge: **802 lulus, 44 gagal**. Suite akhir: **814 lulus, 43 gagal, 7.211 assertion**. Tidak ada signature kegagalan baru. Tes lama yang masih mengharuskan role Akademik walau permission CRUD sudah diberikan diperbarui mengikuti kontrak permission yang diminta. Kegagalan tersisa: `Auth/PasswordResetTest` (3), `Auth/PasswordUpdateTest` (1), `ExamScheduleTest` (4), `GraduationRegistrationTest` (8), `PublicDocumentTest` (2), `QueueHistoryAndLogTest` (12), `QueueTicketTest` (4), `SidangTaHistoryTest` (1), dan `SyncTest` (8). Semua masih terulang dari baseline; belum diperbaiki dalam pekerjaan ini.

Untuk mengulang tes terkait pada database pengujian yang sudah tersedia:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 \
DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 \
DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= \
php artisan test tests/Feature/SidangTaFormGuideTest.php tests/Feature/PermissionManagementTest.php --compact
```

Rujukan perilaku framework/library: [Laravel13 Form Request authorization](https://github.com/laravel/docs/blob/13.x/validation.md#authorizing-form-requests), [Laravel13 Gate interception](https://github.com/laravel/docs/blob/13.x/authorization.md#intercepting-gate-checks), dan [Flysystem exception handling](https://flysystem.thephpleague.com/docs/usage/exception-handling/). Dokumentasi dicocokkan melalui Context7; root disk dan alur exception juga diperiksa pada kode/dependency proyek.
