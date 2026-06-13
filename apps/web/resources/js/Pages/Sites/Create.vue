<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import FlashToast from '@/Components/FlashToast.vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Organization = {
    id: string | number
    name: string
}

type MonitorType = string

type MonitorTypeOption = {
    value: string
    code?: string
    label: string
    name?: string
    short_label?: string
    description?: string
    is_paid?: boolean
    is_default_for_site?: boolean
    default_enabled?: boolean
    unit_price_cents?: number
    currency?: string
    unit_label?: string | null
    sort_order?: number
    ui_meta?: Record<string, any>
}

type AddonCatalogItem = {
    code: string
    name: string
    unit_price_cents: number
    unit_label?: string
    kind?: string
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

type PageProps = {
    flash?: {
        error?: string | null
    }
}

type ToggleCard = {
    type: MonitorType
    title: string
    label: string
    description: string
    summary: string
    enabled: boolean
    included: boolean
    priceRub?: number
    badge?: string
}

const props = withDefaults(defineProps<{
    organization: Organization
    monitorTypes: MonitorTypeOption[]
    currentPlan?: CurrentPlan | null
    usage?: Usage | null
    addonCatalog?: AddonCatalogItem[]
    currentAddons?: Record<string, { quantity: number, unit_price_cents: number, currency: string }>
    entitlements?: Record<string, any> | null
}>(), {
    currentPlan: null,
    usage: null,
    addonCatalog: () => [],
    currentAddons: () => ({}),
    entitlements: null,
})

const page = usePage<PageProps>()
const minimumIntervalSecondsValue = props.usage?.minimum_check_interval_seconds ?? 300
const minimumIntervalMinutes = Math.max(5, Math.ceil(minimumIntervalSecondsValue / 60))
const statusCodesText = ref('200')
const sslWarningDaysText = ref('30, 14, 7, 3, 1')
const domainWarningDaysText = ref('30, 14, 7, 3, 1')
const dnsRecordTypesText = ref('A, AAAA')
const robotsStatusCodesText = ref('200')
const sitemapStatusCodesText = ref('200')
const apiStatusCodesText = ref('200')
const apiResponseContainsText = ref('')
const openedAdvanced = ref<MonitorType | null>('http')
const paymentProcessing = ref(false)

const intervalPresets = [5, 10, 15, 30, 60, 360, 720, 1440]
const availableIntervalPresets = computed(() => intervalPresets.filter((minutes) => minutes >= minimumIntervalMinutes))

const tariffCatalog: Record<string, { name: string; priceRub: number; sites: number; historyDays: number; intervalText: string }> = {
    free: { name: 'Free', priceRub: 0, sites: 1, historyDays: 3, intervalText: 'от 15 минут' },
    pro: { name: 'Pro', priceRub: 390, sites: 10, historyDays: 30, intervalText: 'от 5 минут' },
    plus: { name: 'Plus', priceRub: 690, sites: 30, historyDays: 60, intervalText: 'от 3 минут' },
}

function addonPriceRub(code: string): number {
    const item = props.addonCatalog.find((addon) => addon.code === code)
        ?? props.monitorTypes.find((type) => (type.code ?? type.value) === code)

    return Math.round((item?.unit_price_cents ?? 0) / 100)
}

const form = useForm({
    name: '',
    url: '',
    monitors: {
        http: {
            is_enabled: true,
            name: 'HTTP availability',
            interval_seconds: minimumIntervalMinutes * 60,
            timeout_ms: 10000,
            method: 'GET',
            follow_redirects: true,
            verify_ssl: true,
            max_response_time_ms: 5000,
        },
        ssl: {
            is_enabled: true,
            name: 'SSL certificate',
            interval_seconds: 86400,
            timeout_ms: 10000,
            port: 443,
            valid: true,
        },
        domain: {
            is_enabled: true,
            name: 'Domain expiration',
            interval_seconds: 86400,
            timeout_ms: 10000,
            registered: true,
        },
        dns: {
            is_enabled: true,
            name: 'DNS records',
            interval_seconds: 86400,
            timeout_ms: 10000,
            min_records: 1,
            resolves: true,
        },
        robots_txt: {
            is_enabled: true,
            name: 'Robots.txt',
            interval_seconds: 86400,
            timeout_ms: 10000,
            follow_redirects: true,
            verify_ssl: true,
            exists: true,
            max_response_time_ms: 5000,
        },
        sitemap_xml: {
            is_enabled: false,
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
            is_enabled: false,
            name: 'API endpoint',
            interval_seconds: minimumIntervalMinutes * 60,
            timeout_ms: 10000,
            method: 'GET',
            path: '/api/health',
            follow_redirects: true,
            verify_ssl: true,
            max_response_time_ms: 5000,
        },
        tcp_port: {
            is_enabled: false,
            name: 'TCP port',
            interval_seconds: minimumIntervalMinutes * 60,
            timeout_ms: 10000,
            port: 443,
            open: true,
            max_response_time_ms: 5000,
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
const apiEndpointUrl = computed(() => absoluteUrl(form.monitors.api_endpoint.path, rootUrl.value || httpUrl.value))

const planCode = computed(() => props.currentPlan?.code ?? 'free')
const displayPlan = computed(() => tariffCatalog[planCode.value] ?? {
    name: props.currentPlan?.name ?? 'Free',
    priceRub: props.currentPlan?.price_cents ? Math.round(props.currentPlan.price_cents / 100) : 0,
    sites: props.usage?.site_limit ?? 1,
    historyDays: Number(props.currentPlan?.limits?.history_retention_days?.days ?? 3),
    intervalText: `от ${minimumIntervalMinutes} минут`,
})
const sitesUsed = computed(() => props.usage?.sites ?? 0)
const siteLimit = computed(() => props.usage?.site_limit ?? displayPlan.value.sites)
const sitesAfterCreate = computed(() => sitesUsed.value + 1)
const sitesLeftAfterCreate = computed(() => Math.max(siteLimit.value - sitesAfterCreate.value, 0))
const isSiteLimitReached = computed(() => sitesAfterCreate.value > siteLimit.value)

const monitorTypeOptions = computed(() => [...props.monitorTypes]
    .filter((type) => Boolean((form.monitors as Record<string, any>)[type.code ?? type.value]))
    .sort((left, right) => (left.sort_order ?? 0) - (right.sort_order ?? 0)))

const baseCheckCards = computed<ToggleCard[]>(() => monitorTypeOptions.value
    .filter((type) => !type.is_paid)
    .map((type) => cardFromMonitorType(type, true)))

const paidAddonCards = computed<ToggleCard[]>(() => monitorTypeOptions.value
    .filter((type) => Boolean(type.is_paid))
    .map((type) => cardFromMonitorType(type, false)))

const selectedPaidAddons = computed(() => paidAddonCards.value.filter((card) => card.enabled))
const hasPaidAddons = computed(() => selectedPaidAddons.value.length > 0)
const addonTotalRub = computed(() => selectedPaidAddons.value.reduce((sum, card) => sum + (card.priceRub ?? 0), 0))
const currentAddonMonthlyRub = computed(() => Object.values(props.currentAddons).reduce((sum, addon) => {
    return sum + Math.round((addon.unit_price_cents * addon.quantity) / 100)
}, 0))
const currentMonthlyRub = computed(() => displayPlan.value.priceRub + currentAddonMonthlyRub.value)
const nextMonthlyRub = computed(() => currentMonthlyRub.value + addonTotalRub.value)
const checkoutAmountRub = computed(() => {
    if (props.currentPlan?.code === planCode.value) {
        return addonTotalRub.value
    }

    return nextMonthlyRub.value
})
const enabledBaseCount = computed(() => baseCheckCards.value.filter((card) => card.enabled).length)
const activeMonitorCount = computed(() => enabledBaseCount.value + selectedPaidAddons.value.length)
const monitorCountAfterCreate = computed(() => (props.usage?.monitors ?? 0) + baseCheckCards.value.length + selectedPaidAddons.value.length)
const selectedAddonQuantities = computed<Record<string, number>>(() => {
    const quantities = Object.fromEntries(
        Object.entries(props.currentAddons).map(([code, addon]) => [code, addon.quantity]),
    ) as Record<string, number>

    for (const addon of selectedPaidAddons.value) {
        quantities[addon.type] = (quantities[addon.type] ?? 0) + 1
    }

    return quantities
})

function cardFromMonitorType(type: MonitorTypeOption, included: boolean): ToggleCard {
    const code = type.code ?? type.value
    const priceRub = included ? undefined : addonPriceRub(code)

    return {
        type: code,
        title: type.ui_meta?.title ?? defaultCardTitle(code, type.name ?? type.label),
        label: type.short_label ?? type.label,
        description: type.description ?? defaultCardDescription(code),
        summary: summaryForType(code),
        enabled: Boolean((form.monitors as Record<string, any>)[code]?.is_enabled),
        included,
        priceRub,
        badge: included ? 'В тарифе' : `+${priceRub ?? 0} ₽/мес`,
    }
}

function defaultCardTitle(type: string, fallback: string): string {
    if (type === 'http') return 'Доступность сайта'
    if (type === 'ssl') return 'SSL-сертификат'
    if (type === 'domain') return 'Срок домена'
    if (type === 'dns') return 'DNS-записи'
    if (type === 'robots_txt') return 'Robots.txt'
    if (type === 'sitemap_xml') return 'Sitemap.xml'
    if (type === 'api_endpoint') return 'API endpoint'
    if (type === 'tcp_port') return 'TCP-порт'

    return fallback
}

function defaultCardDescription(type: string): string {
    if (type === 'http') return 'Код ответа, редиректы и время ответа главной страницы.'
    if (type === 'ssl') return 'Валидность сертификата и предупреждения до истечения.'
    if (type === 'domain') return 'WHOIS-проверка регистрации и даты окончания домена.'
    if (type === 'dns') return 'Проверка резолва домена и базовых DNS-записей.'
    if (type === 'robots_txt') return 'Наличие robots.txt и корректный HTTP-ответ.'
    if (type === 'sitemap_xml') return 'Проверка наличия и валидности XML-карты сайта.'
    if (type === 'api_endpoint') return 'Контроль healthcheck, webhook или любого API URL.'
    if (type === 'tcp_port') return 'Проверка открытого порта: HTTPS, SMTP, SSH или свой сервис.'

    return 'Настраиваемый тип мониторинга.'
}

function summaryForType(type: string): string {
    if (type === 'http') return `${form.monitors.http.method} · ${statusCodesText.value} · ${intervalText(form.monitors.http.interval_seconds)}`
    if (type === 'ssl') return `${fallbackHost.value || 'домен из URL'} · порт ${form.monitors.ssl.port}`
    if (type === 'domain') return `${fallbackHost.value || 'домен из URL'} · ${domainWarningDaysText.value} дней`
    if (type === 'dns') return `${dnsRecordTypesText.value} · минимум ${form.monitors.dns.min_records}`
    if (type === 'robots_txt') return robotsUrl.value || 'URL появится после ввода сайта'
    if (type === 'sitemap_xml') return sitemapUrl.value || 'URL появится после ввода сайта'
    if (type === 'api_endpoint') return `${form.monitors.api_endpoint.method} · ${apiEndpointUrl.value || 'endpoint'} · ${apiStatusCodesText.value}`
    if (type === 'tcp_port') return `${fallbackHost.value || 'host'}:${form.monitors.tcp_port.port}`

    return 'Параметры будут применены по умолчанию'
}

function typeLabel(type: string): string {
    const option = props.monitorTypes.find((item) => (item.code ?? item.value) === type)

    return option?.short_label
        ?? option?.name
        ?? option?.label
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
    const state = (form.monitors as Record<string, any>)[type]

    if (!state) return

    state.is_enabled = !state.is_enabled
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

function money(value: number): string {
    return `${new Intl.NumberFormat('ru-RU').format(value)} ₽/мес`
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

function baseMonitorPayloads() {
    return [
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
    ]
}

function paidAddonPayloads() {
    const payloads = []

    if (form.monitors.sitemap_xml.is_enabled) {
        payloads.push({
            type: 'sitemap_xml',
            name: form.monitors.sitemap_xml.name,
            is_enabled: true,
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
        })
    }

    if (form.monitors.api_endpoint.is_enabled) {
        payloads.push({
            type: 'api_endpoint',
            name: form.monitors.api_endpoint.name,
            is_enabled: true,
            interval_seconds: form.monitors.api_endpoint.interval_seconds,
            timeout_ms: form.monitors.api_endpoint.timeout_ms,
            settings: {
                method: form.monitors.api_endpoint.method,
                url: apiEndpointUrl.value,
                headers: {},
                body: null,
                follow_redirects: form.monitors.api_endpoint.follow_redirects,
                verify_ssl: form.monitors.api_endpoint.verify_ssl,
            },
            expected: {
                status_codes: parseNumberList(apiStatusCodesText.value),
                max_response_time_ms: form.monitors.api_endpoint.max_response_time_ms,
                response_contains: apiResponseContainsText.value.trim() || null,
            },
        })
    }

    if (form.monitors.tcp_port.is_enabled) {
        payloads.push({
            type: 'tcp_port',
            name: form.monitors.tcp_port.name,
            is_enabled: true,
            interval_seconds: form.monitors.tcp_port.interval_seconds,
            timeout_ms: form.monitors.tcp_port.timeout_ms,
            settings: {
                host: fallbackHost.value,
                port: form.monitors.tcp_port.port,
            },
            expected: {
                open: form.monitors.tcp_port.open,
                max_response_time_ms: form.monitors.tcp_port.max_response_time_ms,
            },
        })
    }

    return payloads
}

function requestPayload() {
    const visibleTypes = new Set([...baseCheckCards.value, ...paidAddonCards.value].map((card) => card.type))

    return {
        name: form.name,
        url: form.url,
        monitors: [
            ...baseMonitorPayloads(),
            ...paidAddonPayloads(),
        ].filter((monitor) => visibleTypes.has(monitor.type)),
    }
}

function submit(): void {
    if (hasPaidAddons.value) {
        paymentProcessing.value = true

        router.post('/billing/checkout', {
            plan_code: planCode.value,
            addons: selectedAddonQuantities.value,
        }, {
            preserveScroll: true,
            onFinish: () => {
                paymentProcessing.value = false
            },
        })

        return
    }

    form
        .transform(() => requestPayload())
        .post('/sites', {
            preserveScroll: true,
        })
}
</script>

<template>
    <Head title="Добавить сайт" />
    <FlashToast :message="page.props.flash?.error" />

    <DashboardLayout
        :organization="organization"
        active-item="sites"
        title="Добавить сайт"
        subtitle="Базовые проверки входят в тариф, дополнительные можно отметить сразу"
    >
        <template #actions>
            <Link
                href="/sites"
                class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#DDEBE3] bg-white px-5 text-sm font-semibold text-[#173B2A] transition hover:border-[#B8D0C2] hover:bg-[#F6FBF8]"
            >
                Назад к сайтам
            </Link>
        </template>

        <form class="mx-auto max-w-7xl px-5 py-6 sm:px-8" @submit.prevent="submit">
            <section class="rounded-[24px] border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_35px_rgba(38,51,45,0.06)] sm:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="inline-flex rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-[#1E9B5D]">
                            Новый мониторинг
                        </p>
                        <h1 class="mt-3 max-w-3xl text-2xl font-bold tracking-[-0.02em] text-[#173B2A] sm:text-3xl">
                            Добавьте сайт и выберите проверки
                        </h1>
                    </div>
                    <p class="max-w-xl text-sm leading-6 text-[#6A7A70]">
                        Базовые проверки входят в тариф. Платные проверки можно подключить кликом — справа появится итоговая сумма и переход к оплате.
                    </p>
                </div>
            </section>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div class="space-y-6">
                    <section class="rounded-[28px] border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_35px_rgba(38,51,45,0.06)] sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#1E9B5D]">Шаг 1</p>
                                <h2 class="mt-1 text-2xl font-bold text-[#173B2A]">Сайт</h2>
                            </div>
                            <p class="text-sm font-medium text-[#6A7A70]">Название можно оставить пустым — возьмём домен.</p>
                        </div>

                        <div class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(240px,0.6fr)]">
                            <div>
                                <label for="url" class="mb-2 block text-sm font-semibold text-[#26332D]">URL сайта</label>
                                <input
                                    id="url"
                                    v-model="form.url"
                                    type="text"
                                    required
                                    placeholder="https://example.com"
                                    class="h-12 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#9AA9A0] focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15"
                                >
                                <p v-if="form.errors.url" class="mt-2 text-sm font-semibold text-[#EF4444]">{{ form.errors.url }}</p>
                            </div>

                            <div>
                                <label for="name" class="mb-2 block text-sm font-semibold text-[#26332D]">Название</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Основной сайт"
                                    class="h-12 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#9AA9A0] focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15"
                                >
                                <p v-if="form.errors.name" class="mt-2 text-sm font-semibold text-[#EF4444]">{{ form.errors.name }}</p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 rounded-3xl border border-[#DDEBE3] bg-[#F6FBF8] p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6A7A70]">Предпросмотр</p>
                                <p class="mt-2 truncate text-lg font-bold text-[#173B2A]">{{ normalizedSite?.host ?? 'Домен появится после ввода URL' }}</p>
                                <p class="mt-1 truncate text-sm font-medium text-[#6A7A70]">{{ normalizedSite?.url ?? 'Если схема не указана, Montry добавит HTTPS' }}</p>
                            </div>
                            <span class="inline-flex h-10 items-center justify-center rounded-2xl bg-white px-4 text-sm font-semibold text-[#52645A]">
                                {{ normalizedSite ? 'URL распознан' : 'Ожидаем URL' }}
                            </span>
                        </div>
                    </section>

                    <section class="rounded-[28px] border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_35px_rgba(38,51,45,0.06)] sm:p-6">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#1E9B5D]">Шаг 2</p>
                                <h2 class="mt-1 text-2xl font-bold text-[#173B2A]">Базовые проверки в тарифе</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6A7A70]">
                                    Эти проверки не увеличивают стоимость тарифа. Отключенные проверки будут созданы на паузе и останутся доступны в карточке сайта.
                                </p>
                            </div>
                            <span class="inline-flex w-fit rounded-full bg-[#E9F8EF] px-4 py-2 text-sm font-semibold text-[#1E9B5D]">
                                {{ enabledBaseCount }} из {{ baseCheckCards.length }} активны
                            </span>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <article
                                v-for="card in baseCheckCards"
                                :key="card.type"
                                class="group rounded-3xl border p-4 transition"
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

                                <div class="mt-4 flex items-center justify-between gap-3 border-t border-[#DDEBE3] pt-4">
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
                    </section>

                    <section class="rounded-[28px] border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_35px_rgba(38,51,45,0.06)] sm:p-6">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#E08600]">Дополнительно</p>
                                <h2 class="mt-1 text-2xl font-bold text-[#173B2A]">Платные проверки</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6A7A70]">
                                    Отметьте нужные проверки — сумма пересчитается сразу. Пока подключается один Sitemap, один API endpoint и один TCP-порт на сайт.
                                </p>
                            </div>
                            <span class="inline-flex w-fit rounded-full bg-[#FFF7E8] px-4 py-2 text-sm font-semibold text-[#E08600]">
                                +{{ addonTotalRub }} ₽/мес
                            </span>
                        </div>

                        <div class="mt-6 grid gap-4 lg:grid-cols-3">
                            <article
                                v-for="card in paidAddonCards"
                                :key="card.type"
                                class="rounded-3xl border p-4 transition"
                                :class="card.enabled ? 'border-[#F6C66E] bg-[#FFFCF4] shadow-[0_10px_28px_rgba(224,134,0,0.10)]' : 'border-[#DDEBE3] bg-[#F8FAFC]'"
                            >
                                <button type="button" class="block w-full text-left" @click="toggleMonitor(card.type)">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#E08600] ring-1 ring-[#F6C66E]/60">{{ card.badge }}</span>
                                            <h3 class="mt-4 text-lg font-bold text-[#173B2A]">{{ card.title }}</h3>
                                            <p class="mt-2 text-sm leading-6 text-[#6A7A70]">{{ card.description }}</p>
                                        </div>
                                        <span
                                            class="flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition"
                                            :class="card.enabled ? 'justify-end bg-[#E08600]' : 'justify-start bg-[#CFE1D7]'"
                                        >
                                            <span class="h-5 w-5 rounded-full bg-white shadow-sm" />
                                        </span>
                                    </div>
                                </button>
                                <div class="mt-4 flex items-center justify-between gap-3 border-t border-[#EADFD0] pt-4">
                                    <p class="min-w-0 truncate text-xs font-semibold text-[#6A7A70]">{{ card.summary }}</p>
                                    <button type="button" class="shrink-0 text-xs font-bold text-[#E08600] hover:text-[#173B2A]" @click="openAdvanced(card.type)">
                                        {{ openedAdvanced === card.type ? 'Скрыть настройки' : 'Открыть настройки' }}
                                    </button>
                                </div>

                                <div v-if="openedAdvanced === card.type" class="mt-4 grid gap-4 border-t border-[#EADFD0] pt-4 md:grid-cols-2">
                                    <template v-if="card.type === 'sitemap_xml'">
                                        <div>
                                            <label for="sitemap-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                            <input id="sitemap-status" v-model="sitemapStatusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
                                        </div>
                                        <div>
                                            <label for="sitemap-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                            <input id="sitemap-time" v-model.number="form.monitors.sitemap_xml.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'api_endpoint'">
                                        <div>
                                            <label for="api-method" class="mb-2 block text-sm font-semibold text-[#26332D]">Метод</label>
                                            <select id="api-method" v-model="form.monitors.api_endpoint.method" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
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
                                            <input id="api-path" v-model="form.monitors.api_endpoint.path" type="text" placeholder="/api/health" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
                                        </div>
                                        <div>
                                            <label for="api-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                            <input id="api-status" v-model="apiStatusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
                                        </div>
                                        <div>
                                            <label for="api-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                            <input id="api-time" v-model.number="form.monitors.api_endpoint.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label for="api-contains" class="mb-2 block text-sm font-semibold text-[#26332D]">Ответ должен содержать</label>
                                            <input id="api-contains" v-model="apiResponseContainsText" type="text" placeholder="опционально" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
                                        </div>
                                    </template>

                                    <template v-else-if="card.type === 'tcp_port'">
                                        <div>
                                            <label for="tcp-port" class="mb-2 block text-sm font-semibold text-[#26332D]">Порт</label>
                                            <input id="tcp-port" v-model.number="form.monitors.tcp_port.port" min="1" max="65535" type="number" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
                                        </div>
                                        <div>
                                            <label for="tcp-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                            <input id="tcp-time" v-model.number="form.monitors.tcp_port.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#F6C66E]/70 bg-white px-4 text-sm outline-none focus:border-[#E08600] focus:ring-4 focus:ring-[#E08600]/15">
                                        </div>
                                    </template>
                                </div>
                            </article>
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
                                        <select id="api-method" v-model="form.monitors.api_endpoint.method" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
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
                                        <input id="api-path" v-model="form.monitors.api_endpoint.path" type="text" placeholder="/api/health" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="api-status" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                        <input id="api-status" v-model="apiStatusCodesText" type="text" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="api-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                        <input id="api-time" v-model.number="form.monitors.api_endpoint.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="api-contains" class="mb-2 block text-sm font-semibold text-[#26332D]">Ответ должен содержать</label>
                                        <input id="api-contains" v-model="apiResponseContainsText" type="text" placeholder="опционально" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
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
                                        <input id="tcp-port" v-model.number="form.monitors.tcp_port.port" min="1" max="65535" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                    <div>
                                        <label for="tcp-time" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                        <input id="tcp-time" v-model.number="form.monitors.tcp_port.max_response_time_ms" min="1" type="number" class="h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15">
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-28 xl:self-start">
                    <section v-if="!hasPaidAddons" class="rounded-[28px] border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_35px_rgba(38,51,45,0.08)]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-[#6A7A70]">Текущий тариф</p>
                                <h2 class="mt-1 text-2xl font-bold text-[#173B2A]">{{ displayPlan.name }}</h2>
                            </div>
                            <span class="rounded-full bg-[#E9F8EF] px-3 py-1 text-sm font-semibold text-[#1E9B5D]">
                                {{ money(displayPlan.priceRub) }}
                            </span>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-3xl bg-[#F6FBF8] p-4">
                                <p class="text-xs font-semibold text-[#6A7A70]">Сайты после добавления</p>
                                <p class="mt-2 text-2xl font-bold text-[#173B2A]">{{ sitesAfterCreate }} / {{ siteLimit }}</p>
                            </div>
                            <div class="rounded-3xl bg-[#F6FBF8] p-4">
                                <p class="text-xs font-semibold text-[#6A7A70]">История</p>
                                <p class="mt-2 text-2xl font-bold text-[#173B2A]">{{ displayPlan.historyDays }} дн.</p>
                            </div>
                        </div>

                        <p class="mt-4 text-sm leading-6 text-[#6A7A70]">
                            <span v-if="isSiteLimitReached" class="font-semibold text-[#B45309]">Лимит сайтов будет превышен. Докупите пакет +5 сайтов или перейдите на тариф выше.</span><span v-else>После создания останется {{ sitesLeftAfterCreate }} сайтов в текущем тарифе.</span> Минимальный интервал проверок: {{ displayPlan.intervalText }}.
                        </p>

                        <div v-if="form.errors.monitors" class="mt-5 rounded-3xl border border-[#FECACA] bg-[#FEECEC] px-4 py-3 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.monitors }}
                        </div>

                        <button
                            type="submit"
                            class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-2xl bg-[#2FA568] px-5 text-sm font-bold text-white shadow-[0_14px_32px_rgba(47,165,104,0.22)] transition hover:bg-[#248653] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing || paymentProcessing"
                        >
                            <span v-if="form.processing || paymentProcessing">Создаём сайт...</span>
                            <span v-else>Создать сайт</span>
                        </button>

                        <Link
                            href="/sites"
                            class="mt-3 inline-flex h-12 w-full items-center justify-center rounded-2xl border border-[#DDEBE3] bg-white px-5 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:bg-[#F6FBF8] hover:text-[#173B2A]"
                        >
                            Отмена
                        </Link>
                    </section>

                    <section v-else class="rounded-[28px] border border-[#F6C66E] bg-white p-5 shadow-[0_10px_35px_rgba(38,51,45,0.08)]">
                        <h2 class="text-xl font-bold text-[#173B2A]">Итого</h2>
                        <div class="mt-5 rounded-3xl bg-[#FFFCF4] p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#B7791F]">Сейчас к оплате</p>
                            <p class="mt-2 text-2xl font-bold text-[#173B2A]">{{ money(checkoutAmountRub) }}</p>
                            <p class="mt-1 text-sm font-medium text-[#6A7A70]">
                                <span v-if="props.currentPlan?.code === planCode">Оплачивается только стоимость новых платных проверок.</span>
                                <span v-else>Оплачивается тариф {{ displayPlan.name }} и выбранные платные проверки.</span>
                            </p>
                        </div>

                        <div class="mt-5 rounded-3xl bg-[#F6FBF8] p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6A7A70]">Сайт</p>
                            <p class="mt-2 truncate text-lg font-bold text-[#173B2A]">{{ normalizedSite?.host ?? 'Не указан' }}</p>
                            <p class="mt-1 truncate text-sm font-medium text-[#6A7A70]">{{ form.name || 'Название будет взято из домена' }}</p>
                        </div>

                        <div class="mt-5 space-y-3">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-[#6A7A70]">Базовые активные проверки</span>
                                <span class="font-bold text-[#173B2A]">{{ enabledBaseCount }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-[#6A7A70]">Платные проверки</span>
                                <span class="font-bold text-[#173B2A]">{{ selectedPaidAddons.length }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-[#6A7A70]">Всего активных проверок</span>
                                <span class="font-bold text-[#173B2A]">{{ activeMonitorCount }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-[#6A7A70]">Мониторов после создания</span>
                                <span class="font-bold text-[#173B2A]">{{ monitorCountAfterCreate }}</span>
                            </div>
                        </div>

                        <div class="mt-5 rounded-3xl border border-[#F6C66E] bg-[#FFFCF4] p-4">
                            <p class="text-sm font-bold text-[#173B2A]">За что платит клиент</p>
                            <ul class="mt-3 space-y-2 text-sm font-medium text-[#6A7A70]">
                                <li v-if="props.currentPlan?.code !== planCode" class="flex justify-between gap-3">
                                    <span>Тариф {{ displayPlan.name }}</span>
                                    <span class="font-bold text-[#173B2A]">{{ displayPlan.priceRub }} ₽/мес</span>
                                </li>
                                <li v-for="addon in selectedPaidAddons" :key="addon.type" class="flex justify-between gap-3">
                                    <span>{{ addon.title }}</span>
                                    <span class="font-bold text-[#E08600]">+{{ addon.priceRub }} ₽/мес</span>
                                </li>
                            </ul>
                        </div>

                        <div class="mt-5 rounded-3xl bg-[#173B2A] p-4 text-white">
                            <div class="flex items-end justify-between gap-3">
                                <span class="text-sm font-medium text-white/75">Будет списано сейчас</span>
                                <span class="text-2xl font-bold">{{ money(checkoutAmountRub) }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 border-t border-white/10 pt-3">
                                <span class="text-sm font-medium text-white/75">Со следующего месяца</span>
                                <span class="text-lg font-bold">{{ money(nextMonthlyRub) }}</span>
                            </div>
                        </div>

                        <div v-if="form.errors.monitors" class="mt-5 rounded-3xl border border-[#FECACA] bg-[#FEECEC] px-4 py-3 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.monitors }}
                        </div>

                        <button
                            type="submit"
                            class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-2xl bg-[#2FA568] px-5 text-sm font-bold text-white shadow-[0_14px_32px_rgba(47,165,104,0.22)] transition hover:bg-[#248653] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing || paymentProcessing"
                        >
                            <span v-if="form.processing || paymentProcessing">Переходим к оплате...</span>
                            <span v-else>Оплатить {{ checkoutAmountRub }} ₽ и добавить сайт</span>
                        </button>

                        <Link
                            href="/sites"
                            class="mt-3 inline-flex h-12 w-full items-center justify-center rounded-2xl border border-[#DDEBE3] bg-white px-5 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:bg-[#F6FBF8] hover:text-[#173B2A]"
                        >
                            Отмена
                        </Link>
                    </section>
                </aside>
            </div>
        </form>
    </DashboardLayout>
</template>
