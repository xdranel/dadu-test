# 79 — Validasi Sidang TA, periode BPP, dan fixture bahasa bertanggal

Pembaruan 7 November 2026 mengikuti gambar `gambar-fix/01.jpeg` sampai `05.jpeg` dan sumber `rekap_igracias_2026-11-07 NILAI BAHASA UPDATE.json`. Rencana awal dijalankan dalam tiga bagian: validasi WA/IPK, tampilan dan konfirmasi, lalu fixture, regresi, dan integrasi.

## 1. Perubahan formulir

- Nomor WA mahasiswa dan wali wajib berupa string 8–15 digit ASCII dengan awalan `08` atau `628`. Nol depan dipertahankan. Aturan berlaku pada browser, penyimpanan draft, dan pengiriman awal maupun revisi.
- Bintang wajib berwarna merah pada periode, kedua WA, tanggal pembayaran, pilihan biodata, dan tanggal pengajuan PDM.
- IPK minimal **2,00** menjadi syarat akademik pengiriman. Nilai 1,99 menahan kirim; 2,00 sudah memenuhi. IPK kosong atau tidak sah belum terkonfirmasi. Saat profil resmi tersedia, nilai hanya dibaca dari `groups.academic.gpa`; nilai manual tidak dipinjam untuk meluluskan profil resmi yang belum lengkap. Pemeriksaan diulang ketika benar-benar mengirim.
- IPK tampil pada grid pemeriksaan persyaratan. BPP dan persetujuan iGracias memakai kartu berwarna: positif hijau, negatif merah, belum tersedia netral. Keduanya tetap informasi, tidak menjadi syarat pengiriman baru.
- Daftar peringatan akademik kecil di bawah panel kanan dihapus. Pada halaman tinjau, alasan yang menahan pengiriman tampil dekat tombol kirim di bawah. Kartu persyaratan tetap tersedia.
- Teks pernyataan konfirmasi tanpa bintang. Tombol **Ya, kirim pendaftaran** awalnya disabled, aktif setelah checkbox dicentang, kembali disabled ketika dilepas atau sedang mengirim. Server tetap mewajibkan persetujuan.

Aturan EPRT tetap: nilai 450 sudah memenuhi; nilai di bawah 450 setelah tiga tes memerlukan surat permakluman. Formulir dan data SSO tidak diubah.

## 2. Pemeriksaan BPP Docker

Pengguna mengonfirmasi urutan `75-03` kemudian `75-04`, dan gejala berasal dari Docker dadu-test. Pemeriksaan baca saja menemukan 12 profil dengan periode BPP saat ini `null`: 10 berstatus LUNAS dan 2 KREDIT. Satu profil mempunyai riwayat periode, tetapi periode riwayat tidak boleh ditempelkan pada status terbaru tanpa bukti bahwa keduanya satu entri.

Hash lima berkas jalur impor/snapshot di container sama dengan kode sumber sebelum pembaruan79. Uji upload melalui kedua endpoint Profil Akademik membuktikan urutan profil tanpa periode → finance `2627/1` menyimpan periode dan menampilkannya pada kartu. Pemeriksaan ini belum membuktikan mengapa upload terdahulu tidak meninggalkan periode pada seluruh profil; nama berkas upload saja tidak memastikan isi yang diterima runtime saat itu. Tidak dilakukan perubahan database/server selama diagnosis.

Fixture `75-03` terbaru sekarang juga memuat `periode_keuangan: "2627/1"` sebagai konteks **sintetis** yang sama dengan `75-04`. Pembaruan bahasa `75-05` memilih menu bahasa saja sehingga tidak menimpa BPP. UI tidak mengarang periode untuk data lama yang masih kosong.

Setelah kode Docker diperbarui, impor ulang fixture terbaru **75-04** sebagai **Profil Akademik**, lalu **75-05** sebagai **Profil Akademik**. Periksa log setiap impor. Kartu BPP harus menyebut `2627/1`. Jika tetap kosong, simpan hasil log impor tersebut dan bandingkan kode host/container; jangan melakukan fresh untuk diagnosis ini.

## 3. Sumber bahasa dan tanggal dummy

Sumber tambahan berisi 277 mahasiswa dan 555 entri riwayat yang membawa `tanggal_tes`. Tidak ada kecocokan NIM dengan 11 identitas blackbox. Salinan sumber disimpan byte-identik pada folder privat `data_dadu/sumber`; tanggal/nilai mahasiswa nyata tidak digabung berdasarkan indeks dengan data dummy.

Untuk dummy lokal dan Docker, generator menghasilkan `75-05-bahasa-demo.json`, khusus `selected_menus: ["bahasa"]`, berisi 12 mahasiswa dengan skenario nilai, jenis, jumlah tes, dan status yang tetap. Tanggal yang belum ada dibuat khusus fixture dan ditandai `tanggal_tes_sintetis: true`:

- Jika ada kedaluwarsa, gunakan dua tahun kalender sebelumnya; hari kabisat dijepit ke hari terakhir bulan.
- Jika kedaluwarsa kosong, gunakan `2026-10-01`.
- Tanggal buatan dibatasi paling akhir `2026-11-07`; tanggal eksplisit yang sudah ada dipertahankan.

Ini merupakan aturan pembuatan fixture, bukan cara menebak tanggal tes resmi. Fixture publik `67-03` dan `67-04` juga diperbarui dengan tanggal dummy bertanda sintetis. Parser produksi sudah mendukung tanggal tes Indonesia dan tidak diubah.

SHA-256 sumber bahasa tambahan: `cb383175bf3222b72f32cf267b321200bd11fd4b497d19373072b1fae3ef7ab6`.

SHA-256 fixture bahasa turunan: `39991f424f5ee61f8f0cd979ce9883a3849ec4d87e1fd40f3e8ad407970f6eb3`.

## 4. Langkah lokal dan Docker

Kode dan fixture privat dipersiapkan pada kedua repo laptop. Database aplikasi tidak direset, di-seed, atau diimpor oleh pekerjaan ini. Perubahan tidak membutuhkan migration baru.

Development lokal memakai `/home/xdranel/Code/Project/php-laravel/dadu`. Jalankan proses Vite development yang biasa digunakan, atau `npm run build` bila memakai aset hasil build. Untuk data yang sudah ada, impor ulang `75-04` lalu `75-05` dari folder `docs/fixtures/sidang-ta/data_dadu/impor`.

Untuk Docker, setelah commit/push jalankan **bagian4 Update Docker pada [panduan78](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md)** (alur update rutin juga tersedia pada panduan64 bagian12.2 di repo Docker), termasuk build **app dan web** serta recreate container. Perubahan ini membawa PHP dan aset frontend. Pemeriksaan hash file saja tidak membuktikan FPM telah memuat versi baru; image/container yang melayani website harus ikut diperbarui. Setelah update, unggah fixture dari laptop melalui Sinkronisasi Data. JSON privat diabaikan Git dan tidak ikut image.

Bila memang melakukan reset baru, ikuti [panduan75](75-reset-demo-lokal-docker-dan-urutan-impor.md). Urutan yang sama untuk lokal dan Docker:

| Urutan | Berkas | Tipe impor |
| --- | --- | --- |
| 1 | `75-01-mahasiswa-demo.json` | Mahasiswa |
| 2 | `75-02b-krs-demo-skenario-ta.json` | KRS |
| 3 | `75-03-profil-akademik-demo.json` | Profil Akademik |
| 4 | `75-04-bpp-demo.json` | Profil Akademik |
| 5 | `75-05-bahasa-demo.json` | Profil Akademik |

`75-02-krs-demo-sumber.json` merupakan alternatif langkah2 untuk menguji KRS sumber tanpa tambahan TA. Jangan mengimpornya setelah `75-02b` jika ingin mempertahankan skenario TA. Akun, password, roster, dan jumlah KRS tetap mengikuti panduan75.

## 5. Verifikasi

Tes baru terlebih dahulu mereproduksi awalan WA yang sebelumnya lolos, IPK yang belum menjadi syarat, bintang, posisi peringatan, dan tombol konfirmasi yang belum menunggu checkbox. Tes generator juga terlebih dahulu gagal saat sumber ketujuh dan fixture bahasa belum tersedia.

- Regresi kebijakan, seluruh fitur Sidang TA, dan impor profil: **477 lulus, 5.207 assertion**.
- JavaScript: **20 lulus**, termasuk centang → lepas centang → mengirim dan awalan WA.
- Generator fixture: **2 lulus, 229 assertion**, termasuk kompatibilitas enam sumber lama.
- Build Vite berhasil. Review independen tidak menemukan masalah material.

Seluruh suite: **764 lulus, 30 gagal, 6.919 assertion**. Tiga puluh kegagalan mempunyai kelas/lokasi yang sama dengan baseline: reset/update password, jadwal ujian, wisuda, dokumen publik, antrean, dan sinkronisasi lama. Tidak ada kegagalan baru.

Pemeriksaan sumber privat membuktikan tujuh salinan identik dengan Downloads, 277 mahasiswa/555 riwayat bertanggal, nol kecocokan dengan 11 NIM blackbox, dan 12 skenario tetap kecuali penambahan tanggal/penegasan periode sintetis. Roster, master mahasiswa, KRS 84/95 entri, dan status BPP tetap sama; metadata waktu pembuatan fixture dapat berubah. Lint 17 berkas PHP dan syntax 14 blok Bash panduan lulus.

Database tes memakai `dadu_testing`, terpisah dari database aplikasi. Tidak dilakukan deploy atau commit/push otomatis.

Integrasi akhir: 28 berkas kode/panduan/tes dan 16 berkas privat disalin ke repo Docker laptop dan development asli setelah pemeriksaan hash tujuan. Seluruh 44 berkas per repo diverifikasi identik dengan worktree teruji; fixture privat tetap diabaikan Git. Build frontend berhasil pada worktree dan kedua repo tujuan. Server Docker hanya diperiksa baca saja; penerapan kode dan impor UI tetap mengikuti bagian4 di atas.
