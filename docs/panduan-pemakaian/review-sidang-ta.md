# Review Sidang TA — petugas dan dosen pembaca

## Admin/petugas

1. Buka Form → Review Sidang TA. Gunakan filter nama/NIM, periode, status dan prodi, lalu Terapkan; Reset menghapus filter. Export Excel berada sebaris dengan kedua tombol tersebut.
2. Tekan Tinjau pada pengajuan. Status Akademik memuat BPP dan persetujuan sidang iGracias; judul TA dilengkapi pembimbing1/2. Judul/pembimbing kosong pada snapshot lama dapat memakai fallback data akademik terbaru untuk tampilan.
3. Buka berkas dan beri keputusan sesuai kebutuhan. Catatan revisi tetap terlihat mahasiswa. Penggantian berkas pada revisi mengikuti permintaan dan tidak mengulang semua verifikasi.
4. Setelah pemeriksaan lengkap, pilih Setujui, Minta Revisi atau Tolak sesuai kondisi. Jika pengajuan sudah disetujui, Batalkan Persetujuan dapat digunakan dalam jadwal Verifikasi Berkas yang masih terbuka; status berkas dan catatan tetap dipertahankan.
5. Untuk penolakan manual/otomatis yang hendak dibuka kembali, perpanjang/aktifkan jadwal Verifikasi Berkas terlebih dahulu melalui Kelola Periode, lalu buka kembali sesuai tombol yang tersedia. Mahasiswa tidak diberi revisi tanpa batas waktu.
6. Export Excel mengikuti filter. Kolom pembimbing1, pembimbing2, KK pembimbing1 sesudah judul TA; kolom unggahan berisi tautan ke berkas yang tetap memerlukan hak akses aplikasi.
7. Penjadwalan ruangan dan hasil Lulus/Gagal dikelola melalui menu terpisah Pelaksanaan Sidang TA.

## Pelaksana/dosen

Aksi khusus **Lihat Pengajuan (Prodi Dosen)** (`sidang_ta_registration_view_program`) memberi akses baca ke **Review Sidang TA dan Pelaksanaan Sidang TA**. Akun harus cocok dengan satu record Data Dosen berdasarkan Email Domain dan mempunyai prodi. Pembaca hanya melihat prodi tersebut; dropdown prodi disembunyikan.

Dosen pembaca dapat membuka detail, berkas dan catatan yang diizinkan. Tidak dapat menyetujui, menolak, meminta revisi, mengubah catatan, mengimpor, mengekspor atau menetapkan jadwal/hasil. Permission **Review** berbeda: permission itu membuka kewenangan petugas dan tidak boleh diberikan jika tujuan akun hanya membaca prodi.

Sumber dan batasan: [akses105](../arsip-progres/105-akses-baca-sidang-ta-prodi-dosen.md), [hasil125](../arsip-progres/125-hasil-pelaksanaan-sidang-ta-admin.md), [pelaksanaan](pelaksanaan-sidang-ta.md).
