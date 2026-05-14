<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

type Organization = {
    id: string
    name: string
}

type NavigationItem = {
    key: string
    label: string
    icon: string
    href?: string
}

const props = withDefaults(defineProps<{
    organization: Organization
    activeItem: string
    title: string
    subtitle: string
    usageCurrent?: number
    usageLimit?: number
}>(), {
    usageCurrent: 0,
    usageLimit: 50,
})

const navigation: NavigationItem[] = [
    { key: 'dashboard', label: 'Обзор', href: '/dashboard', icon: '●' },
    { key: 'projects', label: 'Проекты', href: '/projects', icon: '□' },
    { key: 'monitors', label: 'Мониторинги', href: '/monitors', icon: '◌' },
    { key: 'incidents', label: 'Инциденты', icon: '!' },
    { key: 'notifications', label: 'Уведомления', icon: '✉' },
    { key: 'reports', label: 'Отчеты', icon: '▤' },
    { key: 'billing', label: 'Тариф', icon: '₽' },
    { key: 'settings', label: 'Настройки', icon: '⚙' },
]

const usagePercent = computed(() => Math.min((props.usageCurrent / props.usageLimit) * 100, 100))
</script>

<template>
    <main class="min-h-screen bg-[#F6F8FB] font-sans text-[#111827] lg:grid lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="hidden bg-[#0B1220] px-5 py-7 text-white lg:block">
            <Link href="/" class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0F6BFF] text-lg font-extrabold text-white">M</span>
                <span class="text-2xl font-extrabold tracking-normal">Montri</span>
            </Link>

            <p class="mt-1 text-sm font-semibold text-[#94A3B8]">Мониторинг сайтов</p>

            <nav class="mt-8 grid gap-1" aria-label="Основная навигация">
                <a
                    v-for="item in navigation"
                    :key="item.key"
                    :href="item.href ?? undefined"
                    class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition"
                    :class="[
                        activeItem === item.key ? 'bg-[#17233A] text-white' : 'text-[#94A3B8]',
                        item.href ? 'hover:bg-white/5 hover:text-white' : 'cursor-not-allowed opacity-55',
                    ]"
                    :aria-disabled="!item.href"
                >
                    <span class="w-5 text-center">{{ item.icon }}</span>
                    {{ item.label }}
                </a>
            </nav>

            <div class="mt-10 rounded-3xl bg-[#17233A] p-4">
                <p class="font-extrabold text-white">Тариф Studio</p>
                <p class="mt-1 text-sm text-[#94A3B8]">{{ usageCurrent }} из {{ usageLimit }} мониторов</p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-[#0F6BFF]" :style="{ width: `${usagePercent}%` }" />
                </div>
                <button class="mt-4 h-10 w-full rounded-xl bg-white text-sm font-extrabold text-[#111827]">
                    Управлять тарифом
                </button>
            </div>
        </aside>

        <section class="min-w-0">
            <header class="border-b border-[#E5E7EB] bg-white px-5 py-5 sm:px-8">
                <div class="mx-auto flex max-w-7xl flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-bold text-[#12B3A8]">{{ organization.name }}</p>
                        <h1 class="mt-1 text-3xl font-extrabold tracking-normal text-[#111827]">{{ title }}</h1>
                        <p class="mt-2 text-[#667085]">{{ subtitle }}</p>
                    </div>

                    <slot name="actions" />
                </div>
            </header>

            <slot />
        </section>
    </main>
</template>
