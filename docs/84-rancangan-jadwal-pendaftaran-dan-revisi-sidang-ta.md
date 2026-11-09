# 84 — Rancangan pemisahan jadwal pendaftaran dan revisi Sidang TA

Status: disetujui pengguna pada9November2026, dilanjutkan dengan rencana85. Pemeriksaan storage produksi dari83 tetap ditunda.

## Tujuan

Periode pendaftaran hanya mengatur pendaftaran baru. Kelola Periode menyediakan jadwal verifikasi/revisi terpisah. Mahasiswa maupun petugas/admin dapat melihat tanggal yang sama. Hitung mundur memakai empat kotak hari/jam/menit/detik mengikuti pola Review Yudisium yang sudah ada.

Contoh jadwal yang diberikan pengguna:

| Tahap | Contoh |
| --- | --- |
| Pendaftaran Sidang TA | 9–11 November2026 |
| Verifikasi Berkas Pendaftaran Sidang | 12–20 November2026 |

Draft pendaftaran baru mengikuti penutupan pendaftaran. Draft revisi mengikuti tenggat revisi yang baru; tidak lagi dibersihkan hanya karena pendaftaran telah tutup. Berkas final pengajuan yang sudah dikirim tetap disimpan.

## Temuan kode saat ini

- `SidangTaPeriod` hanya mempunyai `open_at` dan `close_at` untuk jadwal.
- `SidangTaRevisionDeadline` menggunakan `period.close_at` untuk menahan pengerjaan dan pengiriman revisi.
- `SidangTaRevisionExpiry` memilih pengajuan `needs_review` dari periode yang `close_at` sudah terlewati dan menolak otomatis. Perubahan ini akan memindahkan acuan waktunya, bukan menghapus pengajuan historis.
- `SidangTaExpiredDraftCleanup` memilih seluruh draft aktif berdasarkan `period.close_at`, termasuk draft revisi. Seleksi awal dan pemeriksaan ulang di dalam transaksi sama-sama perlu dibedakan menurut jenis draft.
- Mahasiswa melihat periode melalui pengajuan/draft miliknya. Daftar periode yang terbuka untuk pendaftaran saja tidak mencukupi untuk menampilkan jadwal revisi sesudah pendaftaran tutup.

## Keputusan yang disetujui

- Verifikasi/revisi memakai tanggal mulai dan selesai tersendiri. Tanggal mulai boleh bertepatan dengan pendaftaran, termasuk tanggal9; tidak diwajibkan menunggu pendaftaran tutup.
- Admin/petugas mengisi jadwal pada periode aktif yang sama. Untuk periode lama tanpa jadwal lengkap, penutupan otomatis revisi ditunda sampai jadwal diisi. Pengerjaan revisi yang sudah diminta tetap tersedia selama jadwal belum ditetapkan.
- Di Kelola Periode, bagian atas berjudul Pendaftaran Sidang TA; bagian bawah berjudul Tanggal Verifikasi Berkas.
- Countdown menggunakan empat kotak hari/jam/menit/detik dengan pola Review Yudisium dan dapat dilihat mahasiswa maupun petugas.
- Pada status needs_review, sembunyikan tombol Lihat pengajuan aktif; Mulai revisi dan Detail Revisi tetap tersedia.
- Riwayat mahasiswa memasukkan penolakan, menghapus identitas pelaku dari tampilan/payload mahasiswa, serta mempertahankan alasan. Audit internal petugas tetap tersedia.

## Perlakuan pengajuan produksi yang sudah ada

Pengajuan terhubung ke periode melalui `sidang_ta_period_id`. Rancangan menambah jadwal pada periode yang sama; tidak mengganti ID periode, membuat ulang akun, memindahkan pengajuan, atau menjalankan fresh/seed. Akun mahasiswa tetap sama; pengajuannya yang mengacu ke periode.

Administrator akan mengisi jadwal baru pada periode yang sudah dipakai pendaftar. Pengajuan yang telah dikirim, snapshot, dokumen final, dan riwayat tetap disimpan. Pengajuan yang sebelumnya sudah ditolak otomatis atau draft yang sudah dibersihkan tidak dipulihkan hanya dengan memperpanjang tanggal.

## Rencana implementasi setelah aturan jadwal dipastikan

1. Migration tambahan pada tabel periode dan aturan kompatibilitas periode lama sesuai jawaban pengguna.
2. Form Kelola Periode dan validasi tanggal; pemeriksaan revisi memakai jadwal baru dan membaca ulang periode pada transaksi yang relevan.
3. Seleksi/recheck expiry dan cleanup menurut jenis draft: awal vs revisi. Penghapusan hanya menyentuh berkas milik draft yang tidak lagi dirujuk; dokumen final yang dipinjam tidak dihapus.
4. Partial jadwal dan countdown yang dipakai bersama pada halaman mahasiswa serta review/kelola periode petugas. Pemilihan periode pada wizard harus memperbarui kedua jadwal agar tampilan tidak tertinggal.
5. Tes batas waktu, perpanjangan periode, draft awal vs revisi, data periode lama, relasi pengajuan produksi, tampilan mahasiswa/petugas, JavaScript, dan build frontend. Integrasi ke kedua repo mengikuti pemeriksaan hash tujuan seperti pekerjaan sebelumnya.

Tampilan tanggal mengikuti WIB. Countdown hanya membantu tampilan; server tetap memeriksa jadwal saat menerima penyimpanan/pengiriman.
