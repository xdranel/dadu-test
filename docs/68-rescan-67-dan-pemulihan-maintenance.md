# 68 — Rescan checkpoint 67, JSON terbaru, dan pemulihan maintenance

Pengguna melaporkan seluruh unit test berhasil dan file sudah disalin ke dadu-test. Sesudah update mengikuti 64 bagian12, situs sempat503 dan pulih setelah pengguna menjalankan `artisan up`. Dokumen ini mencatat hasil pemeriksaan ulang, tanpa mengubah source aplikasi kedua repo.

## 1. Hasil perbandingan kedua repo

Folder yang dibandingkan:

- Development: `/home/xdranel/Code/Project/php-laravel/dadu`.
- Salinan deployment: `/home/xdranel/Code/Project/secret/dadu-test`.

Tujuh file checkpoint67 identik berdasarkan SHA-256:

| File | Hasil |
| --- | --- |
| `app/Services/SidangTaAdmissionFacts.php` | Identik |
| `app/Support/SidangTaAdmissionPolicy.php` | Identik |
| `app/Services/SidangTaAcademicSnapshot.php` | Identik |
| `tests/Feature/SidangTaOfficialAdmissionTest.php` | Identik; 24 deklarasi test |
| `tests/Feature/SidangTaAdmissionFactsTest.php` | Identik |
| `tests/Feature/SidangTaWizardUiTest.php` | Identik |
| `tests/Unit/SidangTaAdmissionPolicyTest.php` | Identik; 11 deklarasi test |

Seluruh **510 file** pada folder `app`, `config`, `database`, `resources`, `routes`, dan `tests` identik. Perhitungan mencakup semua file dalam enam folder tersebut, bukan hanya PHP. Empat JSON sintetis67-01 sampai67-04 valid dan identik di kedua repo.

Ketujuh file PHP di atas lulus `php -l`. Tiga suite unit aktual dijalankan dengan konfigurasi pengujian kosong, tanpa bootstrap aplikasi Laravel atau akses database:

```bash
vendor/bin/pest --no-configuration tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php tests/Unit/AcademicProfileJsonNormalizerTest.php --colors=never
```

Hasil: **47 passed, 403 assertions**. Ini verifikasi policy/requirements/normalizer, bukan laporan ulang feature test MySQL. Hasil test pengguna tetap dicatat sebagai laporan pengguna; tidak mengklaim seluruh suite atau uji browser dijalankan asisten.

## 2. Alur checkpoint 67 yang sudah tersambung

- `SidangTaAdmissionFacts` membaca ulang Student dan profil resmi berdasarkan NIM. Saat profil resmi tersedia, grup yang kosong tidak meminjam nilai manual lama.
- Lima syarat akademik dan mode bahasa tetap terpisah. Nilai minimal450 memilih automatic; nilai di bawah450 atau null dengan jumlah tes resmi minimal3 memilih waiver. Nilai null tidak diubah menjadi0.
- Pembaca bahasa memakai ringkasan resmi, tanpa menghitung ulang jumlah tes dari panjang riwayat. SKPI memakai status `Disetujui`.
- Helper KRS TA menerima nama utuh TUGAS AKHIR/TUGAS AKHIR2 dan alias yang didukung, berdasarkan tepat satu periode akademik aktif. Proposal/TA1/TA20 tidak diterima oleh helper ini.
- Controller form awal/draft menimpa ringkasan syarat dan skor bahasa dengan hasil AdmissionFacts. Jadi form sudah menerima fakta checkpoint67 meskipun pembaca snapshot umum belum seluruhnya diselaraskan.
- `SidangTaDraftFinalizer` memanggil fakta kembali saat kirim, memeriksa blocker/dokumen, dan menyimpan nilai resmi serta admission dalam snapshot. Revisi terarah mempertahankan snapshot pengajuan asal.

## 3. Bagian yang masih menunggu penyelarasan berikutnya

| Bagian | Temuan source saat rescan |
| --- | --- |
| Persetujuan sidang pada tahap2 | `SidangTaDraftController::meetContext` masih mengisi kartu dengan `Belum tersedia`, meskipun profil resmi sudah dapat menyimpan `groups.sidang_approval`. |
| Label bahasa | Kartu, ringkasan, dan detail masih memakai label EPRT/EPrT untuk semua jenis. Jenis resmi sudah dibaca AdmissionFacts dan ikut tersimpan dalam admission pengajuan baru; tampilan belum menampilkannya secara konsisten. |
| BPP | Controller masih membaca tabel pembayaran lama; panel selalu menyebut periode belum terkonfirmasi. `groups.bpp`/`bpp_periods` belum menjadi pembaca tampilan yang konsisten. Label kartu `BPP semester berjalan` perlu diselaraskan dengan ketersediaan periode yang benar-benar diketahui. |
| Pembaca snapshot umum | `SidangTaAcademicSnapshot::forStudent` masih memakai relasi lama untuk sejumlah nilai. Form dan finalizer sudah menimpa nilai syarat/bahasa yang relevan, tetapi pemanggil lain perlu diperiksa saat penyelarasan. |
| Pembaca data kampus modul lain | `ImportedCampusDataGrabService` masih memakai skor bahasa maksimum tabel lama dan pencocokan KRS `LIKE`. Aturannya belum sama dengan helper TA yang menerima nama utuh dan tepat satu periode aktif. |

Ini cakupan lanjutan yang sudah disebut pada67, bukan alasan mengulang implementasi checkpoint67. Penyelarasan berikutnya perlu mempertahankan snapshot historis, pemisahan syarat bahasa, serta ketentuan bahwa BPP/persetujuan sidang bukan syarat pemblokir tambahan.

## 4. Pemeriksaan JSON baru 6 November

Berkas `/home/xdranel/Downloads/rekap_igracias_2026-11-06.json` dibaca langsung, tanpa diimpor ke database atau disalin ke repo. Laporan hanya memuat struktur dan agregat.

- Wrapper berisi `generated_at`, `source`, `total`, `selected_menus`, dan `data`; jumlah aktual dan total deklarasi sama-sama277.
- Menu yang dipilih: akademik, bahasa, TAK, keuangan, mata_kuliah, jadwal_sidang, SKPI, dan maju_sidang.
- Ringkasan bahasa tetap berisi nilai, jenis, jumlah tes, dan riwayat. Riwayat juga memuat `kategori_tes_valid`; normalizer menyimpan isi riwayat tanpa menghitung ulang keputusan dari field itu.
- Persetujuan memuat ringkasan `persetujuan_maju_sidang` serta field sumber/catatan seperti `status_maju_sidang_asli`, `sumber_persetujuan_sidang`, dan `catatan_persetujuan_sidang`. Acuan sekarang tetap ringkasan APPROVED; field sumber/catatan tidak otomatis menjadi aturan kelayakan baru.
- SKPI:39 Disetujui,7 Belum Disetujui,231 null. Seluruh231 baris error diklasifikasikan normalizer sebagai kegagalan menu SKPI, sehingga grup SKPI dilewati dan nilai lama dapat dipertahankan oleh importer. Null karena error ini tidak ditulis sebagai pengganti status lama.
- BPP kini memuat248 LUNAS dan29 KREDIT langsung pada `status_keuangan`. Normalizer yang ada sudah menerima kedua status tersebut.
- `semester` masih semester studi7/9/11/13/15. Tidak ada `finance_period` pada wrapper/baris maupun kode periode pembayaran pada `semester`. Berkas baru belum menyelesaikan pertanyaan periode BPP; status tidak boleh diklaim sebagai pembayaran semester berjalan hanya berdasarkan tanggal ekspor.

Normalisasi seluruh277 baris memakai class aktual dan `selected_menus` dari wrapper, tanpa database:

| Hasil | Jumlah baris |
| --- | --- |
| Grup bahasa, TAK, BPP, dan persetujuan dapat dibaca | Masing-masing277 |
| Grup SKPI dapat dibaca | 46 |
| Grup SKPI dilewati karena error sumber | 231 |
| Mode bahasa automatic | 181 |
| Mode bahasa waiver | 15 |
| Mode bahasa retest | 73 |
| Mode bahasa unknown | 8 |

Mode bahasa di atas hanya hasil policy bahasa, bukan jumlah mahasiswa yang layak mengirim TA. Lima syarat akademik, KRS, dan kelengkapan draft/dokumen tetap perlu diperiksa terpisah. Pembacaan ini belum membuktikan pencocokan NIM/nama atau keberhasilan impor database.

### Status contoh KRS

Rekap277 baris ini belum berisi daftar KRS yang sedang diambil. Field `mk_belum_lulus`/`mk_mengulang` adalah informasi mata kuliah bermasalah, bukan bukti KRS aktif.

Dummy sudah tersedia pada [67-02-krs-akun-demo.json](fixtures/sidang-ta/67-02-krs-akun-demo.json), termasuk contoh TUGAS AKHIR dan TUGAS AKHIR2 untuk dua mahasiswa demo pada2026/2027 GANJIL. Tetap impor dengan tipe KRS dan pastikan tepat satu periode akademik aktif yang sesuai. Label menu `mata_kuliah` pada rekap profil tidak menggantikan berkas KRS terpisah.

Pengguna menyampaikan contoh KRS resmi mungkin dikirim berikutnya. Contoh itu nanti diperiksa terhadap format importer dan pengaitan periode; rescan/penyelarasan yang sudah mempunyai kontrak dapat berlanjut memakai dummy. Tidak perlu mengarang struktur exporter baru atau mengubah dummy sebelum contoh diterima.

## 5. Pemulihan503 dan koreksi panduan64 bagian12

Bukti yang tersedia:

1. Sesudah update, URL publik memberi503 dengan `Retry-After: 60`; endpoint `/up` tetap200.
2. Pengguna menjalankan `docker compose --env-file .env.docker exec --user www-data app php artisan up`, lalu mendapat `Application is now live.` dan `LOGIN HTTP 200` pada origin lokal.
3. Pemeriksaan ulang asisten pada `https://dadu-test.gendhiramona.site/login` memberi **PUBLIC LOGIN HTTP200**.

[64 bagian12](64-command-operasional-docker-dadu-test.md#12-update-aplikasi-dari-branch-main) kini mencantumkan pemeriksaan migration/status, langkah `artisan up`, probe login lokal/publik, dan pemeriksaan scheduler/worker yang mungkin masih berhenti. `--retry=60` tidak otomatis mematikan maintenance. Health route `/up` tetap dapat memberi200 selama maintenance.

Rescan juga menemukan risiko stdin: script update memakai heredoc, sedangkan Compose exec/run interaktif secara default. `-T` hanya mematikan TTY. Panduan kini menambahkan `--interactive=false` pada exec/run di blok update dan stdin kosong pada pemanggilan backup. Script backup sendiri tidak diubah.

Verifikasi menggunakan Bash dan stub perintah di direktori sementara, tanpa Docker/server:

| Simulasi | Hasil |
| --- | --- |
| Blok lama, stub container menghabiskan stdin interaktif | Script keluar0 tetapi tidak mencapai `artisan up`/`ps` akhir |
| Blok baru, stub yang sama | Script mencapai `artisan up` dan `ps` akhir, keluar0 |
| Blok baru, migration sengaja gagal dengan exit17 | Script berhenti17 dan tidak membuka maintenance |

Ini membuktikan risiko alur stdin dan penjagaannya dalam simulasi. Output lengkap update saat insiden belum tersedia, sehingga belum dapat dipastikan apakah insiden dipicu stdin atau kegagalan command lain. Status migration/scheduler/worker server juga belum dikirim; HTTP200 tidak membuktikan seluruh tahap deployment selesai.

## 6. Batas perubahan pada rescan ini

Hanya Markdown64 dan68 ditulis oleh asisten. Manifest SHA-256 source dan file deployment yang diperiksa di kedua repo dibandingkan sebelum/sesudah perubahan. Tidak mengubah source, JSON dummy, environment, konfigurasi test, atau file Docker; tidak melakukan SSH, migration, seed, rebuild, restart, commit, atau push.

Langkah berikutnya tetap penyelarasan panel persetujuan/bahasa/BPP, pembaca data kampus, dan snapshot sesuai cakupan67. Periode BPP dan contoh KRS resmi tetap dicatat sebagai informasi yang belum tersedia, tanpa menahan pekerjaan yang sudah mempunyai aturan jelas.
