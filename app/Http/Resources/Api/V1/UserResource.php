<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_unit_id' => $this->organization_unit_id,
            'name' => $this->name,
            'email' => $this->email,
            'employee_code' => $this->employee_code,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'avatar_url' => $this->avatar_url,
            'is_system_admin' => (bool) $this->is_system_admin,
            'is_active' => (bool) $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'organization_unit' => $this->whenLoaded('organizationUnit', fn () => [
                'id' => $this->organizationUnit?->id,
                'name' => $this->organizationUnit?->name,
            ]),
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
