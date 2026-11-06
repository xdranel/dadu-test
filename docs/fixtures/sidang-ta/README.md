# Fixture Sidang TA

## Reset demo75

Untuk reset terbaru dan 11 mahasiswa blackbox, gunakan [panduan75](../../75-reset-demo-lokal-docker-dan-urutan-impor.md). Fixture privat disalin ke repo development asli pada `docs/fixtures/sidang-ta/data_dadu/`; urutan impor: Mahasiswa75-01 → KRS75-02b → Profil Akademik75-03 → BPP75-04 (tipe Profil Akademik). Daftar akun ada pada README privat. Fixture Dummy002 di bawah adalah skenario lama dan tidak membuat akun melalui seeder default terbaru.

## Contoh periode BPP resmi —checkpoint72

Periode pembayaran resmi sudah tersedia sebagai `periode_keuangan="2627/1"` (2026/2027 Ganjil). [72](../../72-periode-bpp-resmi-dan-json-demo.md) mencatat pemeriksaan sumber dan pemetaan yang masih diperlukan; normalizer sekarang belum membaca nama field tersebut.

- `72-01-bpp-periode-akun-demo.json`: keuangan saja, Krisna LUNAS dan Dummy002 KREDIT pada periode yang sama.
- `72-02-bpp-lunas-dummy002.json`: memperbarui Dummy002 menjadi LUNAS pada2627/1.

Kedua file memakai struktur sumber asli dan identitas sintetis, tanpa wrapper finance_period tambahan. Gunakan setelah pemetaan periode_keuangan dari [73](../../73-penyesuaian-periode-keuangan-bpp.md) dipasang, dengan tipe Profil Akademik dan sesudah profil lengkap67-03 tersedia. Contoh sumber yang ditunggu sudah lengkap; dukungan kode dan tampilan BPP masih perlu diselaraskan.

## Contoh KRS resmi —checkpoint69

`69-01-krs-format-resmi-akun-demo.json` memakai struktur ekspor KRS terbaru: `selected_menus=["krs_mk"]`, satu baris per mahasiswa dengan daftar `krs_mata_kuliah`, `tahun_ajaran="2627"`, dan `semester="1"`. Isinya sintetis untuk Krisna dan Dummy002; file asli4.277 mahasiswa tetap di Downloads.

**Dukungan kode sudah tersalin:** rescan [71](../../71-rescan-krs-resmi-dan-status-bpp.md) mengonfirmasi importer resmi pada kedua repo. Pastikan migration pelebaran kelas diterapkan pada database aplikasi dan image Docker sudah diperbarui sebelum impor69-01 dengan tipe KRS. Format ini menggantikan daftar lengkap mahasiswa/periode yang diwakili sumber. Fixture67-02 tetap tersedia untuk regresi jalur datar lama.

Panduan kode/test ada pada [70](../../70-integrasi-krs-resmi-per-mahasiswa-dan-periode.md); hasil baca sumber historis ada pada [69](../../69-contoh-krs-resmi-dan-json-demo.md). Contoh KRS resmi sudah diterima. Periode BPP kemudian tersedia pada72 dan tetap tidak menjadi blocker alur TA saat ini.

## Data manual terbaru —checkpoint67

Empat JSON67 berikut dibuat untuk tiga akun login Docker yang sudah disiapkan: superadmin@example.test sebagai pengimpor, krisna@mahasiswa.test/NIM1202200001, dan dummy-ta-2@mahasiswa.test/NIM1202299002. Superadmin bukan mahasiswa, sehingga payload memuat dua profil mahasiswa. Semua data sintetis; tidak memuat password atau membuat User/role.

Impor melalui IT Panel →Sinkronisasi Data dalam urutan:

1. `67-01-mahasiswa-akun-demo.json` —tipe Mahasiswa. Menyamakan nama Student dummy dengan profil di file berikutnya.
2. `67-02-krs-akun-demo.json` —tipe KRS. Memakai 2026/2027 GANJIL; pastikan tepat satu periode akademik aktif yang cocok sebelum impor. Krisna mengambil TUGAS AKHIR; Dummy002 TUGAS AKHIR2.
3. `67-03-profil-akademik-akun-demo.json` —tipe Profil Akademik. Krisna 470/jumlah 1/automatic, Dummy002 449/jumlah 3/Surat Permakluman setelah kode67 diterapkan. Lima syarat akademik memenuhi; BPP sintetis LUNAS berkonteks 2627/1.
4. Opsional `67-04-profil-ecct-dummy002.json` —tipe Profil Akademik; hanya menu bahasa Dummy002. Nilai null, jumlah3, jenis Online ECCT. Mode waiver setelah kode67. Pulihkan dengan mengimpor file67-03 lagi.

Pakai berkas yang sama dari browser laptop saat menguji situs Docker. Tidak perlu menaruh docs ke dalam image. Panduan lengkap, diagnosis ModelNotFound, command salin ke folder original, dan batas checkpoint: [67](../../67-fakta-resmi-blocking-dan-data-demo.md).

JSON manual ini boleh tetap lokal sesuai pilihan pengguna. Test baru67 membangun payload sintetis sendiri, sehingga tidak bergantung pada empat berkas manual yang tidak di-commit. Jika ingin menyesuaikan periode untuk uji manual, gunakan salinan di subfolder local/; jangan menyimpan file impor pengguna di /tmp.

## Fixture lama untuk regresi

`01-mahasiswa-dummy.json`, `02-krs-ta2-dummy.json`, `03-profil-akademik-dummy.json` tetap dipertahankan karena test sebelumnya masih memakainya. Empat NIM1202299001–1202299004, format nested, petunjuk historis [53](../../53-data-dummy-sinkronisasi-sidang-ta.md). Jangan menimpa file lama untuk menyamakan dengan format baru atau menggunakannya untuk memperbarui profil resmi yang sudah memakai67.

`CONTOH_JSON_LENGKAP_SEMUA_MENU_v6_9.json` adalah contoh awal kontrak, bukan payload akun demo. Keputusan terkini memakai persetujuan_maju_sidang, nilai_bahasa, jumlah_tes_bahasa, dan status_skpi sebagaimana65–67. Tiga ekspor nyata mahasiswa tetap berada di Downloads dan tidak disalin ke fixture publik.
