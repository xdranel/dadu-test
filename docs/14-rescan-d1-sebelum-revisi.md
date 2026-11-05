# 14 — Rescan D1 sebelum revisi

Pengguna melaporkan seluruh test D1 berhasil. Asisten melakukan scan read-only dan pemeriksaan terpisah terhadap folder asli serta dadu-test, tanpa menjalankan suite, migrasi, atau perubahan database.

## Hasil

- Source D1 cocok pada kedua folder: request whitelist, gate tahap, lock owner/NIM/version, kompensasi file baru, dan pemeliharaan file lama sesuai panduan 13.
- Model dokumen final sudah bersih dari relasi draft yang salah. Koreksi key source_registration_id dan typo student sudah diterapkan; titik akhir pesan hanya perapian opsional.
- Regresi C2 dan test skip tahap telah diperbarui dengan benar.
- **Belum tersalin:** `tests/Feature/SidangTaDraftUploadTest.php` hanya ada di `/home/xdranel/Code/Project/php-laravel/dadu/tests/Feature/`. Salin file ini juga ke dadu-test saat menyamakan workspace. Jangan membuat file test kosong hanya agar path ditemukan.
- File `database/database.sqlite` di dadu-test tidak dipakai untuk MySQL Podman.

Test kegagalan storage di folder asli sudah menguji file pertama sukses lalu file kedua gagal, dan memastikan metadata/version/file lama tidak berubah serta file baru dibersihkan. Tidak ditemukan blocker baru pada D1 draft baru. Ini review statis, bukan pembuktian ulang hasil test.

## Hal yang harus berubah pada D2

D1 sengaja melarang source_registration_id dan metadata source_document_id. Jangan hanya menghapus larangannya: penggantian file revisi harus memutus referensi source pada metadata draft **tanpa menghapus file final**, sedangkan penghapusan draft hanya menghapus file yang benar-benar milik draft.

Review petugas lama belum menaikkan lock_version. D2 menambahkan guard versi minimum agar perubahan keputusan dapat terdeteksi oleh draft. Ini bukan pengembangan UI workflow petugas secara menyeluruh.

Lanjutkan [15 — draft revisi dan discard](15-checkpoint-d2-draft-revisi.md). Perubahan phpunit.xml tetap lokal sementara dan dipulihkan pengguna sebelum commit/push. Semua tulisan asisten tetap hanya Markdown.
