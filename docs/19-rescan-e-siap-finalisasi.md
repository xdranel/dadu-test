# 19 — Rescan E setelah hasil Tinker terbaru

Pengguna mengonfirmasi semua test E/regresi berjalan dan sudah menyalin source. Scan read-only membandingkan kedua folder: source app/routes/resources/database/tests/config/bootstrap cocok. phpunit.xml berbeda sesuai rencana: dadu-test memakai konfigurasi awal, folder asli masih memakai MySQL Podman dadu_testing. Jangan memulihkannya otomatis atau menganggap perbedaan ini bug.

Sudah ditemukan pada source: perbaikan query step kosong, 14 test wizard, hidden old version, preview snapshot di bawah lock, registrasi Alpine, serta discard stale. Tidak ditemukan blocker baru sebelum F. Asisten tidak menjalankan ulang suite, bootstrap aplikasi atau query DB.

## Bukti terbaru menggantikan screenshot lama

Tinker yang dikirim pengguna menunjukkan timezone runtime **UTC**, waktu `2026-11-01 17:19:46 +00:00`, database aplikasi **dadu**, serta dua periode is_open=true dan query ID `[1,2]`.

Tanggal tersimpan terbaru berbeda dari screenshot sebelumnya: periode 1 dibuka 24 Oktober dan periode 2 dibuka 31 Oktober. Karena itu penjelasan periode 2 masih menunggu 6 November hanya menjelaskan screenshot lama, **tidak berlaku lagi pada data terbaru**. Pada nilai Tinker ini dropdown pendaftaran baru semestinya berisi dua opsi. Revisi tetap mengunci periode asal.

Source config/app.php memiliki default Asia/Jakarta, tetapi default source tidak membuktikan nilai runtime: environment/cache/proses yang dijalankan dapat berbeda. UTC `1 November 17.19` adalah waktu yang sama dengan `2 November 00.19` WIB. Jangan mengganti timezone atau menggeser semua tanggal secara otomatis; tanggal DATETIME existing sudah ditafsirkan menurut konfigurasi yang sedang dipakai. Bila kebijakan kampus nanti harus WIB, rencanakan konsistensi input/tampilan/data existing tersendiri.

## Siap dikerjakan berikutnya

Pipeline store/update final lama masih menerima kontak/file langsung dan memilih satu active period. F harus menggantinya, bukan menambahkan jalur submit kedua. Semua metadata pengajuan berasal dari draft/Student server; final request hanya membawa draft_id, version dan action=submit.

Lanjutkan [20 — finalisasi draft](20-checkpoint-f-finalisasi-draft.md). Visual dan desain tetap ditunda sampai fungsi student lengkap. Konfigurasi testing tetap MySQL Podman; command berikutnya menyebut dadu_testing secara eksplisit agar tidak mereset database aplikasi dadu/dadu_test.
