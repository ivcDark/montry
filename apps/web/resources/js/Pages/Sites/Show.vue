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
    scheme: string
    host: string
    port: number | null
    path: string
    status: string
    created_at: string | null
    updated_at: string | null
}

defineProps<{
    organization: Organization
    site: Site
}>()

function statusLabel(status: string): string {
    if (status === 'up') return 'Up'
    if (status === 'down') return 'Down'
    if (status === 'degraded') return 'Degraded'

    return 'Unknown'
}
</script>

<template>
    <Head :title="site.name" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <header class="border-b border-white/10 px-6 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-400">
                        {{ organization.name }}
                    </p>

                    <h1 class="text-xl font-semibold">
                        {{ site.name }}
                    </h1>

                    <p class="mt-1 text-sm text-neutral-500">
                        {{ site.url }}
                    </p>
                </div>

                <Link
                    href="/sites"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                >
                    Back to sites
                </Link>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-6 py-10">
            <div class="grid gap-6 md:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <p class="text-sm text-neutral-400">Status</p>

                    <p class="mt-3 text-2xl font-semibold">
                        {{ statusLabel(site.status) }}
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <p class="text-sm text-neutral-400">Host</p>

                    <p class="mt-3 text-2xl font-semibold">
                        {{ site.host }}
                    </p>

                    <p class="mt-2 text-sm text-neutral-500">
                        {{ site.scheme.toUpperCase() }}
                        <template v-if="site.port">
                            :{{ site.port }}
                        </template>
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <p class="text-sm text-neutral-400">Path</p>

                    <p class="mt-3 text-2xl font-semibold">
                        {{ site.path }}
                    </p>

                    <p class="mt-2 text-sm text-neutral-500">
                        Created: {{ site.created_at }}
                    </p>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Monitors</h2>
                        <p class="mt-1 text-sm text-neutral-400">
                            Checks for this site will appear here.
                        </p>
                    </div>

                    <button
                        type="button"
                        disabled
                        class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-500"
                    >
                        Add monitor soon
                    </button>
                </div>

                <div class="mt-6 rounded-xl border border-dashed border-white/10 p-8 text-center">
                    <p class="text-sm text-neutral-400">
                        No monitors yet. The next step is to create a default HTTP monitor for every new site.
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
