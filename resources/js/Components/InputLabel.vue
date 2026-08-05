<script setup lang="ts">
// Dấu * bắt buộc do chính component này vẽ ra, không viết thẳng vào chuỗi nhãn —
// nhờ vậy màu sắc và khoảng cách của nó chỉ được định nghĩa ở một chỗ duy nhất.
defineProps<{
    value?: string;
    required?: boolean;
}>();
</script>

<template>
    <label class="app-label">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
        <span v-if="required" class="app-required" aria-hidden="true">*</span>
    </label>
</template>
