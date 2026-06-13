<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import BrandMark from '@/Components/BrandMark.vue'
import {
    Activity,
    BarChart3,
    FolderKanban,
    Globe2,
    LogOut,
    Settings,
} from '@lucide/vue'

type Organization = {
    id: string
    name: string
}

type User = {
    id: number | string
    name: string
    email: string
}

type BillingUsage = {
    current: number
    limit: number | null
}

type BillingSummary = {
    plan: {
        name: string
    } | null
    monitors: BillingUsage
    sites: BillingUsage
}

type PageProps = {
    auth: {
        user: User | null
    }
    billing?: BillingSummary | null
}

type NavigationItem = {
    key: string
    label: string
    href: string
    icon: typeof Globe2
}

withDefaults(defineProps<{
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
    { key: 'sites', label: 'Сайты', href: '/sites', icon: Globe2 },
    { key: 'projects', label: 'Проекты', href: '/projects', icon: FolderKanban },
    { key: 'reports', label: 'Отчеты', href: '#', icon: BarChart3 },
    { key: 'settings', label: 'Настройки', href: '/settings', icon: Settings },
]

const page = usePage<PageProps>()
const user = page.props.auth.user
const billingSummary = computed(() => page.props.billing ?? null)

const planName = computed(() => billingSummary.value?.plan?.name ?? 'Free')
const monitorsCurrent = computed(() => billingSummary.value?.monitors.current ?? 0)
const monitorsLimit = computed(() => billingSummary.value?.monitors.limit ?? null)
const sitesCurrent = computed(() => billingSummary.value?.sites.current ?? 0)
const sitesLimit = computed(() => billingSummary.value?.sites.limit ?? null)
const userInitial = computed(() => (user?.name || user?.email || 'M').trim().slice(0, 1).toUpperCase())

function usagePercent(current: number, limit: number | null): number {
    if (limit === null) {
        return 100
    }

    if (limit <= 0) {
        return 0
    }

    return Math.min((current / limit) * 100, 100)
}

function limitLabel(limit: number | null): string {
    return limit === null ? '∞' : String(limit)
}
</script>

<template>
    <main class="min-h-screen bg-[#F3F8F5] font-sans text-[#26332D] lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
        <aside class="sticky top-0 hidden h-screen self-start overflow-y-auto border-r border-[#DDEBE3] bg-white px-5 py-7 lg:flex lg:flex-col">
            <div>
                <Link href="/" class="flex items-center gap-3">
                    <BrandMark class="h-9 w-9" />
                    <span class="text-2xl font-bold tracking-normal text-[#173B2A]">Montry</span>
                </Link>

                <nav class="mt-12 grid gap-2" aria-label="Основная навигация">
                    <Link
                        v-for="item in navigation"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition"
                        :class="activeItem === item.key ? 'bg-[#E9F8EF] text-[#173B2A]' : 'text-[#6A7A70] hover:bg-[#F3F8F5] hover:text-[#173B2A]'"
                    >
                        <span
                            class="grid h-8 w-8 place-items-center rounded-xl border"
                            :class="activeItem === item.key ? 'border-[#BEE7CE] bg-[#DDF6E8] text-[#1E9B5D]' : 'border-[#CFE1D7] bg-[#F3F8F5] text-[#8A9A91]'"
                        >
                            <component :is="item.icon" class="h-4 w-4" :stroke-width="2" />
                        </span>
                        {{ item.label }}
                    </Link>
                </nav>
            </div>

            <div class="mt-auto rounded-3xl border border-[#DDEBE3] bg-[#F6FBF8] p-4">
                <p class="text-lg font-semibold text-[#26332D]">Тариф {{ planName }}</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-[#6A7A70]">Сайты</span>
                            <span class="font-medium text-[#26332D]">{{ sitesCurrent }} / {{ limitLabel(sitesLimit) }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-[#E2ECE6]">
                            <div class="h-full rounded-full bg-[#2FA568]" :style="{ width: `${usagePercent(sitesCurrent, sitesLimit)}%` }" />
                        </div>
                    </div>
                </div>

                <Link
                    href="/billing"
                    class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-2xl bg-[#E7F5ED] px-4 text-sm font-medium text-[#173B2A] transition hover:bg-[#D8F0E3]"
                >
                    Управлять
                </Link>
            </div>
        </aside>

        <section class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-[#DDEBE3] bg-white/95 px-5 py-4 backdrop-blur sm:px-8">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3 lg:hidden">
                            <BrandMark class="h-8 w-8" />
                            <span class="text-xl font-bold text-[#173B2A]">Montry</span>
                        </div>
                        <p class="mt-2 truncate text-sm font-medium text-[#6A7A70] lg:mt-0">{{ organization.name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-medium text-[#1E9B5D]">Тариф {{ planName }}</span>
                            <span class="rounded-full bg-[#F3F8F5] px-3 py-1 text-xs font-medium text-[#52645A]">Сайты: {{ sitesCurrent }} / {{ limitLabel(sitesLimit) }}</span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                        <slot name="header-actions" />
                        <slot name="actions" />

                        <div class="hidden h-11 items-center gap-2 rounded-full border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#52645A] sm:flex">
                            <Activity class="h-4 w-4 text-[#E08600]" :stroke-width="2" />
                            Проверки активны
                        </div>

                        <div class="grid h-11 w-11 place-items-center rounded-full border border-[#DDEBE3] bg-[#E9F8EF] text-sm font-semibold text-[#173B2A]">
                            {{ userInitial }}
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="hidden h-10 items-center justify-center gap-2 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A] sm:inline-flex"
                        >
                            <LogOut class="h-4 w-4" :stroke-width="2" />
                            Выйти
                        </Link>
                    </div>
                </div>

                <nav class="mx-auto mt-4 flex max-w-7xl gap-2 overflow-x-auto pb-1 lg:hidden" aria-label="Основная навигация">
                    <Link
                        v-for="item in navigation"
                        :key="item.key"
                        :href="item.href"
                        class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="activeItem === item.key ? 'bg-[#E9F8EF] text-[#173B2A]' : 'bg-white text-[#6A7A70] hover:bg-[#F3F8F5]'"
                    >
                        <component :is="item.icon" class="mr-1.5 inline h-4 w-4 align-[-3px]" :stroke-width="2" />
                        {{ item.label }}
                    </Link>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="shrink-0 rounded-full bg-white px-4 py-2 text-sm font-medium text-[#6A7A70] transition hover:bg-[#F3F8F5]"
                    >
                        <LogOut class="mr-1.5 inline h-4 w-4 align-[-3px]" :stroke-width="2" />
                        Выйти
                    </Link>
                </nav>
            </header>

            <slot />
        </section>
    </main>
</template>
