<script setup lang="ts">
import { ref } from 'vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { router, useForm } from '@inertiajs/vue3';
import type { TaskAttachment } from '@/types';

const props = defineProps<{
    taskId: number;
    attachments: TaskAttachment[];
    canAttach: boolean;
}>();

const MAX_FILES = 5;

const form = useForm<{ files: File[] }>({ files: [] });
const fileInput = ref<HTMLInputElement | null>(null);
const isDraggingOver = ref(false);
const attachmentPendingDeletion = ref<TaskAttachment | null>(null);
const isDeleting = ref(false);

const addFiles = (incoming: FileList | null) => {
    if (!incoming) {
        return;
    }

    form.files = [...form.files, ...Array.from(incoming)].slice(0, MAX_FILES);
    form.clearErrors();
};

const onFileInputChange = (event: Event) => {
    addFiles((event.target as HTMLInputElement).files);
};

const onDrop = (event: DragEvent) => {
    isDraggingOver.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};

const removeSelected = (index: number) => {
    form.files = form.files.filter((_, position) => position !== index);
};

const resetInput = () => {
    form.reset();

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.post(route('tasks.attachments.store', props.taskId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: resetInput,
    });
};

const confirmDeletion = () => {
    const attachment = attachmentPendingDeletion.value;

    if (!attachment) {
        return;
    }

    isDeleting.value = true;

    router.delete(route('tasks.attachments.destroy', [props.taskId, attachment.id]), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            attachmentPendingDeletion.value = null;
        },
    });
};

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
</script>

<template>
    <section class="app-panel overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <h2 class="font-display text-base font-bold text-ink-950">Tệp đính kèm</h2>
            <p class="mt-1 text-xs text-slate-500">
                {{ attachments.length }} tệp trong công việc này.
            </p>
        </header>

        <form v-if="canAttach" class="border-b border-slate-100 px-5 py-4 sm:px-6" @submit.prevent="submit">
            <div
                class="rounded-xl border-2 border-dashed px-4 py-6 text-center transition"
                :class="isDraggingOver ? 'border-brand-400 bg-brand-50' : 'border-slate-200'"
                @dragover.prevent="isDraggingOver = true"
                @dragleave.prevent="isDraggingOver = false"
                @drop.prevent="onDrop"
            >
                <p class="text-sm text-slate-600">Kéo thả tệp vào đây hoặc</p>
                <button
                    type="button"
                    class="mt-1 text-sm font-semibold text-brand-700 underline"
                    @click="fileInput?.click()"
                >
                    chọn tệp từ máy
                </button>
                <p class="mt-2 text-xs text-slate-400">
                    Tối đa {{ MAX_FILES }} tệp mỗi lần, mỗi tệp không quá 10MB. Hỗ trợ PDF, Word,
                    Excel, PowerPoint, CSV, TXT, ZIP và ảnh.
                </p>
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    @change="onFileInputChange"
                />
            </div>

            <ul v-if="form.files.length" class="mt-3 space-y-2">
                <li
                    v-for="(file, index) in form.files"
                    :key="`${file.name}-${index}`"
                    class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2"
                >
                    <span class="min-w-0 truncate text-sm text-slate-700">{{ file.name }}</span>
                    <button
                        type="button"
                        class="shrink-0 text-xs font-semibold text-slate-500 hover:text-red-600"
                        @click="removeSelected(index)"
                    >
                        Bỏ
                    </button>
                </li>
            </ul>

            <InputError class="mt-2" :message="form.errors.files" />
            <InputError
                v-for="index in MAX_FILES"
                :key="`error-${index}`"
                class="mt-1"
                :message="(form.errors as Record<string, string>)[`files.${index - 1}`]"
            />

            <div class="mt-3 flex items-center gap-3">
                <PrimaryButton :disabled="form.processing || !form.files.length">
                    {{ form.processing ? 'Đang tải lên...' : 'Tải lên' }}
                </PrimaryButton>
                <span v-if="form.progress" class="text-xs text-slate-500">
                    {{ form.progress.percentage }}%
                </span>
            </div>
        </form>

        <ul v-if="attachments.length" class="divide-y divide-slate-100">
            <li
                v-for="attachment in attachments"
                :key="attachment.id"
                class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800">
                        {{ attachment.original_name }}
                    </p>
                    <p class="mt-0.5 truncate text-xs text-slate-500">
                        {{ attachment.size_for_humans }} ·
                        {{ attachment.uploader?.name ?? 'Tài khoản đã xóa' }} ·
                        <time :datetime="attachment.created_at">
                            {{ formatDateTime(attachment.created_at) }}
                        </time>
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <a
                        :href="route('tasks.attachments.download', [taskId, attachment.id])"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:underline"
                    >
                        <AppIcon name="arrow-left" class="size-4 rotate-[-90deg]" />
                        Tải xuống
                    </a>
                    <button
                        v-if="attachment.can_delete"
                        type="button"
                        class="text-xs font-semibold text-slate-500 hover:text-red-600"
                        @click="attachmentPendingDeletion = attachment"
                    >
                        Xoá
                    </button>
                </div>
            </li>
        </ul>

        <p v-else class="px-5 py-6 text-center text-sm text-slate-500">
            Chưa có tệp đính kèm.
            <span v-if="canAttach">Tải tệp đầu tiên lên bằng khu vực phía trên.</span>
        </p>

        <AppConfirmDialog
            :show="attachmentPendingDeletion !== null"
            title="Xoá tệp đính kèm?"
            :description="`Tệp “${attachmentPendingDeletion?.original_name ?? ''}” sẽ không còn hiển thị trong công việc. Thao tác này được ghi vào nhật ký.`"
            confirm-label="Xoá tệp"
            :processing="isDeleting"
            @cancel="attachmentPendingDeletion = null"
            @confirm="confirmDeletion"
        />
    </section>
</template>
