<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,

            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,

            'opening_hours' => $this->opening_hours,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}