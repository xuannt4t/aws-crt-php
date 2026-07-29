<?php

namespace App\Models;

use App\Enums\TaskActivityType;
use Database\Factories\TaskActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TaskActivity extends Model
{
    /** @use HasFactory<TaskActivityFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'actor_id',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskActivityType::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
