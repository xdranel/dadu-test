<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * Relasi ke User yang ditempatkan di unit ini.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
