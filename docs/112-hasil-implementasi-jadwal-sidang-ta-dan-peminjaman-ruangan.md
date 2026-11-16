# 112 — Implementasi jadwal Sidang TA dan Peminjaman Ruangan

Status: **T1–T9 selesai; kode telah disalin selektif dan diverifikasi pada dadu dan dadu-test lokal**. Belum deploy, belum menjalankan migrasi database aplikasi, dan tidak melakukan commit/push.

Rujukan: [tanya jawab109](109-rescan-dan-catatan-awal-jadwal-pelaksanaan-sidang-ta.md), [rancangan110](110-rancangan-integrasi-jadwal-sidang-ta-dan-peminjaman-ruangan.md), [rencana111](111-rencana-implementasi-jadwal-sidang-ta-dan-peminjaman-ruangan.md). Jawaban pengguna dan contoh kasus tetap tersimpan pada109;112 mencatat hasil kode dan pemakaiannya.

## 1. Hasil fitur

- Mahasiswa dengan pengajuan disetujui mendapat kartu Pelaksanaan Sidang TA di sebelah Draft tersimpan. Konteks pemesanan baru menggunakan pengajuan disetujui terbaru menurut waktu pengiriman lalu ID. Booking/konteks aktif historis tetap terlihat; periode lama tidak dipakai sebagai pengganti ketika periode terbaru belum diatur.
- Pada halaman terpisah mahasiswa hanya memilih tanggal. Sistem membandingkan seluruh ruang kelas aktif dan mengambil jam paling awal, kemudian kode ruang dan ID sebagai urutan pengikat. Laboratorium dan ruang yang jenisnya belum dipastikan tidak menjadi kandidat TA. Kapasitas minimum dan bentrok dosen belum dijadikan aturan baru.
- Pencarian menampilkan ID Pengajuan TA, NIM, nama, tanggal dan kode/nama pembimbing/penguji yang tersedia. Kode sumber tetap terlihat walaupun nama master belum tersedia. Data kosong ditandai Belum tersedia; profil dan snapshot akademik tidak ditulis ulang oleh booking.
- Pratinjau belum menahan ruang. Token berlaku15 menit; konfirmasi memeriksa ulang pemilik, pengajuan terbaru, persetujuan, versi konteks/pengaturan/sumber, ruang dan kandidat paling awal. Perubahan membuat pengguna mencari ulang; tidak diam-diam memindahkan hasil. Booking hasil konfirmasi langsung disahkan.
- Mahasiswa tidak mempunyai fitur ubah/batal sesudah konfirmasi. Perubahan melalui SSC/Divisi Akademik yang mempunyai hak Review TA. Petugas dapat pindah, batal, dan menetapkan pengganti setelah pembatalan; target gagal mempertahankan jadwal lama. Riwayat lama tidak dihapus.
- Kalender, detail, pencarian, pembuatan, persetujuan dan perpindahan peminjaman menggunakan ketersediaan yang sama: pola mingguan semester + jadwal tanggalan lama + booking internal pending/approved. Data semester belum lengkap berarti belum tersedia, bukan semua ruang kosong.
- Admin dapat menandai Dijadwalkan melalui dosen **sebelum dosen memesan alternatif**. Tab pencarian mahasiswa yang sudah terbuka menjadi tidak berlaku. Petugas kemudian menautkan booking secara eksplisit; tidak mencari keterkaitan melalui teks keperluan/NIM. Daftar pemilihan memuat100 booking terbaru yang belum tertaut, lalu service memeriksa kembali semua ketentuannya.
- Booking dosen pending tetap pending; pemilik dosen tidak diganti dan occurrence tidak digandakan. Persetujuan/pembatalan/penolakan berikutnya memperbarui konteks TA dalam transaksi yang sama. Mode tetap melalui dosen; pembatalan tidak membuka pencarian mahasiswa diam-diam. Penautan/perubahan booking ordinary juga memerlukan hak review peminjaman yang sudah berlaku.
- Pembatalan persetujuan TA sebelum pelaksanaan ikut membatalkan booking tertaut dan mengembalikan slot. Modal menampilkan booking, ruang/tanggal/jam dan konsekuensinya. Konteks mode dosen tanpa booking juga dilepas agar NIM tidak tertahan. Dokumen, status warna, catatan dan snapshot pendaftaran dipertahankan. Booking disahkan yang sudah dimulai tidak bisa dibatalkan melalui endpoint peminjaman lama untuk melewati pengaman SSC.

Pendaftaran dan Verifikasi Berkas tetap memakai jadwal masing-masing. Mahasiswa yang sudah disetujui dapat memesan sebelum hari pelaksanaan meskipun pendaftaran/verifikasi sudah tutup. Tanggal pelaksanaan tidak dimasukkan ke wizard tahap pendaftaran.

## 2. Pengaturan awal dan urutan impor

Lakukan setelah migration biasa berhasil pada lingkungan yang hendak diuji. **Jangan menggunakan migrate:fresh/seed untuk update aplikasi yang sudah dipakai.**

1. Data Akademik → Dosen: impor `Master Pegawai.xlsx`. Header `Kode Dosen Baru`, `Email Domain Telkom University`, `STATUS` diterima, begitu pula header lama. Record yang cocok lewat NIDN/kode mendapat pembaruan identitas eksplisit. Nilai kosong tidak menghapus data lengkap; kecocokan ambigu tidak digabung berdasarkan nama.
2. Data Akademik → Ruangan: impor `DataRuangan (2).xlsx`. Status Tidak Dapat Dipakai tetap dihormati. Impor ulang Excel lama tidak mereset klasifikasi kelas yang sudah diperiksa.
3. Edit Ruangan: pastikan Klasifikasi TA (Ruang kelas/Bukan kelas/Belum dipastikan). Masukkan alias label iGracias untuk ruang fisik yang sama, satu per baris. Alias bersifat tambahan; tidak diam-diam dipindah ke ruang lain.
4. Data Akademik → KRS Mahasiswa → Pengaturan Periode Akademik: pastikan semester sumber terdaftar dengan tahun, term dan batas tanggal yang benar. Contoh `2627/1` adalah2026/2027 Ganjil. Pola ruang mengikuti semester sumber meskipun acuan KRS aktif kemudian berganti. Jangan mengganti identitas periode yang sudah dipakai pola/booking menjadi semester lain.
5. IT Panel → Sinkronisasi → Jadwal Ruangan iGracias: impor **snapshot semester lengkap** dari `rekap_igracias_2026-11-13 JADWAL PERKULIAHAN.json`. Metadata/total/menu/filter harus sesuai, tanpa pemangkasan prodi/ruang/bentrok. `TETAP` diulang mingguan dalam tanggal semester; misalnya Senin9 November memblokir Senin23 November. Snapshot baru mengganti pola semester yang sama, mempertahankan semester lain dan jadwal legacy. Bentrok dengan booking pending/approved, data parsial, atau alias belum pasti membuat impor gagal atomik dan data lama tetap dipakai.
6. Review Sidang TA → Kelola Pelaksanaan: pilih periode TA dan semester sumber, tanggal pelaksanaan (misalnya23–27 November), hari, jam, istirahat dan durasi. Pengaturan tersendiri per periode TA; tidak mengubah tanggal pendaftaran/verifikasi. Perubahan yang memotong booking aktif ditolak. Mengubah durasi default tidak memperpendek booking lama secara diam-diam.
7. Melalui Pengaturan Role, berikan hak yang memang dibutuhkan. Tidak ada reset hak akses/seeder selama update ini. Baca pengaturan memakai hak baca periode; simpan memakai hak ubah periode; koreksi jadwal TA memakai Review TA. Dosen yang hanya mempunyai aksi Lihat Pengajuan (Prodi Dosen) tetap hanya membaca.
8. Pastikan profil mahasiswa dan data akademik TA sudah tersinkron seperti alur sebelumnya. Persetujuan pendaftaran TA tetap keputusan petugas; impor jadwal ruang tidak menyetujui pendaftaran TA.

**Data yang masih perlu dipastikan:**60 ruang pada master memakai kode seperti `1.02`, sedangkan sumber mempunyai69 label seperti `KTT1.02`, `B-CLS-105`, dan laboratorium. Tidak ada kecocokan langsung setelah normalisasi sederhana. Perlu pemetaan resmi label sumber → ruang fisik master, serta pelengkapan ruang yang belum ada. Sistem tidak menebak prefix atau membuat ruang duplikat untuk snapshot mingguan. Tidak perlu mengubah format JSON; lengkapi master/alias terlebih dahulu. Impor akan tetap ditolak jika ada label belum terpetakan, termasuk label ruang nonkelas, agar sumber lengkap tidak menyembunyikan bentrok.

Fixture kecil [111-room-schedule-patterns-demo.json](fixtures/sidang-ta/111-room-schedule-patterns-demo.json) memakai ruang `DEMO-A` dan kode dosen dummy. Gunakan hanya pada lingkungan testing yang master ruangnya cocok; fixture bukan pengganti snapshot asli production. Format legacy `aaData` tetap jadwal tanggal tertentu dan tidak menandai semester sebagai sumber lengkap. Jalur legacy juga menolak jadwal baru yang bertabrakan dengan booking aktif.

## 3. Contoh pemakaian

### Mahasiswa

1. Setelah pengajuan disetujui, buka Pendaftaran Sidang TA → Cari jadwal sidang.
2. Pilih23 November lalu Cari jadwal. Jika kelasA baru kosong09.00 sedangkan kelasB kosong08.00, sistem menawarkanB08.00–10.00. Mahasiswa tidak memilih ruang/jam.
3. Periksa identitas dan jadwal. Ketersediaan ruang **belum memastikan kehadiran pembimbing/penguji**; pastikan kesediaan dosen terlebih dahulu.
4. Konfirmasi jadwal. Booking disahkan dan ditampilkan dengan ID booking. Apabila ada pemesan/perubahan sumber mendahului konfirmasi, cari ulang.
5. Untuk pindah atau batal, hubungi SSC. Tidak ada tombol perubahan mahasiswa atau endpoint yang memberinya izin tersebut.

### Alternatif melalui dosen dan koreksi petugas

1. Mahasiswa/dosen menyampaikan alternatif kepada SSC.
2. Petugas menandai Dijadwalkan melalui dosen sebelum peminjaman alternatif dilakukan. Jika sudah ada booking otomatis aktif, selesaikan/pindahkan/batalkan dahulu secara eksplisit; jangan menciptakan dua reservasi aktif.
3. Dosen memesan melalui Peminjaman Ruangan seperti alur biasa. Pemesanan biasa tetap boleh memakai jenis ruang yang diizinkan fitur tersebut dan jam06.30–21.30; kelas/hari/jam/durasi yang lebih ketat diperiksa ketika ditautkan sebagai sidang TA.
4. Petugas membuka Kelola jadwal pelaksanaan pada tinjauan mahasiswa lalu memilih booking dosen yang tepat. Versi awal menerima sekali pakai, satu occurrence, pending/approved, ruang kelas dan rentang pelaksanaan TA yang sesuai, belum tertaut pengajuan lain.
5. Jika pending, persetujuan peminjaman biasa tetap dibutuhkan. Dosen masih dapat membatalkan pending miliknya; konteks TA mengikuti hasil itu. Mahasiswa tetap melalui SSC untuk penjadwalan pengganti.
6. Koreksi SSC mewajibkan alasan dan memeriksa versi terbaru. Perpindahan gagal tidak menghapus jadwal lama; perpindahan berhasil dan pembatalan dicatat. Petugas juga dapat membatalkan konteks mode dosen yang belum mendapat booking agar tidak menahan NIM terus-menerus.

## 4. Keputusan sementara dan konfirmasi divisi

Tanya jawab lengkap dan contoh ada pada109/T01–T13. Hal berikut tetap perlu konfirmasi divisi, bukan dianggap kebijakan resmi karena sudah ada kode:

| Hal | Yang dipakai sekarang | Contoh yang perlu dikonfirmasi |
| --- | --- | --- |
| Durasi | Default120 menit, dapat diatur petugas per periode | Apakah selalu2 jam, atau1 jam dengan jeda persiapan? |
| Hari/jam/istirahat | Senin–Jumat08.00–16.00 WIB, istirahat12.00–13.00, dapat diatur | Apakah Jumat atau hari libur membutuhkan pengecualian tambahan? |
| Jenis ruang | Hanya kelas aktif yang telah dipastikan | Apakah semua kelas boleh, atau ada daftar/kategori resmi? |
| Kesediaan dosen | Belum memeriksa bentrok mengajar/kehadiran | Ruang08.00 kosong, tetapi pembimbing sedang mengajar: versi awal belum menolaknya |
| Pembatalan approval | Booking tertaut sebelum pelaksanaan ikut batal, riwayat tetap | Approval dibatalkan19 Nov → booking23 Nov08.00 dilepas; apakah divisi menyetujui aturan ini secara resmi? |
| Satu konteks aktif per NIM | Penjagaan teknis terhadap booking ganda | Beberapa approval historis tidak boleh menghasilkan dua booking aktif |
| Booking dosen yang ditautkan | Sekali pakai, satu tanggal, sesuai kelas/jendela/durasi TA | Booking berulang atau laboratorium ditolak walaupun sah sebagai peminjaman biasa |
| ID pada informasi | ID Pengajuan TA, kemudian ID booking setelah tersimpan | Pastikan ini ID yang dimaksud dalam laporan divisi |

**Masih menunggu contoh JSON kehadiran mahasiswa.** Berkas jadwal ruangan ini bukan pengganti data kehadiran. Penguji pada banyak profil juga belum terisi; nilai yang belum tersedia tidak dibuat-buat dan tidak menjadi blokir booking tambahan. Production tetap perlu dikoordinasikan dengan lead dev, termasuk konfirmasi PHP-FPM/izin upload yang sebelumnya ditunda.

## 5. File dan skema

Daftar aktual seluruh file ada pada [allowlist salin](112-daftar-file-salin-sidang-ta-room.txt); hash dan kondisi sebelum penyalinan ada pada [manifest](112-manifest-file-salin.json). Jangan menyalin seluruh branch atau direktori aplikasi.

- Tabel baru: `room_schedule_sources`, `room_weekly_patterns`, `room_schedule_aliases`, `sidang_ta_execution_settings`, `sidang_ta_schedules`.
- Kolom tambahan: klasifikasi kelas nullable pada rooms; asal booking dan FK pengajuan TA nullable pada room_booking_requests; data sebelum/sesudah pada history.
- Empat migration fitur baru15 November. Repo Docker juga mendapat dua migration dasar modul teman11/14 November yang sebelumnya belum ada. Migration yang telah tercatat tidak diulang saat migrate biasa.
- Controller/service/model/view/test baru dan perubahan integrasi terbatas pada availability, booking, Sinkronisasi, pengaturan pelaksanaan, klasifikasi/alias dan identitas dosen. Tidak membawa User/SSO, antrean, Yudisium/stash atau seeder wholesale.
- Tiga berkas mempunyai varian penggabungan untuk source asli: `routes/web.php`, `config/sidebar.php`, `config/permission_modules.php`. Perubahan source lain seperti akses dokumen, antrean dan permission Yudisium dipertahankan. Duplikasi key room hasil merge dibuang. Karena itu **jangan menimpa ketiganya memakai salinan seluruh branch lain**.

## 6. Bukti pengujian dan rescan

Worktree terisolasi: `/home/xdranel/.codex/worktrees/09ea/dadu-test`, basis Docker `57d06d7aa87fbb87b1dbb05a0a488ef2c2321dc8`. Native tool membuat worktree dari repo Docker; dependency room source dibawa selektif lebih awal agar fondasi dapat diuji. Tidak menggunakan worktree lama yang berisi pekerjaan lain.

| Paket | Bukti yang sudah dijalankan |
| --- | --- |
| Fondasi SQLite |4 tes/17 assertions; upgrade pending/approved dan FK tetap aktif |
| Ketersediaan |6 tes/21 assertions; union interval, pola semester dan unknown coverage |
| Impor mingguan awal |16 tes/47 assertions; termasuk endpoint Sinkronisasi dan validasi detik |
| Task2/3 MySQL |22 tes/68 assertions |
| Pengaturan + regresi periode MySQL |17 tes/58 assertions |
| Pencarian/konfirmasi awal |11 tes/27 assertions |
| Lifecycle + regresi107/108 MySQL |47 tes/312 assertions |
| UI/identitas/akses prodi/jadwal |49 tes/253 assertions |
| Konkurensi nyata MySQL |7 skenario/39 assertions, proses/koneksi terpisah dan fixture committed |
| Upgrade native MySQL |1 tes/12 assertions, pending/approved/history tetap utuh |
| Fix review terarah |35 tes/134 assertions sebelum tambahan boundary255 |
| Sintaks/Pint/Blade/build |65 berkas PHP diperiksa; Pint, kompilasi view dan Vite build lulus di worktree |

Uji konkurensi pertama benar-benar mereproduksi **dua booking bentrok yang sama-sama committed**, serta impor dan konfirmasi yang sama-sama berhasil akibat snapshot repeatable-read terlalu awal. Identitas pengajuan kini dibaca sebelum BEGIN; pembacaan locking mendahului snapshot konsisten. Tujuh skenario kemudian lulus. Ini tidak disimpulkan dari SQLite.

Tes query juga mereproduksi66 query pola untuk14 ruang/lima hari; jalur pencarian sekarang mengambil rentang secara batch. Impor legacy baru diperiksa terhadap booking aktif. Batas keperluan peminjaman disamakan dengan varchar255 yang sudah ada, termasuk boundary input.

SQLite memiliki kegagalan baseline foreign-key mismatch student_courses→courses pada sebagian tes lama. Tidak menonaktifkan FK agar tampak hijau. Verifikasi lengkap terkait memakai MySQL8.4.13 port3307 pada **database dadu_testing**, bukan database aplikasi. Tes integrasi committed melakukan fresh/wipe hanya pada DB yang namanya diperiksa eksplisit. Down migration antrean lama memiliki masalah drop index FK; tidak diubah sebagai bagian fitur ini.

Suite lengkap awal:1072 lulus,50 gagal,2 dilewati. Snapshot commit awal diuji terpisah dan mereproduksi49 kegagalan lama yang sama; satu tambahan adalah fixture status baru yang telah dikoreksi. Nama lengkap49 kasus dan cakupan perbandingan ada pada [laporan baseline](112-kegagalan-suite-baseline.md). **Suite lengkap akhir:1080 lulus,49 gagal baseline yang sama persis,2 dilewati,8925 assertions. Perbandingan nama kasus JUnit menunjukkan nol kegagalan tambahan.** Empat belas berkas tes fitur/pendukung yang ditambahkan atau diperluas mencakup91 tes lulus,2 upgrade transaksional yang sengaja dilewati,336 assertions; termasuk seluruh7 skenario konkurensi dan upgrade MySQL native. Seluruh suite tetap berstatus gagal karena49 kasus lama tersebut, bukan dinyatakan hijau. Dua kasus upgrade MySQL di suite Pest dilewati secara eksplisit karena transaksi global tidak cocok untuk DDL; penggantinya dijalankan pada tes upgrade native di atas.

Review kode independen menemukan empat masalah utama: konteks dosen menahan NIM setelah withdrawal, pemilihan approval historis, bypass batas mulai melalui endpoint lama, dan batas input versus MySQL. Semuanya direproduksi RED lalu diperbaiki. Dua kekurangan pesan dikategorikan berdampak pada keputusan operator/mahasiswa dan ikut diperbaiki: dampak pelepasan booking dan batas pemeriksaan dosen. Reviewer tidak menjalankan DB tests; bukti runtime tetap dari pengujian agen utama.

Rescan sampai sebelum port memastikan620 hash kode/config/test root Docker dan637 root source tetap seperti awal. Perubahan awal source `.gitignore`, `package-lock.json`, `phpunit.xml` dipertahankan. Hasil rescan sesudah penyalinan dan lokasi backup dicatat otomatis pada `docs/112-rescan-dan-salin.json`. Laporan memeriksa hash semua file yang disalin, file baseline di luar allowlist, HEAD, serta konfigurasi/lockfile yang harus tetap utuh.

Verifikasi tambahan terhadap varian `/dadu` dilakukan pada snapshot source terisolasi setelah port, dengan database MySQL testing yang sama: **58 tes integrasi lulus,1 tes seeder lama gagal,230 assertions**. Kasus `fresh seed gives Pelaksana scoped viewing without TA review or period management` pada `SidangTaProgramReadAccessTest.php` kemudian diuji pada commit source awal `1c83b32d5f7fd9547666ecf300e0cba6f113e208` dan mereproduksi kegagalan yang sama. Seeder source lama belum memuat default akses prodi seperti varian Docker. Seeder tidak disalin/direset sebagai bagian fitur ini; hak akses live yang telah diatur pengguna tetap dipertahankan. Ini perbedaan baseline source, bukan klaim bahwa seluruh suite source lulus. Jangan menjalankan ulang seeder role untuk update aplikasi ini.

## 7. Command salin lokal yang aman

Paket memuat varian Docker serta penggabungan source asli. Salinan dilakukan setelah verifikasi; hasil nyata dan backup dicatat pada laporan `112-rescan-dan-salin.json`. Untuk mengulang penyalinan **varian Docker** dari worktree hasil kerja ke folder dadu-test lokal, gunakan:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
bash docker/scripts/copy-ta-room-feature.sh \
  /home/xdranel/.codex/worktrees/09ea/dadu-test \
  /home/xdranel/Code/Project/secret/dadu-test
git diff --check
git status --short
```

Script memeriksa allowlist, symlink/path, hash sumber, dan hash tujuan sebelum mulai. Tujuan yang berbeda dari baseline atau hasil yang sudah disalin membuat proses berhenti agar perubahan lain tidak tertimpa. File lama disimpan dalam folder backup `/tmp/dadu112-copy.*`; lokasi dicetak di hasil. File baru dicatat tersendiri. File disalin dengan mtime baru agar Blade tidak memakai compiled view lama. Tidak memakai delete/rsync-delete dan tidak menyentuh `.env`, storage, vendor, node_modules, Git/index atau lockfile.

Command ini **bukan untuk menyalin varian Docker ke source tugasAkhir**, karena tiga file konfigurasi/rute mempunyai penggabungan tersendiri. Source asli digabung berdasarkan tiga versi dan diverifikasi terpisah. Tidak perlu menyalin semua isi `/dadu` ke `/dadu-test`.

## 8. Menjalankan pada lokal dan update Docker

### Lokal development asli

Setelah file hasil integrasi tersedia dan koneksi `.env` telah dipastikan menunjuk database development yang benar:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan migrate:status
php artisan migrate
php artisan view:clear
npm run build
```

Migrasi di atas adalah langkah pengguna untuk mengaktifkan skema fitur pada database development; belum dijalankan agen pada database aplikasi. Tidak memakai fresh/seed. Gunakan hak akses yang sudah ada; menu Role dapat memastikan permission modul baru tersedia tanpa mereset role. Jika locale menggunakan config cache/proses lama, ikuti pengelolaan cache/proses yang memang digunakan di lingkungan itu.

Tes dapat dijalankan memakai konfigurasi MySQL testing yang sudah disiapkan pada phpunit.xml development. Pastikan database **dadu_testing**, bukan database aplikasi:

```bash
php artisan test tests/Unit/RoomTimeWindowsTest.php tests/Unit/WeeklyRoomScheduleNormalizerTest.php \
  tests/Feature/RoomAvailabilityServiceTest.php tests/Feature/WeeklyRoomScheduleImportTest.php \
  tests/Feature/RoomBookingSharedAvailabilityTest.php tests/Feature/SidangTaExecutionSettingsTest.php \
  tests/Feature/SidangTaRoomBookingTest.php tests/Feature/SidangTaLecturerBookingLinkTest.php \
  tests/Feature/SidangTaBookingLifecycleTest.php tests/Feature/SidangTaBookingUiTest.php \
  tests/Feature/LecturerBookingIdentityImportTest.php --compact
```

Untuk bukti konkurensi/upgrade gunakan MySQL testing eksplisit. Test tersebut sengaja dilewati bila MySQL dadu_testing/pcntl tidak tersedia; hasil skipped bukan bukti locking lulus.

### Docker dadu-test di server

Sesudah pengguna commit, push dan mengintegrasikan kode yang ditinjau ke main repo Docker, gunakan [panduan78 bagian4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker) dan panduan64 yang ada pada repo Docker. Build app/web, backup, migrate biasa dari image baru, lalu recreate runtime; restart container saja tidak mengganti kode yang dibangun di image. Ikuti pengaman working tree bersih dan maintenance/up yang sudah ada.

Sesudah update, pemeriksaan singkat:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app php artisan migrate:status
docker compose --env-file .env.docker ps
curl --fail --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
```

Kemudian lakukan urutan master/alias → periode akademik → impor jadwal → Kelola Pelaksanaan pada bagian2. Periksa satu mahasiswa approved, dosen pending, dan koreksi SSC. Jangan menjalankan suite destructive testing pada database container aplikasi. Production tidak diubah; lead dev menentukan prosedur deployment sesuai lingkungan yang dikonfirmasi.

## 9. Keputusan teknis saat eksekusi

- Native worktree tersedia dari repo Docker. Dependency room source dibawa lebih awal secara selektif agar fondasi dapat diuji. Konsekuensinya dependensi harus diaudit; bukti endpoint dan regresinya disertakan.
- Tidak membuat commit otomatis; ledger dan working diff dipertahankan sampai pengguna commit. Review mencakup untracked files, bukan rentang commit yang kosong.
- Label ruang tidak ditebak. Alias fisik wajib dipastikan operator; akibatnya impor asli belum dapat dinyatakan siap hanya dari nama label yang ada.
- Kebijakan durasi/hari/jam dan pemeriksaan dosen masih sementara seperti bagian4. Jika berbeda dari keputusan divisi, ubah pengaturan dan aturan yang telah dikonfirmasi sebelum dipakai sebagai kebijakan resmi.
- Tes committed memakai fresh/wipe pada DB yang diperiksa bernama dadu_testing; down migration antrean lama tidak dijadikan jalan cleanup dan tidak diubah. Pengaman ini tidak memberi izin fresh/seed pada DB aplikasi.
- Reviewer tidak memutuskan pemetaan production, kebijakan resmi, hasil deployment atau atribusi baseline. Agen utama memverifikasi tiga versi source/hash, mempertahankan varian konfigurasi, dan membandingkan49 kegagalan dengan commit awal. Risiko operasional tetap membutuhkan pemeriksaan master dan koordinasi divisi/lead dev.
- Dua pesan konfirmasi dinaikkan prioritasnya karena berdampak pada keputusan: pembatalan booking dan kesediaan dosen. Keduanya ikut diperbaiki dan diuji. Tidak ada temuan review material yang sengaja ditunda;49 kegagalan baseline tetap dilaporkan di luar lingkup fitur.


Penyalinan nyata16 November2026:91 path diproses dan hash kedua varian diverifikasi. Backup source dan backup Docker dicatat pada laporan rescan. Script salin diuji dua kali pada folder percobaan dan terbukti menolak perubahan tujuan tanpa menimpanya. HEAD, stash, konfigurasi lingkungan dan tiga perubahan lokal awal source tetap utuh.
