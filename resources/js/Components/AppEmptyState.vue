<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        icon?: 'building' | 'users' | 'tasks' | 'folder' | 'shield';
    }>(),
    {
        icon: 'tasks',
    },
);
</script>

<template>
    <div class="flex flex-col items-center px-6 py-14 text-center">
        <div
            class="mb-5 flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 ring-1 ring-brand-100"
        >
            <AppIcon :name="icon" class="size-6" />
        </div>
        <h3 class="font-display text-base font-bold text-ink-950">{{ title }}</h3>
        <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </div>
</template>
