# 06 — Checklist pengujian dan rescan student Sidang TA

**Pembaruan keputusan testing:** pengguna mengonfirmasi checkpoint B lulus dan memilih MySQL Podman, tanpa SQLite. Ikuti [09](09-checkpoint-c1-struktur-draft-mysql.md) untuk konfigurasi MySQL dan kode lengkap C1. Instruksi SQLite pada catatan/scan awal di bawah bersifat historis dan tidak dipakai lagi. Contoh memakai schema dadu_testing di server Podman yang sama karena RefreshDatabase dapat mereset tabel tujuan. C dibagi menjadi C1 struktur data dan C2 service/endpoint tahap 1 agar setiap bagian punya test yang dapat dijalankan.

Gunakan sesudah tiap checkpoint [05](05-langkah-form-empat-tahap-sidang-ta.md), bukan hanya setelah semua coding selesai. Saat dokumen ini dibuat, semua test khusus Sidang TA di bawah **masih rencana**, belum ada dan belum dijalankan.

Pembaruan: checkpoint B sekarang mempunyai **kode test lengkap siap salin** pada [08 bagian 6](08-checkpoint-b-periode-sidang-ta.md#6-periksa-checkpoint-ini). Jalankan file itu dahulu di folder asli; tidak perlu memindah hasil ke dadu-test sebelum test lulus. Daftar draft/final di bawah tetap target coverage tahap berikutnya, bukan instruksi membuat test hanya dari nama. Kode lengkap untuk checkpoint tersebut harus disertakan pada panduan rinci sebelum diminta dijalankan.

## Urutan kerja pengguna dan asisten

1. Pengguna mengerjakan satu checkpoint di folder asli, menyimpan perubahan dan hasil pemeriksaan.
2. Pengguna memindahkan berkas sumber yang berubah ke dadu-test, termasuk migrasi, requests, JS/view dan tests. Tidak perlu menyalin `.env`, database, vendor atau node_modules untuk review sumber.
3. Pengguna menyebut checkpoint yang selesai dan error/hasil uji. Jika hanya beberapa berkas dipindah, sebutkan agar scan tidak menganggap versi campuran sebagai implementasi lengkap.
4. Asisten membaca ulang sumber aktual kedua folder untuk area relevan, membandingkan diff/hash serta panduan. Jangan menganggap file yang bernama sama pasti berisi versi sama.
5. Asisten menulis temuan ke `docs/*.md` dengan format: tingkat dampak, file/baris aktual, langkah reproduksi, expected/actual, langkah perbaikan dan tes pembuktian. Kode tetap dikerjakan pengguna.
6. Perbarui catatan [07](07-catatan-pendamping-sidang-ta.md): checkpoint benar-benar selesai, bukti uji, temuan terbuka, keputusan berubah. Jangan mengklaim upload/browser berhasil dari scan statis saja.

Jika temuan membuat spesifikasi berubah (misalnya aturan periode/revisi), perbarui dokumen 03 dan 05 sekaligus. Hindari menambah panduan baru yang bertentangan dengan panduan lama tanpa menandainya.

## Paket bukti per checkpoint

| Checkpoint | Yang dikirim pengguna | Yang diperiksa ulang |
| --- | --- | --- |
| A upload | Cara menjalankan HTTP/PHP, nilai limit sesudah restart, jumlah/ukuran file dan status response | PHP host vs MySQL container; per file vs total; form lama belum diubah. |
| B periode | Model/query, aksi pengelolaan periode, select tahap 1, hasil test periode | Dua periode terbuka bisa dipilih; closed/tampered ID ditolak; dashboard tidak first() acak. |
| C draft | Migrasi baru, models, routes, service/context, requests, hasil test ownership | Draft tidak masuk final; field NOT NULL final aman; hak akses per source. |
| D simpan | Validasi/upload/discard/revisi, error storage test | Whitelist dan existing file fisik; version; cleanup tanpa kehilangan file lama. |
| E wizard | Blade/JS/import dan screenshot tahap/review/resume, build | Empat tahap terkunci; save beneran; buka ulang; error tidak menghilangkan berkas tersimpan. |
| F final | Final requests/controller/service dan test final/race/retry | Tidak ada endpoint bypass; transaksi dan log tepat satu; draft consumed aman. |
| G detail | Index/detail/dashboard, screenshot mobile, test owner/download | Group snapshot historis, timeline, catatan reviewer, izin dan empty-state. |

## Pengujian otomatis yang direncanakan

Gunakan fake storage local pada test file, waktu terkendali, periode/Student/user/permission buatan test sendiri. Pastikan `phpunit.xml` dan environment proses benar-benar menggunakan DB testing sebelum memakai RefreshDatabase. Jangan memakai `migrate:fresh` untuk menjalankan daftar ini pada database aplikasi.

### `tests/Feature/SidangTaPeriodTest.php`

| Nama test | Assertion penting |
| --- | --- |
| `lists_only_open_periods` | Aktif+dalam rentang tampil; inactive/future/closed tidak menjadi pilihan baru. |
| `allows_two_open_periods` | Mengaktifkan/mengedit periode kedua tidak mematikan pertama; kedua ID dapat dipilih. |
| `rejects_closed_or_tampered_period_id` | ID tak ada/tertutup/diubah saat save ditolak tanpa draft baru. |
| `rechecks_period_at_submit` | Draft lengkap sebelum tenggat, final setelah tenggat ditolak; data/file masih ada. |
| `retains_closed_period_on_resume` | Periode lama diberi label ditutup dan draft tetap dapat dilihat/dialihkan ke periode terbuka. |
| `prevents_deleting_period_used_by_draft` | Penghapusan ditolak dengan response yang jelas, bukan error FK mentah. |

### `tests/Feature/SidangTaDraftTest.php`

| Nama test | Assertion penting |
| --- | --- |
| `starting_draft_does_not_submit_registration` | Row hanya di draft; registration/log final tetap nol. |
| `resumes_saved_payload_and_files` | Request baru dari user sama mendapatkan kontak/periode/tahap dan file yang sama. |
| `persists_files_after_logout` | Sesudah sesi baru/login ulang file tidak hilang; hanya session bukan media penyimpanan utama. |
| `rejects_skipped_step` | POST/GET tahap 3 atau 4 sebelum prasyarat ditolak/diarahkan; tidak menyimpan data/file tahap itu. |
| `rejects_unknown_document_key` | File key buatan dan file tahap lain tidak tersimpan ke storage/DB. |
| `validates_file_size_and_content` | Ukuran 10240 KiB diterima, di atas ditolak; isi salah MIME ditolak; tahap 3 baru hanya PDF. |
| `requires_existing_file_on_disk` | Existing row dengan path hilang tidak dihitung lengkap; minta unggah ulang. |
| `switches_biodata_branch_safely` | 1 membutuhkan dua PDF, 0 satu PDF; perubahan gagal tidak merusak cabang server lama; sukses menyisakan syarat aktif saja. |
| `keeps_old_file_when_replacement_fails` | Simulasikan false/throw storage atau DB failure; old file dan metadata tetap tersedia; path baru parsial dibersihkan. |
| `rejects_stale_draft_version` | Dua save dengan versi sama: kedua ditolak 409, tidak overwrite dan tidak meninggalkan file orphan baru. |
| `invalidates_review_after_saved_change` | Setiap save sukses menaikkan version dan mengosongkan reviewed_version. |
| `draft_access_is_owner_only` | User berbeda tidak boleh resume/save/review/download/delete draft walau menebak UUID. Reviewer tidak otomatis membaca draft. |
| `enforces_active_role_permissions` | Krisna dua role pada sesi Mahasiswa tidak mendapat review; sesi Petugas tidak otomatis mendapat create/update. Custom role dengan permission yang tepat tetap didukung. |
| `discard_does_not_delete_final_files` | Draft revisi dibuang; berkas final yang direferensikan tetap utuh; draft-owned file dapat dibersihkan. |

### `tests/Feature/SidangTaRegistrationTest.php`

| Nama test | Assertion penting |
| --- | --- |
| `rejects_submit_without_valid_action_or_draft` | Endpoint lama sekalipun tidak menyimpan submitted saat action/draft hilang atau action salah. |
| `rejects_submit_without_review_or_complete_steps` | Jangan percaya checkbox client; draft incomplete/tidak ditinjau ditolak tanpa registration/log. |
| `submits_accurate_branch_with_fifteen_documents` | Kontak/periode/snapshot server, 15 jenis tepat, tidak ada PDM, satu log submitted. |
| `submits_pdm_branch_with_fourteen_documents` | 14 jenis tepat, tidak ada dua biodata lama, satu log submitted. |
| `final_submit_uses_saved_files` | Final request tanpa file berhasil; file path datang dari draft, bukan client. |
| `repeated_submit_returns_same_registration` | Dua request final draft yang sama: satu registration/log; replay tidak mengubah status yang kemudian diputuskan reviewer. |
| `blocks_second_active_registration_across_periods` | NIM sudah submitted/needs_review tidak membuat final baru pada periode lain. |
| `revision_changes_final_only_at_submit` | Save draft revisi tidak mengubah final; finalisasi baru mengganti dokumen/status/snapshot/log resubmitted. |
| `rejects_revision_after_reviewer_changes_final` | Status/lock_version source berubah: 409, tanpa overwrite, file final tetap utuh. |
| `rejects_stale_reviewer_after_resubmission` | Hidden registration_version halaman petugas sudah lama: 409 sesudah student mengirim revisi; submission baru tidak tertimpa. |
| `consumed_draft_releases_document_references` | Setelah revisi, header consumed tetap ada tetapi row draft-document dihapus; revisi berikutnya bisa mengganti cabang tanpa error FK atau file tertahan selamanya. |
| `preserves_revision_after_storage_or_db_failure` | Metadata/file final lama, reviewer notes dan status tidak berubah; retry draft masih mungkin. |
| `allows_revision_after_period_closed` | Start/save tahap 1 sampai final tetap boleh pada source needs_review yang periodenya tertutup; tidak dapat memindah periode. |
| `prevents_access_to_another_students_registration` | Show/edit/update/download milik NIM lain ditolak; reviewer berizin hanya dapat mengakses final sesuai jalur yang ada. |
| `renders_historical_snapshot_with_missing_values` | Snapshot null/nilai nol/field tambahan/notes panjang tidak crash atau tertukar menjadi data live. |

Untuk checkpoint B saat ini, gunakan perintah pada 08 dengan koneksi SQLite memory eksplisit. Perintah di bawah adalah referensi untuk checkpoint **C–G nanti**, setelah kode test lengkap disediakan dan fitur terkait sudah diimplementasikan; jangan menjalankan filter file yang belum ada lalu menganggap hasil nol test sebagai kelulusan:

```bash
php artisan test --filter=SidangTaPeriodTest
php artisan test --filter=SidangTaDraftTest
php artisan test --filter=SidangTaRegistrationTest
php artisan test --filter=RoleSwitchTest
npm run build
```

Setelah semuanya lulus, jalankan suite relevan lain bila perubahan permission/dashboard berdampak ke sana. Test existing mungkin memiliki asumsi seed yang berbeda; laporkan kegagalan baseline secara terpisah, jangan menutupi dengan menonaktifkan auth/permission middleware.

## Pengujian konkurensi pada MySQL terpisah

SQLite memory tidak memvalidasi perilaku row lock MySQL. Gunakan database testing MySQL terpisah dan dua koneksi/proses:

- [ ] Dua init untuk NIM yang sama bersamaan menghasilkan satu draft aktif.
- [ ] Dua final pada draft sama menghasilkan satu registration dan log, response konsisten.
- [ ] Dua final draft berbeda untuk NIM sama tidak menghasilkan dua pengajuan aktif.
- [ ] Dua save versi sama: satu sukses dan satu konflik; file request kalah dibersihkan.
- [ ] Review petugas terjadi saat student final revisi: status dan lock_version melindungi kedua arah, termasuk halaman review petugas yang sudah lama.

Catat DB engine/version, langkah dua koneksi, hasil row/log dan error. Jangan menganggap uji sequential retry membuktikan tidak ada race.

## Checklist browser nyata

- [ ] Login akun dummy, pilih Mahasiswa, pastikan sidebar/menu sesuai; ulangi pergantian ke Petugas lalu kembali untuk melihat izin berubah.
- [ ] Dua periode terbuka tampil dalam tahap 1 dengan nama/tanggal jelas. Pilih salah satunya, simpan, refresh: pilihan tidak berubah sendiri.
- [ ] Tahap 2–4 terkunci sebelum tahap sebelumnya tersimpan; URL manual tetap tidak bisa melewati prasyarat.
- [ ] Simpan tahap 2 dengan enam file sekitar 1 MiB; tutup tab/browser, login ulang: file tersimpan dapat diunduh.
- [ ] Tahap 3 dengan tujuh PDF dan tanggal; tanggal masa depan ditolak. Resume tetap menampilkan file tahap 2.
- [ ] Tahap 4 kedua cabang; coba bolak-balik pilihan sebelum/sesudah simpan dan pastikan tidak mengirim file cabang tersembunyi.
- [ ] File dipilih tetapi belum disimpan diberi label jelas; ketika navigasi keluar ada peringatan. Jangan mengharapkan file belum disimpan pulih setelah browser ditutup.
- [ ] Review menampilkan seluruh field dan daftar 14/15 file tersimpan; edit satu tahap membatalkan tinjau sebelumnya.
- [ ] Klik Kirim ganda/Enter/reload response tidak menggandakan pengajuan. Final request tidak membawa semua file ulang.
- [ ] Setelah error server/storage/network, draft lama tetap tersedia dan pengguna dapat mencoba ulang tanpa mengulang file yang sudah tersimpan.
- [ ] Revisi dapat memakai existing final yang masih ada; pilih pengganti, simpan draft, lalu cek final lama belum berubah sebelum kirim.
- [ ] File invalid 11 MiB/format salah mendapat pesan sesuai; file yang ditolak tidak dianggap tersimpan.
- [ ] Tampilan mobile, keyboard Tab/Enter, label fokus, error summary, nama file panjang dan catatan reviewer panjang terbaca.
- [ ] Riwayat final dan kartu draft terpisah; detail hanya memakai snapshot saat pengajuan, bukan data akademik live.

Untuk error body terlalu besar: uji transport nyata dan nilai PHP HTTP mengikuti [04](04-perbaikan-upload-sidang-ta.md). Framework fake upload bukan pengganti pengujian ini.

## Scan ulang statis yang wajib

Cari ulang `SidangTaPeriod::active`, `saveDocuments`, `$request->file('documents')`, `action`, `needs_review`, `draft`, route download dan penggunaan snapshot. Pastikan endpoint lama tidak menjalankan implementasi final lama. Periksa juga migrations, factories/fixtures, Blade import JS sebelum Alpine.start, permission seed/migration, dashboard, dan penghapusan file consumed/revisi.

Bandingkan sumber sebelum/sesudah menulis setiap revisi dokumen. Perubahan dari pengguna saat scan berlangsung dicatat sebagai perubahan eksternal, bukan ditimpa. Saat sesi awal ini, scan hash sebelum/sesudah setiap dokumen menunjukkan tidak ada perubahan source oleh asisten. Belum ada hasil test aplikasi/browser untuk dinyatakan lulus.


Pembaruan E: [17](17-checkpoint-e-wizard-student.md) menyediakan kode 10 test wizard/review lengkap, command build dan checklist browser. Ini menggantikan daftar target saja untuk checkpoint E. Suite yang sudah lulus tetap dijalankan pengguna sebagai regresi di MySQL Podman.
