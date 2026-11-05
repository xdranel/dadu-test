<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Status pembayaran wisuda — placeholder.
 *
 * Sumber data: file Excel (format & kolom belum ada contohnya).
 * Kolom masih placeholder, bisa disesuaikan nanti.
 *
 * // TODO: sesuaikan begitu ada contoh file Excel
 */
class StudentGraduationPayment extends Model
{
    protected $table = 'student_graduation_payments';

    protected $fillable = [
        'student_number',
        'payment_type',
        'status',
        'amount',
        'paid_at',
        'raw_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
