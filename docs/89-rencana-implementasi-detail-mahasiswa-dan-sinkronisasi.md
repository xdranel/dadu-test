# Rencana implementasi detail mahasiswa dan catatan sinkronisasi

> Pelaksanaan: primer mengimplementasikan tampilan mahasiswa; satu agen menangani bagian sinkronisasi yang terpisah. Uji database harus serial. Commit/push tetap dilakukan pengguna.

**Tujuan:** Menampilkan profil akademik resmi secara konsisten dan membuat catatan impor dapat ditelusuri berdasarkan mahasiswa serta posisi JSON.

**Arsitektur:** Pembaca tampilan mahasiswa menggunakan fakta resmi Sidang TA dan relasi akademik yang sudah tersimpan. Detail impor disimpan aditif pada log, dengan identitas dan lokasi sumber; pesan lama tetap kompatibel. Tampilan menggunakan Blade/Alpine yang sudah ada.

**Spesifikasi:** [88](88-rancangan-detail-mahasiswa-dan-catatan-sinkronisasi.md).

## Batas global

- Kehadiran baru menunggu contoh sumber; pertahankan data impor yang ada dan rangkum kebutuhannya.
- Nilai KRS aktif berarti nilai tersimpan untuk mata kuliah pada KRS aktif.
- Jangan mengubah keputusan kelayakan atau menulis database aplikasi.
- Unit/feature tests memakai dadu_testing secara serial; build dan pemeriksaan baca dapat paralel.

## Fokus review

Data resmi hilang/null tidak boleh mengambil nilai lama yang menyesatkan. Nilai nol harus terlihat. Mata kuliah ulang tidak boleh kehilangan riwayat nilainya. Modal harus aman untuk teks unggahan, berizin, dan tidak membuang catatan setelah10. Baris fisik harus sesuai berkas, termasuk string JSON yang berisi kurung/escape dan root data/aaData/list/object.

## Tugas1 — Sumber dan tampilan mahasiswa

Berkas: StudentController, layanan pembaca tampilan baru bila perlu, students/show dan partialnya; tests/Feature/StudentAcademicDetailTest.php.

- [x] Tulis regresi impor JSON → render EPRT, TAK, BPP, SKPI, persetujuan; empat bagian KRS/nilai; fallback data lama dan data resmi hilang; izin baca.
- [x] Jalankan RED dan pastikan kegagalan sesuai perubahan yang diminta.
- [x] Terapkan pembaca/tampilan, pertahankan kartu yang sudah benar dan hindari query berulang.
- [x] Jalankan GREEN, tes terkait akademik, dan build.

## Tugas2 — Detail impor dan modal

Berkas: SyncLog, SyncController, job sinkronisasi bila relevan, layanan issue/lokasi baru, migration tambahan, route detail, sync/index dan modal; tes unit lokasi serta feature catatan impor.

- [x] Tulis regresi lebih dari10 catatan, NIM/nama/path/baris fisik, record tanpa identitas, log lama, keamanan modal/izin, serta skip generik.
- [x] Jalankan RED sebelum implementasi; agen tidak menjalankan database bersama tanpa koordinasi primer.
- [x] Terapkan penyimpanan aditif dan modal yang mengambil detail saat dibuka; pertahankan pesan lama dan aturan impor parsial.
- [x] Jalankan GREEN dan tes impor akademik/KRS yang sudah ada.

## Tugas3 — Integrasi dan hasil

- [x] Review independen, perbaiki temuan material dengan regresi.
- [x] Jalankan suite lengkap, bandingkan signature kegagalan dengan baseline87, tes JS, lint, kompilasi Blade, build.
- [x] Catat kebutuhan JSON kehadiran, migration, perubahan file, command copy, dan langkah lokal/Docker pada dokumen90.
- [x] Salin hasil ke kedua repo dengan guard hash serta backup, lalu rescan. Tidak melakukan commit/push/deploy.
