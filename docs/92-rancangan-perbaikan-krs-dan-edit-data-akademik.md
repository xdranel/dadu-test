# 92 — Rancangan dan rencana perbaikan KRS serta edit data akademik

## Keputusan pengguna

- Kerjakan perbaikan KRS, hapus History Nilai (KRS Aktif), dan edit data pada tab Data Yudisium & Tugas Akhir terlebih dahulu. Form Yudisium hanya dicatat pada 91.
- Pada file baru, semester 1 berarti **Ganjil 2026/2027**, bukan semester studi mahasiswa.
- Tambahkan pengaturan periode akademik di KRS Mahasiswa; periode akademik berbeda dari periode pendaftaran Sidang TA/Yudisium.
- Sesudah edit manual, impor JSON berikutnya tetap boleh memperbarui field yang sama.
- JSON Kehadiran belum tersedia; tetap ingatkan pengguna.

## Diagnosis

File KRS baru berisi 1068 baris untuk 277 mahasiswa, seluruhnya tahun 2026/2027, semester integer 1, SKS null. Jalur flat lama menganggap semester sebagai semester studi, tidak meneruskan field itu sebagai term, serta mengganti SKS null dengan 3. Tanpa periode akademik aktif dan tautan academic_period_id yang benar, KRS tidak masuk bagian aktif walaupun tersimpan di daftar umum. Tidak ada halaman pengaturan periode akademik sebelumnya.

## KRS dan periode akademik

Tambahkan pilihan arti semester pada unggahan KRS. Mode lama tetap semester studi. Mode ganjil/genap memakai periode tahun+term yang sudah didaftarkan, mengambil semester studi dari profil mahasiswa, serta mempertahankan SKS null. Import tidak otomatis membuat/mengaktifkan periode atau menghapus mata kuliah yang absen dari berkas.

Mode baru menampilkan pratinjau sebelum penerapan eksplisit. Koreksi baris lama yang salah/null-linked hanya boleh jika cocok dengan NIM/kode/tahun/kelas pada sumber serta tidak ambigu; pertahankan ID. Konflik, kandidat ganda, dan benturan kunci unik dilewati dengan catatan. Terapkan ulang memeriksa token/session dan perubahan sejak preview. Daftar MK serta roster dibedakan menurut academic_period_id.

Pengaturan periode: nama/tahun/ganjil-genap dan tanggal awal/akhir nyata, validasi tanggal serta identitas unik. Membuat periode tidak mengaktifkannya. Aktivasi terpisah memilih satu periode tanpa menghapus riwayat, dengan transaksi/lock. Jangan mengubah tahun/term periode yang sudah dirujuk data historis.

## Edit data pada detail mahasiswa

Tombol baru pada tab Data Yudisium & Tugas Akhir membuka form terpisah dari edit identitas. Gunakan hak student_update dan policy mahasiswa. Data disimpan pada tabel akademik/profil resmi, bukan mengubah berkas JSON. Lingkup: status sidang tingkat, detail TA/pembimbing/penguji/tanggal, nilai TA, total TAK, BASILA, SKS/list MK, BPP per periode, bayar wisuda, SKPI, dan persetujuan sidang.

Update atomik menyelaraskan tabel lama dan StudentAcademicProfile.groups/bpp_periods agar panel persyaratan membaca hasil yang sama. Data bahasa, IPK, identitas, KRS, dan field di luar form dipertahankan. Bila belum ada profil resmi, bentuk profil kompatibel dari fakta legacy agar penambahan data tidak menghilangkan EPRT/IPK yang sebelumnya terbaca. Profil resmi yang sudah ada tidak mengambil nilai legacy untuk kelompok yang absen.

Gunakan penanda versi isi agar form lama tidak menimpa sinkronisasi yang terjadi sesudah form dibuka. Simpan jejak pelaku/waktu/sebelum/sesudah pada tabel audit tambahan. Data pengajuan yang sudah dikirim tidak ditulis ulang oleh fitur edit ini. Impor mendatang dapat memperbarui koreksi manual sesuai konfirmasi pengguna.

Daftar MK: satu baris per entri, tanda '-' berarti tidak ada, kosong berarti belum tersedia. BPP mempertahankan riwayat dan memilih secara eksplisit baris periode yang menjadi status saat ini. Null dan 0 harus dibedakan; status yang belum tersedia tidak berubah menjadi penolakan.

## Rencana pelaksanaan dan pengujian

- [x] Catat 91 dan rancangan 92; simpan guard hash tujuan, gunakan worktree yang tersedia.
- [x] Tulis dan jalankan RED KRS/periode dan edit akademik sebelum implementasi. Database dadu_testing dipakai serial oleh primer.
- [x] Implementasikan KRS/periode pada agen terpisah; primer mengerjakan form edit/audit dan penghapusan histori nilai KRS aktif. Koordinasikan route pada satu pemilik.
- [x] Uji izin/validasi, update seluruh kelompok, data legacy, null/0, BPP lintas periode, form kedaluwarsa, rollback, impor menimpa lagi, dan pengajuan final tidak berubah.
- [x] Uji preview/apply KRS, SKS null, semester 9 vs term 1, pengaitan ID lama yang unik, konflik/ambigu, periode hilang/inaktif, pengulangan impor, dan pemisahan roster.
- [x] Review independen, gate terkait, suite lengkap dibanding baseline HEAD fef6e09, JS/build/lint/Blade.
- [x] Catat hasil/command copy pada 93; salin berkas lingkup ke kedua repo dengan backup serta mempertahankan perbedaan branch admin. Tidak melakukan impor aplikasi, migrate:fresh/seed, commit/push atau deployment. Migrasi aplikasi tetap dijalankan pengguna.
