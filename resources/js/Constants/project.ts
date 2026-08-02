import type { ProjectMemberRole, ProjectStatus } from '@/types';

export const projectStatusLabels: Record<ProjectStatus, string> = {
    planning: 'Lên kế hoạch',
    active: 'Đang thực hiện',
    on_hold: 'Tạm dừng',
    completed: 'Hoàn thành',
    cancelled: 'Đã huỷ',
};

export const projectStatusClasses: Record<ProjectStatus, string> = {
    planning: 'bg-slate-100 text-slate-600',
    active: 'bg-amber-50 text-amber-700',
    on_hold: 'bg-fuchsia-50 text-fuchsia-700',
    completed: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-red-50 text-red-700',
};

export const projectMemberRoleLabels: Record<ProjectMemberRole, string> = {
    manager: 'Quản lý dự án',
    member: 'Thành viên',
    viewer: 'Người theo dõi',
};
