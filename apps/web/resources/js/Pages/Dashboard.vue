<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'

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

const page = usePage<PageProps>()
const user = page.props.auth.user
</script>

<template>
    <Head title="Dashboard" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <header class="border-b border-white/10 px-6 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-400">Montri</p>
                    <h1 class="text-xl font-semibold">Dashboard</h1>
                </div>

                <Link
                    href="/sites"
                    class="mt-6 inline-flex rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-neutral-950 hover:bg-emerald-400"
                >
                    Open sites
                </Link>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                >
                    Logout
                </Link>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-6 py-10">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-8">
                <p class="text-sm text-neutral-400">Signed in as</p>

                <template v-if="user">
                    <p class="mt-2 text-lg font-medium">{{ user.name }}</p>
                    <p class="text-sm text-neutral-500">{{ user.email }}</p>
                </template>
            </div>
        </section>
    </main>
</template>
