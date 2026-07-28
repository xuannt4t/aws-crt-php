<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import TaskForm from '@/Components/TaskForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { OrganizationUnit, Task, TaskPriority, User } from '@/types';

defineProps<{
    task: Pick<Task, 'id' | 'organization_unit_id' | 'assignee_id' | 'title' | 'description' | 'priority' | 'due_at'>;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    assignableUsers: Pick<User, 'id' | 'name'>[];
    priorities: TaskPriority[];
}>();
</script>

<template>
    <Head :title="`Sửa ${task.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="task.title"
                description="Cập nhật nội dung công việc; trạng thái được quản lý bằng luồng chuyển riêng."
                eyebrow="Chỉnh sửa công việc"
            >
                <template #actions>
                    <Link :href="route('tasks.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Quay lại
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <TaskForm
            :task="task"
            :organization-units="organizationUnits"
            :assignable-users="assignableUsers"
            :priorities="priorities"
        />
    </AuthenticatedLayout>
</template>
