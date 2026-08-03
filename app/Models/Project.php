<?php

namespace App\Models;

use App\Enums\DataScope;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Support\DataScopeResolver;
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
        if ($this->relationLoaded('members')) {
            return $this->members->contains(
                static fn (ProjectMember $member): bool => (int) $member->user_id === (int) $user->id,
            );
        }

        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function isManager(User $user): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains(
                static fn (ProjectMember $member): bool => (int) $member->user_id === (int) $user->id
                    && $member->role === ProjectMemberRole::Manager,
            );
        }

        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', ProjectMemberRole::Manager->value)
            ->exists();
    }

    /**
     * Giới hạn danh sách dự án theo phạm vi dữ liệu hiệu lực của người dùng
     * (spec §4.2). Đây là định nghĩa DUY NHẤT của "dự án nào người dùng thấy
     * được" — mở rộng từ Project::scopeVisibleTo() có sẵn từ Sprint 3, không
     * phải cơ chế mới song song. ProjectPolicy::view() hỏi lại chính scope
     * này thay vì viết lại điều kiện, để danh sách và xem trực tiếp qua URL
     * luôn đồng nhất.
     *
     * Toàn bộ điều kiện "own" (và phần bổ sung của "department") nằm trong MỘT
     * closure where(...) duy nhất — nếu tách các orWhere ra ngoài, chúng sẽ phá
     * vỡ những điều kiện lọc khác đã có sẵn trên query (tìm kiếm, trạng thái,
     * đơn vị, chủ dự án, only_mine...).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = app(DataScopeResolver::class)->forProjects($user);

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
     * Thêm điều kiện "own" (spec §4.2) vào một closure where() đã có sẵn: dự
     * án mà người dùng là thành viên. Owner luôn là thành viên vai trò
     * manager nên chủ dự án tự nhiên nằm trong phạm vi này.
     */
    private function addOwnConditions(Builder $query, User $user): void
    {
        $query->whereHas(
            'members',
            fn (Builder $inner) => $inner->where('user_id', $user->id),
        );
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
