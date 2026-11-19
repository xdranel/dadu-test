# Keputusan dan konfirmasi yang masih diperlukan

## Pelaksanaan Sidang TA

| Pertanyaan | Keputusan dan contoh pemakaian |
| --- | --- |
| Jika Excel sudah memuat ruang, apakah impor langsung memesan? | Tidak. Sesuai penjelasan terakhir pengguna, admin tetap menetapkan lewat tombol. Contoh Excel 23 November 08.00–09.30 ruang K-01: setelah impor belum ada booking. Pencarian memilih K-01/jam tersebut sebagai rekomendasi jika tersedia; admin dapat memilih K-02 pada 10.00–11.30 lalu menekan Tetapkan Jadwal. |
| Jika kolom ruang kosong? | Tanggal/jam menjadi preferensi; admin mencari ruang dan memeriksa alternatif. Excel bukan bukti bahwa ruangan tersedia. |
| Jika tidak memakai Excel? | Buka peserta approved dari sistem, pilih tanggal dan cari ketersediaan. Pengaturan tidak dipaksa mengunggah Excel karena ada jalur sistem. |
| Apakah perlu persetujuan kedua dari petugas ruangan? | Tidak setelah Tetapkan Jadwal berhasil: booking langsung approved. Untuk peminjaman dosen lama yang masih pending, izin review ruangan tetap diperlukan sebelum disahkan. Peminjaman lama yang sudah ditolak/dibatalkan tetap tersimpan; penetapan berikutnya membuat booking pengganti. |
| Dari mana nama periode dan semester sumber? | Nama mengikuti Kelola Periode Sidang TA, misalnya Ganjil 2026/2027 Periode I. Semester sumber berasal dari Pengaturan Periode Akademik yang dipakai JSON jadwal ruangan `room_schedule`. Periode TA berikutnya mempunyai pengaturan sendiri; periode lama tidak dipakai ulang sebagai identitas periode baru. |
| Apakah mahasiswa/dosen mengatur jadwal? | Mahasiswa hanya membaca hasil; perubahan melalui admin/petugas SSC. Dosen dengan aksi khusus hanya membaca pengajuan prodi yang sesuai. Peminjaman ruangan biasa tetap mengikuti modul tersendiri. |
| Apakah MK TA mengulang membuktikan gagal sidang sebelumnya? | Belum. Riwayat resmi perlu data tanggal, periode dan keputusan yang terkonfirmasi. Status Lulus/Gagal pada fitur ini ditetapkan manual oleh admin dan bisa dibatalkan kembali. |

Hal yang masih perlu dikonfirmasi divisi: sumber riwayat sidang sebelum DADU dipakai (termasuk tanggal/periode dan bukti hasil), aturan hubungan keputusan pelaksanaan dengan nilai akademik resmi, dan apakah pemeriksaan bentrok pembimbing/penguji akan ditambahkan pada tahap berikutnya. Untuk tahap ini jawaban divisi tetap pemeriksaan ruang kosong saja. **Contoh JSON kehadiran masih belum tersedia dan tetap ditunggu**; Excel plotting bukan penggantinya.

## Hak akses Pelaksana

Aksi khusus Lihat Pengajuan (Prodi Dosen) mencakup Review Sidang TA dan Pelaksanaan Sidang TA. Cocokkan Email Domain Data Dosen dengan email akun; scope memakai prodi dosen saat permintaan dilakukan. Akun pegawai tanpa record dosen yang cocok tidak otomatis memperoleh akses prodi. Jangan berikan Review jika tujuannya hanya membaca.

## Yudisium

Pertanyaan divisi dan aturan yang belum final tetap disimpan pada [catatan91](../arsip-progres/91-catatan-kebutuhan-form-yudisium.md). Hasil pengerjaan101 bersifat catatan branch pada saat itu; perubahan Yudisium pernah disimpan melalui stash oleh pengguna. Penataan dokumentasi126 tidak mengaktifkan, menerapkan stash, atau mengubah fitur Yudisium.

## Data yang masih ditunggu

**JSON kehadiran belum tersedia.** Riwayat gagal sidang sebelum DADU membutuhkan sumber tanggal/periode/keputusan yang terkonfirmasi; MK TA mengulang saja tidak dijadikan bukti gagal otomatis.
