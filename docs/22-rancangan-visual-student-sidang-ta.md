# 22 — Rancangan visual student Sidang TA

Tanggal: 2 November 2026. Status: **disetujui pengguna melalui jawaban lanjutkan; panduan G1 tersedia pada [23](23-checkpoint-g1-visual-wizard-student.md)**.

Dokumen ini menerjemahkan daftar visual pengguna menjadi rancangan konkret sesudah rescan [21](21-rescan-f-siap-visual-student.md). Asisten hanya menulis Markdown. Kode aplikasi kedua folder belum diubah oleh asisten. Panduan [23](23-checkpoint-g1-visual-wizard-student.md) memuat langkah G1, command file baru, kode lengkap, dan test lengkap untuk perilaku yang berubah. G2 menunggu implementasi dan rescan G1.

## 1. Arah tampilan dan pilihan pendekatan

Gunakan warna merah kampus, card putih, border slate, dan tombol yang sudah dipakai aplikasi. Sasaran: alur empat tahap mudah dibaca, informasi akademik selalu tersedia, upload jelas membedakan pilihan baru dengan file yang benar-benar tersimpan, dan detail pengajuan tidak lagi berupa daftar key mentah.

| Pendekatan | Dampak | Rekomendasi |
| --- | --- | --- |
| Rapikan Blade/Alpine Sidang TA yang sudah ada; ambil pola visual upload Yudisium | Perubahan terlokalisasi; kontrak draft, route, dan finalisasi tetap dipakai | **Disarankan** |
| Jadikan upload dan panel akademik komponen bersama TA/Yudisium sekaligus | Perlu mengubah dan menguji ulang Yudisium; kedua modul memiliki field dan ketentuan berbeda | Tunda sampai memang diperlukan |
| Tambahkan library wizard/dropzone baru | Menambah dependency dan integrasi dengan input multipart yang sekarang sudah bekerja | Tidak diperlukan untuk daftar kebutuhan ini |

Pilihan pertama disetujui bersama rancangan ini melalui jawaban lanjutkan. Detail kecil seperti ikon dan jarak dapat diputuskan saat menulis panduan; tidak perlu mengganti framework atau membuat endpoint upload baru.

## 2. Temuan scan yang mendasari rancangan

| Lokasi aktual | Temuan | Implikasi |
| --- | --- | --- |
| `wizard.blade.php`, navigasi tahap | Cabang link mensyaratkan `$draft`. Tanpa draft, tahap 1 masuk cabang span abu-abu meskipun punya `aria-current` | Warna aktif harus ditentukan terpisah dari boleh/tidaknya elemen menjadi link |
| `wizard.blade.php`, card data mahasiswa | Identitas, informasi penyimpanan, dan catatan petugas dicampur dalam satu card lebar | Pisahkan navigasi/status simpan, panel akademik kanan, dan catatan revisi |
| `partials/wizard-upload.blade.php` | Masih input file native terlihat; beberapa error dokumen sudah ada | Ubah tampilan dan interaksi upload; tetap gunakan field/route yang sama |
| `graduation_registrations/create.blade.php` dan CSS `.upload-zone` | Ada pola kotak putus-putus dengan input file transparan, nama pilihan, dan tombol batal | Ambil bentuk visualnya; jangan menyalin ketentuan Yudisium atau menganggap CSS saja membuktikan drag/drop bekerja |
| `SaveSidangTaDraftStepRequest` dan service dokumen | Validasi backend dan error key `documents.<type>` sudah tersedia | Tampilkan error di bawah field tanpa mengurangi validasi server |
| `form.blade.php` lama | Panel kanan lengkap masih tersedia, tetapi form ini tidak lagi dipakai | Ambil susunan informasinya saja, bukan menghidupkan pipeline submit lama |
| `SidangTaAcademicSnapshot` | Sudah mempunyai identitas, judul dua bahasa, pembimbing/penguji, skor/status, jadwal dan waktu sumber | Panel baru dapat memakai sumber TA yang sama; hindari query tersebar di Blade |
| `show.blade.php` | Snapshot di-loop dan underscore diganti spasi; angka/status tidak dibedakan | Gunakan label dan kelompok yang eksplisit dengan pembacaan snapshot historis |

## 3. Halaman Mulai Pendaftaran

Sketsa desktop; bukan screenshot hasil implementasi:

```text
┌─────────────────────────────────────────────────────────────────────┐
│ Tahap 1  >>  Tahap 2  >>  Tahap 3  >>  Tahap 4                       │
│ Kontak       Verifikasi  Dokumen     Biodata                         │
├─────────────────────────────────────────────────────────────────────┤
│ Terakhir disimpan … · Tekan Simpan agar progres dapat dilanjutkan.  │
└─────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────┐ ┌───────────────────────────┐
│ Tahap 1 — Data dasar & kontak         │ │ Data mahasiswa            │
│ Isian bertanda * wajib diisi.         │ │ Nama, NIM, prodi, email…  │
│                                       │ ├───────────────────────────┤
│ Periode Sidang TA *                    │ │ Judul TA: ID dan EN       │
│ [Pilih periode …             v]       │ ├───────────────────────────┤
│                                       │ │ Pembimbing dan penguji    │
│ HP mahasiswa *    HP orang tua/wali * │ ├───────────────────────────┤
│ [08……………]        [08……………]          │ │ Ringkasan akademik        │
│                                       │ ├───────────────────────────┤
│ [Kembali]        [Simpan & Lanjutkan]  │ │ Jadwal sidang TA          │
└───────────────────────────────────────┘ └───────────────────────────┘
```

### Card tahapan dan status penyimpanan

- Satu card luar berisi urutan horizontal **Tahap 1 >> Tahap 2 >> Tahap 3 >> Tahap 4**. Nama tahap menjadi keterangan kecil di bawah masing-masing label.
- Tahap aktif berwarna merah kampus dengan teks putih. Tahap sebelum aktif memakai merah lembut dan penanda selesai; chevron setelah setiap tahap hingga tahap aktif berwarna merah. Contoh tahap 1: label Tahap 1 dan `>>` sesudahnya sudah menyala. Sesudah tahap 4 tidak ada chevron.
- Penanda selesai mengacu prasyarat yang tersimpan, bukan sekadar pernah mengunjungi halaman. Tahap yang dapat diakses tetapi berada setelah tahap yang sedang dibuka tetap netral; tahap belum terbuka abu-abu dan bukan link. Tetap satu `aria-current="step"`.
- Saat baru membuka Mulai Pendaftaran, tahap 1 sudah aktif walaupun draft belum ada. Tidak perlu membuat draft kosong agar warnanya aktif.
- Gunakan aturan akses `$maxStep` yang sudah ada. Styling tidak membuka tahap berikutnya sebelum prasyarat tersimpan. Guard server dan konfirmasi meninggalkan perubahan tetap berlaku.
- **Rekomendasi status simpan: footer di dalam card tahapan**, dipisah garis border. Ini menjaga status progres dekat navigasi tanpa menambah card pendek yang memecah halaman.
- Draft belum ada: **Belum ada data yang disimpan.** Draft ada: **Terakhir disimpan DD/MM/YYYY HH:mm.** Baris bantuan: **Tekan Simpan agar data dan file dapat dilanjutkan setelah halaman ditutup.** Jangan menampilkan waktu palsu atau memberi kesan autosave.
- Pesan **Ada perubahan yang belum disimpan** diletakkan di footer tersebut; saat menyimpan gunakan **Sedang menyimpan…**. Jika gagal, indikator tidak boleh tetap busy. Status sukses tidak menutupi error.
- Waktu mengikuti kebijakan aplikasi saat ini. Perubahan visual ini tidak mengonversi tanggal atau timezone database.
- Mobile mempertahankan urutan satu baris melalui scroll horizontal di dalam card, tanpa membuat seluruh halaman melebar. Tahap aktif harus dapat terlihat saat halaman dibuka dan navigasi keyboard tetap dapat dipakai.

### Dua kolom dan panel informasi kanan

- Desktop lebar: sekitar dua pertiga untuk form dan sepertiga untuk panel kanan. Gunakan batas lebar yang cukup untuk dua kotak upload di kolom form.
- Layar kecil: form dahulu, panel informasi setelahnya dalam aliran halaman. Tidak menyembunyikan panel pada tahap 2–4. Tidak memakai sticky untuk seluruh tumpukan panel yang lebih tinggi dari viewport.
- Panel kanan **read-only**, selalu ada pada keempat tahap. “Statis” berarti bukan isian yang diedit/berubah mengikuti tab tahap; nilai tetap berasal dari data akademik yang tersedia saat halaman dimuat, bukan teks hardcode.
- Susunan panel: **Data mahasiswa**, **Judul Tugas Akhir**, **Pembimbing & Penguji**, **Ringkasan Akademik**, **Jadwal Sidang TA**. Kembalikan judul ID/EN, semester, email, waktu sumber, penguji, TAK/EPrT, dan status yang dulu tersedia di panel lama.
- Panel wizard membaca data akademik mahasiswa melalui sumber TA yang sudah ada. Nilai tidak tersedia diberi **Belum tersedia**; nilai angka nol tetap ditampilkan sebagai nol. Jangan menyalin key khusus Yudisium yang tidak ada pada sumber TA.
- Catatan petugas pada revisi menjadi alert tersendiri di atas form. Catatan tidak dimasukkan ke panel identitas atau footer simpan.

### Tahap 1: ukuran input

- Periode menempati satu baris tersendiri dengan lebar maksimum sekitar 30–32rem, mengikuti ruang yang tersedia; bukan memenuhi seluruh halaman. Nama periode tetap terlihat dan rentang buka/tutup bisa menjadi keterangan di bawah select agar opsi tidak terlalu panjang.
- Dropdown tetap memuat seluruh periode terbuka. Periode tersimpan yang sudah ditutup tetap diberi keterangan sebagaimana perilaku sekarang. Revisi menampilkan periode asal read-only, bukan membuka pilihan periode baru.
- HP mahasiswa dan HP orang tua/wali berdampingan di desktop, masing-masing sekitar 16–18rem; pada layar sempit ditumpuk. Label, input, bantuan dan error rata kiri.
- Nomor HP tetap string dengan inputmode telepon, bukan input angka yang menghilangkan awalan nol. Batas dan aturan data tidak diperketat tanpa keputusan baru.

### Tahap 3 dan 4

- Tanggal pembayaran wisuda di baris tersendiri, lebar maksimum sekitar 16–18rem. Tetap tidak menerima tanggal masa depan dan menampilkan error tepat di bawah input.
- Semua upload tahap 2, 3, dan 4 menggunakan pola yang sama. Ketentuan tahap 2 PDF/JPG/JPEG/PNG, tahap 3–4 PDF, maksimal 10 MB per file tetap mengikuti backend TA.
- Pilihan biodata tahap 4 menjadi dua pilihan radio yang cukup besar untuk diklik, dengan satu label grup wajib. Cabang aktif menampilkan upload yang relevan. Cabang tersembunyi tetap disabled agar tidak ikut multipart/validasi browser; file tersimpan tidak dihapus hanya karena pilihan cabang belum disimpan.
- Tombol Simpan tahap 4 dan Tinjau tetap jelas berbeda: perubahan harus tersimpan sebelum tinjau dapat dijalankan. Jangan melewati review version atau mengubahnya menjadi satu submit langsung.

## 4. Required dan error pada tahap 1–4

- Setiap label isian wajib diberi bintang merah; untuk radio letakkan pada legend grup. Sertakan satu keterangan **Isian bertanda * wajib diisi** agar maknanya tidak bergantung pada warna.
- Dokumen wajib tetap diberi bintang meskipun berkasnya sudah tersimpan. Tambahkan keterangan bahwa berkas sudah memenuhi isian dan pilihan baru hanya untuk mengganti; jangan memaksa upload ulang.
- Nonaktifkan popup validasi HTML melalui `novalidate` pada form save wizard. Jangan memakai `reportValidity()`/`alert()` untuk error isian. Atribut semantik wajib/required boleh tetap ada; server tetap memutuskan validitas.
- Gunakan error backend di bawah field terkait: periode, kedua HP, tanggal, pilihan biodata, dan setiap dokumen. Pesan bahasa Indonesia yang spesifik; contoh **Pilih periode sidang TA**, **Nomor HP mahasiswa wajib diisi**, atau **Unggah dokumen ini terlebih dahulu**.
- Untuk draft versi lama, konflik, atau error tingkat form seperti `version`/`documents` parent, tampilkan alert umum di atas form. Jangan menghilangkan error hanya karena tidak ada input yang cocok.
- Backend dokumen saat ini dapat melaporkan dokumen wajib pertama yang belum ada. Rancangan ini tidak menjanjikan seluruh error dokumen muncul sekaligus dan tidak mengganti transaksi upload untuk mengejar tampilan.
- Saat respons error, input teks/opsi memakai old input dan version lama tetap dipertahankan sesuai regresi sebelumnya. File pilihan browser yang ditolak tidak bisa dipulihkan otomatis: tampilkan pesan perlu memilih ulang; file yang telah tersimpan tetap tersedia.
- Field error memakai border merah, `aria-invalid`, dan hubungan label/bantuan/error yang benar. Setelah kembali dari validasi server, arahkan fokus ke field invalid pertama yang terlihat; bila hanya error umum, fokus ke ringkasan. Pengguna keyboard tidak perlu mencari posisi error sendiri.

## 5. Upload: kotak pilih/drag & drop dan tombol berkas

Satu kartu dokumen memiliki urutan tetap:

1. Label wajib dan deskripsi persyaratan, dengan tinggi yang wajar untuk label panjang.
2. Jika tersedia: nama **Berkas tersimpan** beserta ukuran dan tombol sekunder **Unduh berkas**. Gunakan link download yang ditata sebagai tombol, bukan tombol submit.
3. Kotak border putus-putus dengan ikon upload, teks **Klik untuk memilih atau seret file ke sini**, format, dan ukuran maksimum.
4. Jika ada pilihan baru: nama file, ukuran, badge **Belum disimpan**, serta tombol **Batalkan pilihan baru**.
5. Error dokumen tepat di bawah kotak/pilihan.

Keputusan usulan: **nama file tetap teks, tindakan Unduh dan Batalkan berbentuk tombol**. Mengubah seluruh nama file menjadi satu tombol panjang menyulitkan pemindaian dan nama panjang dapat merusak lebar card. Nama file boleh membungkus, sedangkan aksi tetap terbaca.

Perilaku yang wajib dijaga:

- Klik area membuka pemilih file; keyboard dapat memfokuskan input dan mengaktifkannya. Fokus terlihat pada kotak.
- Drag/drop benar-benar memasukkan tepat satu file ke input multipart yang dipakai submit, bukan hanya mengganti nama dalam Alpine. Uji kedua cara pemilihan dengan file yang sama.
- Drop beberapa file atau non-file ditolak dengan pesan di bawah kotak dan tidak menimpa pilihan valid sebelumnya. Jangan diam-diam memilih file pertama dari beberapa file.
- Format/ukuran dapat diperiksa untuk umpan balik lokal cepat; validasi MIME, ukuran, whitelist jenis dokumen, dan kepemilikan di server tetap berlaku.
- Kotak saat drag diberi highlight merah lembut; file **baru terpilih** belum diberi pesan berhasil upload. Berkas tersimpan dapat memakai indikator hijau yang terpisah.
- **Batalkan pilihan baru** hanya mengosongkan input browser, tidak memanggil endpoint hapus dan tidak menghapus berkas tersimpan. Setelah batal, file tersimpan tetap dapat diunduh. Tidak perlu konfirmasi hapus untuk pilihan yang belum tersimpan.
- Tidak menambahkan tombol hapus dokumen wajib tersimpan pada tahap ini. Penggantian baru berlaku setelah save berhasil.
- Saat mengganti cabang tahap 4, hindari field tersembunyi menjadi fokus/error aktif. Saat menyimpan, hanya cabang yang dipilih yang dikirim.
- Pada browser/perangkat yang tidak mendukung drag/drop, klik pemilih file tetap menjadi jalur utama. Tombol batal/download berada di luar lapisan input transparan agar klik tidak membuka pemilih file.

## 6. Tinjau pendaftaran

- Semua tautan **Unduh** menjadi tombol sekunder yang sama dengan detail pengajuan TA. Semantiknya tetap link ke download privat.
- Label dokumen dan nama file di kiri, ukuran dalam teks pendukung, tombol di kanan; pada mobile tombol dapat turun ke baris berikutnya. Gunakan bungkus kata untuk nama panjang.
- Identitas, kontak dan periode ditata sebagai pasangan label/nilai. Kelompok dokumen tetap mengikuti tahap 2, 3, dan cabang tahap 4.
- Pertahankan pesan bahwa yang ditampilkan adalah draft tersimpan dan pendaftaran belum dikirim. Tombol **Kirim Pendaftaran** tetap hanya ada pada tinjau dan menggunakan kontrak finalizer sekarang.

## 7. Detail: Data akademik saat pengajuan

Rekomendasi asisten: satu section induk dengan judul dan keterangan **Data direkam saat pengajuan dikirim**, lalu kelompok berikut. Tidak membuat satu card kecil untuk setiap key snapshot.

| Kelompok | Isi dan tampilan |
| --- | --- |
| Identitas akademik | Nama, NIM, prodi, semester dan email; pasangan label/nilai dalam dua kolom |
| Ringkasan akademik | Empat kotak angka ringkas IPK, SKS lulus, TAK, EPrT; angka menonjol, label kecil; nilai tidak tersedia dibedakan dari nol |
| Status akademik | Sidang tingkat 2/3, BPP, SKPI, i-Basila dan informasi ketersediaan TAK; daftar/badge status, bukan badge untuk seluruh angka |
| Judul Tugas Akhir | ID dan EN pada area lebar penuh agar judul panjang mudah dibaca |
| Pembimbing & Penguji | Daftar pembimbing dan daftar penguji berdampingan bila ruang cukup; nama boleh membungkus |
| Jadwal sidang | Tanggal, waktu dan lokasi sebagai label/nilai; fallback jika belum tersedia |
| Sumber data | Waktu sumber akademik yang tersimpan pada snapshot; label jujur bahwa ini waktu sumber, bukan waktu submit |

- **Wajib membaca `$registration->academic_snapshot`**. Jangan memanggil snapshot mahasiswa terbaru untuk menggambar detail historis: perubahan data kampus setelah submit tidak boleh mengubah isi detail pengajuan lama.
- Gunakan pemetaan label key yang eksplisit. Snapshot lama yang kehilangan key tetap dapat dirender dengan fallback. Nilai `null`/kosong menjadi **Belum tersedia**, nol tetap nol, dan key tambahan yang belum dikenal ditampilkan dalam area **Informasi tambahan** dengan fallback label yang aman agar data historis tidak diam-diam hilang.
- Angka yang tersedia diformat sesuai jenisnya; jangan menganggap nilai kosong sebagai angka nol atau status gagal. Status tak dikenal/netral diberi warna netral. Tidak menambahkan gate akademik baru atau membuat klaim “memenuhi syarat” dari dekorasi warna.
- Semua teks tetap melalui escaping Blade; judul, nama, catatan dan nama file panjang tidak memecah card.
- Sekaligus benarkan visibilitas **Perbaiki Pengajuan**: hanya untuk pengajuan `needs_review`, milik mahasiswa yang terhubung dengan akun, dan memiliki permission update. Gunakan pemetaan identitas yang sama dengan backend, termasuk fallback username/employee_id; jangan menebak dari email saja. Reviewer yang tidak memiliki data mahasiswa tidak boleh gagal membuka detail hanya karena menghitung visibilitas tombol. Guard endpoint tetap dipertahankan.

## 8. Tipografi dan perapian yang diusulkan

- Judul section 16–18px semibold/bold; label 13–14px; isi/input 14px; bantuan 12–13px. Hindari hampir semua isi memakai 10px dan uppercase panjang.
- Jarak label ke input, bantuan ke error, dan antarcard dibuat konsisten. Judul upload yang panjang rata atas; aksi di bawah tidak bertabrakan dengan teks.
- Fokus keyboard, kontras tombol, label wajib, loading, nama file panjang, dan layout layar kecil termasuk cakupan visual ini.
- Perubahan lain yang belum disebut pengguna dibatasi pada konsistensi spacing, susunan tombol Kembali/Simpan/Tinjau, teks fallback, dan kerapian detail. Tidak merombak sidebar/dashboard, membuat autosave, menambah preview PDF/modal, mengubah workflow petugas, atau mendesain ulang halaman riwayat secara penuh.
- Dua view form lama dibahas sebagai cleanup terpisah sesudah tampilan baru stabil; jangan menjadikannya entry point kembali.

## 9. Pembagian panduan implementasi setelah persetujuan

| Bagian | Materi panduan | Verifikasi yang diperlukan |
| --- | --- | --- |
| G1 wizard | Card tahap/footer, panel kanan, ukuran input, required/error, upload konsisten tahap 1–4 | Test render tahap 1 tanpa draft, navigasi terkunci, panel pada empat tahap, HTML error field/old version; browser keyboard, drag/drop, file replacement dan cabang |
| G2 tinjau/detail | Tombol download, pengelompokan snapshot, fallback, visibility Perbaiki | Test snapshot historis tidak berubah saat Student berubah; snapshot parsial/nol; pemilik/update vs reviewer; browser nama panjang/mobile |

Panduan tiap bagian harus memuat command untuk file baru, lokasi edit yang tepat, kode lengkap yang dapat disalin, dan test lengkap. Test yang sudah lulus tidak ditimpa. Gunakan server MySQL Podman dengan schema testing dan command env eksplisit seperti [20](20-checkpoint-f-finalisasi-draft.md), bukan database aplikasi. Tidak memaksa SQLite.

Test PHP tidak membuktikan warna, ukuran, drag/drop aktual atau fokus keyboard; perlu checklist browser. Tidak menambah framework browser-test atau dependency UI hanya untuk perubahan ini. Tidak menjalankan test/build dari asisten selama batas hanya-docs masih berlaku.

## 10. Status review rancangan

Pengguna telah menyetujui rancangan. Kode aplikasi belum diedit oleh asisten; kode implementasi G1 tersedia sebagai blok yang dapat disalin pada [23](23-checkpoint-g1-visual-wizard-student.md). Kerjakan dan uji G1 terlebih dahulu agar checkpoint mudah direscan, kemudian lanjut G2 untuk tinjau/detail.

Review independen terhadap rancangan dan source tidak menemukan pertanyaan penghambat atau ketidaksesuaian faktual. Perbandingan hash area source kedua folder sesudah penulisan tetap sama dengan sebelum penulisan; tidak ada perubahan aplikasi dari asisten. Dokumen ini belum membuktikan hasil visual di browser.
