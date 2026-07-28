<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="grid min-h-screen bg-sand-50 lg:grid-cols-[1.05fr_0.95fr]">
        <section
            class="relative hidden overflow-hidden bg-ink-950 px-12 py-10 text-white lg:flex lg:flex-col xl:px-16 xl:py-12"
        >
            <div
                class="absolute -right-28 -top-28 size-[420px] rounded-full bg-brand-400/20 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="absolute -bottom-36 -left-24 size-[460px] rounded-full bg-amber-200/10 blur-3xl"
                aria-hidden="true"
            />
            <div class="absolute inset-0 opacity-[0.035]" aria-hidden="true">
                <div
                    class="size-full"
                    style="
                        background-image:
                            linear-gradient(white 1px, transparent 1px),
                            linear-gradient(90deg, white 1px, transparent 1px);
                        background-size: 36px 36px;
                    "
                />
            </div>

            <Link href="/" class="relative z-10 w-fit text-white">
                <ApplicationLogo />
            </Link>

            <div class="relative z-10 my-auto max-w-xl py-16">
                <div
                    class="mb-7 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.06] px-3 py-1.5 text-xs font-semibold text-brand-200"
                >
                    <AppIcon name="sparkles" class="size-3.5" />
                    Một nơi cho mọi công việc
                </div>
                <h1 class="font-display text-4xl font-extrabold leading-[1.12] tracking-[-0.045em] xl:text-5xl">
                    Biến kế hoạch thành tiến độ có thể nhìn thấy.
                </h1>
                <p class="mt-6 max-w-lg text-base leading-7 text-white/58">
                    DORMIDA WORK kết nối con người, cơ cấu tổ chức và luồng công việc trong một không gian tập trung, rõ
                    ràng.
                </p>

                <div class="mt-10 grid max-w-lg grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-white/[0.08] bg-white/[0.045] p-4">
                        <AppIcon name="building" class="size-5 text-brand-300" />
                        <p class="mt-3 text-xs font-semibold text-white/75">Cơ cấu rõ ràng</p>
                    </div>
                    <div class="rounded-2xl border border-white/[0.08] bg-white/[0.045] p-4">
                        <AppIcon name="tasks" class="size-5 text-brand-300" />
                        <p class="mt-3 text-xs font-semibold text-white/75">Công việc liền mạch</p>
                    </div>
                    <div class="rounded-2xl border border-white/[0.08] bg-white/[0.045] p-4">
                        <AppIcon name="shield" class="size-5 text-brand-300" />
                        <p class="mt-3 text-xs font-semibold text-white/75">Quyền hạn an toàn</p>
                    </div>
                </div>
            </div>

            <p class="relative z-10 text-xs text-white/28">© {{ new Date().getFullYear() }} Dormida Work</p>
        </section>

        <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <Link href="/" class="mb-10 inline-flex text-ink-950 lg:hidden">
                    <ApplicationLogo />
                </Link>
                <slot />
            </div>
        </section>
    </div>
</template>
