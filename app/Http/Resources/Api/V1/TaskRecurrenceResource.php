<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskRecurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_unit_id' => $this->organization_unit_id,
            'project_id' => $this->project_id,
            'creator_id' => $this->creator_id,
            'assignee_id' => $this->assignee_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value ?? $this->priority,
            'planned_quantity' => $this->planned_quantity,
            'quantity_unit' => $this->quantity_unit,
            'frequency' => $this->frequency?->value ?? $this->frequency,
            'interval' => $this->interval,
            'weekdays' => $this->weekdays,
            'day_of_month' => $this->day_of_month,
            'start_date' => $this->start_date?->toDateString(),
            'due_time' => $this->due_time,
            'is_active' => (bool) $this->is_active,
            'last_generated_for' => $this->last_generated_for?->toDateString(),
            'organization_unit' => $this->whenLoaded('organizationUnit'),
            'project' => $this->whenLoaded('project'),
            'creator' => $this->whenLoaded('creator'),
            'assignee' => $this->whenLoaded('assignee'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
