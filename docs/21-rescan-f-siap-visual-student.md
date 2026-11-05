# 21 — Rescan finalisasi dan kesiapan visual student

Tanggal: 2 November 2026.

Pengguna mengonfirmasi seluruh test, verifikasi browser/konkurensi, dan uji periode ditutup setelah tinjau berhasil. Source kemudian disalin ke dadu-test. Hasil rescan statis tidak menemukan blocker fungsional baru pada alur student yang diperiksa. Tahap selanjutnya adalah visual/desain dan perapian detail Riwayat Pengajuan, mengikuti daftar pengguna.

## Ruang lingkup dan bukti

- Membaca implementasi aktual di dadu-test dan folder asli; membandingkan source, route, model, view, serta test Sidang TA. Review independen atas 38 berkas Sidang TA dan `routes/web.php` menemukan isinya identik dengan folder asli.
- Memeriksa jalur draft baru/revisi, empat tahap, tinjau, submit final, retry, stale version, kepemilikan, permission, dan pengelolaan file.
- Sebanyak 25 berkas PHP Sidang TA pada area aplikasi dan test lolos `php -l`. Pemeriksaan ini hanya membuktikan sintaks PHP.
- Terdapat 73 definisi test Sidang TA: schema 9, periode 9, draft 10, upload 8, revisi 10, wizard 14, finalisasi 13. Kelulusan suite dan pengujian runtime berdasarkan laporan pengguna; asisten tidak menjalankan ulang suite, aplikasi, database, atau build.
- Perbedaan `phpunit.xml` masih sesuai keputusan sebelumnya: folder asli sementara memakai MySQL Podman, workspace memakai konfigurasi awal. Ini bukan source yang terlewat disalin. Pengguna memulihkannya sebelum commit/push.

## Hasil pemeriksaan alur

| Area | Hasil rescan |
| --- | --- |
| Submit final | Request menerima referensi draft/version/action; payload file langsung dan field form lama ditolak. Store/update memakai finalizer bersama. |
| Tinjau dan versi | Finalizer memeriksa versi draft dan versi tinjau; perubahan dari tab lain tidak boleh menimpa draft terbaru. |
| Periode baru | Keterbukaan periode diperiksa kembali saat finalisasi. Penutupan sesudah tinjau tidak mengonsumsi draft atau menghapus file ketika submit ditolak. Uji manual ini sudah dikonfirmasi pengguna. |
| Revisi | Memakai pengajuan sumber milik sendiri, periode asal, serta versi sumber. Perubahan keputusan petugas membuat revisi lama ditolak. |
| Retry | Header draft consumed dipertahankan; replay tidak membuat pengajuan/log submit baru atau mengubah keputusan final terbaru. |
| File | Final memakai path privat yang sudah tersimpan. Pelepasan metadata draft dan penggantian dokumen berada dalam transaksi; penghapusan file lama dilakukan sesudah commit dan pemeriksaan referensi. |
| Otorisasi | Endpoint tetap memeriksa pemilik dan permission; tidak ditemukan jalur submit legacy aktif yang melewati draft/tinjau. |

Hasil ini tidak menjamin seluruh kemungkinan bug telah teruji; batasnya adalah source yang dibaca, pemeriksaan sintaks, serta hasil pengujian yang dilaporkan pengguna.

## Catatan untuk tahap visual/UX berikutnya

1. `resources/views/sidang_ta_registrations/show.blade.php`: tombol **Perbaiki Pengajuan** masih didasarkan pada status `needs_review`. Saat merapikan detail, sesuaikan visibilitasnya dengan pemilik dan permission update agar pembaca/reviewer tidak diberi tautan yang berakhir 403. Backend sudah menolak akses yang tidak berhak; ini catatan UI, bukan bypass otorisasi.
2. `resources/views/sidang_ta_registrations/form.blade.php` dan `partials/upload.blade.php` merupakan sisa form lama. Partial hanya dipanggil form lama dan tidak ditemukan pemanggil aktif dari controller/route untuk form tersebut. Tentukan penghapusannya saat perapian tampilan agar tidak membingungkan pemeliharaan; tidak perlu mengaktifkan kembali jalur lama.
3. Detail snapshot/riwayat masih perlu label yang mudah dibaca dan penataan informasi. Ini memang ditunda sebelumnya, bukan bagian yang hilang dari finalisasi F.

Tidak ada perubahan kode aplikasi atau test baru yang diminta oleh dokumen rescan ini. Karena itu tidak ada command generator maupun blok test pengganti. Panduan implementasi visual berikutnya harus kembali menyertakan command pembuatan file dan kode test lengkap bila perilaku yang diuji berubah.

## Langkah berikutnya

Checkpoint F sudah dilaporkan lulus dan selesai direscan. Tunggu daftar visual/desain dari pengguna sebelum menulis panduan berikutnya. Cakupan berikutnya tetap student: wizard, tinjau, riwayat/detail dan konsistensi tombol/pesan sesuai daftar tersebut. Workflow petugas penuh menunggu penjelasan terpisah. Jangan mengubah kebijakan periode, aturan revisi, atau kontrak finalisasi demi tampilan.

Catatan pendamping diperbarui di [07](07-catatan-pendamping-sidang-ta.md); implementasi F dan checklist asal ada di [20](20-checkpoint-f-finalisasi-draft.md).
