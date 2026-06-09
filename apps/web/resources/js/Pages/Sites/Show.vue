<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import {
    Activity,
    AlertTriangle,
    CalendarClock,
    Check,
    ChevronDown,
    Clock3,
    FileText,
    Globe2,
    History,
    LoaderCircle,
    Pause,
    Plus,
    RotateCw,
    Settings,
    ShieldCheck,
    Trash2,
    X,
} from '@lucide/vue'
import FlashToast from '@/Components/FlashToast.vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { useAutoRefresh } from '../../Composables/useAutoRefresh'

type Organization = {
    id: string
    name: string
}

type PageProps = {
    flash?: {
        error?: string | null
    }
}

type Project = {
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

type LatestResult = {
    status: string
    checked_at: string | null
    response_time_ms: number | null
    status_code: number | null
    error_code: string | null
    error_message: string | null
    normalized_result: Record<string, unknown>
}

type Monitor = {
    id: string
    type: string
    name: string
    status: string
    is_enabled: boolean
    is_available: boolean
    interval_seconds: number | null
    timeout_ms: number | null
    settings: MonitorSettings | null
    expected: MonitorExpected | null
    last_check_at: string | null
    next_check_at: string | null
    check_in_progress_until: string | null
    is_checking: boolean
    last_success_at: string | null
    last_failure_at: string | null
    latest_result: LatestResult | null
}

type CheckResult = {
    id: number
    monitor_id: number | string
    check_type: string
    status: string
    checked_at: string | null
    response_time_ms: number | null
    status_code: number | null
    error_code: string | null
    error_message: string | null
    normalized_result: Record<string, unknown>
}

type Incident = {
    id: number
    monitor_id: number | string
    status: string
    severity: string
    title: string
    summary: string | null
    started_at: string | null
    resolved_at: string | null
    duration_seconds: number | null
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
    raw_status: string
    problem_label: string
    created_at: string | null
    updated_at: string | null
    project: Project | null
    monitors: Monitor[]
    recent_checks: CheckResult[]
    incidents: Incident[]
}

type MonitorDraft = {
    type: string
    name: string
    is_enabled: boolean
    interval_seconds: number
    timeout_ms: number
    method: string
    url: string
    follow_redirects: boolean
    verify_ssl: boolean
    domain: string
    port: number
    status_codes: string
    max_response_time_ms: number
    warning_days: string
    valid: boolean
    registered: boolean
}

const props = defineProps<{
    organization: Organization
    site: Site
}>()

const page = usePage<PageProps>()

useAutoRefresh({
    only: ['site'],
    intervalMs: 10000,
})

const expandedMonitorId = ref<string | null>(null)
const isDeleteModalOpen = ref(false)
const checkingMonitorIds = ref<string[]>([])
const checkingStartedFrom = ref<Record<string, string | null>>({})
const checkingTimeouts = ref<Record<string, ReturnType<typeof setTimeout>>>({})
const checkingSite = ref(false)
const checkingSiteStartedFrom = ref<string | null>(null)
const checkingSiteTimeout = ref<ReturnType<typeof setTimeout> | null>(null)
const monitorDrafts = ref<Record<string, MonitorDraft>>(
    Object.fromEntries(props.site.monitors.map((monitor) => [monitor.id, draftFromMonitor(monitor)])),
)

const enabledCount = computed(() => props.site.monitors.filter((monitor) => monitor.is_enabled).length)
const availableCount = computed(() => props.site.monitors.filter((monitor) => monitor.is_available).length)
const activeIncidentCount = computed(() => props.site.incidents.filter((incident) => incident.status === 'open').length)
const latestCheckAt = computed(() => {
    const dates = props.site.monitors
        .map((monitor) => monitor.last_check_at)
        .filter((value): value is string => Boolean(value))
        .sort()

    return dates.at(-1) ?? null
})
const successfulMonitorsCount = computed(() => props.site.monitors.filter((monitor) => monitorStatus(monitor) === 'ok').length)
const successRate = computed(() => {
    if (!props.site.monitors.length) return 0

    return Math.round((successfulMonitorsCount.value / props.site.monitors.length) * 100)
})
const averageResponse = computed(() => {
    const values = props.site.monitors
        .map((monitor) => monitor.latest_result?.response_time_ms)
        .filter((value): value is number => typeof value === 'number')

    if (!values.length) return null

    return Math.round(values.reduce((sum, value) => sum + value, 0) / values.length)
})
const sslDaysLeft = computed(() => {
    const values = props.site.monitors
        .map((monitor) => monitor.latest_result?.normalized_result.days_until_expiration)
        .filter((value): value is number => typeof value === 'number')

    return values.length ? Math.min(...values) : null
})
const isSiteChecking = computed(() => checkingSite.value || props.site.monitors.some((monitor) => monitor.is_checking))
const openIncident = computed(() => props.site.incidents.find((incident) => incident.status === 'open') ?? null)

watch(
    () => props.site.monitors,
    (monitors) => {
        const nextDrafts: Record<string, MonitorDraft> = {}

        monitors.forEach((monitor) => {
            nextDrafts[monitor.id] = expandedMonitorId.value === monitor.id && monitorDrafts.value[monitor.id]
                ? monitorDrafts.value[monitor.id]
                : draftFromMonitor(monitor)
        })

        monitorDrafts.value = nextDrafts
        checkingMonitorIds.value.forEach((id) => {
            const refreshedMonitor = monitors.find((monitor) => monitor.id === id)

            if (!refreshedMonitor || refreshedMonitor.last_check_at !== checkingStartedFrom.value[id]) {
                stopChecking(id)
            }
        })

        if (checkingSite.value && latestCheckAt.value !== checkingSiteStartedFrom.value) {
            stopSiteChecking()
        }
    },
)

onUnmounted(() => {
    Object.values(checkingTimeouts.value).forEach(clearTimeout)

    if (checkingSiteTimeout.value) {
        clearTimeout(checkingSiteTimeout.value)
    }
})

function draftFromMonitor(monitor: Monitor): MonitorDraft {
    const settings = monitor.settings ?? {}
    const expected = monitor.expected ?? {}

    return {
        type: monitor.type,
        name: monitor.name,
        is_enabled: monitor.is_enabled,
        interval_seconds: monitor.interval_seconds ?? (monitor.type === 'http' ? 300 : 86400),
        timeout_ms: monitor.timeout_ms ?? 10000,
        method: String(settings.method ?? 'GET'),
        url: String(settings.url ?? props.site.url),
        follow_redirects: Boolean(settings.follow_redirects ?? true),
        verify_ssl: Boolean(settings.verify_ssl ?? true),
        domain: String(settings.domain ?? props.site.host),
        port: Number(settings.port ?? props.site.port ?? 443),
        status_codes: Array.isArray(expected.status_codes) ? expected.status_codes.join(', ') : '200',
        max_response_time_ms: Number(expected.max_response_time_ms ?? 5000),
        warning_days: Array.isArray(settings.warning_days) ? settings.warning_days.join(', ') : '30, 14, 7, 3, 1',
        valid: Boolean(expected.valid ?? true),
        registered: Boolean(expected.registered ?? true),
    }
}

function statusLabel(status: string): string {
    if (status === 'ok' || status === 'success' || status === 'up') return 'Работает'
    if (status === 'down' || status === 'failure') return 'Ошибка'
    if (status === 'warning' || status === 'degraded') return 'Предупреждение'
    if (status === 'checking') return 'Проверяется'
    if (status === 'paused') return 'На паузе'
    if (status === 'empty') return 'Нет проверок'

    return 'Неизвестно'
}

function normalizedStatus(status: string): string {
    if (status === 'success' || status === 'up') return 'ok'
    if (status === 'failure') return 'down'
    if (status === 'degraded') return 'warning'

    return status
}

function statusClass(status: string): string {
    const normalized = normalizedStatus(status)

    if (normalized === 'ok') return 'bg-[#E9F8EF] text-[#159653]'
    if (normalized === 'down') return 'bg-[#FEECEC] text-[#E11D25]'
    if (normalized === 'warning') return 'bg-[#FFF7E8] text-[#D97706]'
    if (normalized === 'checking') return 'bg-[#E9F8EF] text-[#1E9B5D]'
    if (normalized === 'paused') return 'bg-[#ECEFF1] text-[#64706A]'

    return 'bg-[#F3F8F5] text-[#52645A]'
}

function statusIconBoxClass(status: string): string {
    const normalized = normalizedStatus(status)

    if (normalized === 'down') return 'border-[#FFC7C7] bg-[#FEECEC] text-[#E11D25] [animation:pulse_2.8s_cubic-bezier(0.4,0,0.6,1)_infinite]'
    if (normalized === 'warning') return 'border-[#F7D59A] bg-[#FFF7E8] text-[#D97706]'
    if (normalized === 'paused') return 'border-[#D7DDDA] bg-[#ECEFF1] text-[#64706A]'
    if (normalized === 'checking') return 'border-[#BFEBD0] bg-white text-[#24A869]'

    return 'border-[#BFEBD0] bg-[#E9F8EF] text-[#159653]'
}

function statusTextClass(status: string): string {
    const normalized = normalizedStatus(status)

    if (normalized === 'ok') return 'text-[#159653]'
    if (normalized === 'down') return 'text-[#E11D25]'
    if (normalized === 'warning') return 'text-[#D97706]'

    return 'text-[#6A7A70]'
}

function statusIcon(status: string): typeof Check {
    const normalized = normalizedStatus(status)

    if (normalized === 'ok') return Check
    if (normalized === 'down') return X
    if (normalized === 'warning') return AlertTriangle
    if (normalized === 'paused') return Pause

    return Activity
}

function monitorStatus(monitor: Monitor): string {
    if (!monitor.is_available) return 'paused'
    if (!monitor.is_enabled || monitor.status === 'paused') return 'paused'
    if (isChecking(monitor)) return 'checking'
    if (monitor.status === 'success' || monitor.status === 'up') return 'ok'
    if (monitor.status === 'failure' || monitor.status === 'down') return 'down'
    if (monitor.status === 'degraded' || monitor.status === 'warning' || monitor.latest_result?.status === 'warning') return 'warning'

    return 'unknown'
}

function typeLabel(type: string): string {
    return {
        http: 'Доступность сайта',
        ssl: 'SSL',
        domain: 'Домен',
        dns: 'DNS',
        robots_txt: 'Robots.txt',
        sitemap_xml: 'Sitemap.xml',
        tcp_port: 'TCP-порт',
        api_endpoint: 'API endpoint',
    }[type] ?? type.toUpperCase()
}

function shortTypeLabel(type: string): string {
    return {
        http: 'HTTP',
        ssl: 'SSL',
        domain: 'Domain',
        dns: 'DNS',
        robots_txt: 'Robots',
        sitemap_xml: 'Sitemap',
        tcp_port: 'TCP',
        api_endpoint: 'API',
    }[type] ?? type.toUpperCase()
}

function typeIcon(type: string): typeof Globe2 {
    if (type === 'ssl') return ShieldCheck
    if (type === 'domain' || type === 'dns') return Globe2
    if (type === 'robots_txt' || type === 'sitemap_xml') return FileText

    return Activity
}

function typeClass(type: string): string {
    if (type === 'http') return 'bg-[#E9F8EF] text-[#159653]'
    if (type === 'ssl') return 'bg-[#E9F8EF] text-[#159653]'
    if (type === 'domain') return 'bg-[#F3F8F5] text-[#52645A]'

    return 'bg-[#F3F8F5] text-[#52645A]'
}

function monitorCardClass(monitor: Monitor): string {
    if (!monitor.is_available) return 'border-[#D7DDDA] bg-[#F6F7F7]'

    const status = monitorStatus(monitor)

    if (status === 'down') return 'border-[#FFC7C7] bg-[#FFF8F8]'
    if (status === 'warning') return 'border-[#F7D59A] bg-[#FFFCF4]'
    if (status === 'checking') return 'border-[#BFEBD0] bg-white'

    return 'border-[#DDEBE3] bg-white'
}

function formatDate(value: string | null): string {
    if (!value) return 'еще не было'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function relativeDate(value: string | null): string {
    if (!value) return 'нет данных'

    const diffMinutes = Math.max(1, Math.round((Date.now() - new Date(value).getTime()) / 60000))

    if (diffMinutes < 60) return `${diffMinutes} мин назад`

    const diffHours = Math.round(diffMinutes / 60)

    if (diffHours < 24) return `${diffHours} ч назад`

    return formatDate(value)
}

function formatInterval(seconds: number | null): string {
    if (!seconds) return 'по умолчанию'
    if (seconds < 60) return `${seconds} сек`
    if (seconds < 3600) return `${Math.round(seconds / 60)} мин`
    if (seconds < 86400) return `${Math.round(seconds / 3600)} ч`

    return `${Math.round(seconds / 86400)} день`
}

function intervalMinutes(seconds: number): number {
    return Math.round(seconds / 60)
}

function setDraftIntervalMinutes(draft: MonitorDraft, minutes: number): void {
    draft.interval_seconds = minutes * 60
}

function formatDuration(seconds: number | null): string {
    if (!seconds) return '0 мин'
    if (seconds < 3600) return `${Math.max(1, Math.round(seconds / 60))} мин`
    if (seconds < 86400) return `${Math.round(seconds / 3600)} ч`

    return `${Math.round(seconds / 86400)} д`
}

function dayWord(days: number): string {
    const mod10 = days % 10
    const mod100 = days % 100

    if (mod10 === 1 && mod100 !== 11) return 'день'
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'дня'

    return 'дней'
}

function resultText(monitor: Monitor): string {
    const result = monitor.latest_result

    if (isChecking(monitor)) return 'Идет проверка'
    if (!monitor.is_available) return 'Недоступно на текущем тарифе'
    if (!result) return 'Нет результата'
    if (result.error_message) return result.error_message

    if (monitor.type === 'http') {
        const code = result.status_code ? `HTTP ${result.status_code}` : 'HTTP'
        const time = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${code}${time}`
    }

    const days = result.normalized_result.days_until_expiration

    if (typeof days === 'number') {
        return `${days} ${dayWord(days)} до истечения`
    }

    return statusLabel(result.status)
}

function checkResultText(result: CheckResult): string {
    if (result.error_message) return result.error_message
    if (result.check_type === 'http') {
        const code = result.status_code ? `HTTP ${result.status_code}` : 'HTTP'
        const time = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${code}${time}`
    }

    const days = result.normalized_result.days_until_expiration

    if (typeof days === 'number') return `${days} ${dayWord(days)}`

    return statusLabel(result.status)
}

function parseNumberList(value: string): number[] {
    return value
        .split(',')
        .map((item) => Number.parseInt(item.trim(), 10))
        .filter((item) => Number.isInteger(item))
}

function payloadForMonitor(monitor: Monitor) {
    const draft = monitorDrafts.value[monitor.id]

    if (draft.type === 'http') {
        return {
            type: draft.type,
            name: draft.name,
            is_enabled: draft.is_enabled,
            interval_seconds: draft.interval_seconds,
            timeout_ms: draft.timeout_ms,
            settings: {
                method: draft.method,
                url: draft.url,
                follow_redirects: draft.follow_redirects,
                verify_ssl: draft.verify_ssl,
            },
            expected: {
                status_codes: parseNumberList(draft.status_codes),
                max_response_time_ms: draft.max_response_time_ms,
            },
        }
    }

    if (draft.type === 'ssl') {
        return {
            type: draft.type,
            name: draft.name,
            is_enabled: draft.is_enabled,
            interval_seconds: draft.interval_seconds,
            timeout_ms: draft.timeout_ms,
            settings: {
                domain: draft.domain,
                port: draft.port,
                warning_days: parseNumberList(draft.warning_days),
            },
            expected: {
                valid: draft.valid,
            },
        }
    }

    return {
        type: draft.type,
        name: draft.name,
        is_enabled: draft.is_enabled,
        interval_seconds: draft.interval_seconds,
        timeout_ms: draft.timeout_ms,
        settings: {
            domain: draft.domain,
            warning_days: parseNumberList(draft.warning_days),
        },
        expected: {
            registered: draft.registered,
        },
    }
}

function toggleSettings(monitor: Monitor): void {
    if (!monitor.is_available) return

    expandedMonitorId.value = expandedMonitorId.value === monitor.id ? null : monitor.id
}

function saveMonitor(monitor: Monitor): void {
    if (!monitor.is_available) return

    router.put(`/sites/${props.site.id}/monitors/${monitor.id}`, payloadForMonitor(monitor), {
        preserveScroll: true,
    })
}

function toggleMonitor(monitor: Monitor): void {
    if (!monitor.is_available) return

    router.patch(`/sites/${props.site.id}/monitors/${monitor.id}/toggle`, {}, {
        preserveScroll: true,
    })
}

function isChecking(monitor: Monitor): boolean {
    return monitor.is_checking || checkingMonitorIds.value.includes(monitor.id)
}

function stopChecking(monitorId: string): void {
    checkingMonitorIds.value = checkingMonitorIds.value.filter((id) => id !== monitorId)

    const nextStartedFrom = { ...checkingStartedFrom.value }
    delete nextStartedFrom[monitorId]
    checkingStartedFrom.value = nextStartedFrom

    const timeout = checkingTimeouts.value[monitorId]

    if (timeout) {
        clearTimeout(timeout)
    }

    const nextTimeouts = { ...checkingTimeouts.value }
    delete nextTimeouts[monitorId]
    checkingTimeouts.value = nextTimeouts
}

function checkNow(monitor: Monitor): void {
    if (!monitor.is_enabled || isChecking(monitor)) {
        return
    }

    router.post(`/monitors/${monitor.id}/check-now`, {}, {
        preserveScroll: true,
        onStart: () => {
            checkingMonitorIds.value = [...checkingMonitorIds.value, monitor.id]
            checkingStartedFrom.value = {
                ...checkingStartedFrom.value,
                [monitor.id]: monitor.last_check_at,
            }
            checkingTimeouts.value = {
                ...checkingTimeouts.value,
                [monitor.id]: setTimeout(() => stopChecking(monitor.id), 30000),
            }
        },
        onError: () => stopChecking(monitor.id),
        onCancel: () => stopChecking(monitor.id),
    })
}

function checkSiteNow(): void {
    if (isSiteChecking.value) return

    router.post(`/sites/${props.site.id}/check-now`, {}, {
        preserveScroll: true,
        onStart: () => {
            checkingSite.value = true
            checkingSiteStartedFrom.value = latestCheckAt.value
            checkingSiteTimeout.value = setTimeout(stopSiteChecking, 30000)
        },
        onError: stopSiteChecking,
        onCancel: stopSiteChecking,
    })
}

function stopSiteChecking(): void {
    checkingSite.value = false
    checkingSiteStartedFrom.value = null

    if (checkingSiteTimeout.value) {
        clearTimeout(checkingSiteTimeout.value)
        checkingSiteTimeout.value = null
    }
}

function deleteSite(): void {
    router.delete(`/sites/${props.site.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleteModalOpen.value = false
        },
    })
}

function sparkBars(seed: string, status = props.site.status): number[] {
    const base = seed.split('').reduce((sum, char) => sum + char.charCodeAt(0), 0)

    return Array.from({ length: 12 }, (_, index) => 8 + ((base + index * 7) % 22))
}

function sparkClass(status: string): string {
    const normalized = normalizedStatus(status)

    if (normalized === 'down') return 'bg-[#EF6B6B]'
    if (normalized === 'warning') return 'bg-[#F3A83B]'
    if (normalized === 'paused') return 'bg-[#9AA5A0]'

    return 'bg-[#62C98F]'
}
</script>

<template>
    <Head :title="site.name" />
    <FlashToast :message="page.props.flash?.error" />

    <DashboardLayout
        :organization="organization"
        active-item="sites"
        :title="site.name"
        :subtitle="site.url"
        :usage-current="site.monitors.length"
    >
        <template #header-actions>
            <Link
                href="/sites"
                class="hidden h-10 items-center justify-center rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#52645A] transition hover:border-[#24A869] hover:text-[#173B2A] md:inline-flex"
            >
                К списку сайтов
            </Link>
        </template>

        <div class="mx-auto grid max-w-7xl gap-6 px-5 py-5 sm:px-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:py-6">
            <main class="min-w-0 space-y-6">
                <section class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div class="flex min-w-0 gap-4">
                            <span
                                class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border"
                                :class="isSiteChecking ? 'border-[#BFEBD0] bg-white text-[#24A869]' : statusIconBoxClass(site.status)"
                            >
                                <LoaderCircle v-if="isSiteChecking" class="h-5 w-5 animate-spin" :stroke-width="2.2" />
                                <component v-else :is="statusIcon(site.status)" class="h-5 w-5" :stroke-width="2.2" />
                            </span>

                            <div class="min-w-0">
                                <h1 class="truncate text-2xl font-semibold leading-tight text-[#17231C] sm:text-3xl">{{ site.name }}</h1>
                                <p class="mt-2 truncate text-sm text-[#6A7A70]">
                                    {{ site.url }}<span v-if="site.project"> · Проект: {{ site.project.name }}</span>
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-medium" :class="statusClass(isSiteChecking ? 'checking' : site.status)">
                                        <LoaderCircle v-if="isSiteChecking" class="mr-1.5 h-3.5 w-3.5 animate-spin" :stroke-width="2.2" />
                                        <component v-else :is="statusIcon(site.status)" class="mr-1.5 h-3.5 w-3.5" :stroke-width="2.2" />
                                        {{ statusLabel(isSiteChecking ? 'checking' : site.status) }}
                                    </span>
                                    <span class="text-xs font-medium" :class="statusTextClass(site.status)">
                                        {{ site.problem_label }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#2FA568] px-4 text-sm font-medium text-white transition hover:bg-[#278C58] disabled:cursor-not-allowed disabled:opacity-70"
                                :disabled="isSiteChecking"
                                @click="checkSiteNow"
                            >
                                <LoaderCircle v-if="isSiteChecking" class="h-4 w-4 animate-spin" :stroke-width="2" />
                                <RotateCw v-else class="h-4 w-4" :stroke-width="2" />
                                Запустить проверку
                            </button>
                            <Link
                                :href="`/sites/${site.id}/monitors/create`"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]"
                            >
                                <Plus class="h-4 w-4" :stroke-width="2" />
                                Добавить проверку
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="site.status === 'down' || site.status === 'warning'"
                        class="mt-5 flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-start sm:justify-between"
                        :class="site.status === 'down' ? 'border-[#FFB8B8] bg-[#FFF4F4]' : 'border-[#F7D59A] bg-[#FFFCF4]'"
                    >
                        <div class="flex gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" :class="site.status === 'down' ? 'bg-[#FEECEC] text-[#E11D25]' : 'bg-[#FFF7E8] text-[#D97706]'">
                                <AlertTriangle class="h-4 w-4" :stroke-width="2" />
                            </span>
                            <div>
                                <h2 class="text-base font-semibold" :class="site.status === 'down' ? 'text-[#E11D25]' : 'text-[#D97706]'">
                                    {{ site.status === 'down' ? 'Есть активная проблема' : 'Есть предупреждение' }}
                                </h2>
                                <p class="mt-1 text-sm leading-6 text-[#6A7A70]">{{ site.problem_label }}</p>
                            </div>
                        </div>
                        <a
                            v-if="openIncident"
                            href="#incidents"
                            class="inline-flex h-10 items-center justify-center rounded-xl bg-[#E11D25] px-4 text-sm font-medium text-white transition hover:bg-[#C9151C]"
                        >
                            Открыть инцидент
                        </a>
                    </div>
                </section>

                <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                        <p class="text-2xl font-semibold" :class="statusTextClass(site.status)">{{ successRate }}%</p>
                        <p class="mt-2 text-sm font-medium text-[#26332D]">Успешность</p>
                        <p class="mt-1 text-xs text-[#6A7A70]">по мониторингам</p>
                        <div class="mt-3 flex h-7 items-end gap-1">
                            <span v-for="(height, index) in sparkBars('rate')" :key="index" class="w-1.5 rounded-t-full" :class="sparkClass(site.status)" :style="{ height: `${height}px` }"></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                        <p class="text-2xl font-semibold text-[#26332D]">{{ averageResponse === null ? '-' : `${averageResponse} мс` }}</p>
                        <p class="mt-2 text-sm font-medium text-[#26332D]">Ответ</p>
                        <p class="mt-1 text-xs text-[#6A7A70]">среднее</p>
                        <div class="mt-3 flex h-7 items-end gap-1">
                            <span v-for="(height, index) in sparkBars('response')" :key="index" class="w-1.5 rounded-t-full bg-[#62C98F]" :style="{ height: `${height}px` }"></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                        <p class="text-2xl font-semibold" :class="successfulMonitorsCount === site.monitors.length ? 'text-[#159653]' : 'text-[#E11D25]'">{{ successfulMonitorsCount }} из {{ site.monitors.length }}</p>
                        <p class="mt-2 text-sm font-medium text-[#26332D]">Проверки</p>
                        <p class="mt-1 text-xs text-[#6A7A70]">успешно</p>
                        <div class="mt-3 flex h-7 items-end gap-1">
                            <span v-for="(height, index) in sparkBars('checks')" :key="index" class="w-1.5 rounded-t-full" :class="sparkClass(site.status)" :style="{ height: `${height}px` }"></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                        <p class="text-2xl font-semibold" :class="activeIncidentCount ? 'text-[#E11D25]' : 'text-[#159653]'">{{ activeIncidentCount }}</p>
                        <p class="mt-2 text-sm font-medium text-[#26332D]">Инциденты</p>
                        <p class="mt-1 text-xs text-[#6A7A70]">активные</p>
                        <div class="mt-3 flex h-7 items-end gap-1">
                            <span v-for="(height, index) in sparkBars('incidents')" :key="index" class="w-1.5 rounded-t-full" :class="activeIncidentCount ? 'bg-[#EF6B6B]' : 'bg-[#62C98F]'" :style="{ height: `${height}px` }"></span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                        <p class="text-2xl font-semibold text-[#159653]">{{ sslDaysLeft === null ? '-' : `${sslDaysLeft} дн.` }}</p>
                        <p class="mt-2 text-sm font-medium text-[#26332D]">SSL / домен</p>
                        <p class="mt-1 text-xs text-[#6A7A70]">ближайший срок</p>
                        <div class="mt-3 flex h-7 items-end gap-1">
                            <span v-for="(height, index) in sparkBars('ssl')" :key="index" class="w-1.5 rounded-t-full bg-[#62C98F]" :style="{ height: `${height}px` }"></span>
                        </div>
                    </article>
                </section>

                <section>
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold text-[#17231C]">Проверки</h2>
                        <p class="mt-1 text-sm text-[#6A7A70]">Базовые и дополнительные мониторинги этого сайта.</p>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <article
                            v-for="monitor in site.monitors"
                            :key="monitor.id"
                            class="relative overflow-hidden rounded-3xl border p-4 shadow-[0_10px_28px_rgba(31,68,49,0.05)]"
                            :class="monitorCardClass(monitor)"
                        >
                            <div :class="!monitor.is_available ? 'pointer-events-none select-none opacity-45 grayscale' : ''">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 gap-3">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl border" :class="statusIconBoxClass(monitorStatus(monitor))">
                                            <LoaderCircle v-if="isChecking(monitor)" class="h-5 w-5 animate-spin" :stroke-width="2.2" />
                                            <component v-else :is="typeIcon(monitor.type)" class="h-5 w-5" :stroke-width="2.2" />
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-semibold text-[#17231C]">{{ typeLabel(monitor.type) }}</h3>
                                            <span class="mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-medium" :class="statusClass(monitorStatus(monitor))">
                                                <LoaderCircle v-if="isChecking(monitor)" class="mr-1.5 h-3.5 w-3.5 animate-spin" :stroke-width="2.2" />
                                                <component v-else :is="statusIcon(monitorStatus(monitor))" class="mr-1.5 h-3.5 w-3.5" :stroke-width="2.2" />
                                                {{ statusLabel(monitorStatus(monitor)) }}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[#D4E3DA] text-[#52645A] transition hover:border-[#24A869] hover:text-[#1E9B5D]"
                                        :disabled="!monitor.is_available"
                                        @click="toggleSettings(monitor)"
                                    >
                                        <ChevronDown class="h-4 w-4 transition" :class="expandedMonitorId === monitor.id ? 'rotate-180' : ''" :stroke-width="2" />
                                    </button>
                                </div>

                                <p class="mt-4 min-h-6 text-sm font-medium" :class="statusTextClass(monitorStatus(monitor))">{{ resultText(monitor) }}</p>
                                <div class="mt-3 grid gap-2 text-xs text-[#6A7A70]">
                                    <p>Интервал: {{ formatInterval(monitor.interval_seconds) }}</p>
                                    <p>Последняя проверка: {{ relativeDate(monitor.last_check_at) }}</p>
                                    <p v-if="monitor.next_check_at">Следующая: {{ formatDate(monitor.next_check_at) }}</p>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#2FA568] px-4 text-sm font-medium text-white transition hover:bg-[#278C58] disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!monitor.is_enabled || isChecking(monitor)"
                                        @click="checkNow(monitor)"
                                    >
                                        <LoaderCircle v-if="isChecking(monitor)" class="h-4 w-4 animate-spin" :stroke-width="2" />
                                        <RotateCw v-else class="h-4 w-4" :stroke-width="2" />
                                        Проверить
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]"
                                        :disabled="!monitor.is_available"
                                        @click="toggleMonitor(monitor)"
                                    >
                                        <Pause class="h-4 w-4" :stroke-width="2" />
                                        {{ monitor.is_enabled ? 'Пауза' : 'Включить' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="grid h-10 w-10 place-items-center rounded-xl border border-[#D4E3DA] bg-white text-[#52645A] transition hover:border-[#24A869] hover:text-[#1E9B5D]"
                                        :disabled="!monitor.is_available"
                                        @click="toggleSettings(monitor)"
                                    >
                                        <Settings class="h-4 w-4" :stroke-width="2" />
                                    </button>
                                </div>

                                <form
                                    v-if="expandedMonitorId === monitor.id"
                                    class="mt-5 rounded-2xl border border-[#DDEBE3] bg-[#FBFDFC] p-4"
                                    @submit.prevent="saveMonitor(monitor)"
                                >
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <label class="block">
                                            <span class="mb-2 block text-xs font-medium text-[#52645A]">Название</span>
                                            <input v-model="monitorDrafts[monitor.id].name" type="text" required class="h-10 w-full rounded-xl border border-[#D4E3DA] bg-white px-3 text-sm outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15">
                                        </label>
                                        <label class="block">
                                            <span class="mb-2 block text-xs font-medium text-[#52645A]">Таймаут, мс</span>
                                            <input v-model.number="monitorDrafts[monitor.id].timeout_ms" type="number" min="1000" max="60000" required class="h-10 w-full rounded-xl border border-[#D4E3DA] bg-white px-3 text-sm outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15">
                                        </label>
                                        <label class="block md:col-span-2">
                                            <span class="mb-2 block text-xs font-medium text-[#52645A]">Интервал: {{ formatInterval(monitorDrafts[monitor.id].interval_seconds) }}</span>
                                            <input
                                                :value="intervalMinutes(monitorDrafts[monitor.id].interval_seconds)"
                                                type="range"
                                                min="5"
                                                max="1440"
                                                step="1"
                                                class="w-full accent-[#2FA568]"
                                                @input="setDraftIntervalMinutes(monitorDrafts[monitor.id], Number(($event.target as HTMLInputElement).value))"
                                            >
                                        </label>

                                        <template v-if="monitor.type === 'http'">
                                            <label class="block">
                                                <span class="mb-2 block text-xs font-medium text-[#52645A]">Метод</span>
                                                <select v-model="monitorDrafts[monitor.id].method" class="h-10 w-full rounded-xl border border-[#D4E3DA] bg-white px-3 text-sm outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15">
                                                    <option value="GET">GET</option>
                                                    <option value="HEAD">HEAD</option>
                                                    <option value="POST">POST</option>
                                                </select>
                                            </label>
                                            <label class="block">
                                                <span class="mb-2 block text-xs font-medium text-[#52645A]">Ожидаемые коды</span>
                                                <input v-model="monitorDrafts[monitor.id].status_codes" type="text" required class="h-10 w-full rounded-xl border border-[#D4E3DA] bg-white px-3 text-sm outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15">
                                            </label>
                                            <label class="block md:col-span-2">
                                                <span class="mb-2 block text-xs font-medium text-[#52645A]">URL</span>
                                                <input v-model="monitorDrafts[monitor.id].url" type="url" required class="h-10 w-full rounded-xl border border-[#D4E3DA] bg-white px-3 text-sm outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15">
                                            </label>
                                        </template>

                                        <template v-if="monitor.type === 'ssl' || monitor.type === 'domain'">
                                            <label class="block">
                                                <span class="mb-2 block text-xs font-medium text-[#52645A]">Домен</span>
                                                <input v-model="monitorDrafts[monitor.id].domain" type="text" required class="h-10 w-full rounded-xl border border-[#D4E3DA] bg-white px-3 text-sm outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15">
                                            </label>
                                            <label v-if="monitor.type === 'ssl'" class="block">
                                                <span class="mb-2 block text-xs font-medium text-[#52645A]">Порт</span>
                                                <input v-model.number="monitorDrafts[monitor.id].port" type="number" min="1" max="65535" required class="h-10 w-full rounded-xl border border-[#D4E3DA] bg-white px-3 text-sm outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15">
                                            </label>
                                            <label class="block md:col-span-2">
                                                <span class="mb-2 block text-xs font-medium text-[#52645A]">Дни предупреждений</span>
                                                <input v-model="monitorDrafts[monitor.id].warning_days" type="text" required class="h-10 w-full rounded-xl border border-[#D4E3DA] bg-white px-3 text-sm outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15">
                                            </label>
                                        </template>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-3 rounded-xl border border-[#D4E3DA] bg-white px-3 py-2 text-sm font-medium text-[#26332D]"
                                            @click="monitorDrafts[monitor.id].is_enabled = !monitorDrafts[monitor.id].is_enabled"
                                        >
                                            <span class="flex h-5 w-9 items-center rounded-full p-0.5 transition" :class="monitorDrafts[monitor.id].is_enabled ? 'justify-end bg-[#2FA568]' : 'justify-start bg-[#AAB6AF]'">
                                                <span class="h-4 w-4 rounded-full bg-white shadow-sm"></span>
                                            </span>
                                            {{ monitorDrafts[monitor.id].is_enabled ? 'Включен' : 'На паузе' }}
                                        </button>
                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-[#2FA568] px-4 text-sm font-medium text-white transition hover:bg-[#278C58]">
                                            Сохранить
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div
                                v-if="!monitor.is_available"
                                class="absolute inset-0 flex items-center justify-center px-6 text-center"
                                aria-hidden="true"
                            >
                                <p class="rounded-xl bg-white/90 px-5 py-3 text-sm font-medium text-[#52645A] shadow-sm ring-1 ring-[#DDEBE3]">
                                    Доступно на другом тарифе
                                </p>
                            </div>
                        </article>
                    </div>
                </section>

                <section id="incidents" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="overflow-hidden rounded-3xl border border-[#DDEBE3] bg-white shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                        <div class="flex items-start justify-between gap-3 border-b border-[#DDEBE3] p-5">
                            <div>
                                <h2 class="text-xl font-semibold text-[#17231C]">История проверок</h2>
                                <p class="mt-1 text-sm text-[#6A7A70]">Последние технические результаты от poller.</p>
                            </div>
                            <History class="h-5 w-5 text-[#8A9A91]" :stroke-width="2" />
                        </div>

                        <div v-if="site.recent_checks.length" class="overflow-x-auto">
                            <table class="w-full min-w-[720px] border-separate border-spacing-0 text-left text-sm">
                                <thead class="bg-[#FBFDFC] text-xs font-semibold text-[#6A7A70]">
                                    <tr>
                                        <th class="px-5 py-4">Время</th>
                                        <th class="px-5 py-4">Тип</th>
                                        <th class="px-5 py-4">Статус</th>
                                        <th class="px-5 py-4">Результат</th>
                                        <th class="px-5 py-4">Ответ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="result in site.recent_checks" :key="result.id">
                                        <td class="whitespace-nowrap border-t border-[#DDEBE3] px-5 py-4 text-[#6A7A70]">{{ formatDate(result.checked_at) }}</td>
                                        <td class="border-t border-[#DDEBE3] px-5 py-4">
                                            <span class="rounded-full px-3 py-1 text-xs font-medium" :class="typeClass(result.check_type)">
                                                {{ shortTypeLabel(result.check_type) }}
                                            </span>
                                        </td>
                                        <td class="border-t border-[#DDEBE3] px-5 py-4">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium" :class="statusClass(result.status)">
                                                <component :is="statusIcon(result.status)" class="mr-1.5 h-3.5 w-3.5" :stroke-width="2.2" />
                                                {{ statusLabel(result.status) }}
                                            </span>
                                        </td>
                                        <td class="max-w-80 truncate border-t border-[#DDEBE3] px-5 py-4 font-medium text-[#26332D]">{{ checkResultText(result) }}</td>
                                        <td class="border-t border-[#DDEBE3] px-5 py-4 text-[#6A7A70]">{{ result.response_time_ms === null ? '-' : `${result.response_time_ms} мс` }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="p-8 text-center text-sm font-medium text-[#6A7A70]">
                            Проверок еще не было.
                        </div>
                    </div>

                    <aside class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                        <h2 class="text-xl font-semibold text-[#17231C]">Инциденты</h2>
                        <p class="mt-1 text-sm text-[#6A7A70]">Падения и восстановления сайта.</p>

                        <div v-if="site.incidents.length" class="mt-5 grid gap-3">
                            <article
                                v-for="incident in site.incidents"
                                :key="incident.id"
                                class="rounded-2xl border p-4"
                                :class="incident.status === 'open' ? 'border-[#FFB8B8] bg-[#FFF4F4]' : 'border-[#DDEBE3] bg-[#FBFDFC]'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-[#17231C]">{{ incident.title }}</h3>
                                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium" :class="incident.status === 'open' ? 'bg-[#FEECEC] text-[#E11D25]' : 'bg-[#E9F8EF] text-[#159653]'">
                                        {{ incident.status === 'open' ? 'Активный' : 'Закрыт' }}
                                    </span>
                                </div>
                                <p v-if="incident.summary" class="mt-2 text-sm leading-6 text-[#6A7A70]">{{ incident.summary }}</p>
                                <p class="mt-3 text-xs font-medium text-[#6A7A70]">
                                    {{ formatDate(incident.started_at) }} · {{ formatDuration(incident.duration_seconds) }}
                                </p>
                            </article>
                        </div>

                        <div v-else class="mt-5 rounded-2xl border border-dashed border-[#DDEBE3] p-6 text-center text-sm font-medium text-[#6A7A70]">
                            Инцидентов пока нет.
                        </div>
                    </aside>
                </section>
            </main>

            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <section class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                    <h2 class="text-lg font-semibold text-[#17231C]">Статус сайта</h2>
                    <span class="mt-4 inline-flex items-center rounded-full px-3 py-1.5 text-xs font-medium" :class="statusClass(isSiteChecking ? 'checking' : site.status)">
                        <LoaderCircle v-if="isSiteChecking" class="mr-1.5 h-3.5 w-3.5 animate-spin" :stroke-width="2.2" />
                        <component v-else :is="statusIcon(site.status)" class="mr-1.5 h-3.5 w-3.5" :stroke-width="2.2" />
                        {{ statusLabel(isSiteChecking ? 'checking' : site.status) }}
                    </span>
                    <div class="mt-4 grid gap-3 text-sm">
                        <p class="flex items-center gap-2 text-[#6A7A70]">
                            <Clock3 class="h-4 w-4" :stroke-width="2" />
                            Последняя: {{ relativeDate(latestCheckAt) }}
                        </p>
                        <p class="flex items-center gap-2 text-[#6A7A70]">
                            <ShieldCheck class="h-4 w-4" :stroke-width="2" />
                            Доступно проверок: {{ availableCount }} / {{ site.monitors.length }}
                        </p>
                        <p class="flex items-center gap-2 text-[#6A7A70]">
                            <CalendarClock class="h-4 w-4" :stroke-width="2" />
                            Создан: {{ formatDate(site.created_at) }}
                        </p>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                    <h2 class="text-lg font-semibold text-[#17231C]">Быстрые действия</h2>
                    <div class="mt-4 grid gap-2">
                        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#2FA568] px-4 text-sm font-medium text-white transition hover:bg-[#278C58]" :disabled="isSiteChecking" @click="checkSiteNow">
                            <LoaderCircle v-if="isSiteChecking" class="h-4 w-4 animate-spin" :stroke-width="2" />
                            <RotateCw v-else class="h-4 w-4" :stroke-width="2" />
                            Проверить сайт
                        </button>
                        <Link :href="`/sites/${site.id}/monitors/create`" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]">
                            <Plus class="h-4 w-4" :stroke-width="2" />
                            Добавить проверку
                        </Link>
                        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]">
                            <Bell class="h-4 w-4" :stroke-width="2" />
                            Уведомления
                        </button>
                        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]">
                            <FileText class="h-4 w-4" :stroke-width="2" />
                            Создать отчет
                        </button>
                        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#FECACA] bg-white px-4 text-sm font-medium text-[#E11D25] transition hover:bg-[#FFF4F4]" @click="isDeleteModalOpen = true">
                            <Trash2 class="h-4 w-4" :stroke-width="2" />
                            Удалить сайт
                        </button>
                    </div>
                </section>
            </aside>
        </div>

        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-[#0B1220]/55 px-5 py-8 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-site-title"
            @click.self="isDeleteModalOpen = false"
        >
            <section class="w-full max-w-lg overflow-hidden rounded-3xl border border-[#FECACA] bg-white shadow-[0_24px_80px_rgba(15,23,42,0.22)]">
                <div class="border-b border-[#FECACA] bg-[#FFF8F8] p-5">
                    <div class="flex items-start gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#FEECEC] text-[#EF4444]">
                            <Trash2 class="h-5 w-5" :stroke-width="2" />
                        </span>
                        <div>
                            <h2 id="delete-site-title" class="text-xl font-semibold text-[#17231C]">Удалить сайт?</h2>
                            <p class="mt-1 text-sm leading-6 text-[#6A7A70]">
                                Сайт {{ site.name }} будет скрыт из кабинета, а его мониторинги будут отправлены в архив.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-5">
                    <div class="rounded-2xl border border-[#DDEBE3] bg-[#FBFDFC] p-4">
                        <p class="text-xs font-medium uppercase text-[#8A9A91]">Сайт</p>
                        <p class="mt-2 truncate text-sm font-semibold text-[#17231C]">{{ site.name }}</p>
                        <p class="mt-1 truncate text-xs font-medium text-[#6A7A70]">{{ site.url }}</p>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-[#6A7A70]">
                        История проверок и инциденты останутся в системе для внутреннего учета.
                    </p>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-[#DDEBE3] bg-white p-5 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-xl border border-[#D4E3DA] bg-white px-5 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]"
                        @click="isDeleteModalOpen = false"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-xl bg-[#E11D25] px-5 text-sm font-medium text-white transition hover:bg-[#C9151C]"
                        @click="deleteSite"
                    >
                        Удалить сайт
                    </button>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
