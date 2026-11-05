# 45 — Rancangan revisi hasil pertemuan Sidang TA

Status: prioritas baru dari pengguna. Dokumen ini hasil scan dan rancangan untuk menetapkan aturan, **belum panduan kode untuk diterapkan**. Pertanyaan pemetaan data menunggu jawaban; jangan menerapkan asumsi sebagai aturan kampus. Source aplikasi tetap tidak diubah asisten. Semua implementasi selanjutnya tetap melalui docs dengan kode dan test lengkap, generator file baru, test MySQL Podman dan rescan sebelum/sesudah.

**Pembaruan 4 November 2026:** pengguna mengonfirmasi KRS **TUGAS AKHIR 2**. Data resmi SKPI, persetujuan sidang, riwayat EPRT, dan TAK masih menunggu konfirmasi. Pengguna mengizinkan JSON dummy; [53](53-data-dummy-sinkronisasi-sidang-ta.md) menyediakan tiga file yang didukung importer. Persetujuan sidang tetap menunggu dukungan penyimpanan, bukan disamakan dengan foto BASILA.

Checkpoint 46–60 sudah dilaporkan lulus unit/manual dan file dipindahkan. Rescan sebelum 61 memastikan 14 file checkpoint 60 dan seluruh 491 file source yang dibandingkan identik antarfolder. Revisi terarah versi 2 dan deadline/penolakan otomatis sudah tersedia. [61](61-aktivasi-pendaftaran-sidang-ta-versi-dua.md) serta [61a](61a-penyesuaian-test-aktivasi-sidang-ta.md) menyiapkan aktivasi default versi 2 pada endpoint baru dan upgrade eksplisit draft awal 1 dengan konfirmasi, tanpa mengubah pengajuan final/revisi/replay historis 1. Draft awal 1 aktif tidak boleh finalisasi sebelum upgrade; data/file yang masih sesuai dipertahankan. Panduan mencakup 53 kasus baru, penyesuaian 10 file test, CLI salin 19 file; belum dilaporkan diterapkan. Tidak ada migration/import JSON baru atau perubahan PHPUnit. Setelah 61 lulus, lakukan audit penutup sebelum commit. JSON resmi yang belum tersedia tetap menunggu konfirmasi.

## 1. Prioritas dan hubungan dengan panduan lama

Revisi meet menggantikan bagian yang bertentangan pada31–44. Tetap gunakan ownership/permission, private preview, optimistic version, server draft parsial, period selection dan keamanan borrowed files. Target185test sebelumnya hanya baseline, bukan target final sesudah perubahan requirements.

Yang wajib diganti: enam capture/sevenPDF statis, semua dokumen resetpending saat revisi, rejected doc langsung needs_review, review tetap bisaubah needs_review, snapshot/panel akademik lama, log ditampilkan mahasiswa. Jangan terus menjalankan kode lama untuk perilaku yang sengaja berubah atau menghapus assertion tanpa pengganti yang memeriksa aturan baru.

## 2. Bukti JSON dan jalur sinkronisasi

Folder scan: `/home/xdranel/Code/Project/secret/ssc-final-project-test/Dadu JSON BACKUP/`.

- rekap_igracias_20260818093822.json: root data142record. sidang_tingkat_2 seluruhnya Y. Importer StudentAcademicProfileImportService::toBool mengenali y/yes/true/1/passed/lulus/approved/disetujui. Gunakan boolean hasil import, bukan keberadaan string (N tidak boleh menjadi true).
- sks_lulus ada, contoh agregat146 sebanyak70record; gunakan SKS lulus, bukan SKS ditempuh/diambil.
- nilai_bahasa,total_tak,status_keuangan null pada142record. Null bukan nol dan bukan lulus; data ini sendiri belum cukup untuk eligibility lengkap.
- Tidak ada field SKPI, approval sidang iGracias, atau riwayat3percobaan EPRT pada rekap ini. Approval foto dosen wali BASILA tidak sama dengan approval sidang.
- KRS sebelasfile aaData, nama matakuliah index8. Agregat nama relevan: TUGAS AKHIR485, TUGAS AKHIR2 58, PROPOSAL TUGAS AKHIR50, TUGAS AKHIR1 33. Jangan mencari kata thesis pada seluruhrow: bisa cocok nama mahasiswa. Index8 dan kode matakuliah yang divalidasi yang dipakai.
- Importer KRS SyncController menyimpan StudentCourse dan academic_period_id. Existing ImportedCampusDataGrabService sudah membedakan TA2 pada periode akademik aktif, tanpa periode aktif/period_unlinked/other_period. Audit matcher LIKE sebelum dipakai agar tidak salah cocok TA20/semesterlama. Nama generik TUGAS AKHIR menunggu keputusan perprodi; bukan otomatis TA2.
- IT Panel mengimpor DB normalisasi. Halaman mahasiswa/submit membaca DB hasil sinkronisasi, bukan path backupJSON lokal runtime. Jangan mengubah data dummy agar seluruhnya lulus sebagai pengganti sinkronisasi yang benar.

## 3. Eligibility dan sumber data

Lima pemeriksaan wajib saat **Kirim Pendaftaran**: tingkat2lulus, KRSTA2yangsesuai, SKPI memenuhi status resmi, TAK>=60, SKSlulus>=120. Satu gagal/unknown membuat kirim ditolak server dan tombol tidakaktif; draft dan Tinjau tetap dapat diakses selama required upload/scalar selesai. Tampilkan setiap alasan secara spesifik. Cek ulang data saat submit, bukan mempercayai hasil panel/browser. Snapshot saat berhasil submit disimpan agar detail historis tidak mengikuti mutasi data terbaru. Replay submit yang sudah berhasil tetap idempotent, tidak gagal akibat sinkronisasi sesudah submit.

EPRT merupakan kebijakan dokumen tersendiri: >=450 otomatismemenuhi tanpa upload; <450 dan >=3tes membutuhkan suratpermaklumanPDF; <450 dan <3tes menampilkan Ikuti Test EPRT lagi (titik penolakannya menunggu jawaban). Skor/attempt hilang jangan direkayasa menjadi3. Hitung percobaan sah yang unik; impor ulang tidak boleh menambah jumlah tes. Maksimum skor vs skor terakhir/kedaluwarsa perlu ditetapkan sebelum kode, termasuk attempt_number bukan countbaris mentah.

SKPI: keberadaan raw_data saja tidak cukup; perlu field/status resmi dan daftar nilai yang diperbolehkan. iGraciasapproval dan BPP dibaca dari sumber tervalidasi, belum diasumsikan sebagai blocking tambahan di luar5syarat. BPP juga perlu konteks semester berjalan, bukan sembarang status keuangan lama.

## 4. Pemetaan kartu tahap

| Tahap | Data otomatis/tampilan | Upload aktif | Dihapus |
| --- | --- | --- | --- |
| 1 | Panel ringkasan diperbarui, KRS TA2 ditambah | Periode/kontak tetap sebagai scalar | Panel Pembimbing&Penguji, Jadwal; ringkasan tingkat3/BPP/iBasila/ketersediaanTAK |
| 2 | Hasil tingkat2 dan persetujuan sidang iGracias | ta_data_capture, online_supervision_capture | ta_progress_capture, skpi_tak_capture; SKPI/TAK cukup panel |
| 3 | BPP berjalan, EPRT sesuai cabang | tanggalpembayaran, graduation_payment_proof, ta_supervisor_sk, thesis_draft, suratpermakluman jika diwajibkan | transcript_khs, ta_course_ksm, uploadsertifikatEPRT lama |
| 4 | Tetap | cabang diploma_statement+diploma_biodata_form atau pdm_submission_proof | Tidak ada |

Interpretasi konflik SKPI/TAK disebut DariJSON sekaligus Hapus: datanya tetap diperiksa otomatis dan di panel, cardcapture/upload dihapus. Tanda* hanya pada kartu upload yang diwajibkan menurut permintaan; validasi tanggal/kontak/scalar tetap berlaku meski tidak diberi tanda*. Teks Lengkapi tahap ini sebelum melanjutkan tetap untuk kelengkapan upload/scalar; lima eligibility tidak mengunci tombolTinjau.

Rekomendasi gambar: `public/assets/images/forms/sidang-ta/` bila assetexisting memakai public/assets, atau subtree forms/sidang-ta pada sistemassetexisting jika berbeda. Jangan menaruh panduan TA bercampur dengan yudisium. Perlu filegambar nyata dari pengguna; jangan menulis path asset yang belum ada sebagai sudahsiap. Gambar mengganti penjelasan contoh; format/ukuranfile tetap terlihat secara ringkas demivalidasi dan aksesibilitas, altteks bermakna.

Suratpermakluman: link masked **Link Surat Permakluman** ke URL SharePoint yang diberikan pengguna; simpan sebagai konfigurasi/konstanta yang bisa dirawat. Tidak membaca/mengklaim isi file eksternal. Link ini bukan upload; mahasiswa mengunduh template lalu mengunggah suratPDFnya.

## 5. Alur review dan revisi yang menggantikan P3 lama

Pisahkan **penilaian dokumen** dan **pengiriman permintaan revisi**:

1. Dalam submitted petugas memverifikasi atau meminta perbaikan dokumen; rejected pada dokumen tidak langsung mengunci pengajuan. Verifiedcard hijau/buttonVerifikasidisabled; rejectedcardkuning/buttonMintaPerbaikanuntuknilaiidentikdisabled. Aksi lawannya tersedia sebelum MintaRevisi; pindah rejected→verified menghapus notes server. Tombol salahklik tidak boleh mematikan kesempatan koreksi.
2. MintaRevisi merupakan keputusan eksplisit. Usulan invariant: minimal satu dokumen upload rejected agar mahasiswa mempunyai daftar yang bisa diedit. Simpan daftar ID/type target revisi beserta versi/snapshot; generalnotes bolehmenjelaskan, bukan membuka semua field.
3. needs_review mengunci seluruh mutasi petugas di server, termasuk approve/reject/documentreview; preview tetap. Mahasiswa hanya boleh mengganti upload yang diminta revisi. DisabledUI saja tidak cukup: tolak forgedrequest untuk dokumen lain, perubahanperiode/kontak/cabangbiodata yang memengaruhi requiredset. Dataotomatis read-only sejak pengajuanbaru.
4. Kirimrevisi mengganti hanya targetupload, reset pending/notes sesuai targetyangdiganti. Uploadlain yang verified tetapverified/hijau; fileborrowed tidak dihapus. Jangan memakai resetsemuapending existingfinalizer. Tolak kirim jika targetbelumdiganti (rancangan untukkonfirmasi saatcheckpoint). Simpan audit; feedbackperbaikan tetap dapat dilihat saatdraft.
5. Setelah revisi submitted, petugas meninjau ulang targetpending. RequiredJSON dicek kembali sesuai kebijakanfinalsubmit. Guardversion/sourceversion serta lockorder lama tetap. Periode close dan submitsimultan harus hanya menghasilkan satu keputusan.
6. Revisi lewat close_at: command terjadwal menolak needs_review yangmelewatideadline secara idempotent, version++, logautopenolakan, tanpa hapusfile/draft. Semua jalur write juga mengecek bataswaktu agar tidak bergantung pada scheduler yang terlambat. Checkpoint 60 memakai periode `close_at` yang sama (bukan hitung ulang sejak revisi); tepat pada batas masih boleh, sesudahnya ditutup. Versi 1 tetap kompatibel. Scheduler khusus tidak menyentuh pengajuanterminal/submitteddi luar ketentuan.

## 6. Visual mahasiswa dan petugas

- Mahasiswa: hapus section RiwayatStatus dari detail; log tetapdisimpan untuk audit. Hapus pula data log dari payloadviewstudent jika takdibutuhkan komponenlain, jangan hanya CSSdisplaynone.
- Petugas: RiwayatStatus memakai native details/summary defaulttertutup, chevron berubah saatopen dan dapat keyboard. Dataaudit tetapada.
- DokumenPengajuan: grid1kolommobile/2kolomdesktop, kelompok dengan judultahap2/3/4, urutan konsisten denganwizard. Ini menafsirkan dua cardkanankiri sebagai dua kolom kartu dokumen, bukan mengunci dua giantcard lintastahap. Hasilotomatis mempunyai gayaredup/readonly, tidak ada formupload/reviewmanual.
- Snapshot Dataakademiksaatpengajuan memakai fieldyangsama denganpanelbaru. Nilai historis tidak diganti oleh sinkronisasiterbaru; fieldyang belumada di snapshotlama diberi Belumtersedia tanpa fallbacklive.
- Ikonkolomtabel iGracias&Akademik/Pembayaran&Dokumen/Biodata hanyauntukuploadwajib, sesuai rekomendasidanpreferensipengguna. Statusotomatis tetapringkasanterpisah, bukan iconhijau palsu. N/AEPRT tidak membebanihitungan. Setelahrevisi unchangedverifiedtetaphijau, targetpendingmenunggureview.
- Pertahankan anchor kartu sesudahaksi (44bagian6), status warna juga mempunyai teks, tekstarea tanpaindentasi(44bagian5), previewprivat.
- Waktuperiode pada listReviewTA tetapmengikuti cakupanreviewyudisium: buka/tutup,statusperiode,timezonekonsisten. Jangan kehilangan headerwaktu saatdesainulang.

## 7. Checkpoint implementasi yang akan ditulis sesudah pemetaan dikunci

1. **M1 kontrakdata/import:** bukti SKPI/approval/EPRT/BPP, mappingTA2perprodi, missing/null/dedup/semester, testsimportulang. Tidakmengubah makna fieldfotoBASILA.
2. **M2 requirements/eligibility:** registryuploadbaru+EPRTpolicy, required/checklist/save/review/finalize/snapshot, fivegatehanyafinalsubmit, versirequirementsuntukpengajuan/draftlama tanpa hapusdokumenhistoris. Test60/120/450/3batas, unknowndata, satugategagal, racecheck+idempotentreplay.
3. **M3 revisiterarah/reviewerlock:** penilaian dokumen tetapsubmitted, MintaRevisi eksplisit, targetededitserver+fileprotection, unchangedverifiedtetap, verifiedclearnotes, terminal/version/concurrencytests.
4. **M4 deadline:** command+scheduler/clocktest, idempotence/racewithresubmit, close_atguard dan audit.
5. **M5 UI/preview/export:** panelbaru/gambar/tahapcards/gridstatus/historycollapse/iconupload/timeheader/snapshot/exportkolomselaras, tandarequiredkondisional, testDOM danmanualmobile/desktop.
6. Rescan keduafolder, lint/snippets, suiteMySQLPodman/Node/manualkonkurensi, daftarfilepindah lalu commitmessage sesuai difffinal. Tidak commit/push otomatis.

Setiap checkpoint implementasi berikutnya wajib menyertakan kode produksi dan test lengkap, perintah make untuk filebaru dan commandsMySQLdadu_testing. Dokumen45ini belum memberikan kode yang bisa langsungditerapkan dan tidak menganggap185testlama membuktikan flowbaru.

## 8. Pertanyaan yang sedang diajukan

- TUGASAKHIR tanpa2 apakahekuivalen TA2, ataubutuhmappingperprodi/kode?
- EPRT<450/<3ditolaksaatfinalsubmit atau sejaktahap3? Rekomendasi tetapdraft/tinjau lalu tolakfinalsubmit, terpisahdari5gateakademik.
- Sumber fieldSKPI, approvalsidangiGracias, riwayatpercobaanEPRT belumada di backup. Jangan melanjutkan gatekode berdasarkan tebakan. BPPbelumada nilainya, butuhsampelvalidsemesterberjalan.

Langkahberikutnya mengunci pemetaan berdasarkanjawaban dan menulisM1; tidakmemerlukanmemindahkanfileP3yangbelumselesaisebagaisyaratuntukmerancangrevisiini.


> **Pembaruan keputusan 5 November 2026:** bagian rencana implementasi lama di bawah ini mendahului keputusan bahwa kedua deployment hanya lingkungan test dan belum dipakai pengguna. Untuk aktivasi seluruh akun, arahan v1-retention/upgrade dan checkpoint dual-version M1–M5 tidak lagi menjadi rencana aktif. Lihat [checkpoint61 satu alur](61-aktivasi-pendaftaran-sidang-ta-versi-dua.md), yang memecah kode lengkap ke [61a tests](61a-penyesuaian-test-alur-tunggal-sidang-ta.md), [61b backend/reset](61b-backend-dan-reset-data-uji-sidang-ta.md), dan [61c views](61c-view-alur-tunggal-sidang-ta.md). Hanya current marker2 yang diterima; data TA test lama dibersihkan eksplisit setelah test hijau. JSON resmi SKPI/iGracias/EPRT/TAK masih menunggu konfirmasi, jadi jangan menebak mapping. KRS `TUGAS AKHIR 2` sudah terkonfirmasi.


## Status verifikasi akhir checkpoint61

Sesudah alur current-only diterapkan dan koreksi61i selesai, log pasca-salin menunjukkan347 PHP passed/3988 assertions.503 file source di masing-masing clone identik;48 file disalin dan7 file legacy sudah dihapus. Manual TA lulus menurut pengguna; Node13 passed. Pengembangan berikutnya tidak perlu mempertahankan v1 atau membuat tombol upgrade. Data resmi JSON masih menunggu konfirmasi; Auth reset-password61f tetap pekerjaan terpisah yang belum selesai. Lihat [hasil akhir61i](61i-koreksi-test-snapshot-null-dan-debug.md) untuk bukti dan batas verifikasi.
