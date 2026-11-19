# 127 — Label ruangan yang memerlukan konfirmasi

Sumber dibaca tanpa diubah dari folder peminjamana_ruangan. XLSX berisi60 master kelas, sedangkan JSON berisi69 label ruang unik, termasuk lab/aula dan label lain yang belum ada pada master. Tidak ada kecocokan langsung kode/nama setelah normalisasi biasa. Impor mingguan menolak snapshot yang belum dapat dipetakan agar aplikasi tidak menawarkan ruang yang sebenarnya terpakai.

**Tabel ini bukan pemetaan resmi dan belum diterapkan pada database.** Calon di bawah hanya hasil pola teks KTT + kode master yang sama. Admin/divisi tetap perlu memastikan kesamaan fisik. Label tanpa pasangan jangan diarahkan ke ruangan yang sekadar namanya mirip.

Pertanyaan penting untuk divisi:

- Apakah `KTT1.02` benar-benar ruang yang sama dengan master `1.02`? Jika benar, tambahkan alias pada master tersebut.
- `B-CLS-105` dan `KTT B-1.05` apakah sama atau ruangan berbeda? Jangan otomatis menggabungkannya.
- Ke mana `KTT 1.27 B`, `KTT A-1.06`, `KTT A-1.07`, dan `KTT AULA` ditautkan? Beberapa tidak mempunyai master yang sesuai pada XLSX ini.
- Adakah master ruangan yang lebih lengkap untuk label laboratorium? Jika ruang berbeda, lengkapi master dan klasifikasinya sebagai Bukan kelas untuk TA; jangan membuat alias lab ke kelas lain.

Dari 69 label, ada 44 calon kode berbasis pola teks;25 label belum mempunyai pasangan pola tersebut. Semua masih perlu verifikasi identitas.

| Label JSON | Calon/master yang perlu diverifikasi |
| --- | --- |
| `APDEV LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `B-CLS-101` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `B-CLS-102` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `B-CLS-104` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `B-CLS-105` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `B-CLS-106` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `BEE LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `BEST LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `CAM LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `COS(PI) LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `CPROG LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `CYCLD LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `DATAI LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `DIGITAL LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `EM LAB` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `KTT 1.27 B` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `KTT A-1.03` | `A-1.03` — calon yang perlu dikonfirmasi |
| `KTT A-1.04` | `A-1.04` — calon yang perlu dikonfirmasi |
| `KTT A-1.06` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `KTT A-1.07` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `KTT AULA` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `KTT B-1.05` | `B-1.05` — calon yang perlu dikonfirmasi |
| `KTT B-1.06` | `B-1.06` — calon yang perlu dikonfirmasi |
| `KTT B-1.07` | `B-1.07` — calon yang perlu dikonfirmasi |
| `KTT B-1.08` | `B-1.08` — calon yang perlu dikonfirmasi |
| `KTT1.02` | `1.02` — calon yang perlu dikonfirmasi |
| `KTT1.03` | `1.03` — calon yang perlu dikonfirmasi |
| `KTT1.04` | `1.04` — calon yang perlu dikonfirmasi |
| `KTT1.05` | `1.05` — calon yang perlu dikonfirmasi |
| `KTT1.06` | `1.06` — calon yang perlu dikonfirmasi |
| `KTT1.07` | `1.07` — calon yang perlu dikonfirmasi |
| `KTT1.08` | `1.08` — calon yang perlu dikonfirmasi |
| `KTT1.09` | `1.09` — calon yang perlu dikonfirmasi |
| `KTT1.16` | `1.16` — calon yang perlu dikonfirmasi |
| `KTT1.17` | `1.17` — calon yang perlu dikonfirmasi |
| `KTT1.18` | `1.18` — calon yang perlu dikonfirmasi |
| `KTT1.19` | `1.19` — calon yang perlu dikonfirmasi |
| `KTT1.20` | `1.20` — calon yang perlu dikonfirmasi |
| `KTT1.21` | `1.21` — calon yang perlu dikonfirmasi |
| `KTT1.22` | `1.22` — calon yang perlu dikonfirmasi |
| `KTT2.02` | `2.02` — calon yang perlu dikonfirmasi |
| `KTT2.03` | `2.03` — calon yang perlu dikonfirmasi |
| `KTT2.04` | `2.04` — calon yang perlu dikonfirmasi |
| `KTT2.05` | `2.05` — calon yang perlu dikonfirmasi |
| `KTT2.06` | `2.06` — calon yang perlu dikonfirmasi |
| `KTT2.07` | `2.07` — calon yang perlu dikonfirmasi |
| `KTT2.08` | `2.08` — calon yang perlu dikonfirmasi |
| `KTT2.09` | `2.09` — calon yang perlu dikonfirmasi |
| `KTT2.15` | `2.15` — calon yang perlu dikonfirmasi |
| `KTT2.16` | `2.16` — calon yang perlu dikonfirmasi |
| `KTT2.17` | `2.17` — calon yang perlu dikonfirmasi |
| `KTT2.18` | `2.18` — calon yang perlu dikonfirmasi |
| `KTT2.19` | `2.19` — calon yang perlu dikonfirmasi |
| `KTT2.20` | `2.20` — calon yang perlu dikonfirmasi |
| `KTT2.23` | `2.23` — calon yang perlu dikonfirmasi |
| `KTT2.24` | `2.24` — calon yang perlu dikonfirmasi |
| `KTT2.25` | `2.25` — calon yang perlu dikonfirmasi |
| `KTT2.26` | `2.26` — calon yang perlu dikonfirmasi |
| `KTT2.27` | `2.27` — calon yang perlu dikonfirmasi |
| `KTT2.28` | `2.28` — calon yang perlu dikonfirmasi |
| `KTT2.35` | `2.35` — calon yang perlu dikonfirmasi |
| `KTT2.36` | `2.36` — calon yang perlu dikonfirmasi |
| `KTT2.37` | `2.37` — calon yang perlu dikonfirmasi |
| `MANSYS LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `PORSCE LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `QBI LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `SE LAB` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `SWENG LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |
| `TCON LAB.` | Belum ada pasangan pasti; perlu master/pemetaan resmi |

Alur penyelesaian: [panduan ruangan](../panduan-pemakaian/peminjaman-dan-sumber-jadwal-ruangan.md). JSON kehadiran tetap menunggu sumber tersendiri.
