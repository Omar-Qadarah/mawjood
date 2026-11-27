<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'memberships_count' => $this->whenCounted('memberships'),
            'memberships' => $this->whenLoaded('memberships', fn () => $this->memberships->map(fn ($membership) => [
                'pharmacy' => $membership->pharmacy->name_en,
                'role' => $membership->role->value,
                'is_active' => $membership->is_active,
            ])),
        ];
    }
}
