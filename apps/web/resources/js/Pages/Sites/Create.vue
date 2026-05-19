<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Organization = {
    id: string
    name: string
}

type MonitorTypeOption = {
    value: string
    label: string
}

const props = defineProps<{
    organization: Organization
    monitorTypes: MonitorTypeOption[]
}>()

const statusCodesText = ref('200')
const sslWarningDaysText = ref('30, 14, 7, 3, 1')
const domainWarningDaysText = ref('30, 14, 7, 3, 1')
const intervalPresets = [5, 10, 15, 30, 60, 360, 720, 1440]

const form = useForm({
    name: '',
    url: '',
    monitors: {
        http: {
            is_enabled: true,
            name: 'HTTP availability',
            interval_seconds: 300,
            timeout_ms: 10000,
            method: 'GET',
            follow_redirects: true,
            verify_ssl: true,
            max_response_time_ms: 5000,
        },
        ssl: {
            is_enabled: false,
            name: 'SSL certificate',
            interval_seconds: 86400,
            timeout_ms: 10000,
            port: 443,
            valid: true,
        },
        domain: {
            is_enabled: false,
            name: 'Domain expiration',
            interval_seconds: 86400,
            timeout_ms: 10000,
            registered: true,
        },
    },
})

const normalizedSite = computed(() => normalizeUrl(form.url))
const httpUrl = computed(() => normalizedSite.value?.url ?? form.url)
const domain = computed(() => normalizedSite.value?.host ?? '')

const monitorCards = computed(() => [
    {
        type: 'http',
        label: typeLabel('http'),
        enabled: form.monitors.http.is_enabled,
        title: 'Доступность сайта',
        description: 'Проверяет код ответа, время ответа и редиректы.',
        summary: `${form.monitors.http.method} · ${statusCodesText.value} · ${form.monitors.http.max_response_time_ms} мс`,
    },
    {
        type: 'ssl',
        label: typeLabel('ssl'),
        enabled: form.monitors.ssl.is_enabled,
        title: 'SSL сертификат',
        description: 'Следит за валидностью сертификата и сроком истечения.',
        summary: `${domain.value || 'домен из URL'} · порт ${form.monitors.ssl.port}`,
    },
    {
        type: 'domain',
        label: typeLabel('domain'),
        enabled: form.monitors.domain.is_enabled,
        title: 'Срок домена',
        description: 'Предупреждает, когда домен близок к окончанию регистрации.',
        summary: `${domain.value || 'домен из URL'} · ${domainWarningDaysText.value} дней`,
    },
])

function typeLabel(type: string): string {
    return props.monitorTypes.find((option) => option.value === type)?.label
        ?? (type === 'http' ? 'HTTP' : type === 'ssl' ? 'SSL' : 'Domain')
}

function typeClass(type: string): string {
    if (type === 'http') return 'bg-[#EAF2FF] text-[#0F6BFF]'
    if (type === 'ssl') return 'bg-[#ECFDF3] text-[#16A34A]'
    if (type === 'domain') return 'bg-[#FFF7E8] text-[#F59E0B]'

    return 'bg-[#F1F5F9] text-[#64748B]'
}

function parseNumberList(value: string): number[] {
    return value
        .split(',')
        .map((item) => Number.parseInt(item.trim(), 10))
        .filter((item) => Number.isInteger(item))
}

function intervalMinutes(seconds: number): number {
    return Math.round(seconds / 60)
}

function setIntervalMinutes(target: { interval_seconds: number }, minutes: number): void {
    target.interval_seconds = minutes * 60
}

function intervalText(seconds: number): string {
    const minutes = intervalMinutes(seconds)

    if (minutes === 60) return 'Каждый час'
    if (minutes === 1440) return 'Раз в день'
    if (minutes > 60 && minutes % 60 === 0) return `Каждые ${minutes / 60} ч`

    return `Каждые ${minutes} мин`
}

function normalizeUrl(value: string): { url: string, host: string, port: number | null } | null {
    const trimmed = value.trim()

    if (!trimmed) return null

    try {
        const parsed = new URL(trimmed.includes('://') ? trimmed : `https://${trimmed}`)

        return {
            url: parsed.toString(),
            host: parsed.hostname,
            port: parsed.port ? Number.parseInt(parsed.port, 10) : null,
        }
    } catch {
        return null
    }
}

function requestPayload() {
    const fallbackHost = domain.value || form.url.trim()

    return {
        name: form.name,
        url: form.url,
        monitors: [
            {
                type: 'http',
                name: form.monitors.http.name,
                is_enabled: form.monitors.http.is_enabled,
                interval_seconds: form.monitors.http.interval_seconds,
                timeout_ms: form.monitors.http.timeout_ms,
                settings: {
                    method: form.monitors.http.method,
                    url: httpUrl.value,
                    follow_redirects: form.monitors.http.follow_redirects,
                    verify_ssl: form.monitors.http.verify_ssl,
                },
                expected: {
                    status_codes: parseNumberList(statusCodesText.value),
                    max_response_time_ms: form.monitors.http.max_response_time_ms,
                },
            },
            {
                type: 'ssl',
                name: form.monitors.ssl.name,
                is_enabled: form.monitors.ssl.is_enabled,
                interval_seconds: form.monitors.ssl.interval_seconds,
                timeout_ms: form.monitors.ssl.timeout_ms,
                settings: {
                    domain: fallbackHost,
                    port: form.monitors.ssl.port,
                    warning_days: parseNumberList(sslWarningDaysText.value),
                },
                expected: {
                    valid: form.monitors.ssl.valid,
                },
            },
            {
                type: 'domain',
                name: form.monitors.domain.name,
                is_enabled: form.monitors.domain.is_enabled,
                interval_seconds: form.monitors.domain.interval_seconds,
                timeout_ms: form.monitors.domain.timeout_ms,
                settings: {
                    domain: fallbackHost,
                    warning_days: parseNumberList(domainWarningDaysText.value),
                },
                expected: {
                    registered: form.monitors.domain.registered,
                },
            },
        ],
    }
}

function submit(): void {
    form
        .transform(() => requestPayload())
        .post('/sites', {
            preserveScroll: true,
        })
}
</script>

<template>
    <Head title="Добавить сайт" />

    <DashboardLayout
        :organization="organization"
        active-item="sites"
        title="Добавить сайт"
        subtitle="Создайте сайт и сразу подготовьте HTTP, SSL и доменный мониторинг"
    >
        <template #actions>
            <Link
                href="/sites"
                class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
            >
                Назад к сайтам
            </Link>
        </template>

        <form class="mx-auto max-w-7xl px-5 py-8 sm:px-8" @submit.prevent="submit">
            <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <aside class="space-y-4">
                    <section class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <h2 class="text-xl font-extrabold text-[#111827]">Сайт</h2>
                        <p class="mt-1 text-sm leading-6 text-[#667085]">URL обязателен. Название можно оставить пустым, тогда будет использован домен.</p>

                        <div class="mt-5 grid gap-5">
                            <div>
                                <label for="url" class="mb-2 block text-sm font-extrabold text-[#111827]">URL сайта</label>
                                <input
                                    id="url"
                                    v-model="form.url"
                                    type="text"
                                    required
                                    placeholder="https://example.com"
                                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                >
                                <p v-if="form.errors.url" class="mt-2 text-xs font-semibold text-[#EF4444]">{{ form.errors.url }}</p>
                            </div>

                            <div>
                                <label for="name" class="mb-2 block text-sm font-extrabold text-[#111827]">Название</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Основной сайт"
                                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                >
                                <p v-if="form.errors.name" class="mt-2 text-xs font-semibold text-[#EF4444]">{{ form.errors.name }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] p-4">
                            <p class="text-xs font-extrabold uppercase text-[#667085]">Будет создано</p>
                            <p class="mt-2 truncate text-sm font-extrabold text-[#111827]">{{ normalizedSite?.host ?? 'Домен появится после ввода URL' }}</p>
                            <p class="mt-1 truncate text-xs font-semibold text-[#667085]">{{ normalizedSite?.url ?? 'Montry автоматически добавит HTTPS, если схема не указана' }}</p>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <h2 class="text-lg font-extrabold text-[#111827]">Мониторинги</h2>
                        <div class="mt-4 grid gap-3">
                            <article
                                v-for="card in monitorCards"
                                :key="card.type"
                                class="rounded-2xl border p-4 transition"
                                :class="card.enabled ? 'border-[#0F6BFF] bg-[#F8FBFF]' : 'border-[#E5E7EB] bg-white'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="typeClass(card.type)">{{ card.label }}</span>
                                        <h3 class="mt-3 font-extrabold text-[#111827]">{{ card.title }}</h3>
                                        <p class="mt-1 text-sm leading-6 text-[#667085]">{{ card.description }}</p>
                                    </div>
                                    <span
                                        class="flex h-6 w-11 shrink-0 items-center rounded-full p-1 transition"
                                        :class="card.enabled ? 'justify-end bg-[#0F6BFF]' : 'justify-start bg-[#CBD5E1]'"
                                    >
                                        <span class="h-4 w-4 rounded-full bg-white shadow-sm" />
                                    </span>
                                </div>
                                <p class="mt-3 truncate text-xs font-bold text-[#667085]">{{ card.summary }}</p>
                            </article>
                        </div>
                    </section>
                </aside>

                <section class="rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] p-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Настройки мониторингов</h2>
                        <p class="mt-1 text-sm text-[#667085]">Все три мониторинга будут созданы сразу. Выключенные останутся на паузе до редактирования сайта.</p>
                    </div>

                    <div class="grid gap-5 p-5">
                        <section
                            class="rounded-3xl border p-5 transition"
                            :class="form.monitors.http.is_enabled ? 'border-[#0F6BFF] bg-[#F8FBFF] shadow-[0_10px_28px_rgba(15,107,255,0.08)]' : 'border-[#E5E7EB] bg-[#F8FAFC]'"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-extrabold text-[#111827]">HTTP availability</h3>
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-extrabold"
                                            :class="form.monitors.http.is_enabled ? 'bg-[#EAF2FF] text-[#0F6BFF]' : 'bg-[#F1F5F9] text-[#64748B]'"
                                        >
                                            {{ form.monitors.http.is_enabled ? 'Активен' : 'На паузе' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-[#667085]">Основная проверка доступности сайта.</p>
                                </div>
                                <button
                                    type="button"
                                    class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3 text-left transition hover:border-[#0F6BFF]"
                                    :aria-pressed="form.monitors.http.is_enabled"
                                    @click="form.monitors.http.is_enabled = !form.monitors.http.is_enabled"
                                >
                                    <span
                                        class="flex h-6 w-11 shrink-0 items-center rounded-full p-1 transition"
                                        :class="form.monitors.http.is_enabled ? 'justify-end bg-[#0F6BFF]' : 'justify-start bg-[#CBD5E1]'"
                                    >
                                        <span class="h-4 w-4 rounded-full bg-white shadow-sm" />
                                    </span>
                                    <span class="text-sm font-extrabold text-[#111827]">{{ form.monitors.http.is_enabled ? 'Включен' : 'Выключен' }}</span>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="http-name" class="mb-2 block text-sm font-extrabold text-[#111827]">Название</label>
                                    <input id="http-name" v-model="form.monitors.http.name" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <label for="http-interval" class="block text-sm font-extrabold text-[#111827]">Частота проверки</label>
                                        <span class="text-xs font-extrabold text-[#0F6BFF]">{{ intervalText(form.monitors.http.interval_seconds) }}</span>
                                    </div>
                                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="minutes in intervalPresets"
                                                :key="`http-${minutes}`"
                                                type="button"
                                                class="h-8 rounded-full px-3 text-xs font-extrabold transition"
                                                :class="intervalMinutes(form.monitors.http.interval_seconds) === minutes ? 'bg-[#0F6BFF] text-white' : 'bg-[#F8FAFC] text-[#667085] hover:bg-[#EAF2FF] hover:text-[#0F6BFF]'"
                                                @click="setIntervalMinutes(form.monitors.http, minutes)"
                                            >
                                                {{ minutes === 60 ? '1 час' : minutes === 1440 ? '1 день' : minutes < 60 ? `${minutes} мин` : `${minutes / 60} ч` }}
                                            </button>
                                        </div>
                                        <input
                                            id="http-interval"
                                            :value="intervalMinutes(form.monitors.http.interval_seconds)"
                                            type="range"
                                            min="5"
                                            max="1440"
                                            step="1"
                                            class="mt-4 w-full accent-[#0F6BFF]"
                                            @input="setIntervalMinutes(form.monitors.http, Number(($event.target as HTMLInputElement).value))"
                                        >
                                    </div>
                                </div>
                                <div>
                                    <label for="http-method" class="mb-2 block text-sm font-extrabold text-[#111827]">Метод</label>
                                    <select id="http-method" v-model="form.monitors.http.method" class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                        <option value="GET">GET</option>
                                        <option value="HEAD">HEAD</option>
                                        <option value="POST">POST</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="http-timeout" class="mb-2 block text-sm font-extrabold text-[#111827]">Таймаут, мс</label>
                                    <input id="http-timeout" v-model.number="form.monitors.http.timeout_ms" type="number" min="1000" max="60000" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                                <div>
                                    <label for="status-codes" class="mb-2 block text-sm font-extrabold text-[#111827]">Ожидаемые коды</label>
                                    <input id="status-codes" v-model="statusCodesText" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                                <div>
                                    <label for="max-response" class="mb-2 block text-sm font-extrabold text-[#111827]">Макс. время ответа, мс</label>
                                    <input id="max-response" v-model.number="form.monitors.http.max_response_time_ms" type="number" min="1" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.monitors.http.follow_redirects" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">Следовать редиректам</span>
                                </label>
                                <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.monitors.http.verify_ssl" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">Проверять SSL в HTTP</span>
                                </label>
                            </div>
                        </section>

                        <section
                            class="rounded-3xl border p-5 transition"
                            :class="form.monitors.ssl.is_enabled ? 'border-[#16A34A] bg-[#F6FEF9] shadow-[0_10px_28px_rgba(22,163,74,0.08)]' : 'border-[#E5E7EB] bg-[#F8FAFC]'"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-extrabold text-[#111827]">SSL certificate</h3>
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-extrabold"
                                            :class="form.monitors.ssl.is_enabled ? 'bg-[#ECFDF3] text-[#16A34A]' : 'bg-[#F1F5F9] text-[#64748B]'"
                                        >
                                            {{ form.monitors.ssl.is_enabled ? 'Активен' : 'На паузе' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-[#667085]">Настройки берут домен из URL сайта.</p>
                                </div>
                                <button
                                    type="button"
                                    class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3 text-left transition hover:border-[#0F6BFF]"
                                    :aria-pressed="form.monitors.ssl.is_enabled"
                                    @click="form.monitors.ssl.is_enabled = !form.monitors.ssl.is_enabled"
                                >
                                    <span
                                        class="flex h-6 w-11 shrink-0 items-center rounded-full p-1 transition"
                                        :class="form.monitors.ssl.is_enabled ? 'justify-end bg-[#0F6BFF]' : 'justify-start bg-[#CBD5E1]'"
                                    >
                                        <span class="h-4 w-4 rounded-full bg-white shadow-sm" />
                                    </span>
                                    <span class="text-sm font-extrabold text-[#111827]">{{ form.monitors.ssl.is_enabled ? 'Включен' : 'Выключен' }}</span>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="ssl-name" class="mb-2 block text-sm font-extrabold text-[#111827]">Название</label>
                                    <input id="ssl-name" v-model="form.monitors.ssl.name" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                                <div>
                                    <label for="ssl-port" class="mb-2 block text-sm font-extrabold text-[#111827]">Порт</label>
                                    <input id="ssl-port" v-model.number="form.monitors.ssl.port" type="number" min="1" max="65535" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <label for="ssl-interval" class="block text-sm font-extrabold text-[#111827]">Частота проверки</label>
                                        <span class="text-xs font-extrabold text-[#0F6BFF]">{{ intervalText(form.monitors.ssl.interval_seconds) }}</span>
                                    </div>
                                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="minutes in intervalPresets"
                                                :key="`ssl-${minutes}`"
                                                type="button"
                                                class="h-8 rounded-full px-3 text-xs font-extrabold transition"
                                                :class="intervalMinutes(form.monitors.ssl.interval_seconds) === minutes ? 'bg-[#0F6BFF] text-white' : 'bg-[#F8FAFC] text-[#667085] hover:bg-[#EAF2FF] hover:text-[#0F6BFF]'"
                                                @click="setIntervalMinutes(form.monitors.ssl, minutes)"
                                            >
                                                {{ minutes === 60 ? '1 час' : minutes === 1440 ? '1 день' : minutes < 60 ? `${minutes} мин` : `${minutes / 60} ч` }}
                                            </button>
                                        </div>
                                        <input
                                            id="ssl-interval"
                                            :value="intervalMinutes(form.monitors.ssl.interval_seconds)"
                                            type="range"
                                            min="5"
                                            max="1440"
                                            step="1"
                                            class="mt-4 w-full accent-[#0F6BFF]"
                                            @input="setIntervalMinutes(form.monitors.ssl, Number(($event.target as HTMLInputElement).value))"
                                        >
                                    </div>
                                </div>
                                <div>
                                    <label for="ssl-warning-days" class="mb-2 block text-sm font-extrabold text-[#111827]">Дни предупреждений</label>
                                    <input id="ssl-warning-days" v-model="sslWarningDaysText" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                            </div>
                        </section>

                        <section
                            class="rounded-3xl border p-5 transition"
                            :class="form.monitors.domain.is_enabled ? 'border-[#F59E0B] bg-[#FFFCF4] shadow-[0_10px_28px_rgba(245,158,11,0.08)]' : 'border-[#E5E7EB] bg-[#F8FAFC]'"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-extrabold text-[#111827]">Domain expiration</h3>
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-extrabold"
                                            :class="form.monitors.domain.is_enabled ? 'bg-[#FFF7E8] text-[#F59E0B]' : 'bg-[#F1F5F9] text-[#64748B]'"
                                        >
                                            {{ form.monitors.domain.is_enabled ? 'Активен' : 'На паузе' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-[#667085]">Проверка регистрации домена и предупреждения до истечения.</p>
                                </div>
                                <button
                                    type="button"
                                    class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3 text-left transition hover:border-[#0F6BFF]"
                                    :aria-pressed="form.monitors.domain.is_enabled"
                                    @click="form.monitors.domain.is_enabled = !form.monitors.domain.is_enabled"
                                >
                                    <span
                                        class="flex h-6 w-11 shrink-0 items-center rounded-full p-1 transition"
                                        :class="form.monitors.domain.is_enabled ? 'justify-end bg-[#0F6BFF]' : 'justify-start bg-[#CBD5E1]'"
                                    >
                                        <span class="h-4 w-4 rounded-full bg-white shadow-sm" />
                                    </span>
                                    <span class="text-sm font-extrabold text-[#111827]">{{ form.monitors.domain.is_enabled ? 'Включен' : 'Выключен' }}</span>
                                </button>
                            </div>

                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="domain-name" class="mb-2 block text-sm font-extrabold text-[#111827]">Название</label>
                                    <input id="domain-name" v-model="form.monitors.domain.name" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <label for="domain-interval" class="block text-sm font-extrabold text-[#111827]">Частота проверки</label>
                                        <span class="text-xs font-extrabold text-[#0F6BFF]">{{ intervalText(form.monitors.domain.interval_seconds) }}</span>
                                    </div>
                                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="minutes in intervalPresets"
                                                :key="`domain-${minutes}`"
                                                type="button"
                                                class="h-8 rounded-full px-3 text-xs font-extrabold transition"
                                                :class="intervalMinutes(form.monitors.domain.interval_seconds) === minutes ? 'bg-[#0F6BFF] text-white' : 'bg-[#F8FAFC] text-[#667085] hover:bg-[#EAF2FF] hover:text-[#0F6BFF]'"
                                                @click="setIntervalMinutes(form.monitors.domain, minutes)"
                                            >
                                                {{ minutes === 60 ? '1 час' : minutes === 1440 ? '1 день' : minutes < 60 ? `${minutes} мин` : `${minutes / 60} ч` }}
                                            </button>
                                        </div>
                                        <input
                                            id="domain-interval"
                                            :value="intervalMinutes(form.monitors.domain.interval_seconds)"
                                            type="range"
                                            min="5"
                                            max="1440"
                                            step="1"
                                            class="mt-4 w-full accent-[#0F6BFF]"
                                            @input="setIntervalMinutes(form.monitors.domain, Number(($event.target as HTMLInputElement).value))"
                                        >
                                    </div>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="domain-warning-days" class="mb-2 block text-sm font-extrabold text-[#111827]">Дни предупреждений</label>
                                    <input id="domain-warning-days" v-model="domainWarningDaysText" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                </div>
                            </div>
                        </section>

                        <div v-if="form.errors.monitors" class="rounded-2xl border border-[#FECACA] bg-[#FEECEC] px-4 py-3 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.monitors }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#E5E7EB] p-5 sm:flex-row sm:justify-end">
                        <Link
                            href="/sites"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                        >
                            Отмена
                        </Link>
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Создаем...</span>
                            <span v-else>Создать сайт</span>
                        </button>
                    </div>
                </section>
            </div>
        </form>
    </DashboardLayout>
</template>
