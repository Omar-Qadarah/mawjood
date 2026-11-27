<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_path ? route('api.v1.admin.profile.avatar', [
                'v' => hash('sha256', $this->avatar_path),
            ], absolute: false) : null,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'platform_role' => $this->platform_role->value,
            'created_at' => $this->created_at,
        ];
    }
}
