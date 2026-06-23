<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js'
import {
    Activity,
    AlertTriangle,
    CalendarClock,
    Check,
    ChevronDown,
    Clock3,
    Crown,
    FileText,
    Globe2,
    History,
    LoaderCircle,
    Pause,
    Play,
    RotateCw,
    Settings,
    ShieldCheck,
    Trash2,
    X,
} from '@lucide/vue'
import { Line } from 'vue-chartjs'
import CheckIntervalControl from '@/Components/CheckIntervalControl.vue'
import TariffRestriction from '@/Components/TariffRestriction.vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { useAutoRefresh } from '../../Composables/useAutoRefresh'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

type Organization = {
    id: string
    name: string
}

type Project = {
    id: string
    name: string
}

type MonitorTypeOption = {
    value: string
    code: string
    label: string
    name: string
    description: string
    is_paid: boolean
    sort_order: number
    default_interval_seconds: number
    ui_meta?: {
        title?: string
    }
}

type Usage = {
    monitors: number
    monitor_limit: number | null
    minimum_check_interval_seconds: number | null
    allowed_monitor_types: string[] | null
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
    is_paid_addon: boolean
    is_configured: boolean
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

type AvailabilityResponseChartPoint = {
    date: string
    label: string
    average_response_time_ms: number | null
    sample_count: number
}

type AvailabilityResponseChart = {
    points: AvailabilityResponseChartPoint[]
    has_data: boolean
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
    history_retention_days: number
    availability_response_chart: AvailabilityResponseChart
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
    host: string
    record_types: string
    nameservers: string
    exists: boolean
    valid_xml: boolean
    resolves: boolean
    min_records: number
    warn_on_change: boolean
    open: boolean
    headers: string
    body: string
    response_contains: string
}

const props = defineProps<{
    organization: Organization
    site: Site
    monitorTypes: MonitorTypeOption[]
    usage: Usage
}>()

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
const minimumIntervalMinutes = computed(() => Math.max(1, Math.ceil((props.usage.minimum_check_interval_seconds ?? 300) / 60)))

const activeMonitors = computed(() => props.site.monitors.filter((monitor) => monitor.is_configured && monitor.is_available && monitor.is_enabled))
const enabledCount = computed(() => activeMonitors.value.length)
const inactiveCount = computed(() => props.site.monitors.filter((monitor) => monitor.is_configured && monitor.is_available && !monitor.is_enabled).length)
const availableCount = computed(() => props.site.monitors.filter((monitor) => monitor.is_available).length)
const unavailableCount = computed(() => props.site.monitors.length - availableCount.value)
const isMonitorLimitReached = computed(() => props.usage.monitor_limit !== null && props.usage.monitors >= props.usage.monitor_limit)
const activeIncidentCount = computed(() => props.site.incidents.filter((incident) => incident.status === 'open').length)
const latestCheckAt = computed(() => {
    const dates = props.site.monitors
        .map((monitor) => monitor.last_check_at)
        .filter((value): value is string => Boolean(value))
        .sort()

    return dates.at(-1) ?? null
})
const successfulMonitorsCount = computed(() => activeMonitors.value.filter((monitor) => monitorStatus(monitor) === 'ok').length)
const successRate = computed(() => {
    if (!activeMonitors.value.length) return 0

    return Math.round((successfulMonitorsCount.value / activeMonitors.value.length) * 100)
})
const averageResponse = computed(() => {
    const values = activeMonitors.value
        .map((monitor) => monitor.latest_result?.response_time_ms)
        .filter((value): value is number => typeof value === 'number')

    if (!values.length) return null

    return Math.round(values.reduce((sum, value) => sum + value, 0) / values.length)
})
const historyRetentionDays = computed(() => props.site.history_retention_days)
const availabilityChartPoints = computed(() => props.site.availability_response_chart.points)
const hasAvailabilityTrendData = computed(() => props.site.availability_response_chart.has_data)
const availabilityChartData = computed(() => ({
    labels: availabilityChartPoints.value.map((point) => point.label),
    datasets: [
        {
            label: 'Средняя скорость загрузки, мс',
            data: availabilityChartPoints.value.map((point) => point.average_response_time_ms),
            borderColor: '#2FA568',
            backgroundColor: 'rgba(47, 165, 104, 0.12)',
            pointBackgroundColor: '#2FA568',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 5,
            tension: 0.35,
            fill: true,
            spanGaps: true,
        },
    ],
}))
const availabilityChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index' as const,
        intersect: false,
    },
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            displayColors: false,
            callbacks: {
                title: (items: Array<{ dataIndex: number }>) => {
                    const point = availabilityChartPoints.value[items[0]?.dataIndex ?? 0]

                    return point ? formatChartPointDate(point.date) : ''
                },
                label: (context: { dataIndex: number; parsed: { y: number | null } }) => {
                    const point = availabilityChartPoints.value[context.dataIndex]
                    const value = context.parsed.y

                    if (value === null || value === undefined) {
                        return 'Нет данных за этот день'
                    }

                    return `Среднее: ${Math.round(value)} мс · ${point.sample_count} ${pluralizeChecks(point.sample_count)}`
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                color: '#6A7A70',
                maxRotation: 0,
                autoSkipPadding: 20,
            },
            border: {
                display: false,
            },
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: '#6A7A70',
                callback: (value: string | number) => `${value} мс`,
            },
            grid: {
                color: 'rgba(221, 235, 227, 0.9)',
                drawBorder: false,
            },
            border: {
                display: false,
            },
        },
    },
}))
const sslDaysLeft = computed(() => {
    const values = activeMonitors.value
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
        host: String(settings.host ?? props.site.host),
        record_types: Array.isArray(settings.record_types) ? settings.record_types.join(', ') : 'A, AAAA',
        nameservers: Array.isArray(settings.nameservers) ? settings.nameservers.join(', ') : '',
        exists: Boolean(expected.exists ?? true),
        valid_xml: Boolean(expected.valid_xml ?? true),
        resolves: Boolean(expected.resolves ?? true),
        min_records: Number(expected.min_records ?? 1),
        warn_on_change: Boolean(settings.warn_on_change ?? false),
        open: Boolean(expected.open ?? true),
        headers: settings.headers && typeof settings.headers === 'object'
            ? Object.entries(settings.headers as Record<string, unknown>).map(([key, value]) => `${key}: ${String(value)}`).join('\n')
            : '',
        body: String(settings.body ?? ''),
        response_contains: String(expected.response_contains ?? ''),
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

function monitorActivityLabel(monitor: Monitor): string {
    return monitor.is_enabled ? 'Активна' : 'На паузе'
}

function monitorActivityClass(monitor: Monitor): string {
    if (!monitor.is_enabled) return 'border-[#D7E4DC] bg-[#F1F7F3] text-[#6E8075]'

    return 'border-[#BFEBD0] bg-[#E9F8EF] text-[#159653]'
}

function monitorAccessLabel(monitor: Monitor): string {
    return monitor.is_available ? 'В тарифе' : 'Недоступно на тарифе'
}

function monitorAccessClass(monitor: Monitor): string {
    return monitor.is_available
        ? 'border-[#D7E4DC] bg-white text-[#52645A]'
        : 'border-[#D7DDDA] bg-[#ECEFF1] text-[#64706A]'
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

function typeDefinition(type: string): MonitorTypeOption | undefined {
    return props.monitorTypes.find((item) => item.code === type || item.value === type)
}

function typeLabel(type: string): string {
    const definition = typeDefinition(type)

    return definition?.ui_meta?.title ?? definition?.name ?? type.toUpperCase()
}

function typeDescription(type: string): string {
    return typeDefinition(type)?.description ?? ''
}

function shortTypeLabel(type: string): string {
    return typeDefinition(type)?.label ?? type.toUpperCase()
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
    if (!monitor.is_available) return 'border-[#D9DEDB] bg-[#F1F4F2]'
    if (!monitor.is_enabled) return 'border-[#D7E4DC] bg-[#F5FAF7]'

    const status = monitorStatus(monitor)

    if (status === 'down') return 'border-[#FFC7C7] bg-[#FFF8F8]'
    if (status === 'warning') return 'border-[#F7D59A] bg-[#FFFCF4]'
    if (status === 'checking') return 'border-[#BFEBD0] bg-white'

    return 'border-[#DDEBE3] bg-white'
}

function settingsPanelClass(_monitor: Monitor): string {
    return 'mt-5 grid gap-4 border-t border-[#DDEBE3] pt-4 md:grid-cols-2'
}

function settingsInputClass(_monitor: Monitor): string {
    return 'h-11 w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15'
}

function settingsTextareaClass(_monitor: Monitor): string {
    return 'w-full rounded-2xl border border-[#CFE1D7] bg-white px-4 py-3 text-sm outline-none focus:border-[#2FA568] focus:ring-4 focus:ring-[#2FA568]/15'
}

function settingsAccentClass(_monitor: Monitor): string {
    return 'text-[#1E9B5D]'
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

function formatChartPointDate(value: string): string {
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'long',
    }).format(new Date(`${value}T00:00:00`))
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

function intervalText(seconds: number): string {
    const minutes = intervalMinutes(seconds)

    if (minutes === 60) return 'Каждый час'
    if (minutes === 1440) return 'Раз в день'
    if (minutes > 60 && minutes % 60 === 0) return `Каждые ${minutes / 60} ч`

    return `Каждые ${minutes} мин`
}

function intervalMinutes(seconds: number): number {
    return Math.round(seconds / 60)
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

function pluralizeChecks(count: number): string {
    const mod10 = count % 10
    const mod100 = count % 100

    if (mod10 === 1 && mod100 !== 11) return 'проверка'
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'проверки'

    return 'проверок'
}

function resultText(monitor: Monitor): string {
    const result = monitor.latest_result

    if (isChecking(monitor)) return 'Идет проверка'
    if (!monitor.is_configured && monitor.is_paid_addon) return 'Платная проверка еще не добавлена'
    if (!monitor.is_configured) return 'Проверка еще не настроена'
    if (!monitor.is_available) return 'Недоступно на текущем тарифе'
    if (!monitor.is_enabled) return 'Проверка выключена и не запускается по расписанию'
    if (!result) return 'Нет результата'
    if (result.error_message) return result.error_message

    if (['http', 'api_endpoint', 'robots_txt', 'sitemap_xml'].includes(monitor.type)) {
        const code = result.status_code ? `HTTP ${result.status_code}` : 'HTTP'
        const time = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${code}${time}`
    }

    if (monitor.type === 'dns') {
        const records = result.normalized_result.records
        const count = Array.isArray(records) ? records.length : 0

        return count ? `${count} DNS записей` : statusLabel(result.status)
    }

    if (monitor.type === 'tcp_port') {
        const open = result.normalized_result.open === true ? 'порт открыт' : 'порт закрыт'
        const time = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${open}${time}`
    }

    const days = result.normalized_result.days_until_expiration

    if (typeof days === 'number') {
        return `${days} ${dayWord(days)} до истечения`
    }

    return statusLabel(result.status)
}

function checkResultText(result: CheckResult): string {
    if (result.error_message) return result.error_message
    if (['http', 'api_endpoint', 'robots_txt', 'sitemap_xml'].includes(result.check_type)) {
        const code = result.status_code ? `HTTP ${result.status_code}` : 'HTTP'
        const time = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${code}${time}`
    }

    if (result.check_type === 'dns') {
        const records = result.normalized_result.records
        const count = Array.isArray(records) ? records.length : 0

        return count ? `${count} DNS записей` : statusLabel(result.status)
    }

    if (result.check_type === 'tcp_port') {
        const open = result.normalized_result.open === true ? 'порт открыт' : 'порт закрыт'
        const time = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${open}${time}`
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

function payloadForMonitor(monitor: Monitor) {
    const draft = monitorDrafts.value[monitor.id]
    const basePayload = {
        type: draft.type,
        name: draft.name,
        is_enabled: draft.is_enabled,
        interval_seconds: draft.interval_seconds,
        timeout_ms: draft.timeout_ms,
    }

    if (draft.type === 'http') {
        return {
            ...basePayload,
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

    if (draft.type === 'api_endpoint') {
        return {
            ...basePayload,
            settings: {
                method: draft.method,
                url: draft.url,
                headers: parseHeaders(draft.headers),
                body: draft.body || null,
                follow_redirects: draft.follow_redirects,
                verify_ssl: draft.verify_ssl,
            },
            expected: {
                status_codes: parseNumberList(draft.status_codes),
                max_response_time_ms: draft.max_response_time_ms,
                response_contains: draft.response_contains || null,
            },
        }
    }

    if (draft.type === 'ssl') {
        return {
            ...basePayload,
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

    if (draft.type === 'domain') {
        return {
            ...basePayload,
            settings: {
                domain: draft.domain,
                warning_days: parseNumberList(draft.warning_days),
            },
            expected: {
                registered: draft.registered,
            },
        }
    }

    if (draft.type === 'dns') {
        return {
            ...basePayload,
            settings: {
                domain: draft.domain,
                record_types: parseStringList(draft.record_types),
                nameservers: parseStringList(draft.nameservers),
                warn_on_change: draft.warn_on_change,
            },
            expected: {
                resolves: draft.resolves,
                min_records: draft.min_records,
            },
        }
    }

    if (draft.type === 'robots_txt') {
        return {
            ...basePayload,
            settings: {
                url: draft.url,
                follow_redirects: draft.follow_redirects,
                verify_ssl: draft.verify_ssl,
            },
            expected: {
                exists: draft.exists,
                status_codes: parseNumberList(draft.status_codes),
                max_response_time_ms: draft.max_response_time_ms,
            },
        }
    }

    if (draft.type === 'sitemap_xml') {
        return {
            ...basePayload,
            settings: {
                url: draft.url,
                follow_redirects: draft.follow_redirects,
                verify_ssl: draft.verify_ssl,
            },
            expected: {
                exists: draft.exists,
                valid_xml: draft.valid_xml,
                status_codes: parseNumberList(draft.status_codes),
                max_response_time_ms: draft.max_response_time_ms,
            },
        }
    }

    return {
        ...basePayload,
        settings: {
            host: draft.host,
            port: draft.port,
        },
        expected: {
            open: draft.open,
            max_response_time_ms: draft.max_response_time_ms,
        },
    }
}

function toggleSettings(monitor: Monitor): void {
    if (!monitor.is_available) return

    expandedMonitorId.value = expandedMonitorId.value === monitor.id ? null : monitor.id
}

function saveMonitor(monitor: Monitor): void {
    if (!monitor.is_available) return

    if (!monitor.is_configured) {
        router.post(`/sites/${props.site.id}/monitors`, {
            ...payloadForMonitor(monitor),
            feedback_action: 'settings',
        }, {
            preserveScroll: true,
            onSuccess: () => {
                expandedMonitorId.value = null
            },
        })

        return
    }

    router.put(`/sites/${props.site.id}/monitors/${monitor.id}`, {
        ...payloadForMonitor(monitor),
        feedback_action: 'settings',
    }, {
        preserveScroll: true,
    })
}

function toggleMonitor(monitor: Monitor): void {
    if (!monitor.is_available) return

    if (!monitor.is_configured) {
        monitorDrafts.value[monitor.id] = {
            ...monitorDrafts.value[monitor.id],
            is_enabled: true,
        }

        router.post(`/sites/${props.site.id}/monitors`, {
            ...payloadForMonitor(monitor),
            feedback_action: 'toggle',
        }, {
            preserveScroll: true,
        })

        return
    }

    router.patch(`/sites/${props.site.id}/monitors/${monitor.id}/toggle`, {}, {
        preserveScroll: true,
    })
}

function canToggleMonitor(monitor: Monitor): boolean {
    if (!monitor.is_available) return false
    if (monitor.is_enabled) return true

    return !isMonitorLimitReached.value
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

        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-5 py-5 sm:px-8 lg:py-6">
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

                        <div class="xl:max-w-[620px]">
                            <div class="flex flex-wrap gap-2 xl:justify-end">
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
                                    :href="`/sites/${site.id}/edit`"
                                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]"
                                >
                                    <Settings class="h-4 w-4" :stroke-width="2" />
                                    Изменить
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#FECACA] bg-white px-4 text-sm font-medium text-[#E11D25] transition hover:bg-[#FFF4F4]"
                                    @click="isDeleteModalOpen = true"
                                >
                                    <Trash2 class="h-4 w-4" :stroke-width="2" />
                                    Удалить сайт
                                </button>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-[#6A7A70] xl:justify-end">
                                <p class="flex items-center gap-2">
                                    <Clock3 class="h-4 w-4" :stroke-width="2" />
                                    Последняя: {{ relativeDate(latestCheckAt) }}
                                </p>
                                <p class="flex items-center gap-2">
                                    <ShieldCheck class="h-4 w-4" :stroke-width="2" />
                                    Доступно проверок: {{ availableCount }} / {{ site.monitors.length }}
                                </p>
                                <p class="flex items-center gap-2">
                                    <CalendarClock class="h-4 w-4" :stroke-width="2" />
                                    Создан: {{ formatDate(site.created_at) }}
                                </p>
                            </div>
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
                        <p class="mt-1 text-xs text-[#6A7A70]">по активным проверкам</p>
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
                        <p class="text-2xl font-semibold" :class="successfulMonitorsCount === activeMonitors.length ? 'text-[#159653]' : 'text-[#E11D25]'">{{ successfulMonitorsCount }} из {{ activeMonitors.length }}</p>
                        <p class="mt-2 text-sm font-medium text-[#26332D]">Проверки</p>
                        <p class="mt-1 text-xs text-[#6A7A70]">успешно среди активных</p>
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

                <section id="checks" class="scroll-mt-24">
                    <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-[#17231C]">Проверки</h2>
                            <p class="mt-1 text-sm text-[#6A7A70]">Типы и настройки соответствуют текущему тарифу. Здесь можно запускать проверки вручную и ставить их на паузу.</p>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs font-medium">
                            <span class="inline-flex items-center rounded-full border border-[#BFEBD0] bg-[#E9F8EF] px-3 py-1.5 text-[#159653]">
                                Активные: {{ enabledCount }}
                            </span>
                            <span class="inline-flex items-center rounded-full border border-[#D7E4DC] bg-[#F1F7F3] px-3 py-1.5 text-[#6E8075]">
                                На паузе: {{ inactiveCount }}
                            </span>
                            <span v-if="unavailableCount" class="inline-flex items-center rounded-full border border-[#D7DDDA] bg-[#ECEFF1] px-3 py-1.5 text-[#64706A]">
                                Недоступны на тарифе: {{ unavailableCount }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <article
                            v-for="monitor in site.monitors"
                            :key="monitor.id"
                            class="relative overflow-hidden rounded-3xl border p-4 shadow-[0_10px_28px_rgba(31,68,49,0.05)]"
                            :class="monitorCardClass(monitor)"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 gap-3">
                                        <span
                                            class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl border"
                                            :class="!monitor.is_available ? 'border-[#D7DDDA] bg-[#ECEFF1] text-[#64706A]' : statusIconBoxClass(monitorStatus(monitor))"
                                        >
                                            <LoaderCircle v-if="isChecking(monitor)" class="h-5 w-5 animate-spin" :stroke-width="2.2" />
                                            <Crown v-else-if="!monitor.is_available" class="h-5 w-5" :stroke-width="2.2" />
                                            <component v-else :is="typeIcon(monitor.type)" class="h-5 w-5" :stroke-width="2.2" />
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-semibold text-[#17231C]">{{ typeLabel(monitor.type) }}</h3>
                                            <p v-if="typeDescription(monitor.type)" class="mt-1 line-clamp-2 text-xs leading-5 text-[#6A7A70]">{{ typeDescription(monitor.type) }}</p>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium" :class="monitorAccessClass(monitor)">
                                                    {{ monitorAccessLabel(monitor) }}
                                                </span>
                                                <span v-if="monitor.is_available" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium" :class="monitorActivityClass(monitor)">
                                                    {{ monitorActivityLabel(monitor) }}
                                                </span>
                                                <span v-if="monitor.is_configured && monitor.is_available" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium" :class="statusClass(monitorStatus(monitor))">
                                                    <LoaderCircle v-if="isChecking(monitor)" class="mr-1.5 h-3.5 w-3.5 animate-spin" :stroke-width="2.2" />
                                                    <component v-else :is="statusIcon(monitorStatus(monitor))" class="mr-1.5 h-3.5 w-3.5" :stroke-width="2.2" />
                                                    {{ statusLabel(monitorStatus(monitor)) }}
                                                </span>
                                            </div>
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

                                <div v-if="!monitor.is_available" class="mt-4">
                                    <TariffRestriction action="Подключить проверку" />
                                </div>

                                <div v-else class="mt-4 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#2FA568] px-4 text-sm font-medium text-white transition hover:bg-[#278C58] disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!monitor.is_configured || !monitor.is_available || !monitor.is_enabled || isChecking(monitor)"
                                        @click="checkNow(monitor)"
                                    >
                                        <LoaderCircle v-if="isChecking(monitor)" class="h-4 w-4 animate-spin" :stroke-width="2" />
                                        <RotateCw v-else class="h-4 w-4" :stroke-width="2" />
                                        Проверить
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]"
                                        :disabled="!canToggleMonitor(monitor)"
                                        @click="toggleMonitor(monitor)"
                                    >
                                        <Pause v-if="monitor.is_enabled" class="h-4 w-4" :stroke-width="2" />
                                        <Play v-else class="h-4 w-4" :stroke-width="2" />
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
                                    class="mt-5"
                                    @submit.prevent="saveMonitor(monitor)"
                                >
                                    <div :class="settingsPanelClass(monitor)">
                                        <template v-if="monitor.type === 'http'">
                                            <div>
                                                <label :for="`${monitor.id}-name`" class="mb-2 block text-sm font-semibold text-[#26332D]">Название</label>
                                                <input :id="`${monitor.id}-name`" v-model="monitorDrafts[monitor.id].name" type="text" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-method`" class="mb-2 block text-sm font-semibold text-[#26332D]">Метод</label>
                                                <select :id="`${monitor.id}-method`" v-model="monitorDrafts[monitor.id].method" :class="settingsInputClass(monitor)">
                                                    <option value="GET">GET</option>
                                                    <option value="HEAD">HEAD</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-status`" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                                <input :id="`${monitor.id}-status`" v-model="monitorDrafts[monitor.id].status_codes" type="text" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-time`" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                                <input :id="`${monitor.id}-time`" v-model.number="monitorDrafts[monitor.id].max_response_time_ms" min="1" type="number" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <CheckIntervalControl
                                                    v-model="monitorDrafts[monitor.id].interval_seconds"
                                                    :input-id="`${monitor.id}-interval-minutes`"
                                                    :minimum-minutes="minimumIntervalMinutes"
                                                />
                                            </div>
                                        </template>

                                        <template v-else-if="monitor.type === 'ssl'">
                                            <div>
                                                <label :for="`${monitor.id}-port`" class="mb-2 block text-sm font-semibold text-[#26332D]">Порт</label>
                                                <input :id="`${monitor.id}-port`" v-model.number="monitorDrafts[monitor.id].port" min="1" max="65535" type="number" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-days`" class="mb-2 block text-sm font-semibold text-[#26332D]">Дни предупреждений</label>
                                                <input :id="`${monitor.id}-days`" v-model="monitorDrafts[monitor.id].warning_days" type="text" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <CheckIntervalControl
                                                    v-model="monitorDrafts[monitor.id].interval_seconds"
                                                    :input-id="`${monitor.id}-interval-minutes`"
                                                    :minimum-minutes="1440"
                                                :maximum-minutes="10080"
                                                unit="days"
                                                />
                                            </div>
                                        </template>

                                        <template v-else-if="monitor.type === 'domain'">
                                            <div class="md:col-span-2">
                                                <label :for="`${monitor.id}-days`" class="mb-2 block text-sm font-semibold text-[#26332D]">Дни предупреждений</label>
                                                <input :id="`${monitor.id}-days`" v-model="monitorDrafts[monitor.id].warning_days" type="text" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <CheckIntervalControl
                                                    v-model="monitorDrafts[monitor.id].interval_seconds"
                                                    :input-id="`${monitor.id}-interval-minutes`"
                                                    :minimum-minutes="1440"
                                                :maximum-minutes="10080"
                                                unit="days"
                                                />
                                            </div>
                                        </template>

                                        <template v-else-if="monitor.type === 'dns'">
                                            <div>
                                                <label :for="`${monitor.id}-types`" class="mb-2 block text-sm font-semibold text-[#26332D]">Типы записей</label>
                                                <input :id="`${monitor.id}-types`" v-model="monitorDrafts[monitor.id].record_types" type="text" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-min`" class="mb-2 block text-sm font-semibold text-[#26332D]">Минимум записей</label>
                                                <input :id="`${monitor.id}-min`" v-model.number="monitorDrafts[monitor.id].min_records" min="0" type="number" :class="settingsInputClass(monitor)">
                                            </div>
                                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[#DDEBE3] bg-[#F8FAFC] p-3 md:col-span-2">
                                                <input
                                                    v-model="monitorDrafts[monitor.id].warn_on_change"
                                                    type="checkbox"
                                                    class="mt-0.5 h-4 w-4 rounded border-[#B8D0C2] text-[#2FA568] focus:ring-[#2FA568]/25"
                                                    :disabled="!monitor.is_available"
                                                >
                                                <span>
                                                    <span class="block text-sm font-semibold text-[#26332D]">Создавать Warning при изменении записей</span>
                                                    <span class="mt-1 block text-xs leading-5 text-[#6A7A70]">Сравнивать DNS-записи с предыдущей успешной проверкой.</span>
                                                </span>
                                            </label>
                                            <div class="md:col-span-2">
                                                <CheckIntervalControl
                                                    v-model="monitorDrafts[monitor.id].interval_seconds"
                                                    :input-id="`${monitor.id}-interval-minutes`"
                                                    :minimum-minutes="1440"
                                                :maximum-minutes="10080"
                                                unit="days"
                                                />
                                            </div>
                                        </template>

                                        <template v-else-if="monitor.type === 'robots_txt'">
                                            <div>
                                                <label :for="`${monitor.id}-status`" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                                <input :id="`${monitor.id}-status`" v-model="monitorDrafts[monitor.id].status_codes" type="text" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-time`" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                                <input :id="`${monitor.id}-time`" v-model.number="monitorDrafts[monitor.id].max_response_time_ms" min="1" type="number" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <CheckIntervalControl
                                                    v-model="monitorDrafts[monitor.id].interval_seconds"
                                                    :input-id="`${monitor.id}-interval-minutes`"
                                                    :minimum-minutes="1440"
                                                :maximum-minutes="10080"
                                                unit="days"
                                                />
                                            </div>
                                        </template>

                                        <template v-else-if="monitor.type === 'sitemap_xml'">
                                            <div>
                                                <label :for="`${monitor.id}-status`" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                                <input :id="`${monitor.id}-status`" v-model="monitorDrafts[monitor.id].status_codes" type="text" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-time`" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                                <input :id="`${monitor.id}-time`" v-model.number="monitorDrafts[monitor.id].max_response_time_ms" min="1" type="number" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <CheckIntervalControl
                                                    v-model="monitorDrafts[monitor.id].interval_seconds"
                                                    :input-id="`${monitor.id}-interval-minutes`"
                                                    :minimum-minutes="1440"
                                                :maximum-minutes="10080"
                                                unit="days"
                                                />
                                            </div>
                                        </template>

                                        <template v-else-if="monitor.type === 'api_endpoint'">
                                            <div>
                                                <label :for="`${monitor.id}-method`" class="mb-2 block text-sm font-semibold text-[#26332D]">Метод</label>
                                                <select :id="`${monitor.id}-method`" v-model="monitorDrafts[monitor.id].method" :class="settingsInputClass(monitor)">
                                                    <option value="GET">GET</option>
                                                    <option value="HEAD">HEAD</option>
                                                    <option value="POST">POST</option>
                                                    <option value="PUT">PUT</option>
                                                    <option value="PATCH">PATCH</option>
                                                    <option value="DELETE">DELETE</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-endpoint`" class="mb-2 block text-sm font-semibold text-[#26332D]">Endpoint</label>
                                                <input :id="`${monitor.id}-endpoint`" v-model="monitorDrafts[monitor.id].url" type="text" placeholder="/api/health" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-status`" class="mb-2 block text-sm font-semibold text-[#26332D]">Ожидаемые коды</label>
                                                <input :id="`${monitor.id}-status`" v-model="monitorDrafts[monitor.id].status_codes" type="text" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-time`" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                                <input :id="`${monitor.id}-time`" v-model.number="monitorDrafts[monitor.id].max_response_time_ms" min="1" type="number" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label :for="`${monitor.id}-contains`" class="mb-2 block text-sm font-semibold text-[#26332D]">Ответ должен содержать</label>
                                                <input :id="`${monitor.id}-contains`" v-model="monitorDrafts[monitor.id].response_contains" type="text" placeholder="опционально" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <CheckIntervalControl
                                                    v-model="monitorDrafts[monitor.id].interval_seconds"
                                                    :input-id="`${monitor.id}-interval-minutes`"
                                                    :minimum-minutes="1440"
                                                :maximum-minutes="10080"
                                                unit="days"
                                                />
                                            </div>
                                        </template>

                                        <template v-else-if="monitor.type === 'tcp_port'">
                                            <div>
                                                <label :for="`${monitor.id}-port`" class="mb-2 block text-sm font-semibold text-[#26332D]">Порт</label>
                                                <input :id="`${monitor.id}-port`" v-model.number="monitorDrafts[monitor.id].port" min="1" max="65535" type="number" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div>
                                                <label :for="`${monitor.id}-time`" class="mb-2 block text-sm font-semibold text-[#26332D]">Макс. время ответа, мс</label>
                                                <input :id="`${monitor.id}-time`" v-model.number="monitorDrafts[monitor.id].max_response_time_ms" min="1" type="number" :class="settingsInputClass(monitor)">
                                            </div>
                                            <div class="md:col-span-2">
                                                <CheckIntervalControl
                                                    v-model="monitorDrafts[monitor.id].interval_seconds"
                                                    :input-id="`${monitor.id}-interval-minutes`"
                                                    :minimum-minutes="1440"
                                                :maximum-minutes="10080"
                                                unit="days"
                                                />
                                            </div>
                                        </template>
                                    </div>

                                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-[#DDEBE3] pt-4">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-3 rounded-2xl border border-[#DDEBE3] bg-white px-3 py-2 text-sm font-semibold text-[#26332D]"
                                            :disabled="!monitorDrafts[monitor.id].is_enabled && isMonitorLimitReached"
                                            @click="monitorDrafts[monitor.id].is_enabled = !monitorDrafts[monitor.id].is_enabled"
                                        >
                                            <span class="flex h-7 w-12 items-center rounded-full p-1 transition" :class="monitorDrafts[monitor.id].is_enabled ? 'justify-end bg-[#2FA568]' : 'justify-start bg-[#CFE1D7]'">
                                                <span class="h-5 w-5 rounded-full bg-white shadow-sm"></span>
                                            </span>
                                            {{ monitorDrafts[monitor.id].is_enabled ? 'Включен' : 'На паузе' }}
                                        </button>
                                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#2FA568] px-5 text-sm font-bold text-white shadow-[0_14px_32px_rgba(47,165,104,0.18)] transition hover:bg-[#248653]">
                                            Сохранить
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </article>
                    </div>
                </section>

                <section id="incidents" class="space-y-6">
                    <div class="overflow-hidden rounded-3xl border border-[#DDEBE3] bg-white shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                        <div class="flex items-start justify-between gap-3 border-b border-[#DDEBE3] p-5">
                            <div>
                                <h2 class="text-xl font-semibold text-[#17231C]">Скорость загрузки сайта</h2>
                                <p class="mt-1 text-sm text-[#6A7A70]">Среднее время ответа по дням для проверки «Доступность сайта».</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full border border-[#DDEBE3] bg-[#F6FBF8] px-3 py-1 text-xs font-semibold text-[#2B7E53]">
                                    История: {{ historyRetentionDays }} дн.
                                </span>
                                <Activity class="h-5 w-5 text-[#8A9A91]" :stroke-width="2" />
                            </div>
                        </div>

                        <div v-if="hasAvailabilityTrendData" class="p-5">
                            <div class="h-[280px]">
                                <Line :data="availabilityChartData" :options="availabilityChartOptions" />
                            </div>
                        </div>

                        <div v-else class="p-8 text-center text-sm font-medium text-[#6A7A70]">
                            Недостаточно данных по проверке «Доступность сайта», чтобы построить график.
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-3xl border border-[#DDEBE3] bg-white shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                        <div class="flex items-start justify-between gap-3 border-b border-[#DDEBE3] p-5">
                            <div>
                                <h2 class="text-xl font-semibold text-[#17231C]">История проверок</h2>
                                <p class="mt-1 text-sm text-[#6A7A70]">Последние технические результаты от poller за {{ historyRetentionDays }} {{ dayWord(historyRetentionDays) }}.</p>
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
                            Проверок за доступный период еще не было.
                        </div>
                    </div>

                    <div class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
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
                    </div>
                </section>
            </main>


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
