# Form Yudisium Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Menerapkan kebutuhan Yudisium pada 91/99 dengan data lama tetap dapat dibaca.

**Architecture:** Alur draft/versi/snapshot baru memakai model pengajuan Yudisium yang sudah ada. Periode dan panduan berdiri sendiri; admin review/export menggunakan filter bersama.

**Tech Stack:** Laravel yang terpasang, Blade/Alpine/Tailwind, MySQL dadu_testing serial, FastExcel; tanpa update dependency.

**Spec:** docs/99-rancangan-form-yudisium.md dan keputusan pengguna pada docs/91-catatan-kebutuhan-form-yudisium.md.

## Global Constraints

- Jangan ubah SSO, reset DB aplikasi, commit/push/deploy, atau menghapus data historis.
- Implementasi di worktree /home/xdranel/.codex/worktrees/8bb0/dadu-test; integrasi source/development sesudah guard+backup dan verifikasi.
- Hanya primer menjalankan DB tests, serial pada dadu_testing. Agent implementer lain tidak menjalankan tests DB bersamaan.
- Ikuti catatan meeting; aturan belum jelas masuk .md. Satu kriteria prestasi sementara. Plagiasi input 0–100; belum ada batas penolakan otomatis yang dikarang.

## Review Focus

Legacy periode berupa nama tidak boleh ditebak menjadi ID; berkas arsip tidak boleh dianggap dokumen baru; submitted snapshot baru tidak ikut tertimpa sinkronisasi; revisi tidak boleh mengganti dokumen verified; admin/mahasiswa berbeda tidak boleh membaca berkas yang bukan wewenangnya.

## Task 1 — Periode dan fakta akademik

- [x] Tambahkan jadwal revisi/checkbox periode pada GraduationPeriod, controller admin, migration000001, view kelola terpisah. Route dan link dipasang primer.
- [x] Buat GraduationAdmissionFacts dengan interface pada99; jangan mengubah TA gates. Uji threshold, data resmi/legacy, null, waiver, daftar blockers.

## Task 2 — Panduan Form Yudisium

- [x] Model/table migration000002, service, controller/request, view CRUD/partial, permission terpisah. Route/nav dipasang primer.
- [x] Service activeForSlots(array); slot mengikuti99. Uji permission/storage/tautan dan tampilan mahasiswa.

## Task 3 — Draft dan tiga tahap mahasiswa

- [x] Migration000003 menambah versi/payload/snapshot/periodFK/storage disk dan tabel draft+draft documents. Pengajuan lama versi1, alur baru versi2.
- [x] Model/service/request/controller draft/finalization dengan owner, lock/version, periode, scalar/document rules, dan atomisitas file/DB.
- [x] Tiga tahap + panel statis + tinjau/deklarasi + revisi dokumen terarah. Arahkan route create baru; route pengajuan lama tetap kompatibel.
- [x] Uji RED→GREEN data tersimpan, submit, fakta berubah, pengajuan lama/baru, berkas gagal, stale version dan akses.

## Task 4 — Review admin, ekspor, jadwal

- [x] Query/filter bersama, review keputusan eksplisit, angka artikel editable + audit, metadata per dokumen, tautan ekspor.
- [x] Cleanup/expiry memakai jadwal masing-masing dan tidak menyentuh final files yang masih dipakai.
- [x] Sinkronisasi melewati snapshot pengajuan versi2 yang sudah dikirim.
- [x] Uji permission, revision completeness, snapshot, exports/filters, expiry, legacy read.

## Task 5 — Integrasi dan penutupan

- [x] Review independen, gate terkait, suite penuh dibanding baseline, JS/build/lint/Blade.
- [x] Catat keputusan sementara/pertanyaan divisi, hasil tes, langkah lokal/Docker dan command copy; integrasi kedua folder dengan guard+backup.

## Ledger

11 November: pemetaan read-only selesai; pengguna meminta aturan tidak jelas dicatat, satu kriteria sementara, artikel dapat dikoreksi petugas. Belum ada migrasi/kode produk diterapkan pada saat rencana ditulis.


11 November — Task1: RED route/model/facts belum ada → GREEN36 tes fondasi gabungan (periode7, facts7, panduan22),267 assertions pada dadu_testing serial.
11 November — Task2: CRUD panduan/perizinan/storage/tautan dan media privat lulus bersama gate fondasi. Menu ditambahkan melalui permission registry.
11 November — Task3: RED draft404, lalu GREEN6 tes alur dasar; renderUI, kriteria prestasi, rollback, snapshot, dan revisi kondisional diperluas. File upload baru disimpan private dengan metadata disk; legacy public dibaca sesuai jalur lama.
11 November — Task4: GREEN review/export16 tes; cleanup7; expiry5 (fixture balapan jadwal diperbaiki dengan refresh sebelum update, tanpa mengubah aturan produk).
Ruling: Persentase plagiasi 0–100 tanpa ambang blokir otomatis — belum ada batas resmi; petugas menilai/koreksi dengan alasan — jika divisi menetapkan batas perlu penyesuaian kebijakan.
Ruling: IPK 2,00 sementara; satu kriteria prestasi; KRS TA2 dan MK mengulang bukan blocker tambahan — mengikuti catatan/persetujuan pengguna — aturan dapat berubah setelah konfirmasi divisi.
Ruling: Bukti presentasi konferensi boleh PDF/JPG/PNG10 MB — catatan meminta sertifikat/screenshot — bila divisi hanya menerimaPDF, format itu perlu dipersempit.
Ruling: Pengajuan legacy tetap versi1, route POST lama dialihkan ke wizard untuk pengajuan baru — mencegah bypass persyaratan baru — klien POST lama harus berpindah alur.
Final review independen: dua Important, tanpa Critical. Nilai awal mahasiswa dan koreksi petugas kini dipisahkan saat revisi berkas lain; UI penghapusan draft revisi basi ditambahkan. Kedua masalah direproduksi RED sebelum perbaikan; gate gabungan berjalan. Tidak ada Minor yang ditangguhkan.

11 November — Gate Yudisium gabungan:96 tes lulus,701 assertions (85 tes alur baru +11 tes kompatibilitas yang disesuaikan). JS24/24 lulus, buildVite dan Blade cache berhasil. Regresi dua temuan review telah RED→GREEN. Suite penuh final dan integrasi guard masih berjalan.

11 November — Final: dua temuan Important diperbaiki lewat tes RED→GREEN; gate96/96 dan suite penuh1015pass/35fail (sisa headerfail seluruhnya baseline),24JS lulus, build/Blade/lint berhasil. Kegagalan suite penuh yang tersisa tercatat pada101 dan bukan dinyatakan lulus.
11 November — Integrasi61 file ke development dan repoDocker laptop selesai dengan guardhash+backup; empat file bersama diterapkan sebagai patch, menjaga perubahan PortalDokumen/Antrian branchdevelopment. Gate ulang development96/96,701assertions. Tidak ada commit, merge, push, deploy atau migrasi DB aplikasi.
