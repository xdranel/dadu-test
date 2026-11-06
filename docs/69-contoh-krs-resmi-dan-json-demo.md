# 69 — Contoh KRS resmi dan JSON demo dengan struktur sumber

Pengguna memberikan `/home/xdranel/Downloads/rekap_igracias_2026-11-06 KRS.json` untuk dibaca saja dan meminta JSON contoh di docs. Berkas asli tidak diubah, tidak disalin ke repo, dan tidak diimpor ke database. File demo memakai identitas sintetis akun yang sudah tersedia.

## 1. Hasil pembacaan berkas asli

| Bagian | Temuan |
| --- | --- |
| Wrapper | `generated_at`, `source`, `total`, `selected_menus`, `data` |
| Menu | `selected_menus = ["krs_mk"]` |
| Jumlah mahasiswa | 4.277 baris; sesuai `total` |
| Field per mahasiswa | `no`, `nim`, `nama`, `jumlah_mk_krs`, `krs_mata_kuliah` |
| Jumlah entri mata kuliah | 30.590, tersebar dalam daftar per mahasiswa |
| Kesesuaian jumlah | Seluruh `jumlah_mk_krs` cocok dengan panjang `krs_mata_kuliah` |
| Field per mata kuliah | `kode_mk`, `nama_mk`, `kelas`, `kode_dosen`, `tahun_ajaran`, `semester`, `program_studi`; semuanya string pada contoh |
| Tahun/term KRS | Seluruh entri memakai `tahun_ajaran = "2627"`, `semester = "1"` |

Nama mata kuliah setelah normalisasi spasi/huruf memuat22 entri TUGAS AKHIR2,256 TUGAS AKHIR,96 TUGAS AKHIR1, dan45 PROPOSAL TUGAS AKHIR. Ini jumlah entri mata kuliah, bukan jumlah mahasiswa yang sudah memenuhi semua syarat TA. Helper TA yang ada menerima dua nama pertama; TA1 dan proposal tidak meloloskan syarat KRS.

Pasangan periode KRS tersebut dipetakan menjadi2026/2027 GANJIL. `semester` di dalam item KRS ini adalah term periode dan perlu dibedakan dari semester studi mahasiswa pada rekap profil akademik. Angka tersebut tidak boleh langsung mengganti semester studi akun demo yang sudah8.

## 2. File JSON demo baru

File sudah dibuat di:

[69-01-krs-format-resmi-akun-demo.json](fixtures/sidang-ta/69-01-krs-format-resmi-akun-demo.json)

- Krisna/NIM1202200001: satu mata kuliah TUGAS AKHIR.
- Dummy002/NIM1202299002: satu mata kuliah TUGAS AKHIR2.
- Wrapper, field per mahasiswa, field per mata kuliah, dan tipe data mengikuti struktur contoh resmi. Timestamp, sumber, identitas, kelas, serta kode mata kuliah/dosen pada fixture adalah nilai sintetis.
- Tidak membuat akun, role, dosen, periode akademik, atau record database. File ini disiapkan untuk pemeriksaan format dan pengujian dukungan importer berikutnya.

Fixture datar [67-02-krs-akun-demo.json](fixtures/sidang-ta/67-02-krs-akun-demo.json) tetap dipertahankan untuk impor manual yang sudah didukung. Kedua file memakai mahasiswa, kode mata kuliah, nama mata kuliah, dan periode yang konsisten; perbedaannya adalah struktur ekspornya.

## 3. Batas importer sekarang

Pada source saat pemeriksaan ini, `SyncController::importKrsRow` menerima format array indeks lama atau satu objek datar per mata kuliah. Cabang objek mencari `kode_matakuliah`/`course_code` di tingkat baris, bukan membaca daftar `krs_mata_kuliah`.

Akibatnya, mengunggah berkas resmi ini atau fixture69 langsung sebagai tipe KRS akan melewati baris mahasiswa karena kode mata kuliah tidak ditemukan di tingkat yang dicari. Status impor selesai tidak membuktikan daftar bertingkat sudah disimpan; periksa jumlah processed/skipped.

**Fixture69 belum siap diimpor langsung dengan kode sekarang.** Dukungan format baru perlu membaca daftar mata kuliah setiap mahasiswa, memetakan nama field dan tahun/term, serta mengaitkan hasil dengan periode akademik yang sesuai. Penambahan dukungan itu belum dikerjakan pada permintaan baca-saja ini. Untuk uji manual yang perlu berjalan sekarang, gunakan fixture67-02 dengan tipe KRS sesuai67 bagian2.2.

Lanjutan implementasi tersedia pada [70 — integrasi KRS resmi](70-integrasi-krs-resmi-per-mahasiswa-dan-periode.md). Pengguna sudah memastikan daftar sumber lengkap dan memilih penggantian KRS pada mahasiswa/periode yang sama. Panduan70 memuat kode, migration kelas panjang, dan test untuk diterapkan pengguna; source belum berubah hanya karena panduan sudah ditulis.

## 4. Apakah tinggal BPP?

**Untuk kelengkapan contoh sumber yang ditunggu, ya: contoh KRS resmi sudah diterima; yang tersisa adalah kejelasan periode BPP.** Untuk pengerjaan aplikasi, adapter KRS format baru dan penyelarasan tampilan yang dicatat pada68 masih perlu dilanjutkan.

Periode KRS2026/2027 GANJIL tidak otomatis menjadi periode BPP. Berkas KRS ini tidak memuat status/periode pembayaran. Rekap profil sebelumnya sudah memuat LUNAS/KREDIT, tetapi belum mengidentifikasi tahun akademik dan ganjil/genap pembayaran tersebut. Tetap minta periode BPP resmi, termasuk apakah konteksnya sama untuk seluruh ekspor atau berbeda per mahasiswa.

## 5. Verifikasi dan batas perubahan

Fixture69 divalidasi sebagai JSON, diperiksa kesamaan field/tipe dengan contoh resmi, kesesuaian jumlah mahasiswa/mata kuliah, serta kecocokan identitas dengan fixture67-01. Source aplikasi/deployment kedua repo dan berkas KRS asli diperiksa dengan hash sebelum/sesudah dan tetap tidak berubah. Fixture67-02 dipertahankan.

Perubahan hanya JSON sintetis baru, dokumen69, dan indeks fixture README. Tidak melakukan impor, migration, seed, SSH, build, restart, commit, atau push. Berkas baru dibuat hanya di dadu-test; tidak menyalin apa pun ke repo development asli.
