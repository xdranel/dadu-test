<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SKPI (Surat Keterangan Pendamping Ijazah) — placeholder.
 *
 * Struktur data SKPI belum ada contohnya. Model ini menyimpan
 * payload asli apa adanya di kolom `raw_data` (JSON).
 * Begitu format SKPI sudah pasti, tambahkan kolom spesifik
 * dan migrate data dari raw_data.
 */
class StudentSkpi extends Model
{
    protected $table = 'student_skpis';

    protected $fillable = [
        'student_number',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
