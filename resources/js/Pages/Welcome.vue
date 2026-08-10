<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();
</script>

<template>
    <Head title="Không gian làm việc hiện đại" />

    <div class="min-h-screen overflow-hidden bg-sand-50 text-ink-950">
        <header class="relative z-20 mx-auto flex max-w-7xl items-center justify-between px-5 py-6 sm:px-8 lg:px-10">
            <ApplicationLogo />
            <nav v-if="canLogin" class="flex items-center gap-2">
                <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="app-button-primary">
                    Vào không gian làm việc
                    <AppIcon name="arrow-right" class="size-4" />
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="app-button-secondary">Đăng nhập</Link>
                    <Link v-if="canRegister" :href="route('register')" class="app-button-primary">Tạo tài khoản</Link>
                </template>
            </nav>
        </header>

        <main class="relative">
            <div
                class="absolute left-1/2 top-10 size-[520px] -translate-x-1/2 rounded-full bg-brand-200/50 blur-3xl"
                aria-hidden="true"
            />
            <section class="relative mx-auto max-w-7xl px-5 pb-16 pt-16 sm:px-8 sm:pt-24 lg:px-10 lg:pb-24">
                <div class="mx-auto max-w-3xl text-center">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/60 px-3 py-1.5 text-xs font-bold text-brand-800 backdrop-blur"
                    >
                        <AppIcon name="sparkles" class="size-3.5" />
                        Quản trị công việc, không quản trị sự hỗn loạn
                    </div>
                    <h1
                        class="mt-7 font-display text-4xl font-extrabold leading-[1.05] tracking-[-0.055em] sm:text-6xl"
                    >
                        Mọi người cùng nhìn về
                        <span class="text-brand-600">một hướng.</span>
                    </h1>
                    <p class="mx-auto mt-6 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                        DORMIDA WORK giúp doanh nghiệp tổ chức đội ngũ, phân quyền và đưa kế hoạch đến kết quả trong một
                        không gian tập trung.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Link :href="route('login')" class="app-button-primary px-5">
                            Bắt đầu làm việc
                            <AppIcon name="arrow-right" class="size-4" />
                        </Link>
                        <span class="text-xs font-medium text-slate-400">Dành cho đội ngũ nội bộ</span>
                    </div>
                </div>

                <div class="mx-auto mt-16 grid max-w-5xl gap-4 sm:grid-cols-3">
                    <article class="app-panel p-6">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                            <AppIcon name="building" class="size-5" />
                        </span>
                        <h2 class="mt-5 font-display text-base font-bold">Tổ chức rõ ràng</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Phản ánh đúng cơ cấu nhiều cấp và phạm vi quản lý.
                        </p>
                    </article>
                    <article class="app-panel p-6">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                            <AppIcon name="tasks" class="size-5" />
                        </span>
                        <h2 class="mt-5 font-display text-base font-bold">Công việc liền mạch</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Kết nối mục tiêu, việc dự án, nhiệm vụ và phê duyệt.
                        </p>
                    </article>
                    <article class="app-panel p-6">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                            <AppIcon name="shield" class="size-5" />
                        </span>
                        <h2 class="mt-5 font-display text-base font-bold">Kiểm soát an toàn</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Quyền hạn và dữ liệu luôn đi đúng phạm vi.</p>
                    </article>
                </div>
            </section>
        </main>
    </div>
</template>
