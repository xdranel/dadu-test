# 82 — Hasil rescan dan copy dadu ke dadu-test

Tanggal: 8 November 2026, Asia/Jakarta.

## 1. Lingkungan yang dibandingkan

- Sumber: `/home/xdranel/Code/Project/php-laravel/dadu`, HEAD `a286c37`.
- Tujuan: `/home/xdranel/Code/Project/secret/dadu-test`, HEAD `cffa273`.
- Perbandingan memakai SHA-256 isi berkas, bukan waktu modifikasi atau hanya status Git.

## 2. Hasil kode aplikasi

**639 berkas publik pada sumber sudah identik dengan dadu-test.** Kode aplikasi, route, migration, konfigurasi aplikasi yang dipilih pada81, view, audio, serta tes yang disalin pada tahap sebelumnya sudah tersedia. Tidak ada berkas kode aplikasi baru/berbeda yang perlu disalin pada rescan82 ini; jumlah kode aplikasi yang disalin ulang adalah0.

Termasuk perubahan hak akses panduan form Sidang TA, teks form, login/profil SSO, antrean, empat migration antrean, dan perbaikan posisi tombol/peringatan tinjau. Daftar54 berkas tahap sebelumnya tetap tercatat pada [panduan81](81-rescan-dadu-dan-command-copy.md).

## 3. Berkas yang benar-benar disalin sekarang

Folder privat `docs/fixtures/sidang-ta/data_dadu/` belum ada pada dadu-test sebelum rescan. Sebanyak **17 berkas** dari folder tersebut di development asli sudah disalin ke lokasi relatif yang sama pada dadu-test:

| Berkas, relatif terhadap `docs/fixtures/sidang-ta/data_dadu/` | Hasil |
| --- | --- |
| `README.md` | Disalin, SHA-256 identik |
| `blackbox-accounts.json` | Disalin, SHA-256 identik |
| `impor/75-01-mahasiswa-demo.json` | Disalin, SHA-256 identik |
| `impor/75-02-krs-demo-sumber.json` | Disalin, SHA-256 identik |
| `impor/75-02b-krs-demo-skenario-ta.json` | Disalin, SHA-256 identik |
| `impor/75-03-profil-akademik-demo.json` | Disalin, SHA-256 identik |
| `impor/75-04-bpp-demo.json` | Disalin, SHA-256 identik |
| `impor/75-05-bahasa-demo.json` | Disalin, SHA-256 identik |
| `impor/sinkronisasi-data-dadu-test.zip` | Disalin, SHA-256 identik |
| `manifest.json` | Disalin, SHA-256 identik |
| `sumber/MATRICS_BLACKBOX_11_MAHASISWA.json` | Disalin, SHA-256 identik |
| `sumber/SEEDER_IGRACIAS_11_MAHASISWA_BLACKBOX.json` | Disalin, SHA-256 identik |
| `sumber/rekap_igracias_2026-11-06 FINANCE UPDATE.json` | Disalin, SHA-256 identik |
| `sumber/rekap_igracias_2026-11-06 KRS.json` | Disalin, SHA-256 identik |
| `sumber/rekap_igracias_2026-11-06 UPDT TAK & SKPI.json` | Disalin, SHA-256 identik |
| `sumber/rekap_igracias_2026-11-06.json` | Disalin, SHA-256 identik |
| `sumber/rekap_igracias_2026-11-07 NILAI BAHASA UPDATE.json` | Disalin, SHA-256 identik |

Berkas sumber asli tidak diubah. ZIP disalin sebagai ZIP dan tidak diekstrak. File fixture tetap diabaikan Git; tidak menjadi bagian commit maupun image Docker. Izin direktori privat0700 dan file0600.

## 4. Empat perbedaan yang sengaja dipertahankan

| Berkas | Alasan mempertahankan versi dadu-test |
| --- | --- |
| `.gitignore` | Memuat aturan tambahan untuk `.env.docker`, backup, dan private-import |
| `.env.example` | Template lingkungan berbeda; konfigurasi aktual `.env`/`.env.docker` tidak disalin |
| `phpunit.xml` | Pengaturan database tes lokal berbeda dari repo Docker |
| `docs/fixtures/sidang-ta/README.md` | Versi tujuan menyimpan referensi fixture dan panduan historis yang lebih lengkap |

Berkas khusus Docker, Compose, skrip deployment, dan dokumen milik tujuan dipertahankan. Dependency terpasang, storage/upload, cache, hasil build, environment aktual, serta metadata Git tidak disinkronkan. Tidak ada penghapusan berkas tujuan.

## 5. Verifikasi setelah copy

- Rescan ulang: **639 berkas publik identik**, **17 berkas privat identik**, **0 perbedaan privat tersisa**.
- SHA-256 seluruh17 hasil copy sesuai sumber.
- Seluruh15 file JSON yang disalin berhasil diparse; checksum isi satu ZIP valid.
- Git memastikan seluruh berkas privat tetap diabaikan.
- Hash108 berkas khusus/perbedaan tujuan yang dilindungi tetap sama sebelum dan sesudah copy.
- Kode aplikasi tidak diubah pada tahap82; unit/feature suite tidak dijalankan ulang untuk penyalinan fixture privat ini.

Proses ini sudah menjalankan copy pada repo laptop. Tidak dilakukan commit/push, deployment server, migration, seeding, atau impor ke database aplikasi. Perubahan yang muncul pada Git dari tahap ini hanya panduan82; fixture privat tersedia secara lokal pada folder di bagian3.
