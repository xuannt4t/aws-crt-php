<?php

namespace App\Models;

use App\Enums\DataScope;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Support\DataScopeResolver;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_unit_id',
        'project_id',
        'parent_id',
        'creator_id',
        'assignee_id',
        'title',
        'description',
        'status',
        'priority',
        'progress',
        'planned_quantity',
        'actual_quantity',
        'quantity_unit',
        'due_at',
        'completed_at',
        'task_recurrence_id',
        'recurrence_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'progress' => 'integer',
            'planned_quantity' => 'integer',
            'actual_quantity' => 'integer',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'recurrence_date' => 'date',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->latest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(TaskRecurrence::class, 'task_recurrence_id')->withTrashed();
    }

    /**
     * Tính phần trăm tiến độ từ số lượng. Đây là nơi duy nhất giữ công thức này.
     */
    public static function progressFromQuantity(int $planned, int $actual): int
    {
        if ($planned <= 0) {
            return 0;
        }

        return min(100, (int) round($actual / $planned * 100));
    }

    public function tracksQuantity(): bool
    {
        return $this->planned_quantity !== null;
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->where('due_at', '<', now());
    }

    /**
     * Giới hạn danh sách công việc theo phạm vi dữ liệu hiệu lực của người dùng
     * (spec §4.1). Đây là định nghĩa DUY NHẤT của "công việc nào người dùng thấy
     * được" — TaskPolicy::view() hỏi lại chính scope này thay vì viết lại điều
     * kiện, để danh sách và xem trực tiếp qua URL luôn đồng nhất.
     *
     * Toàn bộ điều kiện "own" (và phần bổ sung của "department") nằm trong MỘT
     * closure where(...) duy nhất — nếu tách các orWhere ra ngoài, chúng sẽ phá
     * vỡ những điều kiện lọc khác đã có sẵn trên query (tìm kiếm, trạng thái,
     * độ ưu tiên, người phụ trách, quá hạn...).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = app(DataScopeResolver::class)->forTasks($user);

        if ($scope === DataScope::All) {
            return $query;
        }

        if ($scope === DataScope::Own || $user->organization_unit_id === null) {
            return $query->where(fn (Builder $q) => $this->addOwnConditions($q, $user));
        }

        $unitIds = OrganizationUnit::descendantIdsOf($user->organization_unit_id);

        return $query->where(function (Builder $q) use ($user, $unitIds): void {
            $this->addOwnConditions($q, $user);
            $q->orWhereIn('organization_unit_id', $unitIds);
        });
    }

    /**
     * Thêm các điều kiện "own" (spec §4.1, mục 1-4) vào một closure where() đã
     * có sẵn. Điều kiện việc con chỉ đi đúng một cấp — ứng dụng dùng mô hình
     * việc cha/việc con một cấp, không truy ngược đệ quy.
     */
    private function addOwnConditions(Builder $query, User $user): void
    {
        $query
            ->where('assignee_id', $user->id)
            ->orWhere('creator_id', $user->id)
            ->orWhereHas(
                'project',
                fn (Builder $projectQuery) => $projectQuery->whereHas(
                    'members',
                    fn (Builder $memberQuery) => $memberQuery->where('user_id', $user->id),
                ),
            )
            ->orWhereHas('parent', function (Builder $parentQuery) use ($user): void {
                $parentQuery
                    ->where('assignee_id', $user->id)
                    ->orWhere('creator_id', $user->id)
                    ->orWhereHas(
                        'project',
                        fn (Builder $projectQuery) => $projectQuery->whereHas(
                            'members',
                            fn (Builder $memberQuery) => $memberQuery->where('user_id', $user->id),
                        ),
                    );
            });
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! in_array($this->status, [TaskStatus::Completed, TaskStatus::Cancelled], true);
    }
}
