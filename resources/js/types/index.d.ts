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

export interface Task {
    id: number;
    organization_unit_id: number;
    parent_id: number | null;
    creator_id: number;
    assignee_id: number | null;
    title: string;
    description: string | null;
    description_html?: string | null;
    status: TaskStatus;
    priority: TaskPriority;
    progress: number;
    due_at: string | null;
    completed_at: string | null;
    is_overdue?: boolean;
    organization_unit?: Pick<OrganizationUnit, 'id' | 'name'>;
    creator?: Pick<User, 'id' | 'name' | 'avatar_url'>;
    assignee?: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
    status_histories?: TaskStatusHistory[];
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
