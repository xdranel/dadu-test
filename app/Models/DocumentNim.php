<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentNim extends Model
{
    use HasFactory;

    protected $table = 'document_nim';

    protected $fillable = [
        'document_id',
        'nim',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
