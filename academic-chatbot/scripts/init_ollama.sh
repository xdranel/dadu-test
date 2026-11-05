#!/bin/bash

# ==============================================================================
# Script Inisialisasi Model Ollama
# ==============================================================================
# Script ini digunakan untuk mengunduh model LLM yang digunakan oleh chatbot
# akademik (default: qwen2.5:7b) ke dalam instance Ollama.
#
# Pastikan server Ollama sudah berjalan sebelum menjalankan script ini.
# Cara menjalankan:
#   chmod +x scripts/init_ollama.sh
#   ./scripts/init_ollama.sh
# ==============================================================================

MODEL_NAME="qwen2.5:7b"

echo "=== Memulai Inisialisasi Model Ollama ==="
echo "Mengecek apakah perintah 'ollama' tersedia di sistem..."

if ! command -v ollama &> /dev/null; then
    echo "Error: Perintah 'ollama' tidak ditemukan."
    echo "Pastikan Ollama terinstall di sistem host Anda atau jalankan perintah pull"
    echo "di dalam container Docker Ollama: docker exec -it ollama ollama pull ${MODEL_NAME}"
    exit 1
fi

echo "Mencoba mengunduh model '${MODEL_NAME}' via Ollama CLI..."
echo "Proses ini membutuhkan koneksi internet dan dapat memakan waktu beberapa menit..."

ollama pull "${MODEL_NAME}"

if [ $? -ne 0 ]; then
    echo "Error: Gagal mendownload model '${MODEL_NAME}'."
    exit 1
fi

echo "Memverifikasi apakah model '${MODEL_NAME}' berhasil dimuat..."
VERIFY_MODEL=$(ollama list | grep "${MODEL_NAME}")

if [ -n "${VERIFY_MODEL}" ]; then
    echo "Sukses: Model '${MODEL_NAME}' berhasil diverifikasi dan siap digunakan."
    echo "Detail model:"
    echo "${VERIFY_MODEL}"
else
    echo "Peringatan: Unduhan selesai, namun model '${MODEL_NAME}' tidak terdaftar pada 'ollama list'."
    echo "Silakan periksa log server Ollama."
    exit 1
fi

echo "=== Inisialisasi Model Ollama Selesai ==="
