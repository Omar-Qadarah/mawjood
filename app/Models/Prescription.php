<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    protected $fillable = ['user_id', 'image_path', 'status', 'delete_after'];

    protected $hidden = ['image_path', 'extracted_payload'];

    public function activeReservations(): HasMany
    {
        return $this->hasMany(Reservation::class)->whereIn('status', [
            ReservationStatus::Pending->value,
            ReservationStatus::Confirmed->value,
        ]);
    }

    public function scopeDueForImagePurge(Builder $query, CarbonInterface $cutoff): Builder
    {
        return $query->whereNotNull('image_path')->whereNull('image_deleted_at')
            ->where('delete_after', '<=', $cutoff);
    }

    protected function casts(): array
    {
        return ['delete_after' => 'datetime', 'image_deleted_at' => 'datetime'];
    }
}
