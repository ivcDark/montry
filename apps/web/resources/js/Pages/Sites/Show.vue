<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'

type Organization = {
    id: string
    name: string
}

type MonitorSettings = {
    method?: string
    url?: string
    follow_redirects?: boolean
    verify_ssl?: boolean
    domain?: string
    port?: number
    warning_days?: number[]
    [key: string]: unknown
}

type MonitorExpected = {
    status_codes?: number[]
    max_response_time_ms?: number
    valid?: boolean
    registered?: boolean
    [key: string]: unknown
}

type Monitor = {
    id: string
    type: string
    name: string
    status: string
    is_enabled: boolean
    interval_seconds: number | null
    timeout_ms: number | null
    settings: MonitorSettings | null
    expected: MonitorExpected | null
    last_check_at: string | null
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
    if (status === 'success' || status === 'up') return 'Up'
    if (status === 'failure' || status === 'down') return 'Down'
    if (status === 'degraded') return 'Degraded'
    if (status === 'paused') return 'Paused'

    return 'Unknown'
}

function statusClass(status: string): string {
    if (status === 'success' || status === 'up') return 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20'
    if (status === 'failure' || status === 'down') return 'bg-red-500/10 text-red-300 ring-red-500/20'
    if (status === 'degraded') return 'bg-yellow-500/10 text-yellow-300 ring-yellow-500/20'
    if (status === 'paused') return 'bg-neutral-500/10 text-neutral-300 ring-neutral-500/20'

    return 'bg-neutral-500/10 text-neutral-400 ring-neutral-500/20'
}

function monitorTypeLabel(type: string): string {
    if (type === 'http') return 'HTTP'
    if (type === 'ssl') return 'SSL'
    if (type === 'domain') return 'Domain'

    return type.toUpperCase()
}

function monitorTypeClass(type: string): string {
    if (type === 'http') return 'bg-sky-500/10 text-sky-300 ring-sky-500/20'
    if (type === 'ssl') return 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20'
    if (type === 'domain') return 'bg-amber-500/10 text-amber-300 ring-amber-500/20'

    return 'bg-neutral-500/10 text-neutral-400 ring-neutral-500/20'
}

function formatTimeout(timeoutMs: number | null): string {
    if (!timeoutMs) return 'Default timeout'

    return timeoutMs >= 1000 ? `${timeoutMs / 1000}s timeout` : `${timeoutMs}ms timeout`
}

function formatInterval(intervalSeconds: number | null): string {
    if (!intervalSeconds) return 'Default interval'
    if (intervalSeconds < 60) return `Every ${intervalSeconds}s`

    const minutes = intervalSeconds / 60

    return Number.isInteger(minutes) ? `Every ${minutes}m` : `Every ${intervalSeconds}s`
}

function formatDate(value: string | null): string {
    if (!value) return 'Not checked yet'

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

function httpExpectedStatus(expected: MonitorExpected | null): string {
    const codes = expected?.status_codes

    if (!codes || codes.length === 0) return 'Any status'

    return codes.join(', ')
}

function warningDays(settings: MonitorSettings | null): string {
    const days = settings?.warning_days

    if (!Array.isArray(days) || days.length === 0) return 'Default warnings'

    return days.join(', ') + ' days'
}

function toggleMonitor(monitor: Monitor): void {
    router.patch(`/sites/${props.site.id}/monitors/${monitor.id}/toggle`, {}, {
        preserveScroll: true,
    })
}

function checkNow(monitor: Monitor): void {
    router.post(`/monitors/${monitor.id}/check-now`, {}, {
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
        <header class="border-b border-white/10 px-4 py-4 sm:px-6">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-emerald-400">
                        {{ organization.name }}
                    </p>

                    <h1 class="truncate text-xl font-semibold tracking-normal">
                        {{ site.name }}
                    </h1>

                    <p class="mt-1 truncate text-sm text-neutral-500">
                        {{ site.url }}
                    </p>
                </div>

                <Link
                    href="/sites"
                    class="shrink-0 rounded-lg border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                >
                    Back
                </Link>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-white/10 bg-neutral-900 p-5">
                    <p class="text-sm text-neutral-400">Resource status</p>

                    <span
                        class="mt-3 inline-flex rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset"
                        :class="statusClass(site.status)"
                    >
                        {{ statusLabel(site.status) }}
                    </span>
                </div>

                <div class="rounded-lg border border-white/10 bg-neutral-900 p-5">
                    <p class="text-sm text-neutral-400">Host</p>
                    <p class="mt-3 truncate text-xl font-semibold">{{ site.host }}</p>
                    <p class="mt-2 text-sm text-neutral-500">{{ site.scheme.toUpperCase() }}<template v-if="site.port">:{{ site.port }}</template></p>
                </div>

                <div class="rounded-lg border border-white/10 bg-neutral-900 p-5">
                    <p class="text-sm text-neutral-400">Path</p>
                    <p class="mt-3 truncate text-xl font-semibold">{{ site.path }}</p>
                    <p class="mt-2 text-sm text-neutral-500">Created: {{ formatDate(site.created_at) }}</p>
                </div>
            </div>

            <div class="mt-6 rounded-lg border border-white/10 bg-neutral-900 p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold tracking-normal">Monitors</h2>
                        <p class="mt-1 text-sm text-neutral-400">HTTP, SSL and domain checks for this resource.</p>
                    </div>

                    <Link
                        :href="`/sites/${site.id}/monitors/create`"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-white/10 px-4 text-sm text-neutral-300 hover:bg-white/5"
                    >
                        Add monitor
                    </Link>
                </div>

                <div v-if="site.monitors.length > 0" class="mt-6 grid gap-4">
                    <article
                        v-for="monitor in site.monitors"
                        :key="monitor.id"
                        class="rounded-lg border border-white/10 bg-neutral-950 p-4"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold">{{ monitor.name }}</h3>

                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="monitorTypeClass(monitor.type)">
                                        {{ monitorTypeLabel(monitor.type) }}
                                    </span>

                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusClass(monitor.status)">
                                        {{ statusLabel(monitor.status) }}
                                    </span>

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="monitor.is_enabled ? 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20' : 'bg-neutral-500/10 text-neutral-400 ring-neutral-500/20'"
                                    >
                                        {{ monitor.is_enabled ? 'Enabled' : 'Paused' }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-neutral-500">
                                    {{ formatInterval(monitor.interval_seconds) }} · {{ formatTimeout(monitor.timeout_ms) }} · {{ formatDate(monitor.last_check_at) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="h-9 rounded-lg border border-white/10 px-3 text-sm text-neutral-300 hover:bg-white/5 disabled:opacity-50"
                                    :disabled="!monitor.is_enabled"
                                    @click="checkNow(monitor)"
                                >
                                    Check now
                                </button>

                                <button
                                    type="button"
                                    class="h-9 rounded-lg border border-white/10 px-3 text-sm text-neutral-300 hover:bg-white/5"
                                    @click="toggleMonitor(monitor)"
                                >
                                    {{ monitor.is_enabled ? 'Pause' : 'Resume' }}
                                </button>

                                <Link
                                    :href="`/sites/${site.id}/monitors/${monitor.id}/edit`"
                                    class="inline-flex h-9 items-center rounded-lg border border-white/10 px-3 text-sm text-neutral-300 hover:bg-white/5"
                                >
                                    Edit
                                </Link>

                                <button
                                    type="button"
                                    class="h-9 rounded-lg border border-red-500/20 px-3 text-sm text-red-300 hover:bg-red-500/10"
                                    @click="deleteMonitor(monitor)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>

                        <div v-if="monitor.type === 'http'" class="mt-5 grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-4">
                            <div>
                                <p class="text-xs uppercase text-neutral-500">Method</p>
                                <p class="mt-1 text-sm font-medium text-neutral-200">{{ monitor.settings?.method ?? 'GET' }}</p>
                            </div>

                            <div>
                                <p class="text-xs uppercase text-neutral-500">URL</p>
                                <p class="mt-1 truncate text-sm font-medium text-neutral-200">{{ monitor.settings?.url ?? site.url }}</p>
                            </div>

                            <div>
                                <p class="text-xs uppercase text-neutral-500">Expected</p>
                                <p class="mt-1 text-sm font-medium text-neutral-200">{{ httpExpectedStatus(monitor.expected) }}</p>
                            </div>

                            <div>
                                <p class="text-xs uppercase text-neutral-500">Max response</p>
                                <p class="mt-1 text-sm font-medium text-neutral-200">{{ monitor.expected?.max_response_time_ms ?? 5000 }} ms</p>
                            </div>
                        </div>

                        <div v-if="monitor.type === 'ssl' || monitor.type === 'domain'" class="mt-5 grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-3">
                            <div>
                                <p class="text-xs uppercase text-neutral-500">Domain</p>
                                <p class="mt-1 truncate text-sm font-medium text-neutral-200">{{ monitor.settings?.domain ?? site.host }}</p>
                            </div>

                            <div v-if="monitor.type === 'ssl'">
                                <p class="text-xs uppercase text-neutral-500">Port</p>
                                <p class="mt-1 text-sm font-medium text-neutral-200">{{ monitor.settings?.port ?? 443 }}</p>
                            </div>

                            <div>
                                <p class="text-xs uppercase text-neutral-500">Warnings</p>
                                <p class="mt-1 text-sm font-medium text-neutral-200">{{ warningDays(monitor.settings) }}</p>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-6 rounded-lg border border-dashed border-white/10 p-8 text-center">
                    <p class="text-sm text-neutral-400">
                        No monitors yet. Add an HTTP, SSL or domain check.
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
