# 96 — Kelola Periode Sidang TA: fungsi, pemakaian, dan pengaruhnya

Panduan ini menjelaskan Kelola Periode Sidang TA dengan pertanyaan/kasus seperti [panduan periode akademik dan KRS pada 95](95-panduan-periode-akademik-dan-memilih-format-krs.md). Ini dokumentasi perilaku kode saat ini; tidak menambahkan fitur atau mengubah jadwal/database aplikasi. Seluruh tanggal pada contoh hanya ilustrasi.

## 1. Apa fungsi periode Sidang TA? Apakah tersimpan di database?

**Periode Sidang TA adalah record jadwal satu gelombang pendaftaran, berikut waktu verifikasi/revisi berkasnya.** Data tersimpan pada tabel `sidang_ta_periods`, bukan sekadar kartu informasi.

Data yang disimpan mencakup nama, keterangan, waktu buka/tutup pendaftaran, waktu mulai/selesai verifikasi, dan tiga checkbox kontrol. Menambahkan record, menyimpan perubahan, serta mengubah checkbox semuanya menulis perubahan ke database.

Pengajuan dan draft terhubung ke periode melalui `sidang_ta_period_id`. Jadi mahasiswa yang memilih periode A tetap mempunyai pengajuan milik periode A, walaupun admin kemudian membuat periode B.

Tampilan kartu jadwal yang hilang dari halaman mahasiswa bukan berarti record periode atau pengajuannya dihapus.

## 2. Apa hubungannya dengan Pengaturan Periode Akademik KRS?

Keduanya memiliki tugas berbeda:

| Bagian | Periode akademik KRS | Periode Sidang TA |
|---|---|---|
| Mewakili | Tahun akademik + Ganjil/Genap | Gelombang pendaftaran dan verifikasi/revisi |
| Contoh | Ganjil 2026/2027 | Sidang TA Gelombang November 2026 |
| Kegunaan | Menentukan KRS yang dianggap aktif dan acuan pemeriksaan KRS TA2 | Membatasi kapan mahasiswa dapat mendaftar atau mengerjakan revisi |
| Jumlah yang boleh aktif | Satu periode akademik | Beberapa periode TA boleh aktif bersamaan |
| Apa yang dipilih mahasiswa | Tidak memilih periode akademik global melalui form TA | Memilih periode TA yang pendaftarannya aktif dan sedang terbuka |
| Apakah mengikuti impor? | KRS hasil impor ditautkan ke periode akademik | Periode dipilih pada pengajuan/draft; bukan hasil impor JSON KRS |

Contoh: selama Ganjil 2026/2027 berjalan, kampus bisa membuka gelombang Sidang TA November dan Desember. Keduanya adalah record periode TA berbeda, sementara periode akademik KRS tetap Ganjil 2026/2027.

Tidak ada hubungan yang otomatis menebak periode akademik berdasarkan nama atau bulan periode TA. Mengaktifkan gelombang TA Desember tidak otomatis mengganti periode akademik menjadi Genap.

## 3. Kalau hanya memakai persyaratan, mengapa masih perlu periode TA?

Untuk mengirim pendaftaran, aplikasi memeriksa dua bagian:

1. **Kesempatan mendaftar/revisi:** periode yang dipilih, status aktif, waktu saat ini, status pengajuan, dan izin mahasiswa.
2. **Kelengkapan dan kelayakan:** persyaratan akademik terkini, EPRT, data form, dokumen yang diwajibkan, dan pernyataan pengiriman.

Periode terbuka tidak membuat syarat otomatis lulus. Sebaliknya, seluruh syarat sudah memenuhi juga tidak mengizinkan pengiriman pendaftaran baru setelah waktunya tutup.

KRS TA2 berasal dari data KRS yang relevan dengan periode akademik aktif. IPK, SKS lulus, TAK, SKPI, EPRT dan data akademik lainnya berasal dari sumber/import/koreksi masing-masing. Periode TA mengatur waktu layanan pendaftarannya, bukan mengisi nilai-nilai tersebut.

## 4. Lebih baik satu kartu atau banyak? Boleh dipakai ulang?

**Buat satu record untuk setiap gelombang/periode pendaftaran yang berbeda.** Record lama dipertahankan untuk riwayat; kartu admin akan bertambah mengikuti gelombang yang pernah dibuat.

| Keperluan | Tindakan |
|---|---|
| Baru membuka satu gelombang | Satu record periode cukup |
| Menyiapkan gelombang berikutnya | Tambahkan record baru; atur tanggal dan checkbox sesuai kebutuhan |
| Memperpanjang gelombang yang sama | Edit jadwal pada record gelombang tersebut; pahami pengaruhnya pada pengajuan yang sudah terhubung |
| Membetulkan salah ketik nama/keterangan gelombang yang sama | Edit record yang sama |
| Berpindah dari gelombang November ke gelombang Desember | Buat record baru untuk Desember; jangan mengganti identitas record November |
| Menambah jendela revisi untuk peserta gelombang November | Atur waktu verifikasi/revisi pada record November, bukan membuat periode baru khusus revisinya |

Prinsip menjaga riwayat sama dengan periode akademik, tetapi **pengaman editnya tidak sama**. Kelola Periode TA saat ini mengizinkan perubahan nama/jadwal walaupun sudah ada pengajuan. Tidak ada penguncian identitas tahun/term seperti pada periode akademik.

Artinya, jika kartu November diganti nama dan seluruh tanggalnya menjadi Desember, pengajuan lama tetap mengacu pada ID yang sama dan kini membaca informasi periode yang telah diganti itu. Itulah alasan membuat record baru untuk gelombang berbeda, bukan mendaur ulang record lama.

## 5. Dua jadwal dalam satu periode

### Pendaftaran Sidang TA

- **Dibuka:** waktu mulai pendaftaran baru.
- **Tenggat:** waktu terakhir pendaftaran baru.
- Untuk dapat mendaftar, checkbox Pendaftaran aktif harus dicentang dan waktu saat ini berada dalam rentang tersebut.

Pengecekan dilakukan di server juga, termasuk saat menyimpan/mengirim draft. Membiarkan halaman terbuka sebelum tenggat tidak membolehkan mengirim setelah tenggat.

### Tanggal Verifikasi Berkas

- **Mulai verifikasi:** waktu mahasiswa mulai dapat mengerjakan revisi jika pengajuannya memang diminta revisi.
- **Selesai verifikasi:** batas akhir revisi.
- Revisi membutuhkan checkbox Revisi aktif, jadwal yang sedang terbuka, serta pengajuan yang statusnya memang meminta revisi.

Contoh satu periode:

| Jadwal | Waktu ilustrasi |
|---|---|
| Pendaftaran dibuka | 9 November 2026, 08:00 |
| Tenggat pendaftaran | 11 November 2026, 23:59 |
| Mulai verifikasi | 12 November 2026, 08:00 |
| Selesai verifikasi | 20 November 2026, 23:59 |

Mulai verifikasi juga boleh dibuat pada 9 November jika revisi ingin dapat dikerjakan selama pendaftaran masih terbuka. Form tidak mengharuskan menunggu pendaftaran selesai. Waktu selesai verifikasi harus setelah waktu mulainya dan tidak boleh lebih awal daripada tenggat pendaftaran.

Gunakan tanggal/jam sesuai kalender layanan dan zona waktu aplikasi. Menyimpan waktu buka/tutup belum berarti layanan pasti terbuka: checkbox masing-masing juga menentukan izin.

## 6. Arti tiga checkbox

| Checkbox | Yang diatur | Yang tidak otomatis dilakukan |
|---|---|---|
| Pendaftaran aktif | Mengizinkan pendaftaran pada periode itu jika waktunya juga sudah terbuka | Tidak otomatis mengaktifkan revisi atau menampilkan kartu jadwal |
| Revisi aktif | Mengizinkan pengerjaan revisi sesuai waktu verifikasi bagi pengajuan yang diminta revisi | Tidak membuka pendaftaran baru dan tidak otomatis membuat semua mahasiswa perlu revisi |
| Tampilkan jadwal ke mahasiswa | Menampilkan kartu jadwal pada halaman Pendaftaran Sidang TA selama jadwal belum seluruhnya berakhir | Tidak memberi izin mendaftar/revisi dan tidak menyembunyikan riwayat pengajuan |

Contoh pemakaian ketika waktunya memenuhi rentang masing-masing:

| Pendaftaran | Revisi | Tampilkan | Hasil |
|---|---|---|---|
| Ya | Ya | Ya | Kartu terlihat; pendaftaran/revisi dapat dipakai sesuai keadaan mahasiswa dan jadwal |
| Ya | Ya | Tidak | Kartu jadwal tidak terlihat; izin daftar/revisi tetap berlaku |
| Tidak | Ya | Ya | Tidak menerima pendaftaran baru; revisi pengajuan asal tetap dapat berjalan; kartu terlihat |
| Tidak | Tidak | Ya | Kartu dapat menjadi informasi jadwal, tetapi pendaftaran/revisi dinonaktifkan |
| Tidak | Tidak | Tidak | Tidak menampilkan kartu dan tidak mengizinkan pendaftaran/revisi |

Checkbox Tampilkan **hanya mengatur kartu**, bukan menghapus periode dari pilihan pendaftaran. Pilihan periode pendaftaran mengikuti Pendaftaran aktif + tanggal buka/tutup. Jadi jangan memakai Tampilkan untuk menutup pendaftaran.

Periode baru boleh disiapkan dengan checkbox tidak aktif. Form juga mengizinkan langsung mencentangnya ketika membuat periode; tidak seperti periode akademik yang selalu dibuat belum aktif. Jika Pendaftaran aktif dicentang tetapi waktu buka masih di masa depan, mahasiswa tetap harus menunggu waktu buka.

## 7. Apakah mengaktifkan periode B otomatis menonaktifkan periode A?

**Tidak pada Sidang TA.** Beberapa periode boleh aktif bersamaan. Mengaktifkan B tidak otomatis melepas checkbox aktif A.

Jika A dan B sama-sama aktif dan sama-sama berada dalam rentang pendaftaran, keduanya bisa menjadi pilihan mahasiswa. Jika hanya ingin menerima gelombang B, pastikan pendaftaran A sudah lewat tenggat atau nonaktifkan Pendaftaran aktif pada A.

Hal ini berbeda dari tombol aktivasi periode akademik KRS yang memilih tepat satu periode aktif dan menonaktifkan yang sebelumnya.

Mahasiswa juga tetap dibatasi status pengajuannya. Membuat beberapa periode terbuka tidak membolehkan mahasiswa yang masih mempunyai pengajuan submitted/menunggu proses atau diminta revisi membuat pengajuan aktif lain begitu saja.

## 8. Kasus pendaftaran tutup tetapi revisi masih terbuka

Dengan contoh jadwal bagian 5, pada 15 November:

- Mahasiswa yang belum pernah mengirim pendaftaran tidak dapat mendaftar baru ke periode tersebut.
- Draft pendaftaran awal yang belum dikirim sudah melewati tenggatnya dan menjadi sasaran cleanup.
- Mahasiswa yang sudah mengirim dan diminta revisi dapat mengerjakan revisi jika Revisi aktif dicentang dan tenggat verifikasi belum lewat.
- Revisi mengikuti periode pengajuan asal. Mahasiswa tidak mengganti periodenya ke gelombang baru hanya untuk memperpanjang revisi.

Pendaftaran aktif boleh dinonaktifkan setelah pendaftaran ditutup, sementara Revisi aktif tetap dicentang. Revisi memang tidak bergantung pada checkbox Pendaftaran aktif.

Jika jadwal verifikasi baru dimulai 12 November, mahasiswa yang sudah diminta revisi pada 10 November harus menunggu. Jika ingin revisi dapat dikerjakan sejak 9 November, admin menetapkan waktu mulai verifikasi yang sesuai pada periode tersebut.

## 9. Kapan draft dan kartu mahasiswa hilang?

Ada tiga hal berbeda:

| Kejadian | Perilaku |
|---|---|
| Pendaftaran melewati tenggat | Penyimpanan/pengiriman draft awal ditolak; draft awal aktif menjadi sasaran cleanup |
| Verifikasi melewati tenggat | Penyimpanan/pengiriman revisi ditolak; draft revisi aktif menjadi sasaran cleanup |
| Kedua jadwal sudah berakhir | Kartu jadwal mahasiswa otomatis tidak ditampilkan walaupun Tampilkan masih dicentang |

Cleanup dijadwalkan setiap menit dan membutuhkan scheduler yang berjalan. Jadi penghapusan record/file draft bukan dijanjikan persis pada detik tenggat. Pembatasan simpan/kirim tetap diperiksa oleh server meskipun cleanup belum sempat berjalan.

Menonaktifkan checkbox saja tidak langsung menghapus draft: cleanup memakai waktu tenggat, bukan semata-mata status aktif. Menyembunyikan kartu juga tidak menghapus draft, pengajuan, atau riwayat.

Cleanup hanya menyasar draft berstatus aktif yang sesuai kondisi kedaluwarsa. Berkas final yang sudah dipakai pengajuan tidak dihapus hanya karena berasal dari draft. Berkas unggahan baru milik draft yang dibersihkan dapat ikut dihapus jika tidak dirujuk record lain. Jika storage gagal menghapus file, metadata draft dapat sudah terhapus sementara file orphan tertinggal dan tercatat pada log.

## 10. Apa yang terjadi pada pengajuan yang sudah dikirim?

Pengajuan bukan draft awal. Berakhirnya waktu pendaftaran tidak menghapus pengajuan yang sudah dikirim atau langsung menolak semua peserta.

Ada proses otomatis khusus untuk **pengajuan versi persyaratan 2 yang masih berstatus diminta revisi (`needs_review`) setelah tenggat verifikasi**: statusnya menjadi Ditolak, disertai alasan dan riwayat otomatis. Proses ini dijadwalkan setiap menit; dokumen/snapshot pengajuan tetap disimpan.

Pengajuan yang sudah dikirim ulang dari revisi dan sedang menunggu review, atau yang sudah disetujui, tidak ditolak oleh job khusus ini hanya karena tenggat verifikasi lewat. Kartu jadwal yang hilang juga tidak berarti riwayat pengajuan hilang.

## 11. Jika tanggal/nama periode lama diubah, apa pengaruhnya?

| Perubahan | Pengaruh |
|---|---|
| Mengubah nama/keterangan | Halaman yang membaca relasi periode bisa menampilkan informasi baru, termasuk pada pengajuan lama |
| Memajukan waktu buka | Dapat membuat pendaftaran/revisi tersedia lebih awal jika checkbox dan syarat lain memenuhi |
| Memundurkan waktu buka ke masa depan | Dapat menghentikan sementara akses tulis sampai waktu baru tercapai |
| Memperpanjang tenggat pendaftaran | Mengubah batas pendaftaran/draft awal pada record itu; tidak memperpanjang tenggat revisi kecuali jadwal verifikasi juga disesuaikan |
| Memperpanjang tenggat verifikasi | Mengubah batas revisi pengajuan yang terhubung ke periode tersebut |
| Memajukan tenggat sampai sudah lewat waktu sekarang | Dapat segera memblokir akses dan membuat draft/pengajuan revisi memenuhi kondisi proses kedaluwarsa berikutnya |
| Mematikan Tampilkan | Menyembunyikan kartu jadwal, bukan menutup pendaftaran/revisi |
| Mengaktifkan periode TA lain | Tidak memindahkan pengajuan lama dan tidak otomatis menonaktifkan periode sebelumnya |

Pemeriksaan akses membaca ulang periode terkait; mahasiswa tidak memperoleh pengecualian hanya karena membuka form sebelum admin mengubah tanggal.

**Memperpanjang setelah proses kedaluwarsa sudah berjalan tidak memulihkan data otomatis.** Draft yang sudah dihapus tidak dibuat kembali; pengajuan yang sudah ditolak otomatis tidak berubah kembali menjadi diminta revisi. Karena itu, jika kampus memperpanjang gelombang yang sama, perubahan tanggal perlu dilakukan dengan memahami keadaan pengajuan yang sudah diproses.

Mengedit periode tidak menghitung ulang IPK/EPRT/TAK, mengubah JSON sumber, atau memindahkan KRS. Pengaruh langsungnya berada pada jadwal, tampilan, dan akses/proses pengajuan yang memakai periode itu.

## 12. Bagaimana dengan periode lama yang belum memiliki tanggal verifikasi?

Form admin saat ini mewajibkan tanggal mulai dan selesai verifikasi saat membuat/menyimpan periode.

Namun, record lama mungkin belum mempunyai kedua tanggal itu. Kode masih mempunyai kompatibilitas untuk revisi tanpa batas tanggal verifikasi jika Revisi aktif dan jadwal belum lengkap. **Jangan menganggap tanggal verifikasi kosong berarti revisi pasti ditutup.** Lengkapi jadwal pada record periode yang benar agar tenggatnya eksplisit; periode lama tanpa jadwal lengkap juga tidak mempunyai dasar tanggal untuk expiry revisi yang sama seperti periode baru.

Untuk kartu mahasiswa, jadwal verifikasi tidak lengkap tidak dipakai untuk mempertahankan kartu selamanya setelah pendaftaran selesai.

## 13. Alur penggunaan yang disarankan

1. Perbarui data akademik mahasiswa melalui sinkronisasi/koreksi sesuai sumber. Pastikan periode akademik KRS yang dipakai pemeriksaan TA2 benar.
2. Di Kelola Periode Sidang TA, buat record gelombang baru dengan nama yang jelas, misalnya Sidang TA November 2026 Gelombang 1.
3. Isi jadwal pendaftaran dan verifikasi/revisi berdasarkan ketentuan layanan. Gunakan record yang sama untuk dua jadwal gelombang tersebut.
4. Atur tiga checkbox sesuai tujuan. Untuk menyiapkan lebih dahulu, periode boleh belum diaktifkan; untuk mengumumkan jadwal, gunakan Tampilkan.
5. Saat pendaftaran tersedia, mahasiswa memilih periode yang terbuka, mengisi data, melengkapi dokumen, meninjau, dan mengirim. Kelayakan tetap diperiksa.
6. Admin memeriksa berkas dan meminta revisi jika perlu. Mahasiswa memperbaiki sesuai pengajuan asal dan jendela verifikasi.
7. Sesudah pendaftaran tutup, pertahankan kesempatan revisi sampai tenggat yang disepakati. Pastikan scheduler bekerja untuk proses kedaluwarsa/cleanup.
8. Untuk gelombang berikutnya, buat record baru. Gelombang lama tetap menjadi riwayat. Pastikan tidak ada periode lain yang tanpa sengaja masih menerima pendaftaran jika itu tidak diinginkan.

## 14. Boleh menghapus periode lama?

Endpoint penghapusan menolak periode yang masih mempunyai pengajuan atau draft. Jangan menghapus periode bersejarah untuk merapikan daftar; record tersebut menjelaskan pengajuan berasal dari gelombang mana.

Kartu mahasiswa dapat berhenti tampil karena checkbox/tanggal tanpa perlu menghapus record. Tidak diperlukan migrate:fresh atau seed ulang untuk pergantian gelombang.

## 15. Rujukan dan batas pekerjaan dokumentasi

Panduan diperiksa terhadap model `SidangTaPeriod`, pengelolaan periode pada `SidangTaRegistrationController`, `SidangTaDraftService`, `SidangTaDraftAccess`, `SidangTaDraftFinalizer`, `SidangTaRevisionDeadline`, `SidangTaExpiredDraftCleanup`, `SidangTaRevisionExpiry`, jadwal pada `routes/console.php`, serta tes periode/jadwal yang sudah tersedia. Tidak menjalankan job cleanup/expiry, mengubah periode aplikasi, atau menambahkan unit test hanya untuk perubahan dokumentasi.

Pembaruan kebutuhan Form Yudisium tetap dicatat pada dokumen 91. Contoh JSON Kehadiran masih ditunggu; periode Sidang TA tidak menggantikan data Kehadiran yang belum tersedia.
