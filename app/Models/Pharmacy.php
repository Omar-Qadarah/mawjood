<?php

namespace App\Models;

use App\Enums\PharmacyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pharmacy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'license_no',
        'phone',
        'email',
        'address',
        'city',
        'governorate',
        'latitude',
        'longitude',
        'opening_hours',
    ];

    protected $attributes = [
        'status' => PharmacyStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => PharmacyStatus::class,
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'opening_hours' => 'array',
        ];
    }

    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('status', PharmacyStatus::Active->value);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot(['id', 'role', 'is_active'])
            ->withTimestamps();
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
