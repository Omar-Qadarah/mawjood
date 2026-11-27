<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'quantity', 'reason', 'reference', 'reservation_id', 'created_by'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }
}
