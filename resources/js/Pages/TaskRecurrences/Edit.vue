<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import TaskRecurrenceForm from '@/Components/TaskRecurrenceForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, RecurrenceFrequency, TaskPriority, TaskRecurrence, UserOption } from '@/types';

defineProps<{
    recurrence: Pick<
        TaskRecurrence,
        | 'id'
        | 'organization_unit_id'
        | 'project_id'
        | 'assignee_id'
        | 'title'
        | 'description'
        | 'priority'
        | 'planned_quantity'
        | 'quantity_unit'
        | 'frequency'
        | 'interval'
        | 'weekdays'
        | 'day_of_month'
        | 'start_date'
        | 'due_time'
        | 'is_active'
    >;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    assignableUsers: UserOption[];
    priorities: TaskPriority[];
    frequencies: { value: RecurrenceFrequency; label: string }[];
    projects: Pick<Project, 'id' | 'name' | 'code'>[];
}>();
</script>

<template>
    <Head :title="`Sửa ${recurrence.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="recurrence.title"
                description="Cập nhật nội dung và chu kỳ của mẫu công việc định kỳ."
                eyebrow="Chỉnh sửa mẫu"
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
            :recurrence="recurrence"
            :organization-units="organizationUnits"
            :assignable-users="assignableUsers"
            :priorities="priorities"
            :frequencies="frequencies"
            :projects="projects"
        />
    </AuthenticatedLayout>
</template>
