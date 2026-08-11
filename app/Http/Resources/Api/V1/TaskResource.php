<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_unit_id' => $this->organization_unit_id,
            'parent_id' => $this->parent_id,
            'project_id' => $this->project_id,
            'creator_id' => $this->creator_id,
            'assignee_id' => $this->assignee_id,
            'recurrence_id' => $this->recurrence_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority?->value ?? $this->priority,
            'progress' => $this->progress,
            'planned_quantity' => $this->planned_quantity,
            'actual_quantity' => $this->actual_quantity,
            'quantity_unit' => $this->quantity_unit,
            'due_at' => $this->due_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'is_overdue' => $this->isOverdue(),
            'organization_unit' => $this->whenLoaded('organizationUnit'),
            'creator' => $this->whenLoaded('creator'),
            'assignee' => $this->whenLoaded('assignee'),
            'project' => $this->whenLoaded('project'),
            'recurrence' => $this->whenLoaded('recurrence'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
