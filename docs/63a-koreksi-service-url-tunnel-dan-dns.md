# 63a — Service URL Tunnel dan DNS hostname

## Bukti screenshot

Screenshot095505 menunjukkan Cloudflare menolak `100.100.208.106:80` karena Service URL harus dimulai dengan protokol. Hostname dadu-test.gendhiramona.site dan HTTP Host Header sudah sesuai. UI yang dimiliki pengguna memakai **satu field Service URL**, sedangkan panduan63 sebelumnya mengasumsikan Type dan URL terpisah. Panduan63 telah dikoreksi.

Screenshot095521 dan095531 menunjukkan NPM mengarah ke dadu-test-web:8080 melalui http serta batas upload/proxy timeouts sesuai konfigurasi. Tidak perlu mengubah field tersebut berdasarkan pesan server not found saja. Server not found perlu dibedakan dari origin502; error browser itu belum membuktikan container gagal.

## 1. Perbaiki Service URL pada Cloudflare

Buka published application milik hostname dadu-test di xdranel-laptop-tunnel, lalu isi:

| Field | Nilai |
| --- | --- |
| Subdomain | dadu-test |
| Domain | gendhiramona.site |
| Path | kosong |
| Service URL | **http://100.100.208.106:80** |
| HTTP Host Header | dadu-test.gendhiramona.site |

Pastikan pesan Invalid service URL format hilang, lalu Save. Tidak menambahkan /login pada Service URL; path request browser diteruskan ke aplikasi. Protokol internal http sesuai NPM port80; browser tetap memakai URL publik https.

## 2. Periksa record DNS

Buka Cloudflare dashboard domain gendhiramona.site → DNS → Records. Pada full setup Cloudflare, menambah published hostname melalui dashboard biasanya membuat record DNS otomatis. Pastikan record untuk dadu-test ada:

| Field | Nilai |
| --- | --- |
| Type | CNAME |
| Name | dadu-test |
| Target/Content | UUID milik **xdranel-laptop-tunnel** diikuti .cfargotunnel.com |
| Proxy status | Proxied / awan oranye |
| TTL | Auto |

Jika record belum ada setelah Save berhasil, tambahkan CNAME menggunakan UUID tunnel yang benar. UUID dapat dilihat pada tunnel details atau target record domain lain yang memang memakai tunnel yang sama. Jangan memakai UUID wedding-staging-tunnel, jangan menyalin placeholder UUID, dan jangan membuat A record ke100.100.208.106. Alamat100 tersebut adalah origin yang dicapai connector, bukan alamat publik DNS browser.

Jangan menambahkan CNAME kedua jika dadu-test sudah mempunyai record yang benar. Jika ada konflik A/AAAA/CNAME pada nama yang sama, periksa record khusus dadu-test dahulu; jangan mengubah record domain utama/menu/wedding.

## 3. Verifikasi resolusi dari laptop

```bash
nslookup dadu-test.gendhiramona.site 1.1.1.1
nslookup dadu-test.gendhiramona.site
```

- Jika resolver1.1.1.1 menjawab NXDOMAIN, record belum tersedia pada resolver tersebut. Periksa Save dan record DNS; jangan langsung rebuild Docker.
- Jika resolver1.1.1.1 sudah memberi address tetapi resolver laptop belum, kemungkinan cache/resolver lokal masih menyimpan hasil sebelumnya. Simpan kedua output untuk diagnosis.
- Dengan CNAME proxied, jawaban publik dapat berupa IP Cloudflare, bukan UUID tunnel atau IP100. Itu normal.

Buka kembali https://dadu-test.gendhiramona.site/login setelah DNS resolve. Jika sekarang mendapat502/1033/404, itu hasil diagnosis baru yang perlu diperiksa melalui origin/Tunnel; kirim kode dan pesannya.

## 4. Jika DNS resolve tetapi website belum terbuka

Jalankan pada **server** untuk memeriksa lapisan aplikasi, NPM, dan jalur alamat host:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker ps
curl -I http://127.0.0.1:8091/login
curl -I -H 'Host: dadu-test.gendhiramona.site' http://100.100.208.106:80/login
docker exec nginx-proxy-manager-app-1 curl -I -H 'Host: dadu-test.gendhiramona.site' http://100.100.208.106:80/login
```

Ini tidak mencetak .env/token. Jika diperlukan, kirim outputnya bersama dua nslookup. Tidak perlu mem-posting token Tunnel atau melakukan reset database/migration untuk koreksi field URL/DNS ini.

## Verifikasi dan batas

Konfigurasi/protokol ditentukan dari screenshot nyata dan [dokumentasi routing Cloudflare](https://developers.cloudflare.com/tunnel/concepts/routing/). Asisten belum dapat mengonfirmasi DNS publik langsung karena endpoint DNS web tool tidak dapat diakses; hasil nslookup pengguna menjadi bukti berikutnya. Source aplikasi dan seluruh file Docker tidak berubah selama penulisan dokumen; hash diperiksa sebelum/sesudah masing-masing Markdown.


## Pembaruan hasil DNS dari pengguna

Resolver1.1.1.1 sudah mengembalikan A104.21.62.243/172.67.140.163 dan AAAA untuk dadu-test.gendhiramona.site. Pengguna juga mengonfirmasi CNAME menuju tunnel. Jadi hostname resolve pada resolver publik itu. Output query resolver127.0.0.53 baru menampilkan identitas server, belum jawaban/timeout; jangan menyimpulkan NXDOMAIN atau bahwa origin sudah sehat dari potongan tersebut.

Jalankan langkah berikut di **laptop tempat membuka browser**, bukan terminal SSH server. Tes ini memakai IP publik dari hasil pengguna untuk melewati lookup DNS lokal, sambil mempertahankan nama host HTTPS/TLS:

```bash
curl --connect-timeout 10 --max-time 20 \
  --resolve dadu-test.gendhiramona.site:443:104.21.62.243 \
  -sS -o /dev/null -w 'HTTP %{http_code}\n' \
  https://dadu-test.gendhiramona.site/login
```

- HTTP200: request ke login berhasil melalui jalur IP tersebut. Jika browser masih server not found, periksa resolver laptop/browser.
- HTTP301/302: jalur HTTPS tercapai tetapi ada redirect; kirim kode ini untuk melihat tujuan redirect selanjutnya.
- HTTP502/503/504: request sudah sampai lapisan HTTP; lanjutkan pemeriksaan origin/container pada bagian4 di atas.
- HTTP403 atau kode lain: kirim kode dan error yang terlihat; jangan diasumsikan masalah DNS.
- HTTP000 dengan pesan curl: kirim pesan error lengkap untuk menentukan koneksi/TLS/proxy, bukan mengganti DNS record secara acak.

Jika curl200 tetapi browser masih gagal, dan laptop memakai systemd-resolved:

```bash
sudo resolvectl flush-caches
resolvectl query dadu-test.gendhiramona.site
```

Coba kembali browser. Jika resolver OS sudah berhasil tetapi browser tetap server not found, catat browser yang digunakan dan apakah DNS-over-HTTPS aktif; resolver browser dapat berbeda. IP pada --resolve hanya tes sementara dari hasil DNS pengguna; jangan memasukkannya sebagai origin Tunnel atau mengganti CNAME permanen.

Referensi: [curl --resolve](https://curl.se/docs/manpage.html#--resolve), [resolvectl](https://www.freedesktop.org/software/systemd/man/latest/resolvectl.html). Tidak ada perubahan source/server oleh asisten; hash diperiksa sebelum/sesudah pembaruan catatan ini.


## Hasil berikutnya: HTTPS memberi502

Pengguna menjalankan curl --resolve ke IP publik104.21.62.243 dan mendapat HTTP502. Request mencapai lapisan HTTP; belum diketahui apakah gateway yang gagal ada pada Tunnel, NPM, atau web/PHP. Jangan mengubah DNS atau melakukan rebuild/reset sebelum status origin tersedia.

Jalankan blok berikut pada **server melalui SSH**, bukan laptop browser. Label output membedakan setiap sambungan. Semua probe dibatasi10 detik dan tidak mencetak body/cookie atau environment:

```bash
cd /opt/dadu-test
docker compose --env-file .env.docker ps
curl --max-time 10 -sS -o /dev/null -w 'APP HTTP %{http_code}\n' http://127.0.0.1:8091/login
docker exec nginx-proxy-manager-app-1 curl --max-time 10 -sS -o /dev/null -w 'NPM_TO_APP HTTP %{http_code}\n' http://dadu-test-web:8080/login
curl --max-time 10 -sS -o /dev/null -w 'HOST_TO_NPM HTTP %{http_code}\n' -H 'Host: dadu-test.gendhiramona.site' http://100.100.208.106:80/login
docker exec nginx-proxy-manager-app-1 curl --max-time 10 -sS -o /dev/null -w 'DOCKER_TO_HOST_NPM HTTP %{http_code}\n' -H 'Host: dadu-test.gendhiramona.site' http://100.100.208.106:80/login
```

Kirim seluruh output status dan empat probe itu. Interpretasi awal:

- APP gagal: periksa container web/PHP/DB lebih dahulu.
- APP200 tetapi NPM_TO_APP gagal: periksa alias/network proxy-net dan DNS container.
- Dua probe pertama200 tetapi HOST_TO_NPM gagal: periksa Proxy Host, Host header dan rute ke alamat host.
- HOST_TO_NPM200 tetapi DOCKER_TO_HOST_NPM gagal: alamat host belum dapat dipakai connector Docker, sehingga Service origin belum bisa dianggap benar untuk kedua replica.
- Semua200 tetapi publik502: periksa published route/protokol/target CNAME/tunnel dan kedua connector. Tidak mengubah origin dengan menebak.

Jika APP502/000 atau container Restarting/Exited, log tambahan yang relevan:

```bash
docker compose --env-file .env.docker logs --tail 60 app web
```

Ini diagnosis berikutnya, belum patch Docker. Asisten tidak mengakses SSH atau menjalankan command server. Source dan file deployment di-hash sebelum/sesudah penulisan catatan ini; tidak berubah.
