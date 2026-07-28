<script setup lang="ts">
type IconName =
    | 'arrow-left'
    | 'arrow-right'
    | 'building'
    | 'calendar'
    | 'check'
    | 'chevron-right'
    | 'dashboard'
    | 'edit'
    | 'filter'
    | 'folder'
    | 'logout'
    | 'menu'
    | 'plus'
    | 'report'
    | 'shield'
    | 'sparkles'
    | 'tasks'
    | 'trash'
    | 'user'
    | 'users'
    | 'x';

defineProps<{
    name: IconName;
}>();
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-if="name === 'dashboard'" d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-12h6V4h-6v4Z" />
        <path
            v-else-if="name === 'building'"
            d="M3 21h18M6 21V6l6-3 6 3v15M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"
        />
        <path
            v-else-if="name === 'users'"
            d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"
        />
        <path v-else-if="name === 'user'" d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" />
        <path
            v-else-if="name === 'tasks'"
            d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"
        />
        <path
            v-else-if="name === 'folder'"
            d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"
        />
        <path v-else-if="name === 'calendar'" d="M6 2v4M18 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v15H3V6a2 2 0 0 1 2-2Z" />
        <path v-else-if="name === 'report'" d="M4 19V9M10 19V5M16 19v-7M22 19H2" />
        <path v-else-if="name === 'shield'" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM9 12l2 2 4-4" />
        <path v-else-if="name === 'menu'" d="M4 6h16M4 12h16M4 18h16" />
        <path v-else-if="name === 'x'" d="M18 6 6 18M6 6l12 12" />
        <path v-else-if="name === 'plus'" d="M12 5v14M5 12h14" />
        <path v-else-if="name === 'edit'" d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4L16.5 3.5Z" />
        <path v-else-if="name === 'trash'" d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v5M14 11v5" />
        <path v-else-if="name === 'logout'" d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5" />
        <path v-else-if="name === 'filter'" d="M4 5h16M7 12h10M10 19h4" />
        <path v-else-if="name === 'check'" d="m5 12 4 4L19 6" />
        <path
            v-else-if="name === 'sparkles'"
            d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3ZM5 14l-.8 2.2L2 17l2.2.8L5 20l.8-2.2L8 17l-2.2-.8L5 14ZM19 14l-.8 2.2L16 17l2.2.8L19 20l.8-2.2L22 17l-2.2-.8L19 14Z"
        />
        <path v-else-if="name === 'arrow-left'" d="m15 18-6-6 6-6" />
        <path v-else-if="name === 'arrow-right'" d="m9 18 6-6-6-6" />
        <path v-else-if="name === 'chevron-right'" d="m9 18 6-6-6-6" />
    </svg>
</template>
