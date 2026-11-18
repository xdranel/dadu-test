# 115 — Akun Dosen Pelaksana dan JSON simulasi VPS/Docker

## Lingkup dan hasil rescan sebelum pekerjaan

Permintaan: satu akun Dosen manual dengan role Pelaksana, dibuat hanya pada VPS dadu-test untuk menguji Aksi Khusus Sidang TA. Database development lokal tidak digunakan. Folder `docker/` dan dokumentasi Markdown hanya dikelola di repo `dadu-test`; folder source `dadu` hanya diberi berkas JSON simulasi.

Rescan VPS16 November2026: commit `daf8a99`,14 akun,1 pengguna Pelaksana,12 Student dan1 pengajuan Informatika. Permission TA pada role Pelaksana saat diperiksa hanya `sidang_ta_registration_view_program`. Tidak menjalankan seeder role/akun atau fresh. Pengguna Pelaksana lama dipertahankan.

Akses khusus mencocokkan email akun dengan `email_domain` Data Dosen, kemudian membatasi pengajuan ke prodi dosen. Pilihan sementara untuk akun ini: Informatika, selaras dengan Krisna dan tiga mahasiswa demo lain. Akun ini adalah simulasi manual, bukan akun SSO dan tidak mengubah kode integrasi SSO.

## Akun dan permission

- Email: `dosen.ta@dosen.test`; username: `dosen-ta-test`.
- Nama: Dosen Simulasi Sidang TA; role: Pelaksana.
- Record Data Dosen: kode `DTEST115`, prodi Informatika, Email Domain sama dengan email akun.
- Password disimpan privat di `private-import/115-lecturer-account.json` pada komputer lokal dan `/root/dadu-test-lecturer-account.json` pada VPS; jangan commit berkas ini.
- Aksi Khusus `sidang_ta_registration_view_program` **tetap diwarisi dari role Pelaksana**. Tidak diberikan langsung ke akun, sehingga perubahan checkbox Aksi Khusus pada Pengaturan Role tetap bisa diuji.
- Permission dasar `sidang_ta_registration_read` diberikan hanya ke akun baru; role global tidak diubah. Permission create/update/review Sidang TA tidak diberikan.
- Hak modul lain tetap mengikuti pengaturan role yang sudah ada. Pembatasan baca yang diuji di sini khusus Sidang TA.

Skrip operasional: `docker/scripts/create-test-lecturer.php`. Skrip hanya berjalan pada `APP_ENV=staging`, memerlukan salah satu flag `--check`/`--apply`, tidak mencetak password, dan memakai transaksi. Ia berhenti jika identitas bertabrakan, password akun existing berbeda, atau role Pelaksana masih memiliki aksi tulis/review TA. Menjalankan ulang dengan kredensial yang sama tidak menggandakan akun atau Data Dosen, serta tidak mereset password.

## JSON simulasi

Folder utama pada repo Docker: `private-import/simulasi-vps/` (di-ignore Git). Salinan pada source asli: `/home/xdranel/Code/Project/php-laravel/dadu/data-sinkronisasi-vps/`; hanya enam berkas JSON, tanpa Markdown, skrip, password, atau folder docker. Folder source dikecualikan dari Git lokal melalui `.git/info/exclude`; `.gitignore` tracked tidak diubah. Folder `docs/` dan `docker/` source tetap tidak dibuat kembali.

Lima berkas impor mewakili12 mahasiswa yang cocok dengan akun demo VPS, bukan14/15 akun User. Superadmin dan Dosen bukan Student. Tahun akademik2026/2027, term1/Ganjil; BPP2627/1. KRS95 mata kuliah termasuk tambahan TA sintetis untuk pengujian matriks.

| Urutan | Berkas | Pilihan Sinkronisasi |
| --- | --- | --- |
|1|75-01-mahasiswa-demo.json|Mahasiswa|
|2|75-02b-krs-demo-skenario-ta.json|KRS; format daftar lengkap resmi terdeteksi otomatis|
|3|75-03-profil-akademik-demo.json|Profil Akademik|
|4|75-04-bpp-demo.json|Profil Akademik|
|5|75-05-bahasa-demo.json|Profil Akademik|

`blackbox-accounts.json` adalah roster seeder11 akun, **bukan upload Sinkronisasi**. Disimpan agar server Docker pengganti dapat memakai roster yang sama. Kelima berkas upload tidak membuat akun login. Skor/status/tanggal sintetis tetap mengikuti fixture75/79; bukan fakta mahasiswa resmi.

Pastikan Pengaturan Periode Akademik memiliki2026/2027 Ganjil yang sesuai sebelum impor KRS. Daftar KRS ini merupakan snapshot lengkap mahasiswa/periode yang diwakili; impor ulang dapat mengganti daftar lama pada periode tersebut. Jangan impor ulang sekadar untuk menguji login Dosen apabila data demo sudah ada. Jadwal pendaftaran/revisi/pelaksanaan TA tetap dibuat lewat Kelola Periode/Pelaksanaan, terpisah dari periode akademik KRS.

JSON berasal dari generator committed `docker/scripts/prepare-demo-imports.php` dengan sumber blackbox/matriks dan NILAI BAHASA UPDATE7 November. Hanya hasil turunan enam JSON yang disalin; ekspor sumber mahasiswa lain tidak disalin ke folder simulasi. Contoh JSON kehadiran mahasiswa masih menunggu dan tidak dibuat-buat dari jadwal ruangan.

## Pemakaian kembali pada server Docker pengganti VPS

Gunakan repo `dadu-test` beserta `.env.docker` milik server tersebut; jangan menyalin folder docker/docs ke source development asli.

1. Siapkan aplikasi Docker staging dengan akun/seeder normal bila database memang baru (panduan75/113). Pada database yang sudah digunakan, jangan fresh, jangan seed ulang role.
2. Kirim roster ke `private-import/blackbox-accounts.json` hanya bila setup awal membutuhkannya. Roster boleh0644 agar setup www-data bisa membaca; berkas password akun Dosen harus0600.
3. Pastikan role Pelaksana mempunyai Aksi Khusus **Lihat Pengajuan (Prodi Dosen)** dan tidak mempunyai create/update/review Sidang TA. Skrip tidak mengubah pengaturan role secara global.
4. Kirim skrip operasional dan JSON kredensial privat ke server; contoh SSH berikut memakai root VPS. Ganti target SSH jika kembali ke server laptop Docker.

Dari laptop/repo Docker:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
scp docker/scripts/create-test-lecturer.php root@45.158.126.251:/opt/dadu-test/private-import/115-create-test-lecturer.php
scp private-import/115-lecturer-account.json root@45.158.126.251:/opt/dadu-test/private-import/115-lecturer-account.json
```

Pada server (sekali lagi: ini menyasar database staging pada server, bukan database development):

```bash
cd /opt/dadu-test
chmod 0600 private-import/115-lecturer-account.json
docker cp private-import/115-create-test-lecturer.php dadu-test-app-1:/tmp/dadu-create-test-lecturer.php
docker compose --env-file .env.docker exec -T --user www-data app \
  php /tmp/dadu-create-test-lecturer.php --check < private-import/115-lecturer-account.json
bash docker/scripts/backup.sh </dev/null
docker compose --env-file .env.docker exec -T --user www-data app \
  php /tmp/dadu-create-test-lecturer.php --apply < private-import/115-lecturer-account.json
```

STDIN diperlukan untuk JSON privat, sehingga command ini sengaja tidak memakai `--interactive=false`. Nama container contoh mengikuti Compose project `dadu-test`; sesuaikan bila project server berbeda. Tidak perlu rebuild image hanya untuk menjalankan skrip operasional ini.

5. Unggah JSON mahasiswa melalui browser dengan urutan tabel bila ingin menyiapkan dataset baru; pada aplikasi yang sudah berisi data, periksa kebutuhan sebelum impor ulang.
6. Login akun Dosen → Review Sidang TA. Dropdown prodi disembunyikan; daftar/count hanya dari Informatika. Tinjau/preview berkas dan catatan tetap terlihat; input catatan dan tombol revisi/tolak/setujui tidak tersedia. Ekspor dan Kelola Periode bukan bagian akses khusus ini.
7. Bila ingin mencoba prodi lain, edit **Data Akademik → Dosen → Dosen Simulasi Sidang TA → Prodi** lewat admin. Scope dibaca ulang dari Data Dosen; tidak perlu membuat ulang akun. Skrip pembuatan akan berhenti bila konfigurasi akun/prodi lama berbeda, sehingga tidak menimpa perubahan manual.

## Verifikasi akhir

Hasil aktual pada VPS16 November2026:

- Preflight `--check` lulus tanpa membuat data. Backup sebelum penambahan akun: `/opt/dadu-test/backups/20261116T163113Z.*`.
- Eksekusi pertama membuat tepat1 User dan1 Data Dosen. Eksekusi kedua menghasilkan `user_created=false`/`lecturer_created=false`; tidak menggandakan data atau mereset password.
- Total User14→15 (akun tambahan yang dibuat pengguna sebelumnya dipertahankan), Student tetap12, role global tidak berubah. Aksi Khusus diwarisi dari role; direct permission akun hanya `sidang_ta_registration_read`.
- Login Dosen melalui Cloudflare HTTPS berhasil; daftar pengajuan,1 halaman tinjau Informatika, dan preview PDF merespons200. Dropdown prodi, tombol ekspor, form catatan review, serta aksi revisi/tolak/setujui tidak muncul.
- POST review kosong dengan CSRF valid ditolak403; tidak mengirim keputusan valid yang dapat memodifikasi pengajuan. Halaman Kelola Periode dan ekspor juga403.
- Snapshot raw pengajuan, dokumen, dan riwayat sebelum/sesudah pengujian identik. Audit akun dan permission sebelum/sesudah HTTP juga identik.
- Enam JSON salinan pada source dan repo Docker identik; SHA-256 VPS sesuai. Semua file data/kredensial privat memakai0600; folder source hanya berisiJSON. Daftar Student/NIM pada lima payload sesuai12 akun mahasiswa VPS, total95 KRS pada Ganjil2026/2027.
- `php -l` lulus. Guard CLI tanpa flag/dua flag/identitas salah lulus tanpa boot Laravel atau menyentuh database. Tidak menjalankan suite feature yang membuat fixture akun pada database lokal; tidak mengubah logic aplikasi untuk fitur akses khusus yang sudah ada.

Laporan tanpa password: [115-verifikasi-akun-dosen.json](115-verifikasi-akun-dosen.json). Tidak membuat akun pada database lokal dan tidak mengimpor ulang data akademik VPS pada sesi ini.


## File yang ditambahkan di repo Docker

- `docker/scripts/create-test-lecturer.php`: utilitas staging yang dijalankan eksplisit; tidak terhubung ke seeder/entrypoint/scheduler.
- `docs/115-akun-dosen-pelaksana-dan-json-simulasi-vps.md` dan `docs/115-verifikasi-akun-dosen.json`: catatan dan bukti pemakaian ulang.
- Data dan password disimpan hanya pada `private-import/` yang di-ignore, salinan JSON source, dan folder privat VPS. Tidak menambahkan docker/docs pada source development asli.

Belum ada commit/push dari sesi ini. Source aplikasi yang melayani VPS tetap `daf8a99`; utilitas disalin melalui folder privat ke container yang berjalan, sehingga tidak membutuhkan rebuild atau mengubah working tree source VPS.
