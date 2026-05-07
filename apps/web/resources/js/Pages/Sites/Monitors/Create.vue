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

const props = defineProps<{
    organization: Organization
    site: Site
    monitorTypes: MonitorTypeOption[]
}>()

const form = useForm({
    type: 'http',
    name: 'HTTP check',
    is_enabled: true,
    interval_seconds: 60,
    timeout_ms: 10000,
    settings: {
        method: 'GET',
        path: props.site.path || '/',
        expected_status_min: 200,
        expected_status_max: 399,
        follow_redirects: true,
        host: props.site.host,
        port: props.site.port ?? 443,
        warning_days: 14,
    },
})

const selectedTypeLabel = computed(() => {
    return props.monitorTypes.find((type) => type.value === form.type)?.label ?? 'Monitor'
})

function selectType(type: string): void {
    form.type = type

    if (type === 'http') {
        form.name = 'HTTP check'
        form.timeout_ms = 10000
        form.settings.method = 'GET'
        form.settings.path = props.site.path || '/'
        form.settings.expected_status_min = 200
        form.settings.expected_status_max = 399
        form.settings.follow_redirects = true
    }

    if (type === 'ssl') {
        form.name = 'SSL certificate check'
        form.timeout_ms = 10000
        form.settings.host = props.site.host
        form.settings.port = props.site.port ?? 443
        form.settings.warning_days = 14
    }
}

function submit(): void {
    form.post(`/sites/${props.site.id}/monitors`)
}
</script>

<template>
    <Head title="Add monitor" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <header class="border-b border-white/10 px-6 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-400">
                        {{ organization.name }}
                    </p>

                    <h1 class="text-xl font-semibold">
                        Add monitor
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

        <section class="mx-auto grid max-w-6xl gap-6 px-6 py-10 lg:grid-cols-[320px_1fr]">
            <aside class="rounded-2xl border border-white/10 bg-white/5 p-5">
                <h2 class="text-sm font-semibold text-neutral-200">
                    Monitor type
                </h2>

                <p class="mt-1 text-sm text-neutral-500">
                    Choose what Montri should check.
                </p>

                <div class="mt-5 space-y-3">
                    <button
                        v-for="type in monitorTypes"
                        :key="type.value"
                        type="button"
                        class="w-full rounded-xl border px-4 py-3 text-left transition"
                        :class="form.type === type.value
                            ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300'
                            : 'border-white/10 bg-neutral-950/40 text-neutral-300 hover:bg-white/5'"
                        @click="selectType(type.value)"
                    >
                        <span class="block font-medium">
                            {{ type.label }}
                        </span>

                        <span
                            v-if="type.value === 'http'"
                            class="mt-1 block text-xs text-neutral-500"
                        >
                            Check status code, redirects and response time.
                        </span>

                        <span
                            v-if="type.value === 'ssl'"
                            class="mt-1 block text-xs text-neutral-500"
                        >
                            Check certificate validity and expiry window.
                        </span>
                    </button>
                </div>

                <p
                    v-if="form.errors.type"
                    class="mt-3 text-sm text-red-400"
                >
                    {{ form.errors.type }}
                </p>
            </aside>

            <form
                class="rounded-2xl border border-white/10 bg-white/5 p-6"
                @submit.prevent="submit"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ selectedTypeLabel }} settings
                    </h2>

                    <p class="mt-1 text-sm text-neutral-400">
                        Configure how this monitor should run.
                    </p>
                </div>

                <div class="mt-6 grid gap-5">
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
                            Enable this monitor immediately
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
                        Create monitor
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
