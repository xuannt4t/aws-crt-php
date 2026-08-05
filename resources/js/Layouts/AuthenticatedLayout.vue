<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { roleLabels } from '@/Support/roleLabels';
import AppIcon from '@/Components/AppIcon.vue';
import AppLogoutDialog from '@/Components/AppLogoutDialog.vue';
import AppNotificationBell from '@/Components/AppNotificationBell.vue';
import AppToast from '@/Components/AppToast.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { readSidebarCollapsed, writeSidebarCollapsed } from '@/Support/sidebarPreference';
import { Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

interface NavigationItem {
    label: string;
    routeName: string;
    activePattern: string;
    icon: 'dashboard' | 'building' | 'users' | 'shield' | 'tasks' | 'folder' | 'calendar' | 'lock';
    permission?: string;
}

const page = usePage<PageProps>();
const { can } = usePermissions();
const isSidebarOpen = ref(false);
const isLogoutDialogOpen = ref(false);

// Chỉ áp dụng từ breakpoint lg trở lên. Dưới lg sidebar vốn là ngăn kéo trượt
// ra rồi đóng lại, thu nhỏ ở đó không có ý nghĩa gì.
const isSidebarCollapsed = ref(readSidebarCollapsed());

const toggleSidebarCollapsed = (): void => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    writeSidebarCollapsed(isSidebarCollapsed.value);
};

const navigation: NavigationItem[] = [
    {
        label: 'Tổng quan việc',
        routeName: 'tasks.index',
        activePattern: 'tasks.index',
        icon: 'dashboard',
        permission: 'task.view',
    },
    {
        label: 'Việc định kỳ',
        routeName: 'task-recurrences.index',
        activePattern: 'task-recurrences.*',
        icon: 'calendar',
        permission: 'task.view',
    },
    {
        label: 'Việc phòng ban',
        routeName: 'tasks.departments',
        activePattern: 'tasks.departments*',
        icon: 'tasks',
        permission: 'task.view',
    },
    {
        label: 'Dự án',
        routeName: 'projects.index',
        activePattern: 'projects.*',
        icon: 'folder',
        // Không gate theo project.view: danh sách dự án mở cho mọi người dùng
        // đã đăng nhập, nhưng chỉ hiển thị dự án họ là thành viên nếu thiếu quyền.
    },
    {
        label: 'Cơ cấu tổ chức',
        routeName: 'organization-units.index',
        activePattern: 'organization-units.*',
        icon: 'building',
        permission: 'organization.view',
    },
    {
        label: 'Người dùng',
        routeName: 'users.index',
        activePattern: 'users.*',
        icon: 'users',
        permission: 'user.view',
    },
    {
        label: 'Nhật ký hệ thống',
        routeName: 'audit-logs.index',
        activePattern: 'audit-logs.*',
        icon: 'shield',
        permission: 'system.view_audit_logs',
    },
    {
        label: 'Phân quyền',
        routeName: 'permission-matrix.index',
        activePattern: 'permission-matrix.*',
        icon: 'lock',
        permission: 'system.manage_settings',
    },
];

const visibleNavigation = computed(() => navigation.filter((item) => !item.permission || can(item.permission)));

const primaryRole = computed(() => {
    const role = page.props.auth.roles[0];

    return role ? (roleLabels[role] ?? role) : 'Thành viên';
});

const currentLabel = computed(
    () => visibleNavigation.value.find((item) => route().current(item.activePattern))?.label ?? 'Không gian làm việc',
);

watch(
    () => page.url,
    () => {
        isSidebarOpen.value = false;
    },
);
</script>

<template>
    <div class="min-h-screen bg-[#f7f8f6]">
        <AppToast />

        <button
            v-if="isSidebarOpen"
            type="button"
            class="fixed inset-0 z-40 bg-ink-950/45 backdrop-blur-sm lg:hidden"
            aria-label="Đóng menu"
            @click="isSidebarOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[278px] flex-col bg-ink-950 px-4 py-5 text-white shadow-float transition-[transform,width] duration-300 lg:translate-x-0 lg:shadow-none"
            :class="[
                isSidebarOpen ? 'translate-x-0' : '-translate-x-full',
                isSidebarCollapsed ? 'lg:w-[84px] lg:px-3' : '',
            ]"
        >
            <div
                class="flex items-center justify-between px-2"
                :class="isSidebarCollapsed ? 'lg:flex-col lg:gap-2 lg:px-0' : ''"
            >
                <Link :href="route('dashboard')" class="rounded-xl text-white focus:ring-4 focus:ring-white/10">
                    <ApplicationLogo :text-class="isSidebarCollapsed ? 'lg:hidden' : ''" />
                </Link>
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg text-white/60 hover:bg-white/10 hover:text-white lg:hidden"
                    aria-label="Đóng menu"
                    @click="isSidebarOpen = false"
                >
                    <AppIcon name="x" class="size-5" />
                </button>
                <button
                    type="button"
                    class="hidden size-9 shrink-0 items-center justify-center rounded-lg text-white/60 transition hover:bg-white/10 hover:text-white focus:ring-4 focus:ring-white/10 lg:inline-flex"
                    :aria-label="isSidebarCollapsed ? 'Mở rộng thanh điều hướng' : 'Thu nhỏ thanh điều hướng'"
                    :aria-pressed="isSidebarCollapsed"
                    :title="isSidebarCollapsed ? 'Mở rộng thanh điều hướng' : 'Thu nhỏ thanh điều hướng'"
                    @click="toggleSidebarCollapsed"
                >
                    <AppIcon name="panel-left" class="size-5" :class="isSidebarCollapsed ? 'rotate-180' : ''" />
                </button>
            </div>

            <nav class="mt-9 space-y-1" aria-label="Điều hướng chính">
                <Link
                    v-for="item in visibleNavigation"
                    :key="item.routeName"
                    :href="route(item.routeName)"
                    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                    :class="[
                        route().current(item.activePattern)
                            ? 'bg-white text-ink-950 shadow-sm'
                            : 'text-white/62 hover:bg-white/[0.07] hover:text-white',
                        isSidebarCollapsed ? 'lg:justify-center lg:px-0' : '',
                    ]"
                    :title="isSidebarCollapsed ? item.label : undefined"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg transition"
                        :class="
                            route().current(item.activePattern)
                                ? 'bg-brand-100 text-brand-700'
                                : 'bg-white/[0.06] text-white/55 group-hover:text-white'
                        "
                    >
                        <AppIcon :name="item.icon" class="size-[18px]" />
                    </span>
                    <span :class="isSidebarCollapsed ? 'lg:hidden' : ''">{{ item.label }}</span>
                </Link>
            </nav>

            <div class="mt-auto">
                <div
                    class="mb-4 rounded-2xl border border-white/[0.08] bg-white/[0.045] p-3"
                    :class="isSidebarCollapsed ? 'lg:px-1.5' : ''"
                >
                    <div class="flex items-center gap-3" :class="isSidebarCollapsed ? 'lg:justify-center' : ''">
                        <AppUserAvatar
                            :name="page.props.auth.user.name"
                            :avatar-url="page.props.auth.user.avatar_url"
                        />
                        <div class="min-w-0 flex-1" :class="isSidebarCollapsed ? 'lg:hidden' : ''">
                            <p class="truncate text-sm font-semibold text-white">{{ page.props.auth.user.name }}</p>
                            <p class="mt-0.5 truncate text-xs text-white/45">
                                {{ primaryRole }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-3 grid grid-cols-2 gap-2 border-t border-white/[0.07] pt-3"
                        :class="isSidebarCollapsed ? 'lg:grid-cols-1' : ''"
                    >
                        <Link
                            :href="route('profile.edit')"
                            class="flex items-center justify-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold text-white/60 transition hover:bg-white/[0.07] hover:text-white"
                            :title="isSidebarCollapsed ? 'Hồ sơ' : undefined"
                        >
                            <AppIcon name="user" class="size-4 shrink-0" />
                            <span :class="isSidebarCollapsed ? 'lg:hidden' : ''">Hồ sơ</span>
                        </Link>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold text-white/60 transition hover:bg-white/[0.07] hover:text-white"
                            :title="isSidebarCollapsed ? 'Đăng xuất' : undefined"
                            @click="isLogoutDialogOpen = true"
                        >
                            <AppIcon name="logout" class="size-4 shrink-0" />
                            <span :class="isSidebarCollapsed ? 'lg:hidden' : ''">Đăng xuất</span>
                        </button>
                    </div>
                </div>

                <p
                    class="px-2 text-[10px] font-medium uppercase tracking-[0.15em] text-white/20"
                    :class="isSidebarCollapsed ? 'lg:hidden' : ''"
                >
                    Dormida Work · Foundation
                </p>
            </div>
        </aside>

        <div
            class="min-h-screen transition-[padding] duration-300"
            :class="isSidebarCollapsed ? 'lg:pl-[84px]' : 'lg:pl-[278px]'"
        >
            <header
                class="sticky top-0 z-30 flex h-[68px] items-center border-b border-black/[0.055] bg-[#f7f8f6]/90 px-4 backdrop-blur-xl sm:px-6 lg:px-8"
            >
                <button
                    type="button"
                    class="app-icon-button mr-3 lg:hidden"
                    aria-label="Mở menu"
                    @click="isSidebarOpen = true"
                >
                    <AppIcon name="menu" class="size-5" />
                </button>

                <div class="flex min-w-0 items-center gap-2 text-sm">
                    <span class="hidden text-slate-400 sm:inline">Dormida Work</span>
                    <AppIcon name="chevron-right" class="hidden size-3.5 text-slate-300 sm:block" />
                    <span class="truncate font-semibold text-slate-700">{{ currentLabel }}</span>
                </div>

                <div class="ml-auto flex items-center gap-1.5">
                    <AppNotificationBell :user-id="page.props.auth.user.id" />

                    <Link
                        :href="route('profile.edit')"
                        class="flex items-center gap-2 rounded-xl p-1 transition hover:bg-white focus:ring-4 focus:ring-slate-200"
                        aria-label="Mở hồ sơ cá nhân"
                    >
                        <span class="hidden text-right sm:block">
                            <span class="block max-w-40 truncate text-xs font-semibold text-slate-700">
                                {{ page.props.auth.user.name }}
                            </span>
                            <span class="mt-0.5 block text-[10px] text-slate-400">{{
                                page.props.auth.user.email
                            }}</span>
                        </span>
                        <AppUserAvatar
                            :name="page.props.auth.user.name"
                            :avatar-url="page.props.auth.user.avatar_url"
                            size="sm"
                        />
                    </Link>
                </div>
            </header>

            <main class="px-4 pb-10 pt-7 sm:px-6 sm:pt-9 lg:px-8">
                <!-- Thu nhỏ sidebar là hành động cố ý đòi thêm chỗ, nên phải bỏ luôn
                     trần 1440px — nếu giữ trần thì phần vừa lấy lại được chỉ biến
                     thành lề trắng hai bên, nhìn như không có gì thay đổi. -->
                <div class="mx-auto w-full" :class="isSidebarCollapsed ? 'max-w-none' : 'max-w-[1440px]'">
                    <header v-if="$slots.header" class="mb-7">
                        <slot name="header" />
                    </header>

                    <slot />
                </div>
            </main>
        </div>

        <AppLogoutDialog :show="isLogoutDialogOpen" @close="isLogoutDialogOpen = false" />
    </div>
</template>
