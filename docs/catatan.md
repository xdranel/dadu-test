## Catatan
### T01 — Booking langsung disahkan atau menunggu persetujuan lagi?

**Pertanyaan yang diajukan:** Setelah sistem menemukan ruangan dan jam paling awal pada tanggal pilihan mahasiswa, apakah peminjaman Sidang TA langsung disahkan atau masih harus disetujui petugas peminjaman ruangan?

**Keputusan:** Booking TA langsung approved, admin dapat mengoreksi jika diperlukan. Alur persetujuan peminjaman biasa tetap mengikuti fitur teman.

**Contoh:** Pendaftaran TA mahasiswa sudah Disetujui. Pemesanan hasil pencarian pada 23 November 08.00–10.00 berhasil, ruang langsung tertahan dan tidak menunggu persetujuan kedua.

### T02 — Durasi satu sesi dan jeda pergantian mahasiswa

**Pertanyaan yang diajukan:** Berapa lama satu sesi Sidang TA harus menahan ruangan, termasuk jeda persiapan/pergantian mahasiswa jika diperlukan? Kalender peminjaman sekarang hanya menandai celah minimal satu jam itu belum menjadi aturan durasi TA.

**Jawaban pengguna:** Untuk sementara boleh memakai 1–2 jam agar pekerjaan dapat disiapkan, dan pertanyaan durasi resmi harus dicatat untuk ditanyakan kembali.

**Perkembangan jawaban:** Pada T07 pengguna menyetujui rekomendasi 120 menit sebagai pengaturan awal sementara. Durasi dan jeda resmi tetap terbuka pada P01.

**Contoh:** Celah 09.30–11.30 cukup untuk 120 menit. Celah 10.00–11.00 tidak cukup. Jika divisi kemudian mewajibkan 120 menit sidang ditambah 15 menit persiapan, ruang harus kosong selama 135 menit, tambahan 15 menit tersebut belum disetujui.

### T03 — Pemeriksaan ruang saja atau juga jadwal dosen?

**Pertanyaan yang diajukan:** Pencarian cukup memastikan ruangan kosong, atau juga harus menghindari bentrok jadwal mengajar pembimbing/penguji?

**Jawaban pengguna:** Untuk sekarang ruangan kosong saja. pemeriksaan dosen dicatat sebagai konfirmasi lanjutan karena masih perlu ditanyakan.

**Keputusan:** Versi awal tidak memblokir hasil berdasarkan jadwal pembimbing/penguji. Hasil hanya menjamin ketersediaan ruang menurut sumber jadwal yang diperiksa.

**Contoh:** Ruang B kosong 08.00–10.00, tetapi pembimbing mempunyai kelas 08.30–10.30. Versi awal masih dapat menawarkan ruang B, konfirmasi kehadiran dosen harus diatur di luar pemeriksaan otomatis versi awal. Rencana pemeriksaan dosen berikutnya ada pada P02.

### T04 — Makna jadwal `TETAP` dengan tanggal contoh 9–15 November

**Pertanyaan yang diajukan:** Apakah jadwal berjenis `TETAP` pada JSON 9–15 November memang berulang setiap minggu sepanjang semester yang sama?

**Jawaban pengguna:** Jadwal mingguan sama sepanjang semester tersebut. Sesekali dosen dapat meminta online/asinkron atau pindah hari, peminjaman ruang pengganti dapat melalui fitur Peminjaman Ruangan. Semester berikutnya memiliki jadwal berbeda.

**Keputusan:** Pola mingguan berlaku dalam semester yang sama, semester berikutnya harus memakai pola yang sesuai. Pengecualian satu pertemuan belum ditentukan pada jawaban ini.

**Contoh:** Senin 9 November, ruang A terpakai 08.30–10.30. Pada Senin 23 November dalam semester yang sama, interval tersebut tetap dianggap terpakai. Ketika semester berganti, pola lama tidak otomatis dipakai. Jika pertemuan 23 November dialihkan online, cara mengosongkan ruang A untuk tanggal itu perlu konfirmasi P03.

### T05 — Bentuk tanggal pelaksanaan yang dikelola petugas

**Pertanyaan yang diajukan:** Tanggal pelaksanaan dibuat sebagai rentang mulai–selesai untuk masing-masing periode TA, misalnya23–27 November, benar?

**Keputusan:** Setiap periode TA mempunyai rentang mulai–selesai pelaksanaan tersendiri. Pendaftaran dan Verifikasi Berkas tetap mempunyai jadwal masing-masing.

**Contoh:** Periode TA1 menetapkan 23–27 November. Mahasiswa dengan pengajuan periode TA1 memilih tanggal di rentang tersebut, tanggal22 dan28 tidak boleh dipesan. Periode TA2 dapat memiliki rentang lain. Hari yang diperbolehkan di dalam rentang mengikuti pengaturan pada T07.

### T06 — Hari/jam sidang dan hubungan dengan struktur JSON

**Pertanyaan yang diajukan:** Hari dan jam pelaksanaan sidang mengikuti ketentuan apa? Kalender peminjaman saat ini memakai 06.30–21.30 WIB, tetapi belum tentu seluruh rentang itu dipakai untuk sidang.

**Rekomendasi asisten:** Senin–Jumat 08.00–16.00 WIB, istirahat 12.00–13.00 tanpa sesi yang melewatinya, durasi sementara 120 menit, dapat disesuaikan petugas per periode TA. Struktur JSON tetap dipakai untuk pola mingguan dan interval ruang terpakai. Seluruh jadwal perkuliahan tetap dibaca, termasuk malam dan akhir pekan.

**Contoh:** File tetap memuat `periode`, `hari`, `tanggal`, `ruangan`, `jam_mulai`, `jam_selesai`, dan `jenis_jadwal`. Tidak perlu menambahkan jam layanan Sidang TA ke setiap baris JSON. Ruang yang kosong 11.00–13.00 tidak dapat ditawarkan untuk sesi 120 menit jika istirahat 12.00–13.00 diberlakukan. Ruang yang kosong 13.00–15.00 dapat ditawarkan.

### T07 — Persetujuan pengaturan awal dan cara pencatatan

**Pertanyaan yang diajukan:** Apakah pengaturan awal pada T06 sesuai untuk rancangan sementara?

**Jawaban pengguna:** “sesuai”, dengan instruksi agar setiap pertanyaan/jawaban dicatat dan pertanyaannya dijelaskan menggunakan contoh sehingga dapat ditanyakan atau dibaca langsung oleh divisi.

**Keputusan:** Rekomendasi T06 disetujui untuk rancangan sementara. Ini memperjelas patokan1–2 jam pada T02 menjadi nilai awal120 menit. Seluruh aturan resmi hari, jam, durasi dan jeda tetap dicatat pada P01. Rancangan lengkap masih perlu disusun dan ditinjau sebelum kode diubah.

### T08 — Pratinjau hasil sebelum konfirmasi dan identitas yang ditampilkan

**Pertanyaan yang diajukan:** Setelah mahasiswa menekan Cari Jadwal dan sistem menemukan, misalnya, ruang B pada23 November08.00–10.00, apakah hasil ditampilkan dahulu dengan tombol Konfirmasi Jadwal, atau langsung dipesan saat pencarian? Asisten menyarankan konfirmasi agar mahasiswa sempat membaca hasil, setelah konfirmasi booking langsung disahkan tanpa persetujuan petugas lagi.

**Jawaban pengguna:** Hasil ditampilkan terlebih dahulu. Jika jadwal tidak disukai, biarkan dosen memesan melalui fitur Peminjaman Ruangan. Pengguna juga meminta informasi ID, NIM, nama, tanggal sidang, kode dosen pembimbing dan penguji ditampilkan ketika mencari maupun mengonfirmasi jadwal.

**Keputusan:** Ada tahap pratinjau sebelum konfirmasi. Hasil yang belum dikonfirmasi belum menjadi booking. Alternatif melalui peminjaman dosen diperbolehkan dengan alur dosen yang sudah ada. Identitas dan informasi dosen masuk rancangan halaman pencarian/konfirmasi.

**Contoh:** Mahasiswa melihat ruang B23 November 08.00–10.00, NIM/nama, ID pengajuan, serta kode pembimbing/penguji. Jika cocok, tekan Konfirmasi Jadwal. jika tidak, tinggalkan hasil dan koordinasikan alternatif dengan dosen. Penguji kosong ditampilkan Belum tersedia, jangan menggantinya dengan kode dosen pengampu pada jadwal ruangan. Aturan perubahan setelah booking disahkan dan penautan booking dosen masih ada pada P06.

PENTING TANYAKAN INI:
Alur sementara ini: Pilih tanggal → Cari Jadwal → lihat hasil → klik Konfirmasi Jadwal → booking langsung disahkan dan ruangan tertahan

Ada 2 Hal mengenai T01 dan T08
- T08: mahasiswa melihat hasil pencarian dahulu, lalu menekan tombol Konfirmasi Jadwal jika cocok. Sebelum tombol ditekan, booking belum dibuat dan ruangan belum ditahan
- T01: setelah mahasiswa mengonfirmasi, booking langsung disahkan, tanpa menunggu persetujuan admin/petugas lagi.

Jadi, ada tombol konfirmasi untuk mahasiswa, tetapi mahasiswa menekannya sebelum booking disahkan. Setelah berhasil, halaman menampilkan informasi booking, tidak perlu konfirmasi kedua atau melalui admin/petugas.
Jika hasil pencarian kurang cocok, mahasiswa boleh tidak mengonfirmasi dan berkoordinasi dengan dosen untuk alternatif melalui Peminjaman Ruangan(alur peminjaman ruangan sekarang atau bisa juga melalui ssc/akademik)
???

### T09 — Kandidat ruang kelas yang tersedia dan tidak bentrok

**Pertanyaan yang diajukan:** Untuk Sidang TA, apakah semua ruangan berstatus Dapat Dipakai boleh dialokasikan, atau hanya ruangan tertentu? Contohnya, ruang kelas dan laboratorium sama-sama aktif, apakah keduanya boleh dipilih otomatis? Ini menentukan daftar ruang yang diperiksa sebelum sistem mengambil jam paling awal.

**Jawaban pengguna:** “ruang kelas saja dan jelas yang available saja dan tidak bentrok”.

**Keputusan sementara:** Hanya ruang kelas yang berstatus Dapat Dipakai dan memiliki slot kosong berurutan sepanjang durasi. Laboratorium/ruang lain dikecualikan walaupun kosong. Pemeriksaan berlaku saat pencarian dan diperiksa ulang ketika konfirmasi.

**Contoh:** Ruang kelas A terpakai 08.00–10.00, laboratorium B kosong 08.00–10.00, ruang kelas C kosong 09.00–11.00. Untuk durasi 120 menit, pilih C 09.00–11.00, jangan memilih laboratorium B meskipun lebih pagi. Jika tidak ada kelas yang cukup kosong pada hari pilihan, tampilkan tidak ada jadwal, jangan beralih ke laboratorium secara otomatis.

**Konfirmasi lanjutan:** Apakah ruang kelas saja berlaku untuk semua prodi, serta apakah ada pengecualian gedung/kampus, kebutuhan fasilitas, dan kapasitas minimum? Pertanyaan pokok kelas vs laboratorium sudah dijawab untuk rancangan sementara.

### T10 — Pembatalan booking saat persetujuan TA dibatalkan

**Pertanyaan yang diajukan:** Jika persetujuan Sidang TA dibatalkan untuk diperiksa ulang sebelum hari pelaksanaan, apakah booking TA yang sudah dikonfirmasi ikut dibatalkan otomatis? Contohnya, booking23 November08.00–10.00 sudah disahkan, lalu persetujuan TA dibatalkan19 November. Asisten menyarankan booking ikut dibatalkan dengan riwayat tetap disimpan agar ruang kembali tersedia.

**Keputusan sementara:** Booking TA terkait ikut cancelled ketika persetujuan TA dibatalkan sebelum pelaksanaan. Slot tidak lagi menahan ruangan, sedangkan record/riwayat tetap tersimpan.

**Contoh:** Persetujuan dibatalkan 19 November, booking ruang B23 November 08.00–10.00 ikut dibatalkan. Ruang tersebut dapat dipesan pihak lain. Jika mahasiswa kembali disetujui 20 November, slot lama belum tentu tersedia, jadwal perlu dicari/dikonfirmasi ulang. Booking dosen yang tidak tertaut tidak boleh ikut dibatalkan hanya karena tujuan peminjaman menyebut nama/NIM yang sama.

**Konfirmasi ke divisi:** Apakah pembatalan persetujuan selalu harus melepas ruang meskipun pengajuan diperkirakan segera disetujui kembali? Apakah mahasiswa dan petugas cukup melihat status/riwayat atau memerlukan pemberitahuan tambahan? Permintaan pemberitahuan tambahan belum diputuskan. Kasus perubahan rentang periode dan booking yang sudah berlangsung merupakan pertanyaan berbeda, bukan otomatis dijawab oleh T10.

### T11 — Perubahan booking yang sudah dikonfirmasi melalui SSC/Divisi Akademik

**Pertanyaan yang diajukan:** Setelah mahasiswa menekan Konfirmasi Jadwal dan booking sudah disahkan, apakah ia boleh membatalkan atau mengganti jadwal sendiri, atau harus melalui admin/petugas? Contohnya, sudah mendapat 23 November 08.00–10.00 lalu ingin pindah 24 November. Ini berbeda dari meninggalkan hasil pencarian yang belum dikonfirmasi.

**Jawaban pengguna:** “tetap harus menghubungi admin/petugas di ssc jadi biar divis akademik yang merubahnya mahasiswa tidak bisa mengganti maupun membatalkannya”.

**Keputusan:** Setelah konfirmasi, perubahan/pembatalan hanya dilakukan pihak Divisi Akademik yang berwenang. Mahasiswa menghubungi admin/petugas di SSC dan tidak mempunyai akses mengubah/membatalkan sendiri, termasuk melalui request langsung. Dosen yang meminjam alternatif melalui alur biasa tidak otomatis mendapat hak mengubah booking TA mahasiswa.

**Contoh:** Booking 23 November 08.00–10.00 sudah disahkan. Mahasiswa ingin pindah 24 November: ia menghubungi petugas SSC, lalu Divisi Akademik memeriksa jadwal pengganti dan melakukan perubahan. Tampilan mahasiswa memperlihatkan jadwal terbaru dan petunjuk menghubungi SSC, tanpa tombol Ubah/Batalkan. Kewajiban memeriksa bentrok ulang dan menjaga booking lama jika pemindahan gagal menjadi bagian rancangan teknis.

**Batas jawaban:** Ini menetapkan siapa yang boleh mengubah/membatalkan setelah konfirmasi. Belum menetapkan cara menautkan peminjaman alternatif dosen, jumlah booking aktif, atau hak membuat booking baru setelah pembatalan oleh petugas. Hal-hal yang belum diputuskan tetap berstatus usulan/pertanyaan pada P06.

### T12 — Pendekatan pola mingguan per semester saat pencarian

**Pertanyaan yang diajukan:** Untuk rancangan integrasi, apakah memakai pola mingguan per semester yang dihitung saat pencarian, dengan pemeriksaan bersama untuk TA dan peminjaman ruangan, atau membuat jadwal seluruh tanggal semester saat impor?

**Keputusan:** Pilihan pertama dipakai sebagai dasar rancangan. Perhitungan ketersediaan harus sama pada kedua fitur, pola dibatasi semester dan tidak digandakan menjadi record setiap tanggal saat impor.

**Contoh:** Ruang A setiap Senin 08.30–10.30 pada Ganjil2026/2027 tetap bentrok ketika Senin23 November diperiksa. Saat semester berakhir, pola tidak diteruskan ke semester baru. Kalender peminjaman biasa dan pencarian TA harus menampilkan pemakaian yang konsisten untuk tanggal yang sama.

### T13 — Admin menandai dan menautkan peminjaman alternatif dosen

**Pertanyaan yang diajukan:** Jika dosen menangani peminjaman alternatif untuk sidang mahasiswa, apakah admin perlu menandai pengajuan “Dijadwalkan melalui dosen” dan menautkan booking agar mahasiswa tidak lagi mengonfirmasi booking otomatis? Contohnya, dosen memesan24 November, tetapi mahasiswa masih dapat mengonfirmasi hasil otomatis23 November sehingga dua ruang tertahan. Alternatif yang ditawarkan adalah koordinasi manual saja tanpa penautan aplikasi.

**Keputusan:** Penandaan dan penautan dilakukan admin; koordinasi manual tanpa hubungan aplikasi tidak menjadi pendekatan yang dipilih. Mahasiswa tetap tidak boleh mengubah/membatalkan booking yang sudah dikonfirmasi.

**Contoh A — Alternatif dipilih sebelum mahasiswa mengonfirmasi:** Mahasiswa melihat hasil23 November08.00–10.00 tetapi tidak mengonfirmasi. Petugas menandai pengajuan sebagai Dijadwalkan melalui dosen, lalu dosen mengajukan ruang24 November13.00–15.00. Admin menautkan ID peminjamannya. Jalur otomatis23 November berhenti, termasuk bila mahasiswa masih mempunyai tab hasil lama. Booking pending tetap menunggu persetujuan peminjaman dosen; sesudah approved, kartu mahasiswa menampilkan24 November13.00–15.00.

**Contoh B — Booking pending dosen dibatalkan/ditolak:** Dosen membatalkan pengajuan pending yang sudah ditautkan melalui alur biasa, atau petugas menolaknya. Slot dilepas, riwayat tersimpan, dan kartu TA menunjukkan jadwal alternatif belum terkonfirmasi/perlu ditangani petugas. **Usulan teknis:** status pengajuan tetap mode dosen agar mahasiswa tidak otomatis memesan jalur lain. Admin kemudian menautkan booking pengganti yang benar.

**Contoh C — Mahasiswa sudah terlanjur mengonfirmasi otomatis:** Booking23 November08.00–10.00 sudah approved. Permintaan beralih ke dosen ditangani SSC/Divisi Akademik: booking lama harus dipindahkan/dibatalkan secara jelas sebelum menautkan booking aktif lain. **Usulan teknis:** penandaan/penautan tidak diam-diam melepas booking yang sudah disahkan; tampilkan konflik dan minta tindakan petugas yang berwenang.

**Contoh D — Persetujuan TA dibatalkan:** Booking dosen yang tertaut pada pengajuan TA ikut dibatalkan sesuai T10, jika belum digunakan. Booking dosen lain yang tidak tertaut tidak ikut berubah. Riwayat penautan, persetujuan dan pembatalan tetap disimpan. Tab persetujuan peminjaman yang lama tidak boleh menghidupkan booking cancelled kembali.

**Batas jawaban:** Pengguna menyetujui penandaan/penautan oleh admin dan meminta contoh; urutan penandaan sebelum booking, penerimaan link pending, penjagaan tab lama, serta penanganan status selanjutnya merupakan rincian teknis yang diusulkan dalam110/111. Jangan menulisnya sebagai kutipan kebijakan divisi yang sudah dikonfirmasi.


## Pertanyaan
#### P01 — Aturan resmi durasi, jeda, hari, dan jam

**Pertanyaan:** Apakah satu booking mencakup 120 menit termasuk persiapan/pergantian mahasiswa, atau ada jeda tambahan? Apakah Senin–Jumat08.00–16.00, istirahat12.00–13.00 berlaku untuk semua prodi, termasuk Jumat? Bagaimana hari libur dan perubahan jam khusus?

**Contoh untuk dikonfirmasi:** Sidang A selesai 10.00. Apakah B boleh langsung mulai10.00 atau harus10.15? Apakah Jumat11.00–13.00 harus ditutup lebih panjang? Angka15 menit dan rentang Jumat tersebut hanya contoh pertanyaan.

**Jawaban saat ini:** Pengaturan T07 disetujui sementara; aturan resmi divisi belum ada. Usulan konfigurasi tidak otomatis membuat kalender hari libur tersedia.

#### P02 — Pemeriksaan pembimbing/penguji pada tahap berikutnya

**Pertanyaan:** Apakah versi berikutnya harus memastikan jadwal pembimbing/penguji kosong juga; dosen mana yang wajib hadir; apakah jadwal mengajar saja cukup; dan apa yang dilakukan jika data/jadwal dosen belum lengkap?

**Contoh:** Ruang kosong08.00–10.00 tetapi penguji mengajar09.30–11.30. Apakah hasil harus diblokir atau cukup diberi peringatan?

**Jawaban saat ini:** T03 sudah menetapkan versi awal ruangan saja. Pertanyaan ini tidak menghalangi rancangan versi awal.

#### P03 — Pengecualian pola mingguan dan batas semester

**Pertanyaan:** Siapa yang mengonfirmasi pertemuan online/asinkron/pindah hari sehingga ruang asal boleh dilepas untuk satu tanggal? Tanggal resmi semester mana yang menjadi batas pola `2627/1`? Apakah hari libur perlu pengecualian tersendiri?

**Contoh:** Dosen memindahkan kelas Senin23 November ke Selasa24 November dengan meminjam ruang baru. Apakah Senin23 November pada ruang asal langsung kosong? Data peminjaman baru belum membuktikan kelas asal dibatalkan.

**Jawaban saat ini:** Pengulangan sepanjang semester dikonfirmasi pada T04. Mekanisme pengecualian belum dijawab; usulan awal tetap menganggap ruang asal terpakai sampai ada pengecualian yang jelas. Periode akademik sudah memiliki batas tanggal, tetapi belum ditautkan room schedule.

#### P04 — Titik konfirmasi dan penyimpanan booking mahasiswa

**Pertanyaan:** Setelah mahasiswa memilih tanggal dan menekan Cari Jadwal, apakah ditampilkan satu hasil untuk dikonfirmasi, atau slot langsung dipesan saat pencarian?

**Contoh:** Hasil23 November08.00–10.00 di ruang B muncul. Apakah mahasiswa masih bisa membaca hasil dan menekan Konfirmasi Jadwal sebelum ruang ditahan? Jika orang lain lebih dahulu memesan saat hasil dibaca, apakah hasil baru ditawarkan untuk dikonfirmasi ulang?

**Jawaban saat ini:** **Sudah dijawab pada T08:** tampilkan hasil terlebih dahulu, lalu mahasiswa mengonfirmasi. Jika tidak cocok, dosen dapat menggunakan alur Peminjaman Ruangan biasa. Rincian pemeriksaan ulang saat konfirmasi tetap masuk rancangan teknis.

**Status pertanyaan:** Selesai dijawab setelah T07; dicatat sebagai T08. Pertanyaan konfirmasi pencarian tidak perlu diajukan ulang.

#### P05 — Ruang yang boleh dialokasikan untuk Sidang TA

**Pertanyaan:** Apakah semua ruang berstatus Dapat Dipakai boleh digunakan, atau hanya ruang/gedung/kampus tertentu dengan kapasitas dan fasilitas minimum? Bagaimana ruang yang baru dibuat otomatis dari impor dan datanya belum lengkap?

**Contoh:** Ruang A aktif berkapasitas40, ruang B aktif berkapasitas0 karena dibuat dari impor. Apakah B boleh ditawarkan atau harus dilengkapi/disetujui dulu?

**Jawaban saat ini:** **Sudah dijawab untuk rancangan sementara pada T09:** ruang kelas saja, tersedia, dan tidak bentrok. Pengguna tetap ingin mengonfirmasikannya kepada divisi. Ketentuan kapasitas/fasilitas/kampus serta pengenalan ruang hasil impor yang belum lengkap belum diputuskan. Berbeda69 label ruang JSON dan60 master Excel perlu dipetakan; jangan menganggap semua selisih sebagai ruang baru.

**Status pertanyaan:** Pertanyaan kelas/laboratorium selesai dijawab setelah T08; dicatat sebagai T09. Sisa konfirmasi kepada divisi: apakah batas ruang kelas berlaku umum, kapasitas/fasilitas minimum, dan pengecualian kampus/gedung. Master belum memiliki kategori eksplisit; contoh Excel memiliki60 nama kelas,46 aktif dan14 nonaktif. Jangan menebak kategori ruang baru yang belum jelas.

#### P06 — Pengubahan, pembatalan, dan jumlah booking aktif

**Pertanyaan:** Perubahan admin boleh mengganti tanggal, jam, dan ruang? Apakah mahasiswa boleh mengganti/membatalkan sendiri atau harus menghubungi petugas? Apakah satu pengajuan hanya boleh mempunyai satu booking aktif? Jika dosen membuat peminjaman alternatif, apakah perlu ditautkan ke pengajuan TA agar kartu mahasiswa menampilkan jadwal yang sama?

**Contoh:** Mahasiswa sudah memesan23 November08.00–10.00 lalu meminta pindah24 November. Apakah mahasiswa mengajukan permintaan atau petugas langsung memindahkan? Jika ruang baru bentrok, booking lama perlu tetap ada.

**Jawaban saat ini:** Admin boleh mengubah jika diperlukan (T01). Jika pratinjau tidak cocok, dosen boleh memesan alternatif (T08). **T11:** sesudah konfirmasi mahasiswa menghubungi SSC; Divisi Akademik yang berwenang mengubah/membatalkan. **T13:** admin menandai pengajuan dan menautkan booking alternatif secara eksplisit. Jumlah booking aktif tetap pilihan teknis asisten untuk ditinjau: satu per pengajuan/NIM. Pembatalan approved oleh admin sudah tersedia. Perpindahan diperiksa ulang dan dicatat; tidak menautkan berdasarkan teks tujuan yang mirip.

**Riwayat pertanyaan setelah T10:** Dijawab pada T11: perubahan melalui SSC/Divisi Akademik saja. Penautan alternatif kemudian dijawab pada T13: oleh admin dengan penanda pengajuan. Tidak perlu mengulang kedua pertanyaan pokok tersebut. Batas satu booking dan perilaku pemesanan ulang administratif dirinci dalam110/111 sebagai pilihan teknis untuk ditinjau.

#### P07 — Hubungan booking dengan pembatalan persetujuan dan perubahan periode

**Pertanyaan:** Jika persetujuan TA dibatalkan untuk diperiksa ulang, apakah booking ikut dibatalkan otomatis atau dipertahankan sampai petugas memutuskan? Jika rentang pelaksanaan dipersempit, bagaimana booking yang berada di luar rentang baru?

**Contoh:** Booking27 November sudah approved, lalu rentang pelaksanaan diubah menjadi23–26 November. Apakah perubahan ditolak sampai booking dipindahkan, atau petugas diberi daftar booking yang harus ditangani?

**Jawaban saat ini:** **Sudah dijawab sementara pada T10:** saat persetujuan TA dibatalkan sebelum pelaksanaan, booking terkait ikut dibatalkan otomatis dan riwayat tetap disimpan. Pengguna meminta ini juga dicatat sebagai konfirmasi lanjutan kepada divisi. Kasus mempersempit rentang pelaksanaan dan booking yang sudah berlangsung belum diputuskan.

**Riwayat pertanyaan setelah T09:** Diajukan pilihan booking otomatis cancelled dengan riwayat tersimpan, atau dipertahankan sampai dibatalkan terpisah oleh petugas. Pengguna memilih pembatalan otomatis dan meminta dicatat untuk konfirmasi divisi. Pertanyaan pokok tidak perlu diajukan ulang kepada pengguna; pertanyaan resmi divisi tetap dipertahankan pada T10. Ini belum menjawab kasus mempersempit rentang pelaksanaan.

#### P08 — Hak akses pengelolaan dan koreksi booking

**Pertanyaan:** Siapa boleh mengelola tanggal/jam pelaksanaan, mengubah booking, serta membatalkannya? Apakah cukup permission pengelola periode/reviewer TA, atau perlu aksi khusus?

**Contoh:** Dosen memiliki hak baca pengajuan prodinya tetapi bukan reviewer. Ia boleh melihat jadwal mahasiswa, tetapi apakah boleh memindahkan ruang? Usulan: hak baca tidak memberi hak pengubahan.

**Jawaban saat ini:** **T11 menetapkan Divisi Akademik melalui admin/petugas SSC** sebagai pihak perubahan/pembatalan setelah konfirmasi. Pemetaan ke permission aplikasi untuk pengelolaan jadwal/koreksi masih perlu dirinci dalam rancangan. Istilah Divisi Akademik tidak otomatis dianggap sama dengan setiap pemilik role admin atau role aplikasi bernama Akademik. Hak baca prodi dosen tidak otomatis memberi hak mutasi. Tidak mereset hak akses yang sudah berjalan.

#### P09 — Pengajuan approved sebelum fitur dipasang dan approval historis

**Pertanyaan:** Apakah seluruh pengajuan approved pada periode yang memiliki jadwal pelaksanaan boleh langsung booking, termasuk yang disetujui sebelum pemasangan fitur? Jika satu mahasiswa punya approval lama dan baru, mana yang boleh dijadwalkan?

**Contoh:** Mahasiswa approved minggu lalu dan belum pernah booking. Kartu seharusnya dapat memakai pengajuan tersebut setelah jadwal dilengkapi, tanpa mengirim ulang berkas. Bagaimana jika juga terdapat approval periode sebelumnya?

**Jawaban saat ini:** Kebutuhan awal menyebut mahasiswa yang sudah approved. Detail pemilihan beberapa approval historis belum dijawab; dukungan pengajuan yang sudah ada perlu masuk rancangan migrasi/aktivasi.

#### P10 — Impor jadwal baru bertabrakan dengan booking yang sudah disahkan

**Pertanyaan:** Jika pola perkuliahan diperbarui dan jadwal baru memakai ruang/jam yang sudah dipesan, apakah impor ditahan agar petugas menyelesaikan bentrok, atau diterima dengan daftar bentrok yang wajib ditangani? Siapa yang memindahkan booking?

**Contoh:** Booking TA di ruang B23 November08.00–10.00 sudah disahkan. Impor perkuliahan baru menempatkan kelas di ruang B setiap Senin08.30–10.30. Kedua jadwal berbenturan.

**Jawaban saat ini:** Belum dijawab. Tidak membatalkan booking otomatis atau mengabaikan bentrok tanpa keputusan yang jelas.

#### P11 — Data penguji dan pencocokan kode dosen yang belum lengkap

**Pertanyaan:** Pada JSON profil, apakah penguji kosong karena memang belum ditetapkan sebelum penjadwalan, atau export belum mengambil data yang sudah ada? Pada tahap apa penguji tersedia dan dari menu/field apa diekspor? Bagaimana melengkapi master kode dosen pembimbing/penguji yang belum cocok, termasuk dosen eksternal?

**Contoh:** Pada contoh277 baris, penguji1/2 masing-masing baru terisi6 baris. Apakah271 baris kosong wajar karena belum ditetapkan, sehingga halaman perlu menampilkan Belum tersedia? Jika seharusnya sudah ada, diperlukan contoh tersamarkan yang tetap menautkan NIM dengan kode penguji. Untuk7 isian pembimbing kedua yang tidak cocok master, kode tetap dapat ditampilkan, tetapi nama/record master perlu diverifikasi.

**Jawaban saat ini:** Belum dijawab. User meminta data yang kurang dilaporkan; hasil cek file sudah dicatat pada bagian5. Perlu konfirmasi sumber, bukan membuat kode/nama pengganti. Pemeriksaan jadwal tetap ruangan saja. JSON kehadiran juga masih menunggu pengguna dan merupakan kebutuhan sumber yang berbeda.

#### P12 — Pilihan cara menerapkan pola mingguan pada integrasi

**Pertanyaan:** Untuk rancangan integrasi, apakah menyimpan pola mingguan per semester lalu menghitungnya saat pencarian, atau membuat jadwal untuk seluruh tanggal semester saat impor?

**Pilihan pertama — rekomendasi asisten:** Simpan pola mingguan yang terikat semester dan batas tanggalnya, lalu perluas untuk tanggal yang sedang diperiksa. Sidang TA dan Peminjaman Ruangan harus memakai perhitungan yang sama. Tidak menggandakan setiap pola menjadi banyak record tanggal; membutuhkan penyesuaian seluruh jalur pemeriksaan ketersediaan yang relevan agar konsisten.

**Pilihan kedua:** Bentuk record pemakaian setiap tanggal sampai batas semester saat impor. Pemeriksaan tanggalan yang sudah ada lebih mudah dipertahankan; jumlah record bertambah dan perubahan pola/batas semester memerlukan pembaruan hasil pembentukannya. Ini juga harus menjaga booking internal dan riwayat.

**Contoh:** Pola ruang A setiap Senin08.30–10.30 pada semester Ganjil2026/2027. Pilihan pertama menentukan bahwa Senin23 November terpakai ketika tanggal itu diperiksa. Pilihan kedua membuat record23 November beserta Senin lain ketika impor berjalan. Keduanya harus menghasilkan bentrok yang sama dan tidak meneruskan pola ke semester berikutnya.

**Jawaban saat ini:** **Sudah dijawab pada T12:** memakai pilihan pertama, pola mingguan per semester dihitung saat pencarian. Pilihan ini teknis dan tidak perlu ditanyakan ulang kepada divisi. Rancangan lengkap ditulis pada dokumen110 untuk ditinjau; kode belum diubah.

#### P13 — Mencegah booking ganda melalui alternatif dosen dan jalur otomatis

**Pertanyaan:** Jika dosen menangani peminjaman alternatif, apakah admin perlu menandai pengajuan TA “Dijadwalkan melalui dosen” dan menautkan booking agar mahasiswa tidak lagi mengonfirmasi booking otomatis, atau cukup koordinasi manual pada versi awal?

**Contoh:** Dosen memesan ruang24 November untuk mahasiswa yang sama. Mahasiswa masih melihat hasil otomatis23 November dan mengonfirmasi. Tanpa hubungan/penanda, dua ruang tertahan untuk satu sidang, meskipun aturan satu booking TA aktif tidak dilanggar karena peminjaman dosen belum tertaut.

**Rekomendasi asisten:** Penanda dan penautan oleh admin agar jalur otomatis dihentikan untuk pengajuan yang ditangani melalui dosen. Cara menandai sebelum/selama pemesanan, status peminjaman yang boleh ditautkan, dan konsistensi perubahan/cancel akan dirinci bila pilihan ini dipakai. Jangan menautkan otomatis dari kemiripan teks/NIM pada keterangan. Alternatif: koordinasi manual saja, dengan keterbatasan bahwa aplikasi belum menjamin bebas booking ganda lintasjalur untuk sidang yang sama.

**Jawaban saat ini:** **Sudah dijawab pada T13:** admin menandai pengajuan dan menautkan peminjaman dosen sesuai rekomendasi. Pengguna meminta pencatatan, contoh kasus, dan melanjutkan pekerjaan. Bagian alternatif pada110 diperbarui; rincian teknis dan pengujiannya masuk111. Tidak perlu mengulang pertanyaan pokok ini.
