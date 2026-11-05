<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Mapping dokumen lama ke dokumen baru:
     * - revision_form -> revision_sheet_scan
     * - final_thesis_book -> final_thesis_book_full
     * - similarity_check_thesis -> ithenticate_book_result
     * - similarity_check_publication -> ithenticate_journal_result
     * - library_clearance_letter -> library_clearance_letter (tidak berubah)
     * - financial_clearance_letter -> financial_clearance_letter (tidak berubah)
     * - diploma_biodata_form -> diploma_biodata_statement
     * - ibasila_screenshot -> (dihapus, diganti dengan approval_sheet)
     * - softfile_thesis_upload_proof -> openlib_clearance_letter
     * - level_3_passed_capture -> (dihapus, data ini sudah dari grab sistem)
     * - lab_loan_clearance_letter -> (dihapus)
     */
    public function up(): void
    {
        // Mapping key lama ke key baru
        $mappings = [
            'revision_form'                => 'revision_sheet_scan',
            'final_thesis_book'            => 'final_thesis_book_full',
            'similarity_check_thesis'      => 'ithenticate_book_result',
            'similarity_check_publication' => 'ithenticate_journal_result',
            'diploma_biodata_form'         => 'diploma_biodata_statement',
            'softfile_thesis_upload_proof' => 'openlib_clearance_letter',
        ];

        foreach ($mappings as $oldKey => $newKey) {
            DB::table('graduation_registration_documents')
                ->where('document_type', $oldKey)
                ->update(['document_type' => $newKey]);
        }

        // Dokumen yang dihapus, tandai dengan status 'archived' di notes
        $deprecatedTypes = ['ibasila_screenshot', 'level_3_passed_capture', 'lab_loan_clearance_letter'];
        
        foreach ($deprecatedTypes as $type) {
            DB::table('graduation_registration_documents')
                ->where('document_type', $type)
                ->update([
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), '\n[ARSIP] Jenis dokumen ini sudah tidak digunakan sejak pembaruan sistem')"),
                    'document_type' => $type . '_archived'
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan mapping
        $reverseMappings = [
            'revision_sheet_scan'         => 'revision_form',
            'final_thesis_book_full'      => 'final_thesis_book',
            'ithenticate_book_result'     => 'similarity_check_thesis',
            'ithenticate_journal_result'  => 'similarity_check_publication',
            'diploma_biodata_statement'   => 'diploma_biodata_form',
            'openlib_clearance_letter'    => 'softfile_thesis_upload_proof',
        ];

        foreach ($reverseMappings as $newKey => $oldKey) {
            DB::table('graduation_registration_documents')
                ->where('document_type', $newKey)
                ->update(['document_type' => $oldKey]);
        }

        // Kembalikan dokumen archived
        $archivedTypes = ['ibasila_screenshot_archived', 'level_3_passed_capture_archived', 'lab_loan_clearance_letter_archived'];
        
        foreach ($archivedTypes as $archivedType) {
            $originalType = str_replace('_archived', '', $archivedType);
            DB::table('graduation_registration_documents')
                ->where('document_type', $archivedType)
                ->update(['document_type' => $originalType]);
        }
    }
};
