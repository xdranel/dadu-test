# Penyelarasan file development → dadu-test

Sembilan file progres127 sudah disamakan pada kedua folder dengan backup/hash. Perintah berikut hanya untuk mengulang salinan setelah perubahan berikutnya yang memang ditinjau. Scope ini bukan sinkronisasi seluruh repo atau paket instalasi fitur125 pada checkout lama.

```bash
python3 - <<'DADU_COPY'
from pathlib import Path
import shutil, tempfile
source = Path('/home/xdranel/Code/Project/php-laravel/dadu')
target = Path('/home/xdranel/Code/Project/secret/dadu-test')
paths = [
    'app/Http/Controllers/RoomBookingController.php',
    'app/Services/RoomBookingTransitionService.php',
    'app/Services/SidangTaStaffScheduling.php',
    'app/Support/RoomBookingAccess.php',
    'config/sidebar.php',
    'resources/views/room-bookings/review.blade.php',
    'tests/Feature/RoomBookingAdminAccessTest.php',
    'tests/Feature/RoomBookingFeatureCompleteTest.php',
    'tests/Feature/RoomSchedulingDiagnosticsTest.php',
]
for name in paths:
    if not (source / name).is_file():
        raise SystemExit('Berkas source tidak tersedia: ' + name)
backup = Path(tempfile.mkdtemp(prefix='dadu-copy127-'))
for name in paths:
    destination = target / name
    if destination.exists():
        saved = backup / name
        saved.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(destination, saved)
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source / name, destination)
print('Disalin:', len(paths), 'file. Backup:', backup)
DADU_COPY
```

Sesudah salin, periksa diff sebelum commit. Muat ulang cache konfigurasi/view sesuai alur rilis jika aktif. Progres127 hanya mengubah PHP/config/label; bangun frontend bila perubahan berikutnya juga mengubah style atau aset. Jangan menyalin `.env`, staging Git, seluruh konfigurasi Docker, data privat atau lima adaptasi target hanya karena membandingkan folder. Riwayat daftar40 file fitur125 tersedia di [bukti historis](../catatan/bukti-verifikasi-historis.md#125-daftar-file-pelaksanaan-txt).

Helper `docker/scripts/copy-ta-room-feature.sh` checkpoint112 sudah dipensiunkan karena manifest/hashnya tidak mewakili kode terbaru; ia berhenti dengan pesan yang mengarah ke panduan ini.
