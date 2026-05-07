<script setup lang="ts">
import { computed } from 'vue'
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
    path?: string
    expected_status_min?: number
    expected_status_max?: number
    follow_redirects?: boolean
    host?: string
    port?: number
    warning_days?: number
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
}

const props = defineProps<{
    organization: Organization
    site: Site
    monitor: Monitor
    monitorTypes: MonitorTypeOption[]
}>()

const form = useForm({
    type: props.monitor.type,
    name: props.monitor.name,
    is_enabled: props.monitor.is_enabled,
    interval_seconds: props.monitor.interval_seconds,
    timeout_ms: props.monitor.timeout_ms,
    settings: {
        method: props.monitor.settings.method ?? 'GET',
        path: props.monitor.settings.path ?? props.site.path ?? '/',
        expected_status_min: props.monitor.settings.expected_status_min ?? 200,
        expected_status_max: props.monitor.settings.expected_status_max ?? 399,
        follow_redirects: props.monitor.settings.follow_redirects ?? true,

        host: props.monitor.settings.host ?? props.site.host,
        port: props.monitor.settings.port ?? props.site.port ?? 443,
        warning_days: props.monitor.settings.warning_days ?? 14,
    },
})

const selectedTypeLabel = computed(() => {
    return props.monitorTypes.find((type) => type.value === form.type)?.label ?? 'Monitor'
})

function submit(): void {
    form.put(`/sites/${props.site.id}/monitors/${props.monitor.id}`)
}
</script>

<template>
    <Head title="Edit monitor" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <header class="border-b border-white/10 px-6 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-400">
                        {{ organization.name }}
                    </p>

                    <h1 class="text-xl font-semibold">
                        Edit monitor
                    </h1>

                    <p class="mt-1 text-sm text-neutral-500">
                        {{ site.name }} · {{ site.url }}
                    </p>
                </div>

                <Link
                    :href="`/sites/${site.id}`"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                >
                    Back to site
                </Link>
            </div>
        </header>

        <section class="mx-auto max-w-3xl px-6 py-10">
            <form
                class="rounded-2xl border border-white/10 bg-white/5 p-6"
                @submit.prevent="submit"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ selectedTypeLabel }} settings
                    </h2>

                    <p class="mt-1 text-sm text-neutral-400">
                        Update this monitor configuration.
                    </p>
                </div>

                <div class="mt-6 grid gap-5">
                    <div>
                        <label class="text-sm font-medium text-neutral-300">
                            Monitor type
                        </label>

                        <input
                            :value="selectedTypeLabel"
                            type="text"
                            disabled
                            class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-900 px-4 py-3 text-sm text-neutral-500 outline-none"
                        >

                        <p class="mt-2 text-xs text-neutral-500">
                            Type cannot be changed after creation. Create a new monitor for another type.
                        </p>

                        <p
                            v-if="form.errors.type"
                            class="mt-2 text-sm text-red-400"
                        >
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-neutral-300">
                            Name
                        </label>

                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                        >

                        <p
                            v-if="form.errors.name"
                            class="mt-2 text-sm text-red-400"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-neutral-300">
                                Interval, seconds
                            </label>

                            <input
                                v-model.number="form.interval_seconds"
                                type="number"
                                min="30"
                                class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                            >

                            <p
                                v-if="form.errors.interval_seconds"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ form.errors.interval_seconds }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-neutral-300">
                                Timeout, ms
                            </label>

                            <input
                                v-model.number="form.timeout_ms"
                                type="number"
                                min="1000"
                                class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                            >

                            <p
                                v-if="form.errors.timeout_ms"
                                class="mt-2 text-sm text-red-400"
                            >
                                {{ form.errors.timeout_ms }}
                            </p>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-neutral-950/50 px-4 py-3">
                        <input
                            v-model="form.is_enabled"
                            type="checkbox"
                            class="rounded border-white/10 bg-neutral-950 text-emerald-500"
                        >

                        <span class="text-sm text-neutral-300">
                            Monitor is enabled
                        </span>
                    </label>

                    <div
                        v-if="form.type === 'http'"
                        class="rounded-2xl border border-white/10 bg-neutral-950/40 p-5"
                    >
                        <h3 class="font-medium">
                            HTTP settings
                        </h3>

                        <div class="mt-5 grid gap-5">
                            <div class="grid gap-5 md:grid-cols-[160px_1fr]">
                                <div>
                                    <label class="text-sm font-medium text-neutral-300">
                                        Method
                                    </label>

                                    <select
                                        v-model="form.settings.method"
                                        class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                                    >
                                        <option value="GET">GET</option>
                                        <option value="HEAD">HEAD</option>
                                        <option value="POST">POST</option>
                                    </select>

                                    <p
                                        v-if="form.errors['settings.method']"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors['settings.method'] }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-neutral-300">
                                        Path
                                    </label>

                                    <input
                                        v-model="form.settings.path"
                                        type="text"
                                        class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                                    >

                                    <p
                                        v-if="form.errors['settings.path']"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors['settings.path'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-neutral-300">
                                        Expected status min
                                    </label>

                                    <input
                                        v-model.number="form.settings.expected_status_min"
                                        type="number"
                                        min="100"
                                        max="599"
                                        class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                                    >

                                    <p
                                        v-if="form.errors['settings.expected_status_min']"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors['settings.expected_status_min'] }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-neutral-300">
                                        Expected status max
                                    </label>

                                    <input
                                        v-model.number="form.settings.expected_status_max"
                                        type="number"
                                        min="100"
                                        max="599"
                                        class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                                    >

                                    <p
                                        v-if="form.errors['settings.expected_status_max']"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors['settings.expected_status_max'] }}
                                    </p>
                                </div>
                            </div>

                            <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-neutral-950/50 px-4 py-3">
                                <input
                                    v-model="form.settings.follow_redirects"
                                    type="checkbox"
                                    class="rounded border-white/10 bg-neutral-950 text-emerald-500"
                                >

                                <span class="text-sm text-neutral-300">
                                    Follow redirects
                                </span>
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="form.type === 'ssl'"
                        class="rounded-2xl border border-white/10 bg-neutral-950/40 p-5"
                    >
                        <h3 class="font-medium">
                            SSL settings
                        </h3>

                        <div class="mt-5 grid gap-5">
                            <div>
                                <label class="text-sm font-medium text-neutral-300">
                                    Host
                                </label>

                                <input
                                    v-model="form.settings.host"
                                    type="text"
                                    class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                                >

                                <p
                                    v-if="form.errors['settings.host']"
                                    class="mt-2 text-sm text-red-400"
                                >
                                    {{ form.errors['settings.host'] }}
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-neutral-300">
                                        Port
                                    </label>

                                    <input
                                        v-model.number="form.settings.port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                                    >

                                    <p
                                        v-if="form.errors['settings.port']"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors['settings.port'] }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-neutral-300">
                                        Warning days
                                    </label>

                                    <input
                                        v-model.number="form.settings.warning_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                                    >

                                    <p
                                        v-if="form.errors['settings.warning_days']"
                                        class="mt-2 text-sm text-red-400"
                                    >
                                        {{ form.errors['settings.warning_days'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="form.errors.settings"
                        class="rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300"
                    >
                        {{ form.errors.settings }}
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-white/10 pt-6">
                    <Link
                        :href="`/sites/${site.id}`"
                        class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-medium text-neutral-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Save changes
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
