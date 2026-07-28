<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        avatarUrl?: string | null;
        size?: 'sm' | 'md' | 'lg' | 'xl';
    }>(),
    {
        avatarUrl: null,
        size: 'md',
    },
);

const imageFailed = ref(false);

watch(
    () => props.avatarUrl,
    () => {
        imageFailed.value = false;
    },
);

const initials = computed(() =>
    props.name
        .trim()
        .split(/\s+/)
        .slice(-2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const sizeClass = computed(() => ({
    'size-8 text-[11px]': props.size === 'sm',
    'size-10 text-xs': props.size === 'md',
    'size-12 text-sm': props.size === 'lg',
    'size-20 text-xl': props.size === 'xl',
}));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand-100 font-bold text-brand-800 ring-1 ring-inset ring-brand-200"
        :class="sizeClass"
        aria-hidden="true"
    >
        <img
            v-if="avatarUrl && !imageFailed"
            :src="avatarUrl"
            alt=""
            class="size-full object-cover"
            @error="imageFailed = true"
        />
        <template v-else>{{ initials }}</template>
    </span>
</template>
