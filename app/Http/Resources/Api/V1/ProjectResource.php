<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_unit_id' => $this->organization_unit_id,
            'owner_id' => $this->owner_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'close_reason' => $this->close_reason,
            'progress' => (int) ($this->getAttribute('progress') ?? 0),
            'task_count' => (int) ($this->getAttribute('task_count') ?? 0),
            'open_task_count' => (int) ($this->getAttribute('open_task_count') ?? 0),
            'member_count' => (int) ($this->getAttribute('member_count') ?? 0),
            'organization_unit' => $this->whenLoaded('organizationUnit'),
            'owner' => $this->whenLoaded('owner'),
            'permissions' => $request->user() ? [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
                'manage_members' => $request->user()->can('manageMembers', $this->resource),
                'close' => $request->user()->can('close', $this->resource),
            ] : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
