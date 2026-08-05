export interface OrganizationUnit {
    id: number;
    parent_id: number | null;
    name: string;
    code: string;
    is_active: boolean;
}

export interface Role {
    id: number;
    name: string;
}

export interface PermissionMatrixRole {
    id: number;
    name: string;
    label: string;
}

export interface PermissionMatrixPermission {
    name: string;
    label: string;
}

export interface PermissionGroup {
    key: string;
    label: string;
    permissions: PermissionMatrixPermission[];
}

/** Map tên vai trò → danh sách tên permission đang được gán. */
export type PermissionMatrix = Record<string, string[]>;

export type TaskStatus = 'draft' | 'todo' | 'in_progress' | 'waiting_review' | 'completed' | 'cancelled';

export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';

/** Ba bối cảnh của màn công việc (spec §5.1) — do route quyết định, không đổi được bằng query string. */
export type TaskContext = 'overview' | 'project' | 'department';

/** Tên các bộ lọc khúc 2 mà server cho phép vẽ (spec §6.2) — luôn là tập con của danh sách này. */
export type TaskFilterKey = 'search' | 'organization_unit_id' | 'project_id' | 'assignee_ids' | 'status' | 'priority';

/**
 * Cấp 2 (spec §5.2) — phạm vi cố định bằng route binding, dùng để đổi tiêu
 * đề trang và hiện nút quay lại danh sách cấp 1. `null` ở bối cảnh overview.
 */
export interface TaskDashboardScope {
    type: 'project' | 'department';
    id: number;
    name: string;
    backRouteName: string;
}

/** Route Inertia dùng để áp bộ lọc/phân trang — do controller quyết định, không suy ra ở client. */
export interface TaskApplyRoute {
    name: string;
    params: Record<string, number>;
}

export interface TaskIndexFilters {
    search?: string;
    status?: TaskStatus;
    priority?: TaskPriority;
    organization_unit_id?: number;
    project_id?: number;
    assignee_ids?: number[];
    overdue?: boolean | string;
}

/**
 * Tóm tắt thống kê của khúc 1 (spec §6.1). `earliest_created` là MIN(created_at)
 * — tasks không có cột ngày bắt đầu riêng — KHÔNG phải "ngày bắt đầu".
 */
export interface TaskSummary {
    total: number;
    not_started: number;
    in_progress: number;
    waiting_approval: number;
    completed: number;
    overdue: number;
    earliest_created: string | null;
    latest_due: string | null;
}

export interface TaskStatusHistory {
    id: number;
    task_id: number;
    actor_id: number;
    from_status: TaskStatus;
    to_status: TaskStatus;
    reason: string | null;
    created_at: string;
    actor?: Pick<User, 'id' | 'name' | 'avatar_url'>;
}

export interface TaskComment {
    id: number;
    task_id: number;
    author_id: number;
    body: string;
    created_at: string;
    author?: Pick<User, 'id' | 'name' | 'avatar_url'>;
}

export interface TaskAttachment {
    id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    size_for_humans: string;
    created_at: string;
    uploader?: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
    can_delete: boolean;
}

export type TaskActivityType =
    | 'created'
    | 'status_changed'
    | 'assigned'
    | 'progress_updated'
    | 'quantity_updated'
    | 'commented'
    | 'attachment_added'
    | 'attachment_removed';

export interface TaskActivity {
    id: number;
    type: TaskActivityType;
    payload: Record<string, string | number | null | string[] | number[]> | null;
    created_at: string;
    actor?: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
}

export interface Task {
    id: number;
    organization_unit_id: number;
    project_id: number | null;
    parent_id: number | null;
    creator_id: number;
    assignee_id: number | null;
    task_recurrence_id?: number | null;
    title: string;
    description: string | null;
    description_html?: string | null;
    status: TaskStatus;
    priority: TaskPriority;
    progress: number;
    planned_quantity: number | null;
    actual_quantity: number | null;
    quantity_unit: string | null;
    due_at: string | null;
    completed_at: string | null;
    is_overdue?: boolean;
    organization_unit?: Pick<OrganizationUnit, 'id' | 'name'>;
    creator?: Pick<User, 'id' | 'name' | 'avatar_url'>;
    assignee?: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
    project?: Pick<Project, 'id' | 'name' | 'code'> | null;
    recurrence?: (Pick<TaskRecurrence, 'id' | 'title'> & { deleted_at?: string | null }) | null;
    status_histories?: TaskStatusHistory[];
}

export type RecurrenceFrequency = 'daily' | 'weekly' | 'monthly' | 'quarterly';

export interface TaskRecurrence {
    id: number;
    organization_unit_id: number;
    project_id: number | null;
    creator_id: number;
    assignee_id: number | null;
    title: string;
    description: string | null;
    priority: TaskPriority;
    planned_quantity: number | null;
    quantity_unit: string | null;
    frequency: RecurrenceFrequency;
    interval: number;
    weekdays: number[] | null;
    day_of_month: number | null;
    start_date: string;
    due_time: string | null;
    is_active: boolean;
    last_generated_for: string | null;
    /**
     * Câu mô tả chu kỳ bằng tiếng Việt do server sinh
     * (xem RecurrenceSchedule::describe()). Chỉ có trên response Index/Show.
     */
    cadence?: string;
    next_occurrence?: string | null;
    organization_unit?: Pick<OrganizationUnit, 'id' | 'name'>;
    creator?: Pick<User, 'id' | 'name' | 'avatar_url'>;
    assignee?: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
    project?: Pick<Project, 'id' | 'name' | 'code'> | null;
}

export type ProjectStatus = 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';

export type ProjectMemberRole = 'manager' | 'member' | 'viewer';

export type ProjectTaskVisibility = 'own' | 'all';

export interface Project {
    id: number;
    organization_unit_id: number;
    owner_id: number;
    code: string;
    name: string;
    description: string | null;
    status: ProjectStatus;
    start_date: string | null;
    end_date: string | null;
    closed_at: string | null;
    close_reason: string | null;
    progress?: number;
    task_count?: number;
    open_task_count?: number;
    member_count?: number;
    organization_unit?: Pick<OrganizationUnit, 'id' | 'name'>;
    owner?: Pick<User, 'id' | 'name'>;
}

export interface ProjectMember {
    id: number;
    project_id: number;
    user_id: number;
    role: ProjectMemberRole;
    task_visibility: ProjectTaskVisibility;
    /** Hiệu lực thực tế: luôn "all" khi role là "manager", bất kể task_visibility. */
    effective_task_visibility: ProjectTaskVisibility;
    joined_at: string | null;
    user?: Pick<User, 'id' | 'name' | 'avatar_url'>;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    organization_unit_id: number | null;
    organization_unit?: Pick<OrganizationUnit, 'id' | 'name'> | null;
    is_system_admin?: boolean;
    is_active: boolean;
    employee_code?: string | null;
    phone?: string | null;
    job_title?: string | null;
    avatar_url: string | null;
    roles?: Role[];
}

/**
 * Một dòng trong ô chọn người (người phụ trách, chủ dự án, thành viên).
 * Do `App\Support\UserOptions` sinh ra — sửa đây thì sửa cả bên đó.
 */
export interface UserOption {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    role: string | null;
    organization_unit_id: number | null;
    organization_unit_name: string | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User;
        roles: string[];
        permissions: string[];
    };
    flash: {
        success: string | null;
        error: string | null;
    };
};
