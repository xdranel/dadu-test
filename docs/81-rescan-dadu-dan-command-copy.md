# 81 — Rescan development dadu dan command copy ke dadu-test

## 1. Hasil perbandingan

Perbandingan berdasarkan isi/SHA-256, bukan hanya tanggal modifikasi atau `git status`.

- Sumber: `/home/xdranel/Code/Project/php-laravel/dadu`, HEAD `ec0ee1a`.
- Tujuan: `/home/xdranel/Code/Project/secret/dadu-test`, HEAD `fdbd96e`.
- 585 berkas sumber publik sama persis dengan tujuan.
- 58 berkas sumber publik berbeda atau belum ada pada tujuan. Sebanyak **54 berkas kode/aset/tes dipilih untuk copy**: 24 berbeda dan 30 baru pada tujuan.
- Empat perbedaan khusus lingkungan/dokumentasi dipertahankan pada tujuan, dijelaskan pada bagian4.
- Perbaikan80 pada template tinjau dan tes regresinya sudah sama pada kedua repo.
- Folder JSON privat di development asli berisi 17 berkas, sedangkan folder tersebut belum ada pada dadu-test. Command terpisah tersedia pada bagian5.

File runtime, `.env` aktual, vendor, node_modules, cache, hasil build, storage/upload, backup dan metadata Git tidak menjadi daftar copy kode.

## 2. Pembaruan yang ditemukan

| Kelompok | Isi | Jumlah berkas |
| --- | --- | --- |
| Aplikasi | Antrean/riwayat/event/ekspor, login lokal dan SSO, profil Student/User, izin media panduan TA | 14 |
| Konfigurasi | Timezone MySQL, retensi riwayat antrean, flag SSO lokal, sidebar panduan TA | 4 |
| Migration | Call sequence, status skipped, tabel event, indeks riwayat antrean | 4 |
| Audio | Angka dan frasa panggilan antrean | 17 |
| View | Sidebar/profil, antrean/riwayat, teks form Sidang TA | 8 |
| Route | Web dan jadwal ekspor bulanan antrean | 2 |
| Tes | Authentication, audio/riwayat/skip antrean, profil SSO mahasiswa | 5 |

Empat migration ini merupakan tambahan antrean. Saat menerapkan pada server, gunakan migration biasa dalam alur update; tidak perlu fresh/seed.

`config/database.php` menambahkan `DB_TIMEZONE` dengan default `+07:00`; host/port/credential dan charset tetap berbasis environment seperti target. `config/queue.php` menambahkan retensi default 24 bulan. `config/services.php` menambahkan `SSO_IN_LOCAL=false`, yang hanya diperiksa pada `APP_ENV=local`; Docker tetap mengikuti environment staging. Ketiga konfigurasi tersebut sudah diperiksa silang dengan Compose.

## 3. Command copy kode di laptop

Script `81-copy-dadu-ke-dadu-test.sh` berisi daftar54 berkas dan hash sumber/tujuan hasil rescan. Seluruh sumber dan tujuan diperiksa sebelum copy pertama. Jika sumber atau tujuan berubah sejak rescan, script berhenti agar daftar diperiksa ulang. Tujuan yang sudah identik dengan sumber boleh diproses ulang.

Periksa daftar tanpa mengubah berkas:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
bash docs/81-copy-dadu-ke-dadu-test.sh --dry-run
```

Salin berkas yang sudah diperiksa:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
bash docs/81-copy-dadu-ke-dadu-test.sh --copy
git diff --stat
git status --short
```

Script membuat backup berkas tujuan lama pada folder `dadu-copy81.*` di direktori temporary, mencetak lokasinya, menyalin dengan izin0644, lalu membandingkan setiap hasil dengan sumber. Script tidak menghapus berkas lain, menjalankan Git commit/push, build, migration, seed, atau deploy.

Daftar lengkap:

| Berkas | Pada dadu-test sebelum copy |
| --- | --- |
| `app/Console/Commands/QueueExportMonthCommand.php` | Belum ada |
| `app/Console/Commands/QueuePruneHistoryCommand.php` | Belum ada |
| `app/Http/Controllers/Admin/QueueController.php` | Berbeda |
| `app/Http/Controllers/Admin/QueueHistoryController.php` | Belum ada |
| `app/Http/Controllers/Admin/SidangTaFormGuideController.php` | Berbeda |
| `app/Http/Controllers/QueueController.php` | Berbeda |
| `app/Http/Requests/Auth/LoginRequest.php` | Berbeda |
| `app/Models/QueueState.php` | Berbeda |
| `app/Models/QueueTicket.php` | Berbeda |
| `app/Models/QueueTicketEvent.php` | Belum ada |
| `app/Models/QueueTicketHistory.php` | Berbeda |
| `app/Models/Student.php` | Berbeda |
| `app/Models/User.php` | Berbeda |
| `app/Services/QueueCounterService.php` | Berbeda |
| `config/database.php` | Berbeda |
| `config/queue.php` | Berbeda |
| `config/services.php` | Berbeda |
| `config/sidebar.php` | Berbeda |
| `database/migrations/2026_10_27_000001_add_call_seq_to_queue_states_table.php` | Belum ada |
| `database/migrations/2026_10_28_000001_add_skipped_status_to_queue_tickets_tables.php` | Belum ada |
| `database/migrations/2026_10_28_000002_create_queue_ticket_events_table.php` | Belum ada |
| `database/migrations/2026_10_28_000003_add_performance_indexes_to_queue_ticket_histories_table.php` | Belum ada |
| `public/audio/queue/1.mp3` | Belum ada |
| `public/audio/queue/10.mp3` | Belum ada |
| `public/audio/queue/11.mp3` | Belum ada |
| `public/audio/queue/2.mp3` | Belum ada |
| `public/audio/queue/3.mp3` | Belum ada |
| `public/audio/queue/4.mp3` | Belum ada |
| `public/audio/queue/5.mp3` | Belum ada |
| `public/audio/queue/6.mp3` | Belum ada |
| `public/audio/queue/7.mp3` | Belum ada |
| `public/audio/queue/8.mp3` | Belum ada |
| `public/audio/queue/9.mp3` | Belum ada |
| `public/audio/queue/Loket.mp3` | Belum ada |
| `public/audio/queue/belas.mp3` | Belum ada |
| `public/audio/queue/nomor_antrian.mp3` | Belum ada |
| `public/audio/queue/puluh.mp3` | Belum ada |
| `public/audio/queue/seratus.mp3` | Belum ada |
| `public/audio/queue/silakan_menuju.mp3` | Belum ada |
| `resources/views/components/sidebar.blade.php` | Berbeda |
| `resources/views/queue-tickets/admin.blade.php` | Berbeda |
| `resources/views/queue-tickets/display.blade.php` | Berbeda |
| `resources/views/queue-tickets/history.blade.php` | Belum ada |
| `resources/views/queue-tickets/status.blade.php` | Berbeda |
| `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php` | Berbeda |
| `resources/views/sidang_ta_registrations/wizard.blade.php` | Berbeda |
| `resources/views/students/show.blade.php` | Berbeda |
| `routes/console.php` | Berbeda |
| `routes/web.php` | Berbeda |
| `tests/Feature/Auth/AuthenticationTest.php` | Berbeda |
| `tests/Feature/QueueAudioCallTest.php` | Belum ada |
| `tests/Feature/QueueHistoryAndLogTest.php` | Belum ada |
| `tests/Feature/QueueSkipAndDisplayTest.php` | Belum ada |
| `tests/Unit/StudentSsoProfileTest.php` | Belum ada |

## 4. Perbedaan yang dipertahankan

| Berkas | Alasan |
| --- | --- |
| `.gitignore` | Versi dadu-test juga melindungi `.env.docker`, backup dan private-import |
| `phpunit.xml` | Development asli memuat koneksi MySQL lokal pada port3307; dadu-test mempunyai konfigurasi tes berbeda. Jalankan tes MySQL dengan environment pengujian yang eksplisit bila diperlukan |
| `.env.example` | Template lokal berubah, termasuk timezone dan placeholder chatbot; perubahan kode konfigurasi sudah mempunyai default sehingga template Docker tidak harus ditimpa |
| `docs/fixtures/sidang-ta/README.md` | README lokal lebih ringkas; README tujuan memuat referensi fixture historis yang tetap dipertahankan |

`composer.json`, `composer.lock`, `package.json`, dan `package-lock.json` sudah identik. Tidak ada perubahan dependency yang perlu disalin. Berkas Dockerfile/Compose, `docker/**` khusus Docker, dan panduan lama milik tujuan tetap tersedia. File `.gitignore` storage yang hilang di development asli tidak dijadikan perintah penghapusan.

## 5. Opsional: copy fixture JSON privat

Untuk menyediakan kumpulan JSON yang sama di repo dadu-test laptop, jalankan blok ini. Termasuk ZIP impor yang ada pada sumber. Folder tetap diabaikan Git dan tidak ikut image Docker.

```bash
bash <<'COPY_PRIVATE_DADU81'
set -euo pipefail
source_dir=/home/xdranel/Code/Project/php-laravel/dadu
target_dir=/home/xdranel/Code/Project/secret/dadu-test
private_path=docs/fixtures/sidang-ta/data_dadu
git -C "$target_dir" check-ignore --quiet "$private_path/manifest.json"
install -d -m 0700 -- "$target_dir/$private_path"
cp -a -- "$source_dir/$private_path/." "$target_dir/$private_path/"
chmod -R go-rwx -- "$target_dir/$private_path"
python3 - <<'VERIFY_PRIVATE81'
from pathlib import Path
source = Path('/home/xdranel/Code/Project/php-laravel/dadu/docs/fixtures/sidang-ta/data_dadu')
target = Path('/home/xdranel/Code/Project/secret/dadu-test/docs/fixtures/sidang-ta/data_dadu')
files = [p for p in source.rglob('*') if p.is_file()]
for path in files:
    copied = target / path.relative_to(source)
    assert copied.is_file() and path.read_bytes() == copied.read_bytes(), str(path.relative_to(source))
print(f'{len(files)} berkas privat terverifikasi identik.')
VERIFY_PRIVATE81
COPY_PRIVATE_DADU81
```

## 6. Verifikasi dan langkah selanjutnya

- Syntax29 berkas PHP kode/konfigurasi/migration/tes yang dipilih lulus lint.
- Syntax Bash script copy dan seluruh blok command panduan lulus.
- Dry-run terhadap sumber/tujuan asli berhasil dan menemukan54 berkas sesuai daftar; tidak mengubah kode tujuan.
- Script diuji pada folder temporary: dry-run tanpa penulisan; copy54 berkas identik; pengulangan berhasil; berkas khusus Docker dipertahankan; sumber yang berubah menahan seluruh batch sebelum penulisan.
- Audit terpisah konfigurasi/dependency selesai. Pemeriksaan ini bukan hasil unit/feature suite atau bukti runtime server; suite aplikasi belum dijalankan untuk gabungan perubahan81.

Setelah copy, review perubahan lalu jalankan tes aplikasi dengan database pengujian. Commit/push melalui repo dadu-test setelah hasilnya ditinjau. Untuk server, gunakan [panduan78 bagian4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker), yang sudah mencakup build app/web, migration biasa, dan recreate app/web/scheduler/worker. Migration diperlukan karena ada empat berkas antrean yang baru.

Rescan ini hanya menambahkan panduan81 dan script copy pada repo dadu-test laptop. Kode aplikasi, database, dan server belum diubah oleh proses rescan ini.
