<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { useAutoRefresh } from '../../Composables/useAutoRefresh'

type Organization = {
    id: string
    name: string
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
    interval_seconds: number | null
    timeout_ms: number | null
    settings: MonitorSettings | null
    expected: MonitorExpected | null
    last_check_at: string | null
    next_check_at: string | null
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

useAutoRefresh({
    only: ['site'],
    intervalMs: 10000,
})

const expandedMonitorId = ref<string | null>(null)
const isDeleteModalOpen = ref(false)
const checkingMonitorIds = ref<string[]>([])
const checkingStartedFrom = ref<Record<string, string | null>>({})
const checkingTimeouts = ref<Record<string, ReturnType<typeof setTimeout>>>({})
const intervalPresets = [5, 10, 15, 30, 60, 360, 720, 1440]
const monitorDrafts = ref<Record<string, MonitorDraft>>(
    Object.fromEntries(props.site.monitors.map((monitor) => [monitor.id, draftFromMonitor(monitor)])),
)

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
    },
)

onUnmounted(() => {
    Object.values(checkingTimeouts.value).forEach(clearTimeout)
})

const enabledCount = computed(() => props.site.monitors.filter((monitor) => monitor.is_enabled).length)
const activeIncidentCount = computed(() => props.site.incidents.filter((incident) => incident.status === 'open').length)
const latestCheckAt = computed(() => {
    const dates = props.site.monitors
        .map((monitor) => monitor.last_check_at)
        .filter((value): value is string => Boolean(value))
        .sort()

    return dates.at(-1) ?? null
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
    if (status === 'ok' || status === 'success' || status === 'up') return 'OK'
    if (status === 'down' || status === 'failure') return 'Down'
    if (status === 'warning' || status === 'degraded') return 'Warning'
    if (status === 'paused') return 'Paused'
    if (status === 'empty') return 'Empty'

    return 'Unknown'
}

function statusClass(status: string): string {
    if (status === 'ok' || status === 'success' || status === 'up') return 'bg-[#ECFDF3] text-[#16A34A]'
    if (status === 'down' || status === 'failure') return 'bg-[#FEECEC] text-[#EF4444]'
    if (status === 'warning' || status === 'degraded') return 'bg-[#FFF7E8] text-[#F59E0B]'
    if (status === 'paused') return 'bg-[#F1F5F9] text-[#64748B]'

    return 'bg-[#EAF2FF] text-[#0F6BFF]'
}

function monitorStatus(monitor: Monitor): string {
    if (!monitor.is_enabled || monitor.status === 'paused') return 'paused'
    if (monitor.status === 'success' || monitor.status === 'up') return 'ok'
    if (monitor.status === 'failure' || monitor.status === 'down') return 'down'
    if (monitor.status === 'degraded' || monitor.status === 'warning' || monitor.latest_result?.status === 'warning') return 'warning'

    return 'unknown'
}

function typeLabel(type: string): string {
    if (type === 'http') return 'HTTP'
    if (type === 'ssl') return 'SSL'
    if (type === 'domain') return 'Domain'

    return type.toUpperCase()
}

function typeClass(type: string): string {
    if (type === 'http') return 'bg-[#EAF2FF] text-[#0F6BFF]'
    if (type === 'ssl') return 'bg-[#ECFDF3] text-[#16A34A]'
    if (type === 'domain') return 'bg-[#FFF7E8] text-[#F59E0B]'

    return 'bg-[#F1F5F9] text-[#64748B]'
}

function monitorCardClass(monitor: Monitor): string {
    const status = monitorStatus(monitor)

    if (status === 'down') return 'border-[#FECACA] bg-[#FFF8F8]'
    if (status === 'warning') return 'border-[#FDE68A] bg-[#FFFCF4]'
    if (status === 'ok') return 'border-[#BBF7D0] bg-[#F6FEF9]'

    return 'border-[#E5E7EB] bg-[#F8FAFC]'
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

function intervalText(seconds: number): string {
    const minutes = intervalMinutes(seconds)

    if (minutes === 60) return 'Каждый час'
    if (minutes === 1440) return 'Раз в день'
    if (minutes > 60 && minutes % 60 === 0) return `Каждые ${minutes / 60} ч`

    return `Каждые ${minutes} мин`
}

function formatDuration(seconds: number | null): string {
    if (!seconds) return '0 мин'
    if (seconds < 3600) return `${Math.max(1, Math.round(seconds / 60))} мин`
    if (seconds < 86400) return `${Math.round(seconds / 3600)} ч`

    return `${Math.round(seconds / 86400)} д`
}

function resultText(monitor: Monitor): string {
    const result = monitor.latest_result

    if (!result) return 'Нет результата'
    if (result.error_message) return result.error_message

    if (monitor.type === 'http') {
        const code = result.status_code ? `${result.status_code}` : 'HTTP'
        const time = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${code}${time}`
    }

    const days = result.normalized_result.days_until_expiration

    if (typeof days === 'number') {
        return `истекает через ${days} ${dayWord(days)}`
    }

    return statusLabel(result.status)
}

function checkResultText(result: CheckResult): string {
    if (result.error_message) return result.error_message
    if (result.check_type === 'http') {
        const code = result.status_code ? `${result.status_code}` : 'HTTP'
        const time = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${code}${time}`
    }

    const days = result.normalized_result.days_until_expiration

    if (typeof days === 'number') return `${days} ${dayWord(days)}`

    return statusLabel(result.status)
}

function dayWord(days: number): string {
    const mod10 = days % 10
    const mod100 = days % 100

    if (mod10 === 1 && mod100 !== 11) return 'день'
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'дня'

    return 'дней'
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
    expandedMonitorId.value = expandedMonitorId.value === monitor.id ? null : monitor.id
}

function saveMonitor(monitor: Monitor): void {
    router.put(`/sites/${props.site.id}/monitors/${monitor.id}`, payloadForMonitor(monitor), {
        preserveScroll: true,
    })
}

function toggleMonitor(monitor: Monitor): void {
    router.patch(`/sites/${props.site.id}/monitors/${monitor.id}/toggle`, {}, {
        preserveScroll: true,
    })
}

function isChecking(monitor: Monitor): boolean {
    return checkingMonitorIds.value.includes(monitor.id)
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
        onError: () => {
            stopChecking(monitor.id)
        },
        onCancel: () => {
            stopChecking(monitor.id)
        },
    })
}

function deleteSite(): void {
    router.delete(`/sites/${props.site.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleteModalOpen.value = false
        },
    })
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
        <template #actions>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Link
                    href="/sites"
                    class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                >
                    Назад к сайтам
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Здоровье сайта</p>
                    <div class="mt-4 flex items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(site.status)">
                            {{ statusLabel(site.status) }}
                        </span>
                        <span class="text-sm font-semibold text-[#667085]">{{ site.problem_label }}</span>
                    </div>
                    <p class="mt-4 truncate text-sm text-[#667085]">{{ site.host }}<template v-if="site.port">:{{ site.port }}</template></p>
                </article>

                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Мониторинги</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ enabledCount }} / {{ site.monitors.length }}</p>
                    <p class="mt-2 text-sm text-[#667085]">Включено сейчас</p>
                </article>

                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Последняя проверка</p>
                    <p class="mt-3 text-xl font-extrabold text-[#111827]">{{ formatDate(latestCheckAt) }}</p>
                    <p class="mt-2 text-sm text-[#667085]">По любому мониторингу сайта</p>
                </article>

                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Активные инциденты</p>
                    <p class="mt-3 text-4xl font-extrabold" :class="activeIncidentCount ? 'text-[#EF4444]' : 'text-[#16A34A]'">{{ activeIncidentCount }}</p>
                    <p class="mt-2 text-sm text-[#667085]">Открытые проблемы</p>
                </article>
            </section>

            <section class="mt-6 grid gap-5">
                <article
                    v-for="monitor in site.monitors"
                    :key="monitor.id"
                    class="rounded-3xl border p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)] transition"
                    :class="monitorCardClass(monitor)"
                >
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="typeClass(monitor.type)">
                                    {{ typeLabel(monitor.type) }}
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(monitorStatus(monitor))">
                                    {{ statusLabel(monitorStatus(monitor)) }}
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="monitor.is_enabled ? 'bg-[#ECFDF3] text-[#16A34A]' : 'bg-[#F1F5F9] text-[#64748B]'">
                                    {{ monitor.is_enabled ? 'Включен' : 'На паузе' }}
                                </span>
                            </div>

                            <h2 class="mt-3 text-xl font-extrabold text-[#111827]">{{ monitor.name }}</h2>
                            <p class="mt-1 text-sm leading-6 text-[#667085]">
                                {{ resultText(monitor) }} · последняя: {{ formatDate(monitor.last_check_at) }} · следующая: {{ formatDate(monitor.next_check_at) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex h-9 min-w-[104px] items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-3 text-xs font-extrabold text-[#111827] transition enabled:hover:border-[#0F6BFF] enabled:hover:text-[#0F6BFF] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!monitor.is_enabled || isChecking(monitor)"
                                @click="checkNow(monitor)"
                            >
                                <span
                                    v-if="isChecking(monitor)"
                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-[#0F6BFF]/25 border-t-[#0F6BFF]"
                                    aria-hidden="true"
                                />
                                <span>{{ isChecking(monitor) ? 'Проверяем...' : 'Проверить' }}</span>
                            </button>
                            <button
                                type="button"
                                class="h-9 rounded-xl border border-[#E5E7EB] bg-white px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF] cursor-pointer"
                                @click="toggleMonitor(monitor)"
                            >
                                {{ monitor.is_enabled ? 'Пауза' : 'Включить' }}
                            </button>
                            <button
                                type="button"
                                class="h-9 rounded-xl border border-[#E5E7EB] bg-white px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF] cursor-pointer"
                                @click="toggleSettings(monitor)"
                            >
                                {{ expandedMonitorId === monitor.id ? 'Скрыть настройки' : 'Настроить' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 border-t border-[#E5E7EB] pt-5 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p class="text-xs font-extrabold uppercase text-[#98A2B3]">Интервал</p>
                            <p class="mt-1 text-sm font-bold text-[#111827]">{{ formatInterval(monitor.interval_seconds) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-extrabold uppercase text-[#98A2B3]">Таймаут</p>
                            <p class="mt-1 text-sm font-bold text-[#111827]">{{ monitor.timeout_ms ?? 10000 }} мс</p>
                        </div>
                        <div>
                            <p class="text-xs font-extrabold uppercase text-[#98A2B3]">Успешно</p>
                            <p class="mt-1 text-sm font-bold text-[#111827]">{{ formatDate(monitor.last_success_at) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-extrabold uppercase text-[#98A2B3]">Ошибка</p>
                            <p class="mt-1 text-sm font-bold text-[#111827]">{{ formatDate(monitor.last_failure_at) }}</p>
                        </div>
                    </div>

                    <form
                        v-if="expandedMonitorId === monitor.id"
                        class="mt-5 rounded-3xl border border-[#E5E7EB] bg-white p-5"
                        @submit.prevent="saveMonitor(monitor)"
                    >
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label :for="`monitor-name-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">Название</label>
                                <input :id="`monitor-name-${monitor.id}`" v-model="monitorDrafts[monitor.id].name" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label :for="`interval-${monitor.id}`" class="block text-sm font-extrabold text-[#111827]">Частота проверки</label>
                                    <span class="text-xs font-extrabold text-[#0F6BFF]">{{ intervalText(monitorDrafts[monitor.id].interval_seconds) }}</span>
                                </div>
                                <div class="rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] p-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="minutes in intervalPresets"
                                            :key="`${monitor.id}-${minutes}`"
                                            type="button"
                                            class="h-8 rounded-full px-3 text-xs font-extrabold transition"
                                            :class="intervalMinutes(monitorDrafts[monitor.id].interval_seconds) === minutes ? 'bg-[#0F6BFF] text-white' : 'bg-white text-[#667085] hover:bg-[#EAF2FF] hover:text-[#0F6BFF]'"
                                            @click="setDraftIntervalMinutes(monitorDrafts[monitor.id], minutes)"
                                        >
                                            {{ minutes === 60 ? '1 час' : minutes === 1440 ? '1 день' : minutes < 60 ? `${minutes} мин` : `${minutes / 60} ч` }}
                                        </button>
                                    </div>
                                    <input
                                        :id="`interval-${monitor.id}`"
                                        :value="intervalMinutes(monitorDrafts[monitor.id].interval_seconds)"
                                        type="range"
                                        min="5"
                                        max="1440"
                                        step="1"
                                        class="mt-4 w-full accent-[#0F6BFF]"
                                        @input="setDraftIntervalMinutes(monitorDrafts[monitor.id], Number(($event.target as HTMLInputElement).value))"
                                    >
                                </div>
                            </div>
                            <div>
                                <label :for="`timeout-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">Таймаут, мс</label>
                                <input :id="`timeout-${monitor.id}`" v-model.number="monitorDrafts[monitor.id].timeout_ms" type="number" min="1000" max="60000" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </div>
                            <button
                                type="button"
                                class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] px-4 py-3 text-left transition hover:border-[#0F6BFF]"
                                :aria-pressed="monitorDrafts[monitor.id].is_enabled"
                                @click="monitorDrafts[monitor.id].is_enabled = !monitorDrafts[monitor.id].is_enabled"
                            >
                                <span
                                    class="flex h-6 w-11 shrink-0 items-center rounded-full p-1 transition"
                                    :class="monitorDrafts[monitor.id].is_enabled ? 'justify-end bg-[#0F6BFF]' : 'justify-start bg-[#CBD5E1]'"
                                >
                                    <span class="h-4 w-4 rounded-full bg-white shadow-sm" />
                                </span>
                                <span class="text-sm font-extrabold text-[#111827]">{{ monitorDrafts[monitor.id].is_enabled ? 'Включен' : 'На паузе' }}</span>
                            </button>
                        </div>

                        <div v-if="monitor.type === 'http'" class="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <label :for="`method-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">Метод</label>
                                <select :id="`method-${monitor.id}`" v-model="monitorDrafts[monitor.id].method" class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                                    <option value="GET">GET</option>
                                    <option value="HEAD">HEAD</option>
                                    <option value="POST">POST</option>
                                </select>
                            </div>
                            <div>
                                <label :for="`url-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">URL</label>
                                <input :id="`url-${monitor.id}`" v-model="monitorDrafts[monitor.id].url" type="url" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </div>
                            <div>
                                <label :for="`codes-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">Ожидаемые коды</label>
                                <input :id="`codes-${monitor.id}`" v-model="monitorDrafts[monitor.id].status_codes" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </div>
                            <div>
                                <label :for="`response-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">Макс. время ответа, мс</label>
                                <input :id="`response-${monitor.id}`" v-model.number="monitorDrafts[monitor.id].max_response_time_ms" type="number" min="1" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </div>
                            <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] px-4 py-3">
                                <input v-model="monitorDrafts[monitor.id].follow_redirects" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1] text-[#0F6BFF]">
                                <span class="text-sm font-bold text-[#111827]">Следовать редиректам</span>
                            </label>
                            <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] px-4 py-3">
                                <input v-model="monitorDrafts[monitor.id].verify_ssl" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1] text-[#0F6BFF]">
                                <span class="text-sm font-bold text-[#111827]">Проверять SSL</span>
                            </label>
                        </div>

                        <div v-if="monitor.type === 'ssl' || monitor.type === 'domain'" class="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <label :for="`domain-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">Домен</label>
                                <input :id="`domain-${monitor.id}`" v-model="monitorDrafts[monitor.id].domain" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </div>
                            <div v-if="monitor.type === 'ssl'">
                                <label :for="`port-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">Порт</label>
                                <input :id="`port-${monitor.id}`" v-model.number="monitorDrafts[monitor.id].port" type="number" min="1" max="65535" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </div>
                            <div :class="monitor.type === 'domain' ? 'md:col-span-2' : ''">
                                <label :for="`warning-${monitor.id}`" class="mb-2 block text-sm font-extrabold text-[#111827]">Дни предупреждений</label>
                                <input :id="`warning-${monitor.id}`" v-model="monitorDrafts[monitor.id].warning_days" type="text" required class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8]">
                                Сохранить настройки
                            </button>
                        </div>
                    </form>
                </article>
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                <div class="overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] p-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Последние проверки</h2>
                        <p class="mt-1 text-sm text-[#667085]">Технические результаты, полученные от poller.</p>
                    </div>

                    <div v-if="site.recent_checks.length" class="overflow-x-auto">
                        <table class="min-w-[760px] w-full border-separate border-spacing-0 text-left text-sm">
                            <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                            <tr>
                                <th class="px-5 py-4">Тип</th>
                                <th class="px-5 py-4">Статус</th>
                                <th class="px-5 py-4">Результат</th>
                                <th class="px-5 py-4">Время</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="result in site.recent_checks" :key="result.id">
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="typeClass(result.check_type)">
                                        {{ typeLabel(result.check_type) }}
                                    </span>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(result.status)">
                                        {{ statusLabel(result.status) }}
                                    </span>
                                </td>
                                <td class="max-w-96 truncate border-t border-[#E5E7EB] px-5 py-4 font-semibold text-[#111827]">
                                    {{ checkResultText(result) }}
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                    {{ formatDate(result.checked_at) }}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="p-8 text-center text-sm font-semibold text-[#667085]">
                        Проверок еще не было.
                    </div>
                </div>

                <aside class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <h2 class="text-xl font-extrabold text-[#111827]">Инциденты</h2>
                    <p class="mt-1 text-sm text-[#667085]">История падений и восстановлений сайта.</p>

                    <div v-if="site.incidents.length" class="mt-5 grid gap-3">
                        <article
                            v-for="incident in site.incidents"
                            :key="incident.id"
                            class="rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-sm font-extrabold text-[#111827]">{{ incident.title }}</h3>
                                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-extrabold" :class="incident.status === 'open' ? 'bg-[#FEECEC] text-[#EF4444]' : 'bg-[#ECFDF3] text-[#16A34A]'">
                                    {{ incident.status === 'open' ? 'Open' : 'Resolved' }}
                                </span>
                            </div>
                            <p v-if="incident.summary" class="mt-2 text-sm leading-6 text-[#667085]">{{ incident.summary }}</p>
                            <p class="mt-3 text-xs font-semibold text-[#667085]">
                                {{ formatDate(incident.started_at) }} · {{ formatDuration(incident.duration_seconds) }}
                            </p>
                        </article>
                    </div>

                    <div v-else class="mt-5 rounded-2xl border border-dashed border-[#E5E7EB] p-6 text-center text-sm font-semibold text-[#667085]">
                        Инцидентов пока нет.
                    </div>
                </aside>
            </section>

            <section class="mt-6 rounded-3xl border border-[#FECACA] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-[#111827]">Опасная зона</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-[#667085]">
                            Удаление скроет сайт из кабинета и поставит в архив связанные мониторинги. История проверок и инциденты останутся в системе для внутреннего учета.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-[#EF4444] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(239,68,68,0.18)] transition hover:bg-[#DC2626]"
                        @click="isDeleteModalOpen = true"
                    >
                        Удалить сайт
                    </button>
                </div>
            </section>
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
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#FEECEC] text-xl font-extrabold text-[#EF4444]">!</span>
                        <div>
                            <h2 id="delete-site-title" class="text-xl font-extrabold text-[#111827]">Удалить сайт?</h2>
                            <p class="mt-1 text-sm leading-6 text-[#667085]">
                                Сайт {{ site.name }} будет скрыт из кабинета, а его мониторинги будут отправлены в архив.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-5">
                    <div class="rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] p-4">
                        <p class="text-xs font-extrabold uppercase text-[#98A2B3]">Сайт</p>
                        <p class="mt-2 truncate text-sm font-extrabold text-[#111827]">{{ site.name }}</p>
                        <p class="mt-1 truncate text-xs font-semibold text-[#667085]">{{ site.url }}</p>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-[#667085]">
                        История проверок и инциденты останутся в системе для внутреннего учета. Восстановление через интерфейс пока не реализовано.
                    </p>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-[#E5E7EB] bg-white p-5 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                        @click="isDeleteModalOpen = false"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-xl bg-[#EF4444] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(239,68,68,0.18)] transition hover:bg-[#DC2626]"
                        @click="deleteSite"
                    >
                        Удалить сайт
                    </button>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
