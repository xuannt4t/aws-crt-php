<?php

namespace App\Models;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectTaskVisibility;
use Database\Factories\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectMember extends Model
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'task_visibility',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectMemberRole::class,
            'task_visibility' => ProjectTaskVisibility::class,
            'joined_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Phạm vi xem việc hiệu lực của thành viên này trong việc dự án. Đây là nơi
     * DUY NHẤT diễn giải quy tắc "quản lý việc dự án luôn thấy toàn bộ việc" —
     * bất kể cột task_visibility lưu giá trị gì.
     */
    public function effectiveTaskVisibility(): ProjectTaskVisibility
    {
        if ($this->role === ProjectMemberRole::Manager) {
            return ProjectTaskVisibility::All;
        }

        return $this->task_visibility;
    }
}
