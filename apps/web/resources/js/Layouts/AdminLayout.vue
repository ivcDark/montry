<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import BrandMark from '@/Components/BrandMark.vue'
import FlashToast from '@/Components/FlashToast.vue'
import {
    BookOpen,
    Building2,
    ChevronRight,
    CircleDollarSign,
    Gauge,
    RadioTower,
    ShieldAlert,
    Users,
} from '@lucide/vue'

type User = {
    id: number | string
    name: string
    email: string
}

type PageProps = {
    auth: {
        user: User | null
    }
    errors?: Record<string, string | string[]>
    flash?: {
        success?: string | null
        error?: string | null
        token?: string | null
    }
}

type NavigationItem = {
    key: string
    label: string
    href?: string
    icon: typeof Users
}

defineProps<{
    activeItem: string
    title: string
    subtitle: string
}>()

const navigation: NavigationItem[] = [
    { key: 'users', label: 'Пользователи', href: '/admin/users', icon: Users },
    { key: 'plans', label: 'Тарифы', href: '/admin/plans', icon: CircleDollarSign },
    { key: 'articles', label: 'Статьи', href: '/admin/articles', icon: BookOpen },
    { key: 'dead_letters', label: 'Dead letters', href: '/admin/dead-letters', icon: ShieldAlert },
    { key: 'organizations', label: 'Организации', icon: Building2 },
    { key: 'monitors', label: 'Мониторы', icon: RadioTower },
    { key: 'incidents', label: 'Инциденты', icon: Gauge },
    { key: 'billing', label: 'Биллинг', icon: CircleDollarSign },
]

const page = usePage<PageProps>()
const user = page.props.auth.user
const firstValidationError = computed(() => {
    const error = Object.values(page.props.errors ?? {})[0]

    if (Array.isArray(error)) {
        return error[0] ?? null
    }

    return error ?? null
})
const toastMessage = computed(() => (
    page.props.flash?.error
    ?? firstValidationError.value
    ?? page.props.flash?.success
    ?? null
))
const toastVariant = computed<'success' | 'error'>(() => (
    page.props.flash?.error || firstValidationError.value ? 'error' : 'success'
))
const toastToken = computed(() => (
    page.props.flash?.token
    ?? (firstValidationError.value ? JSON.stringify(page.props.errors) : null)
))
</script>

<template>
    <main class="admin-shell min-h-screen bg-[#F3F8F5] text-[#26332D] lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
        <FlashToast
            :message="toastMessage"
            :token="toastToken"
            :variant="toastVariant"
        />

        <aside class="sticky top-0 hidden h-screen self-start overflow-y-auto border-r border-[#DDEBE3] bg-white px-5 py-7 lg:flex lg:flex-col">
            <div>
                <Link href="/admin/users" class="flex items-center gap-3">
                    <BrandMark class="h-9 w-9" />
                    <span>
                        <span class="block text-2xl font-bold tracking-normal text-[#173B2A]">Montry</span>
                        <span class="block text-xs font-medium tracking-[0.12em] text-[#7B8D82]">ADMIN</span>
                    </span>
                </Link>

                <nav class="mt-11 grid gap-1.5" aria-label="Админская навигация">
                    <component
                        :is="item.href ? Link : 'span'"
                        v-for="item in navigation"
                        :key="item.key"
                        :href="item.href"
                        class="group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                        :class="[
                            activeItem === item.key ? 'bg-[#E9F8EF] text-[#173B2A]' : 'text-[#6A7A70]',
                            item.href ? 'hover:bg-[#F3F8F5] hover:text-[#173B2A]' : 'cursor-not-allowed opacity-50',
                        ]"
                    >
                        <span
                            class="grid h-8 w-8 place-items-center rounded-xl border transition"
                            :class="activeItem === item.key ? 'border-[#BEE7CE] bg-[#DDF6E8] text-[#1E9B5D]' : 'border-[#DDEBE3] bg-[#F6FBF8] text-[#8A9A91]'"
                        >
                            <component :is="item.icon" class="h-4 w-4" :stroke-width="1.8" />
                        </span>
                        {{ item.label }}
                    </component>
                </nav>
            </div>

            <div class="mt-auto rounded-3xl border border-[#DDEBE3] bg-[#F6FBF8] p-4">
                <div v-if="user" class="min-w-0">
                    <p class="truncate text-sm font-semibold text-[#26332D]">{{ user.name }}</p>
                    <p class="mt-1 truncate text-xs font-normal text-[#7B8D82]">{{ user.email }}</p>
                </div>

                <Link
                    href="/sites"
                    class="mt-4 flex h-10 w-full items-center justify-center gap-2 rounded-2xl border border-[#CFE1D7] bg-white text-sm font-medium text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                >
                    В кабинет
                    <ChevronRight class="h-4 w-4" :stroke-width="1.8" />
                </Link>
            </div>
        </aside>

        <section class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-[#DDEBE3] bg-white/95 px-5 py-4 backdrop-blur sm:px-8">
                <div class="mx-auto flex max-w-7xl flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-3 lg:hidden">
                            <BrandMark class="h-8 w-8" />
                            <span class="text-xl font-bold text-[#173B2A]">Montry Admin</span>
                        </div>
                        <p class="mt-2 text-xs font-medium uppercase tracking-[0.12em] text-[#1E9B5D] lg:mt-0">Администрирование</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-[-0.02em] text-[#26332D] sm:text-[28px]">{{ title }}</h1>
                        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-[#6A7A70]">{{ subtitle }}</p>
                    </div>

                    <slot name="actions" />
                </div>

                <nav class="mx-auto mt-4 flex max-w-7xl gap-2 overflow-x-auto pb-1 lg:hidden" aria-label="Админская навигация">
                    <component
                        :is="item.href ? Link : 'span'"
                        v-for="item in navigation"
                        :key="item.key"
                        :href="item.href"
                        class="inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="activeItem === item.key ? 'bg-[#E9F8EF] text-[#173B2A]' : 'bg-[#F6FBF8] text-[#6A7A70]'"
                    >
                        <component :is="item.icon" class="h-4 w-4" :stroke-width="1.8" />
                        {{ item.label }}
                    </component>
                </nav>
            </header>

            <slot />
        </section>
    </main>
</template>
