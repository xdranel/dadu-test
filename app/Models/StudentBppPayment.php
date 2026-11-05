<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Status pembayaran BPP mahasiswa.
 *
 * Sumber data: field `status_keuangan` di JSON iGracias+BASILA.
 * Primary key bisnis: NIM (student_number).
 */
class StudentBppPayment extends Model
{
    protected $table = 'student_bpp_payments';

    protected $fillable = [
        'student_number',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
