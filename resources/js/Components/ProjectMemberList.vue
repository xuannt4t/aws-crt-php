<script setup lang="ts">
import { computed, ref } from 'vue';
import AppActionButton from '@/Components/AppActionButton.vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import AppUserSelect from '@/Components/AppUserSelect.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { projectMemberRoleLabels, projectTaskVisibilityLabels } from '@/Constants/project';
import { router, useForm } from '@inertiajs/vue3';
import type { ProjectMember, ProjectMemberRole, ProjectTaskVisibility, UserOption } from '@/types';

const props = defineProps<{
    projectId: number;
    members: ProjectMember[];
    users: UserOption[];
    taskVisibilityOptions: { value: ProjectTaskVisibility; label: string }[];
    canManage: boolean;
}>();

const roles: ProjectMemberRole[] = ['manager', 'member', 'viewer'];

const availableUsers = computed(() => {
    const memberUserIds = new Set(props.members.map((member) => member.user_id));

    return props.users.filter((user) => !memberUserIds.has(user.id));
});

const addForm = useForm({
    user_id: null as number | null,
    role: 'member' as ProjectMemberRole,
    task_visibility: 'own' as ProjectTaskVisibility,
});

const updatingMemberId = ref<number | null>(null);
const memberToRemove = ref<ProjectMember | null>(null);
const isRemoving = ref(false);

const submitAdd = () => {
    addForm.post(route('projects.members.store', props.projectId), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
};

const updateMember = (
    member: ProjectMember,
    changes: { role?: ProjectMemberRole; task_visibility?: ProjectTaskVisibility },
) => {
    updatingMemberId.value = member.id;

    router.patch(
        route('projects.members.update', [props.projectId, member.id]),
        {
            role: changes.role ?? member.role,
            task_visibility: changes.task_visibility ?? member.task_visibility,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingMemberId.value = null;
            },
        },
    );
};

const confirmRemove = () => {
    if (!memberToRemove.value) {
        return;
    }

    isRemoving.value = true;

    router.delete(route('projects.members.destroy', [props.projectId, memberToRemove.value.id]), {
        preserveScroll: true,
        onFinish: () => {
            isRemoving.value = false;
            memberToRemove.value = null;
        },
    });
};
</script>

<template>
    <div>
        <form
            v-if="canManage"
            class="mb-5 flex flex-col gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-4 sm:flex-row sm:items-end"
            @submit.prevent="submitAdd"
        >
            <div class="min-w-0 flex-1">
                <label class="mb-1.5 block text-xs font-bold text-slate-600" for="member-user">Thêm thành viên</label>
                <AppUserSelect
                    v-model="addForm.user_id"
                    input-id="member-user"
                    :options="availableUsers"
                    :invalid="Boolean(addForm.errors.user_id)"
                    placeholder="Chọn người dùng"
                />
                <InputError class="mt-2" :message="addForm.errors.user_id" />
            </div>
            <div class="w-full sm:w-44">
                <label class="mb-1.5 block text-xs font-bold text-slate-600" for="member-role">Vai trò</label>
                <select id="member-role" v-model="addForm.role" class="app-field">
                    <option v-for="role in roles" :key="role" :value="role">
                        {{ projectMemberRoleLabels[role] }}
                    </option>
                </select>
                <InputError class="mt-2" :message="addForm.errors.role" />
            </div>
            <div class="w-full sm:w-48">
                <label class="mb-1.5 block text-xs font-bold text-slate-600" for="member-task-visibility"
                    >Quyền xem việc</label
                >
                <select id="member-task-visibility" v-model="addForm.task_visibility" class="app-field">
                    <option v-for="option in taskVisibilityOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <InputError class="mt-2" :message="addForm.errors.task_visibility" />
            </div>
            <PrimaryButton :disabled="addForm.processing || !addForm.user_id">
                {{ addForm.processing ? 'Đang thêm...' : 'Thêm' }}
            </PrimaryButton>
        </form>

        <p v-if="members.length === 0" class="py-6 text-center text-sm text-slate-400">Chưa có thành viên nào.</p>

        <ul v-else class="divide-y divide-slate-100">
            <li v-for="member in members" :key="member.id" class="flex items-center justify-between gap-3 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <AppUserAvatar
                        :name="member.user?.name ?? 'Tài khoản đã xóa'"
                        :avatar-url="member.user?.avatar_url"
                        size="sm"
                    />
                    <p class="truncate text-sm font-semibold text-slate-700">
                        {{ member.user?.name ?? 'Tài khoản đã xóa' }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <select
                        v-if="canManage"
                        class="app-field h-9 w-40 py-1 text-xs"
                        :value="member.role"
                        :disabled="updatingMemberId === member.id"
                        aria-label="Đổi vai trò thành viên"
                        @change="
                            updateMember(member, {
                                role: ($event.target as HTMLSelectElement).value as ProjectMemberRole,
                            })
                        "
                    >
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ projectMemberRoleLabels[role] }}
                        </option>
                    </select>
                    <span v-else class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        {{ projectMemberRoleLabels[member.role] }}
                    </span>

                    <template v-if="canManage">
                        <select
                            v-if="member.role !== 'manager'"
                            class="app-field h-9 w-44 py-1 text-xs"
                            :value="member.task_visibility"
                            :disabled="updatingMemberId === member.id"
                            aria-label="Đổi quyền xem việc của thành viên"
                            @change="
                                updateMember(member, {
                                    task_visibility: ($event.target as HTMLSelectElement)
                                        .value as ProjectTaskVisibility,
                                })
                            "
                        >
                            <option v-for="option in taskVisibilityOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <span
                            v-else
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500"
                            title="Quản lý việc dự án luôn thấy toàn bộ việc của việc dự án, không thể đổi."
                        >
                            {{ projectTaskVisibilityLabels.all }}
                        </span>
                    </template>

                    <AppActionButton
                        v-if="canManage"
                        icon="trash"
                        tone="danger"
                        :label="`Xóa ${member.user?.name ?? 'thành viên'} khỏi việc dự án`"
                        @click="memberToRemove = member"
                    />
                </div>
            </li>
        </ul>

        <AppConfirmDialog
            :show="memberToRemove !== null"
            title="Xóa thành viên?"
            :description="`“${memberToRemove?.user?.name ?? ''}” sẽ không còn thuộc việc dự án này.`"
            confirm-label="Xóa thành viên"
            :processing="isRemoving"
            @cancel="memberToRemove = null"
            @confirm="confirmRemove"
        />
    </div>
</template>
