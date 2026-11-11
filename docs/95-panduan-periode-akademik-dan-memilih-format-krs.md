# 95 — Memahami periode akademik dan memilih format impor KRS

Panduan ini menjawab pertanyaan tentang penyimpanan periode, penggunaan satu atau beberapa kartu, perbedaan pilihan impor, dan cara menentukan pilihan untuk JSON berikutnya. Penjelasan mengikuti kode aplikasi saat panduan dibuat. Pekerjaan ini hanya dokumentasi; tidak mengubah kode, menjalankan impor, atau mengubah database aplikasi.

> **Status terbaru — implementasi 98:** sumber asli sudah dikonfirmasi **B (JSON datar dengan term)** dan **C (daftar resmi bersarang)**. Pilihan format lama telah dihapus dari layar dan jalur importernya dipensiunkan. Sekarang cukup pilih jenis data KRS: B otomatis masuk pratinjau Term, C otomatis masuk importer resmi. Contoh A di bawah adalah penjelasan kontrak lama, bukan format yang boleh diunggah saat ini. Rincian pemasangan dan contoh bantuan terbaru ada pada dokumen 98.

## 1. Apakah periode hanya acuan atau tersimpan di database?

**Periode adalah acuan aplikasi yang disimpan di database.** Kata “acuan” menjelaskan kegunaannya; bukan berarti nilainya hanya sementara atau sekadar tampilan.

Setiap kartu pada Pengaturan Periode Akademik mewakili satu record pada tabel `academic_periods`.

| Tindakan admin | Yang tersimpan di database |
|---|---|
| Menambahkan periode | Record baru: tahun, term, label, tanggal mulai/selesai, dan status belum aktif |
| Menyimpan perubahan periode | Memperbarui record periode tersebut |
| Mengaktifkan periode | Mengubah `is_active` periode itu menjadi aktif dan menonaktifkan periode aktif sebelumnya |
| Mengimpor KRS | Menyimpan/memperbarui record KRS mahasiswa pada `student_courses`, termasuk tautannya ke periode |
| Membuka halaman/pratinjau impor | Membaca data; pratinjau mode Term belum menyimpan KRS ke database |

Status aktif juga tersimpan. Setelah refresh, logout, atau container dimulai ulang, pilihan tersebut tetap ada selama menggunakan database yang sama.

**Periode berlaku untuk seluruh aplikasi, bukan satu mahasiswa.** Ganjil 2026/2027 dapat dipakai bersama oleh mahasiswa semester studi 1, 3, 5, 7, dan 9.

Contoh hubungan record berikut hanya ilustrasi, bukan ID asli database:

| Tabel | Contoh isi |
|---|---|
| `academic_periods` | ID 101 = Ganjil 2026/2027, aktif |
| `academic_periods` | ID 102 = Genap 2026/2027, tidak aktif |
| `student_courses` | KRS mahasiswa A, semester studi 9, tahun 2026/2027, `academic_period_id = 101` |

Aplikasi dapat mengetahui bahwa KRS itu termasuk Ganjil karena terhubung ke ID 101. Angka semester studi 9 tidak dipakai sebagai pengganti term Ganjil.

## 2. Boleh memakai satu kartu lalu mengganti isinya setiap semester?

**Satu kartu cukup jika baru ada satu periode yang diperlukan sekarang. Namun jangan mendaur ulang periode yang sudah dipakai menjadi semester/tahun lain.**

Misalnya kamu membuat Ganjil 2026/2027 lalu mengimpor KRS yang terhubung ke periode itu. Ketika masuk Genap, buat periode Genap 2026/2027 baru. Jangan mengganti tahun/term kartu Ganjil tersebut.

Kode sekarang sudah menolak perubahan tahun/term apabila periode dirujuk oleh KRS, master mata kuliah, aktivitas mahasiswa, atau EPRT. Tujuannya menjaga arti data historis. Jika belum dirujuk data tersebut, tahun/term masih dapat diperbaiki, misalnya membetulkan salah input sebelum dipakai.

| Field yang diedit | Perilaku dan pengaruh |
|---|---|
| Tahun akademik | Ditolak jika periode sudah dirujuk data tersebut; buat periode baru untuk tahun berbeda |
| Term Ganjil/Genap | Ditolak jika periode sudah dirujuk data tersebut; buat periode baru untuk term berbeda |
| Label | Boleh diubah; halaman yang membaca label periode dapat menampilkan nama baru, termasuk untuk periode historis tersebut |
| Tanggal mulai/selesai | Boleh diperbarui dengan tanggal valid; mengubah informasi kalender periode, bukan memindahkan KRS ke periode lain |
| Status aktif melalui tombol Aktifkan | Mengubah acuan KRS aktif seluruh aplikasi; record dan hubungan KRS lama tetap tersimpan |

Jangan memberi label “Genap” pada periode yang tahun/term-nya masih Ganjil. Walaupun label dapat diedit, identitas periode tetap ditentukan oleh tahun dan term, bukan tulisan label.

**Tanggal mulai/selesai saat ini tidak otomatis mengaktifkan/menonaktifkan periode.** Admin tetap memilih lewat tombol Aktifkan. Melewati tanggal selesai tidak menghapus KRS dan bukan tenggat pendaftaran Sidang TA/Yudisium.

## 3. Lebih baik satu kartu atau dua?

Gunakan **satu record untuk setiap pasangan tahun akademik + term**. Boleh membuat yang diperlukan dahulu, atau menyiapkan Ganjil dan Genap sekaligus jika tanggal resminya sudah tersedia.

| Periode | Contoh status saat masih Ganjil |
|---|---|
| Ganjil 2026/2027 | Aktif |
| Genap 2026/2027 | Tidak aktif; boleh disiapkan lebih dahulu |
| Ganjil 2027/2028 | Record baru ketika diperlukan, bukan mengganti kartu Ganjil 2026/2027 |
| Genap 2027/2028 | Record baru ketika diperlukan |

Jadi, **bukan hanya dua kartu permanen “Ganjil” dan “Genap” yang tahunnya diganti terus**. Riwayat akan bertambah mengikuti tahun akademik. Membuat banyak periode tidak membuat semuanya aktif; tombol aktivasi memilih tepat satu.

Tidak perlu membuat satu kartu untuk semester studi 1, satu untuk semester studi 3, dan seterusnya. Semester studi milik mahasiswa, sedangkan periode akademik dipakai bersama.

## 4. Apa perbedaan pilihan format lama dan Term?

Sebelum penyederhanaan 98, pilihan mode menentukan **arti field `semester` pada JSON KRS datar**. Tabel berikut mencatat perbedaan kontraknya. **Jalur lama pada kolom kiri sekarang sudah dipensiunkan; tidak ada lagi dropdown lama/Term.** B memakai Term otomatis. “Lama” tidak merujuk tanggal ekspor.

| Aspek | Arsip perilaku format lama — sudah dipensiunkan | Format B saat ini — Term Ganjil/Genap |
|---|---|---|
| Arti `semester` | Semester yang ditempuh mahasiswa: misalnya 4, 8, atau 9; bisa juga 1/2 untuk mahasiswa awal studi | Bagian tahun akademik: 1 = Ganjil, 2 = Genap |
| Semester studi yang disimpan pada KRS | Mengambil field `semester` dari JSON | Baris baru/perbaikan tautan mengambil semester studi dari profil mahasiswa; update KRS yang sudah tertaut mempertahankan semester studi tersimpan |
| Pemilihan periode | Dapat memakai term/ID periode eksplisit; tanpa itu memakai periode aktif yang tahunnya cocok. Jika tidak dapat ditentukan, tautan dapat kosong | Harus ada periode yang cocok dengan `tahun_akademik` dan term pada JSON |
| KRS perlu periode aktif agar bisa disimpan? | Tidak selalu; jalur lama bisa menyimpan KRS dengan tautan kosong | Periode harus terdaftar, tetapi boleh tidak aktif; agar muncul sebagai KRS Aktif harus menjadi periode aktif |
| Pratinjau sebelum simpan | Tidak melalui pratinjau mode Term | Ya, kemudian konfirmasi Simpan hasil tinjauan |
| SKS null pada jalur datar | Jalur lama memakai nilai bawaan 3 bila SKS tidak tersedia | Null dipertahankan; tidak ditebak menjadi 3 |
| Master mata kuliah yang belum ada | Jalur lama dapat membuat master sementara | Mode Term tidak otomatis membuat master mata kuliah |
| MK tidak tercantum pada unggahan berikutnya | Jalur datar tidak otomatis menghapusnya | Mode Term datar juga tidak otomatis menghapusnya |

Perbedaan pembuatan master menjelaskan mengapa data hasil impor lama dapat mempunyai master MK sementara hasil impor Term belum mempunyai master. Patch pada dokumen 94 menjaga halaman Mata Kuliah tetap terbuka untuk kondisi tersebut.

## 5. Contoh A — JSON datar yang `semester`-nya semester studi

Anggap NIM sintetis berikut sudah terdaftar dan sumber mengonfirmasi mahasiswa sedang semester studi 9. Contoh ini untuk penjelasan, bukan data mahasiswa asli atau bukti kelayakan.

```json
[
  {
    "nim": "1295990001",
    "kode_matakuliah": "TA-CONTOH",
    "nama_matakuliah": "TUGAS AKHIR",
    "kelas_matakuliah": "KELAS-TA-A",
    "kode_dosen": "D-CONTOH",
    "nama_dosen": "Dosen Contoh",
    "sks": 4,
    "tahun_akademik": "2026/2027",
    "semester": 9,
    "semester_term": "GANJIL"
  }
]
```

**Contoh A ini tidak lagi diterima melalui endpoint impor KRS saat ini.** Dahulu pilihan format lama membaca `semester: 9` sebagai semester studi 9. Field terpisah `semester_term: "GANJIL"` memberi informasi term agar periode dapat dicocokkan dengan jelas. Periode Ganjil 2026/2027 perlu tersedia untuk menghasilkan tautan periode yang benar.

Tanpa term eksplisit, jalur lama mencoba memakai periode aktif dengan tahun yang sama. Ini alasan penting memeriksa arti data dari sumber; term tidak semestinya ditebak dari semester studi.

Pada alur B saat ini, contoh ini membuat baris dilewati karena 9 bukan term yang valid. Jangan mengganti angka 9 menjadi 1 hanya supaya lolos apabila kontrak sumber memang menyatakan semester studi.

## 6. Contoh B — JSON datar yang `semester`-nya Ganjil/Genap

Ini mengikuti bentuk file **`rekap_igracias_format_krs_matakuliah.json`** yang kamu kirim. Identitas dan kode di bawah adalah sintetis.

```json
[
  {
    "nim": "1295990001",
    "kode_matakuliah": "TA-CONTOH",
    "nama_matakuliah": "TUGAS AKHIR",
    "kelas_matakuliah": "KELAS-TA-A",
    "kode_dosen": "D-CONTOH",
    "nama_dosen": "Dosen Contoh",
    "sks": null,
    "tahun_akademik": "2026/2027",
    "semester": 1
  }
]
```

Unggah dengan jenis data **KRS**. Sistem otomatis memakai pratinjau **Term akademik** untuk format B; tidak ada pilihan mode lagi. Pengguna telah mengonfirmasi bahwa 1 pada sumber ini berarti Ganjil.

Jika profil mahasiswa menunjukkan semester studi 9, hasil KRS baru yang benar adalah:

| Bagian | Hasil |
|---|---|
| Tahun akademik | 2026/2027 |
| Periode yang ditautkan | Ganjil 2026/2027 |
| Semester studi pada KRS baru | 9, dari profil mahasiswa |
| SKS | Null, sesuai sumber |

Sebelum perubahan 98, memilih format lama untuk contoh B berisiko membaca angka 1 sebagai semester studi dan menyimpan SKS bawaan 3. Risiko jalur tersebut sekarang ditutup: tidak ada fallback lama dan request dengan mode study ditolak.

Untuk Genap pada kontrak sumber yang sama, gunakan data sumber dengan `semester: 2` dan periode Genap pada tahun yang sesuai. Jangan mengubah isi ekspor hanya agar cocok dengan periode aktif; daftarkan periode yang benar dan gunakan data asli.

## 7. Contoh C — Format resmi per mahasiswa dengan `krs_mata_kuliah`

C adalah bentuk resmi bersarang, berbeda dari JSON datar B dan contoh A yang telah dipensiunkan. File **`rekap_igracias_2026-11-06 KRS.json`** yang sebelumnya kamu berikan menggunakan bentuk ini: satu mahasiswa memiliki daftar mata kuliah bersarang.

```json
{
  "selected_menus": ["krs_mk"],
  "total": 1,
  "data": [
    {
      "nim": "1295990001",
      "nama": "Mahasiswa Contoh",
      "jumlah_mk_krs": 1,
      "krs_mata_kuliah": [
        {
          "kode_mk": "TA-CONTOH",
          "nama_mk": "TUGAS AKHIR",
          "kelas": "KELAS-TA-A",
          "kode_dosen": "D-CONTOH",
          "tahun_ajaran": "2627",
          "semester": "1",
          "program_studi": "Informatika"
        }
      ]
    }
  ]
}
```

Sistem **mendeteksi format resmi ini terlebih dahulu**, sebelum menggunakan alur B. Unggah sebagai KRS; sistem memakai importer khusus karena menemukan `krs_mata_kuliah`. Tidak perlu dan tidak tersedia pilihan mode lama/Term.

Pada bentuk resmi ini, `tahun_ajaran: "2627"` berarti 2026/2027 dan `semester: "1"` berarti Ganjil. Kontrak importer format resmi mengharuskan term sebagai string `"1"`/`"2"`; ini berbeda dari mode datar Term yang menerima angka 1/2 maupun stringnya. Tidak perlu mengubah tipe data ekspor yang sudah sesuai.

**Format resmi ini mempunyai perilaku impor ulang yang berbeda:** daftar MK dianggap lengkap, sesuai keputusan pengguna sebelumnya. Impor ulang memperbarui daftar untuk mahasiswa dan periode yang disebut dalam unggahan, termasuk menghapus MK lama pada periode tersebut yang tidak lagi tercantum. Periode lain tetap dipertahankan. Daftar kosong tidak dipakai untuk menebak periode yang akan dibersihkan. Jalur ini tidak menggunakan halaman pratinjau mode Term datar.

Jangan menganggap semua format JSON KRS mempunyai perilaku penggantian yang sama. Contoh B bersifat menambah/memperbarui; contoh C menyelaraskan daftar lengkap per periode yang disertakan.

## 8. Bagaimana mengetahui file itu harus memilih yang mana?

**Nama file, tanggal ekspor, dan tampilan angka 1/2 saja tidak cukup.** Yang menentukan adalah struktur dan arti field menurut sumber ekspor.

| Yang ditemukan pada JSON | Keputusan |
|---|---|
| Ada daftar `krs_mata_kuliah` di dalam setiap mahasiswa | C; dikenali otomatis pada impor KRS |
| Objek datar per NIM/MK, dan sumber menyatakan `semester` = Ganjil/Genap | B; otomatis masuk pratinjau Term |
| Objek datar per NIM/MK, dan sumber menyatakan `semester` = semester studi mahasiswa | Kontrak lama sudah dipensiunkan; minta ekspor B/C yang benar |
| Field `semester` berisi 4/8/9 | Nilai itu bukan term 1/2 dan tidak dapat digunakan sebagai term B. Konfirmasi kontrak sumber sebelum impor |
| Field `semester` hanya berisi 1/2 tetapi belum diketahui artinya | Belum bisa dipastikan. Bisa term, bisa mahasiswa semester awal; tanyakan pemilik sumber |
| Nama field/struktur/arti periode berbeda dari ketiga contoh | Perlu diperiksa/diadaptasi; deteksi otomatis tidak menebak arti field atau mengubah struktur menjadi didukung |

Pembungkus `{"data": [...]}` sendiri tidak menentukan lama atau Term: keduanya dapat memakai pembungkus tersebut. `krs_mata_kuliah` sebagai daftar MK bersarang adalah pembeda format resmi. Mode Term datar saat ini menerima daftar langsung atau pembungkus `data`; ekspor DataTables berbentuk baris indeks/`aaData` perlu mengikuti kontrak jalur lamanya, bukan otomatis diperlakukan sebagai contoh B.

Pertanyaan singkat kepada pembuat ekspor jika belum jelas:

> Pada JSON KRS ini, field semester berarti Ganjil/Genap atau semester studi mahasiswa? Di field mana tahun akademik dan term dinyatakan? Apakah daftar mata kuliahnya lengkap atau hanya sebagian?

Untuk file baru yang **tetap menggunakan kontrak yang sama dengan `rekap_igracias_format_krs_matakuliah.json`**, pilih jenis data **KRS** lagi; B otomatis memakai Term. Kamu tidak perlu menanyakan ulang setiap ekspor selama struktur dan maknanya tetap sama. Jika kontrak berubah atau berasal dari endpoint berbeda, periksa kembali.

## 9. Mengapa format salah masih dapat membuka halaman peninjauan?

**Muncul pratinjau bukan berarti seluruh data valid atau sudah disimpan.**

Mode Term dapat menerima daftar objek sebagai bahan peninjauan, lalu menandai baris bermasalah sebagai Dilewati. Contohnya:

| Masalah | Hasil yang perlu diperhatikan |
|---|---|
| `semester: 9` dipakai pada mode Term | Dilewati karena term harus 1/2 |
| Periode tahun+term belum terdaftar | Dilewati; buat periode yang benar lalu unggah ulang |
| NIM belum ada | Dilewati; impor profil mahasiswa dahulu |
| Semester studi profil kosong/tidak valid | Dilewati; perbaiki profil dari sumber yang benar |
| KRS lama mempunyai beberapa kandidat atau benturan kunci | Dilewati; periksa riwayat, jangan menebak pasangan |
| Semua baris Dilewati | Tidak ada KRS valid untuk disimpan meskipun halaman pratinjau tampil |

Sebelum konfirmasi, tidak ada penulisan KRS. Ketika dikonfirmasi, hanya baris valid diterapkan; catatan baris dilewati tetap dicatat. Pratinjau berlaku 30 menit. Jika data/periode berubah sejak peninjauan, sistem meminta unggah ulang agar perubahan tidak diterapkan dari kondisi lama.

## 10. Penjelasan teks tentang TA2 dan periode aktif

Teks bantuan lama yang dibahas pada percakapan menggabungkan penjelasan format dan persyaratan akademik. Teks tersebut telah diganti dengan dua contoh B/C pada 98. Penjelasan berikut menyimpan konteks pertanyaan sebelumnya:

1. **“Semester adalah semester studi pada format lama”**: contoh A, nilai 9 berarti semester mahasiswa, bukan Ganjil.
2. **“Semester adalah 1/2 pada mode Term”**: contoh B, nilai 1 berarti Ganjil; semester studi berasal dari profil.
3. **“Tahun akademik harus mempunyai periode terdaftar”**: contoh B membutuhkan periode 2026/2027 Ganjil sebelum baris dapat disimpan melalui mode Term.
4. **“Tahun akademik harus sama dengan periode aktif”**: ini harus dibaca dalam konteks KRS yang hendak dianggap **aktif/memenuhi pemeriksaan persyaratan saat ini**, bukan larangan mengimpor data historis. Tahun saja belum cukup; term dan tautan ke periode juga harus cocok.
5. **“IF-101 / Pemrograman Web bukan TA2”**: impor mata kuliah biasa tidak otomatis memenuhi syarat KRS TA2. Sistem harus menemukan mata kuliah TA yang sesuai dari KRS mahasiswa pada periode aktif. Jangan mengganti nama/kode mata kuliah biasa menjadi TA2 agar syarat lulus.

Misalnya periode aktif Ganjil 2026/2027:

| Data mahasiswa | Dampak |
|---|---|
| KRS TUGAS AKHIR terhubung ke Ganjil 2026/2027 | Dapat memenuhi pemeriksaan KRS TA2; persyaratan lain tetap diperiksa |
| KRS TUGAS AKHIR hanya terhubung ke Genap 2025/2026 | Tetap tersimpan sebagai riwayat, belum menjadi TA2 pada periode aktif |
| KRS Pemrograman Web terhubung ke Ganjil 2026/2027 | Menjadi KRS aktif, tetapi bukan TA2 |
| KRS TA2 tercatat tetapi tautan periode kosong | Belum cukup untuk menentukan TA2 aktif; perlu perbaikan tautan berdasarkan sumber |

Pemeriksaan KRS TA2 memang ada pada alur Sidang TA dan pemeriksaan Yudisium yang saat ini sudah berjalan. Teks tersebut bukan berarti satu unggahan KRS membuat seluruh syarat Yudisium terpenuhi. Rencana perubahan Form Yudisium pada dokumen 91 belum diimplementasikan lewat panduan ini.

## 11. Urutan pemakaian untuk kasusmu sekarang

1. Pastikan profil mahasiswa sudah ada dan semester studinya benar.
2. Buat/periksa periode **2026/2027 — Ganjil**, dengan tanggal kalender resmi kampus.
3. Aktifkan periode itu jika memang merupakan semester berjalan. Persiapkan Genap sebagai record lain bila diperlukan; tidak perlu mengganti identitas kartu Ganjil.
4. Impor file `rekap_igracias_format_krs_matakuliah.json` sebagai **KRS**; sistem otomatis menampilkan pratinjau Term.
5. Periksa pratinjau, terutama Dilewati dan Perbaiki tautan. Setelah sesuai, konfirmasi penyimpanan.
6. Periksa detail mahasiswa → KRS Aktif dan status KRS TA2 bila relevan. Aktivasi periode saja tidak menautkan ulang KRS lama yang belum punya periode.
7. Ketika berpindah semester: buat periode berikutnya, impor profil/KRS sesuai sumber periode baru, dan aktifkan periode baru pada saat yang benar. Riwayat periode lama tetap ada. Periode tidak aktif juga dapat menerima impor yang memang ditujukan kepadanya.

## 12. Pengaruh ke data lain

| Data/fitur | Pengaruh pengaturan periode dan impor KRS |
|---|---|
| KRS mahasiswa | Impor menyimpan/memperbarui KRS dan tautan periodenya; perilaku hapus MK absen mengikuti jenis format seperti bagian 4 dan 7 |
| KRS Aktif | Mengikuti periode aktif; mengganti aktif mengubah acuan yang ditampilkan untuk semua mahasiswa |
| Pemeriksaan TA2 | Mengikuti KRS pada periode aktif, sehingga hasil persyaratan dapat berubah saat acuan diganti |
| IPK, SKS lulus, TAK, EPRT, SKPI, nilai TA | Tidak otomatis dihitung ulang atau diganti oleh dua pengaturan ini; berasal dari data akademik masing-masing |
| BPP | Status dan periode pembayaran tetap berasal dari data keuangan; mengaktifkan Ganjil tidak otomatis membuat BPP lunas atau mengganti periode pembayaran |
| Periode pendaftaran/revisi TA/Yudisium | Pengaturannya terpisah; aktivasi periode akademik tidak membuka/menutup pendaftaran |
| Pengajuan yang sudah tersimpan | Aktivasi periode tidak menulis ulang isi record pengajuan; pemeriksaan persyaratan yang membaca data terkini memakai acuannya saat diperiksa |
| Kelas/master MK/penjadwalan ujian | Sebagian modul juga membaca periode aktif; ini pengaturan bersama tingkat aplikasi, bukan khusus satu halaman mahasiswa |

## 13. Rujukan kode dan pemeriksaan dokumentasi

Penjelasan diperiksa terhadap `AcademicPeriodController` (simpan/edit/aktivasi), model `AcademicPeriod`, `SyncController` (pemilihan importer dan jalur lama), `FlatTermKrsImportService` (mode Term), `OfficialKrsJsonNormalizer` dan `OfficialKrsJsonImportService` (format bersarang), serta pembacaan KRS aktif/TA2 pada detail mahasiswa dan layanan persyaratan.

Nama file sumber yang diberikan pengguna diperiksa sebatas struktur dan makna field periode; seluruh identitas/kode pada contoh di panduan ini sintetis. Ketiga blok JSON diperiksa dapat di-parse; ini verifikasi dokumentasi, bukan menjalankan impor ke aplikasi. Tidak diperlukan unit test baru untuk perubahan dokumentasi ini.

Contoh JSON Kehadiran masih ditunggu. Ketiga contoh KRS di atas bukan contoh Kehadiran.

## 14. Validasi lanjutan — membuat periode dan memilih format

**Pertanyaan:** Berarti kartu bertambah mengikuti waktu, periode baru dibuat dahulu lalu diaktifkan ketika digunakan? Apakah Kelola Periode Sidang TA juga begitu?

**Jawaban:** Ya. Pertahankan record periode yang sudah dipakai sebagai riwayat; untuk semester/tahun akademik berbeda, buat record baru. Periode baru boleh disiapkan belum aktif lalu diaktifkan ketika menjadi semester berjalan. Kartu yang sama tetap boleh dipakai untuk membetulkan informasi periode itu sendiri, bukan diganti menjadi periode lain.

Pada Sidang TA, prinsip menjaga riwayatnya sama: buat record baru untuk gelombang pendaftaran berikutnya. Jadwal pendaftaran dan jadwal verifikasi/revisi dalam satu gelombang tetap berada pada record yang sama. Namun aturan fiturnya berbeda: **hanya satu periode akademik aktif, sedangkan beberapa periode TA boleh aktif bersamaan**. Kelola Periode TA tidak mempunyai penguncian tahun/term seperti periode akademik; mengedit jadwal TA yang sudah dirujuk dapat memengaruhi akses pendaftaran/revisi pengajuan lama. Penjelasan lengkap dan contoh kasus ada pada [96](96-panduan-kelola-periode-sidang-ta.md).

**Pertanyaan historis sebelum 98:** Contoh A memilih format lama, B memilih Term, C dikenali otomatis? Apakah A–B berarti hasil grab hanya sebagian?

**Jawaban pada saat percakapan:** Pilihannya benar jika arti field sesuai contoh. Sesudah keputusan 98, A dipensiunkan dan B/C dideteksi otomatis. A–B tidak menjelaskan lengkap/sebagian. A dan B sama-sama berbentuk baris datar, dengan arti field semester yang berbeda; file dapat berisi satu mahasiswa atau banyak mahasiswa, seluruh MK atau sebagian MK. C dikenali dari struktur `krs_mata_kuliah`, bukan karena sistem membuktikan kelengkapan seluruh data pada iGracias.

## 15. Kasus satu mahasiswa kekurangan MK/KRS dan menerima JSON baru

**Pertanyaan:** Jika ada satu mahasiswa yang MK/KRS-nya kurang, boleh grab JSON dari iGracias, lihat formatnya, lalu pilih format lama atau Term? Dan C otomatis jika seperti JSON yang dahulu diberikan?

**Jawaban:** Ya, impor dapat digunakan untuk melengkapi data satu mahasiswa, selama NIM sudah terdaftar, tahun/term benar, dan format serta arti field didukung. Pilih berdasarkan **struktur dan makna semester**, bukan berdasarkan jumlah mahasiswa atau banyaknya baris:

1. Kontrak baris datar semester studi pernah dijelaskan sebagai A; sesudah 98 kontrak ini tidak didukung endpoint impor. Jangan mengubah angka/arti sumber hanya agar lolos.
2. Jika baris datar dan `semester` berarti Ganjil/Genap, alur B memakai **Term akademik otomatis**. Ini pilihan untuk file `rekap_igracias_format_krs_matakuliah.json` yang sudah kamu konfirmasi.
3. Jika per mahasiswa terdapat daftar `krs_mata_kuliah` seperti file resmi dahulu, sistem memakai jalur C secara otomatis. Tidak ada dropdown mode yang perlu dipilih.

**Koreksi penting untuk kata “lengkap”: auto-detect C terjadi karena bentuk datanya, bukan karena sistem tahu apakah semua MK iGracias benar-benar sudah diambil.** Sistem memvalidasi struktur, jumlah MK terhadap daftar yang dikirim, serta field yang diperlukan. Daftar satu MK dengan `jumlah_mk_krs: 1` dapat konsisten secara struktur, padahal mahasiswa sebenarnya mengambil beberapa MK lain. Sistem tidak membandingkannya langsung ke iGracias untuk membuktikan bahwa ekspor itu lengkap.

Contoh mahasiswa mempunyai tiga MK pada periode yang sama:

| Keadaan | Data yang diimpor | Hasil sesuai jenis importer |
|---|---|---|
| Aplikasi sudah memiliki MK-A dan MK-B; MK-C belum masuk | JSON datar B hanya berisi MK-C | Menambah/memperbarui MK-C; MK-A dan MK-B tetap tersimpan |
| Aplikasi sudah memiliki MK-A dan MK-B | Format C untuk mahasiswa itu berisi daftar lengkap MK-A, MK-B, MK-C pada periode tersebut | Daftar periode diselaraskan menjadi tiga MK tersebut |
| Aplikasi sudah memiliki MK-A dan MK-B | Format C hanya berisi MK-C padahal daftar sebenarnya ada tiga | MK-A dan MK-B pada periode yang sama dapat dihapus karena daftar C dianggap pengganti lengkap |

**Boleh mengimpor format C yang hanya memuat satu mahasiswa.** Yang harus lengkap adalah daftar MK mahasiswa tersebut untuk setiap periode yang disertakan, bukan daftar seluruh mahasiswa kampus. Mahasiswa lain tidak perlu dimasukkan dan KRS mereka tidak ikut diganti oleh record mahasiswa ini.

Jadi, untuk memperbaiki kekurangan satu mahasiswa:

- JSON datar B dapat berisi tambahan MK yang diperlukan, dengan semester sumber berarti term Ganjil/Genap.
- JSON resmi C harus tetap memuat daftar MK lengkap mahasiswa itu pada periode yang akan diselaraskan. Jangan mengirim hanya satu MK yang kurang dalam bentuk C jika MK lain seharusnya dipertahankan.
- Jangan sekadar memindahkan JSON resmi ke bentuk datar atau mengganti arti field agar memilih importer lain; pastikan konversi/kontraknya benar bila memang diperlukan.
- Jika field `semester` hanya 1/2 dan maknanya belum diketahui, tanyakan pemilik sumber dahulu. Angka itu saja tidak membedakan semester awal studi dan term.

Impor KRS tidak membuat status persyaratan TA/Yudisium otomatis lulus. Data harus benar dan relevan terhadap periode akademik aktif; persyaratan lainnya tetap diperiksa. Contoh JSON Kehadiran masih ditunggu.

## 16. Konfirmasi sumber asli dan rekomendasi mode impor — 11 November 2026

Pengguna mengoreksi asumsi sebelumnya: sumber KRS asli yang dipakai adalah **contoh B dan C**. Angka semester pada ekspor KRS tersebut adalah term, bukan semester studi 9/13. Nilai semester studi yang tersimpan pada profil mahasiswa adalah data terpisah; konfirmasi ini tidak menghapus data semester mahasiswa dari database.

- **B** dapat dipakai untuk menambah/memperbarui MK yang kurang pada satu mahasiswa. MK lama yang tidak disertakan tidak dihapus oleh importer datar Term. Impor menambahkan saja bukan selalu membuat baris baru: MK yang sudah cocok dapat diperbarui.
- **C** dapat berisi satu mahasiswa saja, tetapi daftar MK mahasiswa itu untuk setiap periode yang disertakan harus lengkap. MK lama pada periode tersebut yang tidak ada pada daftar baru dapat dihapus. Auto-detect berdasarkan struktur, bukan bukti kelengkapan terhadap iGracias.
- Jika C hanya memuat satu MK sementara mahasiswa sebenarnya mempunyai MK lain pada periode sama, MK lain itu berisiko terhapus oleh penyelarasan. Gunakan ekspor C yang lengkap atau kontrak B yang benar untuk tambahan parsial.

**Keputusan yang sudah diterapkan pada 98:** hapus pilihan format lama dari layar impor karena tidak mempunyai contoh sumber nyata yang dikonfirmasi, dan berikan alur yang jelas untuk dua kontrak asli: B = Term dengan pratinjau, C = deteksi format resmi dengan daftar lengkap. Mempertahankan pilihan lama hanya “untuk berjaga-jaga” meningkatkan risiko angka 1 disalahartikan sebagai semester studi atau SKS null mendapat nilai bawaan 3.

Implementasi 98 sudah menyelaraskan default/validasi server dan menghapus fallback/importer KRS lama. Flag study ditolak; flag term dari klien lama yang benar masih diterima, tetapi tidak dibutuhkan pada form. Fixture lama tetap menjadi arsip dan penggantinya tersedia dalam format B; pengujian telah diselaraskan. Fixture bukan bukti bahwa iGracias sekarang menyediakan kontrak semester studi. Tidak perlu mengganti nilai semester studi historis atau merombak database hanya untuk menyederhanakan pilihan impor.

**Status akhir:** kolom daftar KRS disederhanakan pada 97. Penghapusan mode lama dan kolom semester studi pratinjau sudah diterapkan pada 98, dengan data semester internal tetap dipakai. Kartu bantuan kini memuat dua contoh B/C di bawah kartu impor sebelah kiri; Status Data tetap di kanan.
