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

export type TaskStatus =
    'draft' | 'todo' | 'in_progress' | 'waiting_review' | 'waiting_approval' | 'completed' | 'cancelled';

export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';

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
