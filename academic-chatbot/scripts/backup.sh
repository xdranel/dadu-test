#!/bin/bash

# ==============================================================================
# Script Backup Chatbot Akademik RAG
# ==============================================================================
# Script ini digunakan untuk mencadangkan data dokumen (docs/), database vector
# (vectorstore/), registry index (data/index_registry.json), dan log interaksi
# (data/interactions.jsonl).
#
# Simpan berkas ini di /app/scripts/backup.sh dan buat agar dapat dieksekusi:
#   chmod +x /app/scripts/backup.sh
#
# PENGATURAN CRONTAB (Jalankan jam 2 pagi setiap hari):
# Untuk memasang crontab secara otomatis, jalankan perintah `crontab -e` dan 
# tambahkan baris berikut di bagian akhir file:
#   0 2 * * * /bin/bash /app/scripts/backup.sh >> /var/log/chatbot_backup.log 2>&1
# ==============================================================================

# Definisikan direktori kerja dan tujuan backup
APP_DIR="/app"
BACKUP_DIR="/backup/chatbot"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/backup_${TIMESTAMP}.tar.gz"

# Membuat folder backup jika belum ada
mkdir -p "${BACKUP_DIR}"

# Fungsi pencatatan log (logging)
log_message() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1"
}

log_message "=== Memulai proses pencadangan data Chatbot Akademik ==="

# Cek apakah direktori aplikasi ada
if [ ! -d "${APP_DIR}" ]; then
    log_message "Error: Direktori aplikasi '${APP_DIR}' tidak ditemukan."
    exit 1
fi

# Pindah ke direktori aplikasi agar struktur file relatif
cd "${APP_DIR}" || exit 1

# List item yang ingin dibackup
ITEMS_TO_BACKUP=()

if [ -d "docs" ]; then
    ITEMS_TO_BACKUP+=("docs")
fi

if [ -d "vectorstore" ]; then
    ITEMS_TO_BACKUP+=("vectorstore")
fi

if [ -f "data/index_registry.json" ]; then
    ITEMS_TO_BACKUP+=("data/index_registry.json")
fi

if [ -f "data/interactions.jsonl" ]; then
    ITEMS_TO_BACKUP+=("data/interactions.jsonl")
fi

# Cek apakah ada file/folder yang siap dibackup
if [ ${#ITEMS_TO_BACKUP[@]} -eq 0 ]; then
    log_message "Peringatan: Tidak ada berkas atau folder yang ditemukan untuk dibackup."
    exit 0
fi

log_message "Daftar item yang akan dicadangkan: ${ITEMS_TO_BACKUP[*]}"

# Melakukan kompresi tar.gz
log_message "Mengompresi berkas ke ${BACKUP_FILE}..."
tar -czf "${BACKUP_FILE}" "${ITEMS_TO_BACKUP[@]}"

if [ $? -eq 0 ]; then
    log_message "Sukses: Backup berhasil disimpan di ${BACKUP_FILE}"
else
    log_message "Error: Terjadi kegagalan saat proses kompresi tar.gz"
    exit 1
fi

# Menghapus file backup yang lebih lama dari 30 hari
log_message "Mengecek dan menghapus berkas cadangan lama (lebih dari 30 hari)..."
find "${BACKUP_DIR}" -type f -name "backup_*.tar.gz" -mtime +30 -exec rm -f {} \; -print | while read -r deleted_file; do
    log_message "Menghapus berkas cadangan lama: ${deleted_file}"
done

log_message "=== Proses pencadangan selesai ==="
