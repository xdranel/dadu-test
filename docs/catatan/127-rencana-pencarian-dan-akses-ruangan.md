# 127 — Rencana diagnosis ketersediaan dan akses peminjaman admin

Urutan: rescan sumber → reproduksi → tes gagal → perubahan terbatas → regresi → rescan/salin source → catatan hasil/panduan.

Temuan awal: JSON sumber memiliki 827 baris TETAP pada 9–15 Nov 2026, periode 2627/1. XLSX ruangan memiliki 60 master (46 Aktif/14 NonAktif) tanpa kolom klasifikasi kelas.69 label ruang JSON tidak cocok langsung dengan kode/nama master tanpa alias. Master dosen tidak menjadi penghalang pencarian ruang karena pemeriksaan bentrok dosen belum diaktifkan.

Tidak menyamakan nama ruangan ambigu atau melonggarkan syarat kelengkapan snapshot. Tidak mengklasifikasikan LAB sebagai kelas atau mengubah flag master berdasarkan tebakan. Minta jumlah/status sumber dari VPS; SSH baca saja tidak diterima dengan key sesi ini, sehingga pemeriksaan melalui command PHP baca saja oleh pengguna.

- [x] Reproduksi parser minggu dan alur master→source; pisahkan kelas belum ditandai, snapshot/alias belum lengkap, dan slot 90 menit yang benar-benar penuh pada pesan pencarian.
- [x] Admin/Akademik dapat memakai submenu Dashboard, Pengajuan dan Daftar Saya sesuai permission. Gunakan helper peminjam yang sama pada controller dan pembatalan owner-pending; dosen lama tetap mengikuti aturan yang sudah ada.
- [x] Admin biasa tetap membuat booking pending; Review tetap permission terpisah. Tidak mengubah seeder/role existing lewat deployment.
- [x] RED/GREEN, regresi peminjaman/TA/sharedavailability; build/lint sesuai perubahan.
- [x] Salin kode/tes ke dadu dengan backup/hash, dokumentasi hanya dadu-test. Tambah kesimpulan127 dan panduan ruangan; fixture privat/config dipertahankan.

Tidak menjalankan migration/reset/seed pada aplikasi/server, atau commit/push/deploy. Pengujian hanya DB dadu_testing.

## Penutupan

VPS dapat diakses dan diperiksa baca saja setelah pengguna mengizinkan sesi ini; autentikasi key awal tidak diterima, koneksi berikutnya berhasil. Tidak ada credential ditulis pada dokumentasi atau commit. Impor JSON asli memang ditolak karena 69 alias belum pasti; log sukses 1 baris berasal dari file dummy tanggal tertentu. Data/master/booking VPS tetap dipertahankan.

Delapan RED sebelum perubahan; 59 tes regresi lulus, reproduksi sumber asli 1 tes lulus, suite penuh 1.284 lulus/45 baseline gagal/2 dilewati tanpa tambahan. Pemeriksaan halaman setelah label 21 tes lulus. Rescan 753 source file/9 salinan hash cocok, lima adaptasi target dipertahankan. Data JSON/konfigurasi fixture tidak diubah. Tidak ada migration baru, commit, push atau deployment.

Pemetaan 69 label dibuat sebagai catatan konfirmasi saja (44 calon berbasis teks,25 belum mempunyai pasangan pola). Klasifikasi dan alias pada VPS tetap harus ditetapkan dari identitas ruang yang benar; hasil ini tidak menganggap data tersebut sudah diperbaiki.
