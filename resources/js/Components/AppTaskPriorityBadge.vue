<script setup lang="ts">
import { computed } from 'vue';
import { taskPriorityLabels } from '@/Constants/task';
import type { TaskPriority } from '@/types';

const props = withDefaults(
    defineProps<{
        priority: TaskPriority;
        showPrefix?: boolean;
    }>(),
    {
        showPrefix: false,
    },
);

const badgeClasses = computed(
    () =>
        ({
            low: 'border-slate-200 bg-slate-50 text-slate-700',
            medium: 'border-sky-200 bg-sky-50 text-sky-700',
            high: 'border-amber-200 bg-amber-50 text-amber-800',
            urgent: 'border-red-200 bg-red-50 text-red-700',
        })[props.priority],
);

const dotClasses = computed(
    () =>
        ({
            low: 'bg-slate-400',
            medium: 'bg-sky-500',
            high: 'bg-amber-500',
            urgent: 'bg-red-500',
        })[props.priority],
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-[11px] font-bold leading-none"
        :class="badgeClasses"
        :aria-label="`Mức ưu tiên ${taskPriorityLabels[priority]}`"
    >
        <span class="size-1.5 shrink-0 rounded-full" :class="dotClasses" aria-hidden="true" />
        <span v-if="showPrefix" class="font-semibold opacity-75">Ưu tiên</span>
        {{ taskPriorityLabels[priority] }}
    </span>
</template>
