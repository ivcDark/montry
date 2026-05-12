<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

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
}

type MonitorTypeOption = {
    value: string
    label: string
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
    is_enabled: boolean
    interval_seconds: number
    timeout_ms: number
    settings: MonitorSettings
    expected: MonitorExpected
}

const props = defineProps<{
    organization: Organization
    site: Site
    monitor: Monitor
    monitorTypes: MonitorTypeOption[]
}>()

const statusCodesText = ref((props.monitor.expected.status_codes ?? [200]).join(', '))
const warningDaysText = ref((props.monitor.settings.warning_days ?? [30, 14, 7, 3, 1]).join(', '))

const form = useForm({
    type: props.monitor.type,
    name: props.monitor.name,
    is_enabled: props.monitor.is_enabled,
    interval_seconds: props.monitor.interval_seconds,
    timeout_ms: props.monitor.timeout_ms,
    settings: {
        method: props.monitor.settings.method ?? 'GET',
        url: props.monitor.settings.url ?? props.site.url,
        follow_redirects: props.monitor.settings.follow_redirects ?? true,
        verify_ssl: props.monitor.settings.verify_ssl ?? true,
        domain: props.monitor.settings.domain ?? props.site.host,
        port: props.monitor.settings.port ?? props.site.port ?? 443,
        warning_days: props.monitor.settings.warning_days ?? [30, 14, 7, 3, 1],
    },
    expected: {
        status_codes: props.monitor.expected.status_codes ?? [200],
        max_response_time_ms: props.monitor.expected.max_response_time_ms ?? 5000,
        valid: props.monitor.expected.valid ?? true,
        registered: props.monitor.expected.registered ?? true,
    },
})

const selectedTypeLabel = computed(() => {
    return props.monitorTypes.find((type) => type.value === form.type)?.label ?? 'Monitor'
})

function parseNumberList(value: string): number[] {
    return value
        .split(',')
        .map((item) => Number.parseInt(item.trim(), 10))
        .filter((item) => Number.isInteger(item))
}

function requestPayload() {
    if (form.type === 'http') {
        return {
            type: form.type,
            name: form.name,
            is_enabled: form.is_enabled,
            interval_seconds: form.interval_seconds,
            timeout_ms: form.timeout_ms,
            settings: {
                method: form.settings.method,
                url: form.settings.url,
                follow_redirects: form.settings.follow_redirects,
                verify_ssl: form.settings.verify_ssl,
            },
            expected: {
                status_codes: parseNumberList(statusCodesText.value),
                max_response_time_ms: form.expected.max_response_time_ms,
            },
        }
    }

    if (form.type === 'ssl') {
        return {
            type: form.type,
            name: form.name,
            is_enabled: form.is_enabled,
            interval_seconds: form.interval_seconds,
            timeout_ms: form.timeout_ms,
            settings: {
                domain: form.settings.domain,
                port: form.settings.port,
                warning_days: parseNumberList(warningDaysText.value),
            },
            expected: {
                valid: form.expected.valid,
            },
        }
    }

    return {
        type: form.type,
        name: form.name,
        is_enabled: form.is_enabled,
        interval_seconds: form.interval_seconds,
        timeout_ms: form.timeout_ms,
        settings: {
            domain: form.settings.domain,
            warning_days: parseNumberList(warningDaysText.value),
        },
        expected: {
            registered: form.expected.registered,
        },
    }
}

function submit(): void {
    form
        .transform(() => requestPayload())
        .put(`/sites/${props.site.id}/monitors/${props.monitor.id}`)
}
</script>

<template>
    <Head title="Edit monitor" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <header class="border-b border-white/10 px-4 py-4 sm:px-6">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-emerald-400">
                        {{ organization.name }}
                    </p>

                    <h1 class="text-xl font-semibold tracking-normal">
                        Edit monitor
                    </h1>

                    <p class="mt-1 truncate text-sm text-neutral-500">
                        {{ site.name }} · {{ site.url }}
                    </p>
                </div>

                <Link
                    :href="`/sites/${site.id}`"
                    class="shrink-0 rounded-lg border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                >
                    Back
                </Link>
            </div>
        </header>

        <section class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
            <form
                class="rounded-lg border border-white/10 bg-neutral-900 p-5 sm:p-6"
                @submit.prevent="submit"
            >
                <div>
                    <h2 class="text-lg font-semibold tracking-normal">
                        {{ selectedTypeLabel }} settings
                    </h2>

                    <p class="mt-1 text-sm text-neutral-400">
                        Type cannot be changed after creation.
                    </p>
                </div>

                <div class="mt-6 grid gap-5">
                    <div>
                        <label for="monitor-name" class="mb-2 block text-sm font-medium text-neutral-200">
                            Name <span class="text-red-300">*</span>
                        </label>

                        <input
                            id="monitor-name"
                            v-model="form.name"
                            type="text"
                            required
                            class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                        >

                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-300">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="interval" class="mb-2 block text-sm font-medium text-neutral-200">
                                Interval, seconds <span class="text-red-300">*</span>
                            </label>

                            <input
                                id="interval"
                                v-model.number="form.interval_seconds"
                                type="number"
                                min="30"
                                max="86400"
                                required
                                class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                            >

                            <p v-if="form.errors.interval_seconds" class="mt-2 text-sm text-red-300">
                                {{ form.errors.interval_seconds }}
                            </p>
                        </div>

                        <div>
                            <label for="timeout" class="mb-2 block text-sm font-medium text-neutral-200">
                                Timeout, ms <span class="text-red-300">*</span>
                            </label>

                            <input
                                id="timeout"
                                v-model.number="form.timeout_ms"
                                type="number"
                                min="1000"
                                max="60000"
                                required
                                class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                            >

                            <p v-if="form.errors.timeout_ms" class="mt-2 text-sm text-red-300">
                                {{ form.errors.timeout_ms }}
                            </p>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 rounded-lg border border-white/10 bg-neutral-950 px-3 py-3">
                        <input
                            v-model="form.is_enabled"
                            type="checkbox"
                            class="rounded border-white/10 bg-neutral-900 text-emerald-500"
                        >

                        <span class="text-sm text-neutral-300">
                            Monitor is enabled
                        </span>
                    </label>

                    <div v-if="form.type === 'http'" class="grid gap-5 rounded-lg border border-white/10 bg-neutral-950 p-4">
                        <div class="grid gap-5 md:grid-cols-[140px_1fr]">
                            <div>
                                <label for="method" class="mb-2 block text-sm font-medium text-neutral-200">
                                    Method <span class="text-red-300">*</span>
                                </label>

                                <select
                                    id="method"
                                    v-model="form.settings.method"
                                    class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                                >
                                    <option value="GET">GET</option>
                                    <option value="HEAD">HEAD</option>
                                    <option value="POST">POST</option>
                                </select>
                            </div>

                            <div>
                                <label for="url" class="mb-2 block text-sm font-medium text-neutral-200">
                                    URL <span class="text-red-300">*</span>
                                </label>

                                <input
                                    id="url"
                                    v-model="form.settings.url"
                                    type="url"
                                    required
                                    class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                                >
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="status-codes" class="mb-2 block text-sm font-medium text-neutral-200">
                                    Expected status codes <span class="text-red-300">*</span>
                                </label>

                                <input
                                    id="status-codes"
                                    v-model="statusCodesText"
                                    type="text"
                                    required
                                    class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                                >
                            </div>

                            <div>
                                <label for="response-time" class="mb-2 block text-sm font-medium text-neutral-200">
                                    Max response time, ms <span class="text-red-300">*</span>
                                </label>

                                <input
                                    id="response-time"
                                    v-model.number="form.expected.max_response_time_ms"
                                    type="number"
                                    min="1"
                                    required
                                    class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                                >
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-3">
                                <input v-model="form.settings.follow_redirects" type="checkbox" class="rounded border-white/10 bg-neutral-900 text-emerald-500">
                                <span class="text-sm text-neutral-300">Follow redirects</span>
                            </label>

                            <label class="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-3">
                                <input v-model="form.settings.verify_ssl" type="checkbox" class="rounded border-white/10 bg-neutral-900 text-emerald-500">
                                <span class="text-sm text-neutral-300">Verify SSL</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="form.type === 'ssl' || form.type === 'domain'" class="grid gap-5 rounded-lg border border-white/10 bg-neutral-950 p-4">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="domain" class="mb-2 block text-sm font-medium text-neutral-200">
                                    Domain <span class="text-red-300">*</span>
                                </label>

                                <input
                                    id="domain"
                                    v-model="form.settings.domain"
                                    type="text"
                                    required
                                    class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                                >
                            </div>

                            <div v-if="form.type === 'ssl'">
                                <label for="port" class="mb-2 block text-sm font-medium text-neutral-200">
                                    Port <span class="text-red-300">*</span>
                                </label>

                                <input
                                    id="port"
                                    v-model.number="form.settings.port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    required
                                    class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="warning-days" class="mb-2 block text-sm font-medium text-neutral-200">
                                Warning days <span class="text-red-300">*</span>
                            </label>

                            <input
                                id="warning-days"
                                v-model="warningDaysText"
                                type="text"
                                required
                                class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                            >
                        </div>
                    </div>

                    <div v-if="form.errors.settings || form.errors.expected" class="rounded-lg border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                        {{ form.errors.settings || form.errors.expected }}
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-white/10 pt-6">
                    <Link
                        :href="`/sites/${site.id}`"
                        class="rounded-lg border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-neutral-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>Save changes</span>
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
