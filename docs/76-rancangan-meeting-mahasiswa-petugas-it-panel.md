# 76 — Penyesuaian meeting: mahasiswa, petugas, dan IT Panel

Status: disetujui pengguna. Pengguna memilih revisi mengikuti data EPRT terbaru dua arah (pilihan1A) dan menyetujui implementasi rancangan ini. Dokumen ini menjadi batas pekerjaan dan acuan rencana kode/test.

Hasil aktual 7 November 2026: implementasi dan audit independen seluruh branch selesai; temuan terakhir tentang contoh panduan kosong telah diperbaiki dan direview ulang tanpa temuan terbuka pada kode `a2684f5396f2d1482769d61171768e0c95829ab6`. Agen utama telah menyalin 77 berkas fitur ke checkout shared dan development asli, memverifikasi SHA256 setiap berkas serta melakukan rescan kode dan berkas terlindungi. Verifikasi sebelum perbaikan teks terakhir: 71 pemeriksaan lint PHP/Blade bersih, 18 test Node lulus, build sukses; suite PHP 741 lulus dan 30 kegagalan baseline identik menurut nama. Gate perbaikan teks terakhir terpisah: 20 test lulus, 174 assertion; suite penuh tidak diulang sesudahnya. Sinkronisasi tiga dokumen status akhir ini dan rescan hash akhirnya menyusul oleh agen utama. Browser/server dan migration/initializer DB aplikasi belum dijalankan; tidak ada merge/push/deploy. Detail hasil, urutan update operator dan checklist ada pada [hasil78](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md).

## 1. Tujuan dan batas pekerjaan

Mahasiswa mendapat empat tahap pendaftaran yang jelas, panel data statis yang tidak berulang, hasil pemeriksaan berwarna dengan nilai sebenarnya, revisi langsung ke ringkasan perbaikan, dan riwayat pengajuan yang ringkas. Petugas mendapat pengelompokan dokumen sesuai tahap baru dan tetap mempunyai audit lengkap. Superadmin dapat mengubah contoh, gambar, penjelasan, serta tautan form melalui IT Panel.

SSO dikerjakan teman pengguna pada branch lain. Pekerjaan ini tidak mengubah autentikasi, pengambilan profil SSO, atau pembuatan/penghubungan Student saat login. Data mahasiswa tetap berasal dari Student dan sumber sinkronisasi yang sudah ada.

Tidak menjalankan fresh/seed, menghapus database aplikasi, atau mengubah akun seeder. Perbaikan Dockerfile/panduan64/75 yang masih ada di workspace merupakan pekerjaan sebelumnya dan dipertahankan.

## 2. Keputusan pengguna yang telah dikonfirmasi

| Hal | Keputusan |
| --- | --- |
| Tahap1 | Identitas sinkronisasi terkunci; WA mahasiswa dan WA orang tua/wali tetap dapat diisi |
| Akhir periode | Hapus seluruh draft yang belum selesai, termasuk draft revisi |
| Batas EPRT | Nilai450 sudah memenuhi; surat untuk nilai di bawah450 setelah minimal3 tes |
| Pengelolaan contoh | Menu khusus **Panduan Form Sidang TA** pada IT Panel |
| Riwayat mahasiswa | Pengajuan dikirim → keputusan admin → revisi dikirim → keputusan admin berikutnya; pola berulang bila ada revisi lagi |

Keputusan terbaru tentang riwayat menggantikan daftar awal event per dokumen untuk mahasiswa. Verifikasi/penolakan dokumen tetap tercatat untuk petugas.

## 3. Pendekatan

Pilihan yang dibahas:

1. Menu khusus panduan form pada IT Panel, dengan konten terhubung ke bagian/dokumen TA. Dipilih pengguna karena gambar, PDF contoh, penjelasan, label tautan dan URL dapat dikelola langsung sesuai kebutuhan form.
2. Menggunakan modul Dokumen umum yang sudah tersedia. Tidak dipilih karena tetap membutuhkan pemetaan ke tiap bagian form dan membawa perubahan ke katalog/visibility dokumen umum.

Tetap gunakan Laravel/Blade/Alpine serta pola draft/review/preview privat yang sudah ada. Tidak menambah editor atau framework frontend baru. Aturan persyaratan dihitung server dan digunakan bersama oleh wizard, tinjau, finalisasi, serta halaman petugas.

## 4. Panel kanan dan pemeriksaan persyaratan

Panel kanan sepanjang wizard memuat:

- Data mahasiswa: nama, NIM, prodi, semester, email. Semuanya statis; tidak ada input kedua yang dapat mengubah identitas melalui request form.
- Detail TA: judul Indonesia, judul Inggris, pembimbing1, pembimbing2. Pembimbing yang tidak tersedia ditampilkan `-`.
- Ringkasan akademik: IPK, SKS lulus, TAK, nilai bahasa/EPRT tertinggi yang sah, hasil sidang tingkat2, KRS TA2, BPP pada periode yang diketahui.

Pemeriksaan persyaratan memakai nilai/status sebenarnya, bukan hanya tulisan `Memenuhi`: contoh `TAK: 80`, `SKS lulus: 140`, `EPRT: 470`, `Sidang tingkat2: Lulus`, `KRS TA2: Terdaftar`. Hijau berarti memenuhi, merah berarti tidak memenuhi. Data yang belum tersedia diberi label jelas dan tidak ditampilkan seolah memenuhi. Warna selalu disertai teks. Untuk mode surat yang sudah dilengkapi PDF sah, tampilkan nilai asli beserta keterangan `Surat permakluman terlampir`; jangan mengganti skor rendah menjadi skor lulus.

EPRT berada dalam grid yang sama dengan pemeriksaan lain dan mengikuti warna/statusnya. Status otomatis yang sebelumnya berupa kartu disabled/tanpa unggahan pada tahap2/3 dipindahkan ke ringkasan ini. Sidang tingkat2, persetujuan sidang iGracias, dan BPP tidak ditampilkan ulang sebagai kartu input di tiap tahap.

BPP tetap merupakan informasi hasil sinkronisasi; meeting tidak menetapkan BPP sebagai penghalang baru. Baca status dan periode_keuangan yang resmi, termasuk KREDIT; hapus kalimat periode belum terkonfirmasi bila sumbernya memang tersedia. Pembayaran periode lain tidak ditampilkan seolah membuktikan pembayaran periode aktif. Status SKPI dan aturan akademik yang sudah berlaku tetap diperiksa; tidak dihapus hanya karena tidak disebut pada daftar panel baru. Persetujuan iGracias memakai sumber persetujuan sidang, bukan approval foto BASILA.

## 5. Tahapan mahasiswa

| Tahap | Isi yang dapat diisi/diunggah | Tampilan bantuan |
| --- | --- | --- |
| 1 — Data Mahasiswa | Pilih periode yang terbuka; Nomor WA Aktif Mahasiswa; Nomor WA Aktif Orang Tua/Wali | Identitas ada di panel statis; label wajib dan pesan per input |
| 2 — Data TA di Igracias | Data TA di iGracias; Bimbingan Online yang Disetujui; SK TA Aktif; Draft Buku TA | Contoh file/gambar dan tautan yang dikelola IT Panel |
| 3 — Pembayaran & Nilai Bahasa | Bukti bayar wisuda dan tanggal pembayaran; Surat Permakluman bila diperlukan | Riwayat tes, status berlaku/kedaluwarsa, tautan template surat |
| 4 — Pernyataan Biodata Dummy Ijazah | Pilihan sudah/belum sesuai; lampiran pernyataan/biodata pada cabang sudah sesuai; bukti PDM dan tanggal pengajuan PDM pada cabang belum sesuai | Contoh dummy ijazah dan contoh PDM dari IT Panel |

SK TA dan draft buku dipindah dari tahap3 ke tahap2. Istilah `Capture` di label mahasiswa dihapus. Seluruh unggahan mahasiswa pada tahap2 hanya PDF; contoh bantuan superadmin tetap dapat berupa gambar atau PDF.

### Validasi dan penyimpanan

Semua label isian/unggahan wajib mempunyai `*`, pesan error Indonesia per field, error summary, dan penanda invalid. Terapkan aturan di server dan bantu pengguna melalui browser; pembatasan browser bukan satu-satunya validasi.

Nomor WA disimpan sebagai string agar nol depan tidak hilang. Pilihan implementasi: digit0–9 saja, panjang8–15 digit, contoh `08...` atau `62...`; bukan input number yang menerima notasi angka. Huruf, tanda baca, dan request buatan yang tidak valid ditolak dengan pesan khusus. Tidak menambahkan pemeriksaan kepemilikan nomor/OTP.

Identitas dari panel tidak dapat ditimpa lewat request. Kontak WA dapat diisi pada tahap1; untuk revisi, perubahan kontak akan diizinkan tanpa membuka perubahan identitas, periode asal, atau cabang biodata pengajuan. Dokumen yang sudah terverifikasi tetap terlindungi dari penggantian tanpa permintaan revisi.

Draft tetap boleh disimpan parsial untuk berkas yang belum lengkap. Kelengkapan tahap menentukan tombol lanjut/tinjau, dan server memeriksa kembali sebelum pengiriman. Nomor kontak yang diwajibkan untuk memulai draft tetap harus valid. Kesalahan tipe/ukuran/tanggal tidak disembunyikan sebagai sekadar draft belum lengkap.

Tanggal pengajuan PDM wajib pada cabang `belum sesuai`, bertipe tanggal dan tidak melewati hari ini. Nilainya disimpan di payload draft serta pengajuan final/snapshot. Beralih ke `sudah sesuai` melepaskan tanggal/bukti PDM dari kelengkapan cabang aktif tanpa menghapus berkas pengajuan final lama. Lampiran pernyataan/biodata yang sebelumnya sudah disyaratkan tetap dipakai; catatan meeting tidak meminta keduanya dihapus.

Pilihan implementasi untuk pengajuan lama: tanggal PDM yang belum pernah tersimpan tetap kosong pada detail historis. Jika pengajuan itu direvisi pada cabang `belum sesuai`, tampilkan input tanggal yang dapat diisi dan wajib dilengkapi sebelum kirim revisi. Jangan mengunci field kosong lalu menolak submit karena field tersebut kosong. Dokumen verified yang dipinjam tetap dilindungi; snapshot lama tidak berubah sampai revisi berhasil dikirim.

## 6. Nilai bahasa, riwayat, dan bug surat permakluman

Aturan EPRT yang dikonfirmasi: nilai sah tertinggi minimal450 memenuhi tanpa surat; nilai di bawah450 dengan minimal3 tes terkonfirmasi meminta surat PDF; percobaan belum cukup menampilkan arahan tes lagi. Mode unknown tetap jelas ketika data tidak lengkap. Perilaku ECCT/null dan jenis tes lain yang telah didukung tidak dikonversi menjadi skala EPRT dengan angka rekaan.

Riwayat menampilkan jenis tes, skor, tanggal tes bila tersedia, tanggal kedaluwarsa bila tersedia, serta `Berlaku`/`Tidak berlaku` dari sumber. Sumber resmi yang sudah diterima belum selalu membawa tanggal tes; tampilkan `Tanggal tes belum tersedia`, bukan memakai tanggal kedaluwarsa sebagai tanggal tes. Tes tidak berlaku atau subkomponen yang tidak sah tidak dipilih sebagai nilai tertinggi yang memenuhi. Jumlah tes mengikuti jumlah yang dikonfirmasi sumber; panjang array riwayat bukan pengganti jumlah percobaan.

Data ringkasan resmi dan riwayat harus dibaca oleh satu penyedia fakta untuk panel kanan, pemeriksaan, upload surat, tinjau, serta submit. Sesuai permintaan nilai tertinggi, ringkasan EPRT440 dengan rekaman EPRT470 yang sah/masih berlaku menghasilkan470. Ini bukan konflik hanya karena angkanya berbeda. Jangan mengambil maksimum mentah lintas IELTS/TOEIC/EPRT. Identitas jenis/skala yang tidak jelas, status masa berlaku yang bertentangan, atau data yang tidak cukup tidak boleh diubah menjadi lulus. Jumlah percobaan tetap berasal dari sumber terkonfirmasi.

Ketika nilai terbaru yang sah sudah memenuhi, kartu upload surat tidak ditampilkan dan server tidak lagi mewajibkannya untuk draft yang sedang dikerjakan, termasuk revisi. Surat yang telah tersimpan tidak boleh membuat dokumen lain terhapus. Pengajuan final yang sudah dikirim tetap memakai snapshot dan berkas historisnya; hasil sinkronisasi tidak menulis ulang pengajuan itu secara diam-diam.

Untuk revisi, peralihan dari mode surat ke mode memenuhi harus dilakukan konsisten di tampilan, daftar required document, pemeriksaan petugas, dan snapshot baru ketika revisi dikirim. Bukan sekadar menyembunyikan input surat dengan JavaScript. Dokumen terverifikasi yang tidak direvisi tetap dipertahankan.

Revisi mengikuti data EPRT terbaru **dua arah**. Jika hasil yang dulu memenuhi kini kedaluwarsa/tidak memenuhi, syarat terbaru diberlakukan juga: surat dapat menjadi wajib atau pengiriman ditahan untuk tes lagi/data belum terkonfirmasi. Jalur revisi mengizinkan unggah surat yang baru diwajibkan walaupun surat itu bukan target penolakan dokumen lama. Surat yang masih diperlukan dan sudah verified tetap dipakai; surat yang ditolak tetap membutuhkan pengganti. Snapshot baru mencatat keputusan terbaru saat kirim revisi berhasil.

## 7. Tinjau dan konfirmasi pengiriman

Halaman tinjau menampilkan nilai/status nyata dengan warna yang sama seperti wizard. Ringkasan otomatis hanya satu kelompok; dokumen unggahan menjadi kelompok tersendiri sesuai tahap. Tidak ada kartu otomatis ganda yang mengulang pemeriksaan persyaratan.

Modal sebelum pengiriman memuat checkbox wajib dengan pernyataan pengguna:

> Saya MENYATAKAN bahwa saya telah memastikan dan memeriksa kembali seluruh berkas telah lengkap dan sesuai dengan ketentuan. Jika berkas tidak lengkap, maka saya siap tidak disertakan pada sidang tugas akhir periode terdekat.

Reminder kebenaran data dan penerimaan konsekuensi kesalahan melengkapi modal yang sama. Checkbox tidak dicentang otomatis. Tanpa persetujuan, kirim ditolak baik di browser maupun server dengan pesan jelas. Simpan waktu dan versi teks persetujuan pada pengiriman awal/revisi; pengajuan historis tidak diberi persetujuan palsu.

## 8. Detail revisi dan riwayat

Pada status perlu revisi, tombol utama mengarah langsung ke **Perbaikan Pengajuan Anda**, berisi ringkasan catatan dan dokumen yang perlu diganti. Hilangkan halaman detail perantara dari perjalanan mahasiswa menuju revisi. Tampilan detail/status pengajuan yang sudah dikirim tetap tersedia untuk status lain dan kebutuhan petugas. Tidak membuat draft atau mengubah data melalui request GET.

Kartu yang sekarang berjudul Aksi pada konteks revisi diganti menjadi **Detail Revisi**. Mahasiswa dapat melihat alasan dan target perbaikan, lalu mulai/lanjutkan revisi. Dokumen yang sudah terverifikasi tidak dibuka untuk penggantian bebas.

Tambahkan tombol **Riwayat** di samping aksi pada daftar pengajuan. Tampilkan timeline seperti gambar referensi: terbaru di atas, waktu WIB, jenis kejadian, pelaku, dan catatan keputusan yang relevan. Filter riwayat dilakukan di server; event audit per dokumen tidak dikirim ke payload timeline mahasiswa.

| Event tersimpan | Label mahasiswa |
| --- | --- |
| submitted | Pengajuan pendaftaran sidang dikirim |
| needs_review | Pengajuan diminta revisi oleh admin |
| approved | Pengajuan disetujui admin |
| resubmitted | Pengajuan revisi dikirim |

Siklus needs_review/resubmitted/approved dapat berulang. Status penolakan/akhir tetap terlihat pada badge dan detail keputusan; audit lengkap, termasuk document_verified/document_rejected/rejected/auto-rejected, tetap tersedia bagi petugas. Mahasiswa hanya boleh membaca riwayat pengajuan miliknya. Riwayat petugas tetap mengikuti hak review yang sudah ada.

## 9. IT Panel — Panduan Form Sidang TA

### Tambahan halaman review petugas

Pengguna menambahkan bahwa halaman Review Sidang TA mengikuti penempatan elemen pada Review Yudisium: header atas hanya judul/penjelasan; tombol Ekspor Excel ditempatkan pada kartu pencarian/filter; tombol Kelola Periode berada pada kartu periode. Kartu periode memuat countdown waktu pendaftaran yang berjalan dan status belum dibuka/aktif/ditutup, memakai jadwal periode yang ditampilkan dan timezone yang benar. Jika tidak ada periode, jangan membuat countdown rekaan. Export tetap mengikuti filter dan permission yang berlaku. Ini merupakan tambahan scope yang langsung diminta pengguna setelah persetujuan rancangan, bukan perubahan alur SSO.

### Pengelolaan konten bantuan

Superadmin/Akademik dapat membuat, melihat, mengubah, dan menghapus konten bantuan per slot form. Petugas dan mahasiswa menggunakan panduan pada form, tanpa mendapat CRUD konten.

Setiap entri panduan berisi bagian/dokumen tujuan, judul, penjelasan singkat, gambar/PDF contoh opsional, label tautan, URL opsional, urutan, dan status aktif. Slot ditetapkan untuk bagian form dan jenis dokumen yang dikenal, termasuk contoh dummy ijazah yang tampil sebelum pilihan cabang. Pengelolaan contoh tidak mengubah aturan wajib, tipe upload mahasiswa, atau syarat kelayakan.

URL ditampilkan sebagai label seperti `Lihat contoh`, `Unduh template`, atau `Ajukan PDM`, bukan alamat panjang. Tautan hanya HTTP/HTTPS yang valid. Penjelasan dirender sebagai teks aman; tidak memerlukan HTML bebas/editor baru.

Gambar/PDF bantuan disimpan pada storage persisten, bukan disalin ulang ke source setiap edit. Preview/unduh menggunakan route aplikasi dengan izin baca. Mengganti atau menghapus contoh tidak boleh menyentuh unggahan mahasiswa. Superadmin mendapat error jelas untuk tipe/ukuran file yang tidak sesuai.

Konten awal memakai dua gambar panduan yang memang sudah tersedia dan tautan template [LAA UPPS Surabaya](https://linktr.ee/laa.upps.sby). Halaman tersebut memuat tautan surat permakluman dan PDM; contoh lain yang belum ada harus ditandai belum diatur, tanpa tautan rusak atau contoh resmi rekaan. Inisialisasi konten dilakukan dengan command khusus yang idempotent; tidak menjalankan ulang seeder akun/password.

## 10. Pembersihan draft sesudah periode berakhir

Saat `now > close_at`, seluruh draft **aktif/belum selesai** pada periode itu dihapus, baik awal maupun revisi. Tepat pada close_at mengikuti batas yang sekarang masih menerima pengiriman; setelahnya ditutup. Periode mendatang atau yang hanya dinonaktifkan sebelum close_at tidak langsung dianggap kedaluwarsa.

Pilihan implementasi: metadata consumed yang merupakan tanda terima finalisasi dipertahankan untuk idempotensi; metadata tersebut bukan draft aktif yang ditampilkan mahasiswa. Pengajuan final, log pengajuan, dan dokumen final tidak ikut dihapus. Ini termasuk file final yang dipinjam oleh draft revisi melalui source_document_id.

Gunakan transaksi dan urutan lock yang konsisten dengan submit agar cleanup tidak menghapus draft yang sudah berhasil difinalisasi. Hapus file khusus draft sesudah commit, hanya jika tidak dipakai dokumen final atau draft lain. Sediakan mode pratinjau pada command; scheduler menjalankan cleanup otomatis. Perintah penolakan revisi lewat deadline yang sudah ada tetap konsisten dan idempotent.

## 11. Kompatibilitas dan pemeriksaan

Perubahan ini tidak boleh merusak pengajuan yang sudah tersimpan. Pengelompokan tahap berubah, tetapi kode identitas dokumen dipertahankan. Tanggal PDM dan persetujuan baru boleh kosong pada pengajuan historis dan tidak direka. Pembatasan PDF berlaku pada unggahan baru; berkas historis yang sebelumnya diterima tetap dapat ditinjau. Penanganan revisi lama mempertahankan berkas terverifikasi serta membuka input tanggal PDM yang diwajibkan untuk pengiriman revisi baru, sesuai bagian5.

Pemeriksaan yang diperlukan sebelum selesai:

- Validasi WA kosong, huruf, format salah, digit yang sah, nol depan, request buatan, dan pesan per field.
- Tahap2 berisi empat jenis upload; gambar ditolak sebagai unggahan mahasiswa; contoh panduan gambar/PDF tetap dapat dibaca.
- Nilai449/450/451, kurang3/minimal3 tes, tes tidak berlaku, riwayat tanpa tanggal, ECCT dan jenis tes berbeda; waiver tidak tersisa ketika nilai sah sudah memenuhi.
- Cabang biodata sesuai/tidak sesuai, tanggal PDM wajib/kosong/masa depan, pergantian cabang, dan berkas lama terlindungi.
- Consent wajib pada server dan modal; submit ulang tetap idempotent; pesan merah/hijau konsisten dengan nilai/status nyata.
- Riwayat mahasiswa hanya event yang disepakati dan hanya milik sendiri; petugas tetap mendapat audit penuh.
- Hak superadmin atas CRUD panduan, penolakan akses role lain, validasi URL/upload, penggantian file tanpa menyentuh berkas mahasiswa.
- Cleanup draft awal/revisi, borrowed file, consumed receipt, batas close_at, perpanjangan periode, pengiriman bersamaan, dan pengulangan scheduler.
- Lint, test PHP/Node yang relevan, build aset, serta panduan migration/update biasa dan rescan kedua repo. Tidak memakai fresh sebagai langkah deployment fitur.

## 12. Bagian source yang akan disentuh

Registry persyaratan, fakta/policy bahasa, validasi draft/final, service revisi/finalisasi, model/snapshot PDM/consent, query/controller riwayat, wizard/tinjau/panel kanan/petugas, komponen JS, command cleanup+scheduler, serta modul baru panduan IT Panel dan permission/menu terkait.

Auth/LoginRequest, SatuService, penghubung Student saat SSO, dan pekerjaan branch SSO teman pengguna berada di luar daftar tersebut. Rancangan telah diperiksa dan disetujui; implementasi mengikuti rencana lanjutan.

## 13. Keputusan EPRT revisi yang telah disetujui

Pengguna menjawab **1A: ikuti data terbaru**. Pilihan mempertahankan syarat asal saat nilai/status menurun tidak digunakan. Persetujuan rancangan diberikan dengan jawaban **2: setuju lanjut**.
