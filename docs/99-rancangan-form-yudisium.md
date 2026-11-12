# 99 — Rancangan pembaruan Form Yudisium

## Acuan dan keputusan pengguna

Acuan kebutuhan: dokumen 91 dan catatan meeting pengguna. Pada 11 November pengguna meminta mulai pelaksanaan, mengikuti catatan yang sudah ada, dan menyimpan hal yang belum jelas ke .md untuk ditanyakan kepada divisi. Sementara hanya **satu kriteria prestasi** dipilih. Batas plagiasi belum ditetapkan; petugas harus dapat mengoreksi angka plagiasi artikel setelah pemeriksaan manual dan memakai hasilnya saat memutuskan pengajuan.

Angka plagiasi divalidasi 0–100 sebagai persentase; tidak menetapkan ambang penolakan otomatis yang belum disahkan. Simpan nilai mahasiswa pada payload pengiriman dan catat perubahan petugas sebelum/sesudah beserta pelaku/catatan. Pertahankan IPK minimal 2,00 dari kode yang sudah ada sebagai nilai sementara, dengan pertanyaan konfirmasi eksplisit kepada divisi. Tidak menambahkan aturan khusus Summa yang tidak tercantum.

## Pendekatan

Gunakan pola TA untuk alur Yudisium baru: draft tersendiri, tiga tahap, halaman tinjau dengan pernyataan, pemeriksaan ulang di server ketika dikirim, revisi dokumen yang diminta saja, periode pendaftaran dan revisi terpisah, serta panduan form dinamis. Pertahankan tabel pengajuan/dokumen/log Yudisium dan akses terhadap pengajuan lama. Tidak mengubah alur SSO atau kode TA.

Alternatif menambal form lama saja tidak mencukupi karena belum ada draft sesungguhnya, periode pengajuan berupa teks, dan penggantian berkas belum dibatasi dokumen revisi. Alternatif membuang data lama tidak sesuai kebutuhan menjaga riwayat. Karena itu alur baru diberi versi persyaratan dan relasi periode yang eksplisit, sementara tampilan/berkas legacy tetap dapat dibaca.

## Tiga tahap mahasiswa

1. **Data dasar:** identitas sinkronisasi terkunci; WA aktif mahasiswa/orang tua mengikuti validasi 08/628 TA; email pribadi valid; konfirmasi judul. Jika judul tidak sesuai, pilih salah satu keadaan yang dicatat meeting: sudah mengajukan belum diproses LAA (tanggal wajib), atau sudah diproses tetapi belum sesuai di DADU. Kasus belum mengajukan dicatat untuk konfirmasi, tidak mengarang jalur keberhasilan lain.
2. **Kelengkapan dokumen:** riwayat EPRT dan surat pemakluman sesuai keputusan TA; lembar pengesahan, buku TA disahkan, lembar revisi, tanggal mulai/selesai revisi, angka dan hasil plagiasi buku/artikel, bebas perpustakaan/keuangan/lab, pernyataan dummy ijazah. Pilihan biodata mengikuti TA: bila tidak sesuai wajib bukti PDM dan tanggal pengajuan. Tanggal pekerjaan revisi TA ini berbeda dari jendela revisi pendaftaran yang dikelola admin.
3. **Predikat:** tidak mengajukan/Cumlaude/Summa. Satu kriteria: HKI, lomba, jurnal, atau konferensi. Tiap kriteria mempunyai field dan dokumen sesuai 91. Indeks jurnal S1/S2/Q1–Q4 ditampilkan sesuai catatan tanpa menetapkan kebijakan tambahan. Status terbit menentukan surat pernyataan menyelesaikan atau bukti presentasi konferensi.

Unggahan baru memakai PDF maksimal 10 MB, mengikuti pola TA. Khusus bukti presentasi konferensi menerima PDF/JPG/PNG maksimal 10 MB sesuai permintaan sertifikat/screenshot saat presentasi. Berkas historis JPG/PNG tetap dapat dibaca. Panduan gambar/PDF/tautan dinamis dapat dipasang pada slot dokumen/tahapan. Ringkasan akademik berada pada panel kanan dan kartu merah/hijau, bukan unggahan ganda pada tahap.

## Data dan pemeriksaan akademik

`GraduationAdmissionFacts::forStudent(Student)` menyediakan satu hasil segar: `facts`, `checks`, `blockers`, `language`, `eprt`, dan `snapshot`. `checks` memetakan key ke label/value/passed/message; blockers adalah key=>pesan untuk pengiriman. Gunakan ringkasan profil resmi dan normalisasi EPRT yang sama dengan TA, bukan mengambil skor lama yang menutupi data resmi tidak lengkap.

Blocker sesuai meeting: IPK (sementara minimal 2,00), SKS lulus 144, TAK 60, EPRT >=450 atau surat setelah >=3 tes valid di bawah 450, sidang tingkat3, foto BASILA disetujui, BPP lunas, SKPI disetujui, bayar wisuda lunas, nilai TA tersedia, dan tidak ada MK belum lulus. KRS TA2 dan MK mengulang tetap dapat ditampilkan tetapi tidak ditambahkan sebagai blocker baru karena tidak ada pada daftar meeting. Pertanyaan tentang keduanya dicatat untuk divisi. Data hilang tidak dibuat lulus atau diganti angka rekaan.

Panel statis: identitas, semester mahasiswa, dosen wali, pembimbing1–2, penguji1–2, judul, nilai/tanggal sidang, MK mengulang/belum lulus, dan pemeriksaan. Riwayat pemeriksa3 pada record lama tidak dihapus.

BPP memakai status pembayaran terkini dari profil resmi; bila tidak ada profil resmi gunakan record legacy. Status lunas/paid yang telah dikenali sistem dinormalisasi. Kebijakan status KREDIT dan kewajiban mencocokkan periode BPP secara khusus masih perlu konfirmasi; jangan menyulap status tak dikenal menjadi lunas.

## Draft, kirim, revisi, dan histori

Draft disimpan terpisah, dimiliki user+mahasiswa, terkait periode ID dan optional pengajuan asal+versinya. Draft bukan submitted. Finalisasi memakai transaksi dan lock mahasiswa/draft/pengajuan/periode, memeriksa versi tinjau, deklarasi, jadwal, scalar, dokumen, dan fakta akademik terbaru. Satu mahasiswa tidak boleh mempunyai pengajuan submitted/needs_review lain.

Draft revisi meminjam referensi berkas pengajuan asal; hanya dokumen berstatus ditolak yang dapat diganti. Berkas terverifikasi tetap ada. Dokumen kondisional baru mengikuti perubahan kelayakan bahasa/biodata sesuai pola TA. Pengiriman gagal tidak menghapus dokumen final. File upload baru dibersihkan secara terkontrol setelah metadata committed; jangan menghapus berkas yang masih dirujuk.

Setelah dikirim, simpan payload dan snapshot pengajuan. Impor akademik tidak menimpa snapshot alur baru secara diam-diam. Koreksi artikel oleh admin disimpan sebagai tindakan terpisah; nilai awal mahasiswa tetap dapat ditelusuri.

Histori mahasiswa berisi pengiriman awal, keputusan revisi/setuju/tolak beserta alasan, pengiriman ulang revisi, lalu keputusan berikutnya. Pelaku tidak perlu ditampilkan kepada mahasiswa; admin mempunyai histori lengkap.

## Periode

`GraduationPeriod` mengikuti pola TA: open/close pendaftaran, open/close verifikasi, Pendaftaran aktif, Revisi aktif, Tampilkan jadwal. Beberapa periode dapat aktif bersamaan, mahasiswa memilih periode yang benar-benar terbuka. Kartu mahasiswa mengikuti checkbox dan otomatis hilang setelah kedua jadwal selesai; bukan di setiap tahap wizard.

Tambahkan FK periode nullable pada pengajuan lama dan wajib pada alur baru. Nama periode historis tetap disimpan. Backfill hanya nama yang cocok unik; nama ganda/berubah tidak ditebak. Periode yang dirujuk pengajuan/draft tidak boleh dihapus. Draft awal kedaluwarsa mengikuti tenggat pendaftaran, draft revisi mengikuti tenggat verifikasi, dan revisi belum dikirim dapat ditolak otomatis seperti TA. Tidak ada penghapusan riwayat final.

## Panduan form

Menu IT Panel khusus Panduan Form Yudisium, dengan permission read/create/update/delete, slot, judul, deskripsi, link berlabel, file PDF/gambar, urutan dan aktif. Ikuti pola penyimpanan privat, validasi tautan HTTP/HTTPS, penanganan storage gagal, dan penggantian file terkontrol yang sudah diperbaiki pada panduan TA. Sediakan service `GraduationFormGuideService::activeForSlots(array)` dan partial mahasiswa untuk slot.

Slot dokumen: language_waiver, approval_sheet, final_thesis_book_full, revision_sheet_scan, ithenticate_book_result, ithenticate_journal_result, library_clearance_letter, financial_clearance_letter, laboratory_clearance_letter, diploma_biodata_statement, pdm_submission_proof, serta dokumen kriteria predikat. Slot umum: student_data, title_confirmation, thesis_documents, honors, review.

## Review dan ekspor admin

Daftar mengikuti pola TA, dengan pencarian nama/NIM, periode, status, prodi, apakah mengajukan predikat, dan kriteria. Ekspor menggunakan query/filter yang sama. Review dokumen menandai sesuai/perlu revisi; keputusan keseluruhan revisi/setuju/tolak terpisah, lengkap dengan versi dan catatan. Admin dapat mengoreksi angka plagiasi artikel. Persetujuan memeriksa seluruh dokumen yang benar-benar diwajibkan ada dan terverifikasi.

Ekspor menyertakan tautan lampiran kriteria predikat melalui endpoint download yang memeriksa akses. Tidak menghasilkan URL publik baru atau formula dari input mahasiswa. Berkas lama public tetap mempunyai jalur baca kompatibel; berkas baru disimpan private dengan penanda disk.

## Hal yang tetap perlu ditanyakan ke divisi

Lihat daftar pada 91: IPK resmi, KRS TA2/MK mengulang sebagai blocker, perbedaan Summa, satu/banyak kriteria, batas plagiasi dan kebijakan otomatis, arti S1/S2 dan bukti konferensi, judul belum diajukan, domain email pribadi, pembayaran BPP KREDIT/periode, serta perlakuan persyaratan baru untuk pengajuan lama. Keputusan sementara di atas dipakai agar bagian yang jelas dapat dikerjakan; tidak dinyatakan sebagai kebijakan kampus yang sudah disahkan.
