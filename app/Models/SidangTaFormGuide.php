<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SidangTaFormGuide extends Model
{
    use SoftDeletes;

    protected $fillable = ['slot', 'title', 'description', 'file_path', 'file_name', 'mime_type',
        'link_label', 'url', 'sort_order', 'is_active', 'seed_key'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
