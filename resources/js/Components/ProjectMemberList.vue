<script setup lang="ts">
import { computed, ref } from 'vue';
import AppActionButton from '@/Components/AppActionButton.vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { projectMemberRoleLabels } from '@/Constants/project';
import { router, useForm } from '@inertiajs/vue3';
import type { ProjectMember, ProjectMemberRole, User } from '@/types';

const props = defineProps<{
    projectId: number;
    members: ProjectMember[];
    users: Pick<User, 'id' | 'name'>[];
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

const updateRole = (member: ProjectMember, role: ProjectMemberRole) => {
    updatingMemberId.value = member.id;

    router.patch(
        route('projects.members.update', [props.projectId, member.id]),
        { role },
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
                <select id="member-user" v-model="addForm.user_id" class="app-field">
                    <option :value="null" disabled>Chọn người dùng</option>
                    <option v-for="user in availableUsers" :key="user.id" :value="user.id">
                        {{ user.name }}
                    </option>
                </select>
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
                        @change="updateRole(member, ($event.target as HTMLSelectElement).value as ProjectMemberRole)"
                    >
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ projectMemberRoleLabels[role] }}
                        </option>
                    </select>
                    <span v-else class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        {{ projectMemberRoleLabels[member.role] }}
                    </span>
                    <AppActionButton
                        v-if="canManage"
                        icon="trash"
                        tone="danger"
                        :label="`Xóa ${member.user?.name ?? 'thành viên'} khỏi dự án`"
                        @click="memberToRemove = member"
                    />
                </div>
            </li>
        </ul>

        <AppConfirmDialog
            :show="memberToRemove !== null"
            title="Xóa thành viên?"
            :description="`“${memberToRemove?.user?.name ?? ''}” sẽ không còn thuộc dự án này.`"
            confirm-label="Xóa thành viên"
            :processing="isRemoving"
            @cancel="memberToRemove = null"
            @confirm="confirmRemove"
        />
    </div>
</template>
