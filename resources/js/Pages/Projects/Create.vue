<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import ProjectForm from '@/Components/ProjectForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { OrganizationUnit, ProjectStatus, UserOption } from '@/types';

defineProps<{
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    users: UserOption[];
    statuses: ProjectStatus[];
}>();
</script>

<template>
    <Head title="Tạo dự án" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Tạo dự án"
                description="Khởi tạo dự án mới và chỉ định đơn vị sở hữu, chủ dự án."
                eyebrow="Project Core"
            >
                <template #actions>
                    <Link :href="route('projects.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Quay lại
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <ProjectForm :organization-units="organizationUnits" :users="users" :statuses="statuses" />
    </AuthenticatedLayout>
</template>
