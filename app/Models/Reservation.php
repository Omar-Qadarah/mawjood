<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Reservation extends Model
{
    protected $fillable = [
        'code', 'user_id', 'pharmacy_id', 'prescription_id', 'status',
        'status_changed_at', 'idempotency_key', 'response_deadline', 'customer_note',
    ];

    protected $hidden = ['idempotency_key', 'pickup_code_hash'];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'status_changed_at' => 'datetime',
            'response_deadline' => 'datetime',
            'pickup_deadline' => 'datetime',
        ];
    }

    public function scopeDueForExpiry(Builder $query, CarbonInterface $cutoff): Builder
    {
        return $query->where(fn (Builder $due) => $due
            ->where(fn (Builder $pending) => $pending->where('status', ReservationStatus::Pending->value)
                ->where('response_deadline', '<=', $cutoff))
            ->orWhere(fn (Builder $confirmed) => $confirmed->where('status', ReservationStatus::Confirmed->value)
                ->where('pickup_deadline', '<=', $cutoff)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function allocations(): HasManyThrough
    {
        return $this->hasManyThrough(BatchAllocation::class, ReservationItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }
}
