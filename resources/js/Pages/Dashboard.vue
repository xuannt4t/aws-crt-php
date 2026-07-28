<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const { can } = usePermissions();

const firstName = computed(() => page.props.auth.user.name.trim().split(/\s+/).at(-1) ?? page.props.auth.user.name);
const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 11) {
        return 'Chào buổi sáng';
    }

    if (hour < 18) {
        return 'Chào buổi chiều';
    }

    return 'Chào buổi tối';
});
</script>

<template>
    <Head title="Tổng quan" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="`${greeting}, ${firstName}`"
                description="Không gian quản trị đang được thiết lập. Bắt đầu từ cơ cấu tổ chức và đội ngũ của bạn."
                eyebrow="Tổng quan"
            />
        </template>

        <section class="relative overflow-hidden rounded-3xl bg-ink-950 p-6 text-white shadow-panel sm:p-8 lg:p-10">
            <div class="absolute -right-20 -top-20 size-72 rounded-full bg-brand-400/20 blur-3xl" aria-hidden="true" />
            <div class="relative z-10 grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                <div class="max-w-2xl">
                    <div
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-brand-200"
                    >
                        <span class="size-1.5 rounded-full bg-brand-300" />
                        Hệ thống hoạt động bình thường
                    </div>
                    <h2 class="font-display text-2xl font-extrabold tracking-[-0.035em] sm:text-3xl">
                        Xây nền móng tốt cho một đội ngũ vận hành trơn tru.
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-white/55">
                        Hoàn thiện đơn vị và hồ sơ thành viên trước khi đưa công việc, dự án và báo cáo vào vận hành.
                    </p>
                </div>

                <div v-if="can('organization.create') || can('user.create')" class="flex flex-wrap gap-2">
                    <Link
                        v-if="can('organization.create')"
                        :href="route('organization-units.create')"
                        class="app-button-secondary border-white/10 bg-white/10 text-white hover:bg-white/15"
                    >
                        <AppIcon name="building" class="size-4" />
                        Thêm đơn vị
                    </Link>
                    <Link v-if="can('user.create')" :href="route('users.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Thêm người dùng
                    </Link>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <section class="app-panel overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="font-display text-base font-bold text-ink-950">Thiết lập không gian làm việc</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Những bước nền tảng cần hoàn thiện trước khi vận hành.
                        </p>
                    </div>
                    <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">Sprint 1</span>
                </div>

                <div class="divide-y divide-slate-100">
                    <Link
                        v-if="can('organization.view')"
                        :href="route('organization-units.index')"
                        class="group flex items-center gap-4 px-5 py-5 transition hover:bg-slate-50/70 sm:px-6"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700"
                        >
                            <AppIcon name="building" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-slate-800">Cơ cấu tổ chức</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                Thiết lập đơn vị gốc, phòng ban và quan hệ phân cấp.
                            </span>
                        </span>
                        <AppIcon
                            name="arrow-right"
                            class="size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600"
                        />
                    </Link>

                    <Link
                        v-if="can('user.view')"
                        :href="route('users.index')"
                        class="group flex items-center gap-4 px-5 py-5 transition hover:bg-slate-50/70 sm:px-6"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700"
                        >
                            <AppIcon name="users" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-slate-800">Đội ngũ</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                Quản lý hồ sơ, trạng thái và đơn vị chính của từng thành viên.
                            </span>
                        </span>
                        <AppIcon
                            name="arrow-right"
                            class="size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600"
                        />
                    </Link>

                    <div class="flex items-center gap-4 px-5 py-5 sm:px-6">
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700"
                        >
                            <AppIcon name="shield" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-slate-800">Vai trò & phân quyền</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                Sáu vai trò chuẩn đã được áp dụng theo ma trận permission.
                            </span>
                        </span>
                        <span
                            class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700"
                        >
                            Hoàn tất
                        </span>
                    </div>
                </div>
            </section>

            <aside class="app-panel p-5 sm:p-6">
                <div class="flex size-11 items-center justify-center rounded-xl bg-sand-100 text-amber-700">
                    <AppIcon name="sparkles" class="size-5" />
                </div>
                <h2 class="mt-5 font-display text-lg font-bold tracking-[-0.02em] text-ink-950">
                    Một nền tảng, nhiều luồng việc
                </h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Task, Project, Approval và Report sẽ dùng chung cơ cấu tổ chức cùng hệ thống phân quyền đang được
                    xây dựng.
                </p>
                <div class="mt-6 space-y-3 border-t border-slate-100 pt-5">
                    <div class="flex items-center gap-3 text-xs font-semibold text-slate-600">
                        <span
                            class="flex size-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"
                        >
                            <AppIcon name="check" class="size-3.5" />
                        </span>
                        Xác thực & hồ sơ người dùng
                    </div>
                    <div class="flex items-center gap-3 text-xs font-semibold text-slate-600">
                        <span
                            class="flex size-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"
                        >
                            <AppIcon name="check" class="size-3.5" />
                        </span>
                        Cơ cấu tổ chức nhiều cấp
                    </div>
                    <div class="flex items-center gap-3 text-xs font-semibold text-slate-600">
                        <span
                            class="flex size-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"
                        >
                            <AppIcon name="check" class="size-3.5" />
                        </span>
                        Phân quyền theo permission
                    </div>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
