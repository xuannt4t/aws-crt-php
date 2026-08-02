<script setup lang="ts">
import { projectStatusLabels } from '@/Constants/project';
import type { ProjectStatus } from '@/types';

defineProps<{
    status: ProjectStatus;
}>();

const dotClasses: Record<ProjectStatus, string> = {
    planning: 'bg-slate-400',
    active: 'bg-amber-500',
    on_hold: 'bg-fuchsia-500',
    completed: 'bg-emerald-500',
    cancelled: 'bg-red-500',
};

const badgeClasses: Record<ProjectStatus, string> = {
    planning: 'bg-slate-100 text-slate-600',
    active: 'bg-amber-50 text-amber-700',
    on_hold: 'bg-fuchsia-50 text-fuchsia-700',
    completed: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-red-50 text-red-700',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold"
        :class="badgeClasses[status]"
        :aria-label="`Trạng thái ${projectStatusLabels[status]}`"
    >
        <span class="size-1.5 shrink-0 rounded-full" :class="dotClasses[status]" aria-hidden="true" />
        {{ projectStatusLabels[status] }}
    </span>
</template>
