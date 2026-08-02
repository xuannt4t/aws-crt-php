<?php

namespace App\Models;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Trạng thái được xem là "đã đóng" — nguồn sự thật duy nhất, dùng bởi
     * isClosed(), scopeOpen() và mọi rule xác thực cần biết dự án nào đã đóng.
     *
     * @var list<ProjectStatus>
     */
    private const CLOSED_STATUSES = [ProjectStatus::Completed, ProjectStatus::Cancelled];

    protected $fillable = [
        'organization_unit_id',
        'owner_id',
        'code',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'closed_at',
        'close_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role', 'joined_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function openTasks(): HasMany
    {
        return $this->tasks()->whereNotIn('status', [
            TaskStatus::Completed->value,
            TaskStatus::Cancelled->value,
        ]);
    }

    public function calculateProgress(): int
    {
        $progress = $this->tasks()
            ->where('status', '!=', TaskStatus::Cancelled->value)
            ->avg('progress');

        if ($progress === null) {
            return 0;
        }

        return (int) round((float) $progress);
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function isManager(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', ProjectMemberRole::Manager->value)
            ->exists();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::closedStatusValues());
    }

    /**
     * @return list<string>
     */
    public static function closedStatusValues(): array
    {
        return array_map(static fn (ProjectStatus $status): string => $status->value, self::CLOSED_STATUSES);
    }
}
