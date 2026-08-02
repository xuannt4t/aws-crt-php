<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import TaskForm from '@/Components/TaskForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, TaskPriority, User } from '@/types';

defineProps<{
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    assignableUsers: Pick<User, 'id' | 'name'>[];
    priorities: TaskPriority[];
    projects: Pick<Project, 'id' | 'name' | 'code'>[];
}>();
</script>

<template>
    <Head title="Tạo công việc" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Tạo công việc"
                description="Ghi nhận đầu việc mới ở trạng thái nháp trước khi đưa vào luồng thực hiện."
                eyebrow="Task Core"
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
            :organization-units="organizationUnits"
            :assignable-users="assignableUsers"
            :priorities="priorities"
            :projects="projects"
        />
    </AuthenticatedLayout>
</template>
