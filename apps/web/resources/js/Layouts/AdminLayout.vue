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

type NavigationItem = {
    key: string
    label: string
    href?: string
}

defineProps<{
    activeItem: string
    title: string
    subtitle: string
}>()

const navigation: NavigationItem[] = [
    { key: 'users', label: 'Пользователи', href: '/admin/users' },
    { key: 'dead_letters', label: 'Dead letters', href: '/admin/dead-letters' },
    { key: 'organizations', label: 'Организации' },
    { key: 'monitors', label: 'Мониторы' },
    { key: 'incidents', label: 'Инциденты' },
    { key: 'billing', label: 'Биллинг' },
]

const page = usePage<PageProps>()
const user = page.props.auth.user
</script>

<template>
    <main class="min-h-screen bg-[#F6F8FB] font-sans text-[#111827] lg:grid lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="sticky top-0 hidden h-screen self-start overflow-y-auto bg-[#0B1220] px-5 py-7 text-white lg:flex lg:flex-col">
            <div>
                <Link href="/admin/users" class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0F6BFF] text-lg font-extrabold text-white">A</span>
                    <span>
                        <span class="block text-2xl font-extrabold tracking-normal">Montry</span>
                        <span class="block text-xs font-extrabold uppercase tracking-[0.18em] text-[#94A3B8]">Admin</span>
                    </span>
                </Link>

                <nav class="mt-8 grid gap-1" aria-label="Админская навигация">
                    <component
                        :is="item.href ? Link : 'span'"
                        v-for="item in navigation"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center rounded-xl px-4 py-3 text-sm font-bold transition"
                        :class="[
                            activeItem === item.key ? 'bg-[#17233A] text-white' : 'text-[#94A3B8]',
                            item.href ? 'hover:bg-white/5 hover:text-white' : 'cursor-not-allowed opacity-55',
                        ]"
                    >
                        {{ item.label }}
                    </component>
                </nav>
            </div>

            <div class="mt-auto border-t border-white/10 pt-5">
                <div v-if="user" class="min-w-0">
                    <p class="truncate text-sm font-extrabold text-white">{{ user.name }}</p>
                    <p class="mt-1 truncate text-xs font-semibold text-[#94A3B8]">{{ user.email }}</p>
                </div>

                <Link
                    href="/dashboard"
                    class="mt-4 flex h-10 w-full items-center justify-center rounded-xl border border-white/10 text-sm font-extrabold text-[#CBD5E1] transition hover:border-white/20 hover:bg-white/5 hover:text-white"
                >
                    В кабинет
                </Link>
            </div>
        </aside>

        <section class="min-w-0">
            <header class="border-b border-[#E5E7EB] bg-white px-5 py-5 sm:px-8">
                <div class="mx-auto flex max-w-7xl flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-bold text-[#0F6BFF]">Администрирование</p>
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
