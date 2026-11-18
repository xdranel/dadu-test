# 109 — Rescan dan catatan awal jadwal pelaksanaan Sidang TA

Tanggal: 15 November 2026, Asia/Jakarta.

Status: **pengumpulan kebutuhan dan penyusunan rancangan; belum implementasi**. Dokumen ini mencatat kondisi sebelum perencanaan, temuan pemeriksaan, keputusan pengguna, dan hal yang perlu dikonfirmasi. Belum merupakan persetujuan untuk mengubah kode aplikasi.

## 1. Urutan dokumentasi pekerjaan

1. Dokumen109 ini: rescan sebelum perencanaan, sumber referensi, keputusan/pertanyaan, serta pemeriksaan ulang setelah pencatatan.
2. [Dokumen110 — Rancangan integrasi jadwal Sidang TA dan peminjaman ruangan](110-rancangan-integrasi-jadwal-sidang-ta-dan-peminjaman-ruangan.md): rancangan lengkap setelah pilihan integrasi dan kebutuhan pokok diperjelas. Rancangan ditinjau pengguna sebelum implementasi.
3. [Dokumen111 — Rencana implementasi](111-rencana-implementasi-jadwal-sidang-ta-dan-peminjaman-ruangan.md): rincian tugas, file, interface dan pengujian berdasarkan rancangan110, setelah jawaban penautan alternatif dosen diterima.
4. Dokumen hasil implementasi terpisah sesudahnya: ditautkan ke rancangan/rencana; berisi file baru/berubah, perubahan skema, hasil pengujian/rescan, serta langkah salin/update yang benar-benar sesuai implementasi. Dokumen hasil belum dibuat karena kode belum dikerjakan.

Pada pemeriksaan awal, dokumen110 belum dibuat. Setelah pendekatan pola mingguan disetujui pada T12, rancangan110 disusun. Setelah T13 menjawab penautan oleh admin, rancangan diperbarui dan rencana111 disusun. Dokumen hasil implementasi tetap terpisah dan belum dibuat; nomor berikutnya disesuaikan ketika tahap implementasi dimulai.

## 2. Kondisi sebelum perencanaan

| Folder | Branch | HEAD saat rescan awal | Perubahan lokal awal |
| --- | --- | --- | --- |
| `/home/xdranel/Code/Project/secret/dadu-test` | `main` | `57d06d7aa87fbb87b1dbb05a0a488ef2c2321dc8` | Bersih |
| `/home/xdranel/Code/Project/php-laravel/dadu` | `tugasAkhir` | `1c83b32d5f7fd9547666ecf300e0cba6f113e208` | `.gitignore`, `package-lock.json`, `phpunit.xml` sudah berubah sebelum pekerjaan ini |

HEAD `/dadu` memuat merge branch `peminjamanRuangan`. Kode room booking belum tersedia di `/dadu-test`. Kode inti Sidang TA yang diperiksa cocok pada kedua folder, termasuk pembatalan persetujuan107 dan pembukaan kembali108. Perbedaan antarfolder juga mencakup SSO, antrean, dokumen, permission, layout, dan modul lain; **bukan seluruh perbedaan merupakan kebutuhan integrasi ini**. Cache bootstrap hasil runtime bukan berkas yang perlu disalin.

Pemeriksaan awal mencatat hash isi berkas pada direktori aplikasi, konfigurasi, database, tampilan, route, pengujian, dan Docker agar perubahan selama pencatatan dapat dibandingkan. Tidak ada stash yang dibuka, branch dipindahkan, impor dijalankan, database diubah, atau deployment dilakukan.

## 3. Kebutuhan yang sudah disampaikan pengguna

### Mahasiswa

- Pada halaman **Pendaftaran Sidang TA**, tampilkan kartu penjadwalan di samping **Draft tersimpan** hanya untuk pengajuan Sidang TA yang sudah **Disetujui**.
- Tombol membuka halaman terpisah untuk memilih tanggal dan mencari jadwal.
- Mahasiswa memilih tanggal saja; tidak memilih ruang, kelas, atau jam.
- Sistem memilih jam paling awal yang memiliki ruang tersedia pada hari itu, dengan membandingkan seluruh ruang yang memenuhi syarat.
- Untuk rancangan sementara, kandidat hanya **ruang kelas**, berstatus Dapat Dipakai, dan kosong tanpa bentrok sepanjang sesi. Laboratorium/ruang lain tidak menjadi kandidat. Aturan ruang kelas saja tetap dicatat untuk dikonfirmasi kepada divisi.
- Peminjaman hasil penjadwalan terhubung ke fitur Peminjaman Ruangan sehingga slot yang sudah diambil tidak dapat dipakai pihak lain pada waktu yang sama.

### Admin/petugas

- Pada Review Sidang TA, kartu **Kelola Tanggal Pelaksanaan Sidang** berada di samping kartu **Kelola Periode**.
- Informasi periode aktif diletakkan di bawah kedua kartu pengelolaan tersebut.
- Jadwal pelaksanaan sidang merupakan jadwal tersendiri, terpisah dari pendaftaran dan Verifikasi Berkas.
- Admin/petugas mengatur **rentang tanggal mulai–selesai untuk masing-masing periode TA**, misalnya23–27 November. Mahasiswa hanya dapat memilih tanggal di dalam rentang tersebut.

### Keputusan lanjutan dalam percakapan

1. **Peminjaman Sidang TA langsung disahkan setelah mahasiswa mengonfirmasi hasil pencarian**, tanpa menunggu persetujuan petugas peminjaman ruangan. Admin tetap dapat mengubahnya bila diperlukan. Rincian perubahan/pembatalan masih perlu ditentukan.
2. Durasi belum dikonfirmasi kepada divisi. Pengguna awalnya mengizinkan patokan sementara **1–2 jam**, kemudian menyetujui rekomendasi **120 menit** beserta pengaturan hari/jam sebagai rancangan sementara. Durasi dapat disesuaikan petugas pada pengaturan pelaksanaan per periode TA. Durasi dan jeda pergantian resmi tetap perlu dikonfirmasi kepada divisi.
3. **Versi awal memeriksa ketersediaan ruangan saja.** Jadwal mengajar/ketersediaan pembimbing maupun penguji belum menjadi syarat pemilihan slot. Pemeriksaan dosen ditunda untuk konfirmasi lanjutan kepada divisi; keberadaan `kode_dosen` dalam JSON tidak otomatis mengaktifkan pemeriksaan bentrok dosen. Jadwal yang ditemukan hanya menyatakan ruangan tersedia, belum menjamin dosen dapat hadir.
4. **Jadwal `TETAP` pada JSON9–15 November merupakan pola mingguan yang berulang sepanjang semester yang sama.** Semester berikutnya memakai jadwal impor untuk semester berikutnya. Pola tidak boleh diteruskan tanpa batas ke semester lain. Pengguna menjelaskan bahwa perubahan sesekali dapat berupa online, asinkron, atau pindah hari; peminjaman pengganti dosen dapat masuk melalui fitur Peminjaman Ruangan.
5. Perubahan pada fitur Peminjaman Ruangan teman **diizinkan sepanjang relevan dengan integrasi pencarian jadwal Sidang TA**. Alur peminjaman biasa, termasuk dosen membatalkan pengajuan yang masih pending, dipertahankan. Jika pembatalan oleh admin untuk peminjaman approved diperlukan, periksa dan gunakan yang sudah tersedia sebelum menambahkan implementasi baru.
6. **Alur pemakaian mahasiswa dan admin harus berada di dokumen yang sama dengan catatan pertanyaan konfirmasi**, yaitu dokumen109 ini. Saat rancangan/implementasi berikutnya dibuat terpisah dan saling ditautkan, alur pada dokumen ini tetap diperbarui sebagai panduan bersama.
7. **Rentang pelaksanaan disimpan per periode TA**, dengan tanggal mulai dan selesai seperti23–27 November. Kedua tanggal batas termasuk tanggal yang dapat dipilih, selama memenuhi aturan hari/jam dan ketersediaan ruang yang nanti disepakati. Rentang ini tidak mengganti jadwal pendaftaran maupun Verifikasi Berkas.
8. **Pengaturan awal disetujui untuk rancangan sementara:** Senin–Jumat,08.00–16.00 WIB, istirahat12.00–13.00 tanpa sesi yang melewatinya, durasi120 menit. Pengaturan dapat disesuaikan petugas per periode TA. Persetujuan ini belum merupakan ketentuan resmi divisi dan tidak mengizinkan langsung memulai implementasi sebelum rancangan ditinjau.
9. Seluruh pertanyaan dan jawaban pengguna dicatat dengan contoh agar dapat dibaca atau ditanyakan kembali kepada divisi. Bagian6 membedakan jawaban yang sudah diterima, keputusan sementara, dan pertanyaan yang belum dijawab; usulan asisten tidak dicatat sebagai jawaban pengguna.
10. **Cari Jadwal menampilkan hasil terlebih dahulu.** Mahasiswa kemudian dapat mengonfirmasi. Jika tidak menyukai jadwal tersebut, mahasiswa dapat meninggalkan hasil tanpa memesan; dosennya dapat melakukan peminjaman alternatif melalui fitur Peminjaman Ruangan dengan alur dosen yang sudah ada.
11. **Informasi harus ditampilkan saat mencari maupun mengonfirmasi jadwal:** ID, NIM, nama, tanggal sidang, serta kode dosen pembimbing dan penguji. Penafsiran awal label ID adalah **ID Pengajuan TA**, karena ID booking baru ada setelah pemesanan disimpan; ini pilihan rancangan yang perlu terlihat jelas saat ditinjau. Rincian sumber dan data yang belum tersedia dicatat di bawah.
12. Data yang memang belum tersedia harus dilaporkan kepada pengguna dengan sumber dan contohnya. Bedakan data tidak ada pada file, data ada tetapi importer belum membaca, dan data tidak cocok dengan master. **JSON kehadiran masih menunggu** dan belum diberikan; tidak mengarang data pengganti.
13. **Ruang kelas saja**, yang available dan tidak bentrok. Pengguna meminta jawaban ini dicatat agar dapat dikonfirmasi kembali. Statusnya keputusan rancangan sementara dari pengguna, belum kebijakan resmi divisi. Status Dapat Dipakai bukan bukti slot kosong; tetap periksa pola perkuliahan dan peminjaman internal sepanjang durasi.
14. **Jika persetujuan TA dibatalkan sebelum pelaksanaan, booking TA terkait ikut dibatalkan otomatis; riwayat tetap disimpan.** Pengguna memilih perilaku tersebut dan meminta agar dicatat sebagai bahan konfirmasi juga. Statusnya keputusan rancangan sementara, belum ketentuan resmi divisi. Tidak otomatis membatalkan peminjaman dosen lain yang tidak tertaut pengajuan tersebut.
15. **Setelah Konfirmasi Jadwal, mahasiswa tidak boleh mengubah maupun membatalkan booking sendiri.** Mahasiswa menghubungi admin/petugas di SSC; **Divisi Akademik** yang melakukan perubahan sesuai hak akses. Pembatasan harus berlaku pada tampilan dan endpoint, bukan hanya menyembunyikan tombol. Ini tidak mengubah alur dosen membatalkan pengajuan peminjaman biasa yang masih pending.
16. **Pendekatan integrasi dipilih:** simpan pola mingguan per semester dan hitung saat pencarian/pemeriksaan tanggal. Sidang TA dan Peminjaman Ruangan menggunakan pemeriksaan ketersediaan yang sama. Pilihan membuat seluruh record tanggal semester saat impor tidak dipakai. Ini keputusan pendekatan, belum persetujuan atas seluruh rincian rancangan110 atau instruksi mulai kode.
17. **Alternatif dosen ditandai dan ditautkan oleh admin**, sesuai rekomendasi yang dipilih pengguna. Pengajuan bertanda Dijadwalkan melalui dosen tidak lagi menerima konfirmasi otomatis mahasiswa. Hubungan booking dipilih secara eksplisit oleh admin, tidak ditebak dari nama/NIM dalam keterangan. Urutan dan penanganan pending/cancel dirinci sebagai pilihan teknis dalam rancangan110 dan rencana111.

### Alur pemakaian mahasiswa — rancangan awal

Alur berikut menjelaskan kebutuhan yang sudah diketahui. Langkah bertanda **usulan** masih perlu ditinjau; fitur belum tersedia di aplikasi.

1. Mahasiswa mengirim pendaftaran Sidang TA dan menunggu hasil pemeriksaan seperti sekarang.
2. Setelah pengajuan **Disetujui**, kartu **Jadwal Pelaksanaan Sidang TA** muncul di samping **Draft tersimpan** pada halaman Pendaftaran Sidang TA. Pengajuan menunggu, revisi, atau ditolak tidak memperoleh hak booking.
3. Mahasiswa membuka halaman pencarian melalui tombol pada kartu tersebut. Tampilkan ID pengajuan, NIM, nama, serta kode pembimbing/penguji yang tersedia. Hak akses tetap diperiksa di server berdasarkan pemilik pengajuan dan status persetujuan terkini.
4. Mahasiswa memilih **tanggal pelaksanaan dalam rentang mulai–selesai periode TA pengajuannya**, lalu menekan **Cari Jadwal**. Contoh23–27 November: tanggal di luar rentang itu tidak dapat dipesan, termasuk melalui request langsung. Mahasiswa tidak mengisi pilihan ruang, kelas, maupun jam. Pengaturan sementara: Senin–Jumat08.00–16.00 WIB, istirahat12.00–13.00, durasi120 menit.
5. Sistem menyeleksi **ruang kelas berstatus Dapat Dipakai**, lalu mencari celah yang cukup untuk durasi sidang, membandingkan waktu mulai kandidat, dan mengambil waktu paling awal pada tanggal pilihan. Jadwal perkuliahan mingguan pada semester tersebut serta peminjaman internal `pending`/`approved` dianggap terpakai. Laboratorium tidak ikut dipilih walaupun kosong. Pada versi awal, ketersediaan dosen belum diperiksa.
6. Jika tidak tersedia slot yang cukup, tampilkan keterangan bahwa tanggal itu penuh/tidak tersedia; mahasiswa memilih tanggal lain. Jangan memesan tanggal lain secara diam-diam.
7. **Sudah dikonfirmasi pengguna:** tampilkan hasil terlebih dahulu, berisi tanggal, jam mulai–selesai, ruang, dan durasi, lalu tombol **Konfirmasi Jadwal**. Tampilkan kembali ID pengajuan, NIM, nama, tanggal sidang, serta kode pembimbing/penguji pada hasil/konfirmasi. Cari Jadwal belum menahan ruang. Rancangan pemeriksaan saat konfirmasi: server memeriksa ulang persetujuan, tanggal yang diizinkan, dan bentrok; hasil yang berubah perlu ditampilkan kembali untuk dikonfirmasi, bukan mengganti jadwal tanpa diketahui mahasiswa.
8. Saat pemesanan berhasil, peminjaman Sidang TA **langsung disahkan**, tanpa antre persetujuan peminjaman biasa. Slot tersimpan pada sumber peminjaman bersama sehingga peminjam lain tidak mendapat ruang/jam yang sama.
9. Kartu mahasiswa menampilkan hasil booking dan statusnya. **Usulan:** satu booking aktif per pengajuan; klik berulang tidak membuat booking ganda. Setelah booking dikonfirmasi, mahasiswa tidak memperoleh tombol atau hak endpoint untuk mengubah/membatalkannya sendiri.
10. Jika perlu mengganti atau membatalkan booking yang sudah dikonfirmasi, mahasiswa **menghubungi admin/petugas di SSC**. **Divisi Akademik** melakukan perubahan sesuai hak akses; mahasiswa melihat hasil/status terbaru. Tidak diperlukan form permintaan perubahan baru di aplikasi karena pengguna menyebut jalur kontak SSC, bukan meminta modul permintaan baru.
11. Jika hasil pencarian tidak cocok **sebelum konfirmasi**, mahasiswa tidak perlu mengonfirmasi. Dosen dapat memesan alternatif lewat fitur Peminjaman Ruangan biasa. **Admin menandai pengajuan dan menautkan booking alternatif** sesuai T13; jalur otomatis dihentikan. Jika booking TA terlanjur disahkan, perubahan/pembatalannya tetap melalui Divisi Akademik di SSC. Alur pending/approved peminjaman dosen dipertahankan; rincian rancangan terdapat pada110 bagian10.
12. Jika persetujuan TA dibatalkan sebelum pelaksanaan, booking TA terkait menjadi Dibatalkan dan ruang kembali tersedia. Riwayat tetap dapat ditelusuri. Bila pengajuan disetujui kembali, rancangan tidak menjamin slot lama masih kosong; jadwal perlu dicari/dikonfirmasi lagi. Konsekuensi ini ikut dicatat untuk konfirmasi divisi pada P07.

### Informasi pada pencarian dan konfirmasi jadwal

Informasi berasal dari data pengajuan/akademik dan hasil pencarian; mahasiswa tetap hanya memilih tanggal. Tabel ini adalah rancangan tampilan, belum perubahan kode.

| Informasi | Sumber/rancangan | Jika belum tersedia |
| --- | --- | --- |
| ID Pengajuan TA | ID pengajuan approved yang sedang dijadwalkan; penafsiran awal untuk permintaan “ID” | Tidak membuat ID booking sementara; ID booking baru tampil setelah tersimpan |
| NIM dan nama | Identitas pengajuan milik mahasiswa yang sudah disetujui | Tidak mengambil identitas mahasiswa lain atau menerima identitas dari input bebas |
| Tanggal sidang | Tanggal yang dipilih untuk pencarian dan ditawarkan dalam hasil booking | Sebelum memilih tanggal: “Belum dipilih”; tidak mengambil tanggal sidang historis dari profil sebagai pengganti |
| Kode pembimbing | Data TA `advisor_1`/`advisor_2` dari impor `pembimbing_1`/`pembimbing_2`; cocokkan dengan master dosen untuk nama bila tersedia | Kode sumber yang sudah ada tetap ditampilkan. Jika kode tidak cocok master, nama ditandai belum tersedia/terkonfirmasi. Jika sumber kosong, tampilkan “Belum tersedia” |
| Kode penguji | Data TA penguji yang sudah tersedia pada profil, dari impor `penguji_1`/`penguji_2` | “Belum tersedia” jika kosong; jangan menebak penguji dari dosen pengampu ruangan |
| Jam mulai–selesai, ruang, durasi | Hasil pencarian otomatis; tidak menjadi pilihan bebas mahasiswa | Tampilkan keterangan jika belum mencari atau tidak ada slot |

Kode dosen pada jadwal perkuliahan menyatakan dosen pengampu kelas tersebut; field itu tidak membuktikan ia pembimbing/penguji mahasiswa. Rancangan tetap memeriksa bentrok ruang saja sesuai T03. Data dosen kosong tidak otomatis menjadi syarat blokir baru. Jika nilai pada profil berupa nama, bukan kode seperti data dummy, gunakan kecocokan master yang jelas; jangan membuat kode atau memasangkan orang yang ambigu.

### Alur pemakaian admin/petugas — rancangan awal

1. Siapkan Data Akademik → Ruangan dan impor jadwal perkuliahan melalui IT Panel → Sinkronisasi → **Jadwal Ruangan iGracias (`room_schedule`)** sesuai hak akses. Jika impor master dosen tetap diperlukan, perhatikan ketidakcocokan header Excel yang dicatat pada bagian5; data dosen belum menjadi syarat pencarian slot versi awal.
2. **Usulan integrasi:** kaitkan pola mingguan dengan periode akademik yang sesuai (`2627/1` berarti Ganjil2026/2027) dan tanggal batas semester. Jadwal semester lain tidak boleh otomatis dipakai sebagai acuan. Cara penyimpanan/pengulangannya belum dipilih; lihat temuan dan pertanyaan konfirmasi.
3. Buka Review Sidang TA. Kartu **Kelola Periode** dan **Kelola Tanggal Pelaksanaan Sidang** tampil berdampingan; informasi periode aktif berada di bawah keduanya. Pada layar sempit, kartu dapat tersusun vertikal.
4. Pilih periode TA yang dikelola dan isi **tanggal mulai serta tanggal selesai pelaksanaan**, misalnya23–27 November. Rentang tersebut berlaku untuk pengajuan yang terhubung ke periode TA itu. Ini tidak mengubah tanggal pendaftaran atau Verifikasi Berkas. Pengaturan awal yang disetujui sementara: Senin–Jumat08.00–16.00 WIB, istirahat12.00–13.00, durasi120 menit; petugas dapat menyesuaikannya per periode TA setelah aturan resmi diketahui.
5. Periksa dan setujui berkas Sidang TA seperti sekarang. Persetujuan yang sudah ada sebelum fitur dipasang perlu diperhitungkan; tidak boleh mengharuskan mahasiswa mengirim ulang berkas hanya untuk memperoleh kartu booking. Pemilihan approval historis yang berhak masih perlu dirinci.
6. Mahasiswa mencari dan memesan jadwal. Booking hasil Sidang TA langsung approved dan ikut menahan slot pada Peminjaman Ruangan. **Usulan tampilan:** booking ditandai asal Sidang TA serta identitas NIM/nama agar dapat dibedakan dari peminjaman dosen.
7. Jika mahasiswa meminta perubahan booking yang sudah dikonfirmasi, ia menghubungi admin/petugas **SSC**; **Divisi Akademik** yang berwenang menggunakan tindakan perubahan/pembatalan. **Pembatalan approved sudah ada di kode `/dadu`**: tombol **Batalkan Peminjaman**, alasan wajib, dan riwayat status. Rencana integrasi memakai jalur ini; pemetaan permission untuk petugas Akademik tetap perlu dirinci tanpa otomatis memberi hak kepada seluruh pemilik role admin. Fitur mengganti tanggal/jam/ruang belum tersedia dan akan dirancang sesuai kebutuhan TA.
8. Perubahan waktu/ruang **usulan** wajib memeriksa bentrok kembali dan menyimpan perubahan secara utuh; jika hasil baru bentrok, booking lama jangan terlepas hanya karena pemindahan gagal. Pembatalan melepas slot tanpa menghapus riwayat.
9. Impor pola semester berikutnya dilakukan untuk semester berikutnya. Booking internal dan riwayat mahasiswa tidak ikut dihapus. Penanganan perubahan jadwal perkuliahan yang berbenturan dengan booking yang sudah disahkan harus diperjelas sebelum implementasi.
10. Ketika membatalkan persetujuan TA sebelum pelaksanaan, admin diberi informasi bahwa booking TA terkait ikut dibatalkan otomatis. Slot dilepas dan riwayat persetujuan/booking dipertahankan. Penyampaian dampak sebelum konfirmasi dan pencatatan perubahan menjadi bagian rancangan teknis; aturan otomatisnya sudah dipilih sementara pada T10 dan masih menjadi bahan konfirmasi divisi.
11. Untuk alternatif dosen, petugas **menandai pengajuan lebih dahulu**, kemudian menautkan ID peminjaman dosen yang benar. Penandaan mencegah konfirmasi dari pratinjau/tab lama. Booking pending tetap pending; jika disetujui, jadwal muncul sebagai terkonfirmasi. Jika pending ditolak/dibatalkan, pengajuan tetap ditangani melalui petugas, tanpa membuka jalur otomatis mahasiswa diam-diam. Ini rincian teknis yang diusulkan untuk menjalankan T13, dengan contoh di bawah.

**Batas scope:** tidak mengubah alur dosen membatalkan pengajuan pending atau menambahkan fitur peminjaman lain yang tidak dibutuhkan Sidang TA. Pemeriksaan dosen, penyesuaian online/asinkron, aturan durasi resmi, dan keputusan lain yang belum jelas tetap masuk daftar konfirmasi di bagian6.

### Pengaturan sementara yang disetujui dan kesesuaian JSON

Pengguna meminta rekomendasi yang tetap mengikuti struktur data/JSON, kemudian menjawab **“sesuai”** atas rekomendasi berikut. Statusnya disetujui sebagai rancangan sementara; aturan resmi tetap perlu dikonfirmasi kepada divisi.

| Pengaturan Sidang TA | Nilai awal | Status |
| --- | --- | --- |
| Tanggal | Di dalam rentang per periode TA, misalnya23–27 November | Sudah dikonfirmasi |
| Hari | Senin–Jumat | Disetujui sementara; aturan resmi perlu konfirmasi divisi |
| Jam | 08.00–16.00 WIB | Disetujui sementara; aturan resmi perlu konfirmasi divisi |
| Istirahat | 12.00–13.00 WIB; sesi tidak boleh melewatinya | Disetujui sementara; aturan resmi perlu konfirmasi divisi |
| Durasi | 120 menit per booking | Disetujui sementara; durasi/jeda resmi perlu konfirmasi divisi |
| Penyesuaian | Dapat disesuaikan petugas pada pengaturan pelaksanaan per periode TA | Disetujui untuk rancangan sementara |

Alasan rekomendasi: membatasi hasil otomatis pada jam siang yang lebih mudah ditinjau petugas dan menghindari sistem memilih06.30 atau malam sebagai hasil paling awal yang tersedia. Ini pertimbangan rancangan, **bukan aturan kampus yang sudah diketahui**. Hari libur, ketentuan Jumat, serta durasi/jeda resmi tetap perlu ditanyakan kepada divisi. Jeda tambahan di antara dua booking belum ditetapkan; usulan istirahat tengah hari tidak otomatis menentukan jeda pergantian mahasiswa.

**Struktur JSON referensi tetap dapat digunakan**, tanpa meminta perubahan nama field:

- `periode: 2627/1` menautkan pola ke semester Ganjil2026/2027; batas awal–akhir semester perlu memakai periode akademik yang sesuai.
- `hari` dan `tanggal` menunjukkan hari pola mingguan; keduanya perlu divalidasi agar konsisten. Dengan konfirmasi pengguna, `jenis_jadwal: TETAP` diperlakukan berulang pada hari yang sama sepanjang semester tersebut.
- `ruangan` tetap dicocokkan dengan master ruang. `jam_mulai` dan `jam_selesai` tetap menyatakan interval ruang terpakai.
- Contoh tanggal Senin23 November memakai pola Senin9 November pada semester yang sama. Peminjaman internal pada23 November juga diperiksa, termasuk status `pending` dan `approved`.
- Jadwal impor lengkap tetap disimpan/dibaca, termasuk Sabtu/Minggu dan jam di luar usulan jam Sidang TA. Batas08.00–16.00 dan Senin–Jumat hanya membatasi pencarian Sidang TA; tidak menghapus jadwal perkuliahan atau membatasi alur dosen meminjam ruangan.
- Hasil pencarian adalah celah kosong **berurutan sepanjang durasi** di dalam jam sidang. Celah satu jam belum cukup untuk durasi120 menit. Bila ruang baru kosong09.30, hasil09.30–11.30 dapat dipakai; tidak perlu membulatkan diam-diam ke jam bulat atau membentuk sesi tetap08–10/10–12.
- Bandingkan kandidat seluruh ruang yang memenuhi syarat lalu ambil waktu mulai paling awal. Contoh ilustrasi: A baru kosong09.30–11.30, B kosong08.00–10.00; pilih B08.00–10.00. Jika waktu sama, usulan memakai urutan kode ruang lalu ID secara konsisten. Contoh ini bukan hasil pencarian pada database nyata.

Jam/hari sidang merupakan pengaturan layanan yang terpisah dari interval pemakaian dalam JSON. Pada file referensi, jadwal terawal06.30 dan paling akhir selesai23.30, sedangkan kalender peminjaman saat ini memakai06.30–21.30. Perbedaan itu dicatat; tidak otomatis memperluas jam Sidang TA maupun mengubah jam peminjaman teman.

## 4. Referensi yang sudah dibaca

### Gambar jadwal

`/home/xdranel/Pictures/Screenshots/Screenshot_20261109_091214.png`

Contoh pada gambar membedakan:

- Pendaftaran Sidang TA: 9–11 November 2026.
- Verifikasi Berkas Pendaftaran Sidang: 12–20 November 2026.
- Pelaksanaan Sidang: **23–27 November 2026**.
- Periode Revisi pascasidang: 30 November–4 Desember 2026.

Tanggal pada gambar adalah referensi, belum diisikan ke database. Periode revisi pascasidang pada gambar tidak otomatis disamakan dengan Verifikasi Berkas pendaftaran yang sudah ada di aplikasi.

### Folder sumber

`/home/xdranel/Downloads/data_dadu/peminjamana_ruangan/`

| Berkas | Hasil pemeriksaan baca saja | Jalur penggunaan |
| --- | --- | --- |
| `DataRuangan (2).xlsx` | 60 record ruang; 46 `Aktif`, 14 `Non Aktif`; dua gedung; seluruh kapasitas positif | Data Akademik → Ruangan → Impor |
| `Master Pegawai.xlsx` | 155 record; kolom `Kode Dosen Baru` terisi; 5 record tanpa email | Data Akademik → Dosen → Impor |
| `rekap_igracias_2026-11-13 JADWAL PERKULIAHAN.json` | 827 jadwal, 69 nama/kode ruang unik, periode `2627/1`, seluruh `jenis_jadwal: TETAP` | IT Panel → Sinkronisasi → `room_schedule` |

JSON memiliki metadata `generated_at`, `source`, `total`, `selected_menus`, `filter_jadwal_perkuliahan` dan `data[]`. Field jadwal mencakup `id_jadwal`, `periode`, `hari`, `tanggal`, `jam_mulai`, `jam_selesai`, `ruangan`, `kode_mk`, `nama_mk`, `kelas`, `kode_dosen`, `bentrok`, `jenis_jadwal`.

**Cakupan tanggal JSON hanya 9–15 November 2026**, bukan 23–27 November. Jumlah baris per tanggal: 181, 179, 176, 166, 105, 17, dan3. Tidak ada data pribadi mentah disalin ke dokumen ini.

## 5. Temuan pada kode yang sudah ada

### Alur Peminjaman Ruangan di `/dadu`

- `app/Http/Controllers/RoomBookingController.php`: akses peminjaman mahasiswa belum ada; pembuatan/pencarian saat ini dibatasi akun dosen/Pelaksana sesuai pemeriksaan controller dan permission.
- Peminjaman biasa menerima ruang dan waktu pilihan pengguna; belum memenuhi alur tanggal saja dan pemilihan otomatis Sidang TA.
- `room_schedules` menjadi sumber jadwal iGracias. `room_booking_requests` dan `room_booking_occurrences` menyimpan peminjaman internal. `room_booking_histories` mencatat perubahan status.
- Status internal `pending` dan `approved` sama-sama menahan slot. Status `rejected` dan `cancelled` tidak menahan slot.
- Pembuatan peminjaman biasa menghasilkan `pending`; Sidang TA perlu jalur tersendiri untuk langsung `approved`, tanpa mengubah aturan peminjaman biasa.
- Pemeriksaan bentrok memakai tanggal dan interval waktu; akhir suatu pemakaian boleh tepat sama dengan awal pemakaian berikutnya.
- Pembuatan peminjaman memeriksa ulang bentrok di dalam transaksi dengan mengunci record ruang. Integrasi TA harus memakai koordinasi yang sama agar tidak terjadi pemesanan ganda.
- Kalender saat ini memakai jam operasional **06.30–21.30 WIB**, celah minimal **60 menit**. Ini perilaku kalender peminjaman, belum aturan Sidang TA yang disepakati.
- Pembatalan oleh peminjam biasa hanya untuk status `pending`; admin peminjaman dapat membatalkan `approved`. Pengubahan jadwal Sidang TA belum tersedia dan perlu dirancang sesuai permintaan pengguna.
- Tampilan review peminjaman sekarang berorientasi dosen/NIP; untuk booking mahasiswa perlu identitas NIM/nama yang benar, bukan memperlakukan mahasiswa sebagai dosen.

### Impor jadwal dan master

- `app/Services/RoomScheduleImportService.php` membaca **tanggal persis** dari tiap baris. `hari`/`jenis_jadwal: TETAP` belum dijadikan aturan pengulangan mingguan.
- Impor yang valid **menggantikan seluruh jadwal bersumber iGracias**; bukan menambahkan jadwal per minggu. Jadwal peminjaman internal tetap berada di tabel terpisah.
- Ruang yang belum dikenali dapat dibuat otomatis dengan gedung belum ditentukan dan kapasitas0. Daftar69 label ruang JSON dan60 master Excel perlu direkonsiliasi; perbedaan jumlah belum membuktikan9 ruang benar-benar baru karena alias dapat berbeda.
- Pada ekspor yang dianggap lengkap tanpa filter, importer juga dapat memperbarui status aktif ruang. Referensi saat ini memiliki filter `schoolYear: 2627/1`, sehingga tidak memenuhi cabang lengkap tanpa filter tersebut.
- **Identifikasi ruang kelas:** model/form master ruang belum memiliki field jenis/kategori. Excel yang diberikan juga hanya memuat Kode Ruang, Nama Ruang, Lokasi Gedung, Kapasitas, Status. Seluruh60 nama pada contoh Excel memuat `KELAS`;46 berstatus Aktif dan14 Non Aktif. Nama tersebut membantu pemeriksaan referensi, tetapi bukan jaminan seluruh ruang baru/hasil impor dapat dikenali sebagai kelas. Rancangan perlu menetapkan pemetaan/penandaan kelas yang jelas; ruang yang belum teridentifikasi tidak otomatis dianggap kelas hanya karena statusnya aktif. Ini kebutuhan pengenalan kategori, bukan alasan meminta JSON mahasiswa baru.
- Importer dosen saat ini membaca `KODE DOSEN`/`kode_dosen`, `Email Domain`/`email_domain`, dan `STATUS Kepegawaian`/`status_kepegawaian`. Excel referensi memakai **`Kode Dosen Baru`**, **`Email Domain Telkom University`**, dan **`STATUS`**. Impor belum dapat diasumsikan mengisi kode, email, dan status kepegawaian dengan benar. Record yang sudah ada juga dilewati, bukan diperbarui. Ini temuan untuk rencana penyesuaian, **belum diperbaiki**.
- Setelah jawaban pengguna, diperlukan dukungan pola mingguan sepanjang semester. `AcademicPeriod` sudah memiliki `year`, `semester`, `start_date`, dan `end_date`, tetapi room schedule belum tertaut periode tersebut. Menyalakan periode aktif saja belum membuat importer room schedule saat ini mengulang tanggal. Cara menautkan dan menerapkan batas semester akan dipilih dalam rancangan; **belum ada perubahan kode**.

### Pemeriksaan data pembimbing/penguji dan data yang masih menunggu

Pemeriksaan file baca saja, bukan pemeriksaan database production. Sumber profil asli: `rekap_igracias_2026-11-06.json`, berisi277 baris. Dibandingkan dengan155 record pada `Master Pegawai.xlsx` yang sudah diberikan:

| Field profil | Terisi | Kosong/penanda kosong | Cocok kode pada master yang diberikan |
| --- | --- | --- | --- |
| `pembimbing_1` | 257 | 20 | Seluruh257 isian terisi cocok |
| `pembimbing_2` | 175 | 102 | 168 cocok;7 isian berupa kode singkat belum cocok master |
| `penguji_1` | 6 | 271 | Seluruh6 isian terisi cocok |
| `penguji_2` | 6 | 271 | Seluruh6 isian terisi cocok |

- **Sudah tersedia:** kode pembimbing, sebagian kode penguji, dan master kode/nama dosen. Tidak perlu meminta JSON baru hanya untuk data yang sudah ada ini.
- **Belum tersedia pada banyak baris:** penguji;6 baris terisi untuk masing-masing penguji,271 lainnya kosong. Ini tidak membuktikan penguji belum ditetapkan di sumber asli: bisa belum ditetapkan atau belum ikut diekspor. Konfirmasi lanjutannya ada pada P11.
- **Perlu pencocokan master:**7 isian pembimbing kedua, mewakili6 kode unik, belum cocok dengan master yang diberikan. Kode sumber tetap ada; yang belum pasti adalah pemetaan nama/record master, termasuk kemungkinan dosen eksternal. Jangan menyebutnya7 kode hilang.
- **Data ada tetapi importer belum membaca header yang sesuai:** master dosen memiliki kolom `Kode Dosen Baru` dan `Email Domain Telkom University`, sedangkan importer saat ini menerima nama header lain. Ini masalah penyesuaian impor yang relevan untuk tampilan kode/nama, bukan bukti perlu ekspor ulang semua mahasiswa.
- **Data dummy:** pada file seeder11 mahasiswa terdapat nilai pembimbing/penguji yang tidak cocok dengan master dosen asli. Jangan memasangkan identitas dummy ke dosen nyata secara tebakan. Fixture tampilan perlu disesuaikan saat implementasi dengan penanda dummy yang jelas.
- **Masih menunggu pengguna:** contoh JSON kehadiran belum diberikan. Kehadiran mahasiswa bukan syarat pencarian ruang versi awal dan tidak dipakai untuk mengarang bentrok dosen.

Jika meminta contoh data pelengkap kepada divisi, cukup contoh tersamarkan yang mempertahankan hubungan NIM/ID pengajuan dengan kode pembimbing/penguji beserta referensi master yang diperlukan. Untuk penguji yang belum ditetapkan memang tidak ada nilai yang boleh dibuat-buat.

### Sidang TA saat ini

- `SidangTaPeriod` memiliki jadwal pendaftaran, jadwal Verifikasi Berkas, flag aktif, dan visibilitas mahasiswa; belum menyimpan jadwal pelaksanaan sidang.
- `SidangTaRegistrationController::index()` menganggap `submitted`/`needs_review` sebagai pengajuan aktif. Kartu baru perlu mengambil pengajuan **approved** milik mahasiswa secara eksplisit, bukan bergantung pada variabel pengajuan aktif tersebut atau sekadar halaman pertama riwayat.
- `resources/views/sidang_ta_registrations/index.blade.php` memuat kartu Draft tersimpan; halaman admin berada di `resources/views/sidang_ta_registrations/admin/index.blade.php`.
- Persetujuan TA dapat dibatalkan sesuai aturan107. Hubungan booking dengan pembatalan ini harus diputuskan agar ruang tidak tetap tertahan untuk pengajuan yang kehilangan persetujuan.
- Tabel `exam_schedules` juga ada untuk jadwal ujian mata kuliah. Pemeriksaan bentrok room booking saat ini belum memasukkan tabel tersebut. Cakupan pemakaian ruang lain perlu dipastikan, tidak otomatis dianggap tercakup JSON.

## 6. Catatan pertanyaan, jawaban, dan bahan konfirmasi divisi

Bagian ini dapat dibaca langsung oleh divisi. Pertanyaan/jawaban panjang diringkas tanpa mengubah maksud; kutipan pendek ditandai secara terpisah. Pertanyaan yang diulang untuk memperjelas pokok yang sama digabungkan pada satu nomor. Contoh di bawah adalah ilustrasi, bukan hasil booking pada data nyata. **Disetujui sementara** berarti boleh menjadi dasar rancangan awal, belum merupakan kebijakan resmi divisi atau bukti fitur telah diimplementasikan.

### T01 — Booking langsung disahkan atau menunggu persetujuan lagi?

**Pertanyaan yang diajukan:** Setelah sistem menemukan ruangan dan jam paling awal pada tanggal pilihan mahasiswa, apakah peminjaman Sidang TA langsung disahkan atau masih harus disetujui petugas peminjaman ruangan?

**Jawaban pengguna:** “sahkan saja langsung tetapi seperti sidang TA jika memang dibutuhkan admin melalui fitur bisa merubahnya”.

**Keputusan:** Booking TA langsung approved; admin dapat mengoreksi jika diperlukan. Alur persetujuan peminjaman biasa tetap mengikuti fitur teman.

**Contoh:** Pendaftaran TA mahasiswa sudah Disetujui. Pemesanan hasil pencarian pada23 November08.00–10.00 berhasil: ruang langsung tertahan dan tidak menunggu persetujuan kedua. Apakah Cari Jadwal harus lebih dahulu diikuti Konfirmasi Jadwal dibahas terpisah pada P04; jawaban ini belum menetapkan titik penyimpanan booking.

### T02 — Durasi satu sesi dan jeda pergantian mahasiswa

**Pertanyaan yang diajukan:** Berapa lama satu sesi Sidang TA harus menahan ruangan, termasuk jeda persiapan/pergantian mahasiswa jika diperlukan? Kalender peminjaman sekarang hanya menandai celah minimal satu jam; itu belum menjadi aturan durasi TA.

**Jawaban pengguna:** Belum ditanyakan kepada divisi. Untuk sementara boleh memakai1–2 jam agar pekerjaan dapat disiapkan, dan pertanyaan durasi resmi harus dicatat untuk ditanyakan kembali.

**Perkembangan jawaban:** Pada T07 pengguna menyetujui rekomendasi120 menit sebagai pengaturan awal sementara. Durasi dan jeda resmi tetap terbuka pada P01.

**Contoh:** Celah09.30–11.30 cukup untuk120 menit. Celah10.00–11.00 tidak cukup. Jika divisi kemudian mewajibkan120 menit sidang ditambah15 menit persiapan, ruang harus kosong selama135 menit; tambahan15 menit tersebut belum disetujui.

### T03 — Pemeriksaan ruang saja atau juga jadwal dosen?

**Pertanyaan yang diajukan:** Pencarian cukup memastikan ruangan kosong, atau juga harus menghindari bentrok jadwal mengajar pembimbing/penguji?

**Jawaban pengguna:** Untuk sekarang ruangan kosong saja; pemeriksaan dosen dicatat sebagai konfirmasi lanjutan karena masih perlu ditanyakan.

**Keputusan:** Versi awal tidak memblokir hasil berdasarkan jadwal pembimbing/penguji. Hasil hanya menjamin ketersediaan ruang menurut sumber jadwal yang diperiksa.

**Contoh:** Ruang B kosong08.00–10.00, tetapi pembimbing mempunyai kelas08.30–10.30. Versi awal masih dapat menawarkan ruang B; konfirmasi kehadiran dosen harus diatur di luar pemeriksaan otomatis versi awal. Rencana pemeriksaan dosen berikutnya ada pada P02.

### T04 — Makna jadwal `TETAP` dengan tanggal contoh9–15 November

**Pertanyaan yang diajukan:** Apakah jadwal berjenis `TETAP` pada JSON9–15 November memang berulang setiap minggu sepanjang semester yang sama?

**Jawaban pengguna:** Jadwal mingguan sama sepanjang semester tersebut. Sesekali dosen dapat meminta online/asinkron atau pindah hari; peminjaman ruang pengganti dapat melalui fitur Peminjaman Ruangan. Semester berikutnya memiliki jadwal berbeda.

**Keputusan:** Pola mingguan berlaku dalam semester yang sama; semester berikutnya harus memakai pola yang sesuai. Pengecualian satu pertemuan belum ditentukan pada jawaban ini.

**Contoh:** Senin9 November, ruang A terpakai08.30–10.30. Pada Senin23 November dalam semester yang sama, interval tersebut tetap dianggap terpakai. Ketika semester berganti, pola lama tidak otomatis dipakai. Jika pertemuan23 November dialihkan online, cara mengosongkan ruang A untuk tanggal itu perlu konfirmasi P03.

### T05 — Bentuk tanggal pelaksanaan yang dikelola petugas

**Pertanyaan yang diajukan:** Tanggal pelaksanaan dibuat sebagai rentang mulai–selesai untuk masing-masing periode TA, misalnya23–27 November, benar?

**Jawaban pengguna:** “benar seperti di contoh misal tangal 23-27 nov”.

**Keputusan:** Setiap periode TA mempunyai rentang mulai–selesai pelaksanaan tersendiri. Pendaftaran dan Verifikasi Berkas tetap mempunyai jadwal masing-masing.

**Contoh:** Periode TA1 menetapkan23–27 November. Mahasiswa dengan pengajuan periodeTA1 memilih tanggal di rentang tersebut; tanggal22 dan28 tidak boleh dipesan. Periode TA2 dapat memiliki rentang lain. Hari yang diperbolehkan di dalam rentang mengikuti pengaturan pada T07.

### T06 — Hari/jam sidang dan hubungan dengan struktur JSON

**Pertanyaan yang diajukan:** Hari dan jam pelaksanaan sidang mengikuti ketentuan apa? Kalender peminjaman saat ini memakai06.30–21.30 WIB, tetapi belum tentu seluruh rentang itu dipakai untuk sidang.

**Jawaban/pertanyaan balik pengguna:** “rekomendasimu gimana? tetapi tetap mengikuti struktur dari data dan json nya?”

**Rekomendasi asisten:** Senin–Jumat08.00–16.00 WIB, istirahat12.00–13.00 tanpa sesi yang melewatinya, durasi sementara120 menit; dapat disesuaikan petugas per periode TA. Struktur JSON tetap dipakai untuk pola mingguan dan interval ruang terpakai. Seluruh jadwal perkuliahan tetap dibaca, termasuk malam dan akhir pekan.

**Contoh:** File tetap memuat `periode`, `hari`, `tanggal`, `ruangan`, `jam_mulai`, `jam_selesai`, dan `jenis_jadwal`. Tidak perlu menambahkan jam layanan Sidang TA ke setiap baris JSON. Ruang yang kosong11.00–13.00 tidak dapat ditawarkan untuk sesi120 menit jika istirahat12.00–13.00 diberlakukan. Ruang yang kosong13.00–15.00 dapat ditawarkan.

### T07 — Persetujuan pengaturan awal dan cara pencatatan

**Pertanyaan yang diajukan:** Apakah pengaturan awal pada T06 sesuai untuk rancangan sementara?

**Jawaban pengguna:** “sesuai”, dengan instruksi agar setiap pertanyaan/jawaban dicatat dan pertanyaannya dijelaskan menggunakan contoh sehingga dapat ditanyakan atau dibaca langsung oleh divisi.

**Keputusan:** Rekomendasi T06 disetujui untuk rancangan sementara. Ini memperjelas patokan1–2 jam pada T02 menjadi nilai awal120 menit. Seluruh aturan resmi hari, jam, durasi dan jeda tetap dicatat pada P01. Rancangan lengkap masih perlu disusun dan ditinjau sebelum kode diubah.

**Contoh:** Divisi dapat membaca nilai awal120 menit lalu menjawab apakah nilai itu sudah termasuk persiapan/pergantian, atau perlu diganti. Catatan sementara tidak diubah menjadi klaim aturan resmi.

### T08 — Pratinjau hasil sebelum konfirmasi dan identitas yang ditampilkan

**Pertanyaan yang diajukan:** Setelah mahasiswa menekan Cari Jadwal dan sistem menemukan, misalnya, ruang B pada23 November08.00–10.00, apakah hasil ditampilkan dahulu dengan tombol Konfirmasi Jadwal, atau langsung dipesan saat pencarian? Asisten menyarankan konfirmasi agar mahasiswa sempat membaca hasil; setelah konfirmasi booking langsung disahkan tanpa persetujuan petugas lagi.

**Jawaban pengguna:** Hasil ditampilkan terlebih dahulu. Jika jadwal tidak disukai, biarkan dosen memesan melalui fitur Peminjaman Ruangan. Pengguna juga meminta informasi ID, NIM, nama, tanggal sidang, kode dosen pembimbing dan penguji ditampilkan ketika mencari maupun mengonfirmasi jadwal.

**Keputusan:** Ada tahap pratinjau sebelum konfirmasi. Hasil yang belum dikonfirmasi belum menjadi booking. Alternatif melalui peminjaman dosen diperbolehkan dengan alur dosen yang sudah ada. Identitas dan informasi dosen masuk rancangan halaman pencarian/konfirmasi.

**Contoh:** Mahasiswa melihat ruang B23 November08.00–10.00, NIM/nama, ID pengajuan, serta kode pembimbing/penguji. Jika cocok, tekan Konfirmasi Jadwal; jika tidak, tinggalkan hasil dan koordinasikan alternatif dengan dosen. Penguji kosong ditampilkan Belum tersedia; jangan menggantinya dengan kode dosen pengampu pada jadwal ruangan. Aturan perubahan setelah booking disahkan dan penautan booking dosen masih ada pada P06.

### T09 — Kandidat ruang kelas yang tersedia dan tidak bentrok

**Pertanyaan yang diajukan:** Untuk Sidang TA, apakah semua ruangan berstatus Dapat Dipakai boleh dialokasikan, atau hanya ruangan tertentu? Contohnya, ruang kelas dan laboratorium sama-sama aktif; apakah keduanya boleh dipilih otomatis? Ini menentukan daftar ruang yang diperiksa sebelum sistem mengambil jam paling awal.

**Jawaban pengguna:** “ruang kelas saja dan jelas yang available saja dan tidak bentrok, catat juga ini biar bisa ku konfirmasikan”.

**Keputusan sementara:** Hanya ruang kelas yang berstatus Dapat Dipakai dan memiliki slot kosong berurutan sepanjang durasi. Laboratorium/ruang lain dikecualikan walaupun kosong. Pemeriksaan berlaku saat pencarian dan diperiksa ulang ketika konfirmasi.

**Contoh:** Ruang kelas A terpakai08.00–10.00, laboratorium B kosong08.00–10.00, ruang kelas C kosong09.00–11.00. Untuk durasi120 menit, pilih C09.00–11.00; jangan memilih laboratorium B meskipun lebih pagi. Jika tidak ada kelas yang cukup kosong pada hari pilihan, tampilkan tidak ada jadwal; jangan beralih ke laboratorium secara otomatis.

**Konfirmasi ke divisi:** Apakah ruang kelas saja berlaku untuk semua prodi, serta apakah ada pengecualian gedung/kampus, kebutuhan fasilitas, dan kapasitas minimum? Pertanyaan pokok kelas vs laboratorium sudah dijawab untuk rancangan sementara; rinciannya tetap dilacak pada P05.

### T10 — Pembatalan booking saat persetujuan TA dibatalkan

**Pertanyaan yang diajukan:** Jika persetujuan Sidang TA dibatalkan untuk diperiksa ulang sebelum hari pelaksanaan, apakah booking TA yang sudah dikonfirmasi ikut dibatalkan otomatis? Contohnya, booking23 November08.00–10.00 sudah disahkan, lalu persetujuan TA dibatalkan19 November. Asisten menyarankan booking ikut dibatalkan dengan riwayat tetap disimpan agar ruang kembali tersedia.

**Jawaban pengguna:** “Booking ikut dibatalkan otomatis; riwayat tetap disimpan”. Pengguna kemudian menambahkan: “catat itu sebagai konfirmasi juga”.

**Keputusan sementara:** Booking TA terkait ikut cancelled ketika persetujuan TA dibatalkan sebelum pelaksanaan. Slot tidak lagi menahan ruangan, sedangkan record/riwayat tetap tersimpan. Perilaku ini harus dibawa untuk konfirmasi divisi; bukan klaim kebijakan resmi.

**Contoh:** Persetujuan dibatalkan19 November; booking ruang B23 November08.00–10.00 ikut dibatalkan. Ruang tersebut dapat dipesan pihak lain. Jika mahasiswa kembali disetujui20 November, slot lama belum tentu tersedia; jadwal perlu dicari/dikonfirmasi ulang. Booking dosen yang tidak tertaut tidak boleh ikut dibatalkan hanya karena tujuan peminjaman menyebut nama/NIM yang sama.

**Konfirmasi ke divisi:** Apakah pembatalan persetujuan selalu harus melepas ruang meskipun pengajuan diperkirakan segera disetujui kembali? Apakah mahasiswa dan petugas cukup melihat status/riwayat atau memerlukan pemberitahuan tambahan? Permintaan pemberitahuan tambahan belum diputuskan. Kasus perubahan rentang periode dan booking yang sudah berlangsung merupakan pertanyaan berbeda, bukan otomatis dijawab oleh T10.

### T11 — Perubahan booking yang sudah dikonfirmasi melalui SSC/Divisi Akademik

**Pertanyaan yang diajukan:** Setelah mahasiswa menekan Konfirmasi Jadwal dan booking sudah disahkan, apakah ia boleh membatalkan atau mengganti jadwal sendiri, atau harus melalui admin/petugas? Contohnya, sudah mendapat23 November08.00–10.00 lalu ingin pindah24 November. Ini berbeda dari meninggalkan hasil pencarian yang belum dikonfirmasi.

**Jawaban pengguna:** “tetap harus menghubungi admin/petugas di ssc jadi biar divis akademik yang merubahnya mahasiswa tidak bisa mengganti maupun membatalkannya”.

**Keputusan:** Setelah konfirmasi, perubahan/pembatalan hanya dilakukan pihak Divisi Akademik yang berwenang. Mahasiswa menghubungi admin/petugas di SSC dan tidak mempunyai akses mengubah/membatalkan sendiri, termasuk melalui request langsung. Dosen yang meminjam alternatif melalui alur biasa tidak otomatis mendapat hak mengubah booking TA mahasiswa.

**Contoh:** Booking23 November08.00–10.00 sudah disahkan. Mahasiswa ingin pindah24 November: ia menghubungi petugas SSC, lalu Divisi Akademik memeriksa jadwal pengganti dan melakukan perubahan. Tampilan mahasiswa memperlihatkan jadwal terbaru dan petunjuk menghubungi SSC, tanpa tombol Ubah/Batalkan. Kewajiban memeriksa bentrok ulang dan menjaga booking lama jika pemindahan gagal menjadi bagian rancangan teknis.

**Batas jawaban:** Ini menetapkan siapa yang boleh mengubah/membatalkan setelah konfirmasi. Belum menetapkan cara menautkan peminjaman alternatif dosen, jumlah booking aktif, atau hak membuat booking baru setelah pembatalan oleh petugas. Hal-hal yang belum diputuskan tetap berstatus usulan/pertanyaan pada P06.

### T12 — Pendekatan pola mingguan per semester saat pencarian

**Pertanyaan yang diajukan:** Untuk rancangan integrasi, apakah memakai pola mingguan per semester yang dihitung saat pencarian, dengan pemeriksaan bersama untuk TA dan peminjaman ruangan, atau membuat jadwal seluruh tanggal semester saat impor?

**Jawaban pengguna:** “ya memakai pendekatan itu dan juga dicata seperti sebelumnya”, merujuk rekomendasi pola mingguan per semester yang dihitung saat pencarian.

**Keputusan:** Pilihan pertama dipakai sebagai dasar rancangan. Perhitungan ketersediaan harus sama pada kedua fitur; pola dibatasi semester dan tidak digandakan menjadi record setiap tanggal saat impor.

**Contoh:** Ruang A setiap Senin08.30–10.30 pada Ganjil2026/2027 tetap bentrok ketika Senin23 November diperiksa. Saat semester berakhir, pola tidak diteruskan ke semester baru. Kalender peminjaman biasa dan pencarian TA harus menampilkan pemakaian yang konsisten untuk tanggal yang sama.

**Dampak pada tahap pekerjaan:** Rancangan lengkap disusun pada dokumen110 yang ditautkan dari bagian1. Jawaban ini memilih pendekatan; rincian teknis yang belum dijawab pengguna tetap diberi label usulan asisten dalam rancangan dan menunggu tinjauan.

### T13 — Admin menandai dan menautkan peminjaman alternatif dosen

**Pertanyaan yang diajukan:** Jika dosen menangani peminjaman alternatif untuk sidang mahasiswa, apakah admin perlu menandai pengajuan “Dijadwalkan melalui dosen” dan menautkan booking agar mahasiswa tidak lagi mengonfirmasi booking otomatis? Contohnya, dosen memesan24 November, tetapi mahasiswa masih dapat mengonfirmasi hasil otomatis23 November sehingga dua ruang tertahan. Alternatif yang ditawarkan adalah koordinasi manual saja tanpa penautan aplikasi.

**Jawaban pengguna:** “ya seperti saran mu oleh admin, dicatat juga dan berikan contoh kasusnya. lanjutkan”.

**Keputusan:** Penandaan dan penautan dilakukan admin; koordinasi manual tanpa hubungan aplikasi tidak menjadi pendekatan yang dipilih. Mahasiswa tetap tidak boleh mengubah/membatalkan booking yang sudah dikonfirmasi.

**Contoh A — Alternatif dipilih sebelum mahasiswa mengonfirmasi:** Mahasiswa melihat hasil23 November08.00–10.00 tetapi tidak mengonfirmasi. Petugas menandai pengajuan sebagai Dijadwalkan melalui dosen, lalu dosen mengajukan ruang24 November13.00–15.00. Admin menautkan ID peminjamannya. Jalur otomatis23 November berhenti, termasuk bila mahasiswa masih mempunyai tab hasil lama. Booking pending tetap menunggu persetujuan peminjaman dosen; sesudah approved, kartu mahasiswa menampilkan24 November13.00–15.00.

**Contoh B — Booking pending dosen dibatalkan/ditolak:** Dosen membatalkan pengajuan pending yang sudah ditautkan melalui alur biasa, atau petugas menolaknya. Slot dilepas, riwayat tersimpan, dan kartu TA menunjukkan jadwal alternatif belum terkonfirmasi/perlu ditangani petugas. **Usulan teknis:** status pengajuan tetap mode dosen agar mahasiswa tidak otomatis memesan jalur lain. Admin kemudian menautkan booking pengganti yang benar.

**Contoh C — Mahasiswa sudah terlanjur mengonfirmasi otomatis:** Booking23 November08.00–10.00 sudah approved. Permintaan beralih ke dosen ditangani SSC/Divisi Akademik: booking lama harus dipindahkan/dibatalkan secara jelas sebelum menautkan booking aktif lain. **Usulan teknis:** penandaan/penautan tidak diam-diam melepas booking yang sudah disahkan; tampilkan konflik dan minta tindakan petugas yang berwenang.

**Contoh D — Persetujuan TA dibatalkan:** Booking dosen yang tertaut pada pengajuan TA ikut dibatalkan sesuai T10, jika belum digunakan. Booking dosen lain yang tidak tertaut tidak ikut berubah. Riwayat penautan, persetujuan dan pembatalan tetap disimpan. Tab persetujuan peminjaman yang lama tidak boleh menghidupkan booking cancelled kembali.

**Batas jawaban:** Pengguna menyetujui penandaan/penautan oleh admin dan meminta contoh; urutan penandaan sebelum booking, penerimaan link pending, penjagaan tab lama, serta penanganan status selanjutnya merupakan rincian teknis yang diusulkan dalam110/111. Jangan menulisnya sebagai kutipan kebijakan divisi yang sudah dikonfirmasi.

### Instruksi tambahan pengguna yang memengaruhi scope

- **Batas perubahan fitur teman:** Tambahkan hal yang kurang hanya sepanjang relevan untuk pencarian jadwal Sidang TA. Pengguna memberi contoh pembatalan approved oleh admin jika belum tersedia, serta meminta alur dosen membatalkan pending tetap dipertahankan. Hasil pemeriksaan: pembatalan approved oleh admin sudah tersedia di `/dadu`; gunakan dan integrasikan yang ada.
- **Lokasi alur pemakaian:** Alur mahasiswa dan admin berada pada dokumen yang sama dengan pertanyaan konfirmasi. Keduanya tersedia pada bagian3 dokumen ini.
- **Tahap pekerjaan:** Mulai dengan plan/pertanyaan, dokumentasikan sebelum/sesudah, gunakan dokumen implementasi terpisah yang saling ditautkan, dan rescan sebelum/sesudah pekerjaan. Belum ada instruksi baru untuk langsung mengubah kode.
- **Data yang belum tersedia:** Pengguna meminta diberi tahu bila ada data yang belum tersedia, sebagaimana JSON kehadiran yang memang masih menunggu. Hasil pemeriksaan pembimbing/penguji dicatat pada bagian5 dan pertanyaan lanjutannya pada P11.

### Daftar pertanyaan lanjutan dan status jawabannya

NomorP berikut melacak bahan pertanyaan kepada pengguna/divisi. Pertanyaan yang sudah dijawab tetap dipertahankan nomornya dengan status dan rujukan jawaban. Yang belum dijawab **tidak dianggap disepakati**. Jika sudah dijawab, tambahkan tanggal/tahap jawaban, identitas sumber bila diberikan, isi keputusan, serta perubahan terhadap nilai sementara. Jangan menghapus riwayat keputusan sebelumnya.

#### P01 — Aturan resmi durasi, jeda, hari, dan jam

**Pertanyaan untuk divisi:** Apakah satu booking mencakup 120 menit termasuk persiapan/pergantian mahasiswa, atau ada jeda tambahan? Apakah Senin–Jumat08.00–16.00, istirahat12.00–13.00 berlaku untuk semua prodi, termasuk Jumat? Bagaimana hari libur dan perubahan jam khusus?

**Contoh untuk dikonfirmasi:** Sidang A selesai 10.00. Apakah B boleh langsung mulai10.00 atau harus10.15? Apakah Jumat11.00–13.00 harus ditutup lebih panjang? Angka15 menit dan rentang Jumat tersebut hanya contoh pertanyaan.

**Jawaban saat ini:** Pengaturan T07 disetujui sementara; aturan resmi divisi belum ada. Usulan konfigurasi tidak otomatis membuat kalender hari libur tersedia.

#### P02 — Pemeriksaan pembimbing/penguji pada tahap berikutnya

**Pertanyaan untuk divisi:** Apakah versi berikutnya harus memastikan jadwal pembimbing/penguji kosong juga; dosen mana yang wajib hadir; apakah jadwal mengajar saja cukup; dan apa yang dilakukan jika data/jadwal dosen belum lengkap?

**Contoh:** Ruang kosong08.00–10.00 tetapi penguji mengajar09.30–11.30. Apakah hasil harus diblokir atau cukup diberi peringatan?

**Jawaban saat ini:** T03 sudah menetapkan versi awal ruangan saja. Pertanyaan ini tidak menghalangi rancangan versi awal.

#### P03 — Pengecualian pola mingguan dan batas semester

**Pertanyaan untuk divisi:** Siapa yang mengonfirmasi pertemuan online/asinkron/pindah hari sehingga ruang asal boleh dilepas untuk satu tanggal? Tanggal resmi semester mana yang menjadi batas pola `2627/1`? Apakah hari libur perlu pengecualian tersendiri?

**Contoh:** Dosen memindahkan kelas Senin23 November ke Selasa24 November dengan meminjam ruang baru. Apakah Senin23 November pada ruang asal langsung kosong? Data peminjaman baru belum membuktikan kelas asal dibatalkan.

**Jawaban saat ini:** Pengulangan sepanjang semester dikonfirmasi pada T04. Mekanisme pengecualian belum dijawab; usulan awal tetap menganggap ruang asal terpakai sampai ada pengecualian yang jelas. Periode akademik sudah memiliki batas tanggal, tetapi belum ditautkan room schedule.

#### P04 — Titik konfirmasi dan penyimpanan booking mahasiswa

**Pertanyaan kepada pengguna/divisi:** Setelah mahasiswa memilih tanggal dan menekan Cari Jadwal, apakah ditampilkan satu hasil untuk dikonfirmasi, atau slot langsung dipesan saat pencarian?

**Contoh:** Hasil23 November08.00–10.00 di ruang B muncul. Apakah mahasiswa masih bisa membaca hasil dan menekan Konfirmasi Jadwal sebelum ruang ditahan? Jika orang lain lebih dahulu memesan saat hasil dibaca, apakah hasil baru ditawarkan untuk dikonfirmasi ulang?

**Jawaban saat ini:** **Sudah dijawab pada T08:** tampilkan hasil terlebih dahulu, lalu mahasiswa mengonfirmasi. Jika tidak cocok, dosen dapat menggunakan alur Peminjaman Ruangan biasa. Rincian pemeriksaan ulang saat konfirmasi tetap masuk rancangan teknis.

**Status pertanyaan:** Selesai dijawab setelah T07; dicatat sebagai T08. Pertanyaan konfirmasi pencarian tidak perlu diajukan ulang.

#### P05 — Ruang yang boleh dialokasikan untuk Sidang TA

**Pertanyaan kepada pengguna/divisi:** Apakah semua ruang berstatus Dapat Dipakai boleh digunakan, atau hanya ruang/gedung/kampus tertentu dengan kapasitas dan fasilitas minimum? Bagaimana ruang yang baru dibuat otomatis dari impor dan datanya belum lengkap?

**Contoh:** Ruang A aktif berkapasitas40, ruang B aktif berkapasitas0 karena dibuat dari impor. Apakah B boleh ditawarkan atau harus dilengkapi/disetujui dulu?

**Jawaban saat ini:** **Sudah dijawab untuk rancangan sementara pada T09:** ruang kelas saja, tersedia, dan tidak bentrok. Pengguna tetap ingin mengonfirmasikannya kepada divisi. Ketentuan kapasitas/fasilitas/kampus serta pengenalan ruang hasil impor yang belum lengkap belum diputuskan. Berbeda69 label ruang JSON dan60 master Excel perlu dipetakan; jangan menganggap semua selisih sebagai ruang baru.

**Status pertanyaan:** Pertanyaan kelas/laboratorium selesai dijawab setelah T08; dicatat sebagai T09. Sisa konfirmasi kepada divisi: apakah batas ruang kelas berlaku umum, kapasitas/fasilitas minimum, dan pengecualian kampus/gedung. Master belum memiliki kategori eksplisit; contoh Excel memiliki60 nama kelas,46 aktif dan14 nonaktif. Jangan menebak kategori ruang baru yang belum jelas.

#### P06 — Pengubahan, pembatalan, dan jumlah booking aktif

**Pertanyaan kepada pengguna/divisi:** Perubahan admin boleh mengganti tanggal, jam, dan ruang? Apakah mahasiswa boleh mengganti/membatalkan sendiri atau harus menghubungi petugas? Apakah satu pengajuan hanya boleh mempunyai satu booking aktif? Jika dosen membuat peminjaman alternatif, apakah perlu ditautkan ke pengajuan TA agar kartu mahasiswa menampilkan jadwal yang sama?

**Contoh:** Mahasiswa sudah memesan23 November08.00–10.00 lalu meminta pindah24 November. Apakah mahasiswa mengajukan permintaan atau petugas langsung memindahkan? Jika ruang baru bentrok, booking lama perlu tetap ada.

**Jawaban saat ini:** Admin boleh mengubah jika diperlukan (T01). Jika pratinjau tidak cocok, dosen boleh memesan alternatif (T08). **T11:** sesudah konfirmasi mahasiswa menghubungi SSC; Divisi Akademik yang berwenang mengubah/membatalkan. **T13:** admin menandai pengajuan dan menautkan booking alternatif secara eksplisit. Jumlah booking aktif tetap pilihan teknis asisten untuk ditinjau: satu per pengajuan/NIM. Pembatalan approved oleh admin sudah tersedia. Perpindahan diperiksa ulang dan dicatat; tidak menautkan berdasarkan teks tujuan yang mirip.

**Riwayat pertanyaan setelah T10:** Dijawab pada T11: perubahan melalui SSC/Divisi Akademik saja. Penautan alternatif kemudian dijawab pada T13: oleh admin dengan penanda pengajuan. Tidak perlu mengulang kedua pertanyaan pokok tersebut. Batas satu booking dan perilaku pemesanan ulang administratif dirinci dalam110/111 sebagai pilihan teknis untuk ditinjau.

#### P07 — Hubungan booking dengan pembatalan persetujuan dan perubahan periode

**Pertanyaan kepada pengguna/divisi:** Jika persetujuan TA dibatalkan untuk diperiksa ulang, apakah booking ikut dibatalkan otomatis atau dipertahankan sampai petugas memutuskan? Jika rentang pelaksanaan dipersempit, bagaimana booking yang berada di luar rentang baru?

**Contoh:** Booking27 November sudah approved, lalu rentang pelaksanaan diubah menjadi23–26 November. Apakah perubahan ditolak sampai booking dipindahkan, atau petugas diberi daftar booking yang harus ditangani?

**Jawaban saat ini:** **Sudah dijawab sementara pada T10:** saat persetujuan TA dibatalkan sebelum pelaksanaan, booking terkait ikut dibatalkan otomatis dan riwayat tetap disimpan. Pengguna meminta ini juga dicatat sebagai konfirmasi lanjutan kepada divisi. Kasus mempersempit rentang pelaksanaan dan booking yang sudah berlangsung belum diputuskan.

**Riwayat pertanyaan setelah T09:** Diajukan pilihan booking otomatis cancelled dengan riwayat tersimpan, atau dipertahankan sampai dibatalkan terpisah oleh petugas. Pengguna memilih pembatalan otomatis dan meminta dicatat untuk konfirmasi divisi. Pertanyaan pokok tidak perlu diajukan ulang kepada pengguna; pertanyaan resmi divisi tetap dipertahankan pada T10. Ini belum menjawab kasus mempersempit rentang pelaksanaan.

#### P08 — Hak akses pengelolaan dan koreksi booking

**Pertanyaan kepada pengguna/divisi:** Siapa boleh mengelola tanggal/jam pelaksanaan, mengubah booking, serta membatalkannya? Apakah cukup permission pengelola periode/reviewer TA, atau perlu aksi khusus?

**Contoh:** Dosen memiliki hak baca pengajuan prodinya tetapi bukan reviewer. Ia boleh melihat jadwal mahasiswa, tetapi apakah boleh memindahkan ruang? Usulan: hak baca tidak memberi hak pengubahan.

**Jawaban saat ini:** **T11 menetapkan Divisi Akademik melalui admin/petugas SSC** sebagai pihak perubahan/pembatalan setelah konfirmasi. Pemetaan ke permission aplikasi untuk pengelolaan jadwal/koreksi masih perlu dirinci dalam rancangan. Istilah Divisi Akademik tidak otomatis dianggap sama dengan setiap pemilik role admin atau role aplikasi bernama Akademik. Hak baca prodi dosen tidak otomatis memberi hak mutasi. Tidak mereset hak akses yang sudah berjalan.

#### P09 — Pengajuan approved sebelum fitur dipasang dan approval historis

**Pertanyaan kepada pengguna/divisi:** Apakah seluruh pengajuan approved pada periode yang memiliki jadwal pelaksanaan boleh langsung booking, termasuk yang disetujui sebelum pemasangan fitur? Jika satu mahasiswa punya approval lama dan baru, mana yang boleh dijadwalkan?

**Contoh:** Mahasiswa approved minggu lalu dan belum pernah booking. Kartu seharusnya dapat memakai pengajuan tersebut setelah jadwal dilengkapi, tanpa mengirim ulang berkas. Bagaimana jika juga terdapat approval periode sebelumnya?

**Jawaban saat ini:** Kebutuhan awal menyebut mahasiswa yang sudah approved. Detail pemilihan beberapa approval historis belum dijawab; dukungan pengajuan yang sudah ada perlu masuk rancangan migrasi/aktivasi.

#### P10 — Impor jadwal baru bertabrakan dengan booking yang sudah disahkan

**Pertanyaan kepada pengguna/divisi:** Jika pola perkuliahan diperbarui dan jadwal baru memakai ruang/jam yang sudah dipesan, apakah impor ditahan agar petugas menyelesaikan bentrok, atau diterima dengan daftar bentrok yang wajib ditangani? Siapa yang memindahkan booking?

**Contoh:** Booking TA di ruang B23 November08.00–10.00 sudah disahkan. Impor perkuliahan baru menempatkan kelas di ruang B setiap Senin08.30–10.30. Kedua jadwal berbenturan.

**Jawaban saat ini:** Belum dijawab. Tidak membatalkan booking otomatis atau mengabaikan bentrok tanpa keputusan yang jelas.

#### P11 — Data penguji dan pencocokan kode dosen yang belum lengkap

**Pertanyaan untuk divisi:** Pada JSON profil, apakah penguji kosong karena memang belum ditetapkan sebelum penjadwalan, atau export belum mengambil data yang sudah ada? Pada tahap apa penguji tersedia dan dari menu/field apa diekspor? Bagaimana melengkapi master kode dosen pembimbing/penguji yang belum cocok, termasuk dosen eksternal?

**Contoh:** Pada contoh277 baris, penguji1/2 masing-masing baru terisi6 baris. Apakah271 baris kosong wajar karena belum ditetapkan, sehingga halaman perlu menampilkan Belum tersedia? Jika seharusnya sudah ada, diperlukan contoh tersamarkan yang tetap menautkan NIM dengan kode penguji. Untuk7 isian pembimbing kedua yang tidak cocok master, kode tetap dapat ditampilkan, tetapi nama/record master perlu diverifikasi.

**Jawaban saat ini:** Belum dijawab. User meminta data yang kurang dilaporkan; hasil cek file sudah dicatat pada bagian5. Perlu konfirmasi sumber, bukan membuat kode/nama pengganti. Pemeriksaan jadwal tetap ruangan saja. JSON kehadiran juga masih menunggu pengguna dan merupakan kebutuhan sumber yang berbeda.

#### P12 — Pilihan cara menerapkan pola mingguan pada integrasi

**Pertanyaan kepada pengguna:** Untuk rancangan integrasi, apakah menyimpan pola mingguan per semester lalu menghitungnya saat pencarian, atau membuat jadwal untuk seluruh tanggal semester saat impor?

**Pilihan pertama — rekomendasi asisten:** Simpan pola mingguan yang terikat semester dan batas tanggalnya, lalu perluas untuk tanggal yang sedang diperiksa. Sidang TA dan Peminjaman Ruangan harus memakai perhitungan yang sama. Tidak menggandakan setiap pola menjadi banyak record tanggal; membutuhkan penyesuaian seluruh jalur pemeriksaan ketersediaan yang relevan agar konsisten.

**Pilihan kedua:** Bentuk record pemakaian setiap tanggal sampai batas semester saat impor. Pemeriksaan tanggalan yang sudah ada lebih mudah dipertahankan; jumlah record bertambah dan perubahan pola/batas semester memerlukan pembaruan hasil pembentukannya. Ini juga harus menjaga booking internal dan riwayat.

**Contoh:** Pola ruang A setiap Senin08.30–10.30 pada semester Ganjil2026/2027. Pilihan pertama menentukan bahwa Senin23 November terpakai ketika tanggal itu diperiksa. Pilihan kedua membuat record23 November beserta Senin lain ketika impor berjalan. Keduanya harus menghasilkan bentrok yang sama dan tidak meneruskan pola ke semester berikutnya.

**Jawaban saat ini:** **Sudah dijawab pada T12:** memakai pilihan pertama, pola mingguan per semester dihitung saat pencarian. Pilihan ini teknis dan tidak perlu ditanyakan ulang kepada divisi. Rancangan lengkap ditulis pada dokumen110 untuk ditinjau; kode belum diubah.

#### P13 — Mencegah booking ganda melalui alternatif dosen dan jalur otomatis

**Pertanyaan kepada pengguna:** Jika dosen menangani peminjaman alternatif, apakah admin perlu menandai pengajuan TA “Dijadwalkan melalui dosen” dan menautkan booking agar mahasiswa tidak lagi mengonfirmasi booking otomatis, atau cukup koordinasi manual pada versi awal?

**Contoh:** Dosen memesan ruang24 November untuk mahasiswa yang sama. Mahasiswa masih melihat hasil otomatis23 November dan mengonfirmasi. Tanpa hubungan/penanda, dua ruang tertahan untuk satu sidang, meskipun aturan satu booking TA aktif tidak dilanggar karena peminjaman dosen belum tertaut.

**Rekomendasi asisten:** Penanda dan penautan oleh admin agar jalur otomatis dihentikan untuk pengajuan yang ditangani melalui dosen. Cara menandai sebelum/selama pemesanan, status peminjaman yang boleh ditautkan, dan konsistensi perubahan/cancel akan dirinci bila pilihan ini dipakai. Jangan menautkan otomatis dari kemiripan teks/NIM pada keterangan. Alternatif: koordinasi manual saja, dengan keterbatasan bahwa aplikasi belum menjamin bebas booking ganda lintasjalur untuk sidang yang sama.

**Jawaban saat ini:** **Sudah dijawab pada T13:** admin menandai pengajuan dan menautkan peminjaman dosen sesuai rekomendasi. Pengguna meminta pencatatan, contoh kasus, dan melanjutkan pekerjaan. Bagian alternatif pada110 diperbarui; rincian teknis dan pengujiannya masuk111. Tidak perlu mengulang pertanyaan pokok ini.

## 7. Urutan kerja berikutnya

1. Cakupan versi awal sudah ditetapkan: ketersediaan **ruang kelas saja**, pola perkuliahan mingguan sepanjang semester yang sesuai, rentang tanggal pelaksanaan per periode TA, serta hari/jam/durasi sementara pada T07. Lanjutkan keputusan yang mengubah alur utama, khususnya batas semester dan siklus booking pada pertanyaanP. Pertanyaan aturan resmi yang ditunda tetap dicatat untuk divisi.
2. Pilihan integrasi sudah dijelaskan dan dipilih pada T12: pola mingguan per semester dihitung saat pencarian.
3. Tinjau rancangan pada dokumen110. Aturan sementara dan pilihan teknis asisten tetap ditandai sesuai statusnya.
4. Rencana implementasi111 sudah disusun berdasarkan rancangan dan jawaban T13. Tinjau rencana tersebut sebelum eksekusi; dokumen hasil tetap terpisah dan belum dibuat. Rescan ulang sebelum mulai kode.
5. Pada implementasi kelak, verifikasi pemilihan jam paling awal lintasruang, durasi, bentrok impor/peminjaman lain, dua pengguna bersamaan, satu booking aktif, permission/kepemilikan, pembatalan/perubahan, dan pengajuan approved lama. Detail pengujian mengikuti rancangan final.

Belum ada command copy kode atau migrasi untuk fitur baru ini karena belum ada implementasi. Jangan menyalin seluruh folder `/dadu` ke `/dadu-test`; paket dependensi room booking dan perubahan bersama harus ditelaah agar tidak menimpa Docker, SSO, atau modul teman.

**Tinjauan sebelum eksekusi (bukan pertanyaan kebijakan divisi):** Apakah sembilan task pada111 sudah sesuai untuk dikerjakan langsung oleh agen utama dengan review selektif? Status: rencana baru diserahkan untuk tinjauan; belum ada kode yang dijalankan atau checkbox task yang dianggap selesai.

## 8. Rescan setelah pencatatan awal

- Perbandingan ulang hash **620 berkas** di `/dadu-test` dan **637 berkas** di `/dadu` pada direktori kode/config/database/view/route/test/Docker serta manifest yang dipantau: **tidak ada perubahan isi, penambahan, atau penghapusan pada cakupan tersebut**.
- Branch/HEAD kedua folder tetap sama dengan pemeriksaan awal.
- Status Git `/dadu-test` hanya bertambah dokumen109 ini. Tiga perubahan lokal `/dadu` yang sudah ada tetap dipertahankan tanpa perubahan isi.
- Pemeriksaan `git diff --check` tidak melaporkan masalah. Unit/feature test tidak dijalankan karena pekerjaan tahap ini hanya membaca sumber dan menulis dokumentasi; tidak ada klaim pengujian implementasi fitur baru.
- Rescan ini menutup pemeriksaan/pencatatan awal. Rancangan final dan catatan sesudah pembuatan plan akan ditambahkan setelah pertanyaan utama dijawab dan pendekatan dipilih.

### Pembaruan setelah jawaban cakupan pemeriksaan

- Sebelum pembaruan, status Git dan HEAD kedua folder diperiksa lagi; sama seperti hasil rescan awal. Importer juga diperiksa ulang: tanggal pemakaian masih dibaca langsung dari field tanggal.
- Keputusan pengguna ditambahkan pada bagian3 dan6: versi awal hanya memeriksa ruangan. Pemeriksaan bentrok dosen menjadi pertanyaan lanjutan, bukan syarat implementasi versi awal.
- Rescan sesudah pembaruan: hash620 berkas `/dadu-test` dan637 berkas `/dadu` tetap sama dengan baseline; HEAD tidak berubah. Hanya dokumen109 yang ditambahkan. Tiga perubahan lokal awal `/dadu` tetap utuh; `git diff --check` lulus. Kode aplikasi belum diubah.

### Pembaruan setelah jawaban pola mingguan dan batas integrasi

- Sebelum pembaruan, Git/HEAD kedua folder diperiksa ulang dan tetap sama. `AcademicPeriod`, importer room schedule, route pembatalan, controller pembatalan, dan tombol review peminjaman dibaca ulang.
- Pembatalan approved oleh admin dipastikan sudah ada pada `RoomBookingController::adminCancel()`, route `admin.room-bookings.cancel`, dan form tombol di `resources/views/room-bookings/review.blade.php`. Ini verifikasi kode baca saja, belum pengujian endpoint/runtime.
- Alur pemakaian mahasiswa dan admin ditambahkan ke bagian3 bersama penandaan langkah yang masih berupa usulan. Pertanyaan konfirmasi tetap disimpan di dokumen yang sama pada bagian6.
- Rescan sesudah pembaruan membandingkan kembali620 berkas `/dadu-test` dan637 berkas `/dadu`: tidak ada perubahan kode/config/test maupun HEAD. Hanya dokumen109 yang bertambah; tiga perubahan lokal awal `/dadu` tetap sama. `git diff --check` lulus. Tidak menjalankan unit/feature test karena belum ada perubahan kode.

### Pembaruan setelah konfirmasi rentang pelaksanaan

- Sebelum perubahan dokumen, status Git dan HEAD kedua folder diperiksa ulang; tetap sesuai kondisi sebelumnya.
- Rentang tanggal mulai–selesai **per periode TA** sudah dikonfirmasi pengguna. Keputusan dan alur kedua sisi diperbarui pada bagian3; pertanyaan pada bagian6 tidak lagi menanyakan bentuk rentangnya.
- Jam dan hari yang diperbolehkan masih menjadi pertanyaan berikutnya. Contoh jam operasional peminjaman ruangan bukan aturan Sidang TA yang otomatis disetujui.
- Rescan sesudah pencatatan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tetap. `git diff --check` lulus. Perubahan sesi ini hanya dokumentasi109, tanpa perubahan kode atau database.

### Pembaruan rekomendasi hari/jam

- Sebelum pencatatan, Git/HEAD kedua folder serta jam operasional pada controller peminjaman diperiksa ulang. Field hari/jam pada827 baris JSON dianalisis baca saja: terdapat Senin–Minggu, jam mulai minimum06.30, dan jam selesai maksimum23.30. Tidak mencetak identitas pribadi atau mengimpor data.
- Rekomendasi ditulis pada bagian3 dan pertanyaan pada bagian6. Seluruh nilai baru tetap berlabel usulan, belum keputusan pengguna/divisi.
- Rescan sesudah pencatatan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tidak berubah. `git diff --check` lulus. Hanya dokumentasi109 bertambah; tiga perubahan lokal awal `/dadu` tetap utuh. Tidak ada kode/database yang diubah atau klaim pengujian implementasi.

### Pembaruan setelah persetujuan pengaturan awal dan permintaan catatan tanya jawab

- Git/HEAD kedua folder diperiksa sebelum pembaruan; sama dengan keadaan sebelumnya.
- Jawaban terbaru **sesuai** dicatat sebagai persetujuan rancangan sementara hari/jam/istirahat/durasi. Ringkasan keputusan, tabel pengaturan, dan alur kedua sisi diperbarui agar tidak lagi menyebut rekomendasi ini menunggu jawaban pengguna.
- Bagian6 sekarang memuat T01–T07: pertanyaan yang sudah diajukan, jawaban pengguna, perkembangan keputusan, dan contoh. Pertanyaan balik pengguna mengenai rekomendasi dan struktur JSON juga dicatat pada T06.
- P01–P10 memuat pertanyaan lanjutan dengan contoh serta status jawaban yang sebenarnya. Persetujuan sementara pengguna dibedakan dari ketentuan resmi divisi. Instruksi tambahan tentang scope, alur pemakaian, dan tahap pekerjaan ikut dicatat.
- Rescan setelah pembaruan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tidak berubah. Status Git hanya menunjukkan dokumen109 baru serta tiga perubahan lokal awal `/dadu` yang tetap utuh. `git diff --check` lulus. Nomor T01–T07/P01–P10, keberadaan contoh, status jawaban pertanyaan lanjutan, dan whitespace dokumen diperiksa. Tidak ada kode/database yang diubah; unit/feature test aplikasi tidak diperlukan untuk pembaruan dokumentasi ini.

### Pembaruan setelah konfirmasi pratinjau, informasi identitas, dan laporan data belum tersedia

- Sebelum pembaruan, Git/HEAD kedua folder diperiksa ulang; tetap sesuai sebelumnya. Model/profil TA, snapshot, importer akademik, master dosen, dan alur peminjaman dosen dibaca untuk menelusuri sumber informasi yang diminta.
- T08 mencatat jawaban pengguna tentang pratinjau, alternatif booking dosen, dan informasi pada pencarian/konfirmasi. P04 ditutup dengan rujukan T08; P06 diperjelas tanpa menganggap perubahan booking yang sudah disahkan telah diputuskan.
- Pemeriksaan baca saja terhadap JSON/Excel menemukan ketersediaan kode pembimbing dan kekosongan penguji seperti tabel pada bagian5. Tidak mencetak identitas mahasiswa/dosen ke dokumen. Ketidakcocokan header importer dibedakan dari data yang memang kosong.
- Instruksi melaporkan data yang belum tersedia dicatat. P11 berisi contoh pertanyaan sumber penguji/master dosen. JSON kehadiran tetap ditandai masih menunggu.
- P05 tentang daftar ruang telah diajukan dan belum dianggap dijawab.
- Rescan sesudah pencatatan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tetap. `git diff --check` lulus. Nomor T01–T08/P01–P11, contoh, status P04 sudah dijawab, serta whitespace diperiksa. Perubahan hanya dokumen109; tiga perubahan lokal awal `/dadu` tetap utuh. Tidak ada kode/database yang diubah atau unit/feature test implementasi yang diklaim.

### Pembaruan setelah jawaban ruang kelas saja

- Git/HEAD kedua folder diperiksa sebelum pencatatan. Model/request/form/importer ruang dan header/isi kategori Excel diperiksa baca saja.
- Jawaban pengguna dicatat pada T09 dan P05; ringkasan kebutuhan serta alur pencarian diperbarui. Pembatasan ke ruang kelas ditandai sebagai keputusan sementara yang masih akan dikonfirmasi kepada divisi.
- Temuan pengenalan jenis ruang ditambahkan: belum ada field kategori eksplisit, sedangkan seluruh60 nama master contoh memuat KELAS. Status aktif dan ketersediaan waktu tetap merupakan pemeriksaan berbeda.
- Pertanyaan pembatalan booking ketika persetujuan TA dibatalkan telah diajukan dengan contoh pada P07; menunggu jawaban. Tidak menganggap usulan pembatalan otomatis telah disetujui.
- Rescan setelah pencatatan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tidak berubah. `git diff --check` lulus. Nomor T01–T09/P01–P11, contoh, status jawaban P05 dan whitespace diperiksa. Hanya dokumen109 bertambah; tiga perubahan lokal awal `/dadu` tetap utuh. Kode/database belum berubah dan tidak ada klaim unit/feature test implementasi.

### Pembaruan setelah jawaban pembatalan booking dan permintaan konfirmasi divisi

- Jawaban pemilihan pembatalan otomatis, lalu instruksi mencatatnya sebagai konfirmasi juga, diterima setelah pencatatan T09.
- T10 menyimpan pertanyaan, kedua jawaban, contoh dampak, dan status keputusan sementara. P07 diperbarui agar tidak lagi menyatakan pertanyaan pokok belum dijawab.
- Ringkasan kebutuhan serta alur mahasiswa/admin mencantumkan pembatalan booking terkait dengan riwayat tetap tersimpan. Tidak memperluas aturan ke peminjaman dosen lain, booking yang sudah berjalan, atau perubahan rentang periode tanpa keputusan tambahan.
- Rescan sesudah pembaruan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tetap. `git diff --check` lulus. Nomor T01–T10/P01–P11, contoh, status P07 sudah dijawab sementara, dan whitespace diperiksa. Perubahan hanya dokumen109; kode/database dan perubahan lokal awal tetap utuh. Tidak menjalankan unit/feature test implementasi karena belum ada perubahan kode.

### Pembaruan setelah penetapan perubahan melalui SSC/Divisi Akademik

- Git/HEAD kedua folder diperiksa sebelum pembaruan dan tetap sesuai keadaan sebelumnya.
- T11 mencatat pertanyaan, jawaban pengguna, contoh pindah tanggal, serta batas jawaban. Ringkasan, alur mahasiswa/admin, P06, dan P08 diperbarui agar tidak lagi menyatakan hak mahasiswa mengubah/membatalkan belum diputuskan.
- Setelah konfirmasi, mahasiswa hanya melihat hasil/status dan menghubungi SSC untuk koreksi; tidak memperoleh akses endpoint perubahan/pembatalan. Perubahan dilakukan petugas Divisi Akademik yang berwenang. Tidak memperluas permintaan ini menjadi modul permintaan perubahan baru atau mengubah alur dosen.
- Rescan sesudah pembaruan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tetap. `git diff --check` lulus. Nomor T01–T11/P01–P11, contoh, jawaban P06 tentang perubahan melalui petugas, dan whitespace diperiksa. Perubahan hanya dokumen109; kode/database dan tiga perubahan lokal awal tetap utuh. Tidak menjalankan unit/feature test karena belum ada implementasi.
- Sesudah pencatatan T11, pilihan penerapan pola mingguan diajukan sebagai P12 sebelum rancangan lengkap ditulis. Rekomendasi tetap ditandai sebagai rekomendasi, bukan jawaban pengguna.

### Sebelum penyusunan rancangan110

- Git/HEAD kedua folder diperiksa ulang sebelum menyusun rancangan: `/dadu-test` hanya memiliki dokumen109 baru; `/dadu` tetap memiliki perubahan lokal awal `.gitignore`, `package-lock.json`, dan `phpunit.xml`. HEAD sesuai baseline.
- Importer, pemeriksaan kalender/bentrok, serta pengaturan periode akademik dibaca ulang untuk menentukan kebutuhan integrasi. Tidak menjalankan impor atau mengubah database.
- T12 mencatat pertanyaan dan pilihan pengguna. P12 ditutup dengan rujukan T12. Catatan sebelum pembuatan rancangan disimpan di sini; hasil pemeriksaan sesudah penulisan akan dicatat di dokumen110 dan diringkas kembali pada bagian ini.

### Sesudah penyusunan awal dan review rancangan110

- Dokumen110 dibuat sebagai rancangan untuk ditinjau, terhubung dua arah dengan catatan109. Keputusan pengguna dipisahkan dari pilihan teknis yang diusulkan asisten; belum ada instruksi mulai implementasi.
- Rescan setelah penulisan awal membandingkan620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline: isi dan HEAD tetap. Hanya dua dokumen ditambahkan. Tautan lokal, urutan T01–T12/P01–P12, whitespace dan `git diff --check` diperiksa.
- Satu agen peninjau independen membaca rancangan tanpa mengubah file. Pertanyaan penting mengenai peminjaman ganda jalur dosen/otomatis dicatat pada P13 dan diajukan kepada pengguna, dengan contoh serta pilihan. Tidak menganggap semua pertanyaan rancangan telah selesai.
- Rancangan juga memperjelas penjagaan endpoint lama, ketersediaan yang belum diketahui bila sumber belum siap, serta pemesanan setelah pendaftaran tutup/sebelum tanggal pelaksanaan.
- Rescan final setelah catatan review:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan baseline tetap sama; HEAD kedua folder tetap. Tautan109↔110, urutan T01–T12/P01–P13, contoh/status jawaban, whitespace dan `git diff --check` diperiksa dan lulus. Status Git hanya bertambah dokumen109/110; tiga perubahan lokal awal tetap utuh. Belum mengubah kode, database, stash, branch, atau menjalankan unit/feature test implementasi. Pertanyaan P13 tetap menunggu jawaban.

### Pembaruan sesudah jawaban penautan admin dan sebelum rencana111

- Git/HEAD kedua folder diperiksa sebelum pencatatan; keadaan kode sama seperti sebelumnya. Alur cancel/approve peminjaman dosen dan urutan lock TA dibaca untuk menentukan rincian integrasi.
- T13 mencatat pertanyaan, jawaban pengguna dan empat contoh. P13 ditutup sebagai pertanyaan yang sudah dijawab. Usulan teknis di dalam contoh dibedakan dari keputusan yang dinyatakan pengguna.
- Rancangan110 diperbarui dan rencana111 disusun memakai skill writing-plans. Keduanya menautkan catatan109. Dokumen hasil kode tetap terpisah dan belum dibuat.
- Review independen pembaruan110/111 tidak menemukan masalah penghambat; memeriksa dosen pemilik membatalkan pending, larangan mahasiswa, urutan lock dan perubahan link. Self-review rencana memperbaiki urutan task agar ketersediaan tersedia sebelum impor serta memastikan tidak ada callback memanggil class yang belum dibuat.
- Rescan sesudah penulisan:620 berkas `/dadu-test` dan637 berkas `/dadu` pada cakupan kode/config/test baseline tetap sama; HEAD kedua folder tetap. Tautan109↔110↔111, T01–T13/P01–P13, status P13 sudah dijawab, sembilan task belum dieksekusi, interface, whitespace dan `git diff --check` diperiksa dan lulus. Git hanya bertambah tiga dokumen; tiga perubahan awal `/dadu` tetap utuh. Kode/database, stash dan branch tidak diubah; unit/feature test implementasi tidak dijalankan.
