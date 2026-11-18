<?php

namespace App\Services;

use App\Enums\AccessGroup;
use App\Models\DocumentCategory;
use App\Models\User;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class DocumentImportTemplateService
{
    /**
     * Header template import sesuai urutan baku.
     */
    public static function getHeaders(): array
    {
        return [
            'Nama Lama',
            'Jenis Dokumen',
            'Role Dokumen',
            'Folder',
            'Nama Baru',
            'NIM',
            'Keyword',
        ];
    }

    /**
     * Role dokumen yang valid untuk impor.
     */
    public static function getValidRoles(): array
    {
        return [
            'Mahasiswa',
            'Dosen dan Staf',
            'Akademik',
            'Publik',
        ];
    }

    /**
     * Dapatkan daftar path folder yang dapat diakses oleh user.
     */
    public function getAvailableFolders(?User $user = null): array
    {
        $categories = DocumentCategory::visibleTo($user)
            ->with(['parent'])
            ->orderBy('name')
            ->get();

        $folders = [];
        foreach ($categories as $cat) {
            $root = $cat->getRoot();
            $ag = $root->access_group instanceof AccessGroup ? $root->access_group->label() : ($root->access_group ?? 'Publik');
            $folders[] = [
                'path' => $cat->getFullPath(),
                'role' => $ag,
                'name' => $cat->name,
            ];
        }

        usort($folders, fn($a, $b) => strcmp($a['path'], $b['path']));

        return $folders;
    }

    /**
     * Buat berkas XLSX template dengan 3 sheets.
     */
    public function generateXlsx(string $destinationPath, ?User $user = null): string
    {
        $writer = new XlsxWriter();
        $writer->openToFile($destinationPath);

        // 1. Sheet Template Import
        $sheet1 = $writer->getCurrentSheet();
        $sheet1->setName('Template Import');

        $headers = self::getHeaders();
        $writer->addRow(Row::fromValues($headers));

        // Baris contoh
        $sampleFolder = 'Publik / Umum';
        $available = $this->getAvailableFolders($user);
        if (!empty($available)) {
            $sampleFolder = $available[0]['path'];
        }

        $exampleRow = [
            'contoh_file.pdf',
            'Surat Keterangan',
            'Publik',
            $sampleFolder,
            'Surat Keterangan Aktif Kuliah',
            '',
            'surat, aktif, kuliah',
        ];
        $writer->addRow(Row::fromValues($exampleRow));

        // 2. Sheet Daftar Folder
        $sheet2 = $writer->addNewSheetAndMakeItCurrent();
        $sheet2->setName('Daftar Folder');
        $writer->addRow(Row::fromValues(['Path Folder', 'Role Akses', 'Nama Folder']));

        foreach ($available as $f) {
            $writer->addRow(Row::fromValues([
                $f['path'],
                $f['role'],
                $f['name'],
            ]));
        }

        // 3. Sheet Petunjuk Singkat
        $sheet3 = $writer->addNewSheetAndMakeItCurrent();
        $sheet3->setName('Petunjuk');
        $writer->addRow(Row::fromValues(['Nama Kolom', 'Kewajiban', 'Ketentuan & Format']));
        $writer->addRow(Row::fromValues(['Nama Lama', 'Wajib', 'Mode Upload: Nama berkas fisik yang diunggah (contoh: laporan.pdf). Mode Update: Judul dokumen atau nama berkas lama yang sudah ada di sistem.']));
        $writer->addRow(Row::fromValues(['Jenis Dokumen', 'Opsional', 'Teks bebas kategori/jenis dokumen (contoh: Surat Keputusan, Transkrip, Ijazah, Formulir).']));
        $writer->addRow(Row::fromValues(['Role Dokumen', 'Wajib', 'Hanya bernilai: Mahasiswa, Dosen dan Staf, Akademik, Publik. Wajib sesuai dengan kategori root folder.']));
        $writer->addRow(Row::fromValues(['Folder', 'Wajib', 'Path folder yang SUDAH ADA di sistem dipisahkan dengan " / " (contoh: "Publik / Umum"). Lihat sheet Daftar Folder.']));
        $writer->addRow(Row::fromValues(['Nama Baru', 'Wajib', 'Judul resmi dokumen yang akan disimpan di sistem.']));
        $writer->addRow(Row::fromValues(['NIM', 'Wajib (Role Mahasiswa)', 'Nomor Induk Mahasiswa (8-15 digit). Banyak NIM dipisahkan dengan titik koma (;) tanpa spasi. Selalu gunakan format teks.']));
        $writer->addRow(Row::fromValues(['Keyword', 'Opsional', 'Kata kunci pencarian, pisahkan dengan koma (,).']));

        $writer->close();

        return $destinationPath;
    }

    /**
     * Buat berkas CSV template (UTF-8 dengan BOM).
     */
    public function generateCsv(string $destinationPath, ?User $user = null): string
    {
        $fp = fopen($destinationPath, 'w');
        // UTF-8 BOM
        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, self::getHeaders());

        $available = $this->getAvailableFolders($user);
        $sampleFolder = !empty($available) ? $available[0]['path'] : 'Publik / Umum';

        fputcsv($fp, [
            'contoh_file.pdf',
            'Surat Keterangan',
            'Publik',
            $sampleFolder,
            'Surat Keterangan Aktif Kuliah',
            '',
            'surat, aktif, kuliah',
        ]);

        fclose($fp);

        return $destinationPath;
    }
}
