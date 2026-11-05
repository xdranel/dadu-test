# 16 — Rescan D2 sebelum wizard

Pengguna melaporkan semua test D2 lulus. Source pada app/routes/resources/database/tests/config/bootstrap di kedua folder cocok. Test upload dan revisi yang sebelumnya tertinggal sekarang sudah ada di dadu-test. Asisten tidak menjalankan ulang suite aplikasi.

Tidak ditemukan blocker pada permission create/update dinamis, pemeliharaan file final saat save/discard revisi, pemeriksaan source_version di bawah lock, atau hidden registration_version dan kenaikan lock_version pada keputusan petugas. Fixture final sudah memakai fresh() untuk membaca default DB.

## Kebutuhan UI yang tidak boleh terlewat

Draft revisi stale memang mendapat 409 ketika dibuka. Halaman index harus menampilkan daftar draft terfilter owner dan NIM, termasuk version dan tombol hapus, tanpa memanggil owned()/check() yang menolak draft stale. Discard tetap memeriksa owner/izin/versi dan hanya menghapus file milik draft. Dengan begitu pengguna dapat membuang draft stale lalu memulai revisi baru.

Wizard menggunakan satu form untuk **tahap aktif**, bukan satu form seluruh file. Response JSON C2/D dipertahankan agar test backend tetap berlaku; browser HTML mendapat view/redirect. Pilihan file baru belum tersimpan sampai Simpan berhasil; kembali ke tahap lama membaca metadata/file yang sudah tersimpan.

Lanjutkan [17 — wizard dan tinjau](17-checkpoint-e-wizard-student.md). Final submit draft belum dipasang sampai F; jangan menghubungkan halaman tinjau ke store/update lama yang menerima file mentah. Semua kode ditulis pengguna di folder asli dan semua tulisan asisten hanya docs/*.md.
