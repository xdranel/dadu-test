# 91 — Catatan kebutuhan Form Yudisium (belum diimplementasikan)

Dicatat dari arahan pengguna 10 November 2026. Prioritas saat ini adalah koreksi KRS, menghapus History Nilai (KRS Aktif), dan fitur edit data akademik dari detail mahasiswa. Implementasi Form Yudisium menunggu pekerjaan prioritas selesai. Contoh JSON Kehadiran **masih belum tersedia** dan tetap perlu diminta; berkas `rekap_igracias_format_krs_matakuliah.json` adalah KRS, bukan Kehadiran.

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
