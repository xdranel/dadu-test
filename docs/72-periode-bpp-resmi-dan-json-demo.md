# 72 — Periode BPP resmi sudah tersedia

Pengguna memberikan `/home/xdranel/Downloads/data_dadu/rekap_igracias_2026-11-06 FINANCE UPDATE.json` dan mengonfirmasi kode2627/1 sebagai Ganjil. Berkas dibaca saja, tanpa impor database atau salinan data pribadi ke repo. Dokumentasi ini melengkapi informasi sumber yang masih terbuka pada65–71; belum memasang perubahan kode BPP.

## 1. Kontrak dari contoh terbaru

| Bagian | Temuan |
| --- | --- |
| Wrapper | `generated_at`, `source`, `total`, `selected_menus`, `data` |
| Menu | `selected_menus = ["keuangan"]` |
| Jumlah baris | 277; sesuai total deklarasi |
| Field per mahasiswa | `no`, `nim`, `nama`, `semester`, `periode_keuangan`, `status_keuangan` |
| Periode BPP | Seluruh277 baris memakai string `periode_keuangan = "2627/1"` |
| Status BPP | 250 LUNAS,27 KREDIT |
| Error sumber | Tidak ada baris error |
| Semester studi | String7/9/11/13/15; tetap terpisah dari periode pembayaran |

**2627/1 berarti BPP tahun akademik2026/2027 Ganjil; kode /2 berarti Genap.** Konteks pembayaran sekarang berasal dari field resmi tersendiri pada setiap mahasiswa, sehingga tidak perlu menyimpulkannya dari semester studi, tanggal ekspor, atau periode KRS.

Contoh ini seragam pada satu periode, tetapi pemetaan perlu membaca field per baris. Jangan mengunci kode pada2627/1 atau menganggap seluruh ekspor mendatang selalu mempunyai satu periode yang sama.

Dengan diterimanya contoh ini, **contoh sumber KRS dan kejelasan periode BPP yang ditunggu sudah tersedia**. Ketersediaan sumber dibedakan dari kesiapan implementasi importer/tampilan.

## 2. Hasil pemeriksaan kode sekarang

`AcademicProfileJsonNormalizer::bpp` saat ini mengenali:

- `finance_period` dari konteks wrapper yang diteruskan controller.
- Kode periode lama bila benar-benar terdapat pada field `semester`, untuk kompatibilitas sebelumnya.

Method tersebut **belum membaca `periode_keuangan`**. Pada contoh baru tidak ada wrapper finance_period dan semester hanya berisi semester studi.

Probe normalizer aktual pada277 baris, memakai selected_menus dari sumber, menghasilkan:

| Hasil | Jumlah |
| --- | --- |
| Update grup BPP | 277 |
| Period masih null | 277 |
| Period terkonfirmasi | 0 |
| Grup dilewati | 0 |

Ini menunjukkan status LUNAS/KREDIT sudah terbaca tetapi kode periode baru masih diabaikan. Jangan menganggap periode sudah tersimpan hanya karena log impor menyatakan berhasil.

## 3. Pemetaan yang diperlukan berikutnya

Penyesuaian perlu membuat `periode_keuangan` menjadi sumber periode BPP resmi pada baris. `semester` tetap menyimpan konteks semester studi sebagai source_semester; nilai7 tidak boleh menjadi periode pembayaran.

Syarat yang perlu dipertahankan saat implementasi:

- Field resmi valid seperti2627/1 disimpan pada `groups.bpp.period` dan kunci `bpp_periods["2627/1"]`.
- KREDIT→LUNAS pada periode yang sama memperbarui entri periode tersebut. Riwayat periode lain tetap dipertahankan.
- Jika ada sumber periode ganda, misalnya wrapper finance_period dan periode_keuangan, keduanya harus konsisten. Konflik atau field periode resmi malformed melewati grup BPP dengan catatan, tanpa menimpa data BPP terakhir yang valid.
- Field resmi yang tidak ada pada ekspor lama tetap mengikuti kompatibilitas lama; jangan menebak dari periode aktif atau tanggal.
- Impor keuangan saja mempertahankan grup akademik/SKPI/TAK/bahasa yang sebelumnya sudah tersedia, serta tidak mengubah KRS.

Penyimpanan yang dibutuhkan sudah tersedia pada StudentAcademicProfile.groups dan bpp_periods; tidak diperlukan tabel baru hanya untuk membaca nama field ini. Pembaca tampilan/snapshot tetap perlu diselaraskan agar memakai periode terkonfirmasi dan tidak selalu mencetak keterangan belum terkonfirmasi.

Panduan penyesuaian pembacaan/penyimpanan dan test tersedia pada [73](73-penyesuaian-periode-keuangan-bpp.md). Pengguna menerapkannya pada tiga file, menjalankan test, lalu menyalin untuk rescan. Penulisan panduan belum mengubah normalizer source atau tampilan aplikasi.

## 4. Dua JSON sintetis yang sudah dibuat

| File | Tujuan setelah pemetaan dipasang |
| --- | --- |
| [72-01-bpp-periode-akun-demo.json](fixtures/sidang-ta/72-01-bpp-periode-akun-demo.json) | Krisna LUNAS, Dummy002 KREDIT, keduanya2627/1 |
| [72-02-bpp-lunas-dummy002.json](fixtures/sidang-ta/72-02-bpp-lunas-dummy002.json) | Mengubah Dummy002 menjadi LUNAS pada periode2627/1 yang sama |

Wrapper dan field mengikuti contoh resmi, tanpa menambahkan finance_period buatan pada wrapper agar kekurangan pemetaan tidak tertutup. Timestamp/identitas/status per akun adalah skenario sintetis. Kedua file valid JSON dan NIM/nama sesuai fixture67-01.

**Belum untuk menguji keberhasilan periode dengan kode sekarang:** gunakan setelah penyesuaian periode_keuangan dipasang. Pilihan tipe nanti tetap **Profil Akademik**, bukan Mahasiswa/KRS, karena sumber ini merupakan grup keuangan pada importer profil.

Untuk uji demo, impor profil lengkap67-03 terlebih dahulu, lalu keuangan72-01/72-02. Keuangan saja bukan sumber lima syarat akademik. Jika keuangan menjadi impor resmi pertama, record profil bisa bersifat parsial; syarat yang belum pernah diimpor tetap unknown sesuai kontrak65–67. Hal ini berbeda dari menjadikan status BPP sebagai syarat kirim.

## 5. Dampak terhadap alur TA dan batas pemeriksaan

BPP tetap informasi dan tidak menjadi blocker pengiriman TA sesuai71. Tugas berikutnya adalah memastikan periode/status tersimpan dan ditampilkan dengan benar. Lima syarat akademik, aturan bahasa, dokumen wajib, serta bukti/tanggal pembayaran wisuda tetap mempunyai validasi masing-masing.

Asisten belum mengubah normalizer, controller, service, Blade, migration, atau test pada tahap ini. Hanya Markdown72, dua JSON sintetis, dan indeks fixture ditulis. Berkas asli serta source/deployment kedua repo diperiksa dengan hash sebelum/sesudah dan tidak berubah. Tidak ada impor, migration, seed, SSH, build, restart, commit, atau push.
