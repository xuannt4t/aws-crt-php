<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use Database\Factories\TaskRecurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TaskRecurrence extends Model
{
    /** @use HasFactory<TaskRecurrenceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_unit_id',
        'project_id',
        'creator_id',
        'assignee_id',
        'title',
        'description',
        'priority',
        'planned_quantity',
        'quantity_unit',
        'frequency',
        'interval',
        'weekdays',
        'day_of_month',
        'start_date',
        'due_time',
        'is_active',
        'last_generated_for',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurrenceFrequency::class,
            'priority' => TaskPriority::class,
            'weekdays' => 'array',
            'interval' => 'integer',
            'day_of_month' => 'integer',
            'planned_quantity' => 'integer',
            'start_date' => 'date',
            'last_generated_for' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->latest('recurrence_date');
    }
}
