# 02 — Setup database lokal dengan Podman

Panduan ini menjalankan MySQL 8.4 di Podman, sedangkan PHP/Laravel dan Vite tetap berjalan di laptop. Ini menyediakan database baru yang terpisah dari MariaDB lokal untuk melanjutkan instalasi. Keberhasilan migrasi tetap perlu diuji; penyebab error MariaDB sebelumnya belum terbukti.

MySQL dipilih karena README mendukung MySQL 8.0+ dan koneksi `mysql` proyek menggunakan `utf8mb4_0900_ai_ci`. Port host menggunakan `3307` agar tidak bentrok dengan MariaDB lokal pada `3306`. Tidak perlu memindahkan data instalasi yang gagal.

## 1. Siapkan Podman

Jalankan perintah terminal dari direktori proyek. Jika Podman belum terpasang, gunakan perintah sesuai distro:

```bash
# Fedora
sudo dnf install podman

# Ubuntu / Debian
sudo apt update
sudo apt install podman
```

Verifikasi:

```bash
podman --version
podman info
```

Perintah container selanjutnya dijalankan sebagai pengguna biasa, tanpa `sudo`. Instalasi Linux native tidak memerlukan `podman machine init` atau service Docker. Lihat [instalasi resmi Podman](https://podman.io/docs/installation).

PHP CLI di laptop tetap harus memenuhi persyaratan README dan memiliki ekstensi `pdo_mysql`:

```bash
php --version
php -r 'var_export(extension_loaded("pdo_mysql"));'
```

Hasil pemeriksaan ekstensi harus `true`.

## 2. Buat database container

Password berikut merupakan contoh untuk development lokal. Jika diganti, samakan password aplikasi pada langkah konfigurasi Laravel.

```bash
podman volume create dadu-mysql84-data

podman run -d \
  --name dadu-mysql84 \
  --publish 127.0.0.1:3307:3306 \
  --volume dadu-mysql84-data:/var/lib/mysql \
  --env MYSQL_ROOT_PASSWORD=dadu_root_local_2026 \
  --env MYSQL_DATABASE=dadu_test \
  --env MYSQL_USER=dadu \
  --env MYSQL_PASSWORD=dadu_local_2026 \
  docker.io/library/mysql:8.4 \
  --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_0900_ai_ci
```

Named volume menyimpan data secara terpisah dari container; publikasi port dibatasi ke localhost. Referensi: [opsi podman run](https://docs.podman.io/en/latest/markdown/podman-run.1.html). Database dan user dibuat oleh entrypoint image ketika direktori data belum diinisialisasi; lihat [entrypoint image MySQL 8.4](https://github.com/docker-library/mysql/blob/master/8.4/docker-entrypoint.sh).

Perintah `podman run` digunakan sekali saat membuat container. Jika nama container sudah ada dari percobaan sebelumnya, periksa `podman ps -a` dan gunakan `podman start dadu-mysql84` untuk container yang sesuai. Jangan membuat container lain dengan volume yang sama secara bersamaan.

## 3. Tunggu MySQL siap

```bash
podman logs -f dadu-mysql84
```

Inisialisasi pertama dapat memerlukan waktu. Tunggu sampai inisialisasi selesai dan server utama siap menerima koneksi pada port `3306`; pesan siap dari server sementara pada port `0` belum cukup. Tekan `Ctrl+C` untuk keluar dari tampilan log; container tetap berjalan.

Uji koneksi TCP dari dalam container:

```bash
podman exec -it dadu-mysql84 \
  mysql --protocol=TCP -h 127.0.0.1 -u dadu -p dadu_test \
  -e 'SELECT VERSION(), DATABASE();'
```

Masukkan `dadu_local_2026`. Hasil harus menunjukkan MySQL `8.4.x` dan database `dadu_test`.

## 4. Arahkan Laravel ke container

Edit `.env` yang sudah ada. Pertahankan `APP_KEY` dan konfigurasi aplikasi lainnya. Ganti pengaturan database menjadi:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=dadu_test
DB_USERNAME=dadu
DB_PASSWORD=dadu_local_2026
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_0900_ai_ci
DB_SOCKET=
```

Jika ada `DB_URL` lama, hapus atau komentari agar tidak menimpa pengaturan ini. Gunakan `127.0.0.1` karena Laravel berjalan di host dan mengakses port yang dipublikasikan oleh Podman.

Kemudian:

```bash
php artisan config:clear
php artisan migrate --seed
php artisan migrate:status
```

Ini database baru jika volume baru dibuat pada langkah 2, sehingga tidak perlu `migrate:fresh`. Pastikan migrasi `2026_06_14_000003_add_fk_lecturer_code_to_course_lecturers_table` dan seluruh migrasi berikutnya berhasil, lalu seeding selesai. Jika gagal, simpan error lengkap; perpindahan server tidak menjamin seluruh migrasi proyek bebas masalah.

## 5. Jalankan aplikasi

Jika dependensi dan konfigurasi awal README sudah selesai, jalankan:

```bash
php artisan storage:link
php artisan serve
```

`storage:link` cukup sekali; jika link sudah ada, tidak perlu dibuat ulang. Di terminal lain:

```bash
npm run dev
```

Buka `http://127.0.0.1:8000`. Ikuti langkah Composer/NPM di README jika dependensi belum terpasang.

## 6. Pemakaian sehari-hari

```bash
# Status container
podman ps -a

# Hentikan database tanpa menghapus data
podman stop dadu-mysql84

# Jalankan kembali database yang sama
podman start dadu-mysql84

# Lihat log terbaru
podman logs --tail 100 dadu-mysql84
```

Menghentikan container tidak menghapus named volume. Setelah restart laptop, jalankan `podman start dadu-mysql84` sebelum memakai Laravel. Mengubah variabel `MYSQL_PASSWORD` atau `MYSQL_DATABASE` saat volume sudah berisi database tidak mengganti password atau menginisialisasi ulang database; lakukan perubahan akun melalui SQL jika diperlukan.

Tag `8.4` dapat mengikuti pembaruan patch. Jika ingin hasil lintas laptop benar-benar sama, catat digest image hasil pull dan gunakan referensi `docker.io/library/mysql@sha256:...` yang sama di kedua laptop.

## Troubleshooting singkat

- `Connection refused`: pastikan container berjalan, server utama sudah siap, dan `.env` menggunakan `127.0.0.1:3307`; jalankan `php artisan config:clear`.
- `Access denied`: samakan username/password dengan saat volume pertama kali diinisialisasi.
- Port `3307` terpakai: gunakan port host lain pada `--publish` dan samakan `DB_PORT`.
- `could not find driver`: aktifkan ekstensi PHP CLI `pdo_mysql`.
- Jangan memasang direktori data MariaDB lama ke container MySQL. Gunakan named volume baru dari panduan ini.

Panduan telah dicocokkan dengan konfigurasi proyek dan dokumentasi resmi. Container, migrasi, dan perubahan `.env` belum dijalankan oleh penulis pada sesi ini.
