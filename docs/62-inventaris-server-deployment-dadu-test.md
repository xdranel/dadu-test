# 62 — Inventaris server untuk hosting repo privat dadu-test

## Tujuan dan lingkup

Pengguna menjelaskan bahwa dadu-test adalah clone/repo privat miliknya, terpisah dari repo development asli milik orang lain. Repo privat akan di-host pada laptop server2 core/4 thread yang sudah menjalankan Docker, Portainer, Nginx Proxy Manager dan Cloudflare untuk beberapa aplikasi test. Asisten tetap hanya menulis docs; tidak mengubah source aplikasi atau menjalankan SSH/deploy ke server.

Akses DADU direncanakan untuk pengguna, developer asli, dan beberapa penguji divisi menggunakan role aplikasi superadmin/Petugas/Mahasiswa. Arahan awal adalah menempatkan Cloudflare Access dengan daftar email penguji di depan subdomain, kemudian tetap memakai login aplikasi. OTP harus dipasangkan dengan daftar email yang diizinkan, bukan membuka akses bagi sembarang alamat. Detail belum diterapkan dan menunggu topologi server. Pembatasan Access tidak memperbaiki masalah reset Auth61f; masalah tersebut tetap dicatat.

## Hasil scan repo

- composer.json mensyaratkan PHP `^8.4.27`, Laravel13; image PHP nantinya harus benar-benar memenuhi constraints serta composer.lock, bukan sekadar memakai PHP8.x.
- package.json meminta Node>=20/npm>=10 untuk build Vite. Node dibutuhkan ketika membangun aset; metode build dipilih sesudah kapasitas server diketahui.
- Tidak ada Compose aplikasi utama Laravel; Compose yang ada hanya academic-chatbot. Jangan menjalankannya untuk menganggap Laravel sudah ikut di-host.
- deploy.sh lama menjalankan git pull origin develop dan perintah runtime native. Jangan dipakai untuk repo privat Docker ini; branch/remote server belum diketahui.
- MySQL menjadi arah database sesuai pengujian Podman yang sudah lulus; collation yang dipakai utf8mb4_0900_ai_ci. Jangan mengganti lagi ke MariaDB lokal yang sebelumnya bermasalah. Versi image dan apakah memakai DB terpisah atau existing menunggu inventaris.
- Storage local memakai storage/app; dokumen TA diunduh lewat route berotorisasi dan perlu volume persisten. Jangan mengekspos seluruh storage/app sebagai folder publik. storage/app/public adalah cakupan berbeda.
- routes/console.php menjalankan expiry revisi TA setiap menit, tetapi juga task antrean harian08:00 Asia/Jakarta. Scheduler menjalankan semua task terdaftar, bukan khusus TA. Perlu proses terkelola dan database aplikasi test yang sesuai.
- Queue/session/cache contoh memakai database; kebutuhan worker diputuskan setelah melihat job yang digunakan dan resource. Tidak mengasumsikan Redis wajib.
- Academic chatbot/Qwen7B adalah beban terpisah; deployment Laravel awal tidak otomatis menyalakan LLM sebelum kapasitas RAM/tujuan chatbot dipastikan.
- Batas upload PHP/webserver/proxy harus konsisten dengan form TA agar masalah POST terlalu besar tidak berulang.

## 1. Login SSH seperti biasa

Jalankan pada laptop development:

```bash
ssh \
  -L 8181:localhost:81 \
  -L 9443:localhost:9443 \
  xdranel@100.100.208.106
```

Forward8181/9443 yang sudah biasa dipakai pengguna dipertahankan. Port aplikasi baru belum dipilih; jangan membuka port atau mengubah proxy saat inventaris.

## 2. Jalankan inventaris di terminal server

Blok ini hanya membaca keadaan OS/Docker dan menulis satu report sementara di /tmp. Tidak install, restart, prune, pull image, atau mengubah service/database. Docker inspect dibatasi pada network/project/restart; tidak mencetak Env/Cmd/Args/token. Status cloudflared inactive/not-found berarti belum ada service host dengan nama itu; cloudflared bisa tetap berjalan sebagai container atau nama unit lain.

Jika Docker permission denied, kirim error tersebut bersama hasil; jangan mengubah permission socket Docker untuk memaksa akses.

```bash
bash <<'DADU_SERVER_INVENTORY' 2>&1 | tee /tmp/dadu-server-inventory.txt
section() {
    printf '\n[%s]\n' "$1"
}
section 'OS dan CPU'
date -Is
cat /etc/os-release
uname -m
lscpu | head -25
section 'RAM, swap, disk, dan load'
free -h
swapon --show
df -hT /
uptime
section 'Docker dan Compose'
docker version --format 'Docker server: {{.Server.Version}}'
docker compose version
docker info --format 'OS={{.OperatingSystem}} arch={{.Architecture}} CPU={{.NCPU}} RAM_bytes={{.MemTotal}} data_root={{.DockerRootDir}}'
section 'Container aktif dan port'
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}'
section 'Project Compose'
docker compose ls
section 'Network Docker'
docker network ls
section 'Koneksi container ke network'
docker ps -q | xargs -r docker inspect --format '{{.Name}} | mode={{.HostConfig.NetworkMode}} | networks={{range $name, $value := .NetworkSettings.Networks}}{{$name}} {{end}} | project={{index .Config.Labels "com.docker.compose.project"}} | restart={{.HostConfig.RestartPolicy.Name}}'
section 'Pemakaian container saat ini'
docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}'
section 'Pemakaian disk Docker'
docker system df
section 'Port TCP host'
ss -lnt
section 'Cloudflared pada host bila ada'
systemctl is-active cloudflared
section 'Git'
git --version
DADU_SERVER_INVENTORY

```

Pemakaian RAM/CPU adalah snapshot sesaat, bukan estimasi puncak. Docker system df hanya menghitung penggunaan, tidak membersihkan apa pun. Jika Docker root memakai disk terpisah dari /, info data_root akan menjadi petunjuk untuk pemeriksaan filesystem berikutnya.

## 3. Ambil report ke laptop development

Buka terminal **lokal baru**, tanpa menutup SSH. Jalankan:

```bash
scp xdranel@100.100.208.106:/tmp/dadu-server-inventory.txt /tmp/dadu-server-inventory.txt
```

Lalu beri tahu asisten bahwa file sudah diambil; asisten bisa membaca /tmp/dadu-server-inventory.txt lokal langsung. Alternatifnya kirim output report sebagai teks.

Jangan mengirim .env, isi docker inspect lengkap, cloudflared token, private key SSH, PAT Git, credential JSON, atau dump database. Report yang diminta di atas tidak mengambil nilai-nilai tersebut.

## 4. Informasi dashboard yang diperlukan bersama report

Kirim sebagai teks ketika sudah tersedia:

1. Subdomain yang ingin dipakai untuk DADU.
2. Untuk **satu aplikasi test yang sudah berjalan**, Cloudflare memakai DNS proxied A/CNAME atau Public Hostname pada Tunnel? Jika Tunnel, tulis Service origin-nya, misalnya http://nginx-proxy-manager:80 atau http://IP-LAN:PORT, tanpa token tunnel. Jika belum tahu, cukup sebut bahwa memakai menu Tunnel/Public Hostname atau menu DNS.
3. Apakah Nginx Proxy Manager mengurus semua domain atau ada aplikasi yang langsung diarahkan dari Tunnel ke container?
4. Repo privat ada di GitHub/GitLab/provider lain dan branch deployment yang ingin dipakai. URL SSH repo boleh diberikan; jangan sertakan token/password pada URL.

Daftar email penguji tidak harus dibagikan ke asisten; pengguna bisa memasukkannya sendiri saat panduan Access disiapkan. Jika orang penguji memakai jaringan berbeda, gate Access berlaku berdasarkan identitas email, bukan mengharuskan semuanya masuk Tailscale hanya untuk membuka aplikasi.

## Sesudah report tersedia

Rancangan akan disesuaikan dengan kapasitas dan jaringan yang ada: container Laravel, database, volume upload, build aset, scheduler/worker, proxy internal, Access/DNS/Tunnel, deploy dari repo privat, backup serta rollback. Network/port/image/resource belum ditetapkan pada62. Port admin NPM/Portainer tidak dijadikan port aplikasi DADU.

Pendekatan Docker diprioritaskan karena pengguna sudah mengoperasikannya. Pemilihan DB terpisah vs existing dan jalur NPM/Tunnel akan dibahas berdasarkan bukti inventaris; tidak akan memindahkan atau mematikan aplikasi lain sebagai prasyarat.

## Referensi dan verifikasi

Syntax CLI inventaris diperiksa terhadap [Docker inspect](https://docs.docker.com/reference/cli/docker/inspect/), [Docker stats](https://docs.docker.com/reference/cli/docker/container/stats/), dan [Docker Compose ls](https://docs.docker.com/reference/cli/docker/compose/ls/). Pembatasan identitas merujuk [Cloudflare Access policies](https://developers.cloudflare.com/cloudflare-one/access-controls/policies/).

Script lolos bash syntax check tanpa dieksekusi oleh asisten. Source kedua clone di-hash sebelum/sesudah tiap penulisan Markdown dan tidak berubah. Tidak ada SSH atau operasi server yang dijalankan oleh asisten; report server masih menunggu eksekusi pengguna.
