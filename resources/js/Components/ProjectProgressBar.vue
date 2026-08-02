<script setup lang="ts">
withDefaults(
    defineProps<{
        progress: number;
        showLabel?: boolean;
    }>(),
    {
        showLabel: true,
    },
);
</script>

<template>
    <div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-brand-600" :style="{ width: `${progress}%` }" />
        </div>
        <p v-if="showLabel" class="mt-1.5 text-xs font-semibold text-slate-500">{{ progress }}%</p>
    </div>
</template>
