# 110 — Rancangan integrasi jadwal Sidang TA dan Peminjaman Ruangan

Tanggal: 15 November 2026, Asia/Jakarta.

Status: **rancangan untuk ditinjau; belum implementasi dan belum disetujui seluruh rinciannya**. Pendekatan pola mingguan sudah dipilih pengguna pada T12. Dokumen ini menjelaskan bentuk fitur dan pilihan teknis yang diusulkan; rencana implementasi rinci baru disusun setelah tinjauan rancangan.

Sumber kebutuhan, alur pemakaian, seluruh tanya jawab, contoh, kekurangan data, serta daftar konfirmasi divisi: [dokumen109](109-rescan-dan-catatan-awal-jadwal-pelaksanaan-sidang-ta.md). Alur mahasiswa/admin dan pertanyaan tetap dipelihara di dokumen109 sesuai permintaan pengguna. Dokumen hasil implementasi akan dibuat terpisah dan ditautkan setelah tahap itu dimulai.

Rincian tugas implementasi, interface dan pengujian: [rencana111](111-rencana-implementasi-jadwal-sidang-ta-dan-peminjaman-ruangan.md). T13 sudah menjawab temuan penautan alternatif dosen; rincian teknis di bawah tetap diberi label usulan agar tidak dianggap kutipan keputusan divisi.

## 1. Tujuan dan batas pekerjaan

Mahasiswa dengan pendaftaran Sidang TA Disetujui memilih tanggal, melihat satu hasil jam/ruang paling awal, lalu mengonfirmasi peminjaman yang langsung disahkan. Pemakaian ruang masuk ke sumber peminjaman bersama agar tidak bentrok dengan perkuliahan maupun peminjaman internal.

Perubahan pada fitur teman hanya mencakup kebutuhan integrasi: pola mingguan, pemeriksaan ketersediaan bersama, identitas booking TA, serta koreksi/pembatalan terkait. Alur dosen memesan ruang dan membatalkan pengajuan pending dipertahankan. Pembatalan approved oleh admin sudah ada dan akan digunakan.

Tidak mengubah SSO, pemetaan unit/role, Yudisium yang masih distash, antrean, format ekspor TA, atau keputusan akademik mahasiswa. Tidak mengimpor data, menjalankan migrasi, mereset seeder/permission, melakukan deployment, atau menyalin seluruh folder pada tahap rancangan ini.

## 2. Keputusan pengguna yang menjadi dasar

| Pokok | Keputusan | Rujukan109 |
| --- | --- | --- |
| Hak memesan | Pengajuan TA harus Disetujui | Kebutuhan awal |
| Pilihan mahasiswa | Tanggal saja; jam dan ruang otomatis | Kebutuhan awal |
| Persetujuan booking | Langsung disahkan sesudah konfirmasi mahasiswa | T01, T08 |
| Pratinjau | Cari Jadwal menampilkan hasil dahulu; belum menahan ruang | T08 |
| Pemeriksaan dosen | Versi awal memeriksa ruang saja | T03 |
| Tanggal pelaksanaan | Rentang mulai–selesai per periode TA, misalnya23–27 November | T05 |
| Hari/jam/durasi | Sementara Senin–Jumat08.00–16.00, istirahat12.00–13.00,120 menit; dapat disesuaikan petugas | T02, T06, T07 |
| Jenis ruang | Ruang kelas saja, tersedia dan tidak bentrok; konfirmasi resmi divisi masih diperlukan | T09 |
| Pengulangan | Pola mingguan sepanjang semester yang sesuai | T04, T12 |
| Alternatif dosen | Jika pratinjau tidak cocok, dosen boleh memakai peminjaman biasa | T08 |
| Informasi | ID, NIM, nama, tanggal sidang, kode pembimbing/penguji pada pencarian/konfirmasi | T08 |
| Sesudah booking | Mahasiswa menghubungi SSC; Divisi Akademik yang berwenang mengubah/membatalkan | T11 |
| Pembatalan persetujuan | Booking TA terkait ikut batal sebelum pelaksanaan; riwayat tetap, untuk konfirmasi divisi | T10 |
| Alternatif tertaut | Admin menandai pengajuan dan menautkan peminjaman dosen; jalur otomatis dihentikan | T13 |

Persetujuan aturan sementara tidak disebut sebagai aturan kampus resmi. Pertanyaan divisi pada109 tetap berlaku.

## 3. Pendekatan yang dipilih dan alternatif

**Dipilih pengguna:** simpan pola mingguan per semester, lalu hitung pemakaiannya untuk tanggal/rentang yang sedang diperiksa. Sidang TA dan Peminjaman Ruangan memakai satu komponen pemeriksaan ketersediaan.

**Alternatif yang tidak dipilih:** membuat record untuk setiap tanggal sampai akhir semester saat impor. Alternatif ini lebih mudah mengikuti query tanggalan lama, tetapi menggandakan jadwal dan memerlukan pembaruan hasil pembentukannya ketika pola/batas semester berubah.

Konsekuensi pilihan: seluruh pembacaan yang relevan pada fitur peminjaman harus diperiksa, bukan hanya endpoint TA. Kalender bulanan, detail harian, pencarian, pembuatan, persetujuan, dan pemindahan booking tidak boleh mempunyai cara menghitung bentrok yang berbeda. Perlu pengujian regresi alur dosen.

## 4. Bentuk tampilan dan alur

### Mahasiswa

- Halaman Pendaftaran Sidang TA menempatkan kartu jadwal di samping Draft tersimpan untuk mahasiswa yang memiliki pengajuan approved. Pada layar kecil, kartu tersusun vertikal. Jadwal tidak dimasukkan ke wizard tahap pendaftaran.
- Kartu memperlihatkan periode pengajuan dan status penjadwalan. Bila pengaturan/sumber jadwal belum lengkap, tampilkan alasannya dan petunjuk menghubungi petugas; jangan menawarkan ruang seolah-olah semua tanggal kosong.
- Penjadwalan pengajuan approved tetap tersedia setelah pendaftaran/Verifikasi Berkas ditutup apabila sumber/pengaturan pelaksanaan sudah siap. Mahasiswa boleh memesan sebelum tanggal pelaksanaan tiba; yang dibatasi rentangnya adalah tanggal pemakaian. Flag visibilitas kartu pendaftaran lama tidak otomatis menjadi izin/larangan booking baru.
- Halaman terpisah memuat identitas, pembimbing/penguji, tanggal pilihan, dan Cari Jadwal. Sesudah pencarian tampil satu hasil beserta Konfirmasi Jadwal. Mahasiswa dapat meninggalkan hasil tanpa memesan.
- Sesudah konfirmasi berhasil, kartu memperlihatkan tanggal, jam, ruang dan status. Tidak ada tindakan mahasiswa untuk mengubah/membatalkan atau memesan booking kedua guna mengganti booking aktif. Tampilkan petunjuk menghubungi SSC.
- Saat petugas mengubah/membatalkan, halaman mahasiswa menampilkan status terkini. Riwayat keputusan dan peminjaman tetap dapat ditelusuri oleh pihak yang berhak.

**Usulan teknis identitas:** label ID berarti **ID Pengajuan TA** yang sudah ada. ID booking baru ditampilkan sesudah penyimpanan berhasil. Tanggal sidang adalah tanggal pilihan/hasil pemesanan ini, bukan tanggal sidang historis pada profil. Identitas tidak menjadi input bebas. Kode dosen yang tersedia ditampilkan dari data TA; pemetaan nama memakai master yang cocok secara jelas. Data kosong ditulis Belum tersedia dan tidak menambahkan blokir dosen pada versi awal.

**Usulan pemilihan pengajuan:** gunakan pengajuan approved terbaru milik NIM mahasiswa sebagai konteks kartu, dengan urutan waktu pengiriman lalu ID untuk hasil yang konsisten. Pengajuan lama tidak dipilih diam-diam sebagai pengganti jika periode pengajuan terbaru belum diatur. Booking aktif yang sudah dimiliki tetap ditampilkan dan mencegah pemesanan kedua. Kasus beberapa approval historis dipastikan saat implementasi tanpa mengubah approval yang sudah ada.

### Admin/petugas Divisi Akademik

- Review Sidang TA memuat kartu Kelola Periode dan Kelola Tanggal Pelaksanaan Sidang berdampingan; informasi periode aktif berada di bawah.
- Pengaturan pelaksanaan terikat satu periode TA: tanggal mulai–selesai, hari/jam, istirahat, durasi, serta sumber periode akademik untuk jadwal ruangan. Nilai awal mengikuti tabel bagian2.
- **Usulan pengaitan sumber:** petugas memilih periode akademik sumber jadwal, misalnya Ganjil2026/2027. Boleh diisi awal hanya jika satu sumber yang cocok dapat ditentukan dengan pasti; jika ambigu harus dipilih petugas. Pergantian periode KRS aktif tidak otomatis menautkan ulang jadwal pelaksanaan lama.
- Booking TA dapat dilihat dari pengajuan terkait dan dikenali dalam review Peminjaman Ruangan sebagai Sidang TA dengan NIM/nama; jangan menampilkan label dosen/NIP untuk mahasiswa.
- Perubahan tanggal/jam/ruang dan pembatalan dilakukan petugas berwenang setelah mahasiswa menghubungi SSC. Sebelum disimpan, periksa bentrok kembali. Perubahan gagal tidak boleh melepas booking lama.
- Sebelum membatalkan persetujuan TA, tampilkan dampak booking ikut dibatalkan. Simpan riwayat keduanya; tidak menghapus dokumen atau mengulang status verifikasi berkas.

## 5. Data dan sumber jadwal

Nama tabel/field berikut merupakan arah rancangan; implementer boleh menyesuaikan nama dan pembagian komponen sambil mempertahankan aturan dan keterlacakan.

| Data | Rancangan penyimpanan |
| --- | --- |
| Pengaturan pelaksanaan TA | Satu pengaturan per `sidang_ta_period_id`, tertaut `academic_period_id`; rentang tanggal, hari, jam, istirahat, durasi, versi perubahan |
| Pola mingguan | Record pola: periode akademik, ruang, hari, jam mulai–selesai, kode dosen/keterangan sumber, identitas sumber dan batch impor |
| Jadwal tanggal tertentu | Pertahankan sumber tanggalan lama `room_schedules` untuk kejadian tanggal tertentu/legacy yang belum terbukti sebagai pola mingguan |
| Bukti sumber siap | Metadata batch berhasil: periode, jenis cakupan, kelengkapan/filter, waktu impor; bukan sekadar pernah ada log sukses |
| Penanda kelas | Penandaan eksplisit kelas/nonkelas/belum ditentukan pada master atau pemetaan yang ditinjau petugas |
| Booking dan pemakaian | Gunakan `room_booking_requests` dan `room_booking_occurrences`, dengan hubungan eksplisit ke pengajuan TA untuk booking TA otomatis/alternatif yang ditautkan admin |
| Konteks penjadwalan | Mode otomatis/dosen, versi perubahan, booking saat ini dan jejak pernah dikonfirmasi; penanda mode dosen menghentikan konfirmasi otomatis sebelum booking alternatif dibuat |
| Riwayat | Pertahankan log TA dan `room_booking_histories`; perubahan jadwal juga harus menyimpan tanggal/jam/ruang sebelum–sesudah, alasan, dan pelaku |

**Usulan batas booking:** maksimal satu booking TA aktif per pengajuan dan per NIM. Ini mencegah beberapa approval historis dipakai untuk menahan beberapa ruang. Mengonfirmasi ulang request yang sama tidak membuat booking baru. Riwayat booking cancelled tetap tersimpan. Pembatasan ini pilihan rancangan asisten yang perlu ditinjau bersama dokumen.

Perubahan master dosen, kode pembimbing/penguji dan tanggal sidang akademik tidak dilakukan sebagai efek samping pemesanan. Jadwal pemesanan memiliki hubungan ke pengajuan sendiri. Data akademik tetap mengikuti sumber sinkronisasi yang sudah ada.

### Mengidentifikasi ruang kelas

Master dan Excel saat ini belum mempunyai field jenis. Seluruh60 nama pada Excel contoh memuat KELAS,46 aktif dan14 nonaktif. **Usulan:** tambahkan penandaan kelas yang dapat ditinjau petugas pada Data Akademik → Ruangan. Nama/kode master dapat membantu menawarkan pemetaan awal, tetapi status aktif saja tidak cukup membuktikan kelas. Ruang hasil impor yang belum dikenali jenisnya dikecualikan dari kandidat TA sampai diperiksa; tetap dapat mengikuti alur peminjaman biasa sesuai aturan yang sudah ada.

Tidak menetapkan kapasitas minimum baru tanpa aturan divisi. Kapasitas/fasilitas/kampus tetap bagian P05 pada109. Pilihan bentuk input dan pemetaan yang efisien diserahkan kepada implementer, dengan syarat tidak menandai laboratorium atau ruang ambigu sebagai kelas secara diam-diam.

## 6. Kontrak impor dan pengulangan semester

1. Struktur JSON resmi tetap: metadata sumber/filter dan `data[]` dengan `periode`, `hari`, `tanggal`, `jam_mulai`, `jam_selesai`, `ruangan`, `jenis_jadwal`, serta field pendukung yang sudah ada.
2. `periode: 2627/1` berarti Ganjil2026/2027. Periode dan batas tanggal harus terdaftar; jangan menebak dari periode aktif apabila metadata menunjuk periode lain. Validasi hari cocok dengan tanggal dan waktu akhir setelah awal.
3. `TETAP` menjadi pola mingguan. Tanggal referensi tidak menjadi batas seminggu. Saat diperiksa, pola berlaku hanya dalam batas semester yang terdaftar. Jenis yang tidak dikenali tidak boleh otomatis dianggap TETAP; tampilkan alasan agar sumber diperjelas.
4. Pola disimpan sekali dengan identitas yang memungkinkan deduplikasi. Beberapa baris referensi yang mewakili pemakaian sama tidak menggandakan pemakaian dalam hasil gabungan.
5. Sumber lengkap per semester diperlukan sebelum pencarian TA dibuka. File contoh mencakup seluruh pekan9–15 November untuk periode2627/1, dengan filter prodi/ruang/bentrok kosong. Kelengkapan harus diperiksa dari metadata dan validitas seluruh baris; `total` sesuai jumlah baris saja tidak membuktikan kelengkapan.
6. Snapshot lengkap mengganti pola **semester yang sama saja**, tidak menghapus pola semester lain, booking internal, riwayat, atau sumber tanggalan yang tidak terbukti termasuk cakupan tersebut. File terfilter/parsial tidak boleh diam-diam mengganti baseline lengkap. **Usulan versi awal:** tolak pembaruan pola parsial dengan pesan penjelasan, sampai kontrak pembaruan parsial memang diperlukan.
7. Jadwal malam/akhir pekan tetap diimpor dan dipakai sebagai data terpakai bagi peminjaman biasa. Batas layanan TA hanya membatasi pencarian TA. Import pola tidak boleh menganggap ruang tanpa perkuliahan sebagai ruang rusak/nonaktif atau mengaktifkan ruang nonaktif semata karena muncul pada jadwal.
8. Format lama `aaData` dapat dipertahankan untuk jadwal tanggal tertentu. Data lama tetap dibaca sesuai tanggal, bukan direinterpretasi mingguan tanpa bukti. Log sukses lama tidak otomatis membuktikan sumber pola semester sudah siap untuk TA.
9. **Usulan konflik impor:** validasi snapshot baru terhadap booking internal pending/approved yang masih relevan sebelum mengganti data. Jika berbenturan, tolak penggantian secara utuh, pertahankan baseline lama dan berikan daftar bentrok untuk ditangani petugas. Jangan membatalkan booking diam-diam. Ini jawaban rancangan asisten atas P10, belum keputusan kebijakan pengguna/divisi.
10. Perubahan tahun/term periode yang sudah dipakai pola/pelaksanaan harus dijaga seperti periode yang dipakai KRS. Perubahan batas semester yang menghilangkan cakupan booking atau membuat pola baru bertabrakan memerlukan penyelesaian dahulu; tidak memperbarui jadwal mahasiswa secara diam-diam.

**Usulan kesiapan sumber bersama:** hasil pemeriksaan membedakan tersedia, terpakai, dan belum diketahui karena sumber belum terverifikasi. Kalender/pencarian biasa juga tidak boleh menandai hari kosong hanya karena pola semester belum dimuat. Saat dukungan pola diaktifkan, pencarian/penyimpanan baru memerlukan sumber yang mencakup tanggalnya; daftar/status/pembatalan booking yang sudah ada tetap dapat diakses sesuai hak. Log sukses legacy semata tidak membuktikan cakupan masa depan. Aktivasi perlu didahului impor/peninjauan sumber yang tepat agar tidak menghentikan pemesanan karena persiapan terlewat.

Perubahan online/asinkron/pindah hari sesekali belum mempunyai kontrak pengecualian. Versi awal tetap menganggap pola asal terpakai sampai ada koreksi sumber yang terverifikasi. Peminjaman pengganti tidak otomatis menghapus pertemuan asal. Kalender pengecualian perkuliahan menjadi tindak lanjut P03, bukan pekerjaan baru pada versi awal ini.

## 7. Pemeriksaan ketersediaan dan pemilihan slot

Komponen bersama menggabungkan:

- Pola mingguan yang hari dan batas semesternya mencakup tanggal pemeriksaan.
- Jadwal tanggal tertentu pada tanggal itu.
- Peminjaman internal pending/approved, termasuk booking TA yang sudah dikonfirmasi.

Pemeriksaan pemakaian fisik tidak bergantung semata pada flag periode KRS aktif. Bila beberapa sumber semester yang batas tanggalnya masih berlaku mencatat ruang terpakai, semuanya diperhitungkan; tidak mengabaikan pemakaian hanya karena salah satu periode bukan periode aktif UI.

Algoritme TA:

1. Pastikan pengajuan approved milik mahasiswa, pengaturan pelaksanaan lengkap, sumber semester siap, dan tidak ada booking aktif lain yang melanggar batas bagian5.
2. Pastikan tanggal berada dalam rentang pelaksanaan dan sumber semester, hari diizinkan, dan bukan tanggal yang sudah lewat. **Usulan:** jika tanggal yang dipilih hari ini, jangan menawarkan jam yang sudah lewat; syarat jeda minimal sebelum sidang masih menjadi konfirmasi divisi jika diperlukan.
3. Ambil hanya kelas yang Dapat Dipakai dan jenisnya telah jelas.
4. Gabungkan interval terpakai per ruang. Batas waktu memakai interval awal inklusif/akhir eksklusif: sesi yang berakhir10.00 boleh diikuti mulai10.00 selama belum ada aturan jeda tambahan.
5. Batasi pencarian dengan jam layanan dan keluarkan istirahat. Sesi harus muat utuh dalam satu celah, tidak melewati istirahat atau batas akhir hari.
6. Hitung awal paling cepat untuk setiap kandidat, kemudian urutkan menurut waktu mulai, kode ruang, dan ID sebagai pengikat urutan yang stabil.
7. Tampilkan satu hasil. Bila tidak ada, mahasiswa memilih tanggal lain; tidak berpindah hari/jenis ruang otomatis.

Contoh120 menit: kelas A baru kosong09.30–11.30; kelas C kosong08.00–10.00; lab B kosong08.00–10.00. Pilih C08.00–10.00. Celah11.00–13.00 tidak dapat dipakai jika istirahat12.00–13.00. Celah09.30–11.30 tetap sah tanpa pembulatan ke jam bulat.

Kalender peminjaman biasa mempertahankan jam dan cakupan ruangnya sendiri, tetapi memakai interval terpakai dari komponen bersama. Jangan memaksakan jam/durasi/kelas-saja milik TA ke alur dosen.

## 8. Konfirmasi, perubahan, dan pembatalan

### Konfirmasi mahasiswa

Pratinjau tidak menahan slot. Saat Konfirmasi Jadwal, server membaca ulang status pengajuan, versi pengaturan, sumber jadwal, dan ruang. Identitas/konteks tidak dipercaya dari input bebas.

Penyimpanan booking, occurrence, hubungan TA dan riwayat dilakukan utuh dalam transaksi. Koordinasikan penguncian dengan impor, peminjaman biasa, perubahan periode dan pembatalan persetujuan. Urutan lock konsisten perlu ditetapkan setelah audit seluruh jalur terkait; uji dua pemesan bersamaan pada MySQL yang digunakan aplikasi. Jangan mengklaim uji SQLite membuktikan perilaku lock MySQL.

Jika hasil berubah/diambil orang lain, tampilkan hasil terkini dan minta konfirmasi ulang. Jangan memindahkan mahasiswa ke jam/ruang lain diam-diam. Request ganda atau tab lama tidak membuat booking kedua atau menghidupkan status lama.

### Koreksi oleh Divisi Akademik

- Wajib permission yang sesuai dan alasan perubahan. Simpan sebelum–sesudah beserta pelaku/waktu pada riwayat.
- Perubahan memeriksa kembali sumber, bentrok, dan batas jadwal. Gagal pindah berarti booking lama tetap berlaku; berhasil pindah berarti slot lama dilepas dan slot baru ditahan dalam satu perubahan utuh.
- **Usulan:** perubahan jadwal di luar rentang/hari/jam tidak diberikan sebagai pengecualian tersembunyi. Petugas terlebih dahulu memperbarui pengaturan yang relevan secara sah.
- Pembatalan menyimpan record/history dan melepas slot. Mahasiswa tetap tidak mendapat hak endpoint mutasi setelah konfirmasi.

### Pembatalan persetujuan TA

Ikuti T10: sebelum pelaksanaan, pembatalan persetujuan membatalkan booking TA aktif yang tertaut secara eksplisit. Perubahan status dan riwayat dibuat utuh; jangan membatalkan peminjaman dosen berdasarkan pencocokan teks.

**Usulan batas aman:** jika pemakaian sudah dimulai/selesai, jangan membatalkan booking historis sebagai efek samping; tampilkan alasan dan minta penanganan petugas yang sesuai. Perilaku kasus tersebut belum ditentukan pengguna. Jika persetujuan diberikan lagi, booking lama tidak otomatis hidup kembali.

**Usulan pemesanan ulang:** booking yang pernah dikonfirmasi tidak dapat diganti mahasiswa dengan membuat booking baru setelah petugas membatalkan. Penjadwalan penggantinya tetap dikelola Divisi Akademik. Ini menjaga aturan T11. Cara pelaksanaan penjadwalan ulang administratif dicantumkan pada halaman koreksi, dengan hubungan/riwayat yang tetap tersimpan.

### Perubahan rentang atau parameter pelaksanaan

**Usulan:** tolak perubahan yang membuat booking aktif keluar dari rentang/hari/jam yang baru, kecuali seluruh booking terdampak lebih dahulu diselesaikan/dipindahkan. Jangan memotong durasi booking tersimpan ketika nilai default diganti. Tampilkan daftar booking terdampak untuk petugas; pertahankan nilai lama jika penyimpanan ditolak.

## 9. Hak akses

Pemetaan berikut merupakan usulan memakai permission yang sudah ada, tanpa mereset penetapan role di server:

| Kegiatan | Pemeriksaan |
| --- | --- |
| Mahasiswa mencari/mengonfirmasi | Konteks mahasiswa, kepemilikan NIM/pengajuan, approved, hak TA mahasiswa yang sesuai, dan seluruh syarat booking |
| Mahasiswa melihat jadwal | Milik sendiri; tidak memberi akses kalender/data mahasiswa lain tanpa hak yang memang dimiliki |
| Mengatur pelaksanaan | Hak baca periode TA untuk melihat, hak ubah periode TA untuk mengubah |
| Memperbaiki booking TA | Hak Review TA yang diberikan kepada petugas Divisi Akademik; koreksi dibatasi booking TA yang berhak dikelolanya |
| Dosen baca prodi | Hak baca pengajuan prodi yang sudah ada; tidak memperoleh aksi penjadwalan/pembatalan mahasiswa |
| Booking dosen biasa | Tetap memakai hak/aturan Peminjaman Ruangan yang sudah ada |
| Impor sumber | Tetap memakai hak sinkronisasi yang sesuai; klasifikasi ruang memakai hak ubah master ruang |

“Divisi Akademik” adalah penanggung jawab operasional. Nama divisi tidak otomatis dipetakan ke seluruh pemilik role aplikasi tertentu. Jika permission yang ada ternyata terlalu luas saat audit implementasi, rincikan aksi khusus dan tampilkan perubahan tersebut dalam rencana untuk ditinjau; jangan memperluas akses diam-diam.

**Penjagaan endpoint lama:** `RoomBookingController::adminCancel()` saat ini menerima reviewer peminjaman umum. Setelah booking TA memakai tabel yang sama, endpoint itu wajib membedakan booking TA dan menerapkan otorisasi koreksi TA yang sama dengan halaman TA. Permission review ruangan saja tidak otomatis membolehkan mengubah/membatalkan booking TA. Endpoint pemilik peminjaman biasa juga tidak boleh menjadi jalan mahasiswa mengubah/membatalkan booking yang sudah dikonfirmasi.

## 10. Alternatif peminjaman oleh dosen

Sesuai T08, dosen boleh memakai peminjaman biasa jika mahasiswa meninggalkan pratinjau. Booking dosen tetap mengikuti persetujuan/status dan hak akses yang sudah ada.

**Sudah dipilih pada T13:** admin menandai pengajuan Dijadwalkan melalui dosen dan menautkan peminjaman yang benar. Tidak menautkan otomatis dari NIM/nama dalam tujuan peminjaman. Rincian berikut merupakan pilihan teknis untuk menjalankan keputusan tersebut:

1. Petugas menandai pengajuan **sebelum dosen membuat peminjaman alternatif**. Mode dosen, pelaku/alasan/waktu dan versi konteks disimpan. Cari/Konfirmasi otomatis mahasiswa dihentikan; pratinjau/tab lama ditolak ketika diperiksa ulang.
2. Bila sudah ada booking TA otomatis yang aktif, penandaan tidak diam-diam membatalkannya. Petugas terlebih dahulu memindahkan/membatalkan melalui jalur yang berwenang. Penandaan dan konfirmasi yang bersamaan harus terserialisasi; tidak boleh keduanya berhasil menahan jadwal berbeda.
3. Dosen memakai form peminjaman yang sudah ada. Admin menautkan ID booking secara eksplisit ke pengajuan. Data NIM/pengajuan dan identitas peminjam ditampilkan untuk diperiksa sebelum konfirmasi penautan.
4. **Usulan kelayakan link versi awal:** hanya peminjaman single dengan tepat satu occurrence, status pending/approved, ruang kelas, tanggal/hari/jam serta durasi sesuai pengaturan TA, dan belum tertaut ke pengajuan lain. Peminjaman berulang/multioccurrence atau jadwal di luar aturan tidak ditautkan secara diam-diam; petugas mengoreksi booking/pengaturan dahulu.
5. Pending tetap pending dan tetap menahan slot sesuai aturan peminjaman. Kartu mahasiswa menampilkan Menunggu persetujuan peminjaman, tanpa konfirmasi otomatis. Menautkan tidak menjadi tindakan menyetujui peminjaman dosen.
6. Jika kemudian approved, jadwal kartu TA menjadi terkonfirmasi. Jika pending ditolak/dibatalkan, kartu menunjukkan belum terkonfirmasi/perlu petugas; mode dosen tetap dipertahankan. Tidak membuka jalur otomatis atau menghidupkan slot lama diam-diam.
7. Dosen pemilik tetap boleh membatalkan **pengajuan pending miliknya** sesuai alur lama; sinkronkan konteks TA dan riwayat saat peminjaman itu tertaut. Hak ini tidak diberikan kepada mahasiswa dan tidak berlaku pada booking TA otomatis yang approved.
8. Booking yang tertaut hanya mempunyai satu pengajuan tujuan. Hubungan/riwayat lama tidak ditulis ulang menjadi mahasiswa lain. Penggantian link oleh petugas memerlukan booking lama tidak aktif atau tindakan pembatalan/perpindahan yang sah.
9. Pembatalan persetujuan TA membatalkan booking aktif yang tertaut, baik pending maupun approved, sebelum pelaksanaan. Ini bagian perubahan persetujuan T10 yang tertarget, bukan akses membatalkan peminjaman lain. Persetujuan dari tab lama setelah pembatalan ditolak.
10. Penautan/koreksi peminjaman dosen memerlukan hak Review TA dan hak pengelolaan peminjaman yang sesuai. Endpoint dosen lama mempertahankan hak pemilik pending dengan sinkronisasi yang aman. Reviewer ruangan saja tidak memperoleh akses mengubah booking/pengajuan TA secara umum.

**Contoh lengkap:** hasil otomatis23 November08.00–10.00 tidak dipilih; admin menandai mode dosen; dosen mengajukan24 November13.00–15.00; admin menautkan ID tersebut. Mahasiswa melihat pending lalu terkonfirmasi setelah peminjaman approved. Jika dosen membatalkan saat pending, slot24 November dilepas dan pengajuan tetap mode dosen untuk ditangani petugas. Contoh tambahan A–D ada di109 T13.

Penanda harus mendahului proses alternatif untuk menjamin jalur otomatis berhenti. Jika dosen telanjur memesan sebelum penandaan dan booking otomatis juga sudah ada, aplikasi tidak dapat menebak hubungan dari keterangan; petugas menyelesaikan kedua booking secara eksplisit sebelum mengaitkannya. Panduan harus menyebut urutan ini agar jaminan aplikasi tidak dilebihkan.

## 11. Data belum lengkap dan konfirmasi yang tetap terbuka

Rincian sumber dan contoh berada pada109 bagian5/6:

- Durasi/jeda resmi, hari libur/Jumat, pemeriksaan dosen, pengecualian perkuliahan, kelas yang diperbolehkan, dan konsekuensi pembatalan otomatis tetap bahan konfirmasi divisi.
- JSON profil277 baris: penguji1/2 masing-masing baru terisi6 baris;271 kosong. Tujuh isian pembimbing kedua belum cocok master. Nilai yang ada ditampilkan; yang kosong tidak dibuat-buat.
- Importer dosen perlu menerima header yang benar dari Excel yang diberikan agar kode/nama tersedia. Perubahan dibatasi kebutuhan tampilan identitas terkait; jangan mengubah seluruh kebijakan impor dosen tanpa alasan.
- JSON kehadiran masih menunggu. Tidak menjadi dependensi pencarian ruang versi awal.
- Slot ruang kosong belum menjamin seluruh dosen hadir; ini perlu jelas dalam panduan pencarian dan konfirmasi.

Usulan asisten yang masih perlu diperiksa pada tinjauan rancangan: satu booking aktif per NIM/pengajuan, pemilihan approval terbaru, penandaan kelas, pengaitan periode sumber, penolakan impor parsial/bentrok, pembatasan perubahan yang berdampak ke booking aktif, permission koreksi, serta pemesanan ulang melalui petugas. Semuanya belum dicatat sebagai jawaban pengguna.

## 12. Cakupan perubahan yang diperkirakan

Daftar ini untuk peninjauan scope, bukan daftar file yang sudah dibuat. Nama komponen baru final ditentukan pada rencana implementasi setelah rancangan disetujui.

| Area | Perubahan yang diperkirakan |
| --- | --- |
| Model/skema | Pengaturan pelaksanaan TA, pola mingguan/metadata sumber, penanda kelas, hubungan booking–pengajuan, riwayat perubahan jadwal; migrasi tambahan yang menjaga data lama |
| Service baru | Pemeriksaan ketersediaan bersama; pencarian/konfirmasi/koreksi booking TA; pembacaan identitas dosen yang aman |
| `RoomScheduleImportService` | Memahami pola/semester, validasi kelengkapan, pembaruan per semester dan bentrok dengan booking |
| `RoomBookingController`/view | Memakai pemeriksaan bersama di seluruh jalur relevan; identitas booking TA; mempertahankan alur dosen |
| `SidangTaRegistrationController`/view | Kartu mahasiswa/admin, konteks booking approved, tautan pencarian/detail |
| `SidangTaRegistrationReview` | Integrasi pembatalan persetujuan dan booking terkait sesuai T10; pertahankan verifikasi dokumen |
| Pengaturan periode/master ruang | Hubungan sumber semester, pembatasan perubahan berdampak, penandaan ruang kelas |
| Route/otorisasi | Endpoint mahasiswa/petugas sesuai hak tanpa mengubah SSO atau menyebarkan hak booking dosen ke mahasiswa |
| Tests/fixture/docs | Kasus fungsional dan konkurensi, data dummy yang jelas, panduan pemakaian/impor, daftar file perubahan dan command salin/update saat implementasi selesai |

`/dadu` sudah memuat modul peminjaman; `/dadu-test` belum. Sebelum implementasi, buat inventaris dependensi modul yang benar-benar diperlukan dan patch gabungan pada berkas bersama. Jangan menimpa Docker, SSO, permission, atau fitur antrean dari perbedaan branch yang tidak terkait. Penyalinan ke repo Docker diuji dengan hash/filelist yang spesifik, bukan menyalin seluruh direktori.

## 13. Verifikasi yang harus dilakukan saat implementasi

Pengujian mengikuti perilaku penting, termasuk:

1. Pengajuan sudah approved sebelum pemasangan tetap dapat memakai pengaturan baru; pengajuan lain tidak dapat memesan.
2. Pola Senin9 November memblokir Senin23 November; tidak melewati batas semester; kalender/pencarian/persetujuan dosen dan TA konsisten.
3. Semua kelas dibandingkan; lab dan ruang ambigu tidak terpilih; celah pendek, istirahat, batas hari, jam lewat, dan waktu yang berdampingan ditangani benar.
4. Pratinjau tidak menahan ruang; konfirmasi langsung approved; dua pengguna/tab mengincar slot sama tidak menghasilkan bentrok. Verifikasi MySQL untuk transaksi/locking, serta jangan menukar koneksi dengan database aplikasi aktif.
5. Mahasiswa tidak dapat mengubah/membatalkan setelah konfirmasi melalui UI maupun endpoint. Petugas yang berhak dapat memindahkan tanpa kehilangan booking lama ketika target gagal.
6. Pembatalan persetujuan membatalkan booking terkait, mempertahankan warna/status berkas dan seluruh riwayat; peminjaman dosen lain tidak ikut batal.
7. Impor/pengaturan yang tidak valid tidak mengganti data sebagian; sumber semester lain dan booking internal tetap utuh; format lama tetap dibaca sesuai kontraknya.
8. ID/NIM/nama/tanggal/kode dosen muncul sesuai sumber; data kosong/ambigu tidak diisi tebakan; tidak terjadi kebocoran mahasiswa/prodi lain.
9. Regresi permission, role Pelaksana baca prodi, alur dosen pending/approved/cancelled, pengaturan pendaftaran/Verifikasi Berkas dan kode TA yang sudah berjalan.

Pada tahap dokumentasi ini tidak menjalankan unit/feature test aplikasi dan tidak mengklaim fitur sudah lulus. Hasil nyata pengujian dicatat pada dokumen implementasi kelak, termasuk baseline gagal yang tidak terkait jika ditemukan.

## 14. Urutan tahap setelah rancangan ditinjau

1. Selesaikan tinjauan rancangan/rencana111, terutama pilihan teknis asisten. Penautan alternatif sudah dipilih pada T13 dan termasuk scope.
2. Susun rencana implementasi rinci dan dokumen hasil terpisah yang menautkan110/109. Rescan ulang sebelum kode dan isolasikan perubahan dari pekerjaan teman/stash.
3. Kerjakan fondasi pola/ketersediaan bersama dan migrasi kompatibel, lalu integrasi TA dan otorisasi.
4. Uji perilaku baru serta regresi terkait, tinjau hasil, dan rescan setelah perubahan.
5. Tulis command copy khusus file yang berubah dan panduan update Docker/production yang menjaga data lama. Tidak memakai migrate:fresh/seed untuk fitur ini.

## 15. Pemeriksaan sebelum/sesudah penulisan rancangan

Sebelum penulisan: Git/HEAD kedua folder sama dengan baseline pada109; modul Peminjaman Ruangan hanya tersedia di `/dadu`. Perubahan awal `.gitignore`, `package-lock.json`, dan `phpunit.xml` dipertahankan. Tidak ada kode/database yang diubah.

Setelah penulisan awal:

- Hash620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan kode/config/test baseline tetap sama; HEAD tidak berubah. Hanya dokumen109/110 ditambahkan. Tiga perubahan lokal awal `/dadu` tetap utuh.
- Tautan lokal, nomor T01–T12/P01–P12 pada catatan109, whitespace, dan `git diff --check` diperiksa. Tidak menjalankan unit/feature test aplikasi karena belum ada implementasi.
- Satu review independen baca saja menemukan pertanyaan penting tentang dua peminjaman untuk sidang yang sama melalui jalur alternatif dosen. Temuan dicatat pada bagian10 dan P13 dokumen109, lalu diajukan kepada pengguna.
- Review juga mengingatkan penjagaan endpoint pembatalan peminjaman umum; penjelasan diperjelas pada bagian9. Kesiapan sumber bagi kedua alur dan pemesanan sebelum masa pelaksanaan juga diperjelas pada pemeriksaan langsung.
- Rescan final pada tahap catatan review awal:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tetap. Tautan lokal109↔110, nomor T01–T12/P01–P13, status jawaban/contoh, whitespace dan `git diff --check` lulus. Saat itu P13 masih menunggu jawaban.
- Pembaruan berikutnya: P13 dijawab pada T13, memilih admin menandai/menautkan. Bagian10 dirinci dan rencana111 disusun. Rincian pending/cancel, urutan penandaan dan penautan merupakan pilihan teknis yang ditinjau bersama rencana.
- Review independen pembaruan110/111 tidak menemukan masalah penghambat; khususnya memeriksa pemilik dosen membatalkan pending, larangan mahasiswa, parent/room/booking lock, dan perubahan link. Self-review memastikan dependensi task tidak terbalik.
- Rescan setelah pembaruan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan kode/config/test baseline tetap sama; HEAD tetap. Tautan109↔110↔111, T01–T13/P01–P13, contoh/status jawaban, urutan sembilan task, whitespace dan `git diff --check` lulus. Hanya tiga dokumen baru; tiga perubahan lokal awal tetap utuh. Kode/database belum berubah; tes aplikasi belum dijalankan. Rencana111 diserahkan untuk ditinjau sebelum eksekusi.
