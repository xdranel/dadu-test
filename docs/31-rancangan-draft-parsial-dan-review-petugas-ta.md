# 31 — Rancangan draft parsial dan Review Sidang TA petugas

Status: **rancangan disetujui pengguna melalui “setuju dan lanjutkan”**. Student ditutup pada [37](37-penutupan-student-setelah-commit.md). P1/P2a/P2b termasuk koreksi41 sudah dilaporkan lulus unit/manual, tersalin dan direscan tanpa blocker. Panduan aktif [42 — P3](42-checkpoint-p3-verifikasi-dokumen-dan-feedback.md) menunggu penerapan/test pengguna. Asisten tetap hanya menulis docs.

## 1. Keputusan dan cakupan

Baseline sebelumnya adalah versi student yang telah dilaporkan lulus 98 test PHP dan pengujian manual pada [30](30-rescan-akhir-student-dan-commit.md). Permintaan ini mengubah sebagian kebijakan penyimpanan draft dan mulai menambahkan workflow petugas.

Keputusan pengguna yang sudah diberikan:

| Pertanyaan | Jawaban |
| --- | --- |
| Boleh menyimpan tahap 3–4 ketika tanggal/pilihan biodata belum diisi, selama berkas yang dipilih valid? | **Boleh simpan isian belum lengkap.** |
| Aksi petugas cukup keputusan pengajuan atau perlu verifikasi tiap dokumen? | **Tambahkan verifikasi setiap dokumen.** |

Kebutuhan lain dari daftar pengguna:

- Draft dengan sebagian file tetap tersimpan; tahap berikutnya baru dapat dibuka setelah prasyarat lengkap.
- Hapus tombol Unduh berkas pada area Berkas tersimpan tahap 2–4.
- Header atas Pendaftaran Sidang TA mengikuti pola halaman Pendaftaran Yudisium; pertahankan section Draft tersimpan dan tabel Riwayat Pengajuan.
- Hapus kalimat “Informasi akademik bersifat baca saja. Lengkapi dokumen sesuai persyaratan pada form.”
- Sidebar petugas hanya memiliki Review Sidang TA; pengaturan periode masuk dari halaman review dan memiliki tombol kembali.
- Review petugas mengikuti pola Yudisium: periode/waktu/status, jumlah ringkasan, pencarian/filter, tabel ikon dokumen, tindakan dan ekspor Excel, dengan data serta aturan TA.

## 2. Pendekatan yang direkomendasikan

Gunakan pola visual dan dependency yang sudah ada pada Yudisium, tetapi pertahankan model, route, permission dan aturan TA sendiri. Kedua modul memiliki jumlah/cabang dokumen, periode dan kontrak revisi berbeda.

| Pilihan | Konsekuensi |
| --- | --- |
| Adaptasi tampilan Yudisium pada modul TA yang terpisah | **Direkomendasikan.** Dapat menjaga kontrak TA dan menguji perubahan per checkpoint |
| Menyatukan backend Yudisium/TA menjadi modul form generik | Mengharuskan refactor Yudisium yang tidak diperlukan untuk daftar ini |
| Menyalin controller/view Yudisium apa adanya | Membawa asumsi satu periode aktif, nama field/permission/dokumen yang berbeda dan ketidaksamaan filter ekspor |

Untuk student, pilih dua tombol **Simpan draft** dan **Lanjutkan**. Ini merupakan pilihan usulan asisten dari dua bentuk tombol yang diizinkan pengguna; tidak menambahkan autosave atau endpoint upload satu-file baru.

## 3. Hasil scan yang berpengaruh

### Penyimpanan parsial

`SidangTaDraftDocumentService::save()` sekarang melempar ValidationException ketika ada satu dokumen wajib yang belum tersimpan. Karena berada di dalam transaksi, file valid yang dipilih pada request tersebut ikut dibatalkan. `SaveSidangTaDraftStepRequest` dan service juga memakai scalarRules wajib yang sama dengan pemeriksaan kelengkapan. Jadi perubahan perlu memisahkan **data draft yang sah untuk disimpan** dari **tahap yang lengkap untuk dilanjutkan**.

Controller saveStep() sekarang selalu mengarahkan ke tahap berikutnya setelah save. UI memakai Simpan & Lanjutkan. Keduanya perlu disesuaikan; mengganti label tombol saja tidak menyelesaikan kebutuhan.

### Referensi Yudisium dan petugas

- Referensi student: `resources/views/graduation_registrations/index.blade.php`, bagian header judul/aksi. Tabel riwayat TA tidak perlu ditiru ulang.
- Referensi petugas: `resources/views/admin/graduation_registrations/index.blade.php`, banner periode, filter/ekspor, ringkasan dan ikon berkas.
- Backend referensi: `GraduationRegistrationController::adminIndex()` dan `exportExcel()`; FastExcel sudah menjadi dependency proyek.
- TA sekarang mempunyai daftar/filter dasar, pengaturan periode terpisah, keputusan registration dan download privat. Belum ada endpoint verifikasi dokumen TA atau preview inline TA.
- Yudisium mengasumsikan satu periode aktif; TA telah mendukung beberapa periode terbuka. Aturan TA tidak diubah menjadi satu periode hanya untuk menyamakan banner.
- Pada referensi Yudisium, statistik hanya mengikuti periode, sementara filter study_program pada tabel belum diterapkan pada ekspornya. Rancangan TA di bawah memilih kriteria yang konsisten, bukan menyalin ketidaksamaan ini.
- Renderer `sidebar-admin.blade.php` memanggil `$user->can($perm)` untuk satu nama permission. Menulis string `permission_a|permission_b` pada config sidebar tidak otomatis memberikan perilaku OR seperti middleware Spatie.

## 4. Alur student: simpan dahulu, lanjut setelah lengkap

### Tahap 1

Tetap memilih periode terbuka dan mengisi kontak sebagaimana kontrak awal. Perubahan parsial ini berfokus pada tahap upload 2–4 yang diminta pengguna. Draft tetap memerlukan periode yang valid ketika pertama dibuat.

Untuk konsistensi UI, setelah simpan awal mahasiswa berada pada tahap 1 dengan draft yang sudah tercatat, lalu dapat menekan Lanjutkan. Tidak membuat draft kosong hanya karena membuka halaman.

### Tahap 2–4

| Aksi | Hasil yang dirancang |
| --- | --- |
| Pilih 1 dari 6 capture lalu Simpan draft | Satu berkas benar-benar disimpan; tetap tahap 2; tampil jumlah dan dokumen yang belum lengkap |
| Tutup halaman kemudian buka kembali | File yang berhasil disimpan tetap terlihat pada area Berkas tersimpan |
| Tanggal wisuda kosong pada tahap 3 | Simpan file valid boleh; tahap 3 belum lengkap dan belum dapat lanjut |
| Biodata belum dipilih pada tahap 4 | Draft boleh disimpan; berkas cabang baru memerlukan pilihan cabang yang jelas |
| Dokumen tersimpan sudah memenuhi isian | Tidak perlu mengunggah ulang untuk menyimpan isian lain |
| Salah satu file yang benar-benar dikirim berformat/ukuran tidak valid | Request ditolak secara atomik; isi draft/file sebelumnya tetap aman. Dokumen yang tidak dipilih berbeda dengan file yang dipilih tetapi tidak valid |
| Semua persyaratan tahap sudah tersimpan | Lanjutkan aktif; pada tahap 4 Tinjau sebelum kirim dapat digunakan setelah seluruh tahap lengkap |

Ketentuan detail:

- **Simpan draft** menyimpan data valid yang tersedia dan kembali ke tahap yang sama. Sesudah sukses, dirty dibersihkan lewat respons halaman baru, waktu simpan diperbarui dan version bertambah seperti sekarang.
- Isian opsional ketika menyimpan draft tetap divalidasi bila diisi: tanggal valid/tidak di masa depan, boolean biodata valid, batas kontak, whitelist dokumen, tipe MIME dan maksimal 10 MB tetap berlaku.
- Field yang tidak dikirim mempertahankan nilai lama; field tanggal yang sengaja dikosongkan boleh menyimpan null dan menjadikan tahap belum lengkap.
- Dokumen yang tidak dipilih tidak dihapus dan tidak menyebabkan seluruh save gagal. File tersimpan yang hilang secara fisik tetap dihitung belum lengkap.
- Jika pilihan biodata belum ada, jangan memperlakukannya sebagai false/PDM secara otomatis. Validasi tipe file cabang harus memakai pilihan yang benar setelah data request digabungkan dengan draft tersimpan.
- Bila cabang diganti dan disimpan, metadata/file khusus draft dari cabang lama dibersihkan sesuai transaksi yang aman. File final yang dipinjam revisi tetap terlindungi. Pilihan kosong tidak boleh diam-diam menghapus dokumen lama.
- **Lanjutkan** menggunakan kelengkapan data tersimpan, bukan sekadar file masih terpilih di browser. Tombol disabled ketika belum lengkap, ada perubahan belum disimpan, atau sedang save. Tautan tahap tetap dijaga di server.
- Tahap 4 menggunakan **Simpan draft** dan **Tinjau sebelum kirim**. Tinjau/final submit tetap menolak dokumen/isian yang belum lengkap. Jangan melonggarkan pemeriksaan final hanya karena save draft dilonggarkan.
- Indikator amber: **Draft tersimpan. Lengkapi persyaratan tahap ini sebelum melanjutkan.** Sertakan daftar yang kurang, misalnya **1 dari 6 dokumen tersimpan** dan tanggal/pilihan yang belum diisi.
- Bintang merah tetap menunjukkan isian wajib **sebelum lanjut/kirim**, bukan kewajiban melengkapi seluruhnya sebelum menyimpan progres.
- Owner/permission/version, stale revision, transaksi file dan penolakan lompat tahap tetap berlaku. JSON first_incomplete_step dipertahankan; informasi kelengkapan tahap dapat ditambahkan tanpa menghilangkan field respons lama.

### Perubahan visual student lain

- Area Berkas tersimpan tahap 2–4 hanya menampilkan nama, ukuran dan status tersimpan. Tombol Unduh berkas di area tersebut dihapus. Tombol unduh pada Tinjau dan Detail tetap ada.
- Route download privat dan kontrol akses tetap dipertahankan; pengguna meminta menghapus tombol, bukan mengubah penyimpanan atau menjadikan file publik.
- Kalimat bantuan read-only yang disebut pengguna dihapus dari `partials/wizard-academic.blade.php`; card data akademiknya tetap tampil.
- Header index menggunakan judul/subjudul dan aksi di kanan seperti Yudisium. Tampilkan aksi sesuai kondisi: pengajuan aktif, revisi, mulai pengajuan atau pendaftaran ditutup. Draft tersimpan dan tabel Riwayat Pengajuan TA tetap dipertahankan.
- Header tidak kembali memakai satu activePeriod. Jumlah/periode terbuka TA tetap konsisten dengan pilihan tahap 1.

## 5. Satu pintu Review Sidang TA dan pengaturan periode

Hapus item sidebar **Periode Sidang TA**. Satu item **Review Sidang TA** juga tetap aktif ketika sedang membuka halaman periode TA.

Usulan navigasi yang menjaga role superadmin:

| Permission | Halaman hub Review TA |
| --- | --- |
| registration_review | Bisa melihat data pengajuan, statistik, ekspor dan dokumen; tombol Kelola Periode hanya jika juga punya period_read |
| period_read saja | Bisa masuk hub dan Kelola Periode, tetapi tidak menerima tabel/nama mahasiswa/statistik/ekspor/review dokumen |
| Keduanya | Memiliki kedua area sesuai permission masing-masing |
| Tidak punya keduanya | Menu tersembunyi dan route hub ditolak |

- Route index hub dapat memakai OR review/period_read; route detail pengajuan, review, preview dokumen dan ekspor tetap khusus review.
- Renderer sidebar mendapat dukungan OR yang eksplisit dan terbatas, misalnya key `permissions_any`. Permission tunggal dan roles pada item lain tetap bekerja. Item yang tidak memenuhi permissions_any tidak boleh jatuh ke cabang “tanpa permission” lalu terlihat untuk semua akun.
- Tidak memberi permission review otomatis kepada pengelola periode dan tidak memakai nama role Petugas sebagai pengganti permission.
- Kelola/Atur Periode membuka view periode TA terpisah. Tambahkan tombol **Kembali ke Review Sidang TA** dengan route tetap; buat/edit/aktivasi tetap memakai permission periode yang sudah ada.
- Beberapa periode aktif tetap diperbolehkan. Pembeda status: tidak aktif, belum dibuka, sedang terbuka, dan ditutup berdasarkan status aktivasi serta waktu.

## 6. Halaman review petugas

### Bagian atas dan periode

- Pola visual Yudisium: judul, informasi periode/waktu/status dan aksi Kelola Periode.
- Jika filter memilih satu periode, tampilkan ringkasan periode itu. Jika Semua periode, tampilkan kartu/ringkasan beberapa periode aktif; tidak mengambil first() sebagai satu periode resmi TA.
- Periode aktif yang belum mulai/telah tutup tetap diberi status yang benar. Jika tidak ada periode aktif, tampilkan keadaan kosong yang jelas dan akses kelola sesuai izin.
- Bila memakai countdown/minimize seperti referensi, gunakan key state yang khusus TA agar tidak berbagi preferensi dengan Yudisium. Countdown hanya informasi; backend tetap memutuskan keterbukaan periode.

### Pencarian, filter dan statistik

Kontrak usulan: search nama/NIM, periode melalui ID FK TA, status registration, dan program studi. Tabel, ringkasan jumlah dan ekspor mengikuti **seluruh filter yang sama**. Judul ringkasan: **Ringkasan hasil filter**.

Lima kartu: Total, Menunggu peninjauan, Perlu revisi, Disetujui, Ditolak. Saat status tertentu dipilih, kartu status lain dapat bernilai nol; total tetap hasil filter, bukan jumlah pada satu halaman pagination. Kriteria ini dinyatakan agar tidak mencampur total semua data dengan tabel terfilter.

Pagination mempertahankan filter, Reset mengembalikan filter, dan state kosong membedakan belum ada pengajuan dengan tidak ada hasil pencarian. Nilai filter divalidasi; query untuk daftar/statistik/ekspor tidak ditulis dengan aturan berbeda.

### Tabel dan ikon TA

Identitas mahasiswa, prodi, periode, tanggal kirim, status pengajuan dan aksi Tinjau/detail tetap ada. Gunakan ikon mata untuk detail, ikon Excel pada ekspor dan ikon dokumen dengan tooltip/label aksesibel.

Rekomendasi tata letak: **tiga kolom kelompok ikon**—iGracias & Akademik, Pembayaran & Dokumen, Biodata—agar 16 jenis dokumen TA tidak membuat 16 kolom judul lebar. Tetap satu ikon per jenis dokumen dengan urutan/legenda yang tetap:

- Enam capture tahap 2.
- Tujuh dokumen tahap 3.
- Tiga posisi biodata: dua dokumen ijazah dan satu PDM; yang tidak berlaku diberi N/A.

Satu pengajuan membutuhkan 15 atau 14 dokumen, bukan semua 16. Ikon menunjukkan status dokumen nyata: pending, verified, rejected; dokumen wajib tanpa metadata dibedakan dari cabang N/A. Jangan mengubah semua ikon menjadi hijau hanya karena status registration approved.

Klik ikon membuka preview privat bagi petugas. Endpoint TA untuk preview perlu ditambahkan; download yang sudah ada tidak diberi label preview secara keliru. PDF/gambar dapat ditampilkan inline melalui response privat; file lain yang tidak cocok ditangani sebagai unduhan. Nama/file path storage tidak menjadi URL publik.

Kontrak download final juga diselaraskan: middleware route download menerima **registration_read OR registration_review**, lalu guard pemilik/reviewer yang sekarang tetap memeriksa akses file. Ini diperlukan karena pemegang review saja belum tentu mempunyai read mahasiswa. Endpoint preview petugas khusus review; akun periode-only tidak mendapat akses berkas mahasiswa. Test harus mencakup reviewer tanpa read, bukan hanya akun yang diberi seluruh permission sekaligus.

Tidak menambahkan hapus pengajuan final pada rancangan ini: TA belum memiliki kontrak/permission delete registration seperti Yudisium. Aksi yang ditambahkan adalah verifikasi dokumen sesuai jawaban pengguna dan keputusan pengajuan yang sudah ada.

## 7. Verifikasi setiap dokumen — aturan rancangan yang disetujui

Bagian ini merinci perilaku yang belum disebut eksplisit oleh pengguna. Berikut **rekomendasi asisten**, bukan keputusan yang dianggap sudah diberikan:

1. Setiap dokumen final memiliki pending, verified atau rejected seperti kolom yang sudah ada. Petugas dapat Verifikasi atau Minta perbaikan dokumen; penolakan dokumen wajib memiliki catatan yang jelas.
2. Review dokumen dilakukan pada detail pengajuan, dengan preview/unduh dan kontrol per dokumen. Tabel daftar merefleksikan hasil review itu.
3. Satu dokumen ditolak mengubah pengajuan submitted menjadi **needs_review** secara atomik, mengikuti pola Yudisium. Mahasiswa dapat melihat dokumen dan catatan yang perlu diperbaiki.
4. Menyelesaikan verifikasi semua dokumen **tidak otomatis menyetujui pengajuan**. Petugas tetap memberikan keputusan akhir.
5. **Setujui pengajuan hanya boleh jika seluruh dokumen wajib cabang aktif tersedia dan verified.** Pending/rejected/missing mencegah approved. Cabang N/A tidak dihitung. Keputusan Minta Revisi/Tolak tetap memiliki catatan sebagaimana aturan sekarang.
6. Registration approved/rejected bersifat read-only untuk review dokumen pada tahap ini. Tidak menambahkan pembukaan ulang keputusan final.
7. Setiap perubahan status/catatan review dokumen menaikkan lock_version registration dalam transaksi. Request membawa expected version; halaman lama mendapat 409 dan perlu muat ulang. Dokumen harus benar-benar milik registration yang direview.
8. Perubahan reviewer setelah mahasiswa mulai revisi dapat membuat draft revisinya stale. Perilaku source_version yang sudah ada dipertahankan; jangan menonaktifkan guard agar save mahasiswa tampak berhasil.
9. Kirim ulang revisi tetap mengembalikan dokumen final ke pending seperti finalizer sekarang, termasuk berkas yang dipakai ulang. Ini mempertahankan kontrak awal; tidak diam-diam mempertahankan verifikasi lama atas kiriman revisi baru.
10. Catatan/status per dokumen tampil juga pada detail mahasiswa dan konteks draft revisi agar mahasiswa tahu berkas mana yang bermasalah. Catatan umum registration tidak dijadikan satu-satunya tempat menyimpan semua catatan dokumen.

Gunakan permission `sidang_ta_registration_review` untuk review dokumen. Tidak menggunakan permission update milik mahasiswa sebagai izin menilai berkas. Log review dokumen disimpan sebagai tambahan riwayat dengan label Indonesia; idempotent final submit dan perlindungan file pinjaman tetap berlaku.

Tidak menambahkan email/Slack/notifikasi eksternal pada rancangan ini. Pemberitahuan yang diperlukan tersedia di halaman mahasiswa; jika diperlukan notifikasi aplikasi tersendiri, kontraknya dapat ditambahkan secara eksplisit kemudian.

## 8. Export Excel

- Gunakan FastExcel yang sudah terpasang. Ekspor khusus pemegang registration_review dan mencakup seluruh hasil filter, bukan hanya halaman pagination.
- Kriteria search/period/status/prodi sama dengan tabel/statistik. Penempatan route ekspor tidak boleh tertangkap route detail `{sidang_ta_registration}`.
- Satu baris per pengajuan: periode, NIM/nama/prodi, kontak, tanggal bayar, kesesuaian biodata, waktu/status pengajuan, catatan reviewer, data akademik dari academic_snapshot, serta status/catatan dokumen yang relevan.
- Nilai akademik historis tidak diganti Student terbaru; NIM/telepon tetap teks sehingga nol awal tidak hilang. Tidak mengekspor file_path privat sebagai tautan publik.
- Cabang dokumen tidak berlaku ditulis N/A. Nilai kosong dan nol tetap dibedakan. Format/kolom final ditetapkan pada panduan ekspor lengkap, lalu diuji membaca isi spreadsheet, tidak hanya memeriksa respons download.

## 9. Urutan checkpoint setelah rancangan disetujui

| Checkpoint | Hasil yang dapat diuji |
| --- | --- |
| S1 | Save draft parsial tahap 2–4, validasi draft/kelengkapan terpisah, dua tombol, peringatan, blok lanjut/tinjau/final, regresi file/version/revisi |
| S2 | Hapus tombol unduh pada berkas tersimpan dan kalimat bantuan, header index seperti Yudisium; riwayat/draft tetap |
| P1 | Satu menu Review TA, hub permission yang benar, akses Kelola Periode dan tombol kembali, multi-periode |
| P2a | Review daftar: banner periode, filter/statistik, matriks ikon TA, detail/preview privat, pagination dan state kosong |
| P2b | Export Excel mengikuti query filter yang sama, dengan test isi spreadsheet dan akses |
| P3 | Review per dokumen, guard persetujuan akhir, version/lock/log, tampilan catatan pada mahasiswa dan uji konkurensi |

Pola visual dokumen belum terverifikasi pada P2a akan tetap pending; kemampuan mengubah statusnya baru tersedia setelah P3. Setiap checkpoint direscan sebelum berpindah berikutnya. Nama/detail file, generator command, kode produksi dan test lengkap diberikan pada panduan implementasi masing-masing, bukan ditebak pengguna dari rancangan ini.

## 10. Test yang harus diperbarui dan ditambah

Baseline 98 test tidak dihapus. Namun assertion yang secara eksplisit mengharuskan draft tidak lengkap ditolak memang berubah kebijakan, sehingga tidak bisa dipertahankan apa adanya lalu menganggap partial-save selesai.

- UploadTest: save sebagian, isi yang kurang berikutnya, resume sesudah tutup, validasi file yang dikirim, whitelist, rollback, version stale dan file hilang.
- WizardUi/WizardTest: save tetap pada tahap saat ini, Lanjutkan disabled untuk data belum tersimpan/lengkap, tahap 3 tanpa tanggal dan tahap 4 tanpa pilihan tetap dapat menyimpan, tinjau/final belum boleh.
- UploadTest yang sebelumnya menolak pergantian cabang tanpa berkas baru perlu berubah menjadi draft berhasil disimpan tetapi belum lengkap; file final pinjaman tetap tidak terhapus.
- Contoh assertion tombol Unduh di wizard direvisi menjadi nama/ukuran tersimpan tanpa tombol. Test endpoint download privat dan tombol Tinjau/Detail tetap ada.
- FinalizationTest tetap menguji replay/rollback/owner/source version. Fixture persetujuan akhir perlu menyelesaikan verifikasi dokumen dahulu ketika guard approved baru diterapkan; jangan menghapus assertion replay keputusan reviewer.
- P1: reviewer, periode-only, keduanya dan tidak memiliki izin; role switch; menu lain tidak menjadi publik akibat schema permission OR.
- P2: dua periode terbuka, periode future/closed, semua kombinasi filter termasuk prodi, total vs pagination, N/A vs missing, status ikon dan akses preview/download untuk reviewer-only tanpa read, pemilik, mahasiswa lain dan periode-only.
- P2b: isi ekspor cocok hasil filter, seluruh halaman tercakup, NIM/nol/snapshot terjaga, periode-only tidak dapat ekspor.
- P3: reject wajib catatan, verified per dokumen, approved diblokir bila belum semua verified, terminal read-only, dokumen lintas pengajuan ditolak, version stale, perubahan reviewer saat draft revisi aktif, resubmit menjadi pending dan log tetap tepat.

Jumlah test baru tidak ditetapkan sebelum kode/panduan checkpoint disusun. Seluruh panduan tetap menyertakan command DB_* eksplisit pada MySQL Podman schema dadu_testing, kode test lengkap dan checklist browser. Tidak memaksa SQLite atau menjalankan suite aplikasi dari asisten selama batas hanya-docs masih berlaku.

## 11. Batas selesai dokumen ini

Ini rancangan yang telah disetujui, bukan kode untuk langsung ditempel ke aplikasi. Dua jawaban pengguna pada bagian 1, aturan bagian 7 dan pola filter/permission menjadi acuan checkpoint berikutnya. Student, P1/P2a/P2b sudah diterapkan, diuji dan direscan. Panduan [42 — P3](42-checkpoint-p3-verifikasi-dokumen-dan-feedback.md) menunggu penerapan/test/salinan dan rescan penutup sebelum commit petugas.

Source dibaca sebelum penulisan dan diperiksa kembali sesudahnya. Tidak menulis aplikasi, mengubah konfigurasi/permission/database, menjalankan test/build, atau mengirim pesan eksternal oleh asisten pada sesi rancangan ini.

Review independen menemukan satu kontrak yang perlu diselaraskan: permission download final untuk reviewer tanpa read. Hal itu sudah ditambahkan pada bagian 6 dan cakupan test. Tidak ditemukan pertanyaan penghambat lain; aturan bisnis verifikasi pada bagian 7 tetap menunggu persetujuan pengguna.
