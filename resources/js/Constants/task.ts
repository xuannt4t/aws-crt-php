import type { TaskPriority, TaskStatus } from '@/types';

export const taskStatusLabels: Record<TaskStatus, string> = {
    draft: 'Nháp',
    todo: 'Cần làm',
    in_progress: 'Đang thực hiện',
    waiting_review: 'Chờ kiểm tra',
    waiting_approval: 'Chờ phê duyệt',
    completed: 'Hoàn thành',
    cancelled: 'Đã hủy',
};

export const taskStatusClasses: Record<TaskStatus, string> = {
    draft: 'bg-slate-100 text-slate-600',
    todo: 'bg-blue-50 text-blue-700',
    in_progress: 'bg-amber-50 text-amber-700',
    waiting_review: 'bg-violet-50 text-violet-700',
    waiting_approval: 'bg-fuchsia-50 text-fuchsia-700',
    completed: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-red-50 text-red-700',
};

export const taskPriorityLabels: Record<TaskPriority, string> = {
    low: 'Thấp',
    medium: 'Trung bình',
    high: 'Cao',
    urgent: 'Khẩn cấp',
};

export const taskPriorityClasses: Record<TaskPriority, string> = {
    low: 'text-slate-500',
    medium: 'text-blue-600',
    high: 'text-amber-700',
    urgent: 'text-red-700',
};
