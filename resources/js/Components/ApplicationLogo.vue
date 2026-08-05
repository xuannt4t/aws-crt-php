<script setup lang="ts">
withDefaults(
    defineProps<{
        compact?: boolean;
        // Dùng để ẩn phần chữ theo breakpoint (ví dụ `lg:hidden` khi sidebar thu
        // nhỏ). `compact` bỏ hẳn phần chữ khỏi DOM nên không đáp ứng được nhu cầu
        // "chỉ ẩn ở màn rộng, vẫn hiện trong ngăn kéo trên mobile".
        textClass?: string;
    }>(),
    {
        compact: false,
        textClass: '',
    },
);
</script>

<template>
    <div class="inline-flex items-center gap-3">
        <svg class="size-10 shrink-0" viewBox="0 0 40 40" fill="none" aria-hidden="true">
            <rect width="40" height="40" rx="12" fill="#319B82" />
            <path
                d="M10.5 14.25C10.5 12.18 12.18 10.5 14.25 10.5H20C25.25 10.5 29.5 14.75 29.5 20C29.5 25.25 25.25 29.5 20 29.5H14.25C12.18 29.5 10.5 27.82 10.5 25.75V14.25Z"
                fill="white"
                fill-opacity="0.96"
            />
            <path
                d="M16 15.75H19.8C22.64 15.75 24.95 18.06 24.95 20.9C24.95 23.74 22.64 26.05 19.8 26.05H16V15.75Z"
                fill="#319B82"
            />
            <path d="M16 10.5V29.5" stroke="#D9F3EB" stroke-width="1.5" />
        </svg>

        <span v-if="!compact" class="flex flex-col text-left" :class="textClass">
            <span class="font-display text-[15px] font-extrabold leading-4 tracking-[-0.02em] text-current">
                DORMIDA
            </span>
            <span class="text-[10px] font-semibold uppercase tracking-[0.24em] text-current opacity-55">Work</span>
        </span>
    </div>
</template>
