# Pelaksanaan Sidang TA oleh admin — Implementation Plan

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

> Pelaksanaan sesuai permintaan lanjut pengguna; agen utama mengintegrasikan dan memverifikasi. Pekerjaan domain data dan tampilan didelegasikan pada file terpisah; seluruh tes database diserialkan oleh agen utama.

**Goal:** Dua jalur plotting/pencarian admin dengan mahasiswa read-only,90 menit, riwayat dan hasil sidang.

**Architecture:** Perluas konteks/lock booking yang ada. DTO info bersama menyatukan sumber akademik, plotting dan snapshot; model jadwal tetap satu per pengajuan. Route UUID dan otorisasi120 dipertahankan.

**Tech Stack:** Laravel13, MySQL, Blade/Alpine, OpenSpout yang sudah tersedia, Pest.

**Spec:** [123](123-rancangan-pelaksanaan-sidang-ta-admin.md), keputusan bisnis [121](121-catatan-divisi-pelaksanaan-sidang-ta.md).

## Global constraints

Dokumentasi target saja; implementasi/test disamakan ke dadu sesudah verifikasi. Backup/hash/staging dipertahankan. Tidak menulis DB aplikasi/VPS atau mengubah SSO/peminjaman biasa. Hanya primary menjalankan tes DB. Tidak commit/push. Klarifikasi pengguna: ruang Excel hanya preferensi/pilihan awal, selalu ada tombol Tetapkan Jadwal sebelum ruangan dipesan.

## Kontrak bersama

- `SidangTaSchedule`: tambah `participant_data`/`details_snapshot` arraynullable, `planning_source` string system/import/legacy, `preferred_date` dateNullable, `preferred_start_time`/`preferred_end_time` nullabletime, `preferred_room_id` nullableFKrooms, `result_status` pending/passed/failed, `result_notes`, `result_by`, `result_at`. `lock_version` tetap sumber kontrol versi. Relasi `executionLogs()` menuju model baru `SidangTaExecutionLog`.
- `SidangTaExecutionLog`: schedule FK, `action`, `from_status`, `to_status`, `notes`, `data` JSON, `performed_by`, `performed_at`; tidak mencampur audit ini ke riwayat pendaftaran mahasiswa.
- `SidangTaExecutionSetting`: tambah `is_active` boolean true; default baru90 menit dan tanpa istirahat wajib. Migration mengubah setting pencarian lama menjadi90 tanpa mengubah occurrence booking; naikkan versi agar token lama stale. Fixture legacy dapat menyebut120 secara eksplisit.
- `SidangTaExecutionInfoService::forRegistration(SidangTaRegistration $reg, bool $historical=false): array` mengembalikan flat DTO: `period`, `student_number`, `student_name`, `study_program`, `registration_status`, `expertise_group`, `title`, `advisor_1_code`, `advisor_2_code`, `examiner_1_code`, `examiner_2_code`, `weekday`, `date` ISO|null, `start_time`/`end_time` HH:MM|null, `room` string|null, `room_id` int|null, `is_confirmed` bool, `result_status`, `result_label`, `scheduling_state`. `capture(SidangTaRegistration $reg): void` menyimpan snapshot konteks setelah penetapan/koreksi dalam transaksi pemanggil.
- `SidangTaExecutionOutcomeService::set(User $actor, SidangTaRegistration $reg, string $status, int $version, ?string $notes=null): SidangTaSchedule`: reviewer saja; pending adalah undo; simpan audit, naikkan versi; approval pendaftaran/berkas tidak berubah.
- `SidangTaPlottingImportService::import(User $actor, SidangTaPeriod $period, string $path): array` → `processed`, `skipped`, `errors` array berisi NIM/baris/alasan. Header16 kolom; periode dipilih admin; tiap baris atomic; pengajuan approved harus ada; duplikat tidak menimpa diam-diam. Room kosong hanya preferensi. Room terisi disimpan sebagai preferred_room_id; tidak membuat booking saat impor.
- `SidangTaExecutionExport::download(Collection $registrations): BinaryFileResponse`: XLSX16 kolom sesuai DTO/Excel, string aman dari formula.
- `SidangTaStaffScheduling::preview(User $actor, SidangTaRegistration $reg, string $date): array` → `options` (`candidate`, `token`, `recommended`), `context_version`, `reason`. Candidate date,room_id,room_code,room_name,start_time,end_time,duration_minutes. `confirm(User,Registration,string $token): SidangTaSchedule`. Koreksi lama tetap memakai Administration; capture snapshot setelahnya.

## Task1 — fondasi dan penjadwalan (primary)

- [x] Tulis HTTP RED: halaman mahasiswa/info, daftar admin, larangan POST mahasiswa.
- [x] Migration/model metadata, logs, is_active/default90; snapshot awal/hash backup.
- [x] StaffScheduling kandidat90/alternatif, token, lock/recheck, penetapan dan koreksi; student search/confirm ditutup. Settings mengikuti aktivasi dan jam istirahat yang boleh dipakai.
- [x] Tes jadwal/konflik/stale/alternatif/kelas serta regresi lifecycle lama.

## Task2 — data plotting/info/hasil/ekspor (agen domain)

- [x] Tulis tes importer/DTO/snapshot/outcome; koordinasikan RED DB dengan primary.
- [x] Implementasikan empat service kontrak di atas, hanya file domain sendiri dan tesnya. Tidak mengubah model/migration/route/controller/shared service.
- [x] Periksa parser tanggal serial/DateTime, duplikatNIM, file hilang/formula, penetapan lama tidak tertimpa, pemilik/prodi tidak diambil dari kolom file untuk izin.

## Task3 — HTTP dan tampilan (agen UI)

- [x] Controller mahasiswa `SidangTaExecutionController@index/show`, admin `Admin\SidangTaExecutionController@index/settings/show/preview/confirm/import/export/outcome/update`; routes/sidebar; view execution mahasiswa/admin/detail/settings/info.
- [x] Gunakan DTO/domain contracts; readonly scope sama Review; student tidak menulis. Route `sidang-ta-execution.index/show`, `admin.sidang-ta-execution.index/settings/show/preview/confirm/import/export/outcome/update`; show/aksi memakai `sidang_ta_registration`, update memakai `period` UUID.
- [x] Daftar filter/count/pagination/status; kartu pengaturan penuh; import+export; detail rekomendasi/dropdown dan hasil+undo. Pindahkan tombol/kartu dari Review, hapus kartu booking mahasiswa. Jadwal setting historis collapse, input campus style, tombol Kembali.
- [x] Tes HTTP halaman/perizinan/riwayat snapshot, tanpa menjalankan DB paralel.

## Task4 — integrasi dan rescan (primary)

- [x] Tetapkan pilihan pengguna: impor tidak membuat booking; ruang terisi hanya pilihan awal yang tetap bisa diganti admin.
- [x] Integrasikan, review hasil agen, jalankan tes terarah dan suite penuh; laporkan baseline45 kegagalan dari122 secara eksplisit.
- [x] Build/lint/tinjauan keamanan; samakan ke dadu dengan backup/hash; docs125 berisi hasil, alur student/admin, pertanyaan yang masih menunggu, dan command migration/update biasa.

## Review focus

Lintas prodi/mahasiswa; URL lama yang masih bisa mengubah jadwal; import ulang saat booking aktif; dua admin memilih ruang sama; hasil/undo bersamaan reschedule/cancel; snapshot historis setelah metadata baru; sumber jadwal kosong; batas tanggal/jam dan perubahan durasi tanpa merusak booking lama.

## Hasil

Seluruh task implementasi/rescan selesai; hasil dan batasan suite penuh (45 kegagalan baseline yang sama, tanpa tambahan) ada pada [125](125-hasil-pelaksanaan-sidang-ta-admin.md). Tidak commit/push/deploy atau migrasi DB aplikasi.
