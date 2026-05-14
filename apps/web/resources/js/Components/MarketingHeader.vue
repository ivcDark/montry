<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'

type User = {
    id: number | string
    name: string
    email: string
}

type PageProps = {
    auth: {
        user: User | null
    }
}

defineProps<{
    contextLabel?: string
}>()

const page = usePage<PageProps>()
const user = page.props.auth.user

const navItems = [
    { label: 'Возможности', href: '/#features' },
    { label: 'Для кого', href: '/#audience' },
    { label: 'Тарифы', href: '/#pricing' },
    { label: 'FAQ', href: '/#faq' },
]

const ctaHref = user ? '/dashboard' : '/register'
const ctaLabel = user ? 'Перейти в кабинет' : 'Начать бесплатно'
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-[#E5E7EB]/80 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8">
            <Link href="/" class="flex items-center gap-3" aria-label="Montri">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0F6BFF] text-lg font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)]">
                    M
                </span>
                <span class="text-2xl font-extrabold tracking-normal text-[#111827]">Montri</span>
            </Link>

            <nav class="hidden items-center gap-8 lg:flex" aria-label="Главная навигация">
                <a
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="text-[15px] font-semibold text-[#667085] transition hover:text-[#111827]"
                >
                    {{ item.label }}
                </a>
            </nav>

            <div class="flex items-center gap-3">
                <span
                    v-if="contextLabel"
                    class="text-[15px] font-extrabold text-[#111827]"
                >
                    {{ contextLabel }}
                </span>

                <template v-else>
                    <Link
                        v-if="!user"
                        href="/login"
                        class="hidden text-[15px] font-semibold text-[#111827] transition hover:text-[#0F6BFF] sm:inline-flex"
                    >
                        Войти
                    </Link>

                    <Link
                        :href="ctaHref"
                        class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-bold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2"
                    >
                        {{ ctaLabel }}
                    </Link>
                </template>
            </div>
        </div>
    </header>
</template>
