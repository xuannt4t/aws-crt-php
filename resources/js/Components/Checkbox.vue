<script setup lang="ts">
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps<{
    checked: boolean;
    value?: string | number | boolean;
}>();

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        v-model="proxyChecked"
        type="checkbox"
        :value="value"
        class="size-4 rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 focus:ring-offset-2"
    />
</template>
