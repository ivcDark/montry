<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

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
    code?: string
    label: string
    name?: string
    short_label?: string
    description?: string
    default_interval_seconds?: number | null
    default_timeout_ms?: number | null
}

const props = defineProps<{
    organization: Organization
    site: Site
    monitorTypes: MonitorTypeOption[]
}>()

const statusCodesText = ref('200')
const warningDaysText = ref('30, 14, 7, 3, 1')
const dnsRecordTypesText = ref('A, AAAA')
const dnsNameserversText = ref('')
const headersText = ref('')
const httpIntervalPresets = [5, 10, 15, 30, 60, 360, 720, 1440]
const dayIntervalPresets = [1, 2, 3, 4, 5, 6, 7]

const rootUrl = computed(() => siteRootUrl(props.site.url))
const initialType = props.monitorTypes[0]?.code ?? props.monitorTypes[0]?.value ?? 'http'

const form = useForm({
    type: initialType,
    name: 'HTTP check',
    is_enabled: true,
    interval_seconds: 300,
    timeout_ms: 10000,
    settings: {
        method: 'GET',
        url: props.site.url,
        follow_redirects: true,
        verify_ssl: true,
        domain: props.site.host,
        host: props.site.host,
        port: props.site.port ?? 443,
        warning_days: [30, 14, 7, 3, 1],
        record_types: ['A', 'AAAA'],
        nameservers: [],
        headers: {},
        body: '',
    },
    expected: {
        status_codes: [200],
        max_response_time_ms: 5000,
        valid: true,
        registered: true,
        resolves: true,
        min_records: 1,
        exists: true,
        valid_xml: true,
        open: true,
        response_contains: '',
    },
})

const selectedType = computed(() => props.monitorTypes.find((type) => (type.code ?? type.value) === form.type))
const isHttpMonitor = computed(() => form.type === 'http')
const intervalPresets = computed(() => isHttpMonitor.value ? httpIntervalPresets : dayIntervalPresets)

const typeHints: Record<string, { title: string, description: string, result: string }> = {
    http: {
        title: 'HTTP/HTTPS',
        description: 'Проверяет доступность сайта, код ответа и время загрузки.',
        result: 'Ожидаемый результат: 200 OK до 5000 мс',
    },
    ssl: {
        title: 'SSL',
        description: 'Следит за валидностью сертификата и предупреждает до истечения.',
        result: 'Предупреждения: 30, 14, 7, 3, 1 дней',
    },
    domain: {
        title: 'Домен',
        description: 'Проверяет срок регистрации домена и заранее подсвечивает риск.',
        result: 'Предупреждения: 30, 14, 7, 3, 1 дней',
    },
    dns: {
        title: 'DNS',
        description: 'Проверяет, что DNS-записи домена резолвятся.',
        result: 'A/AAAA записи, минимум 1 ответ',
    },
    robots_txt: {
        title: 'Robots.txt',
        description: 'Проверяет наличие и доступность файла robots.txt.',
        result: 'Ожидаемый результат: 200 OK',
    },
    sitemap_xml: {
        title: 'Sitemap.xml',
        description: 'Проверяет наличие sitemap.xml и валидность XML.',
        result: 'Ожидаемый результат: 200 OK и валидный XML',
    },
    api_endpoint: {
        title: 'API endpoint',
        description: 'Проверяет отдельный API-адрес, метод, заголовки и код ответа.',
        result: 'Ожидаемый результат: 200 OK до 5000 мс',
    },
    tcp_port: {
        title: 'TCP-порт',
        description: 'Проверяет, что нужный TCP-порт открыт.',
        result: 'Ожидаемый результат: порт открыт',
    },
}

const activeHint = computed(() => {
    const hint = typeHints[form.type]

    if (hint) {
        return {
            ...hint,
            title: selectedType.value?.name ?? selectedType.value?.label ?? hint.title,
            description: selectedType.value?.description ?? hint.description,
        }
    }

    return {
        title: selectedType.value?.name ?? selectedType.value?.label ?? 'Мониторинг',
        description: selectedType.value?.description ?? 'Настройте параметры проверки для выбранного типа.',
        result: 'Проверка будет создана после сохранения.',
    }
})

function selectType(type: string): void {
    form.type = type
    form.timeout_ms = 10000

    if (type === 'http') {
        form.name = 'HTTP check'
        form.interval_seconds = 300
        form.settings.method = 'GET'
        form.settings.url = props.site.url
        form.settings.follow_redirects = true
        form.settings.verify_ssl = true
        statusCodesText.value = '200'
        form.expected.max_response_time_ms = 5000
        return
    }

    if (type === 'ssl') {
        form.name = 'SSL certificate check'
        form.interval_seconds = 86400
        form.settings.domain = props.site.host
        form.settings.port = props.site.port ?? 443
        warningDaysText.value = '30, 14, 7, 3, 1'
        form.expected.valid = true
        return
    }

    if (type === 'domain') {
        form.name = 'Domain expiration check'
        form.interval_seconds = 86400
        form.settings.domain = props.site.host
        warningDaysText.value = '30, 14, 7, 3, 1'
        form.expected.registered = true
        return
    }

    if (type === 'dns') {
        form.name = 'DNS records check'
        form.interval_seconds = 86400
        form.settings.domain = props.site.host
        dnsRecordTypesText.value = 'A, AAAA'
        dnsNameserversText.value = ''
        form.expected.resolves = true
        form.expected.min_records = 1
        return
    }

    if (type === 'robots_txt') {
        form.name = 'Robots.txt check'
        form.interval_seconds = 86400
        form.settings.url = `${rootUrl.value}/robots.txt`
        form.settings.follow_redirects = true
        form.settings.verify_ssl = true
        statusCodesText.value = '200'
        form.expected.exists = true
        form.expected.max_response_time_ms = 5000
        return
    }

    if (type === 'sitemap_xml') {
        form.name = 'Sitemap.xml check'
        form.interval_seconds = 86400
        form.settings.url = `${rootUrl.value}/sitemap.xml`
        form.settings.follow_redirects = true
        form.settings.verify_ssl = true
        statusCodesText.value = '200'
        form.expected.exists = true
        form.expected.valid_xml = true
        form.expected.max_response_time_ms = 5000
        return
    }

    if (type === 'api_endpoint') {
        form.name = 'API endpoint check'
        form.interval_seconds = 86400
        form.settings.method = 'GET'
        form.settings.url = props.site.url
        form.settings.headers = {}
        form.settings.body = ''
        form.settings.follow_redirects = true
        form.settings.verify_ssl = true
        statusCodesText.value = '200'
        headersText.value = ''
        form.expected.max_response_time_ms = 5000
        form.expected.response_contains = ''
        return
    }

    if (type === 'tcp_port') {
        form.name = 'TCP port check'
        form.interval_seconds = 86400
        form.settings.host = props.site.host
        form.settings.port = props.site.port ?? 443
        form.expected.open = true
        form.expected.max_response_time_ms = 5000
    }
}

function parseNumberList(value: string): number[] {
    return value
        .split(',')
        .map((item) => Number.parseInt(item.trim(), 10))
        .filter((item) => Number.isInteger(item))
}

function parseStringList(value: string): string[] {
    return value
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
}

function parseHeaders(value: string): Record<string, string> {
    return Object.fromEntries(
        value
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean)
            .map((line) => {
                const separatorIndex = line.indexOf(':')

                if (separatorIndex === -1) return [line, '']

                return [line.slice(0, separatorIndex).trim(), line.slice(separatorIndex + 1).trim()]
            })
            .filter(([key]) => Boolean(key)),
    )
}

function intervalMinutes(seconds: number): number {
    return Math.round(seconds / 60)
}

function intervalDays(seconds: number): number {
    return Math.round(seconds / 86400)
}

function intervalValue(seconds: number): number {
    return isHttpMonitor.value ? intervalMinutes(seconds) : intervalDays(seconds)
}

function setIntervalValue(value: number): void {
    form.interval_seconds = value * (isHttpMonitor.value ? 60 : 86400)
}

function intervalPresetLabel(value: number): string {
    if (!isHttpMonitor.value) return `${value} дн.`

    return value === 60 ? '1 час' : value === 1440 ? '1 день' : value < 60 ? `${value} мин` : `${value / 60} ч`
}

function intervalText(seconds: number): string {
    if (!isHttpMonitor.value) {
        const days = intervalDays(seconds)

        return days === 1 ? 'Раз в день' : `Раз в ${days} дн.`
    }

    const minutes = intervalMinutes(seconds)

    if (minutes === 60) return 'Каждый час'
    if (minutes === 1440) return 'Раз в день'
    if (minutes > 60 && minutes % 60 === 0) return `Каждые ${minutes / 60} ч`

    return `Каждые ${minutes} мин`
}

function siteRootUrl(url: string): string {
    try {
        const parsed = new URL(url)

        return `${parsed.protocol}//${parsed.host}`
    } catch {
        return `https://${props.site.host}`
    }
}

function basePayload() {
    return {
        type: form.type,
        name: form.name,
        is_enabled: form.is_enabled,
        interval_seconds: form.interval_seconds,
        timeout_ms: form.timeout_ms,
    }
}

function httpExpected() {
    return {
        status_codes: parseNumberList(statusCodesText.value),
        max_response_time_ms: form.expected.max_response_time_ms,
    }
}

function requestPayload() {
    if (form.type === 'http') {
        return {
            ...basePayload(),
            settings: {
                method: form.settings.method,
                url: form.settings.url,
                follow_redirects: form.settings.follow_redirects,
                verify_ssl: form.settings.verify_ssl,
            },
            expected: httpExpected(),
        }
    }

    if (form.type === 'api_endpoint') {
        return {
            ...basePayload(),
            settings: {
                method: form.settings.method,
                url: form.settings.url,
                headers: parseHeaders(headersText.value),
                body: form.settings.body || null,
                follow_redirects: form.settings.follow_redirects,
                verify_ssl: form.settings.verify_ssl,
            },
            expected: {
                ...httpExpected(),
                response_contains: form.expected.response_contains || null,
            },
        }
    }

    if (form.type === 'ssl') {
        return {
            ...basePayload(),
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

    if (form.type === 'domain') {
        return {
            ...basePayload(),
            settings: {
                domain: form.settings.domain,
                warning_days: parseNumberList(warningDaysText.value),
            },
            expected: {
                registered: form.expected.registered,
            },
        }
    }

    if (form.type === 'dns') {
        return {
            ...basePayload(),
            settings: {
                domain: form.settings.domain,
                record_types: parseStringList(dnsRecordTypesText.value),
                nameservers: parseStringList(dnsNameserversText.value),
            },
            expected: {
                resolves: form.expected.resolves,
                min_records: form.expected.min_records,
            },
        }
    }

    if (form.type === 'robots_txt') {
        return {
            ...basePayload(),
            settings: {
                url: form.settings.url,
                follow_redirects: form.settings.follow_redirects,
                verify_ssl: form.settings.verify_ssl,
            },
            expected: {
                exists: form.expected.exists,
                ...httpExpected(),
            },
        }
    }

    if (form.type === 'sitemap_xml') {
        return {
            ...basePayload(),
            settings: {
                url: form.settings.url,
                follow_redirects: form.settings.follow_redirects,
                verify_ssl: form.settings.verify_ssl,
            },
            expected: {
                exists: form.expected.exists,
                valid_xml: form.expected.valid_xml,
                ...httpExpected(),
            },
        }
    }

    return {
        ...basePayload(),
        settings: {
            host: form.settings.host,
            port: form.settings.port,
        },
        expected: {
            open: form.expected.open,
            max_response_time_ms: form.expected.max_response_time_ms,
        },
    }
}

function submit(): void {
    form
        .transform(() => requestPayload())
        .post(`/sites/${props.site.id}/monitors`)
}
</script>

<template>
    <Head title="Создать мониторинг" />

    <DashboardLayout
        :organization="organization"
        active-item="monitors"
        title="Создать мониторинг"
        :subtitle="`${site.name} · ${site.url}`"
    >
        <template #actions>
            <Link
                :href="`/sites/${site.id}`"
                class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
            >
                Назад к сайту
            </Link>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                <aside class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <h2 class="text-xl font-extrabold text-[#111827]">Тип проверки</h2>
                    <p class="mt-1 text-sm leading-6 text-[#667085]">Выберите, что Montry будет проверять для этого сайта.</p>

                    <div class="mt-5 grid gap-3">
                        <button
                            v-for="type in monitorTypes"
                            :key="type.code ?? type.value"
                            type="button"
                            class="rounded-2xl border p-4 text-left transition"
                            :class="form.type === (type.code ?? type.value)
                                ? 'border-[#0F6BFF] bg-[#EAF2FF] text-[#0F6BFF]'
                                : 'border-[#E5E7EB] bg-white text-[#111827] hover:border-[#0F6BFF]'"
                            @click="selectType(type.code ?? type.value)"
                        >
                            <span class="block text-sm font-extrabold">{{ type.short_label ?? type.name ?? type.label }}</span>
                            <span class="mt-1 block text-xs font-semibold text-[#667085]">
                                {{ type.description ?? typeHints[type.code ?? type.value]?.description ?? 'Пользовательский тип мониторинга' }}
                            </span>
                        </button>
                    </div>

                    <p v-if="form.errors.type" class="mt-3 text-sm font-semibold text-[#EF4444]">
                        {{ form.errors.type }}
                    </p>

                    <div class="mt-6 rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] p-4">
                        <p class="text-sm font-extrabold text-[#111827]">{{ activeHint.title }}</p>
                        <p class="mt-1 text-sm leading-6 text-[#667085]">{{ activeHint.description }}</p>
                        <span class="mt-4 inline-flex rounded-full bg-[#ECFDF3] px-3 py-1 text-xs font-extrabold text-[#16A34A]">
                            {{ activeHint.result }}
                        </span>
                    </div>
                </aside>

                <form
                    class="rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]"
                    @submit.prevent="submit"
                >
                    <div class="border-b border-[#E5E7EB] p-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Настройки проверки</h2>
                        <p class="mt-1 text-sm text-[#667085]">Поля уже заполнены рекомендуемыми значениями для MVP.</p>
                    </div>

                    <div class="grid gap-6 p-5">
                        <section class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="monitor-name" class="mb-2 block text-sm font-extrabold text-[#111827]">Название</label>
                                <input
                                    id="monitor-name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                >
                                <p v-if="form.errors.name" class="mt-2 text-xs font-semibold text-[#EF4444]">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label for="interval" class="block text-sm font-extrabold text-[#111827]">Частота проверки</label>
                                    <span class="text-xs font-extrabold text-[#0F6BFF]">{{ intervalText(form.interval_seconds) }}</span>
                                </div>
                                <div class="rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] p-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="interval in intervalPresets"
                                            :key="interval"
                                            type="button"
                                            class="h-8 rounded-full px-3 text-xs font-extrabold transition"
                                            :class="intervalValue(form.interval_seconds) === interval ? 'bg-[#0F6BFF] text-white' : 'bg-white text-[#667085] hover:bg-[#EAF2FF] hover:text-[#0F6BFF]'"
                                            @click="setIntervalValue(interval)"
                                        >
                                            {{ intervalPresetLabel(interval) }}
                                        </button>
                                    </div>
                                    <input
                                        id="interval"
                                        :value="intervalValue(form.interval_seconds)"
                                        type="range"
                                        :min="isHttpMonitor ? 5 : 1"
                                        :max="isHttpMonitor ? 1440 : 7"
                                        step="1"
                                        class="mt-4 w-full accent-[#0F6BFF]"
                                        @input="setIntervalValue(Number(($event.target as HTMLInputElement).value))"
                                    >
                                </div>
                                <p v-if="form.errors.interval_seconds" class="mt-2 text-xs font-semibold text-[#EF4444]">{{ form.errors.interval_seconds }}</p>
                            </div>

                            <div>
                                <label for="timeout" class="mb-2 block text-sm font-extrabold text-[#111827]">Таймаут, мс</label>
                                <input
                                    id="timeout"
                                    v-model.number="form.timeout_ms"
                                    type="number"
                                    min="1000"
                                    max="60000"
                                    required
                                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                >
                                <p v-if="form.errors.timeout_ms" class="mt-2 text-xs font-semibold text-[#EF4444]">{{ form.errors.timeout_ms }}</p>
                            </div>

                            <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] px-4 py-3">
                                <span
                                    class="flex h-6 w-11 items-center rounded-full p-1 transition"
                                    :class="form.is_enabled ? 'justify-end bg-[#0F6BFF]' : 'justify-start bg-[#CBD5E1]'"
                                >
                                    <span class="h-4 w-4 rounded-full bg-white shadow-sm" />
                                </span>
                                <input v-model="form.is_enabled" type="checkbox" class="sr-only">
                                <span>
                                    <span class="block text-sm font-extrabold text-[#111827]">Мониторинг включён</span>
                                    <span class="text-xs font-semibold text-[#667085]">Начать проверки после создания</span>
                                </span>
                            </label>
                        </section>

                        <section v-if="['http', 'api_endpoint', 'robots_txt', 'sitemap_xml'].includes(form.type)" class="rounded-3xl border border-[#E5E7EB] bg-[#F8FAFC] p-5">
                            <div class="grid gap-5 md:grid-cols-[150px_minmax(0,1fr)]">
                                <div v-if="form.type === 'http' || form.type === 'api_endpoint'">
                                    <label for="method" class="mb-2 block text-sm font-extrabold text-[#111827]">Метод</label>
                                    <select
                                        id="method"
                                        v-model="form.settings.method"
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                        <option value="GET">GET</option>
                                        <option value="HEAD">HEAD</option>
                                        <option value="POST">POST</option>
                                        <option v-if="form.type === 'api_endpoint'" value="PUT">PUT</option>
                                        <option v-if="form.type === 'api_endpoint'" value="PATCH">PATCH</option>
                                        <option v-if="form.type === 'api_endpoint'" value="DELETE">DELETE</option>
                                        <option v-if="form.type === 'api_endpoint'" value="OPTIONS">OPTIONS</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="url" class="mb-2 block text-sm font-extrabold text-[#111827]">URL</label>
                                    <input
                                        id="url"
                                        v-model="form.settings.url"
                                        type="url"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>
                            </div>

                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="status-codes" class="mb-2 block text-sm font-extrabold text-[#111827]">Ожидаемые коды</label>
                                    <input
                                        id="status-codes"
                                        v-model="statusCodesText"
                                        type="text"
                                        placeholder="200, 204"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div>
                                    <label for="response-time" class="mb-2 block text-sm font-extrabold text-[#111827]">Макс. время ответа, мс</label>
                                    <input
                                        id="response-time"
                                        v-model.number="form.expected.max_response_time_ms"
                                        type="number"
                                        min="1"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.settings.follow_redirects" type="checkbox" class="h-4 w-4 rounded border-[#E5E7EB] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">Следовать редиректам</span>
                                </label>

                                <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.settings.verify_ssl" type="checkbox" class="h-4 w-4 rounded border-[#E5E7EB] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">Проверять SSL при HTTP</span>
                                </label>
                            </div>


                            <div v-if="form.type === 'api_endpoint'" class="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="headers" class="mb-2 block text-sm font-extrabold text-[#111827]">Заголовки</label>
                                    <textarea
                                        id="headers"
                                        v-model="headersText"
                                        rows="4"
                                        placeholder="Authorization: Bearer token"
                                        class="w-full rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    ></textarea>
                                    <p class="mt-1 text-xs font-semibold text-[#667085]">Один заголовок на строку в формате Header: value.</p>
                                </div>

                                <div>
                                    <label for="body" class="mb-2 block text-sm font-extrabold text-[#111827]">Body</label>
                                    <textarea
                                        id="body"
                                        v-model="form.settings.body"
                                        rows="4"
                                        placeholder="{&quot;ping&quot;: true}"
                                        class="w-full rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    ></textarea>
                                </div>

                                <div class="md:col-span-2">
                                    <label for="response-contains" class="mb-2 block text-sm font-extrabold text-[#111827]">Ответ должен содержать</label>
                                    <input
                                        id="response-contains"
                                        v-model="form.expected.response_contains"
                                        type="text"
                                        placeholder="необязательно"
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>
                            </div>

                            <div v-if="form.type === 'robots_txt' || form.type === 'sitemap_xml'" class="mt-5 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.expected.exists" type="checkbox" class="h-4 w-4 rounded border-[#E5E7EB] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">Файл должен существовать</span>
                                </label>

                                <label v-if="form.type === 'sitemap_xml'" class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.expected.valid_xml" type="checkbox" class="h-4 w-4 rounded border-[#E5E7EB] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">XML должен быть валидным</span>
                                </label>
                            </div>
                        </section>

                        <section v-if="form.type === 'ssl' || form.type === 'domain'" class="rounded-3xl border border-[#E5E7EB] bg-[#F8FAFC] p-5">
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="domain" class="mb-2 block text-sm font-extrabold text-[#111827]">Домен</label>
                                    <input
                                        id="domain"
                                        v-model="form.settings.domain"
                                        type="text"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div v-if="form.type === 'ssl'">
                                    <label for="port" class="mb-2 block text-sm font-extrabold text-[#111827]">Порт</label>
                                    <input
                                        id="port"
                                        v-model.number="form.settings.port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>
                            </div>

                            <div class="mt-5">
                                <label for="warning-days" class="mb-2 block text-sm font-extrabold text-[#111827]">Дни предупреждений</label>
                                <input
                                    id="warning-days"
                                    v-model="warningDaysText"
                                    type="text"
                                    placeholder="30, 14, 7, 3, 1"
                                    required
                                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                >
                            </div>
                        </section>


                        <section v-if="form.type === 'dns'" class="rounded-3xl border border-[#E5E7EB] bg-[#F8FAFC] p-5">
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="dns-domain" class="mb-2 block text-sm font-extrabold text-[#111827]">Домен</label>
                                    <input
                                        id="dns-domain"
                                        v-model="form.settings.domain"
                                        type="text"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div>
                                    <label for="record-types" class="mb-2 block text-sm font-extrabold text-[#111827]">Типы записей</label>
                                    <input
                                        id="record-types"
                                        v-model="dnsRecordTypesText"
                                        type="text"
                                        placeholder="A, AAAA"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div>
                                    <label for="nameservers" class="mb-2 block text-sm font-extrabold text-[#111827]">DNS-серверы</label>
                                    <input
                                        id="nameservers"
                                        v-model="dnsNameserversText"
                                        type="text"
                                        placeholder="необязательно"
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div>
                                    <label for="min-records" class="mb-2 block text-sm font-extrabold text-[#111827]">Минимум записей</label>
                                    <input
                                        id="min-records"
                                        v-model.number="form.expected.min_records"
                                        type="number"
                                        min="0"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>
                            </div>
                        </section>

                        <section v-if="form.type === 'tcp_port'" class="rounded-3xl border border-[#E5E7EB] bg-[#F8FAFC] p-5">
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="tcp-host" class="mb-2 block text-sm font-extrabold text-[#111827]">Host</label>
                                    <input
                                        id="tcp-host"
                                        v-model="form.settings.host"
                                        type="text"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div>
                                    <label for="tcp-port" class="mb-2 block text-sm font-extrabold text-[#111827]">Порт</label>
                                    <input
                                        id="tcp-port"
                                        v-model.number="form.settings.port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div>
                                    <label for="tcp-response-time" class="mb-2 block text-sm font-extrabold text-[#111827]">Макс. время подключения, мс</label>
                                    <input
                                        id="tcp-response-time"
                                        v-model.number="form.expected.max_response_time_ms"
                                        type="number"
                                        min="1"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.expected.open" type="checkbox" class="h-4 w-4 rounded border-[#E5E7EB] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">Порт должен быть открыт</span>
                                </label>
                            </div>
                        </section>


                        <div v-if="form.errors.settings || form.errors.expected" class="rounded-2xl border border-[#FECACA] bg-[#FEECEC] px-4 py-3 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.settings || form.errors.expected }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#E5E7EB] p-5 sm:flex-row sm:justify-end">
                        <Link
                            :href="`/sites/${site.id}`"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                        >
                            Отмена
                        </Link>

                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Создаём...</span>
                            <span v-else>Создать мониторинг</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
