<script setup lang="ts">
import { computed } from 'vue';
import type { TaskSummary, TaskSummaryCardKey } from '@/types';

const props = defineProps<{
    summary: TaskSummary;
    /** Ô đang được chọn, suy ra từ bộ lọc hiện tại của trang. */
    activeKey?: TaskSummaryCardKey;
}>();

const emit = defineEmits<{
    select: [key: TaskSummaryCardKey];
}>();

interface SummaryCard {
    key: TaskSummaryCardKey;
    label: string;
    value: number;
    tone: 'default' | 'success' | 'warning';
}

// Khúc 1 (spec §6.1): sáu ô số, KHÔNG biểu đồ, KHÔNG phần trăm tiến độ. Năm ô
// đầu loại trừ lẫn nhau (trừ `cancelled` không rơi vào ô nào), ô trễ hạn cắt
// ngang các ô kia nên tổng không nhất thiết khớp ô tổng — đó là chủ ý.
const cards = computed<SummaryCard[]>(() => [
    { key: 'total', label: 'Tổng đầu việc', value: props.summary.total, tone: 'default' },
    { key: 'not_started', label: 'Chưa làm', value: props.summary.not_started, tone: 'default' },
    { key: 'in_progress', label: 'Đang làm', value: props.summary.in_progress, tone: 'default' },
    { key: 'waiting_approval', label: 'Chờ duyệt', value: props.summary.waiting_approval, tone: 'default' },
    { key: 'completed', label: 'Hoàn thành', value: props.summary.completed, tone: 'success' },
    { key: 'overdue', label: 'Trễ hạn', value: props.summary.overdue, tone: 'warning' },
]);

const toneCardClasses: Record<SummaryCard['tone'], string> = {
    default: 'border-slate-100 bg-slate-50/60',
    success: 'border-emerald-100 bg-emerald-50/60',
    warning: 'border-red-200 bg-red-50',
};

const toneLabelClasses: Record<SummaryCard['tone'], string> = {
    default: 'text-slate-500',
    success: 'text-emerald-700',
    warning: 'text-red-600',
};

const toneValueClasses: Record<SummaryCard['tone'], string> = {
    default: 'text-ink-950',
    success: 'text-emerald-700',
    warning: 'text-red-700',
};

// Viền đậm hơn hẳn khi ô đang được chọn. Chỉ đổi nền thôi thì ở ô "Trễ hạn" và
// "Hoàn thành" gần như không thấy khác biệt, vì hai ô đó vốn đã có nền màu.
const toneActiveClasses: Record<SummaryCard['tone'], string> = {
    default: 'border-brand-500 bg-brand-50 ring-1 ring-brand-500',
    success: 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-500',
    warning: 'border-red-500 bg-red-100 ring-1 ring-red-500',
};

const isActive = (card: SummaryCard): boolean => (props.activeKey ?? 'total') === card.key;

// `earliest_created` là MIN(created_at) — tasks không có cột ngày bắt đầu
// riêng — nên nhãn phải trung thực, KHÔNG được ghi là "ngày bắt đầu".
const formatMarker = (value: string | null) => {
    if (!value) {
        return 'Chưa có dữ liệu';
    }

    return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium' }).format(new Date(value));
};
</script>

<template>
    <section class="app-panel p-5 sm:p-6">
        <h2 class="font-display text-base font-bold text-ink-950">Tóm tắt thống kê</h2>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <button
                v-for="card in cards"
                :key="card.key"
                type="button"
                class="rounded-xl border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-panel focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                :class="isActive(card) ? toneActiveClasses[card.tone] : toneCardClasses[card.tone]"
                :aria-pressed="isActive(card)"
                @click="emit('select', card.key)"
            >
                <p class="text-[11px] font-bold uppercase tracking-wide" :class="toneLabelClasses[card.tone]">
                    {{ card.label }}
                </p>
                <p class="mt-2 text-2xl font-extrabold" :class="toneValueClasses[card.tone]">
                    {{ card.value }}
                </p>
            </button>
        </div>

        <div class="mt-5 flex flex-wrap gap-x-8 gap-y-3 border-t border-slate-100 pt-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Việc tạo sớm nhất</p>
                <p class="mt-1 text-sm font-semibold text-slate-700">{{ formatMarker(summary.earliest_created) }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Hạn muộn nhất</p>
                <p class="mt-1 text-sm font-semibold text-slate-700">{{ formatMarker(summary.latest_due) }}</p>
            </div>
        </div>
    </section>
</template>
