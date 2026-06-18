<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Crown, Plus, Trash2 } from '@lucide/vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Organization = {
    id: string | number
    name: string
}

type MonitorType = 'http' | 'ssl' | 'domain' | 'dns' | 'robots_txt' | 'sitemap_xml' | 'api_endpoint' | 'tcp_port'

type MonitorTypeOption = {
    value: string
    label: string
}

type CurrentPlan = {
    code: string
    name: string
    price_cents?: number | null
    limits?: Record<string, any>
}

type Usage = {
    sites: number
    monitors: number
    active_monitors: number
    site_limit: number | null
    monitor_limit: number | null
    minimum_check_interval_seconds: number | null
    allowed_monitor_types: string[] | null
}

type ToggleCard = {
    type: MonitorType
    title: string
    label: string
    description: string
    summary: string
    enabled: boolean
    included: boolean
    badge?: string
}

type TcpPortConfig = {
    port: number | null
    max_response_time_ms: number
}

type ApiEndpointConfig = {
    method: string
    path: string
    status_codes: string
    max_response_time_ms: number
    response_contains: string
}

type MonitorPayload = {
    type: MonitorType
    name: string
    is_enabled: boolean
    interval_seconds: number
    timeout_ms: number
    settings: Record<string, unknown>
    expected: Record<string, unknown>
}

const props = withDefaults(defineProps<{
    organization: Organization
    monitorTypes: MonitorTypeOption[]
    currentPlan?: CurrentPlan | null
    usage?: Usage | null
}>(), {
    currentPlan: null,
    usage: null,
})

const minimumIntervalSecondsValue = props.usage?.minimum_check_interval_seconds ?? 300
const minimumIntervalMinutes = Math.max(1, Math.ceil(minimumIntervalSecondsValue / 60))
const statusCodesText = ref('200')
const sslWarningDaysText = ref('30, 14, 7, 3, 1')
const domainWarningDaysText = ref('30, 14, 7, 3, 1')
const dnsRecordTypesText = ref('A, AAAA')
const robotsStatusCodesText = ref('200')
const sitemapStatusCodesText = ref('200')
const openedAdvanced = ref<MonitorType | null>(null)
const apiEndpoints = ref<ApiEndpointConfig[]>([
    {
        method: 'GET',
        path: '/api/health',
        status_codes: '200',
        max_response_time_ms: 5000,
        response_contains: '',
    },
])
const tcpPorts = ref<TcpPortConfig[]>([
    { port: 443, max_response_time_ms: 5000 },
])

const intervalPresets = [1, 5, 10, 15, 30, 60, 360, 720, 1440]
const availableIntervalPresets = computed(() => intervalPresets.filter((minutes) => minutes >= minimumIntervalMinutes))

const tariffCatalog: Record<string, { name: string; priceRub: number; monitorLimit: number; historyDays: number; intervalText: string }> = {
    free: { name: 'Free', priceRub: 0, monitorLimit: 5, historyDays: 7, intervalText: 'от 5 минут' },
    pro: { name: 'Pro', priceRub: 590, monitorLimit: 100, historyDays: 30, intervalText: 'от 1 минуты' },
    team: { name: 'Team', priceRub: 1490, monitorLimit: 500, historyDays: 90, intervalText: 'от 1 минуты' },
}

const monitorTypeOrder: MonitorType[] = ['http', 'ssl', 'domain', 'dns', 'robots_txt', 'sitemap_xml', 'api_endpoint', 'tcp_port']

function initiallyAllowsType(type: MonitorType): boolean {
    if (type === 'http' || type === 'ssl') return true

    const code = props.currentPlan?.code ?? 'free'

    if (code === 'free') return false
    if (code === 'pro' || code === 'team') return true

    const allowedTypes = props.usage?.allowed_monitor_types

    return allowedTypes?.includes(type) === true || allowedTypes?.includes('*') === true
}

const initialMonitorLimit = props.usage?.monitor_limit
    ?? tariffCatalog[props.currentPlan?.code ?? 'free']?.monitorLimit
    ?? null
const initialAvailableSlots = initialMonitorLimit === null
    ? Number.POSITIVE_INFINITY
    : Math.max(initialMonitorLimit - (props.usage?.active_monitors ?? 0), 0)
const initiallyEnabledTypes = new Set(
    monitorTypeOrder
        .filter((type) => initiallyAllowsType(type))
        .slice(0, initialAvailableSlots),
)

const form = useForm({
    name: '',
    url: '',
    monitors: {
        http: {
            is_enabled: initiallyEnabledTypes.has('http'),
            name: 'HTTP availability',
            interval_seconds: minimumIntervalMinutes * 60,
            timeout_ms: 10000,
            method: 'GET',
            follow_redirects: true,
            verify_ssl: true,
            max_response_time_ms: 5000,
        },
        ssl: {
            is_enabled: initiallyEnabledTypes.has('ssl'),
            name: 'SSL certificate',
            interval_seconds: 86400,
            timeout_ms: 10000,
            port: 443,
            valid: true,
        },
        domain: {
            is_enabled: initiallyEnabledTypes.has('domain'),
            name: 'Domain expiration',
            interval_seconds: 86400,
            timeout_ms: 10000,
            registered: true,
        },
        dns: {
            is_enabled: initiallyEnabledTypes.has('dns'),
            name: 'DNS records',
            interval_seconds: 86400,
            timeout_ms: 10000,
            min_records: 1,
            resolves: true,
            warn_on_change: false,
        },
        robots_txt: {
            is_enabled: initiallyEnabledTypes.has('robots_txt'),
            name: 'Robots.txt',
            interval_seconds: 86400,
            timeout_ms: 10000,
            follow_redirects: true,
            verify_ssl: true,
            exists: true,
            max_response_time_ms: 5000,
        },
        sitemap_xml: {
            is_enabled: initiallyEnabledTypes.has('sitemap_xml'),
            name: 'Sitemap.xml',
            interval_seconds: 86400,
            timeout_ms: 10000,
            follow_redirects: true,
            verify_ssl: true,
            exists: true,
            valid_xml: true,
            max_response_time_ms: 5000,
        },
        api_endpoint: {
            is_enabled: initiallyEnabledTypes.has('api_endpoint'),
            name: 'API endpoint',
            interval_seconds: minimumIntervalMinutes * 60,
            timeout_ms: 10000,
            follow_redirects: true,
            verify_ssl: true,
        },
        tcp_port: {
            is_enabled: initiallyEnabledTypes.has('tcp_port'),
            name: 'TCP port',
            interval_seconds: minimumIntervalMinutes * 60,
            timeout_ms: 10000,
            open: true,
        },
    },
})

const normalizedSite = computed(() => normalizeUrl(form.url))
const httpUrl = computed(() => normalizedSite.value?.url ?? form.url.trim())
const rootUrl = computed(() => normalizedSite.value ? siteRootUrl(normalizedSite.value.url) : '')
const domain = computed(() => normalizedSite.value?.host ?? '')
const fallbackHost = computed(() => domain.value || form.url.trim())
const robotsUrl = computed(() => rootUrl.value ? `${rootUrl.value}/robots.txt` : '')
const sitemapUrl = computed(() => rootUrl.value ? `${rootUrl.value}/sitemap.xml` : '')

const planCode = computed(() => props.currentPlan?.code ?? 'free')
const displayPlan = computed(() => tariffCatalog[planCode.value] ?? {
    name: props.currentPlan?.name ?? 'Free',
    priceRub: props.currentPlan?.price_cents ? Math.round(props.currentPlan.price_cents / 100) : 0,
    monitorLimit: props.usage?.monitor_limit ?? 5,
    historyDays: Number(props.currentPlan?.limits?.history_retention_days?.days ?? 7),
    intervalText: `от ${minimumIntervalMinutes} минут`,
})
const monitorLimit = computed(() => props.usage?.monitor_limit ?? displayPlan.value.monitorLimit)
const allowedMonitorTypes = computed<string[] | null>(() => props.usage?.allowed_monitor_types ?? null)

function planAllowsType(type: MonitorType): boolean {
    if (type === 'http' || type === 'ssl') return true
    if (planCode.value === 'free') return false
    if (planCode.value === 'pro' || planCode.value === 'team') return true
    if (allowedMonitorTypes.value) return allowedMonitorTypes.value.includes(type) || allowedMonitorTypes.value.includes('*')

    return false
}

const baseCheckCards = computed<ToggleCard[]>(() => [
    {
        type: 'http',
        title: 'Доступность сайта',
        label: typeLabel('http'),
        description: 'Код ответа, редиректы и время ответа главной страницы.',
        summary: `${form.monitors.http.method} · ${statusCodesText.value} · ${intervalText(form.monitors.http.interval_seconds)}`,
        enabled: form.monitors.http.is_enabled,
        included: true,
        badge: 'В тарифе',
    },
    {
        type: 'ssl',
        title: 'SSL-сертификат',
        label: typeLabel('ssl'),
        description: 'Валидность сертификата и предупреждения до истечения.',
        summary: `${fallbackHost.value || 'домен из URL'} · порт ${form.monitors.ssl.port}`,
        enabled: form.monitors.ssl.is_enabled,
        included: true,
        badge: 'В тарифе',
    },
])

const paidPlanCards = computed<ToggleCard[]>(() => [
    {
        type: 'domain',
        title: 'Срок домена',
        label: typeLabel('domain'),
        description: 'WHOIS-проверка регистрации и даты окончания домена.',
        summary: `${fallbackHost.value || 'домен из URL'} · ${domainWarningDaysText.value} дней`,
        enabled: form.monitors.domain.is_enabled,
        included: planAllowsType('domain'),
        badge: planAllowsType('domain') ? 'В вашем тарифе' : 'Pro и Team',
    },
    {
        type: 'dns',
        title: 'DNS-записи',
        label: typeLabel('dns'),
        description: 'Проверка резолва домена и базовых DNS-записей.',
        summary: `${dnsRecordTypesText.value} · минимум ${form.monitors.dns.min_records}`,
        enabled: form.monitors.dns.is_enabled,
        included: planAllowsType('dns'),
        badge: planAllowsType('dns') ? 'В вашем тарифе' : 'Pro и Team',
    },
    {
        type: 'robots_txt',
        title: 'Robots.txt',
        label: typeLabel('robots_txt'),
        description: 'Наличие robots.txt и корректный HTTP-ответ.',
        summary: robotsUrl.value || 'URL появится после ввода сайта',
        enabled: form.monitors.robots_txt.is_enabled,
        included: planAllowsType('robots_txt'),
        badge: planAllowsType('robots_txt') ? 'В вашем тарифе' : 'Pro и Team',
    },
    {
        type: 'sitemap_xml',
        title: 'Sitemap.xml',
        label: typeLabel('sitemap_xml'),
        description: 'Проверка наличия и валидности XML-карты сайта.',
        summary: sitemapUrl.value || 'URL появится после ввода сайта',
        enabled: form.monitors.sitemap_xml.is_enabled,
        included: planAllowsType('sitemap_xml'),
        badge: planAllowsType('sitemap_xml') ? 'В вашем тарифе' : 'Pro и Team',
    },
    {
        type: 'api_endpoint',
        title: 'API endpoint',
        label: typeLabel('api_endpoint'),
        description: 'Контроль healthcheck, webhook или любого API URL.',
        summary: apiEndpoints.value.length === 1
            ? `${apiEndpoints.value[0]?.method} · ${apiEndpoints.value[0]?.path || 'endpoint'}`
            : `${apiEndpoints.value.length} API endpoint`,
        enabled: form.monitors.api_endpoint.is_enabled,
        included: planAllowsType('api_endpoint'),
        badge: planAllowsType('api_endpoint') ? 'В вашем тарифе' : 'Pro и Team',
    },
    {
        type: 'tcp_port',
        title: 'TCP-порт',
        label: typeLabel('tcp_port'),
        description: 'Проверка открытого порта: HTTPS, SMTP, SSH или свой сервис.',
        summary: tcpPorts.value.length === 1
            ? `${fallbackHost.value || 'host'}:${tcpPorts.value[0]?.port ?? 'порт'}`
            : `${tcpPorts.value.length} TCP-порта`,
        enabled: form.monitors.tcp_port.is_enabled,
        included: planAllowsType('tcp_port'),
        badge: planAllowsType('tcp_port') ? 'В вашем тарифе' : 'Pro и Team',
    },
])

const enabledBaseCount = computed(() => baseCheckCards.value.filter((card) => card.enabled).length)
const enabledPaidPlanCount = computed(() => paidPlanCards.value.filter((card) => !['api_endpoint', 'tcp_port'].includes(card.type) && card.enabled && card.included).length)
const activeApiEndpointCount = computed(() => form.monitors.api_endpoint.is_enabled && planAllowsType('api_endpoint') ? apiEndpoints.value.length : 0)
const activeTcpPortCount = computed(() => form.monitors.tcp_port.is_enabled && planAllowsType('tcp_port') ? tcpPorts.value.length : 0)
const activeMonitorCount = computed(() => enabledBaseCount.value + enabledPaidPlanCount.value + activeApiEndpointCount.value + activeTcpPortCount.value)
const activeMonitorsAfterCreate = computed(() => (props.usage?.active_monitors ?? 0) + activeMonitorCount.value)
const monitorsLeftAfterCreate = computed(() => Math.max((monitorLimit.value ?? 0) - activeMonitorsAfterCreate.value, 0))
const isMonitorLimitReached = computed(() => monitorLimit.value !== null && activeMonitorsAfterCreate.value > monitorLimit.value)
const canAddApiEndpoint = computed(() => (
    form.monitors.api_endpoint.is_enabled
    && planAllowsType('api_endpoint')
    && (monitorLimit.value === null || activeMonitorsAfterCreate.value < monitorLimit.value)
))
const canAddTcpPort = computed(() => (
    form.monitors.tcp_port.is_enabled
    && planAllowsType('tcp_port')
    && (monitorLimit.value === null || activeMonitorsAfterCreate.value < monitorLimit.value)
))

function typeLabel(type: string): string {
    return props.monitorTypes.find((option) => option.value === type)?.label
        ?? fallbackTypeLabel(type)
}

function fallbackTypeLabel(type: string): string {
    if (type === 'http') return 'HTTP'
    if (type === 'ssl') return 'SSL'
    if (type === 'domain') return 'Domain'
    if (type === 'dns') return 'DNS'
    if (type === 'robots_txt') return 'Robots.txt'
    if (type === 'sitemap_xml') return 'Sitemap.xml'
    if (type === 'api_endpoint') return 'API'
    if (type === 'tcp_port') return 'TCP'

    return type
}

function toggleMonitor(type: MonitorType): void {
    if (!planAllowsType(type)) return
    if (
        !monitorEnabled(type)
        && monitorLimit.value !== null
        && activeMonitorsAfterCreate.value + monitorActivationCost(type) > monitorLimit.value
    ) return

    if (type === 'http') form.monitors.http.is_enabled = !form.monitors.http.is_enabled
    if (type === 'ssl') form.monitors.ssl.is_enabled = !form.monitors.ssl.is_enabled
    if (type === 'domain') form.monitors.domain.is_enabled = !form.monitors.domain.is_enabled
    if (type === 'dns') form.monitors.dns.is_enabled = !form.monitors.dns.is_enabled
    if (type === 'robots_txt') form.monitors.robots_txt.is_enabled = !form.monitors.robots_txt.is_enabled
    if (type === 'sitemap_xml') form.monitors.sitemap_xml.is_enabled = !form.monitors.sitemap_xml.is_enabled
    if (type === 'api_endpoint') form.monitors.api_endpoint.is_enabled = !form.monitors.api_endpoint.is_enabled
    if (type === 'tcp_port') form.monitors.tcp_port.is_enabled = !form.monitors.tcp_port.is_enabled
}

function monitorEnabled(type: MonitorType): boolean {
    return form.monitors[type].is_enabled
}

function monitorActivationCost(type: MonitorType): number {
    if (type === 'api_endpoint') return apiEndpoints.value.length
    if (type === 'tcp_port') return tcpPorts.value.length

    return 1
}

function addApiEndpoint(): void {
    if (!canAddApiEndpoint.value) return

    apiEndpoints.value.push({
        method: 'GET',
        path: '',
        status_codes: '200',
        max_response_time_ms: 5000,
        response_contains: '',
    })
}

function removeApiEndpoint(index: number): void {
    if (apiEndpoints.value.length <= 1) return

    apiEndpoints.value.splice(index, 1)
}

function addTcpPort(): void {
    if (!canAddTcpPort.value) return

    tcpPorts.value.push({
        port: null,
        max_response_time_ms: 5000,
    })
}

function removeTcpPort(index: number): void {
    if (tcpPorts.value.length <= 1) return

    tcpPorts.value.splice(index, 1)
}

function openAdvanced(type: MonitorType): void {
    openedAdvanced.value = openedAdvanced.value === type ? null : type
}

function parseNumberList(value: string): number[] {
    return value
        .split(',')
        .map((item) => Number.parseInt(item.trim(), 10))
        .filter((item) => Number.isInteger(item))
}

function parseTextList(value: string): string[] {
    return value
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item.length > 0)
}

function intervalMinutes(seconds: number): number {
    return Math.round(seconds / 60)
}

function setIntervalMinutes(target: { interval_seconds: number }, minutes: number): void {
    target.interval_seconds = Math.max(minutes, minimumIntervalMinutes) * 60
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

function siteRootUrl(value: string): string {
    try {
        const parsed = new URL(value)
        const port = parsed.port ? `:${parsed.port}` : ''

        return `${parsed.protocol}//${parsed.hostname}${port}`
    } catch {
        return ''
    }
}

function absoluteUrl(pathOrUrl: string, baseUrl: string): string {
    const value = pathOrUrl.trim()

    if (!value) return baseUrl
    if (value.startsWith('http://') || value.startsWith('https://')) return value
    if (!baseUrl) return value

    return `${baseUrl.replace(/\/$/, '')}/${value.replace(/^\//, '')}`
}

function monitorPayloads() {
    const payloads: MonitorPayload[] = [
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
                domain: fallbackHost.value,
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
                domain: fallbackHost.value,
                warning_days: parseNumberList(domainWarningDaysText.value),
            },
            expected: {
                registered: form.monitors.domain.registered,
            },
        },
        {
            type: 'dns',
            name: form.monitors.dns.name,
            is_enabled: form.monitors.dns.is_enabled,
            interval_seconds: form.monitors.dns.interval_seconds,
            timeout_ms: form.monitors.dns.timeout_ms,
            settings: {
                domain: fallbackHost.value,
                record_types: parseTextList(dnsRecordTypesText.value),
                nameservers: [],
                warn_on_change: form.monitors.dns.warn_on_change,
            },
            expected: {
                resolves: form.monitors.dns.resolves,
                min_records: form.monitors.dns.min_records,
            },
        },
        {
            type: 'robots_txt',
            name: form.monitors.robots_txt.name,
            is_enabled: form.monitors.robots_txt.is_enabled,
            interval_seconds: form.monitors.robots_txt.interval_seconds,
            timeout_ms: form.monitors.robots_txt.timeout_ms,
            settings: {
                url: robotsUrl.value || absoluteUrl('/robots.txt', rootUrl.value || httpUrl.value),
                follow_redirects: form.monitors.robots_txt.follow_redirects,
                verify_ssl: form.monitors.robots_txt.verify_ssl,
            },
            expected: {
                exists: form.monitors.robots_txt.exists,
                status_codes: parseNumberList(robotsStatusCodesText.value),
                max_response_time_ms: form.monitors.robots_txt.max_response_time_ms,
            },
        },
        {
            type: 'sitemap_xml',
            name: form.monitors.sitemap_xml.name,
            is_enabled: form.monitors.sitemap_xml.is_enabled,
            interval_seconds: form.monitors.sitemap_xml.interval_seconds,
            timeout_ms: form.monitors.sitemap_xml.timeout_ms,
            settings: {
                url: sitemapUrl.value || absoluteUrl('/sitemap.xml', rootUrl.value || httpUrl.value),
                follow_redirects: form.monitors.sitemap_xml.follow_redirects,
                verify_ssl: form.monitors.sitemap_xml.verify_ssl,
            },
            expected: {
                exists: form.monitors.sitemap_xml.exists,
                valid_xml: form.monitors.sitemap_xml.valid_xml,
                status_codes: parseNumberList(sitemapStatusCodesText.value),
                max_response_time_ms: form.monitors.sitemap_xml.max_response_time_ms,
            },
        },
    ]

    return payloads
        .filter((monitor) => planAllowsType(monitor.type))
        .concat(planAllowsType('api_endpoint')
            ? apiEndpoints.value.map<MonitorPayload>((endpoint, index) => ({
                type: 'api_endpoint',
                name: `API endpoint ${index + 1}`,
                is_enabled: form.monitors.api_endpoint.is_enabled,
                interval_seconds: form.monitors.api_endpoint.interval_seconds,
                timeout_ms: form.monitors.api_endpoint.timeout_ms,
                settings: {
                    method: endpoint.method,
                    url: absoluteUrl(endpoint.path, rootUrl.value || httpUrl.value),
                    headers: {},
                    body: null,
                    follow_redirects: form.monitors.api_endpoint.follow_redirects,
                    verify_ssl: form.monitors.api_endpoint.verify_ssl,
                },
                expected: {
                    status_codes: parseNumberList(endpoint.status_codes),
                    max_response_time_ms: endpoint.max_response_time_ms,
                    response_contains: endpoint.response_contains.trim() || null,
                },
            }))
            : [])
        .concat(planAllowsType('tcp_port')
            ? tcpPorts.value.map<MonitorPayload>((tcpPort) => ({
                type: 'tcp_port',
                name: `TCP port ${tcpPort.port ?? ''}`.trim(),
                is_enabled: form.monitors.tcp_port.is_enabled,
                interval_seconds: form.monitors.tcp_port.interval_seconds,
                timeout_ms: form.monitors.tcp_port.timeout_ms,
                settings: {
                    host: fallbackHost.value,
                    port: tcpPort.port,
                },
                expected: {
                    open: form.monitors.tcp_port.open,
                    max_response_time_ms: tcpPort.max_response_time_ms,
                },
            }))
            : [])
}

function requestPayload() {
    return {
        name: form.name,
        url: form.url,
        monitors: monitorPayloads(),
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
        subtitle="Выберите проверки, доступные на вашем тарифе"
    >
        <form class="mx-auto max-w-7xl px-5 pb-6 pt-6 sm:px-8" @submit.prevent="submit">
            <div>
                <div class="space-y-6">
                    <section class="rounded-[24px] border border-[#DDEBE3] bg-white p-4 shadow-[0_10px_35px_rgba(38,51,45,0.06)] sm:p-5">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.1em] text-[#1E9B5D]">Шаг 1</p>
                                <h2 class="mt-1 text-xl font-bold text-[#173B2A]">Сайт</h2>
                            </div>
                            <p class="text-xs font-medium text-[#6A7A70]">Название можно оставить пустым — возьмём домен.</p>
                        </div>

                        <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(240px,0.6fr)]">
                            <div>
                                <label for="url" class="mb-1.5 block text-sm font-semibold text-[#26332D]">URL сайта</label>
                                <input
                                    id="url"
                                    v-model="form.url"
                                    type="text"
                                    required
                                    placeholder="https://example.com"
                                    class="h-11 w-full rounded-xl border border-[#CFE1D7] bg-white px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#9AA9A0] focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15"
                                >
                                <p v-if="form.errors.url" class="mt-2 text-sm font-semibold text-[#EF4444]">{{ form.errors.url }}</p>
                            </div>

                            <div>
                                <label for="name" class="mb-1.5 block text-sm font-semibold text-[#26332D]">Название</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Основной сайт"
                                    class="h-11 w-full rounded-xl border border-[#CFE1D7] bg-white px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#9AA9A0] focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15"
                                >
                                <p v-if="form.errors.name" class="mt-2 text-sm font-semibold text-[#EF4444]">{{ form.errors.name }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-col gap-2 rounded-2xl border border-[#DDEBE3] bg-[#F6FBF8] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-[#173B2A]">{{ normalizedSite?.host ?? 'Домен появится после ввода URL' }}</p>
                                <p class="mt-0.5 truncate text-xs font-medium text-[#6A7A70]">{{ normalizedSite?.url ?? 'Если схема не указана, Montry добавит HTTPS' }}</p>
                            </div>
                            <span class="inline-flex h-8 shrink-0 items-center justify-center rounded-xl bg-white px-3 text-xs font-semibold text-[#52645A]">
                                {{ normalizedSite ? 'URL распознан' : 'Ожидаем URL' }}
                            </span>
                        </div>
                    </section>

                    <section class="rounded-[28px] border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_35px_rgba(38,51,45,0.06)] sm:p-6">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#1E9B5D]">Шаг 2</p>
                                <h2 class="mt-1 text-2xl font-bold text-[#173B2A]">Проверки</h2>
                            </div>
                            <span class="inline-flex w-fit rounded-full bg-[#E9F8EF] px-4 py-2 text-sm font-semibold text-[#1E9B5D]">
                                {{ activeMonitorCount }} из {{ baseCheckCards.length + paidPlanCards.length }} активны
                            </span>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <article
                                v-for="card in baseCheckCards"
                                :key="card.type"
                                class="group flex flex-col rounded-3xl border p-4 transition"
                                :class="card.enabled ? 'border-[#BEE7CE] bg-[#F8FFFB] shadow-[0_10px_28px_rgba(47,165,104,0.08)]' : 'border-[#DDEBE3] bg-[#F8FAFC]'"
                            >
                                <button type="button" class="block w-full text-left" @click="toggleMonitor(card.type)">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#1E9B5D] ring-1 ring-[#DDEBE3]">{{ card.label }}</span>
                                                <span class="rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-semibold text-[#1E9B5D]">{{ card.badge }}</span>
                                            </div>
                                            <h3 class="mt-4 text-lg font-bold text-[#173B2A]">{{ card.title }}</h3>
                                            <p class="mt-2 text-sm leading-6 text-[#6A7A70]">{{ card.description }}</p>
                                        </div>
                                        <span
                                            class="flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition"
                                            :class="card.enabled ? 'justify-end bg-[#2FA568]' : 'justify-start bg-[#CFE1D7]'"
                                        >
                                            <span class="h-5 w-5 rounded-full bg-white shadow-sm" />
                                        </span>
                                    </div>
                                </button>

                                <div class="mt-5 flex items-center justify-between gap-3 border-t border-[#DDEBE3] pt-3">
                                    <p class="min-w-0 truncate text-xs font-semibold text-[#6A7A70]">{{ card.summary }}</p>
                                    <button type="button" class="shrink-0 text-xs font-bold text-[#1E9B5D] hover:text-[#173B2A]" @click="openAdvanced(card.type)">
                                        {{ openedAdvanced === card.type ? 'Скрыть настройки' : 'Открыть настройки' }}
                                    </button>
                                </div>

                                <div v-if="openedAdvanced === card.type" class="mt-4 grid gap-4 border-t border-[#DDEBE3] pt-4 md:grid-cols-2">
                                    <template v-if="card.type === 'http'">
                                        <div>
                                            <label for="http-name" class="mb-2 block text-sm font-semibold text-[#26332D]">Название</label>
                                            <input id="http-name" v-model="form.monitors.http.name" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div>
                                            <label for="http-method" class="mb-2 block text-sm font-semibold text-[#26332D]">Метод</label>
                                            <select id="http-method" v-model="form.monitors.http.method" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                                <option value="GET">GET</option>
                                                <option value="HEAD">HEAD</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="http-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                            <input id="http-status" v-model="statusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div>
                                            <label for="http-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                            <input id="http-time" v-model.number="form.monitors.http.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div class="md:col-span-2">
                                            <div class="mb-3 flex items-center justify-between gap-3">
                                                <span class="text-sm font-semibold text-[#26332D]">Частота проверки</span>
                                                <span class="text-sm font-bold text-[#1E9B5D]">{{ intervalText(form.monitors.http.interval_seconds) }}</span>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button v-for="minutes in availableIntervalPresets" :key="`http-${minutes}`" type="button" class="rounded-full px-3 py-2 text-xs font-bold transition" :class="intervalMinutes(form.monitors.http.interval_seconds) === minutes ? 'bg-[#2FA568] text-white' : 'bg-white text-[#52645A] ring-1 ring-[#DDEBE3] hover:text-[#173B2A]'" @click="setIntervalMinutes(form.monitors.http, minutes)">
                                                    {{ minutes === 60 ? '1 час' : minutes === 1440 ? '1 день' : minutes < 60 ? `${minutes} мин` : `${minutes / 60} ч` }}
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'ssl'">
                                        <div>
                                            <label for="ssl-port" class="mb-2 block text-sm font-semibold text-[#26332D]">Порт</label>
                                            <input id="ssl-port" v-model.number="form.monitors.ssl.port" min="1" max="65535" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div>
                                            <label for="ssl-days" class="mb-2 block text-sm font-semibold text-[#26332D]">Дни предупреждений</label>
                                            <input id="ssl-days" v-model="sslWarningDaysText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'domain'">
                                        <div class="md:col-span-2">
                                            <label for="domain-days" class="mb-2 block text-sm font-semibold text-[#26332D]">Дни предупреждений</label>
                                            <input id="domain-days" v-model="domainWarningDaysText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'dns'">
                                        <div>
                                            <label for="dns-types" class="mb-2 block text-sm font-semibold text-[#26332D]">Типы записей</label>
                                            <input id="dns-types" v-model="dnsRecordTypesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div>
                                            <label for="dns-min" class="mb-2 block text-sm font-semibold text-[#26332D]">Минимум записей</label>
                                            <input id="dns-min" v-model.number="form.monitors.dns.min_records" min="0" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'robots_txt'">
                                        <div>
                                            <label for="robots-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                            <input id="robots-status" v-model="robotsStatusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div>
                                            <label for="robots-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                            <input id="robots-time" v-model.number="form.monitors.robots_txt.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                    </template>
                                </div>
                            </article>
                        </div>

                        <div class="mt-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <article
                                    v-for="card in paidPlanCards"
                                    :key="card.type"
                                    class="relative flex flex-col overflow-hidden rounded-3xl border p-4 transition"
                                    :class="card.enabled && card.included ? 'border-[#BEE7CE] bg-[#F8FFFB] shadow-[0_10px_28px_rgba(47,165,104,0.08)]' : card.included ? 'border-[#DDEBE3] bg-[#F8FAFC]' : 'border-[#D9DEDB] bg-[#F1F4F2]'"
                                >
                                <div class="flex flex-1 flex-col" :class="!card.included ? 'pointer-events-none opacity-40 grayscale' : ''">
                                <button type="button" class="block w-full text-left" @click="toggleMonitor(card.type)">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#1E9B5D] ring-1 ring-[#DDEBE3]">{{ card.label }}</span>
                                                <span class="rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-semibold text-[#1E9B5D]">{{ card.badge }}</span>
                                            </div>
                                            <h3 class="mt-4 text-lg font-bold text-[#173B2A]">{{ card.title }}</h3>
                                            <p class="mt-2 text-sm leading-6 text-[#6A7A70]">{{ card.description }}</p>
                                        </div>
                                        <span
                                            class="flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition"
                                            :class="card.enabled && card.included ? 'justify-end bg-[#2FA568]' : 'justify-start bg-[#CFE1D7]'"
                                        >
                                            <span class="h-5 w-5 rounded-full bg-white shadow-sm" />
                                        </span>
                                    </div>
                                </button>
                                <div class="mt-5 flex items-center justify-between gap-3 border-t border-[#DDEBE3] pt-3">
                                    <p class="min-w-0 truncate text-xs font-semibold text-[#6A7A70]">{{ card.summary }}</p>
                                    <button type="button" class="shrink-0 text-xs font-bold text-[#1E9B5D] hover:text-[#173B2A]" @click="openAdvanced(card.type)">
                                        {{ openedAdvanced === card.type ? 'Скрыть настройки' : 'Открыть настройки' }}
                                    </button>
                                </div>

                                <div v-if="openedAdvanced === card.type" class="mt-4 grid gap-4 border-t border-[#DDEBE3] pt-4 md:grid-cols-2">
                                    <template v-if="card.type === 'domain'">
                                        <div class="md:col-span-2">
                                            <label for="domain-days-paid" class="mb-2 block text-sm font-semibold text-[#26332D]">Дни предупреждений</label>
                                            <input id="domain-days-paid" v-model="domainWarningDaysText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'dns'">
                                        <div>
                                            <label for="dns-types-paid" class="mb-2 block text-sm font-semibold text-[#26332D]">Типы записей</label>
                                            <input id="dns-types-paid" v-model="dnsRecordTypesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div>
                                            <label for="dns-min-paid" class="mb-2 block text-sm font-semibold text-[#26332D]">Минимум записей</label>
                                            <input id="dns-min-paid" v-model.number="form.monitors.dns.min_records" min="0" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[#DDEBE3] bg-[#F8FAFC] p-3 md:col-span-2">
                                            <input
                                                v-model="form.monitors.dns.warn_on_change"
                                                type="checkbox"
                                                class="mt-0.5 h-4 w-4 rounded border-[#B8D0C2] text-[#2FA568] focus:ring-[#2FA568]/25"
                                            >
                                            <span>
                                                <span class="block text-sm font-semibold text-[#26332D]">Создавать Warning при изменении записей</span>
                                                <span class="mt-1 block text-xs leading-5 text-[#6A7A70]">Montry сравнит результат с предыдущей успешной проверкой и покажет, какие записи добавились или исчезли.</span>
                                            </span>
                                        </label>
                                    </template>

                                    <template v-else-if="card.type === 'robots_txt'">
                                        <div>
                                            <label for="robots-status-paid" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                            <input id="robots-status-paid" v-model="robotsStatusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div>
                                            <label for="robots-time-paid" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                            <input id="robots-time-paid" v-model.number="form.monitors.robots_txt.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'sitemap_xml'">
                                        <div>
                                            <label for="sitemap-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                            <input id="sitemap-status" v-model="sitemapStatusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                        <div>
                                            <label for="sitemap-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                            <input id="sitemap-time" v-model.number="form.monitors.sitemap_xml.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'api_endpoint'">
                                        <div class="md:col-span-2">
                                            <div
                                                v-for="(endpoint, index) in apiEndpoints"
                                                :key="index"
                                                class="border-t border-[#E5ECE8] py-3 first:border-t-0 first:pt-0"
                                            >
                                                <div class="mb-2 flex items-center justify-between gap-3">
                                                    <span class="text-xs font-bold uppercase tracking-[0.08em] text-[#6A7A70]">Endpoint {{ index + 1 }}</span>
                                                    <button
                                                        type="button"
                                                        class="grid h-8 w-8 place-items-center rounded-lg text-[#98A2B3] transition hover:bg-[#FEECEC] hover:text-[#D64545] disabled:cursor-not-allowed disabled:opacity-30"
                                                        :disabled="apiEndpoints.length === 1"
                                                        aria-label="Удалить API endpoint"
                                                        @click="removeApiEndpoint(index)"
                                                    >
                                                        <Trash2 class="h-4 w-4" :stroke-width="2" />
                                                    </button>
                                                </div>

                                                <div class="grid gap-2 sm:grid-cols-[100px_minmax(0,1fr)]">
                                                    <select
                                                        v-model="endpoint.method"
                                                        :aria-label="`Метод endpoint ${index + 1}`"
                                                        class="h-9 rounded-xl border border-[#CFE1D7] bg-white px-3 text-sm outline-none focus:border-[#2FA568] focus:ring-3 focus:ring-[#2FA568]/15"
                                                    >
                                                        <option value="GET">GET</option>
                                                        <option value="HEAD">HEAD</option>
                                                        <option value="POST">POST</option>
                                                        <option value="PUT">PUT</option>
                                                        <option value="PATCH">PATCH</option>
                                                        <option value="DELETE">DELETE</option>
                                                    </select>
                                                    <input
                                                        v-model="endpoint.path"
                                                        required
                                                        type="text"
                                                        :aria-label="`URL endpoint ${index + 1}`"
                                                        placeholder="/api/health или полный URL"
                                                        class="h-9 w-full rounded-xl border border-[#CFE1D7] bg-white px-3 text-sm outline-none focus:border-[#2FA568] focus:ring-3 focus:ring-[#2FA568]/15"
                                                    >
                                                </div>

                                                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                                    <input
                                                        v-model="endpoint.status_codes"
                                                        required
                                                        type="text"
                                                        :aria-label="`Ожидаемые коды endpoint ${index + 1}`"
                                                        placeholder="Коды: 200, 204"
                                                        class="h-9 w-full rounded-xl border border-[#CFE1D7] bg-white px-3 text-sm outline-none focus:border-[#2FA568] focus:ring-3 focus:ring-[#2FA568]/15"
                                                    >
                                                    <input
                                                        v-model.number="endpoint.max_response_time_ms"
                                                        min="1"
                                                        required
                                                        type="number"
                                                        :aria-label="`Максимальное время ответа endpoint ${index + 1}`"
                                                        placeholder="Время, мс"
                                                        class="h-9 w-full rounded-xl border border-[#CFE1D7] bg-white px-3 text-sm outline-none focus:border-[#2FA568] focus:ring-3 focus:ring-[#2FA568]/15"
                                                    >
                                                    <input
                                                        v-model="endpoint.response_contains"
                                                        type="text"
                                                        :aria-label="`Текст ответа endpoint ${index + 1}`"
                                                        placeholder="Ответ содержит (необязательно)"
                                                        class="h-9 w-full rounded-xl border border-[#CFE1D7] bg-white px-3 text-sm outline-none focus:border-[#2FA568] focus:ring-3 focus:ring-[#2FA568]/15"
                                                    >
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                class="mt-2 inline-flex h-9 items-center gap-2 rounded-xl border border-[#BEE7CE] bg-[#E9F8EF] px-3 text-sm font-semibold text-[#1E9B5D] transition hover:bg-[#DDF6E8] disabled:cursor-not-allowed disabled:border-[#DDEBE3] disabled:bg-[#F3F5F4] disabled:text-[#98A2B3]"
                                                :disabled="!canAddApiEndpoint"
                                                @click="addApiEndpoint"
                                            >
                                                <Plus class="h-4 w-4" :stroke-width="2" />
                                                Добавить endpoint
                                            </button>
                                            <p v-if="!canAddApiEndpoint" class="mt-2 text-xs font-medium text-[#8A6D3B]">
                                                <span v-if="!form.monitors.api_endpoint.is_enabled">Включите API-мониторинг, чтобы добавить endpoint.</span>
                                                <span v-else>Лимит активных мониторингов исчерпан.</span>
                                            </p>
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'tcp_port'">
                                        <div class="md:col-span-2">
                                            <div class="mb-1 hidden grid-cols-[minmax(0,1fr)_minmax(0,1fr)_32px] gap-2 px-1 text-xs font-semibold text-[#6A7A70] sm:grid">
                                                <span>Порт</span>
                                                <span>Макс. время ответа, мс</span>
                                                <span />
                                            </div>
                                            <div
                                                v-for="(tcpPort, index) in tcpPorts"
                                                :key="index"
                                                class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_32px] items-center gap-2 border-t border-[#E5ECE8] py-2 first:border-t-0"
                                            >
                                                <input
                                                    :id="`tcp-port-${index}`"
                                                    v-model.number="tcpPort.port"
                                                    min="1"
                                                    max="65535"
                                                    required
                                                    type="number"
                                                    aria-label="Порт"
                                                    placeholder="Порт"
                                                    class="h-9 w-full rounded-xl border border-[#CFE1D7] bg-white px-3 text-sm outline-none focus:border-[#2FA568] focus:ring-3 focus:ring-[#2FA568]/15"
                                                >
                                                <input
                                                    :id="`tcp-time-${index}`"
                                                    v-model.number="tcpPort.max_response_time_ms"
                                                    min="1"
                                                    required
                                                    type="number"
                                                    aria-label="Максимальное время ответа в миллисекундах"
                                                    placeholder="Время, мс"
                                                    class="h-9 w-full rounded-xl border border-[#CFE1D7] bg-white px-3 text-sm outline-none focus:border-[#2FA568] focus:ring-3 focus:ring-[#2FA568]/15"
                                                >
                                                <button
                                                    type="button"
                                                    class="grid h-8 w-8 place-items-center rounded-lg text-[#98A2B3] transition hover:bg-[#FEECEC] hover:text-[#D64545] disabled:cursor-not-allowed disabled:opacity-30"
                                                    :disabled="tcpPorts.length === 1"
                                                    aria-label="Удалить TCP-порт"
                                                    @click="removeTcpPort(index)"
                                                >
                                                    <Trash2 class="h-4 w-4" :stroke-width="2" />
                                                </button>
                                            </div>

                                            <button
                                                type="button"
                                                class="mt-2 inline-flex h-9 items-center gap-2 rounded-xl border border-[#BEE7CE] bg-[#E9F8EF] px-3 text-sm font-semibold text-[#1E9B5D] transition hover:bg-[#DDF6E8] disabled:cursor-not-allowed disabled:border-[#DDEBE3] disabled:bg-[#F3F5F4] disabled:text-[#98A2B3]"
                                                :disabled="!canAddTcpPort"
                                                @click="addTcpPort"
                                            >
                                                <Plus class="h-4 w-4" :stroke-width="2" />
                                                Добавить порт
                                            </button>
                                            <p v-if="!canAddTcpPort" class="text-xs font-medium text-[#8A6D3B]">
                                                <span v-if="!form.monitors.tcp_port.is_enabled">Включите TCP-мониторинг, чтобы добавить порт.</span>
                                                <span v-else>Лимит активных мониторингов исчерпан.</span>
                                            </p>
                                        </div>
                                    </template>
                                </div>
                                </div>

                                <Link
                                    v-if="!card.included"
                                    href="/billing"
                                    class="absolute inset-0 z-10 flex items-center justify-center bg-[#F1F4F2]/55 p-5"
                                >
                                    <span class="inline-flex items-center gap-2 rounded-2xl border border-[#C7D2CC] bg-white px-4 py-3 text-sm font-bold text-[#52645A] shadow-[0_10px_30px_rgba(38,51,45,0.12)] transition hover:border-[#9FB7AA] hover:text-[#173B2A]">
                                        <Crown class="h-5 w-5 text-[#C59A2D]" />
                                        Доступно в Pro, Team
                                    </span>
                                </Link>
                                </article>
                            </div>
                        </div>
                    </section>

                    <section v-if="false" class="rounded-[28px] border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_35px_rgba(38,51,45,0.06)] sm:p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-2xl font-bold text-[#173B2A]">Тонкие настройки</h2>
                                <p class="mt-2 text-sm leading-6 text-[#6A7A70]">Меняйте только то, что отличается от стандартного сценария.</p>
                            </div>
                            <span class="rounded-full bg-[#F3F8F5] px-4 py-2 text-sm font-semibold text-[#52645A]">Интервал: минимум {{ minimumIntervalMinutes }} мин</span>
                        </div>

                        <div class="mt-6 space-y-3">
                            <article class="rounded-3xl border border-[#DDEBE3] bg-[#F8FAFC]">
                                <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="openAdvanced('http')">
                                    <span class="font-bold text-[#173B2A]">HTTP availability</span>
                                    <span class="text-sm font-semibold text-[#6A7A70]">{{ openedAdvanced === 'http' ? 'Скрыть' : 'Открыть' }}</span>
                                </button>
                                <div v-if="openedAdvanced === 'http'" class="grid gap-4 border-t border-[#DDEBE3] p-4 md:grid-cols-2">
                                    <div>
                                        <label for="http-name" class="mb-2 block text-sm font-semibold text-[#26332D]">Название</label>
                                        <input id="http-name" v-model="form.monitors.http.name" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="http-method" class="mb-2 block text-sm font-semibold text-[#26332D]">Метод</label>
                                        <select id="http-method" v-model="form.monitors.http.method" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                            <option value="GET">GET</option>
                                            <option value="HEAD">HEAD</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="http-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                        <input id="http-status" v-model="statusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="http-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                        <input id="http-time" v-model.number="form.monitors.http.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div class="md:col-span-2">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <span class="text-sm font-semibold text-[#26332D]">Частота проверки</span>
                                            <span class="text-sm font-bold text-[#1E9B5D]">{{ intervalText(form.monitors.http.interval_seconds) }}</span>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button v-for="minutes in availableIntervalPresets" :key="`http-${minutes}`" type="button" class="rounded-full px-3 py-2 text-xs font-bold transition" :class="intervalMinutes(form.monitors.http.interval_seconds) === minutes ? 'bg-[#2FA568] text-white' : 'bg-white text-[#52645A] ring-1 ring-[#DDEBE3] hover:text-[#173B2A]'" @click="setIntervalMinutes(form.monitors.http, minutes)">
                                                {{ minutes === 60 ? '1 час' : minutes === 1440 ? '1 день' : minutes < 60 ? `${minutes} мин` : `${minutes / 60} ч` }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-3xl border border-[#DDEBE3] bg-[#F8FAFC]">
                                <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="openAdvanced('ssl')">
                                    <span class="font-bold text-[#173B2A]">SSL certificate</span>
                                    <span class="text-sm font-semibold text-[#6A7A70]">{{ openedAdvanced === 'ssl' ? 'Скрыть' : 'Открыть' }}</span>
                                </button>
                                <div v-if="openedAdvanced === 'ssl'" class="grid gap-4 border-t border-[#DDEBE3] p-4 md:grid-cols-2">
                                    <div>
                                        <label for="ssl-port" class="mb-2 block text-sm font-semibold text-[#26332D]">Порт</label>
                                        <input id="ssl-port" v-model.number="form.monitors.ssl.port" min="1" max="65535" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="ssl-days" class="mb-2 block text-sm font-semibold text-[#26332D]">Дни предупреждений</label>
                                        <input id="ssl-days" v-model="sslWarningDaysText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-3xl border border-[#DDEBE3] bg-[#F8FAFC]">
                                <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="openAdvanced('domain')">
                                    <span class="font-bold text-[#173B2A]">Domain expiration</span>
                                    <span class="text-sm font-semibold text-[#6A7A70]">{{ openedAdvanced === 'domain' ? 'Скрыть' : 'Открыть' }}</span>
                                </button>
                                <div v-if="openedAdvanced === 'domain'" class="border-t border-[#DDEBE3] p-4">
                                    <label for="domain-days" class="mb-2 block text-sm font-semibold text-[#26332D]">Дни предупреждений</label>
                                    <input id="domain-days" v-model="domainWarningDaysText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                </div>
                            </article>

                            <article class="rounded-3xl border border-[#DDEBE3] bg-[#F8FAFC]">
                                <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="openAdvanced('dns')">
                                    <span class="font-bold text-[#173B2A]">DNS records</span>
                                    <span class="text-sm font-semibold text-[#6A7A70]">{{ openedAdvanced === 'dns' ? 'Скрыть' : 'Открыть' }}</span>
                                </button>
                                <div v-if="openedAdvanced === 'dns'" class="grid gap-4 border-t border-[#DDEBE3] p-4 md:grid-cols-2">
                                    <div>
                                        <label for="dns-types" class="mb-2 block text-sm font-semibold text-[#26332D]">Типы записей</label>
                                        <input id="dns-types" v-model="dnsRecordTypesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="dns-min" class="mb-2 block text-sm font-semibold text-[#26332D]">Минимум записей</label>
                                        <input id="dns-min" v-model.number="form.monitors.dns.min_records" min="0" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-3xl border border-[#DDEBE3] bg-[#F8FAFC]">
                                <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="openAdvanced('robots_txt')">
                                    <span class="font-bold text-[#173B2A]">Robots.txt</span>
                                    <span class="text-sm font-semibold text-[#6A7A70]">{{ openedAdvanced === 'robots_txt' ? 'Скрыть' : 'Открыть' }}</span>
                                </button>
                                <div v-if="openedAdvanced === 'robots_txt'" class="grid gap-4 border-t border-[#DDEBE3] p-4 md:grid-cols-2">
                                    <div>
                                        <label for="robots-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                        <input id="robots-status" v-model="robotsStatusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="robots-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                        <input id="robots-time" v-model.number="form.monitors.robots_txt.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-3xl border border-[#DDEBE3] bg-[#F8FAFC]">
                                <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="openAdvanced('sitemap_xml')">
                                    <span class="font-bold text-[#173B2A]">Sitemap.xml</span>
                                    <span class="text-sm font-semibold text-[#6A7A70]">{{ openedAdvanced === 'sitemap_xml' ? 'Скрыть' : 'Открыть' }}</span>
                                </button>
                                <div v-if="openedAdvanced === 'sitemap_xml'" class="grid gap-4 border-t border-[#DDEBE3] p-4 md:grid-cols-2">
                                    <div>
                                        <label for="sitemap-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                        <input id="sitemap-status" v-model="sitemapStatusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="sitemap-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                        <input id="sitemap-time" v-model.number="form.monitors.sitemap_xml.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-3xl border border-[#DDEBE3] bg-[#F8FAFC]">
                                <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="openAdvanced('api_endpoint')">
                                    <span class="font-bold text-[#173B2A]">API endpoint</span>
                                    <span class="text-sm font-semibold text-[#6A7A70]">{{ openedAdvanced === 'api_endpoint' ? 'Скрыть' : 'Открыть' }}</span>
                                </button>
                                <div v-if="openedAdvanced === 'api_endpoint'" class="grid gap-4 border-t border-[#DDEBE3] p-4 md:grid-cols-2">
                                    <div>
                                        <label for="api-method" class="mb-2 block text-sm font-semibold text-[#26332D]">Метод</label>
                                        <select id="api-method" v-model="apiEndpoints[0].method" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                            <option value="GET">GET</option>
                                            <option value="HEAD">HEAD</option>
                                            <option value="POST">POST</option>
                                            <option value="PUT">PUT</option>
                                            <option value="PATCH">PATCH</option>
                                            <option value="DELETE">DELETE</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="api-path" class="mb-2 block text-sm font-semibold text-[#26332D]">Endpoint</label>
                                        <input id="api-path" v-model="apiEndpoints[0].path" type="text" placeholder="/api/health" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="api-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                        <input id="api-status" v-model="apiEndpoints[0].status_codes" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="api-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                        <input id="api-time" v-model.number="apiEndpoints[0].max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="api-contains" class="mb-2 block text-sm font-semibold text-[#26332D]">Ответ должен содержать</label>
                                        <input id="api-contains" v-model="apiEndpoints[0].response_contains" type="text" placeholder="опционально" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-3xl border border-[#DDEBE3] bg-[#F8FAFC]">
                                <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left" @click="openAdvanced('tcp_port')">
                                    <span class="font-bold text-[#173B2A]">TCP port</span>
                                    <span class="text-sm font-semibold text-[#6A7A70]">{{ openedAdvanced === 'tcp_port' ? 'Скрыть' : 'Открыть' }}</span>
                                </button>
                                <div v-if="openedAdvanced === 'tcp_port'" class="grid gap-4 border-t border-[#DDEBE3] p-4 md:grid-cols-2">
                                    <div>
                                        <label for="tcp-port" class="mb-2 block text-sm font-semibold text-[#26332D]">Порт</label>
                                        <input id="tcp-port" v-model.number="tcpPorts[0].port" min="1" max="65535" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="tcp-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                        <input id="tcp-time" v-model.number="tcpPorts[0].max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </div>

            <div class="sticky bottom-4 z-10 mt-6 rounded-[24px] border border-[#DDEBE3] bg-white/95 shadow-[0_12px_40px_rgba(38,51,45,0.14)] backdrop-blur">
                <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div class="min-w-0">
                        <div class="flex items-baseline gap-2">
                            <span class="text-sm font-semibold text-[#6A7A70]">Активные мониторинги</span>
                            <span class="text-xl font-bold text-[#173B2A]">{{ activeMonitorsAfterCreate }} / {{ monitorLimit }}</span>
                        </div>
                        <p
                            class="mt-1 truncate text-xs font-medium"
                            :class="isMonitorLimitReached ? 'text-[#B45309]' : 'text-[#6A7A70]'"
                        >
                            <span v-if="isMonitorLimitReached">Лимит будет превышен — отключите часть проверок или смените тариф.</span>
                            <span v-else>После создания останется {{ monitorsLeftAfterCreate }} мониторингов.</span>
                        </p>
                        <p v-if="form.errors.monitors" class="mt-1 text-xs font-semibold text-[#EF4444]">
                            {{ form.errors.monitors }}
                        </p>
                    </div>

                    <div class="grid shrink-0 grid-cols-2 gap-3 sm:flex">
                        <Link
                            href="/sites"
                            class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#DDEBE3] bg-white px-5 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:bg-[#F6FBF8] hover:text-[#173B2A] sm:min-w-32"
                        >
                            Отмена
                        </Link>
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#2FA568] px-6 text-sm font-bold text-white shadow-[0_10px_24px_rgba(47,165,104,0.22)] transition hover:bg-[#248653] disabled:cursor-not-allowed disabled:opacity-60 sm:min-w-44"
                            :disabled="form.processing || isMonitorLimitReached"
                        >
                            <span v-if="form.processing">Создаём сайт...</span>
                            <span v-else>Создать сайт</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </DashboardLayout>
</template>
