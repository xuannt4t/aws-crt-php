<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { isScopePermission } from '@/Support/permissionMatrix';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps, PermissionGroup, PermissionMatrix, PermissionMatrixRole } from '@/types';

const props = defineProps<{
    roles: PermissionMatrixRole[];
    permissionGroups: PermissionGroup[];
    matrix: PermissionMatrix;
    lockedRoles: string[];
}>();

const page = usePage<PageProps>();

const SYSTEM_MANAGE_SETTINGS = 'system.manage_settings';

const isLockedRole = (roleName: string) => props.lockedRoles.includes(roleName);
const isHeldByCurrentUser = (roleName: string) => page.props.auth.roles.includes(roleName);

type CellState = Record<string, Record<string, boolean>>;

const buildState = (): CellState => {
    const state: CellState = {};

    for (const role of props.roles) {
        const grantedPermissions = new Set(props.matrix[role.name] ?? []);
        state[role.name] = {};

        for (const group of props.permissionGroups) {
            for (const permission of group.permissions) {
                state[role.name][permission.name] = isLockedRole(role.name)
                    ? true
                    : grantedPermissions.has(permission.name);
            }
        }
    }

    return state;
};

// Ảnh chụp trạng thái ban đầu — dùng để tính số ô đã đổi, không cần reactive.
const initialState = buildState();
const state = reactive(buildState());

const editableRoles = computed(() => props.roles.filter((role) => !isLockedRole(role.name)));

const selfLockoutWarning = ref('');

const toggleCell = (roleName: string, permissionName: string) => {
    if (isLockedRole(roleName)) {
        return;
    }

    const next = !state[roleName][permissionName];

    if (permissionName === SYSTEM_MANAGE_SETTINGS && !next && isHeldByCurrentUser(roleName)) {
        const role = props.roles.find((item) => item.name === roleName);
        selfLockoutWarning.value =
            `Không thể bỏ tích "Quản lý cài đặt hệ thống" ở vai trò "${role?.label ?? roleName}" ` +
            'vì đây là vai trò bạn đang giữ — làm vậy sẽ tự khoá quyền quản trị của chính bạn.';

        return;
    }

    selfLockoutWarning.value = '';
    state[roleName][permissionName] = next;
};

const changedCells = computed(() => {
    let count = 0;

    for (const role of editableRoles.value) {
        for (const group of props.permissionGroups) {
            for (const permission of group.permissions) {
                if (state[role.name][permission.name] !== initialState[role.name][permission.name]) {
                    count++;
                }
            }
        }
    }

    return count;
});

const isDirty = computed(() => changedCells.value > 0);

const resetChanges = () => {
    for (const role of editableRoles.value) {
        for (const group of props.permissionGroups) {
            for (const permission of group.permissions) {
                state[role.name][permission.name] = initialState[role.name][permission.name];
            }
        }
    }

    selfLockoutWarning.value = '';
};

const form = useForm<{ matrix: PermissionMatrix }>({ matrix: {} });

const errorMessages = computed(() => Object.values(form.errors));

const submit = () => {
    const matrix: PermissionMatrix = {};

    for (const role of editableRoles.value) {
        matrix[role.name] = props.permissionGroups
            .flatMap((group) => group.permissions)
            .filter((permission) => state[role.name][permission.name])
            .map((permission) => permission.name);
    }

    form.transform(() => ({ matrix })).put(route('permission-matrix.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Phân quyền" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Ma trận phân quyền"
                description="Xem và chỉnh quyền của từng vai trò. Thay đổi được áp dụng ngay sau khi lưu và ghi lại trong nhật ký hệ thống."
                eyebrow="Quản trị"
            />
        </template>

        <div class="mb-5 rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3 text-xs leading-5 text-brand-800">
            <p class="font-bold">Quyền phạm vi dữ liệu (đánh dấu "Phạm vi")</p>
            <p class="mt-1">
                Các quyền <code>view_own</code> / <code>view_department</code> / <code>view_all</code> quyết định vai
                trò thấy được công việc và dự án ở mức nào: của riêng người dùng, trong đơn vị (kể cả đơn vị con), hay
                toàn bộ hệ thống. Một vai trò có thể giữ nhiều mức — hệ thống luôn áp dụng mức rộng nhất. Đây là nhóm
                quyền sẽ được điều chỉnh nhiều nhất nên được xếp liền nhau trong từng nhóm bên dưới.
            </p>
        </div>

        <div
            v-if="selfLockoutWarning"
            class="mb-5 flex items-start justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800"
        >
            <div class="flex items-start gap-2">
                <AppIcon name="lock" class="mt-0.5 size-3.5 shrink-0" />
                <p>{{ selfLockoutWarning }}</p>
            </div>
            <button
                type="button"
                class="shrink-0 font-semibold text-amber-700 hover:text-amber-900"
                @click="selfLockoutWarning = ''"
            >
                Đóng
            </button>
        </div>

        <div
            v-if="errorMessages.length > 0"
            class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-700"
        >
            <p class="font-bold">Không thể lưu thay đổi</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                <li v-for="(message, key) in form.errors" :key="key">{{ message }}</li>
            </ul>
        </div>

        <section class="app-panel overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-display text-base font-bold text-ink-950">Vai trò và quyền</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ permissionGroups.length }} nhóm quyền · {{ roles.length }} vai trò
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1080px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-xs font-bold text-slate-500">
                            <th class="sticky left-0 z-10 bg-slate-50/70 px-5 py-3 sm:px-6">Quyền</th>
                            <th v-for="role in roles" :key="role.id" class="px-3 py-3 text-center">
                                <div class="flex items-center gap-1">
                                    <span>{{ role.label }}</span>
                                    <AppIcon
                                        v-if="isLockedRole(role.name)"
                                        name="lock"
                                        class="size-3.5 text-slate-400"
                                    />
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="group in permissionGroups" :key="group.key">
                            <tr class="bg-slate-50/50">
                                <td
                                    :colspan="roles.length + 1"
                                    class="sticky left-0 bg-slate-50/50 px-5 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 sm:px-6"
                                >
                                    {{ group.label }}
                                </td>
                            </tr>
                            <tr
                                v-for="permission in group.permissions"
                                :key="permission.name"
                                :class="isScopePermission(permission.name) ? 'bg-brand-50/30' : ''"
                            >
                                <td
                                    class="sticky left-0 z-[1] px-5 py-3 text-sm font-medium text-slate-700 sm:px-6"
                                    :class="isScopePermission(permission.name) ? 'bg-[#fbf8f2]' : 'bg-white'"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>{{ permission.label }}</span>
                                        <span
                                            v-if="isScopePermission(permission.name)"
                                            class="rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-bold text-brand-700"
                                        >
                                            Phạm vi
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] font-normal text-slate-400">{{ permission.name }}</p>
                                </td>
                                <td v-for="role in roles" :key="role.id" class="px-3 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60"
                                        :checked="state[role.name][permission.name]"
                                        :disabled="isLockedRole(role.name)"
                                        :aria-label="`${permission.label} — ${role.label}`"
                                        @change="toggleCell(role.name, permission.name)"
                                    />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div
                class="flex items-start gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-3 text-xs leading-5 text-slate-500 sm:px-6"
            >
                <AppIcon name="lock" class="mt-0.5 size-3.5 shrink-0 text-slate-400" />
                <p>
                    Vai trò "Quản trị hệ thống" luôn giữ toàn bộ quyền và không thể chỉnh sửa tại đây — bỏ tích nhầm ở
                    vai trò này sẽ khoá toàn bộ hệ thống khỏi mọi người quản trị.
                </p>
            </div>
        </section>

        <div
            class="mt-5 flex flex-col items-start justify-between gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center"
        >
            <p class="text-xs font-semibold text-slate-500">
                <span v-if="isDirty">Đã thay đổi {{ changedCells }} ô so với trạng thái hiện tại.</span>
                <span v-else>Chưa có thay đổi nào.</span>
            </p>
            <div class="flex items-center gap-2">
                <SecondaryButton v-if="isDirty" :disabled="form.processing" @click="resetChanges">
                    Hoàn tác thay đổi
                </SecondaryButton>
                <PrimaryButton :disabled="!isDirty || form.processing" @click="submit">
                    {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                </PrimaryButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
