# Meeting mahasiswa, petugas, dan IT Panel — implementation plan77

> Eksekusi memakai subagent-driven-development, tugas berurutan dengan review per tugas. Pengguna telah menyetujui rancangan76 dan meminta implementasi dilanjutkan; tidak meminta persetujuan lagi antar tugas.

**Hasil aktual 7 November 2026:** Task 1–5 telah selesai pada lingkup implementasi, audit independen dan integrasi berkas fitur. Kode `a2684f5396f2d1482769d61171768e0c95829ab6` telah direview akhir; temuan contoh panduan kosong ditutup melalui RED→GREEN dan review ulang tanpa temuan terbuka. Agen utama menyalin 77 berkas fitur ke shared dan development asli dengan SHA256 cocok serta rescan kode/berkas terlindungi. Hasil sebelumnya tetap: lint 71 PHP/Blade bersih, 18 test Node lulus, build sukses, suite penuh 741 lulus dan 30 kegagalan baseline identik; gate perbaikan teks terakhir 20 lulus/174 assertion, tanpa pengulangan suite penuh sesudahnya. Sinkronisasi tiga dokumen status akhir dan rescan hash setelahnya ditangani agen utama. Belum ada migration/initializer DB aplikasi, merge/push/deploy atau uji browser/server manual. Panduan mandiri dan hasil lengkap: [78](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md).

**Goal:** menerapkan seluruh rancangan76 yang disetujui, termasuk EPRT terbaru dua arah pada revisi.
**Architecture:** pertahankan registry dan layanan draft yang ada; satukan penyedia fakta bahasa dan aturan revisi; tambahkan modul panduan form yang terpisah dari dokumen mahasiswa. UI menggunakan Blade/Alpine yang sudah tersedia.
**Tech Stack:** PHP ^8.4.27, Laravel13, Pest4, MySQL, Alpine3, Node20+, Vite6.
**Spec:** [76](76-rancangan-meeting-mahasiswa-petugas-it-panel.md).

## Global Constraints

- SSO/Auth/LoginRequest/SatuService dan pembuatan Student saat SSO berada di luar scope.
- Tidak fresh/seed database aplikasi, mengubah akun seeder, push, atau deploy ke server.
- Identitas read-only; WA mahasiswa/orang tua wajib string digit0–9 panjang8–15, nol depan dipertahankan; WA dapat diperbaiki dalam revisi.
- Tahap2: ta_data_capture, online_supervision_capture, ta_supervisor_sk, thesis_draft; unggahan baru PDF10MB maksimum. Tahap3: graduation_payment_proof dan eprt_waiver_letter kondisional.
- EPRT450 memenuhi; skor sah tertinggi dipakai, tidak maksimum lintas skala; jumlah tes dari sumber. Revisi mengikuti EPRT terbaru dua arah.
- PDM false mewajibkan tanggal pengajuan <=hari ini; consent wajib server sebelum kirim awal/revisi.
- Hapus seluruh draft active yang period.close_at < now, termasuk revisi; consumed receipt dan seluruh file final tetap ada.
- Riwayat mahasiswa hanya submitted, needs_review, approved, resubmitted dan hanya miliknya; petugas mempertahankan audit lengkap.
- CRUD panduan hanya superadmin/Akademik; gambar/PDF bantuan disimpan terpisah di storage persisten dan dibaca lewat route berizin.
- Workspace utama memiliki perubahan Dockerfile/64/75 sebelumnya; jangan menimpanya. Kerjakan fitur pada checkout terpisah, kemudian integrasikan hanya berkas tugas.
- Uji DB hanya dadu_testing. Jangan jalankan tes database secara bersamaan antar agen.

## Review Focus

1. EPRT berubah automatic↔waiver saat revisi, perubahan terjadi sesudah tinjau, dan surat bukan target penolakan awal.
2. Cleanup bersamaan finalisasi, dokumen borrowed, consumed receipt, serta perpanjangan close_at.
3. Request buatan melewati filter WA/consent/tipe berkas serta ownership endpoint riwayat/media.
4. Riwayat tes tanpa tanggal; skor expired/subscore/jenis berbeda; aggregate440 dengan history470 yang sah.
5. Pengajuan lama tanpa PDM date/consent, file gambar historis, serta verified file yang tidak direvisi.

## Task 1: kontrak empat tahap, WA, PDM, consent, dan cleanup

**Files:** Support/SidangTaMeetRequirements.php, SidangTaDocumentRequirements.php, SidangTaRevisionContract.php; Requests/SaveSidangTaDraftStepRequest.php, StoreSidangTaRegistrationRequest.php; Services/SidangTaDraftService.php, SidangTaRevisionDraftService.php, SidangTaDraftFinalizer.php; Models/SidangTaRegistration.php; migration kolom pdm_submission_date dan declaration_accepted_at/declaration_version; command/service cleanup baru dan routes/console.php. Test baru Feature/SidangTaMeetingContractTest.php, Feature/SidangTaExpiredDraftCleanupTest.php dan penyesuaian helpers test lama yang berkontrak berubah.

**Interfaces:** scalarRules/draftScalarRules menerima payload opsional untuk PDM kondisional; typesForStep tetap mengembalikan array kode dokumen. Field pdm_submission_date ada pada draft payload/final model. Final request memakai declaration_accepted=true; versi pernyataan konstanta1. Cleanup service run(bool $dryRun=false): array ringkasan; command sidang-ta:purge-expired-drafts --dry-run.

- [x] RED: WA alphabet ditolak dengan pesan field; nol depan valid; tahap2 empatPDF; PDM false tanpa tanggal tidak lengkap; consent hilang menolak submit; revisi boleh perbaiki WA/tanggalPDM tetapi tidak mengganti identitas/periode/cabang. Test cleanup close_at boundary, initial/revision, borrowed final, consumed receipt, future period dan idempotence.
- [x] Jalankan hanya test baru pada dadu_testing dan buktikan kegagalan sesuai aturan baru.
- [x] GREEN: implementasi minimal. PDM date nullable pada histori tetapi wajib di draft/revisi false; consent tersimpan saat finalisasi; cleanup lock Student→draft→source→period, recheck active, hapus hanya file orphan setelah commit. Tambah scheduler per menit. Jangan mengubah policy EPRT task2.
- [x] Verifikasi test baru, Unit/SidangTaMeetRequirementsTest.php dan test finalisasi/revisi yang terpengaruh; perbarui payload fixture test konsisten, bukan melemahkan assertion. Commit hanya perubahan task pada branch kerja.

## Task 2: fakta bahasa dan EPRT revisi dua arah

**Files:** Services/SidangTaAdmissionFacts.php, SidangTaAcademicSnapshot.php; Support/SidangTaLanguageHistory.php baru; Support/SidangTaRevisionContract.php, SidangTaDocumentRequirements.php; Services/SidangTaDraftDocumentService.php, SidangTaDraftFinalizer.php; Controllers/SidangTaDraftController.php, SidangTaRegistrationController.php; test Unit/SidangTaLanguageHistoryTest.php dan Feature/SidangTaMeetingLanguageTest.php.

**Interfaces:** eprt_facts ditambah history(array baris display), type, score, confirmed_attempts. Tiap baris history memiliki type, score, test_date nullable, expires_at nullable, validity (valid/expired/unknown), source fields bila perlu. SidangTaRevisionContract::effectiveTypes()/effectiveMode() berdasarkan fakta saat ini; mempertahankan signature publik lama sebagai wrapper bila dipakai. Snapshot baru pada submit mencatat fakta saat submit.

- [x] RED:449/450/451; tertinggi470 vs aggregate440; expired510 diabaikan; subscore900 diabaikan; tanggal tes tidak direka dari kedaluwarsa; ECCT/null dan jenis tes lain tidak dikonversi. Revisi waiver→automatic menghilangkan surat dan automatic→waiver membuka unggah surat baru; unknown/retest menahan kirim. Verified unrelated file tetap, rejected waiver tetap butuh pengganti, source snapshot tidak berubah sebelum submit.
- [x] Jalankan test baru, lihat RED; implementasikan satu pembaca fakta/riwayat dan satu effective requirement set untuk request/progress/wizard/review/finalisasi. Jumlah tes tidak dihitung dari panjang riwayat.
- [x] Cek ulang fakta sebelum submit; hilangkan snapshot-frozen EPRT hanya pada draft/revisi aktif, bukan mutasi histori karena import. Revisi boleh menambah surat baru tanpa membuka dokumen verified lain.
- [x] Jalankan test bahasa + official admission + targeted revision/finalization; commit task.

## Task 3: CRUD panduan form IT Panel

**Files:** model/migration SidangTaFormGuide; Support/SidangTaFormGuideSlots.php; Services/SidangTaFormGuideService.php; controller/request admin baru; views/sidang_ta_guides/{index,form}.blade.php; command initialize guides; routes/web.php, config/sidebar.php, config/permission_modules.php dan permission seeder terkait; Feature/SidangTaFormGuideTest.php.

**Interfaces:** slot kode dokumen ditambah diploma_biodata (contoh dummy ijazah). Service activeForSlots(): collection/group; view guide partial menerima guides keyed slot. Resource CRUD hanya role Akademik, read media authenticated permission sidang_ta_registration_read|sidang_ta_registration_review atau Akademik. Admin fields slot,title,description,file optional jpg/jpeg/png/pdf max10MB,link_label,url nullable HTTP/HTTPS max2048,sort_order,is_active. Content default command sidang-ta:initialize-form-guides idempotent dua gambar aktual + linktree template/PDM; tidak seed akun.

- [x] RED: role nonAkademik tidakCRUD; URL javascript ditolak; upload valid persistent, replacement aman, inactive tidak ditampilkan, media permission, slot salah ditolak; initializer tidak membuat User dan tidak menimpa edit admin.
- [x] GREEN: CRUD dan pengelolaan file transactional dengan cleanup rollback/after commit; deskripsi escaped; contoh terpisah dari uploaded student. Tidak mengubah modul Dokumen umum.
- [x] Jalankan test guide dan permission/sidebar terkait; commit task.

## Task 4: wizard, tinjau, revisi, dan riwayat mahasiswa/petugas

**Files:** views/sidang_ta_registrations/wizard.blade.php, wizard-review.blade.php, index.blade.php, revision-start.blade.php, show.blade.php, admin views/partials; partials/wizard-academic, meet-admission-summary, wizard-upload, content-guide, language-history baru; Controllers/SidangTaRegistrationController.php dan DraftController; Services/SidangTaHistoryPresenter.php baru; Models/SidangTaRegistrationLog.php; resources/js/sidang-ta*.js, routes/web.php; Feature/SidangTaMeetingUiTest.php, Feature/SidangTaHistoryTest.php; tests/Js terkait.

**Interfaces:** template menggunakan fakta/required types task1–2, guides task3. History GET endpoint berizin menghasilkan hanya event whitelist mahasiswa; payload actor name/time/label/notes tanpa audit dokumen. GET registration.show needs_review milik mahasiswa redirect ke edit/recap tanpa membuat draft.

- [x] RED: title tahap dan urutan empatPDF; label WA*, required period/date/checkbox errors; readonly identitas; pembimbing1/2; tanpa kartu otomatis duplikat; pemeriksaan nilai nyata warna merah/hijau termasukEPRT; history statusmasa berlaku tanpa tanggal palsu; PDF accept danJSmenolakgambar/karakterWA; datePDMkondisional; consent modal wajib.
- [x] RED: sidebar/dashboard menuju route sama, revisi langsungrecap; tombol Detail Revisi dan Riwayat; timeline whitelist/ownership/actor/tanggalWIB; petugas fullaudit dan kelompok dokumenbaru.
- [x] RED tambahan pengguna: Review Sidang TA mencontoh Review Yudisium, header hanya judul, Ekspor Excel di kartu filter, Kelola Periode di kartu periode, countdown/status untuk jadwal periode ditampilkan; tanpa periode tidak membuat timer palsu. Export mempertahankan filters dan permission.
- [x] GREEN: gunakan native dialog dan previewyangada, hubungkan bantuan dari DB; nilaiBPP resmi/statusperiode; jangan mengubah gateBPP/SSO. Tidak menampilkan data identik dua kali pada form/tinjau.
- [x] Jalankan test UI/histori baru, PHP UI/review/navigation existing serta Node tests. Build npm. Commit task.

## Task 5: integrasi, pemeriksaan akhir, dan panduan deploy

**Files:** tests integrasi yang diperlukan, docs/78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md; daftar berkas perubahan/command migration+initializer+update. Spec76/plan77 diberi status hasil aktual, tidak klaim manual server.

- [x] Jalankan lint seluruh PHP berubah, Node tests dan build, suite PHP penuh pada dadu_testing. Bandingkan kegagalan dengan baseline; perbaiki regresi fitur, catat kegagalan lama menurut nama.
- [x] Audit independen seluruh diff terhadap spec dan lima Review Focus. Tindak lanjuti temuan material dengan reproduksi RED→GREEN, bukan hanya asumsi.
- [x] Verifikasi tidak ada perubahan SSO/auth, akun seeder, JSON privat, atau operasi DB aplikasi. Cleanup scheduler diuji isolated; tidak menjalankannya ke database aplikasi.
- [x] Integrasikan hanya berkas fitur ke workspace yang dibagi pengguna, pertahankan Dockerfile/64/75 yang sudah ada; salin ke development asli sesuai otorisasi sesi dengan eskalasi filesystem bila diperlukan. Tidak merge/push/deploy otomatis.
- [x] Tulis urutan migration biasa → initialize form guides → build/recreate sesuai64, contoh/manualcheck mahasiswa-petugas-superadmin, dan batas verifikasi. Berikan hasil dan commit message bila diminta.


Task 5 — hasil aktual: implementasi, audit independen dan integrasi 77 berkas fitur selesai, termasuk rescan kode serta pemeriksaan SHA256 pada kedua checkout. Warning cleanup menjelaskan pemulihan orphan manual; duplicate key consent dibersihkan; dua ekspektasi pengelompokan staff lama diperbaiki setelah reproduksi RED. Temuan audit terakhir tentang contoh kosong ditutup dengan teks `Contoh belum diatur.`, gate 20 lulus/174 assertion dan review ulang bersih. Tidak ada perubahan auth/seeder akun/JSON privat atau operasi migration/initializer pada DB aplikasi. Sinkronisasi dokumen status akhir dan rescan hash akhirnya menyusul oleh agen utama; checklist browser/server tetap belum dijalankan.
