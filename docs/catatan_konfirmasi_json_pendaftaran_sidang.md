# Catatan Konfirmasi Kontrak JSON dan Aturan Pemeriksaan Pendaftaran Sidang

Dokumen ini merangkum hal-hal yang perlu dikonfirmasi kepada pembuat JSON atau pihak akademik sebelum aturan pemeriksaan pendaftaran sidang diterapkan.

## 1. SKPI: apa yang menandakan sudah memenuhi?

Contoh data:

```json
{
  "skpi_status_1": "Y",
  "skpi_status_2": "Y",
  "skpi_status_3": "Y",
  "status_skpi": "Disetujui"
}
```

SKPI menjadi salah satu syarat yang menentukan apakah mahasiswa boleh menekan **Kirim Pendaftaran**. Nama field saja belum menjelaskan arti status 1–3.

Contoh yang perlu dijelaskan:

```json
{
  "skpi_status_1": "Y",
  "skpi_status_2": "Y",
  "skpi_status_3": "N",
  "status_skpi": "Disetujui"
}
```

Apakah mahasiswa memenuhi syarat pada kondisi tersebut? Jawabannya bergantung pada arti resmi setiap field.

**Pertanyaan kepada pembuat JSON/akademik:**
- Apa arti masing-masing `skpi_status_1`, `skpi_status_2`, dan `skpi_status_3`?
- Apakah `status_skpi` merupakan hasil akhir dari ketiga status tersebut?
- Apakah cukup `status_skpi = "Disetujui"` untuk memenuhi syarat?
- Jika status ringkasan dan status 1–3 berbeda, mana yang menjadi acuan?
- Apa saja kemungkinan nilainya? Apakah ada `Menunggu`, `Ditolak`, atau nilai lain?
- Bagaimana bentuk data mahasiswa yang belum mengisi SKPI?

**Hasil yang diperlukan:** aturan tegas untuk mengubah data menjadi **memenuhi**, **belum memenuhi**, atau **belum diketahui**.

## 2. Persetujuan sidang: field mana yang merupakan izin mengikuti sidang?

Contoh:

```json
{
  "persetujuan_maju_sidang": "APPROVED",
  "status_maju_sidang_asli": "APPROVED",
  "pembimbing_ta": "Dosen Pembimbing TA",
  "status_ta": "APPROVED",
  "status_permintaan": null
}
```

Perlu dipastikan apakah semua field membahas persetujuan yang sama atau proses berbeda. Persetujuan judul TA dan izin mengikuti sidang, misalnya, dapat merupakan dua keputusan berbeda.

Contoh kondisi yang perlu dijelaskan:

```json
{
  "persetujuan_maju_sidang": "PENDING",
  "status_maju_sidang_asli": "Menunggu Persetujuan",
  "status_ta": "APPROVED",
  "status_permintaan": null
}
```

Apakah mahasiswa sudah diizinkan sidang karena `status_ta` disetujui, atau masih menunggu karena persetujuan maju sidang belum selesai?

**Pertanyaan:**
- Apa arti masing-masing field?
- Apakah `persetujuan_maju_sidang` adalah status akhir izin mengikuti sidang?
- Apakah field berakhiran `_asli` merupakan teks langsung dari iGracias, sedangkan field lainnya dinormalisasi oleh exporter?
- Persetujuan diberikan oleh siapa dan pada proses apa?
- Apa saja status untuk belum mengajukan, menunggu, disetujui, dan ditolak?
- Apakah persetujuan berlaku untuk semester atau pengajuan TA tertentu?

**Kesepakatan fitur saat ini:** data ini digunakan untuk **informasi otomatis tahap 2**. Lima syarat akademik pemblokir tetap seperti yang telah disepakati; persetujuan ini belum ditetapkan sebagai syarat pemblokir tambahan.

## 3. Nilai EPRT: nilai mana yang digunakan?

Contoh:

```json
{
  "nilai_bahasa": "525",
  "jenis_tes_bahasa": "EPrT"
}
```

Riwayat:

```json
{
  "riwayat_nilai_bahasa": [
    {
      "jenis_tes": "EPrT",
      "nilai": "525",
      "kedaluwarsa": "2027-06-15",
      "status_berlaku": "Berlaku"
    },
    {
      "jenis_tes": "EPrT",
      "nilai": "480",
      "kedaluwarsa": "2025-08-20",
      "status_berlaku": "Kedaluwarsa"
    }
  ]
}
```

Sampel belum menjelaskan bagaimana `nilai_bahasa` dipilih. Nilai 525 bisa merupakan nilai terbaru, tertinggi, atau nilai yang masih berlaku.

Contoh perbedaan hasil:

| Hasil tes | Nilai | Masa berlaku |
|---|---:|---|
| Tes pertama | 500 | Sudah kedaluwarsa |
| Tes kedua | 430 | Masih berlaku |

Jika memakai nilai tertinggi sepanjang riwayat, hasilnya 500. Jika harus memakai nilai yang masih berlaku, hasilnya 430.

**Pertanyaan:**
- `nilai_bahasa` mengambil nilai terbaru, tertinggi, atau tertinggi yang masih berlaku?
- Apakah field tersebut selalu khusus EPRT sesuai `jenis_tes_bahasa`?
- Apakah jenis tes bahasa lain dapat muncul pada mahasiswa yang sama?
- Untuk syarat sidang, apakah nilai EPRT ≥450 harus masih berlaku?
- Jika tanggal kedaluwarsa dan `status_berlaku` bertentangan, mana yang menjadi acuan?
- Apakah sertifikat masih berlaku pada tanggal kedaluwarsanya?

**Pembagian konfirmasi:** pembuat JSON menjelaskan cara nilai dipilih. Pihak akademik menentukan apakah nilai kedaluwarsa boleh digunakan untuk syarat sidang.

**Aturan fitur yang telah disepakati:** nilai EPRT ≥450 memenuhi secara otomatis. Yang belum dipastikan adalah nilai mana yang sah digunakan untuk pemeriksaan.

## 4. Jumlah tes EPRT: apa yang dihitung sebagai tiga kali tes?

JSON menyediakan:

```json
{
  "jumlah_tes_bahasa": 2
}
```

Aturan fitur:
- Nilai memenuhi ≥450: otomatis memenuhi.
- Nilai belum memenuhi dan sudah mengikuti minimal tiga kali tes: unggah Surat Permakluman.
- Nilai belum memenuhi dan belum tiga kali tes: diminta mengikuti tes lagi.

Contoh: mahasiswa memiliki 2 tes EPRT dan 1 tes bahasa jenis lain. Jika `jumlah_tes_bahasa` bernilai 3, belum tentu mahasiswa sudah mengikuti **tiga kali EPRT**.

Contoh lain: mahasiswa pernah mengikuti tiga kali EPRT, tetapi dua hasilnya kedaluwarsa. Perlu dipastikan apakah ketiga tes tetap dihitung untuk Surat Permakluman.

**Pertanyaan:**
- Apakah `jumlah_tes_bahasa` khusus menghitung EPRT atau seluruh jenis tes bahasa?
- Apakah angka tersebut menghitung seluruh riwayat atau hanya hasil yang masih berlaku?
- Apakah tes kedaluwarsa tetap dihitung untuk syarat tiga kali tes?
- Apakah tes yang didaftarkan tetapi tidak diikuti ikut dihitung?
- Apakah `riwayat_nilai_bahasa` memuat seluruh percobaan, termasuk nilai rendah?
- Jika `jumlah_tes_bahasa = 3` tetapi riwayat hanya berisi dua hasil, apa penyebabnya dan sumber mana yang dipercaya?

**Hasil yang diperlukan:** jumlah percobaan EPRT yang diakui untuk aturan Surat Permakluman.

## 5. Identitas setiap tes: mencegah satu tes terhitung berkali-kali

Contoh riwayat belum mempunyai ID tes, tanggal pelaksanaan, atau nomor percobaan. Yang tersedia hanya tanggal kedaluwarsa.

Aplikasi perlu mengenali bahwa hasil yang diimpor hari ini dan besok merupakan tes yang sama.

Misalnya, impor pertama berisi dua hasil:

```text
525
480
```

Impor berikutnya berisi:

```text
540
525
480
```

Jika aplikasi mengenali tes berdasarkan urutan array, urutan pertama sekarang berubah dari 525 menjadi 540. Data lama bisa tertimpa dengan identitas yang salah.

Contoh field yang dapat membantu (nama hanya ilustrasi):

```json
{
  "id_tes": "EPRT-00123",
  "tanggal_tes": "2026-06-15",
  "jenis_tes": "EPrT",
  "nilai": "525",
  "kedaluwarsa": "2027-06-15"
}
```

**Pertanyaan kepada pembuat JSON:**
- Apakah sumber menyediakan ID tes yang tetap?
- Apakah tanggal pelaksanaan tes bisa diekspor?
- Apakah tersedia nomor percobaan yang tetap?
- Apakah urutan riwayat selalu tertentu?
- Apakah hasil yang sama bisa muncul dua kali di sumber?

Tanggal pelaksanaan **tidak boleh ditebak dari tanggal kedaluwarsa**, karena masa berlaku tes belum tentu selalu sama.

## 6. TAK: apakah `total_tak` merupakan total poin acuan?

Data:

```json
{
  "total_tak": "80"
}
```

Aturan minimal 60:

| Nilai | Hasil pemeriksaan |
|---|---|
| `"80"` | Memenuhi |
| `"60"` | Memenuhi |
| `"59"` | Belum memenuhi |
| `"0"` | Belum memenuhi |
| `null` | Belum diketahui |

Yang masih perlu dipastikan adalah makna totalnya:

- Apakah `total_tak` merupakan total poin kumulatif yang sudah disahkan?
- Apakah poin kegiatan yang masih menunggu persetujuan ikut dihitung?
- Apakah totalnya sepanjang masa studi atau hanya semester tertentu?
- Bagaimana bentuk nilai ketika mahasiswa belum mempunyai poin: `0`, `null`, atau field tidak ada?

Batas minimal 60 sudah disepakati. Konfirmasi ini memastikan angka dari JSON memang tepat untuk dibandingkan dengan batas tersebut.

## 7. BPP: lunas untuk pembayaran apa dan periode kapan?

Data:

```json
{
  "status_keuangan": "LUNAS",
  "status_keuangan_asli": "LUNAS",
  "semester": "2627/1"
}
```

Kebutuhan tampilan adalah status **BPP semester berjalan**. Perlu dipastikan bahwa `LUNAS` memang menjelaskan pembayaran tersebut.

**Pertanyaan:**
- Apakah `status_keuangan` khusus BPP atau mencakup kewajiban keuangan lain?
- Apakah status tersebut berlaku untuk semester pada field `semester`?
- Apakah `"2627/1"` berarti tahun akademik 2026/2027 semester ganjil?
- Apakah `semester` berlaku untuk seluruh data pada baris tersebut atau hanya menu tertentu?
- Apa saja kemungkinan status selain `LUNAS`?
- Apa perbedaan `status_keuangan` dengan `status_keuangan_asli`?

**Kesepakatan alur:** BPP merupakan informasi otomatis yang ditampilkan. BPP belum ditambahkan sebagai syarat akademik pemblokir baru.

## 8. Menu yang tidak dipilih dan nilai kosong: apakah data lama dipertahankan?

JSON memiliki:

```json
{
  "selected_menus": [
    "akademik",
    "bahasa",
    "tak",
    "keuangan",
    "mata_kuliah",
    "jadwal_sidang",
    "skpi",
    "maju_sidang",
    "basila_foto"
  ]
}
```

Exporter mempunyai pilihan menu. Perlu diketahui bentuk hasil ketika hanya sebagian menu dipilih.

Contoh: hari ini admin mengimpor seluruh data dan SKPI sudah disetujui. Besok admin hanya mengekspor menu TAK.

| Bentuk data | Makna yang perlu dipastikan |
|---|---|
| Field SKPI tidak ada | Menu SKPI tidak diambil? |
| `"status_skpi": null` | Tidak diambil, belum mempunyai status, atau pengambilan gagal? |
| `"riwayat_nilai_bahasa": []` | Tidak pernah tes atau menu bahasa tidak diambil? |

Ketiga kondisi tersebut dapat memerlukan tindakan berbeda. Data yang tidak ikut diekspor biasanya perlu dipertahankan, sedangkan perubahan resmi dari sumber mungkin perlu memperbarui data lama.

**Pertanyaan kepada pembuat JSON:**
- Ketika menu tidak dipilih, apakah field dihilangkan atau tetap muncul dengan `null`?
- Apa arti `null` dan array kosong pada setiap menu?
- Bagaimana exporter menandai kegagalan mengambil data?
- Apakah ekspor merupakan salinan lengkap kondisi terbaru atau hanya sebagian perubahan?
- Jika hasil lama tidak muncul pada ekspor berikutnya, apakah hasil tersebut memang dihapus atau sekadar tidak ikut diambil?

Importer saat ini belum menggunakan `selected_menus` sebagai panduan, dan beberapa bagian masih memperbarui data meskipun field sumber tidak tersedia.

## 9. KRS TA 2: bukti mengambil mata kuliah berbeda dengan nilai TA

JSON memiliki:

```json
{
  "indeks_nilai_tugas_akhir": "A"
}
```

Field tersebut menjelaskan nilai tugas akhir. Untuk syarat ini, aplikasi perlu mengetahui bahwa mahasiswa **mengambil TUGAS AKHIR 2 pada periode akademik aktif**.

Contoh informasi KRS yang diperlukan:

```text
Mahasiswa: NIM tertentu
Mata kuliah: TUGAS AKHIR 2
Tahun akademik: 2026/2027
Semester: GANJIL
```

Mahasiswa bisa mempunyai nilai TA dari periode sebelumnya. Karena itu, nilai saja belum cukup untuk membuktikan KRS periode sekarang.

**Konfirmasi kepada penyedia data:**
- Apakah daftar KRS tetap berasal dari ekspor terpisah?
- Field apa yang menunjukkan tahun akademik dan semester KRS?
- Apakah ada status pembatalan mata kuliah yang perlu diperhatikan?

Jika JSON KRS terpisah yang sebelumnya digunakan memang sumber resmi, tetap gunakan sumber tersebut.

## 10. Apakah contoh v6.9 sudah mewakili kontrak ekspor yang akan dipakai?

Karena nama file adalah `CONTOH_JSON_LENGKAP_SEMUA_MENU_v6_9.json`, perlu konfirmasi mendasar:

> Apakah nama field, tipe nilai, dan susunan JSON ini sudah sama dengan hasil ekspor aplikasi sebenarnya, atau masih berupa contoh rancangan?

Contoh:

```json
{
  "total_tak": "80",
  "jumlah_tes_bahasa": 2
}
```

`total_tak` berupa angka dalam bentuk teks, sedangkan `jumlah_tes_bahasa` berupa angka. Keduanya dapat ditangani, tetapi perlu diketahui variasi yang benar-benar mungkin muncul.

Minta juga contoh dari kondisi berikut, dengan identitas boleh disamarkan:

- SKPI belum disetujui atau ditolak.
- Persetujuan sidang masih menunggu atau ditolak.
- EPRT di bawah 450 dengan minimal tiga percobaan.
- Mahasiswa yang belum pernah mengikuti EPRT.
- Ekspor yang hanya memilih satu atau dua menu.

Sidang tingkat 2 dan SKS lulus sudah mempunyai field yang terbaca. Untuk keduanya, cukup pastikan `Y/N` memang menunjukkan lulus/belum lulus dan `sks_lulus` merupakan SKS yang sudah dinyatakan lulus.

Ambang **SKS 120, TAK 60, dan EPRT 450** tidak perlu ditanyakan ulang kecuali pihak akademik menyampaikan perubahan aturan.
