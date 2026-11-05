# Fixture Sidang TA

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
