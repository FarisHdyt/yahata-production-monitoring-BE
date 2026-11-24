<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nik' => $this->nik,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'isActive' => $this->is_active,
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'permissions' => [
                'canInput' => $this->hasMinimumRole('operator'),
                'canDelete' => $this->hasMinimumRole('admin'),
                'canEditMaster' => $this->hasMinimumRole('admin'),
                'canManageUsers' => $this->hasMinimumRole('admin'),
            ],
        ];
    }
}