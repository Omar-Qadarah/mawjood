<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $fillable = ['user_id', 'image_path', 'status', 'delete_after'];

    protected $hidden = ['image_path', 'extracted_payload'];

    protected function casts(): array
    {
        return ['delete_after' => 'datetime', 'image_deleted_at' => 'datetime'];
    }
}
