<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

interface NavigationItem {
    label: string;
    routeName: string;
    activePattern: string;
    icon: 'dashboard' | 'building' | 'users' | 'shield';
    permission?: string;
}

const page = usePage<PageProps>();
const { can } = usePermissions();
const isSidebarOpen = ref(false);

const navigation: NavigationItem[] = [
    {
        label: 'Tổng quan',
        routeName: 'dashboard',
        activePattern: 'dashboard',
        icon: 'dashboard',
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
];

const visibleNavigation = computed(() =>
    navigation.filter((item) => !item.permission || can(item.permission)),
);

const roleLabels: Record<string, string> = {
    system_admin: 'Quản trị hệ thống',
    director: 'Giám đốc',
    department_manager: 'Quản lý phòng ban',
    project_manager: 'Quản lý dự án',
    employee: 'Nhân viên',
    auditor: 'Kiểm toán viên',
};

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
        <button
            v-if="isSidebarOpen"
            type="button"
            class="fixed inset-0 z-40 bg-ink-950/45 backdrop-blur-sm lg:hidden"
            aria-label="Đóng menu"
            @click="isSidebarOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[278px] flex-col bg-ink-950 px-4 py-5 text-white shadow-float transition-transform duration-300 lg:translate-x-0 lg:shadow-none"
            :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center justify-between px-2">
                <Link :href="route('dashboard')" class="rounded-xl text-white focus:ring-4 focus:ring-white/10">
                    <ApplicationLogo />
                </Link>
                <button
                    type="button"
                    class="inline-flex size-9 items-center justify-center rounded-lg text-white/60 hover:bg-white/10 hover:text-white lg:hidden"
                    aria-label="Đóng menu"
                    @click="isSidebarOpen = false"
                >
                    <AppIcon name="x" class="size-5" />
                </button>
            </div>

            <div class="mt-9 px-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/35">Không gian làm việc</p>
            </div>

            <nav class="mt-3 space-y-1" aria-label="Điều hướng chính">
                <Link
                    v-for="item in visibleNavigation"
                    :key="item.routeName"
                    :href="route(item.routeName)"
                    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                    :class="
                        route().current(item.activePattern)
                            ? 'bg-white text-ink-950 shadow-sm'
                            : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                    "
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg transition"
                        :class="
                            route().current(item.activePattern)
                                ? 'bg-brand-100 text-brand-700'
                                : 'bg-white/[0.06] text-white/55 group-hover:text-white'
                        "
                    >
                        <AppIcon :name="item.icon" class="size-[18px]" />
                    </span>
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="mt-auto">
                <div class="mb-4 rounded-2xl border border-white/[0.08] bg-white/[0.045] p-3">
                    <div class="flex items-center gap-3">
                        <AppUserAvatar :name="page.props.auth.user.name" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white">{{ page.props.auth.user.name }}</p>
                            <p class="mt-0.5 truncate text-xs text-white/45">
                                {{ primaryRole }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-white/[0.07] pt-3">
                        <Link
                            :href="route('profile.edit')"
                            class="flex items-center justify-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold text-white/60 transition hover:bg-white/[0.07] hover:text-white"
                        >
                            <AppIcon name="user" class="size-4" />
                            Hồ sơ
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex items-center justify-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold text-white/60 transition hover:bg-white/[0.07] hover:text-white"
                        >
                            <AppIcon name="logout" class="size-4" />
                            Đăng xuất
                        </Link>
                    </div>
                </div>

                <p class="px-2 text-[10px] font-medium uppercase tracking-[0.15em] text-white/20">
                    Dormida Work · Foundation
                </p>
            </div>
        </aside>

        <div class="min-h-screen lg:pl-[278px]">
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

                <Link
                    :href="route('profile.edit')"
                    class="ml-auto flex items-center gap-2 rounded-xl p-1 transition hover:bg-white focus:ring-4 focus:ring-slate-200"
                    aria-label="Mở hồ sơ cá nhân"
                >
                    <span class="hidden text-right sm:block">
                        <span class="block max-w-40 truncate text-xs font-semibold text-slate-700">
                            {{ page.props.auth.user.name }}
                        </span>
                        <span class="mt-0.5 block text-[10px] text-slate-400">{{ page.props.auth.user.email }}</span>
                    </span>
                    <AppUserAvatar :name="page.props.auth.user.name" size="sm" />
                </Link>
            </header>

            <main class="px-4 pb-10 pt-7 sm:px-6 sm:pt-9 lg:px-8">
                <div class="mx-auto w-full max-w-[1440px]">
                    <header v-if="$slots.header" class="mb-7">
                        <slot name="header" />
                    </header>

                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
