# Label master ruangan dan impor jadwal iGracias

`DataRuangan.xlsx` terbaru memuat 79 kode ruang, dengan 63 `KELAS`, 12 `LAB`, dan empat label kosong. `KTT 1.27 B` adalah kode kelas yang benar dan langsung cocok dengan iGracias. Empat label yang belum ditentukan dan rincian lab yang kosong dapat dilengkapi Akademik lewat Edit Ruangan. Data manual tetap tersimpan saat Excel dengan sel kosong diimpor ulang.

Kolom **Label** ada setelah Kapasitas. Form Tambah dan Edit menyediakan `KELAS`, `LAB`, dan pilihan kosong. Input alias sebelumnya dihapus dan pemetaan yang sudah tersimpan tetap dapat dipakai importer.

Pencarian dan penetapan baru pada Sidang TA serta Peminjaman Ruangan hanya bisa menggunakan ruang `KELAS` berstatus **Dapat Dipakai**. Excel terbaru memberi 46 kandidat sebelum pemeriksaan bentrok. Status master mengikuti Excel dan riwayat booking lama tetap tersimpan.

Uji terisolasi dengan Excel dan JSON asli menghasilkan **798 pola tersimpan**, termasuk sembilan untuk `KTT 1.27 B`. Sebanyak 27 pola dari `DATAI LAB.`, `COS(PI) LAB.`, `PORSCE LAB.`, `BEE LAB.`, dan `EM LAB` dilewati karena ruang tersebut tidak ada di master. Impor masih menolak ruang lain yang belum terpetakan dan konflik pemakaian. Kode lama duplikat tanpa label dan tanpa pemakaian mendatang dapat tetap disimpan. Sebanyak 154 tes terkait lulus dengan 765 asersi.

Urutan di VPS setelah deploy adalah migrasi biasa, impor `DataRuangan.xlsx`, lalu sinkronisasi JSON Jadwal Ruangan iGracias. Kolom baru berawal kosong. Lakukan impor master sebelum mencari ruangan. [Panduan penyiapan dan peminjaman](../panduan-pemakaian/peminjaman-dan-sumber-jadwal-ruangan.md)
