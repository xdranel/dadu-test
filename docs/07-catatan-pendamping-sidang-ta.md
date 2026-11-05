# 07 — Catatan pendamping untuk sesi Sidang TA berikutnya

**Pembaruan keputusan testing:** pengguna mengonfirmasi checkpoint B lulus dan memilih MySQL Podman, tanpa SQLite. Ikuti [09](09-checkpoint-c1-struktur-draft-mysql.md) untuk konfigurasi MySQL dan kode lengkap C1. Instruksi SQLite pada catatan/scan awal di bawah bersifat historis dan tidak dipakai lagi. Contoh memakai schema dadu_testing di server Podman yang sama karena RefreshDatabase dapat mereset tabel tujuan. C dibagi menjadi C1 struktur data dan C2 service/endpoint tahap 1 agar setiap bagian punya test yang dapat dijalankan.

Dokumen kerja asisten, bukan spesifikasi baru. Terakhir diperbarui: 1 November 2026. Baca bersama [03](03-peta-fitur-sidang-ta-student.md), [04](04-perbaikan-upload-sidang-ta.md), [05](05-langkah-form-empat-tahap-sidang-ta.md), [06](06-checklist-rescan-sidang-ta.md).

## Instruksi pengguna yang terus berlaku

1. Folder development asli `/home/xdranel/Code/Project/php-laravel/dadu/` boleh dibaca seluruhnya tetapi jangan ditulis oleh asisten.
2. Pada workspace `/home/xdranel/Code/Project/secret/dadu-test/`, asisten hanya menulis `docs/*.md`. Tidak mengubah source, dependencies, `.env`, konfigurasi PHP atau database. Jangan menjalankan build/test yang menulis cache/artifact tanpa perubahan izin pengguna; pada sesi ini hanya read-only scan dan dokumentasi.
3. Pengguna mengerjakan kode mengikuti panduan, lalu memindahkannya ke dadu-test. Setiap checkpoint perlu scan/rescan versi aktual untuk mencari salah/terlewat. Jangan menyatakan sudah benar hanya karena panduan ditulis.
4. Fokus student dahulu. Petugas dan role lainnya disesuaikan kemudian melalui permission superadmin. Penyesuaian minimum periode jamak dan guard versi review diperlukan untuk kompatibilitas student; jangan memperluas menjadi desain workflow petugas penuh.
5. Akun uji: `krisna@mahasiswa.test` / `password`. Role kode bernama `mahasiswa` dan `Petugas`, label UI Mahasiswa. Role aktif dapat diganti pada sidebar di atas Logout. Default prioritas user multirole dapat membuka Petugas lebih dahulu.

## Keputusan dari pengguna

| Pertanyaan | Jawaban yang sudah diberikan |
| --- | --- |
| Progres cukup selama halaman terbuka? | **Harus bisa dilanjutkan setelah ditutup.** Draft server dan file tersimpan wajib. |
| Periode tunggal aktif atau dipilih? | **Mahasiswa memilih periode terbuka.** Jangan kembali ke rancangan satu periode aktif. |
| Siapa mengerjakan kode? | Pengguna; asisten menyediakan langkah dan review di Markdown. |
| Urutan fitur | Perbaiki upload dahulu; Mulai Pendaftaran empat tahap + tinjau/kirim; detail Riwayat Pengajuan sesudahnya. |

Pernyataan awal asisten tentang wizard satu halaman tanpa draft telah dibatalkan oleh jawaban pengguna. Dokumen 03 dan 05 sudah disesuaikan. Dokumen 04 masih membahas upload form **lama** untuk membuktikan perbaikan konfigurasi sebelum perubahan draft; jangan menganggapnya kontradiksi dengan final draft yang mengirim file per tahap.

## Keputusan teknis usulan asisten

- Dua tabel draft terpisah menjaga skema/status final dan daftar petugas.
- Save eksplisit per tahap, native multipart POST+redirect; Alpine untuk interaksi. Belum menjanjikan autosave perubahan yang belum disimpan.
- Final store/update menerima draft_id/version/action; tidak lagi mengambil seluruh uploaded files dari client. Satu pipeline finalisasi untuk baru/revisi.
- Header draft consumed dipertahankan untuk replay idempotent; metadata draft-document consumed dibuang dalam transaksi setelah dipetakan ke final, agar FK source_document_id tidak memblokir revisi berikutnya. Jangan menghapus file final saat membersihkan draft.
- Draft version menangani dua tab; lock_version final dan source_version draft menangani perubahan reviewer/revisi. Review petugas perlu hidden registration_version + row lock untuk mencegah halaman lama menimpa revisi baru.
- Satu active draft baru per NIM dan satu active registration submitted/needs_review lintas periode. Lock row Student menserialisasi inisialisasi/final; SQLite tidak cukup untuk membuktikan lock MySQL.
- Init draft **baru** dengan existing pengajuan aktif ditolak/diarahkan ke pengajuan itu. Init **revisi** needs_review milik sendiri boleh meskipun source itu dihitung pengajuan aktif; jangan menggunakan guard baru yang memblokir revisi sendiri.
- Beberapa is_active=true periode diperbolehkan, opsi student tetap dibatasi open_at/close_at. Tidak membuat semester baru atau batas maksimum empat periode tanpa kebijakan lanjut.
- Revisi mempertahankan periode asal dan boleh diproses setelah tutup, sesuai kode lama. Aturan tahap 1 revisi harus bercabang eksplisit dari pengajuan baru.
- Upload baru tahap 3 PDF-only sesuai gambar; existing file sah lama tetap dihormati. Ambang IPK/SKS/EPrT masih informasi/verifikasi dokumen, bukan blok otomatis baru.

## Temuan source awal yang harus dicari lagi

| Prioritas | Temuan | Lokasi awal |
| --- | --- | --- |
| Tinggi | Total POST 8M kalah oleh 14/15 file x sekitar 1MiB | PHP host + form multipart |
| Tinggi | Batas PHP per file 2M bertentangan dengan label/validasi 10MiB | `/etc/php.ini`, Request max:10240 |
| Tinggi | Action client menentukan required tetapi controller selalu submitted | `StoreSidangTaRegistrationRequest.php:16,28`, controller store/update |
| Tinggi | Unknown document key bisa masuk loop saveDocuments tanpa whitelist parent | Request dan controller `saveDocuments()` |
| Tinggi | Registration submitted sebelum storage selesai; old file dihapus sebelum pengganti berhasil | Controller store/update/saveDocuments |
| Sedang | Pengajuan ganda mungkin dari check exists lalu create | Controller store |
| Sedang | Cabang biodata tersembunyi tidak disabled | form + partial upload |
| Sedang | Periode tunggal eksklusif bertentangan pilihan beberapa periode | Model active(), store/update/setActivePeriod, dashboard |
| Sedang | Detail snapshot menampilkan key mentah | student show.blade.php |

## Baseline scan

434 berkas pada tujuh direktori sumber dibandingkan. Source kedua folder sama; hanya file `database/database.sqlite` ada pada dadu-test. Pemeriksaan tambahan juga mengambil hash berkas root non-secret, tanpa membaca `.env`. Vendor tidak dibandingkan lintas folder secara menyeluruh. Jangan mengklaim seluruh runtime/dependensi/database sama.

SHA-256 berkas utama sebelum coding:

| File | SHA-256 |
| --- | --- |
| `app/Http/Controllers/SidangTaRegistrationController.php` | `d863b5152d008f49c6ca4043badef576bd666896d4feee473984f8c6161cd000` |
| `app/Http/Requests/StoreSidangTaRegistrationRequest.php` | `9a9fefec3dc6225e5d27cd4dfb994756d23b2f986d9f75ab56ca9d1ecb1555dd` |
| `app/Models/SidangTaRegistration.php` | `c7b8863f9968eb74cc64d82ebc3a25ec0f8bce77815e27891b2aef2092161b66` |
| `app/Models/SidangTaPeriod.php` | `e459f03231e207b7e2b97a85af76e63af8614ceddea21ece52981aa318a00208` |
| `resources/views/sidang_ta_registrations/form.blade.php` | `71141dab20f5853433583a8d61296bf42490ecf1b973aaf840b4b22627ee3a6a` |
| `resources/views/sidang_ta_registrations/partials/upload.blade.php` | `957086d17c89e2f48a5389f08747dd314324f34b502a9438d7a9a7fdc21bfa92` |
| `routes/web.php` | `c02f9576f8ac53d66dc9c2ca940a985dad377ea96457d82d3cd06714ee3f9941` |
| `database/migrations/2026_10_31_000001_create_sidang_ta_registration_tables.php` | `054ec83272ea42326536f5b6b05d4230cd98214a153d8008656e154bc9a6029f` |

Hash hanya penanda baseline, bukan bukti semantik bahwa kode baru benar. Baris referensi akan bergeser setelah pengguna mengubah source; selalu ambil nomor baris terbaru.

## Verifikasi sesi dokumentasi pertama

- Scan awal controller/request/model/migrations/route/view/role/permission/test/runtime selesai.
- Pemeriksaan upload independen menemukan limit CLI 8M/2M dan exact middleware error. HTTP runtime/upload nyata belum diuji; pastikan cara PHP dijalankan sebelum menyatakan setting HTTP pasti sama.
- Dokumentasi Laravel 13 dan Alpine diambil melalui Context7; PHP dari manual resmi. Tidak mengedit vendor atau menambahkan dependency.
- Scan hash sebelum/sesudah setiap penulisan dokumen menunjukkan source kedua folder tetap seperti baseline. Seluruh tulisan baru ada pada docs/03–07.
- Reviewer read-only menemukan lima masalah rencana: FK consumed document, urutan replay, checkpoint B bergantung tahap berikutnya, revisi periode tertutup, dan stale reviewer. Kelimanya sudah ditangani di 05 dan checklist 06.
- Tidak login browser, menjalankan upload, migrasi, seed, test atau build aplikasi. Jangan melaporkan test sudah lulus.

## Status pekerjaan pengguna

| Checkpoint | Status | Bukti |
| --- | --- | --- |
| A upload | Upload berhasil menurut pengguna; persiapan test tetap diperiksa sebelum B | Nilai dilaporkan 192M/12M/20/256M; max_input_time masih -1, investigasi ditunda atas instruksi pengguna. Belum semua kasus batas diuji. |
| B periode jamak | Lulus menurut pengguna | Pengguna mengonfirmasi semua test berhasil. Asisten belum menjalankan suite sendiri. |
| C draft schema/akses | C1 siap dikerjakan; C2 menunggu | Kode migrasi/model dan delapan test lengkap tersedia di 09. |
| D save/upload/revisi | Belum dikerjakan | Panduan tersedia. |
| E UI empat tahap/review | Belum dikerjakan | Panduan tersedia. |
| F finalisasi/idempotensi | Belum dikerjakan | Panduan tersedia. |
| G detail riwayat | Belum dikerjakan | Panduan tersedia. |

Pembaruan setelah laporan upload: lanjut ke [08 — panduan checkpoint B](08-checkpoint-b-periode-sidang-ta.md). Scan ulang model/controller/view periode menunjukkan implementasi tunggal masih ada di kedua folder; jangan menganggap checkpoint B sudah diimplementasikan. Asisten tetap hanya menulis Markdown.

Pembaruan berikutnya menggantikan status scan pada paragraf sebelumnya: source asli sudah mengandung scope/perubahan aktivasi, sedangkan dadu-test masih lama. Pengguna meminta setiap instruksi test dilengkapi kode lengkap dalam Markdown, bukan hanya daftar nama/assertion. Dokumen 08 sekarang memuat satu file Pest penuh; 05/06 merujuknya dan membedakan kontrak test masa depan. Tidak menjalankan test aplikasi atau menulis tests/*.php oleh asisten. Pemeriksaan syntax dilakukan langsung dari blok PHP Markdown melalui stdin, tanpa membuat file source.

## Saat pengguna kembali

Baca catatan ini lalu tanyakan hanya checkpoint/file yang belum jelas dari pesan mereka. Scan hasil yang dipindah terlebih dahulu; jangan mulai membuat aplikasi. Bandingkan rencana dengan kode aktual dan uji yang mereka kirim. Tuliskan temuan serta langkah koreksi pada dokumen review berikutnya bernomor `08-...md` atau revisi catatan checkpoint yang relevan; tandai tanggal dan bukti aktual. Jika ada keputusan kebijakan baru, perbarui rancangan 03 dan rencana 05 sebelum instruksi berikutnya.


## Pembaruan rescan B/C1 dan transisi C2

Pengguna meminta scan langsung folder asli sebelum dipindahkan. Hasil: model dokumen final tertimpa isi model draft; relasi documents draft menunjuk model final; file DraftDocument belum ada. Migrasi aktual `2026_11_01_115038_create_sidang_ta_regristration_drafts.php` berisi skema benar dengan typo basename yang jangan diubah jika sudah tercatat berjalan. Koreksi dan test regresi ada di 10; test di 09 juga sudah diselaraskan. C1 belum dinyatakan lulus oleh asisten.

Panduan 11 menyediakan backend C2 khusus draft baru: start/resume/save kontak tahap 1 dengan delapan feature test lengkap. JSON masih antarmuka backend sampai E; revisi ditolak eksplisit sampai D. Belum membuat UI, upload atau finalisasi. Pengguna mempertahankan konfigurasi phpunit.xml Podman selama student dan akan memulihkannya sebelum commit/push; petugas nanti bisa memakai environment lokal yang sama. Command environment lengkap setelah pemulihan tersedia di 11. Jangan memulihkan file atau melakukan commit dari asisten tanpa instruksi baru.
