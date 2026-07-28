<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    active: boolean;
    activeLabel?: string;
    inactiveLabel?: string;
}>();

const label = computed(() =>
    props.active ? (props.activeLabel ?? 'Hoạt động') : (props.inactiveLabel ?? 'Ngừng hoạt động'),
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
        :class="
            active ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200'
        "
    >
        <span class="size-1.5 rounded-full" :class="active ? 'bg-emerald-500' : 'bg-slate-400'" />
        {{ label }}
    </span>
</template>
