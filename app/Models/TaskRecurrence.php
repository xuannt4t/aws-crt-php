<?php

namespace App\Models;

use App\Enums\DataScope;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Support\DataScopeResolver;
use Database\Factories\TaskRecurrenceFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Giới hạn danh sách mẫu việc định kỳ theo phạm vi dữ liệu hiệu lực của
     * người dùng (spec §4.3) — dùng lại phạm vi công việc qua
     * DataScopeResolver::forTasks(). Đây là định nghĩa DUY NHẤT của "mẫu nào
     * người dùng thấy được" — TaskRecurrencePolicy::view() hỏi lại chính scope
     * này thay vì viết lại điều kiện.
     *
     * Toàn bộ điều kiện "own" (và phần bổ sung của "department") nằm trong MỘT
     * closure where(...) duy nhất — nếu tách các orWhere ra ngoài, chúng sẽ phá
     * vỡ những điều kiện lọc khác đã có sẵn trên query (tìm kiếm, đơn vị, người
     * phụ trách, tần suất, is_active...).
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
     * Thêm điều kiện "own" (spec §4.3) vào một closure where() đã có sẵn: mẫu
     * người dùng tạo hoặc mẫu giao cho người dùng.
     */
    private function addOwnConditions(Builder $query, User $user): void
    {
        $query
            ->where('creator_id', $user->id)
            ->orWhere('assignee_id', $user->id);
    }
}
