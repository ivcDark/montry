<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import BrandMark from '@/Components/BrandMark.vue'

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
    { label: 'Проверки', href: '/#checks' },
    { label: 'Тарифы', href: '/#pricing' },
    { label: 'Статьи', href: '/articles' },
    { label: 'FAQ', href: '/#faq' },
    { label: 'Связаться', href: '/#feedback' },
]

const ctaHref = user ? '/dashboard' : '/register'
const ctaLabel = user ? 'Перейти в кабинет' : 'Начать бесплатно'
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-[#DDEBE3]/80 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
            <Link href="/" class="flex items-center gap-3" aria-label="Montry">
                <BrandMark class="h-8 w-8 drop-shadow-[0_10px_20px_rgba(18,61,43,0.16)]" />
                <span class="text-lg font-extrabold tracking-normal text-[#26332D]">Montry</span>
            </Link>

            <nav class="hidden items-center gap-7 lg:flex" aria-label="Главная навигация">
                <a
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="text-sm font-semibold text-[#6B7D72] transition hover:text-[#26332D]"
                >
                    {{ item.label }}
                </a>
            </nav>

            <div class="flex items-center gap-3">
                <span
                    v-if="contextLabel"
                    class="text-[15px] font-semibold text-[#111827]"
                >
                    {{ contextLabel }}
                </span>

                <template v-else>
                    <Link
                        v-if="!user"
                        href="/login"
                        class="hidden text-sm font-bold text-[#26332D] transition hover:text-[#24A869] sm:inline-flex"
                    >
                        Войти
                    </Link>

                    <Link
                        :href="ctaHref"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-[#24A869] px-4 text-xs font-semibold text-white shadow-[0_10px_24px_rgba(36,168,105,0.18)] transition hover:bg-[#1D9059] focus:outline-none focus:ring-2 focus:ring-[#24A869]/30 focus:ring-offset-2 sm:px-5 sm:text-sm"
                    >
                        {{ ctaLabel }}
                    </Link>
                </template>
            </div>
        </div>
    </header>
</template>
