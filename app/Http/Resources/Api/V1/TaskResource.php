<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\TaskStatus;
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
            'task_recurrence_id' => $this->task_recurrence_id,
            'recurrence_date' => $this->recurrence_date?->toDateString(),
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
            'permissions' => $request->user() ? [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
                'dispatch' => $this->status === TaskStatus::Draft && $request->user()->can('dispatch', $this->resource),
                'start' => $this->status === TaskStatus::Todo && $request->user()->can('start', $this->resource),
                'submit' => $this->status === TaskStatus::InProgress && $request->user()->can('submit', $this->resource),
                'recall' => $this->status === TaskStatus::WaitingReview && $request->user()->can('recall', $this->resource),
                'approve' => $this->status === TaskStatus::WaitingReview && $request->user()->can('approve', $this->resource),
                'reject' => $this->status === TaskStatus::WaitingReview && $request->user()->can('reject', $this->resource),
                'update_progress' => $this->status === TaskStatus::InProgress && $request->user()->can('updateProgress', $this->resource),
                'comment' => $request->user()->can('comment', $this->resource),
                'attach' => $request->user()->can('attach', $this->resource),
            ] : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
