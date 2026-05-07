<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

type Organization = {
    id: string
    name: string
}

type Site = {
    id: string
    name: string
    url: string
    host: string
    status: string
    last_checked_at: string | null
    created_at: string | null
}

defineProps<{
    organization: Organization
    sites: Site[]
}>()

function statusLabel(status: string): string {
    if (status === 'up') return 'Up'
    if (status === 'down') return 'Down'
    if (status === 'degraded') return 'Degraded'

    return 'Unknown'
}
</script>

<template>
    <Head title="Sites" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <header class="border-b border-white/10 px-6 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-400">
                        {{ organization.name }}
                    </p>
                    <h1 class="text-xl font-semibold">Sites</h1>
                </div>

                <Link
                    href="/sites/create"
                    class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-neutral-950 hover:bg-emerald-400"
                >
                    Add site
                </Link>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-6 py-10">
            <div
                v-if="sites.length === 0"
                class="rounded-2xl border border-white/10 bg-white/5 p-10 text-center"
            >
                <h2 class="text-lg font-semibold">No sites yet</h2>
                <p class="mt-2 text-sm text-neutral-400">
                    Add your first site to start monitoring.
                </p>

                <Link
                    href="/sites/create"
                    class="mt-6 inline-flex rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-neutral-950 hover:bg-emerald-400"
                >
                    Add site
                </Link>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-2xl border border-white/10 bg-white/5"
            >
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-white/10 text-neutral-400">
                    <tr>
                        <th class="px-5 py-4 font-medium">Name</th>
                        <th class="px-5 py-4 font-medium">URL</th>
                        <th class="px-5 py-4 font-medium">Status</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="site in sites"
                        :key="site.id"
                        class="border-b border-white/5 last:border-0"
                    >
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ site.name }}</div>
                            <div class="text-xs text-neutral-500">{{ site.host }}</div>
                        </td>

                        <td class="px-5 py-4 text-neutral-300">
                            {{ site.url }}
                        </td>

                        <td class="px-5 py-4">
                            <span
                                class="rounded-full border border-white/10 px-3 py-1 text-xs text-neutral-300"
                            >
                              {{ statusLabel(site.status) }}
                            </span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
