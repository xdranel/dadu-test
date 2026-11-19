# 113 — Pengganti server laptop dadu-test pada VPS

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

Status: setup selesai. Aplikasi, HTTPS Cloudflare, akun, storage, dan layanan setelah reboot telah diverifikasi pada 16 November 2026; source VPS tetap commit `57d06d7`.

Pengguna memberi izin setup VPS pengganti `45.158.126.251`, 1 vCPU/1 GB RAM/25 GB disk. Source aplikasi harus mengikuti commit terbaru repo dadu-test, **tanpa fitur ruang/TA111–112 yang belum di-review/commit/push**.

## Bukti awal dan keputusan

- Origin/main diperiksa langsung: `57d06d7aa87fbb87b1dbb05a0a488ef2c2321dc8`.
- VPS awal Ubuntu20.04,981MB RAM, tanpa swap,22GB disk tersedia; hanya SSH mendengarkan port publik. Pengguna memilih reinstall Ubuntu24.04LTS melalui panel VPS. SSH tersambung kembali dan banner Ubuntu24.04/kernel6.8 terverifikasi; bootstrap host dimulai sesudah pemeriksaan ini.
- Password aplikasi lama tidak tersedia pada checkout lokal. Pengguna menyetujui password baru untuk superadmin/Krisna dan meminta daftar akun. Sebelas akun blackbox tetap menggunakan `password` sesuai roster/seeder committed. Password SSH dan database tidak ditulis pada dokumen ini.
- Tidak ada backup SQL/storage server lama yang tersedia. ZIP Downloads yang diperiksa hanya JSON. Database VPS dibuat baru; ini bukan klaim pemulihan data pengajuan/unggahan lama.
- Docker/PHP/MySQL/Nginx serta scheduler/worker dipertahankan. Resource disesuaikan untuk 1 GB dengan swap2GB, satu child FPM, worker satu proses, opcache lebih kecil. Ini lingkungan pengujian ringan; kapasitas berbeda dari laptop RAM3.4GB.
- Build image dilakukan dari arsip Git commit bersih di komputer lokal, lalu image dipindah ke VPS agar build tidak menghabiskan RAM VPS. Arsip tidak memuat file uncommitted maupun roster/rahasia.
- Source repo tetap bersih di VPS. Konfigurasi resource/proxy host memakai `compose.override.yaml` yang otomatis digabung Docker Compose, serta file host-only yang dikecualikan lewat `.git/info/exclude`. Jangan membuang override ketika update VPS1GB.

## Domain dan jaringan

Hostname tetap `dadu-test.gendhiramona.site`. Pilihan yang disiapkan: A record ke IPv4 VPS, sertifikat HTTPS otomatis pada Caddy, kemudian Cloudflare Proxied/SSL Full(strict). NPM/Portainer tidak ditambahkan pada VPS1GB karena tidak diperlukan untuk satu aplikasi ini.

Saat menerbitkan sertifikat pertama, DNS only sementara memudahkan challenge langsung ke VPS. Setelah sertifikat terbit, aktifkan Proxied. Jangan mengubah domain lain. Jangan menjalankan connector tunnel lama yang melayani banyak aplikasi pada VPS yang tidak mempunyai semua origin tersebut.

Port publik:22/SSH,80/HTTP untuk redirect dan ACME,443/HTTPS. MySQL dan PHP-FPM berada pada network internal. Port8091 tetap loopback untuk pemeriksaan lokal seperti server lama. Volume database, unggahan dan sertifikat persisten. Firewall mengizinkan SSH sebelum diaktifkan.

Cloudflare Proxied sudah diperiksa melalui alamat edge dan header CF-Ray; pengguna mengonfirmasi SSL Full (strict). HTTPS dan login melalui domain publik berhasil.

## Akun dan data demo

Roster privat11 mahasiswa tersedia dari fixture lokal yang sudah disiapkan sebelumnya. Setup dari commit ini menghasilkan13 akun: superadmin, Krisna tetap, dan11 blackbox. SSO/manual tambahan yang dahulu dibuat sendiri tidak dapat direkonstruksi dari server yang offline tanpa backup; SSO tetap mengikuti integrasi source committed.

Salinan password lokal berada di `private-import/vps-accounts.txt` (0600, di-ignore Git). Environment baru berada di `private-import/vps-env.docker` (0600, di-ignore Git). Jangan commit keduanya atau menyalin environment lokal development ke VPS.

Bootstrap akun hanya dijalankan pada database VPS baru. Setelah itu jangan menjalankan ulang seeder role saat update rutin. Periode/hasil impor lama tidak dianggap sudah ada. Fixture demo dan urutan impor pada75 dapat dipakai kembali untuk pengujian, atau database/upload dipulihkan kemudian dari backup server lama.

## Checklist verifikasi akhir

- [x] Ubuntu24.04 dan Docker29.9.3/Compose5.7.1 terverifikasi; swap2GB/firewall/Docker enabled siap.
- [x] Source VPS tepat57d06d7; file fitur111 tidak ikut dan working tree aplikasi bersih.
- [x] Image app/web berhasil dibangun dari57d06d7, dipindah dan dijalankan pada VPS.
- [x] Migration biasa/bootstrap berhasil;13 akun,12 Student, semua password/role aktif cocok. Satu periode akademik default; belum ada periode pendaftaran TA.
- [x] App,web,db,scheduler,worker berjalan; db/web healthy; PHP-FPM/Nginx config test lulus.
- [x] Localhost8091/login200; reset/register guest404 seperti hosting lama.
- [x] HTTPS via Cloudflare Proxied dan Full(strict) valid; sesi/CSRF/login/aset200 diperiksa.
- [x] Backup awal20261116T023754Z dibuat; OOM=false dan restart0 saat pemeriksaan, Docker enabled.
- [x] Daftar 13 akun/password tersedia pada berkas privat lokal dan `/root/dadu-test-accounts.txt`; panduan operasional akhir siap dipakai.

Referensi yang diperiksa melalui Context7: [Docker Ubuntu installation](https://docs.docker.com/engine/install/ubuntu/), [Compose file merging](https://docs.docker.com/compose/how-tos/multiple-compose-files/merge/), [Caddy Docker](https://caddyserver.com/docs/running#docker-compose), [Caddy automatic HTTPS](https://caddyserver.com/docs/automatic-https). Perintah operasional Compose tetap dijalankan dari `/opt/dadu-test` dengan `.env.docker`; khusus build pada VPS 1 GB, ikuti catatan resource di bawah.

Pengguna menanyakan lokasi perubahan DNS saat tunnel mati. Dijelaskan bahwa DNS dapat diubah pada Domains/Websites → gendhiramona.site → DNS → Records tanpa menghidupkan connector laptop. Record dadu-test diarahkan A ke VPS; DNS only sementara sebelum sertifikat, lalu Proxied.

SSH sesudah reinstall: fingerprint RSA baru diverifikasi langsung pada VPS sebagai `SHA256:YL3bJDCig5MFJJ1FYYQUcgCgx4v4qpkt56+MfbOsO5k`. Jika laptop masih menyimpan host key lama, jalankan `ssh-keygen -R 45.158.126.251` pada laptop, lalu konek ulang dan cocokkan fingerprint. Jangan menonaktifkan pemeriksaan host key secara global.

Build lokal Podman awal berhenti pada COPY image Composer eksternal yang belum tersedia secara lokal. Image resmi composer:2 di-pull eksplisit, lalu build diulang memakai cache. Dockerfile committed dan source bisnis tidak diubah. Kedua target app/web kemudian berhasil.

Migration biasa selesai pada database baru. Guard bootstrap awal berhenti karena migration committed14 September memang membuat stub superadmin@example.test. Guard diperjelas hanya menerima tepat stub migration tersebut (identitas dan hash default diverifikasi); bootstrap mengganti password sebelum web dinyalakan. Tidak menjalankan fresh, tidak menghapus user, dan tidak membuka web memakai password stub.

Hasil HTTP terperinci tanpa password: [113-verifikasi-http-vps.json](../catatan/bukti-verifikasi-historis.md#113-verifikasi-http-vps-json). Pengujian menggunakan form action aktual `/auth/login-proses`; POST `/login` sendiri memang bukan rute login source committed. Superadmin, Krisna dan satu blackbox berhasil login, halaman dashboard/admin/sinkronisasi/mahasiswa/pendaftaran TA200, aset200, reset guest404, redirect HTTP→HTTPS aktif. Pemeriksaan setelah Proxied menggunakan alamat edge dari DNS-over-HTTPS dan memeriksa CF-Ray serta X-Dadu-Release57d06d7.

## Konfigurasi VPS yang dipertahankan

Salinan konfigurasi tanpa password tersedia pada [fixtures/vps-113](../fixtures/vps-113):

- `compose.override.yaml` diletakkan di `/opt/dadu-test/compose.override.yaml`.
- `php-lowmem.ini`, `pool-lowmem.conf`, dan `Caddyfile` berada di `/opt/dadu-test/docker/vps/`.
- Keduanya adalah konfigurasi host, dikecualikan dari status Git VPS melalui `.git/info/exclude`; source aplikasi tetap mengikuti `main`.
- Database dibatasi 384 MB, app 320 MB dengan satu child FPM, worker 192 MB, scheduler 128 MB, web 32 MB, Caddy 64 MB. Batas ini bukan reservasi RAM; swap 2 GB membantu lonjakan, tetapi bukan pengganti kapasitas RAM untuk banyak pengguna bersamaan.
- Volume `mysql_data`, `app_storage`, `caddy_data`, dan `caddy_config` harus dipertahankan. Jangan memakai `docker compose down -v` untuk update.

## Pemeriksaan dan penggunaan berikutnya

```bash
ssh root@45.158.126.251
cd /opt/dadu-test
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs --since 5m --tail 60 app worker scheduler
curl --max-time 10 -sS -o /dev/null -w 'LOGIN HTTP %{http_code}\n' http://127.0.0.1:8091/login
free -m
df -h /
```

Akun dan data awal:

1. Login memakai akun pada daftar privat `private-import/vps-accounts.txt` di komputer lokal. Salinannya pada VPS berada di `/root/dadu-test-accounts.txt` dengan mode 0600.
2. Database baru memuat 13 akun/12 mahasiswa serta satu periode akademik Ganjil 2026/2027. Belum ada periode pendaftaran Sidang TA, pengajuan lama, atau unggahan lama.
3. Untuk demo, ikuti [75 — reset demo dan urutan impor](75-reset-demo-lokal-docker-dan-urutan-impor.md), mulai dari urutan impor setelah seed. Seed sudah selesai di VPS, **jangan ulangi `migrate:fresh --seed`** untuk sekadar impor.
4. Impor profil mahasiswa → KRS → profil akademik → BPP → nilai bahasa sesuai fixture pada75; atur periode akademik yang sesuai KRS dan buat jadwal/periode Sidang TA yang diperlukan.
5. Password baru superadmin/Krisna berbeda dari server laptop lama atas persetujuan pengguna. Sebelas akun blackbox memakai `password`. Password database/APP_KEY tidak dibagikan sebagai password login aplikasi.

Backup awal tersimpan di `/opt/dadu-test/backups/` dengan prefix `20261116T023754Z`: SQL, arsip storage, environment, dan arsip konfigurasi VPS. Backup ini mencerminkan database baru setelah setup, bukan data laptop lama. Salinan backup awal juga disimpan di komputer lokal pada `private-import/vps-backups/` (di-ignore Git; berkas 0600, folder 0700). Arsip storage/konfigurasi diperiksa dapat dibaca. Backup awal dibuat manual; belum ada jadwal backup otomatis baru.

Untuk backup berikutnya:

```bash
cd /opt/dadu-test
bash docker/scripts/backup.sh </dev/null
```

Update source/image berikutnya harus mempertahankan `.env.docker`, roster privat, override resource, dan volume. Pastikan source sudah di-commit/push, lalu periksa working tree sebelum `git pull --ff-only origin main`. Pada setup ini image dibangun di komputer lokal dari commit bersih dan dikirim ke VPS; jangan langsung build app dan web bersamaan pada RAM 1 GB. Jika memakai prosedur update pada64/78, sesuaikan bagian build dengan pengiriman image tersebut dan tetap pakai override VPS. Header diagnostik `X-Dadu-Release` di Caddyfile perlu diperbarui saat merilis commit berbeda.

Kode fitur111–112 yang masih lokal tidak ikut deployment ini. Tidak ada perubahan business logic maupun commit/push dari sesi setup VPS.

### Pemeriksaan operasional

Uji baca/tulis private storage sebagai `www-data` berhasil dan marker uji dihapus; Laravel memakai timezone `Asia/Jakarta`, PHP memory limit 192 MB. `schedule:list` memuat tugas kedaluwarsa revisi dan pembersihan draft setiap menit.

Semua enam container memakai restart policy `unless-stopped`; Docker enabled saat boot. Pemeriksaan sebelum pembaruan OS mencatat OOM=false/restart=0, RAM host tersedia sekitar 356 MB dan ruang disk sekitar 16 GB. Angka ini snapshot setelah pengujian, bukan hasil uji beban.

Image Ubuntu dari penyedia masih mempunyai pembaruan keamanan tertunda. Pembaruan paket Ubuntu 24.04 dijalankan memakai `apt-get upgrade --with-new-pkgs` tanpa upgrade ke rilis Ubuntu lain; 373 paket diperbarui dan 34 paket baru dipasang, tanpa penghapusan paket. `dpkg --audit` bersih; setelah reboot kernel aktif `6.8.0-149-generic`, tidak ada restart tambahan yang diminta, dan `systemctl --failed` kosong.

### Hasil pemeriksaan setelah reboot

- Enam layanan kembali berjalan otomatis; database dan web healthy, policy seluruh layanan `unless-stopped`, Docker enabled. Tidak perlu menjalankan fresh/seed ulang.
- Source tetap `57d06d7aa87fbb87b1dbb05a0a488ef2c2321dc8`, working tree VPS bersih, file booking TA111 tidak ikut. Keempat salinan konfigurasi pada fixture113 cocok SHA-256 dengan konfigurasi aktif VPS.
- Uji konfigurasi PHP-FPM/Nginx lulus. Baca/tulis storage sebagai `www-data` berhasil. Semua13 akun cocok password/role/statusnya; terdapat12 Student,1 periode akademik,0 periode TA.
- Worker sempat restart dua kali ketika MySQL belum menerima koneksi saat boot; restart policy memulihkannya otomatis. Setelah database siap, worker stabil lebih dari18 menit, tidak ada error worker pada pemeriksaan5 menit terakhir. Semua container OOM=false. Ini berbeda dari snapshot restart0 sebelum reboot, bukan indikasi kehilangan database.
- Snapshot sesudah reboot: RAM tersedia414 MB, swap terpakai144 MB dari2 GB, disk tersedia14 GB. Angka ini bukan hasil uji beban.
- Pemeriksaan HTTPS publik setelah reboot berhasil untuk superadmin, Krisna, dan satu mahasiswa blackbox; dashboard, halaman Review TA/Sinkronisasi/Mahasiswa, halaman pendaftaran TA, serta aset CSS/JS merespons200. Sertifikat TLS diverifikasi dan CF-Ray terdeteksi. HTTP dialihkan ke HTTPS; rute registrasi/reset guest tetap404.
- Backup SQL/storage/env/konfigurasi tersedia pada VPS dan salinan privat komputer lokal. Tidak ada jadwal backup otomatis baru.

Tidak menjalankan unit test aplikasi ulang pada sesi infrastruktur ini: source bisnis tidak diubah. Validasi yang dilakukan adalah build image commit bersih, pemeriksaan konfigurasi/runtime, audit akun, reboot aktual, dan pengujian HTTP/login pada lingkungan VPS yang melayani domain.
