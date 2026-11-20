# Review Pelaksanaan Sidang TA dan ruangan

- Menu Form dan judul halaman staf memakai **Review Pelaksanaan Sidang TA**. Mahasiswa tetap membuka **Pelaksanaan Sidang TA** lewat menu sendiri.
- Akun manual Pelaksana di staging dapat mengakses Dashboard, Pengajuan Peminjaman Ruangan, dan Daftar Peminjaman Saya sesuai izin masing-masing. Pengecekan VPS mengonfirmasi `dosen.ta@dosen.test` sudah punya izin baca, ajukan, dan ubah. Akses review memerlukan izin terpisah. Pembaruan aplikasi cukup untuk akun ini.
- Simpan dan Batal pada Edit Ruangan kembali ke nomor halaman, kata pencarian, gedung, dan status yang dipakai ketika membuka form.

## Pencarian jadwal

Pencarian menawarkan slot 90 menit dalam rentang pelaksanaan untuk kelas berstatus **Dapat Dipakai**. Slot harus kosong menurut jadwal kuliah semester, jadwal per tanggal yang sudah diimpor, dan peminjaman tercatat yang menunggu persetujuan atau sudah disetujui. Ketersediaan diperiksa lagi saat penetapan. Masukkan pemakaian di luar aplikasi melalui Peminjaman Ruangan agar ikut diperhitungkan.

## Pemeriksaan master dan VPS

`DataRuangan.xlsx` memuat 67 kelas, 46 aktif dan 21 nonaktif. Dari 69 label ruang di ekspor kuliah iGracias, 51 cocok langsung dengan kode atau nama setelah normalisasi. Sebanyak 18 label masih di luar pencocokan, yaitu 16 label lab, `KTT AULA`, dan `KTT 1.27 B`. Jangan petakan label yang berada di luar cakupan Akademik ke kelas yang berbeda. Identitas `KTT 1.27 B` perlu dikonfirmasi. Importer saat ini menolak snapshot dengan label yang belum terpetakan.

VPS masih berada di commit `6096455` saat diperiksa. Database berisi 60 ruang, 46 aktif dan 14 nonaktif, tanpa pola mingguan, sumber semester lengkap, atau transaksi peminjaman. Impor XLSX memperbarui berdasarkan **Kode Ruang** dan menambahkan kode baru. Cocokkan kode lama dengan kelas fisik yang sama sebelum memasukkan master terbaru agar satu ruang tidak muncul dengan dua ID dan terlihat kosong melalui salah satu ID.

Sebanyak 85 tes terkait lulus dengan 389 asersi. Pengujian alur mencakup peminjaman dari akun admin, Akademik, dan dosen manual di staging, persetujuan oleh petugas berwenang, serta penolakan slot yang sudah terpakai. Pemeriksaan VPS dilakukan secara baca saja.
