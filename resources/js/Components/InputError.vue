<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="flex items-start gap-1.5 text-sm font-medium text-red-600">
            <span class="mt-[7px] size-1 shrink-0 rounded-full bg-red-500" />
            {{ message }}
        </p>
    </div>
</template>
