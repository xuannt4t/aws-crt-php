<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import ProjectForm from '@/Components/ProjectForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, ProjectStatus, UserOption } from '@/types';

defineProps<{
    project: Pick<
        Project,
        | 'id'
        | 'organization_unit_id'
        | 'owner_id'
        | 'code'
        | 'name'
        | 'description'
        | 'status'
        | 'start_date'
        | 'end_date'
    >;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    users: UserOption[];
    statuses: ProjectStatus[];
}>();
</script>

<template>
    <Head :title="`Sửa ${project.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="project.name"
                description="Cập nhật thông tin, phân công và lịch trình của việc dự án."
                eyebrow="Chỉnh sửa việc dự án"
            >
                <template #actions>
                    <Link :href="route('projects.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Quay lại
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <ProjectForm :project="project" :organization-units="organizationUnits" :users="users" :statuses="statuses" />
    </AuthenticatedLayout>
</template>
