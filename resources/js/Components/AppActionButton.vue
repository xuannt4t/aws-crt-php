<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link } from '@inertiajs/vue3';

type ActionIcon = 'edit' | 'trash' | 'lock' | 'unlock';
type ActionTone = 'info' | 'danger' | 'warning' | 'success';

const props = withDefaults(
    defineProps<{
        label: string;
        icon: ActionIcon;
        tone?: ActionTone;
        href?: string;
        disabled?: boolean;
    }>(),
    {
        tone: 'info',
        href: undefined,
        disabled: false,
    },
);

defineEmits<{
    click: [event: MouseEvent];
}>();

const toneClasses = computed(
    () =>
        ({
            info: 'border-sky-200 bg-sky-50 text-sky-700 hover:border-sky-300 hover:bg-sky-100 focus:ring-sky-200',
            danger: 'border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100 focus:ring-red-200',
            warning:
                'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-300 hover:bg-amber-100 focus:ring-amber-200',
            success:
                'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100 focus:ring-emerald-200',
        })[props.tone],
);

const buttonClasses = computed(() => [
    'inline-flex size-9 shrink-0 items-center justify-center rounded-lg border shadow-sm transition hover:-translate-y-px focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0',
    toneClasses.value,
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="buttonClasses" :aria-label="label" :title="label">
        <AppIcon :name="icon" class="size-[18px]" />
    </Link>
    <button
        v-else
        type="button"
        :class="buttonClasses"
        :aria-label="label"
        :title="label"
        :disabled="disabled"
        @click="$emit('click', $event)"
    >
        <AppIcon :name="icon" class="size-[18px]" />
    </button>
</template>
