# Penyelarasan file development → dadu-test

Dua puluh enam file progres128 sudah disamakan pada kedua folder dengan backup/hash. Perintah berikut hanya untuk mengulang salinan setelah perubahan berikutnya yang memang ditinjau. Scope ini bukan sinkronisasi seluruh repo atau paket instalasi fitur125 pada checkout lama.

```bash
python3 - <<'DADU_COPY'
from pathlib import Path
import shutil, tempfile
source = Path('/home/xdranel/Code/Project/php-laravel/dadu')
target = Path('/home/xdranel/Code/Project/secret/dadu-test')
paths = [
    'app/Http/Controllers/Admin/SidangTaBookingController.php',
    'app/Http/Controllers/Admin/SidangTaExecutionController.php',
    'app/Http/Requests/StoreRoomRequest.php',
    'app/Http/Requests/UpdateRoomRequest.php',
    'app/Models/Room.php',
    'app/Services/SidangTaBookingAdministration.php',
    'app/Services/SidangTaBookingService.php',
    'app/Services/SidangTaPlottingImportService.php',
    'app/Services/SidangTaStaffScheduling.php',
    'database/migrations/2026_11_19_128000_remove_room_ta_classification.php',
    'resources/views/rooms/create.blade.php',
    'resources/views/rooms/edit.blade.php',
    'resources/views/rooms/index.blade.php',
    'resources/views/sync/index.blade.php',
    'tests/Feature/RoomClassificationRemovalMysqlTest.php',
    'tests/Feature/RoomMasterEligibilityTest.php',
    'tests/Feature/RoomSchedulingDiagnosticsTest.php',
    'tests/Feature/SidangTaBookingConcurrencyTest.php',
    'tests/Feature/SidangTaBookingLifecycleTest.php',
    'tests/Feature/SidangTaExecutionDomainTest.php',
    'tests/Feature/SidangTaExecutionSettingsTest.php',
    'tests/Feature/SidangTaLecturerBookingLinkTest.php',
    'tests/Feature/SidangTaRoomMysqlUpgradeTest.php',
    'tests/Feature/SidangTaRoomSchemaCompatibilityTest.php',
    'tests/Feature/SidangTaStaffSchedulingTest.php',
    'tests/Support/TaRoomScenario.php',
]
for name in paths:
    if not (source / name).is_file():
        raise SystemExit('Berkas source tidak tersedia: ' + name)
backup = Path(tempfile.mkdtemp(prefix='dadu-copy128-'))
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

Sesudah salin, periksa diff sebelum commit. Muat ulang cache konfigurasi/view sesuai alur rilis jika aktif. Progres128 mencakup migration baru penghapusan klasifikasi. Jalankan migration biasa melalui alur maintenance/backup saat memakai atau merilis kode baru; status ruangan tidak diubah. Jangan menyalin `.env`, staging Git, seluruh konfigurasi Docker, data privat atau lima adaptasi target hanya karena membandingkan folder. Riwayat daftar40 file fitur125 tersedia di [bukti historis](../catatan/bukti-verifikasi-historis.md#125-daftar-file-pelaksanaan-txt).

Helper `docker/scripts/copy-ta-room-feature.sh` checkpoint112 sudah dipensiunkan karena manifest/hashnya tidak mewakili kode terbaru; ia berhenti dengan pesan yang mengarah ke panduan ini.
