<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import TaskRecurrenceForm from '@/Components/TaskRecurrenceForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, RecurrenceFrequency, TaskPriority, User } from '@/types';

defineProps<{
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    assignableUsers: Pick<User, 'id' | 'name'>[];
    priorities: TaskPriority[];
    frequencies: { value: RecurrenceFrequency; label: string }[];
    projects: Pick<Project, 'id' | 'name' | 'code'>[];
}>();
</script>

<template>
    <Head title="Tạo mẫu công việc định kỳ" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Tạo mẫu công việc định kỳ"
                description="Thiết lập chu kỳ để hệ thống tự động sinh công việc theo lịch."
                eyebrow="Task Core"
            >
                <template #actions>
                    <Link :href="route('task-recurrences.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Quay lại
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <TaskRecurrenceForm
            :organization-units="organizationUnits"
            :assignable-users="assignableUsers"
            :priorities="priorities"
            :frequencies="frequencies"
            :projects="projects"
        />
    </AuthenticatedLayout>
</template>
