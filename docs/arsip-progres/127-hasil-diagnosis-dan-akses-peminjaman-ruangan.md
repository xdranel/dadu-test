# 127 — Diagnosis sumber jadwal dan akses peminjaman admin

Status: implementasi, reproduksi, pengujian dan penyelarasan source selesai; belum commit/push/deploy. Data VPS belum diubah. Rencana: [127](../catatan/127-rencana-pencarian-dan-akses-ruangan.md).

## Penyebab yang terkonfirmasi di VPS

Pemeriksaan 19 November 2026 dilakukan melalui SSH **baca saja**. Tidak mengubah master ruang, jadwal, akun, permission, source code atau container VPS.

| Data | Kondisi |
| --- | --- |
| Master ruangan |60: 46 Dapat Dipakai dan 14 Tidak Dapat Dipakai |
| Kelas aktif untuk TA |0; semua 60 belum diklasifikasikan |
| Pola mingguan |0 |
| Sumber semester |Tidak ada |
| Jadwal tanggal tertentu |1, dari `Dummy Json peminjaman ruangan.txt` |
| Impor JSON asli |0 baris; dibatalkan karena pemetaan alias ruang belum pasti |
| Periode akademik |2026/2027 Ganjil, 1 Agustus 2026–31 Januari 2027; sesuai 2627/1 |
| Pelaksanaan |23–27 November 2026,08.00–16.00 WIB,90 menit |

Jadi pesan pencarian bukan bukti bahwa semua ruangan terpakai. Belum ada kelas yang boleh dipilih dan belum ada sumber lengkap untuk mengetahui okupansi semester.

## Hasil pembacaan tiga sumber

- JSON asli memang pola satu minggu yang diulang selama semester.827 baris TETAP pada 9–15 November dibaca normalizer menjadi 825 pola unik, periode 2627/1. Periode pada VPS sudah benar.
- XLSX ruangan memiliki60 master tanpa kolom klasifikasi kelas. Impor menjaga flag existing dan tidak menganggap semua nama sebagai kelas secara otomatis.
- JSON memiliki69 label ruang unik. Tidak ada kecocokan langsung dengan kode/nama pada XLSX tanpa alias; sebagian lab/aula atau label tambahan belum mempunyai master yang jelas.
- Master pegawai/dosen tidak menjadi syarat algoritme ruang kosong. Data tersebut membantu identitas dosen; bentrok jadwal dosen belum diperiksa sesuai keputusan divisi.
- Reproduksi pada dadu_testing memakai XLSX dan JSON asli menghasilkan 60 room/60 flag null/46 aktif, lalu impor mingguan ditolak karena alias: **1 tes lulus, 9 assertion**. Sumber asli tidak diubah dan data pribadi dosen tidak disalin ke tes permanen.

Pemetaan tidak ditebak atau dilewati: [tabel label dan konfirmasi](../catatan/127-pemetaan-label-ruangan.md). Ada 44 calon berdasarkan teks KTT+kode,25 label belum memiliki pasangan pola tersebut; semuanya belum diterapkan dan tetap membutuhkan konfirmasi identitas fisik.

## Perubahan kode

1. Pesan pencarian Pelaksanaan TA membedakan klasifikasi kelas belum ada, sumber semester belum lengkap/periode belum mencakup tanggal, serta slot 90 menit yang benar-benar tidak tersedia. Jika dua prasyarat kurang, keduanya disebut beserta letak pengaturannya.
2. Helper peminjam bersama menerima admin/Akademik selain Pelaksana yang sudah diizinkan. Permission per tindakan tetap diperiksa.
3. Dashboard, Pengajuan dan Daftar Peminjaman Saya tidak lagi disembunyikan/dialihkan untuk Akademik. Review ditampilkan menurut permission Review, terpisah dari hak meminjam.
4. Admin operasional tanpa Review tetap dapat mengajukan dan membatalkan ordinary booking miliknya yang pending bila mempunyai permission yang diperlukan. Tidak dapat membatalkan booking akun lain atau yang sudah disetujui lewat jalur pemilik.
5. Pengajuan biasa admin tetap **pending**; tidak mengikuti aturan penetapan TA langsung approved. Tag ordinary booking pada review menjadi Peminjaman agar tidak salah menamai semua peminjam sebagai dosen.
6. Seeder, mapping SSO, sumber/alias, klasifikasi ruang, jadwal dan aturan bentrok tidak diubah otomatis. Akun admin operasional memerlukan Read/Create/Update pada Pengaturan Role sesuai kebutuhan; permission Review tidak diberikan otomatis.

## Pengujian

Delapan kasus baru teramati gagal sebelum implementasi. Paket regresi akses admin, pencarian TA, peminjaman biasa/sharedavailability, importer mingguan dan coverage: **59 lulus, 279 assertion** (22,94 detik). Reproduksi sumber asli lulus terpisah. Suite penuh: **1.284 lulus, 45 gagal, 2 dilewati**, 10.334 assertion, 317,18 detik. Nama seluruh 45 kegagalan sama persis dengan baseline125; tidak ada tambahan dan tidak ada klaim seluruh suite hijau. Sesudah label ordinary booking dibuat umum, paket peminjaman/admin kembali lulus: **21 tes, 104 assertion**, 16,63 detik. Tidak ada perubahan logika produk setelah suite penuh.

Hasil lengkap/log/JUnit, termasuk 45 nama baseline, disimpan privat pada private-import/127-test-summary.json dan private-import/dadu120-127-*. Review keamanan diperiksa pada controller dan service pembatalan; role tidak menggantikan permission per tindakan. Pengujian mempertahankan penolakan akses Create/Review yang tidak diberikan dan larangan pembatalan milik orang lain.

Pengujian database hanya dadu_testing. Tidak ada migration baru. Frontend JS/CSS tidak berubah; pengujian halaman memastikan view tetap dapat dirender. Log/JUnit dan output diagnosis lengkap disimpan privat pada private-import/127-* atau private-import/dadu120-127-*.

## Pemakaian dan rilis

Langkah admin serta data yang perlu dilengkapi: [panduan peminjaman/sumber semester](../panduan-pemakaian/peminjaman-dan-sumber-jadwal-ruangan.md). Ini tetap memerlukan penyelesaian klasifikasi dan alias pada VPS; pembaruan kode tidak membuat data yang belum terkonfirmasi menjadi lengkap.

Sesudah commit/push, update aplikasi memakai [panduan lokal/Docker/VPS](../panduan-pemakaian/update-lokal-docker-vps.md). Tidak perlu migrate:fresh/seed. Pada VPS1GB pertahankan override dan gunakan alur image yang sesuai. Sesi ini tidak melakukan deployment atau perubahan data VPS.

JSON kehadiran tetap ditunggu dan berbeda dari JSON jadwal ruangan yang sekarang sudah tersedia.

## Rescan dan penyalinan akhir

**9 file kode/config/tes** sudah sama SHA-256 di dadu dan dadu-test, menggunakan backup source private-import/127-source-before/. Manifest/rescan disimpan privat pada private-import/127-copy-manifest.json dan private-import/127-rescan-final.json.

- `app/Http/Controllers/RoomBookingController.php`
- `app/Services/RoomBookingTransitionService.php`
- `app/Services/SidangTaStaffScheduling.php`
- `app/Support/RoomBookingAccess.php`
- `config/sidebar.php`
- `resources/views/room-bookings/review.blade.php`
- `tests/Feature/RoomBookingAdminAccessTest.php`
- `tests/Feature/RoomBookingFeatureCompleteTest.php`
- `tests/Feature/RoomSchedulingDiagnosticsTest.php`

Sebanyak 753 file aplikasi/tes source diperiksa: 748 sama, 5 adaptasi lama tetap berbeda secara sengaja (dua policy dokumen, RolePermissionSeeder, CSS dan antrean admin). View kompatibilitas documents/all tetap khusus target. Tidak ada file source yang hilang dari target. Syntax sembilan berkas dan whitespace lulus di kedua repo.

Konfigurasi, environment, dependency, fixture privat dan index Git dipertahankan. Tidak membuat kembali docs/docker di source dadu; catatan hanya pada dadu-test. Tidak menjalankan fresh/seed/migration pada DB aplikasi maupun mengubah data/konfigurasi VPS. Frontend build tidak diulang karena JS/CSS tidak berubah.

Command salin ulang terbatas ada pada [panduan penyelarasan](../panduan-pemakaian/penyelarasan-file.md). Tindakan data yang masih perlu dilakukan di VPS tetap klasifikasi kelas dan pemetaan alias terkonfirmasi sebelum impor penuh. Tabel calon bukan instruksi otomatis atau pemetaan yang sudah disimpan.

Audit dokumentasi akhir: **1.191 tautan lokal dan 55 anchor diperiksa, nol rusak**. Seluruh 34 berkas fixture/data/config/ZIP tetap identik SHA-256; root docs hanya README dan tidak ada laporan SH/TXT/patch baru. Laporan mesin sesi127 hanya disimpan privat.
