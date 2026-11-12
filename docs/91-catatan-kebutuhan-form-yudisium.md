# 91 — Catatan kebutuhan Form Yudisium

Dicatat dari arahan pengguna 10 November 2026. Prioritas KRS/edit data akademik sudah diselesaikan sampai dokumen 98. Pada 11 November 2026 pengguna meminta melanjutkan Form Yudisium; penggalian kebutuhan dan pemetaan kode dimulai. Catatan ini menjadi acuan kebutuhan, sedangkan pertanyaan yang belum jelas dicatat untuk dikonfirmasi kepada divisi. Contoh JSON Kehadiran **masih belum tersedia** dan tetap perlu diminta; berkas `rekap_igracias_format_krs_matakuliah.json` adalah KRS, bukan Kehadiran.

## Sisi mahasiswa dan data statis

Samakan pola TA: kartu status persyaratan hijau/merah, unggahan hanya untuk dokumen yang diminta revisi, tinjauan/konfirmasi pengiriman, pengaturan periode pendaftaran/revisi/tampilan, serta CRUD Panduan Form Yudisium untuk admin. Penguji maksimal 2.

Data statis: pembimbing 1–2, penguji 1–2, dosen wali, nilai TA, MK mengulang, MK belum lulus, tanggal sidang, semester mahasiswa.

Persyaratan: IPK lulus, SKS lulus minimal 144, TAK minimal 60, EPRT minimal 450 atau surat pemakluman setelah 3 tes dengan nilai di bawah 450 mengikuti TA, sidang tingkat 3, approval foto I-BASILA, BPP, SKPI, pembayaran wisuda. Nilai TA harus tersedia dan adanya MK belum lulus menjadi penghalang. Daftar blocking eksplisit: BPP, sidang tingkat, foto BASILA, TAK, IPK, SKS lulus, SKPI, bayar wisuda, EPRT, nilai TA belum tersedia, dan MK belum lulus.

## Tahap 1 — Data dasar

Nomor HP mahasiswa/orang tua dan email pribadi. Konfirmasi judul TA karena dicetak pada transkrip:

- Jika sesuai, lanjut tahap berikutnya.
- Jika tidak sesuai, tanyakan apakah sudah mengajukan perubahan judul pascasidang.
- Sudah mengajukan, belum diproses LAA: input tanggal pengajuan.
- Sudah mengajukan, sudah diproses LAA tetapi data DADU belum sesuai: tampilkan pilihan tersebut untuk tindak lanjut.

## Tahap 2 — Kelengkapan dokumen

Riwayat EPRT dan surat pemakluman bila 3 tes dengan nilai di bawah 450. Unggah lembar pengesahan, buku TA disahkan, lembar revisi; input tanggal mulai/selesai revisi. Input angka plagiasi buku TA serta unggah hasilnya; input angka plagiasi artikel ilmiah serta unggah hasilnya. Unggah surat bebas perpustakaan, keuangan, dan laboratorium. Pernyataan biodata dummy ijazah mengikuti pilihan/form TA.

## Tahap 3 — Cumlaude / Summa-Cumlaude

Tanyakan apakah mengajukan. Jika mengajukan, kriteria tambahan:

1. HKI/hak cipta: jenis (program komputer/modul/desain industri/dll), nama mitra, form validasi unit riset dan kerja sama, sertifikat HKI, surat implementasi mitra.
2. Lomba: tingkat, nama penyelenggara, tanggal, BA verifikasi unit KKA, sertifikat.
3. Publikasi jurnal S 1–S 2 / Q 1–Q 4: nama jurnal, tautan website, indeksasi Scopus, LoA, form validasi unit riset dan kerja sama. Jika belum terbit, surat pernyataan menyelesaikan.
4. Konferensi internasional: nama, penyelenggaraan keberapa (minimal sudah 2 kali), tautan website, indeksasi Scopus, LoA, revisi reviewer. Jika belum terbit, surat pernyataan menyelesaikan; jika sudah, sertifikat/screenshot presentasi.

## Sisi admin

Pola review mengikuti TA. Angka plagiasi artikel ilmiah dapat diedit admin. Filter nama/NIM, periode, status, prodi, pengajuan cumlaude (semua/ya/tidak), dan jenis kriteria (HKI/lomba/jurnal; rincian konferensi perlu diselaraskan). Export Excel pada kolom kriteria cumlaude/summa menyertakan tautan PDF.

## Hal yang perlu diperjelas sebelum implementasi Yudisium

Batas angka IPK lulus dan plagiasi belum ditetapkan pada arahan ini. Kriteria Summa tersendiri, apakah kriteria prestasi dapat dipilih lebih dari satu, arti kategori S 1–S 2/indeksasi konferensi, serta alur judul belum mengajukan perlu dipastikan nanti. Ini tidak menghalangi perbaikan prioritas 92–93.

## Keputusan sementara pembahasan 11 November 2026

- Daftar blokir mengikuti catatan meeting di atas. Pertanyaan tentang batas IPK dan tambahan blocker KRS TA2/MK mengulang diminta dicatat untuk ditanyakan kepada divisi, bukan dijadikan penambahan syarat tanpa konfirmasi.
- Pengajuan predikat sementara memilih **satu kriteria prestasi**. Pengguna masih membutuhkan konfirmasi apakah kelak dapat lebih dari satu; simpan pertanyaan ini.
- Persyaratan Cumlaude/Summa mengikuti rincian yang ada pada catatan. Perbedaan khusus yang tidak disebutkan belum boleh dikarang; catat untuk divisi.

## Pertanyaan kepada divisi — disimpan sesuai arahan pengguna

1. Berapa angka IPK minimal Yudisium? Kode lama memakai 2,00, sementara catatan hanya menulis IPK lulus.
2. Apakah KRS TA2 pada periode akademik aktif tetap menjadi blocker Yudisium, atau cukup informasi? Daftar blocker meeting tidak menyebutnya.
3. Apakah MK mengulang ikut memblokir jika tidak berada pada daftar MK belum lulus? Catatan menyebut MK mengulang sebagai data statis dan MK belum lulus sebagai blocker.
4. Apa perbedaan persyaratan Cumlaude dan Summa-Cumlaude selain pilihan nama predikat?
5. Bolehkah memilih beberapa kriteria prestasi? Sementara satu kriteria sesuai arahan pengguna.
6. Berapa batas plagiasi buku TA dan artikel ilmiah? Pengguna mengonfirmasi petugas dapat mengoreksi angka artikel jika hasil pemeriksaan manual berbeda dan menjadikannya alasan keputusan. Batas maksimal dicatat untuk divisi; sementara input 0–100 dan tidak ada ambang penolakan otomatis yang dikarang.
7. Kategori jurnal yang ditulis S1–S2 apakah berarti SINTA 1–2; apa bukti indeksasi yang diminta untuk konferensi internasional?
8. Jika judul belum sesuai dan mahasiswa belum mengajukan perubahan ke LAA, apakah pengiriman harus ditahan sampai mengajukan? Catatan baru merinci dua keadaan sudah mengajukan.
9. Email pribadi tetap wajib Gmail seperti kode lama, atau seluruh domain email pribadi yang valid diperbolehkan?
10. Apakah BPP harus lunas pada periode tertentu, dan bagaimana status KREDIT diperlakukan?
11. Apakah pengajuan lama harus mengikuti persyaratan baru saat revisi, atau mempertahankan versi persyaratan asal?

## Temuan pemetaan kode awal — belum mengubah perilaku aplikasi

- Yudisium saat ini belum memiliki tabel draft terpisah. Simpan awal/perbaikan menulis pengajuan submitted; alur ini perlu dibedakan dari draft saat mengikuti pola TA.
- Periode pada pengajuan masih berupa nama teks, bukan relasi ID periode. Rancangan pembaruan perlu menjaga nama/periode dan dokumen pengajuan lama.
- Revisi unggahan lama dapat mengganti berkas selain yang diminta revisi; permintaan baru mengharuskan pembatasan di server seperti TA.
- Kode lama mempunyai blocker KRS TA2 dan MK mengulang, tetapi belum memeriksa seluruh persyaratan baru seperti SKS 144, nilai TA tersedia, dan BPP terpisah secara lengkap.
- Data riwayat EPRT dan sumber profil resmi yang sudah dipakai TA dapat digunakan kembali sebagai pola agar data Yudisium tidak membaca ringkasan lama yang berbeda.
- Berkas Yudisium lama memakai storage public, sedangkan berkas TA memakai akses terkontrol. Penanganan kompatibilitas perlu dipertahankan tanpa menebak kategori berkas historis, termasuk arsip bebas lab dan lampiran predikat generik.

Pemetaan di atas merekam keadaan awal. Implementasi berikutnya dikerjakan terpisah dan hasil verifikasinya dicatat pada dokumen 101; database aplikasi tidak direset.

Rancangan pelaksanaan dan keputusan sementara dicatat pada dokumen 99; rencana implementasi pada 100.
