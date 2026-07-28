<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-2 text-xs font-bold uppercase tracking-[0.16em] text-brand-700">
                {{ eyebrow }}
            </p>
            <h1 class="font-display text-2xl font-extrabold tracking-[-0.035em] text-ink-950 sm:text-[2rem]">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
