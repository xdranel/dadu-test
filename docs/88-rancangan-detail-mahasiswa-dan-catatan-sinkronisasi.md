# 88 — Rancangan detail mahasiswa dan catatan sinkronisasi

Permintaan10 November2026 meliputi modal catatan impor dan penyelarasan halaman Data Akademik → Mahasiswa → View. Pengguna mengonfirmasi bahwa History Nilai (KRS Aktif) berarti nilai tersimpan untuk kode mata kuliah pada KRS aktif. Penyesuaian Kehadiran menunggu contoh JSON; kerjakan bagian lain dahulu dan berikan rangkuman data yang dibutuhkan pada hasil akhir.

## Sumber data dan tampilan

- Profil serta kartu yang dinyatakan benar dipertahankan.
- Rencana Studi & Nilai tetap satu kartu, berisi empat bagian yang dapat dibuka/ditutup: Mata Kuliah Semester Ini (KRS Aktif), History Nilai (KRS Aktif), Mata Kuliah Semua Semester, Histori Nilai. Gunakan elemen details/summary dengan chevron dan dukungan keyboard; bagian KRS aktif terbuka awalnya.
- Nilai KRS aktif disaring berdasarkan kode mata kuliah, bukan diklaim sebagai nilai yang diperoleh pada semester aktif. Semua mata kuliah tetap memperlihatkan periode/semester yang tersedia; nilai historis tidak ditimpa atau digabung menjadi satu nilai.
- EPRT menggunakan pembaca fakta/riwayat resmi yang dipakai panel Sidang TA, termasuk jenis tes, nilai, tanggal, masa berlaku, dan status. Bila profil resmi ada tetapi kelompok bahasa tidak tersedia, jangan mengambil skor lama sebagai skor resmi.
- TAK menampilkan total resmi yang sama dengan panel Sidang TA, riwayat TAK tersimpan bila ada, serta kegiatan lokal sebagai data terpisah. Skor nol tetap berbeda dari data belum tersedia; tidak mengarang rincian kegiatan dari total TAK.
- Kehadiran tetap membaca hasil impor kehadiran yang tersedia. Jangan menyimpulkan tidak hadir dari JSON yang tidak menyediakan kehadiran. Contoh yang perlu diminta dirangkum di dokumen hasil.
- Nama tab menjadi Data Yudisium & Tugas Akhir. BPP menampilkan status tersimpan untuk seluruh periode dalam profil resmi, dengan fallback data lama yang ditandai belum memiliki periode. Ini adalah riwayat status per periode, bukan catatan transaksi yang tidak disediakan sumber.
- SKPI mengganti JSON mentah menjadi status keseluruhan, status1–3, serta approval. Nilai yang tidak tersedia ditulis belum tersedia; warna hanya mengikuti status yang dikenali.
- Tambahkan kartu Persetujuan Sidang iGracias dengan status resmi dan warna sesuai persetujuannya.

## Catatan sinkronisasi

Modal diakses dari tombol pada kolom Keterangan dan dapat digulir. Impor selesai tanpa catatan menampilkan Selesai. Catatan diidentifikasi dengan NIM, nama bila tersedia, urutan record manusia (mulai1), path JSON, dan baris fisik JSON bila sumber unggahan tersedia. Jangan menyebut indeks record sebagai baris IDE.

Pendekatan yang dipilih: simpan detail terstruktur baru secara aditif; pertahankan pesan lama agar log historis tetap terbaca. Pesan lama yang sudah dipotong tidak dapat dipulihkan. Seluruh catatan impor baru disimpan, termasuk setelah catatan ke10. Parser lokasi hanya membaca struktur JSON yang sudah valid; tidak menyimpan keseluruhan berkas pribadi. Modal meng-escape isi unggahan dan tetap dibatasi izin sync_read.

Kolom opsional yang tidak dikirim pada impor parsial bukan otomatis error. Laporkan validasi/skip/error yang benar-benar terjadi, termasuk kegagalan per menu sumber. Untuk impor tanpa berkas (endpoint), baris fisik tidak tersedia dan ditampilkan jujur sebagai urutan record.

## Batas pekerjaan

Tidak melakukan reset/seed, impor data aplikasi, deployment, perubahan SSO, atau perubahan keputusan kelayakan Sidang TA. Implementasi/pengujian memakai worktree terisolasi yang telah tersedia. Setelah lolos verifikasi, salin berkas lingkup ini ke dadu serta dadu-test dengan pemeriksaan hash dan backup; jaga perubahan pengguna di luar lingkup. Produksi tetap ditunda.
