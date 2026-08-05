import type { TaskContext, TaskPriority, TaskStatus, TaskStatusBucket } from '@/types';

export const taskStatusLabels: Record<TaskStatus, string> = {
    draft: 'Nháp',
    todo: 'Cần làm',
    in_progress: 'Đang thực hiện',
    waiting_review: 'Chờ duyệt',
    completed: 'Hoàn thành',
    cancelled: 'Đã hủy',
};

export const taskStatusClasses: Record<TaskStatus, string> = {
    draft: 'bg-slate-100 text-slate-600',
    todo: 'bg-blue-50 text-blue-700',
    in_progress: 'bg-amber-50 text-amber-700',
    waiting_review: 'bg-violet-50 text-violet-700',
    completed: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-red-50 text-red-700',
};

export const taskPriorityLabels: Record<TaskPriority, string> = {
    low: 'Thấp',
    medium: 'Trung bình',
    high: 'Cao',
    urgent: 'Khẩn cấp',
};

/** Nhãn tiêu đề của ba màn công việc (spec §5.1, §7), khớp App\Enums\TaskContext::label(). */
export const taskContextLabels: Record<TaskContext, string> = {
    overview: 'Tổng quan việc',
    project: 'Việc dự án',
    department: 'Việc phòng ban',
};

/** Mô tả phụ theo bối cảnh, dùng cho phần header của trang. */
export const taskContextDescriptions: Record<TaskContext, string> = {
    overview: 'Theo dõi mọi đầu việc trong phạm vi bạn được xem, người phụ trách, thời hạn và trạng thái thực hiện.',
    project: 'Chỉ hiển thị công việc thuộc dự án, trong phạm vi bạn được xem.',
    department: 'Chỉ hiển thị công việc thuộc phòng ban, trong phạm vi bạn được xem.',
};

/**
 * Nhãn các nhóm trạng thái đứng sau ô tóm tắt — khớp App\Enums\TaskStatusBucket.
 * Ghi kèm những trạng thái mà nhóm đó gộp lại, vì "Chưa làm" gồm cả nháp lẫn cần
 * làm và người dùng không đoán được điều đó từ cái tên.
 */
export const taskStatusBucketLabels: Record<TaskStatusBucket, string> = {
    not_started: 'Chưa làm (nháp, cần làm)',
    in_progress: 'Đang làm',
    waiting_approval: 'Chờ duyệt',
    completed: 'Hoàn thành',
};
