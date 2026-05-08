<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'

type Organization = {
    id: string
    name: string
}

type MonitorSettings = {
    method?: string
    path?: string
    expected_status_min?: number
    expected_status_max?: number
    follow_redirects?: boolean
    [key: string]: unknown
}

type Monitor = {
    id: string
    type: string
    name: string
    is_enabled: boolean
    interval_seconds: number | null
    timeout_ms: number | null
    settings: MonitorSettings | null
    created_at: string | null
    updated_at: string | null
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
    monitors: Monitor[]
}

const props = defineProps<{
    organization: Organization
    site: Site
}>()

function statusLabel(status: string): string {
    if (status === 'up') return 'Up'
    if (status === 'down') return 'Down'
    if (status === 'degraded') return 'Degraded'

    return 'Unknown'
}

function statusClass(status: string): string {
    if (status === 'up') return 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20'
    if (status === 'down') return 'bg-red-500/10 text-red-400 ring-red-500/20'
    if (status === 'degraded') return 'bg-yellow-500/10 text-yellow-400 ring-yellow-500/20'

    return 'bg-neutral-500/10 text-neutral-400 ring-neutral-500/20'
}

function monitorTypeLabel(type: string): string {
    if (type === 'http') return 'HTTP'
    if (type === 'ping') return 'Ping'
    if (type === 'dns') return 'DNS'
    if (type === 'ssl') return 'SSL'

    return type.toUpperCase()
}

function monitorTypeClass(type: string): string {
    if (type === 'http') return 'bg-blue-500/10 text-blue-400 ring-blue-500/20'
    if (type === 'ping') return 'bg-purple-500/10 text-purple-400 ring-purple-500/20'
    if (type === 'dns') return 'bg-cyan-500/10 text-cyan-400 ring-cyan-500/20'
    if (type === 'ssl') return 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20'

    return 'bg-neutral-500/10 text-neutral-400 ring-neutral-500/20'
}

function formatTimeout(timeoutMs: number | null): string {
    if (!timeoutMs) return 'Default timeout'

    if (timeoutMs >= 1000) {
        return `${timeoutMs / 1000}s timeout`
    }

    return `${timeoutMs}ms timeout`
}

function formatInterval(intervalSeconds: number | null): string {
    if (!intervalSeconds) return 'Default interval'

    if (intervalSeconds < 60) {
        return `Every ${intervalSeconds}s`
    }

    const minutes = intervalSeconds / 60

    if (Number.isInteger(minutes)) {
        return `Every ${minutes}m`
    }

    return `Every ${intervalSeconds}s`
}

function httpExpectedStatus(settings: MonitorSettings | null): string {
    const min = settings?.expected_status_min
    const max = settings?.expected_status_max

    if (!min || !max) return 'Any status'

    return `${min}–${max}`
}

function httpMethod(settings: MonitorSettings | null): string {
    return settings?.method ?? 'GET'
}

function httpPath(settings: MonitorSettings | null): string {
    return settings?.path ?? '/'
}

function toggleMonitor(monitor: Monitor): void {
    router.patch(`/sites/${props.site.id}/monitors/${monitor.id}/toggle`, {}, {
        preserveScroll: true,
    })
}

function deleteMonitor(monitor: Monitor): void {
    if (!confirm(`Delete monitor "${monitor.name}"?`)) {
        return
    }

    router.delete(`/sites/${props.site.id}/monitors/${monitor.id}`, {
        preserveScroll: true,
    })
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

                    <div class="mt-3 flex items-center gap-3">
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset"
                            :class="statusClass(site.status)"
                        >
                            {{ statusLabel(site.status) }}
                        </span>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <p class="text-sm text-neutral-400">Host</p>

                    <p class="mt-3 truncate text-2xl font-semibold">
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

                    <p class="mt-3 truncate text-2xl font-semibold">
                        {{ site.path }}
                    </p>

                    <p class="mt-2 text-sm text-neutral-500">
                        Created: {{ site.created_at }}
                    </p>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Monitors</h2>

                        <p class="mt-1 text-sm text-neutral-400">
                            Automated checks configured for this site.
                        </p>
                    </div>

                    <Link
                        :href="`/sites/${site.id}/monitors/create`"
                        class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                    >
                        Add monitor
                    </Link>
                </div>

                <div
                    v-if="site.monitors.length > 0"
                    class="mt-6 space-y-4"
                >
                    <article
                        v-for="monitor in site.monitors"
                        :key="monitor.id"
                        class="rounded-2xl border border-white/10 bg-neutral-950/40 p-5"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="font-semibold">
                                        {{ monitor.name }}
                                    </h3>

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="monitorTypeClass(monitor.type)"
                                    >
                                        {{ monitorTypeLabel(monitor.type) }}
                                    </span>

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="monitor.is_enabled
                                            ? 'bg-emerald-500/10 text-emerald-400 ring-emerald-500/20'
                                            : 'bg-neutral-500/10 text-neutral-400 ring-neutral-500/20'"
                                    >
                                        {{ monitor.is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-neutral-500">
                                    {{ formatInterval(monitor.interval_seconds) }}
                                    ·
                                    {{ formatTimeout(monitor.timeout_ms) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl border border-white/10 px-3 py-2 text-sm text-neutral-300 hover:bg-white/5"
                                    @click="toggleMonitor(monitor)"
                                >
                                    {{ monitor.is_enabled ? 'Pause' : 'Resume' }}
                                </button>

                                <Link
                                    :href="`/sites/${site.id}/monitors/${monitor.id}/edit`"
                                    class="rounded-xl border border-white/10 px-3 py-2 text-sm text-neutral-300 hover:bg-white/5"
                                >
                                    Edit
                                </Link>

                                <button
                                    type="button"
                                    class="rounded-xl border border-red-500/20 px-3 py-2 text-sm text-red-300 hover:bg-red-500/10"
                                    @click="deleteMonitor(monitor)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="monitor.type === 'http'"
                            class="mt-5 grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-4"
                        >
                            <div>
                                <p class="text-xs uppercase tracking-wide text-neutral-500">
                                    Method
                                </p>

                                <p class="mt-1 text-sm font-medium text-neutral-200">
                                    {{ httpMethod(monitor.settings) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-neutral-500">
                                    Path
                                </p>

                                <p class="mt-1 truncate text-sm font-medium text-neutral-200">
                                    {{ httpPath(monitor.settings) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-neutral-500">
                                    Expected status
                                </p>

                                <p class="mt-1 text-sm font-medium text-neutral-200">
                                    {{ httpExpectedStatus(monitor.settings) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-neutral-500">
                                    Redirects
                                </p>

                                <p class="mt-1 text-sm font-medium text-neutral-200">
                                    {{ monitor.settings?.follow_redirects ? 'Follow' : 'Do not follow' }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="monitor.type === 'ssl'"
                            class="mt-5 grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-3"
                        >
                            <div>
                                <p class="text-xs uppercase tracking-wide text-neutral-500">
                                    Host
                                </p>

                                <p class="mt-1 truncate text-sm font-medium text-neutral-200">
                                    {{ monitor.settings?.host ?? site.host }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-neutral-500">
                                    Port
                                </p>

                                <p class="mt-1 text-sm font-medium text-neutral-200">
                                    {{ monitor.settings?.port ?? 443 }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-neutral-500">
                                    Warning
                                </p>

                                <p class="mt-1 text-sm font-medium text-neutral-200">
                                    {{ monitor.settings?.warning_days ?? 14 }} days before expiry
                                </p>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-xl border border-dashed border-white/10 p-8 text-center"
                >
                    <p class="text-sm text-neutral-400">
                        No monitors yet. New sites should automatically receive a default HTTP monitor.
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
