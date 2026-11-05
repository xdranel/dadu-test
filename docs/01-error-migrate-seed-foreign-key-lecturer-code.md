# 01 — Error `migrate --seed`: foreign key `lecturer_code`

## Error yang terjadi

Saat menjalankan langkah Database & Seeding di [README](../README.md):

```bash
php artisan migrate --seed
```

migrasi `2026_06_14_000003_add_fk_lecturer_code_to_course_lecturers_table` gagal dengan `SQLSTATE[HY000]`, error 1005, errno 150: `Foreign key constraint is incorrectly formed`.

Operasi yang ditolak adalah penambahan relasi berikut:

```sql
ALTER TABLE course_lecturers
ADD CONSTRAINT course_lecturers_lecturer_code_foreign
FOREIGN KEY (lecturer_code)
REFERENCES lecturers (lecturer_code)
ON DELETE RESTRICT ON UPDATE CASCADE;
```

Artinya, database menolak definisi foreign key antara tabel pengampu mata kuliah dan tabel dosen. Kegagalan terjadi pada tahap migrasi, sehingga seeder dari perintah ini belum dijalankan. Kata `create table` dalam pesan error juga dapat muncul saat `ALTER TABLE`; bukan berarti Laravel sedang membuat tabel baru dari awal.

## Temuan dari kode proyek

| Urutan | Berkas migrasi | Perilaku |
| --- | --- | --- |
| 1 | [create_lecturers_table](../database/migrations/2026_05_01_142450_create_lecturers_table.php) | Membuat `lecturers.lecturer_code` melalui `string()` dengan indeks unique dan indeks biasa. |
| 2 | [make_kode_dosen_nullable](../database/migrations/2026_05_18_153952_make_kode_dosen_nullable_in_dosens_table.php) | Mengubah kolom tersebut menjadi nullable. |
| 3 | [add_extra_fields_and_relations_to_courses](../database/migrations/2026_06_12_085200_add_extra_fields_and_relations_to_courses_table.php) | Membuat `course_lecturers.lecturer_code` sebagai `VARCHAR(20)` beserta foreign key ke `lecturers`. |
| 4 | [remove_lecturer_fk](../database/migrations/2026_06_12_091000_remove_lecturer_fk_from_course_lecturers_table.php) | Menghapus foreign key agar kode dosen eksternal dapat disimpan tanpa baris induk. |
| 5 | [add_external_fields_to_lecturers](../database/migrations/2026_06_14_000002_add_external_fields_to_lecturers_table.php) | Menambahkan `is_external` dan `institution`. |
| 6 | [add_fk_lecturer_code](../database/migrations/2026_06_14_000003_add_fk_lecturer_code_to_course_lecturers_table.php) | Mengimpor kode orphan ke `lecturers`, kemudian memasang kembali foreign key pada baris 68. |

Kode sumber saja belum membuktikan penyebab spesifik pada database pengguna. Error ini mengarah ke persyaratan struktur foreign key yang tidak terpenuhi, misalnya charset/collation berbeda, indeks induk tidak sesuai, atau storage engine berbeda. Skema aktual dapat berbeda dari berkas migrasi akibat impor database atau perubahan manual.

**Perbedaan panjang string bukan bukti penyebab.** Pada definisi standar, `string()` menghasilkan `VARCHAR(255)`, sedangkan kolom anak menggunakan `VARCHAR(20)`. MySQL mengizinkan panjang string berbeda untuk foreign key; charset dan collation kolom string harus sama. Nullable pada kolom induk juga bukan otomatis penyebab error ini. Lihat [persyaratan foreign key MySQL](https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html).

Foreign key yang sama bahkan sudah dibuat pada migrasi langkah 3. Jika seluruh migrasi benar-benar dijalankan dari database kosong dengan konfigurasi tetap, relasi awal seharusnya juga memenuhi syarat. Karena itu, jangan langsung menyimpulkan bahwa panjang kolom harus diubah atau seluruh database harus direset.

## 1. Pastikan koneksi dan kumpulkan diagnosis

Dari direktori proyek, setelah memastikan `.env` menunjuk database yang benar:

```bash
php artisan config:clear
php artisan migrate:status
```

Migrasi yang gagal biasanya masih berstatus `Pending`; migrasi sebelumnya yang sukses tetap `Ran`. Jangan langsung menggunakan `migrate:rollback`: perintah itu membatalkan batch migrasi sukses, bukan sekadar membersihkan migrasi yang gagal.

Masuk melalui klien database, menggunakan kredensial `.env` dan memasukkan password melalui prompt:

```bash
mysql -h 127.0.0.1 -P 3306 -u NAMA_USER -p dadu_test
```

Jalankan SQL berikut pada database tersebut:

```sql
SELECT VERSION(), DATABASE();
SHOW CREATE TABLE lecturers;
SHOW CREATE TABLE course_lecturers;
SHOW INDEX FROM lecturers;
SHOW INDEX FROM course_lecturers;
SHOW ENGINE INNODB STATUS;

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('lecturers', 'course_lecturers');

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
       CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('lecturers', 'course_lecturers')
  AND COLUMN_NAME = 'lecturer_code';
```

Cari bagian `LATEST FOREIGN KEY ERROR` pada keluaran InnoDB. Ambil sesegera mungkin setelah kegagalan karena error koneksi lain dapat menggantikannya. Pada beberapa server, perintah tersebut memerlukan hak `PROCESS`; minta administrator mengambil hasilnya jika akses ditolak.

Periksa apakah kedua kolom bertipe kompatibel, memiliki charset/collation sama, kedua tabel memakai InnoDB, dan kolom induk memiliki indeks unique penuh pada `lecturer_code`. Sumber proyek sudah mendefinisikan unique; jika tidak ada pada database, itu perbedaan skema yang perlu diperbaiki.

## 2. Perbaiki sesuai hasil diagnosis

Backup database terlebih dahulu jika sudah berisi data. SQL di bawah adalah tindakan bersyarat: jalankan hanya cabang yang sesuai temuan, bukan semuanya sekaligus.

### A. Charset atau collation kolom berbeda

Samakan kolom anak dengan **nilai aktual kolom induk** dari hasil diagnosis. Contoh berikut hanya berlaku jika induk menggunakan `utf8mb4` dan `utf8mb4_unicode_ci`, serta kolom anak masih `VARCHAR(20) NOT NULL` tanpa atribut tambahan:

```sql
ALTER TABLE course_lecturers
MODIFY COLUMN lecturer_code VARCHAR(20)
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
```

Ganti charset/collation contoh dengan hasil pemeriksaan. Pertahankan panjang, nullability, default, dan atribut lain yang memang ada pada `SHOW CREATE TABLE`. Periksa relasi lain sebelum mengubah kolom induk atau mengonversi seluruh tabel.

Mengubah `DB_COLLATION` di `.env` saja tidak mengonversi kolom yang sudah ada. [Konfigurasi database](../config/database.php) menggunakan nilai default berbeda untuk koneksi `mysql` dan `mariadb`; gunakan collation yang tersedia pada server dan konsisten dengan skema proyek.

### B. Indeks unique pada induk hilang

Periksa duplikasi terlebih dahulu:

```sql
SELECT lecturer_code, COUNT(*) AS jumlah
FROM lecturers
WHERE lecturer_code IS NOT NULL
GROUP BY lecturer_code
HAVING COUNT(*) > 1;
```

Jika hasil kosong dan tidak ada indeks unique penuh yang setara, tambahkan:

```sql
ALTER TABLE lecturers
ADD UNIQUE KEY lecturers_lecturer_code_unique (lecturer_code);
```

Jika ada duplikasi, tentukan penggabungan data dosen dan relasinya terlebih dahulu. Jangan menghapus data sembarang hanya agar indeks dapat dibuat. Jika diagnosis menunjukkan indeks prefix, perbaiki menjadi indeks penuh sesuai batas indeks server.

### C. Storage engine tidak sesuai

Jika hasil pemeriksaan menunjukkan salah satu tabel bukan InnoDB, evaluasi relasi serta dampak penguncian tabel, lalu konversi tabel yang diperlukan:

```sql
ALTER TABLE lecturers ENGINE = InnoDB;
ALTER TABLE course_lecturers ENGINE = InnoDB;
```

### D. Ada kode dosen tanpa baris induk

Periksa data orphan:

```sql
SELECT DISTINCT cl.lecturer_code
FROM course_lecturers AS cl
LEFT JOIN lecturers AS l ON l.lecturer_code = cl.lecturer_code
WHERE l.lecturer_code IS NULL;
```

Migrasi yang gagal sudah berusaha mengimpor data ini dengan `insertOrIgnore()`. Jika masih ada orphan, periksa mengapa insert diabaikan dan lengkapi data induk yang valid. Jangan mematikan `FOREIGN_KEY_CHECKS` sebagai solusi. Orphan umumnya memunculkan pelanggaran integritas data seperti error 1452; jangan menganggapnya penyebab pasti errno 150 yang dilaporkan.

## 3. Jalankan kembali migrasi dan seed

Setelah masalah struktur dan data diselesaikan:

```bash
php artisan migrate --seed
php artisan migrate:status
```

Laravel menjalankan migrasi yang belum tercatat berhasil. Tidak perlu menjalankan ulang migrasi sukses secara manual atau memasukkan baris ke tabel `migrations`. Perubahan DDL MySQL dan impor orphan sebelum kegagalan bisa sudah tersimpan, sehingga periksa kondisi aktual sebelum mencoba ulang.

Pastikan migrasi `2026_06_14_000003_add_fk_lecturer_code_to_course_lecturers_table` berstatus `Ran` dan proses seeding selesai. Periksa juga definisi relasinya:

```sql
SHOW CREATE TABLE course_lecturers;
```

Hasil harus memuat `course_lecturers_lecturer_code_foreign` dengan `ON DELETE RESTRICT` dan `ON UPDATE CASCADE`.

`php artisan migrate:fresh --seed` hanya layak untuk database lokal yang seluruh datanya boleh dibuang, setelah penyebab diperbaiki. Perintah tersebut menghapus semua tabel pada koneksi tujuan dan tidak memperbaiki definisi yang salah dengan sendirinya. Lihat [dokumentasi migrasi Laravel 13](https://github.com/laravel/docs/blob/13.x/migrations.md).

## 4. Jadikan perbaikan konsisten di proyek

Jika penyebabnya perubahan skema manual pada satu database, pulihkan skema sesuai definisi proyek dan dokumentasikan perubahan aktual. Jika masalah dapat direproduksi dari database kosong, perbaiki migrasi sumber yang menyebabkan ketidaksesuaian dan uji kembali pada database pengujian MySQL/MariaDB yang terpisah.

Untuk database yang sudah dipakai bersama, sediakan migrasi koreksi yang berjalan **sebelum** migrasi `2026_06_14_000003` yang gagal, atau prosedur pemulihan SQL yang ditinjau. Migrasi koreksi bertimestamp lebih akhir tidak akan tercapai selama migrasi ini masih gagal. Perhatikan juga instalasi yang sudah berhasil: migrasi koreksi harus mempertimbangkan foreign key yang mungkin sudah terpasang. Laravel menyediakan modifier `charset`, `collation`, serta `change()` untuk perubahan kolom; atribut yang perlu dipertahankan harus dinyatakan dengan benar. Lihat [dokumentasi perubahan kolom](https://github.com/laravel/docs/blob/13.x/migrations.md#modifying-columns).

## Batas verifikasi dokumen ini

Penjelasan disusun dari log error yang dilaporkan, migrasi di repositori, dokumentasi Laravel 13 melalui Context7, dan dokumentasi resmi MySQL. Database pengguna belum diperiksa dan perintah perubahan database di atas belum dijalankan. Karena itu, penyebab spesifik serta keberhasilan perbaikannya harus dikonfirmasi melalui hasil diagnosis; dokumen ini tidak mengklaim collation, indeks, atau engine tertentu sudah terbukti bermasalah.

## Pembaruan diagnosis: MariaDB 11.8.8

Hasil yang dikirim pengguna memperlihatkan kedua kolom sudah `VARCHAR(255)`, kedua tabel memakai InnoDB dan default collation `utf8mb4_0900_ai_ci`, serta indeks unique induk tersedia. Penambahan constraint melalui SQL langsung tetap menghasilkan errno 150; `SHOW WARNINGS` hanya memberikan pesan umum dan status InnoDB tidak memuat `LATEST FOREIGN KEY ERROR`. Jadi Laravel bukan satu-satunya tempat kegagalan dapat direproduksi, dan mengganti panjang kolom bukan perbaikan yang terbukti.

Langkah berikutnya adalah memeriksa metadata kolom/indeks dan nama constraint yang masih tercatat. Pada MariaDB sebelum 12.1, nama foreign key harus unik dalam satu database, menurut [dokumentasi resmi MariaDB](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/foreign-keys). Konflik biasa umumnya menghasilkan errno 121, sehingga belum dapat disimpulkan sebagai penyebab errno 150 ini.

```sql
SELECT TABLE_NAME, COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'dadu_test'
  AND TABLE_NAME IN ('lecturers', 'course_lecturers')
  AND COLUMN_NAME = 'lecturer_code';

SHOW INDEX FROM lecturers;
SHOW INDEX FROM course_lecturers;

SELECT CONSTRAINT_SCHEMA, TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'dadu_test'
  AND CONSTRAINT_NAME = 'course_lecturers_lecturer_code_foreign';

SELECT ID, FOR_NAME, REF_NAME
FROM information_schema.INNODB_SYS_FOREIGN
WHERE ID = 'dadu_test/course_lecturers_lecturer_code_foreign';
```

Query `INNODB_SYS_FOREIGN` dapat memerlukan hak `PROCESS`. Jika ditolak, catat penolakannya tanpa mengubah hak akses sendiri. Ada [laporan MariaDB MDEV-40087](https://jira.mariadb.org/browse/MDEV-40087) tentang errno 150 terkait nama foreign key setelah upgrade server, tetapi kasus tersebut melibatkan tabel dari versi lama. Belum ada bukti kondisi upgrade itu berlaku pada instalasi pengguna; laporan ini hanya petunjuk investigasi, bukan diagnosis atau alasan langsung untuk downgrade/reset database.


### Hasil pemeriksaan lanjutan

Pengguna mengonfirmasi indeks induk unique BTREE penuh dan indeks anak BTREE penuh, tanpa prefix atau status ignored. Nama constraint tidak ditemukan pada `TABLE_CONSTRAINTS` maupun `INNODB_SYS_FOREIGN`. Pengujian dengan nama baru `fk_cl_lecturer_probe` juga gagal dengan errno 150. Metadata kolom mengonfirmasi tipe dan collation identik; hanya nullability berbeda, sesuai desain induk nullable dan anak wajib diisi. Karena itu, mengganti nama constraint atau menyamakan panjang/collation bukan perbaikan yang terbukti. Penyebab belum teridentifikasi; pengujian berikutnya perlu membandingkan relasi minimal pada tabel diagnosis baru dengan relasi pada tabel aplikasi.
