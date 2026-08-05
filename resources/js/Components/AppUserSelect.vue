<script setup lang="ts">
import { computed } from 'vue';
import Select from 'primevue/select';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { roleLabel, roleRank } from '@/Support/roleLabels';
import type { UserOption } from '@/types';

const props = withDefaults(
    defineProps<{
        options: UserOption[];
        // Id của người đang đăng nhập, để đánh dấu "(Bạn)".
        currentUserId?: number | null;
        inputId?: string;
        placeholder?: string;
        // Cho phép bỏ trống — ví dụ công việc chưa phân công ai.
        clearable?: boolean;
        clearLabel?: string;
        disabled?: boolean;
        invalid?: boolean;
    }>(),
    {
        currentUserId: null,
        inputId: undefined,
        placeholder: 'Chọn người',
        clearable: false,
        clearLabel: 'Chưa phân công',
        disabled: false,
        invalid: false,
    },
);

const model = defineModel<number | null>({ required: true });

interface UserGroup {
    key: string;
    label: string;
    users: UserOption[];
}

const UNASSIGNED_GROUP_KEY = 'none';

/**
 * Gom theo đơn vị, trong mỗi đơn vị xếp theo cấp bậc rồi mới tới tên. Danh sách
 * phẳng theo bảng chữ cái không nói được người đó là ai — hai người trùng tên là
 * chịu, và người phụ trách phòng lẫn giữa hàng chục nhân viên.
 */
const groups = computed<UserGroup[]>(() => {
    const byUnit = new Map<string, UserGroup>();

    for (const user of props.options) {
        const key = user.organization_unit_id === null ? UNASSIGNED_GROUP_KEY : String(user.organization_unit_id);

        if (!byUnit.has(key)) {
            byUnit.set(key, {
                key,
                label: user.organization_unit_name ?? 'Chưa thuộc đơn vị',
                users: [],
            });
        }

        byUnit.get(key)!.users.push(user);
    }

    for (const group of byUnit.values()) {
        group.users.sort((a, b) => roleRank(a.role) - roleRank(b.role) || a.name.localeCompare(b.name, 'vi'));
    }

    return [...byUnit.values()].sort((a, b) => {
        // Nhóm "Chưa thuộc đơn vị" luôn nằm cuối, không xếp theo tên.
        if (a.key === UNASSIGNED_GROUP_KEY) {
            return 1;
        }

        if (b.key === UNASSIGNED_GROUP_KEY) {
            return -1;
        }

        return a.label.localeCompare(b.label, 'vi');
    });
});

const selected = computed<UserOption | null>(() => props.options.find((user) => user.id === model.value) ?? null);

const isCurrentUser = (user: UserOption): boolean => user.id === props.currentUserId;

// Dòng phụ dưới tên: chức danh nếu có, không thì rơi về vai trò hệ thống.
const subtitle = (user: UserOption): string => user.job_title || roleLabel(user.role);

/**
 * PrimeVue lọc theo các trường liệt kê ở `filterFields`, nên phải kể đủ những gì
 * người dùng có thể gõ: tên, email, chức danh và tên đơn vị.
 */
const filterFields = ['name', 'email', 'job_title', 'organization_unit_name'];
</script>

<template>
    <Select
        v-model="model"
        :input-id="inputId"
        :options="groups"
        option-group-label="label"
        option-group-children="users"
        option-label="name"
        option-value="id"
        :placeholder="placeholder"
        :disabled="disabled"
        :invalid="invalid"
        :show-clear="clearable"
        filter
        :filter-fields="filterFields"
        filter-placeholder="Tìm theo tên, email, chức danh, đơn vị"
        :auto-filter-focus="true"
        empty-message="Không có người nào"
        empty-filter-message="Không tìm thấy người phù hợp"
        class="app-user-select mt-2 w-full"
        :pt="{ list: { class: 'py-1' } }"
    >
        <template #value="{ value }">
            <span v-if="selected && value !== null && value !== undefined" class="flex min-w-0 items-center gap-2.5">
                <AppUserAvatar :name="selected.name" size="sm" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-slate-800">
                        {{ selected.name }}<span v-if="isCurrentUser(selected)" class="text-slate-400"> (Bạn)</span>
                    </span>
                    <span class="block truncate text-xs text-slate-500">
                        {{ subtitle(selected) }}
                        <template v-if="selected.organization_unit_name">
                            · {{ selected.organization_unit_name }}
                        </template>
                    </span>
                </span>
            </span>
            <span v-else class="text-sm text-slate-400">{{ clearable ? clearLabel : placeholder }}</span>
        </template>

        <template #optiongroup="{ option }">
            <div class="flex items-center gap-2 px-1 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-500">
                <AppIcon name="building" class="size-3.5 shrink-0" />
                <span class="truncate">{{ option.label }}</span>
                <span class="ml-auto shrink-0 font-semibold normal-case tracking-normal text-slate-400">
                    {{ option.users.length }}
                </span>
            </div>
        </template>

        <template #option="{ option }">
            <div class="flex min-w-0 items-center gap-2.5 py-0.5">
                <AppUserAvatar :name="option.name" size="sm" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800">
                        {{ option.name }}<span v-if="isCurrentUser(option)" class="text-slate-400"> (Bạn)</span>
                    </p>
                    <p class="truncate text-xs text-slate-500">{{ subtitle(option) }} · {{ option.email }}</p>
                </div>
            </div>
        </template>
    </Select>
</template>

<style scoped>
/* Ô chọn phải cao bằng các trường `app-field` bên cạnh, nếu không hàng lưới sẽ lệch. */
.app-user-select :deep(.p-select-label) {
    display: flex;
    align-items: center;
    min-height: 2.75rem;
}
</style>
