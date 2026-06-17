<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js'
import { Bar, Doughnut, Line } from 'vue-chartjs'
import { Download, Filter, FileText } from '@lucide/vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, LineElement, PointElement, Tooltip, Legend)

type Organization = {
    id: number | string
    name: string
}

type Plan = {
    code: string
    name: string
}

type Filters = {
    period: string
    type: string
    project_id: number | null
    date_from: string
    date_to: string
}

type Retention = {
    days: number
    requested_days: number
    was_limited_by_plan: boolean
}

type MonitorTypeOption = {
    value?: string
    code?: string
    label: string
    name?: string
    short_label?: string
    sort_order?: number
}

type ProjectOption = {
    id: number
    name: string
}

type SeriesPoint = {
    date: string
    value: number
}

type ResourceSummary = {
    id: number
    project: string
    name: string
    uptime_percent: number
    total_checks: number
    incident_count: number
    downtime_seconds: number
    avg_response_time_ms: number
    last_status: string
    last_check_at: string | null
}

type IncidentRow = {
    id: number
    resource: string
    project: string
    type: string
    status: string
    title: string
    started_at: string | null
    resolved_at: string | null
    duration_seconds: number | null
}

type ExpirationRisk = {
    id: number
    type: string
    resource: string
    project: string
    expires_at: string | null
    days_left: number | null
    status: string
    last_check_at: string | null
}

type NotificationLog = {
    id: number
    event_type: string
    channel: string
    channel_name: string | null
    status: string
    sent_at: string | null
    created_at: string | null
}

type ReportPayload = {
    kpi: {
        uptime_percent: number
        total_checks: number
        avg_response_time_ms: number
        total_incidents: number
        open_incidents: number
        downtime_seconds: number
        monitors_total: number
        warnings_total: number
    }
    series: {
        uptime: SeriesPoint[]
        response_time: SeriesPoint[]
        incidents: SeriesPoint[]
    }
    status_distribution: Record<string, number>
    resources: ResourceSummary[]
    projects: ProjectOption[]
    incident_history: IncidentRow[]
    expiration_risks: ExpirationRisk[]
    notification_logs: NotificationLog[]
}

const props = defineProps<{
    organization: Organization
    plan: Plan
    filters: Filters
    retention: Retention
    report: ReportPayload
    monitorTypes: MonitorTypeOption[]
}>()

const period = ref(props.filters.period)
const type = ref(props.filters.type)
const projectId = ref(props.filters.project_id ? String(props.filters.project_id) : '')
const dateFrom = ref(props.filters.date_from)
const dateTo = ref(props.filters.date_to)

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
            },
        },
    },
}

const percentChartOptions = {
    ...chartOptions,
    scales: {
        y: {
            min: 0,
            max: 100,
            ticks: {
                precision: 0,
            },
        },
    },
}

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
        },
    },
}

const uptimeChartData = computed(() => ({
    labels: props.report.series.uptime.map((point) => shortDate(point.date)),
    datasets: [
        {
            label: 'Uptime, %',
            data: props.report.series.uptime.map((point) => point.value),
            borderColor: '#168A5A',
            backgroundColor: 'rgba(22, 138, 90, 0.12)',
            tension: 0.32,
            fill: true,
            pointRadius: 2,
        },
    ],
}))

const responseChartData = computed(() => ({
    labels: props.report.series.response_time.map((point) => shortDate(point.date)),
    datasets: [
        {
            label: 'Response time, ms',
            data: props.report.series.response_time.map((point) => point.value),
            borderColor: '#1E9B5D',
            backgroundColor: 'rgba(30, 155, 93, 0.12)',
            tension: 0.32,
            fill: true,
            pointRadius: 2,
        },
    ],
}))

const incidentsChartData = computed(() => ({
    labels: props.report.series.incidents.map((point) => shortDate(point.date)),
    datasets: [
        {
            label: 'Инциденты',
            data: props.report.series.incidents.map((point) => point.value),
            backgroundColor: '#EF4444',
            borderRadius: 6,
        },
    ],
}))

const statusRows = computed(() => Object.entries(props.report.status_distribution)
    .map(([status, value]) => ({ status, value }))
    .sort((a, b) => b.value - a.value))

const statusChartData = computed(() => ({
    labels: statusRows.value.map((row) => statusLabel(row.status)),
    datasets: [
        {
            data: statusRows.value.map((row) => row.value),
            backgroundColor: ['#168A5A', '#EF4444', '#E08600', '#1E9B5D', '#8A9A91', '#12B3A8'],
        },
    ],
}))

const selectedProjectName = computed(() => {
    if (!projectId.value) return 'Все проекты'

    return props.report.projects.find((project) => String(project.id) === projectId.value)?.name ?? 'Выбранный проект'
})

function applyFilters(): void {
    router.get('/reports', {
        period: period.value,
        type: type.value,
        project_id: projectId.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function resetFilters(): void {
    period.value = 'max'
    type.value = 'all'
    projectId.value = ''
    dateFrom.value = ''
    dateTo.value = ''
    applyFilters()
}

function exportPdf(): void {
    window.print()
}

function formatDate(value: string | null): string {
    if (!value) return '-'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value))
}

function formatDateTime(value: string | null): string {
    if (!value) return '-'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function shortDate(value: string): string {
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
    }).format(new Date(value))
}

function formatDuration(seconds: number | null): string {
    if (!seconds) return '-'
    if (seconds < 60) return `${seconds} сек`
    if (seconds < 3600) return `${Math.round(seconds / 60)} мин`
    if (seconds < 86400) return `${Math.round(seconds / 3600)} ч`

    return `${Math.round(seconds / 86400)} дн`
}

function typeLabel(value: string): string {
    const option = props.monitorTypes.find((type) => (type.code ?? type.value) === value)

    return option?.short_label
        ?? option?.name
        ?? option?.label
        ?? value.toUpperCase()
}

function statusLabel(value: string): string {
    const labels: Record<string, string> = {
        success: 'Успешно',
        failed: 'Ошибка',
        failure: 'Ошибка',
        warning: 'Предупреждение',
        timeout: 'Таймаут',
        open: 'Открыт',
        resolved: 'Решен',
        sent: 'Отправлено',
        delivered: 'Доставлено',
        error: 'Ошибка',
    }

    return labels[value] ?? value
}

function statusClass(value: string): string {
    if (['success', 'resolved', 'sent', 'delivered'].includes(value)) return 'bg-[#E7F7ED] text-[#168A5A]'
    if (['warning'].includes(value)) return 'bg-[#FFF7E8] text-[#B45309]'

    return 'bg-[#FEECEC] text-[#B42318]'
}
</script>

<template>
    <Head title="Отчеты" />

    <DashboardLayout
        :organization="organization"
        active-item="reports"
        title="Отчеты"
        subtitle="Сводка доступности, инцидентов, SSL, доменов и уведомлений"
    >
        <template #actions>
            <button
                type="button"
                class="no-print inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#1E9B5D] px-5 text-sm font-semibold text-white shadow-[0_12px_32px_rgba(30,155,93,0.16)] transition hover:bg-[#168A5A]"
                @click="exportPdf"
            >
                <Download class="h-4 w-4" :stroke-width="2" />
                Выгрузить в PDF
            </button>
        </template>

        <div class="report-page mx-auto grid max-w-7xl gap-6 px-5 py-6 sm:px-8">
            <section class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_18px_60px_rgba(23,59,42,0.05)] sm:p-6">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-[#E9F8EF] text-[#1E9B5D]">
                                <FileText class="h-4 w-4" :stroke-width="2" />
                            </span>
                            <p class="text-sm font-semibold text-[#1E9B5D]">Отчет по мониторингу</p>
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold tracking-normal text-[#173B2A] sm:text-3xl">{{ selectedProjectName }}</h1>
                        <p class="mt-2 text-sm leading-6 text-[#6A7A70]">
                            Период: {{ formatDate(filters.date_from) }} - {{ formatDate(filters.date_to) }}.
                            Тариф {{ plan.name }} хранит события {{ retention.days }} дн.
                        </p>
                        <p v-if="retention.was_limited_by_plan" class="mt-2 rounded-2xl bg-[#FFF7E8] px-4 py-3 text-sm font-medium text-[#B45309]">
                            Запрошенный период больше доступной истории, поэтому отчет ограничен лимитом текущего тарифа.
                        </p>
                    </div>

                    <div class="no-print flex flex-col gap-3 sm:flex-row lg:justify-end">
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-semibold text-[#26332D] transition hover:border-[#B8D0C2]"
                            @click="applyFilters"
                        >
                            <Filter class="h-4 w-4" :stroke-width="2" />
                            Применить
                        </button>
                        <button
                            type="button"
                            class="h-11 rounded-xl border border-[#DDEBE3] px-4 text-sm font-semibold text-[#6A7A70] transition hover:border-[#B8D0C2] hover:text-[#26332D]"
                            @click="resetFilters"
                        >
                            Сбросить
                        </button>
                    </div>
                </div>

                <div class="no-print mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <select v-model="period" class="h-11 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#26332D] outline-none focus:border-[#1E9B5D] focus:ring-2 focus:ring-[#1E9B5D]/15" @change="applyFilters">
                        <option value="7">7 дней</option>
                        <option value="30">30 дней</option>
                        <option value="90">90 дней</option>
                        <option value="max">Доступный период</option>
                    </select>
                    <select v-model="projectId" class="h-11 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#26332D] outline-none focus:border-[#1E9B5D] focus:ring-2 focus:ring-[#1E9B5D]/15" @change="applyFilters">
                        <option value="">Все проекты</option>
                        <option v-for="project in report.projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>
                    <select v-model="type" class="h-11 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#26332D] outline-none focus:border-[#1E9B5D] focus:ring-2 focus:ring-[#1E9B5D]/15" @change="applyFilters">
                        <option value="all">Все проверки</option>
                        <option
                            v-for="monitorType in monitorTypes"
                            :key="monitorType.code ?? monitorType.value"
                            :value="monitorType.code ?? monitorType.value"
                        >
                            {{ monitorType.short_label ?? monitorType.name ?? monitorType.label }}
                        </option>
                    </select>
                    <input v-model="dateFrom" type="date" class="h-11 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#26332D] outline-none focus:border-[#1E9B5D] focus:ring-2 focus:ring-[#1E9B5D]/15" @change="applyFilters">
                    <input v-model="dateTo" type="date" class="h-11 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#26332D] outline-none focus:border-[#1E9B5D] focus:ring-2 focus:ring-[#1E9B5D]/15" @change="applyFilters">
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_18px_60px_rgba(23,59,42,0.05)]">
                    <p class="text-sm font-medium text-[#6A7A70]">Uptime</p>
                    <p class="mt-3 text-3xl font-semibold" :class="report.kpi.uptime_percent >= 99 ? 'text-[#168A5A]' : 'text-[#B42318]'">{{ report.kpi.uptime_percent }}%</p>
                    <p class="mt-2 text-xs font-medium text-[#6A7A70]">{{ report.kpi.total_checks }} проверок</p>
                </div>
                <div class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_18px_60px_rgba(23,59,42,0.05)]">
                    <p class="text-sm font-medium text-[#6A7A70]">Инциденты</p>
                    <p class="mt-3 text-3xl font-semibold" :class="report.kpi.total_incidents ? 'text-[#B42318]' : 'text-[#168A5A]'">{{ report.kpi.total_incidents }}</p>
                    <p class="mt-2 text-xs font-medium text-[#6A7A70]">Открыто: {{ report.kpi.open_incidents }}</p>
                </div>
                <div class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_18px_60px_rgba(23,59,42,0.05)]">
                    <p class="text-sm font-medium text-[#6A7A70]">Downtime</p>
                    <p class="mt-3 text-3xl font-semibold text-[#26332D]">{{ formatDuration(report.kpi.downtime_seconds) }}</p>
                    <p class="mt-2 text-xs font-medium text-[#6A7A70]">За выбранный период</p>
                </div>
                <div class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_18px_60px_rgba(23,59,42,0.05)]">
                    <p class="text-sm font-medium text-[#6A7A70]">Средний ответ</p>
                    <p class="mt-3 text-3xl font-semibold text-[#26332D]">{{ report.kpi.avg_response_time_ms }} мс</p>
                    <p class="mt-2 text-xs font-medium text-[#6A7A70]">Мониторов: {{ report.kpi.monitors_total }}</p>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="grid gap-6">
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                        <h2 class="text-lg font-extrabold text-[#111827]">Uptime по дням</h2>
                        <p class="mt-1 text-sm text-[#667085]">Процент успешных проверок в рамках доступной истории тарифа.</p>
                        <div class="mt-5 h-72">
                            <Line :data="uptimeChartData" :options="percentChartOptions" />
                        </div>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                            <h2 class="text-lg font-extrabold text-[#111827]">Время ответа</h2>
                            <p class="mt-1 text-sm text-[#667085]">Среднее значение по успешным и техническим результатам.</p>
                            <div class="mt-5 h-64">
                                <Line :data="responseChartData" :options="chartOptions" />
                            </div>
                        </div>
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                            <h2 class="text-lg font-extrabold text-[#111827]">Инциденты</h2>
                            <p class="mt-1 text-sm text-[#667085]">Количество новых инцидентов по дням.</p>
                            <div class="mt-5 h-64">
                                <Bar :data="incidentsChartData" :options="chartOptions" />
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="grid gap-6">
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                        <h2 class="text-lg font-extrabold text-[#111827]">Статусы проверок</h2>
                        <div class="mt-5 h-64">
                            <Doughnut :data="statusChartData" :options="doughnutOptions" />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                        <h2 class="text-lg font-extrabold text-[#111827]">SSL и домены</h2>
                        <div v-if="report.expiration_risks.length" class="mt-4 grid gap-3">
                            <div v-for="risk in report.expiration_risks" :key="risk.id" class="rounded-xl border border-[#E5E7EB] p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-extrabold text-[#111827]">{{ risk.resource }}</p>
                                        <p class="mt-1 text-xs font-bold text-[#667085]">{{ risk.project }} · {{ typeLabel(risk.type) }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-extrabold" :class="risk.days_left !== null && risk.days_left <= 7 ? 'bg-[#FEECEC] text-[#B42318]' : 'bg-[#FFF7E8] text-[#B45309]'">
                                        {{ risk.days_left ?? '?' }} дн.
                                    </span>
                                </div>
                                <p class="mt-2 text-xs font-bold text-[#667085]">До {{ formatDate(risk.expires_at) }}</p>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-[#667085]">Критичных сроков до 30 дней нет.</p>
                    </div>
                </aside>
            </section>

            <section class="rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                <div class="border-b border-[#E5E7EB] px-5 py-5">
                    <h2 class="text-xl font-extrabold text-[#111827]">Сводка по ресурсам</h2>
                    <p class="mt-1 text-sm text-[#667085]">Uptime, инциденты, downtime и средний ответ по сайтам и доменам.</p>
                </div>
                <div v-if="report.resources.length" class="overflow-x-auto">
                    <table class="report-table min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-normal text-[#8A9A91]">
                            <tr>
                                <th class="px-5 py-3 font-extrabold">Ресурс</th>
                                <th class="px-5 py-3 font-extrabold">Uptime</th>
                                <th class="px-5 py-3 font-extrabold">Инциденты</th>
                                <th class="px-5 py-3 font-extrabold">Downtime</th>
                                <th class="px-5 py-3 font-extrabold">Ответ</th>
                                <th class="px-5 py-3 font-extrabold">Последняя проверка</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="resource in report.resources" :key="resource.id">
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <p class="font-extrabold text-[#111827]">{{ resource.name }}</p>
                                    <p class="mt-1 text-xs font-bold text-[#98A2B3]">{{ resource.project }} · {{ resource.total_checks }} проверок</p>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 font-extrabold" :class="resource.uptime_percent >= 99 ? 'text-[#168A5A]' : 'text-[#B42318]'">{{ resource.uptime_percent }}%</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ resource.incident_count }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDuration(resource.downtime_seconds) }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ resource.avg_response_time_ms }} мс</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(resource.last_status)">{{ statusLabel(resource.last_status) }}</span>
                                    <p class="mt-2 text-xs font-bold text-[#98A2B3]">{{ formatDateTime(resource.last_check_at) }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="px-5 py-8 text-sm text-[#667085]">За выбранный период проверок нет.</p>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">
                <div class="rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] px-5 py-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">История инцидентов</h2>
                        <p class="mt-1 text-sm text-[#667085]">Последние проблемы за период отчета.</p>
                    </div>
                    <div v-if="report.incident_history.length" class="overflow-x-auto">
                    <table class="report-table min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-normal text-[#8A9A91]">
                                <tr>
                                    <th class="px-5 py-3 font-extrabold">Ресурс</th>
                                    <th class="px-5 py-3 font-extrabold">Причина</th>
                                    <th class="px-5 py-3 font-extrabold">Период</th>
                                    <th class="px-5 py-3 font-extrabold">Длительность</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="incident in report.incident_history" :key="incident.id">
                                    <td class="border-t border-[#E5E7EB] px-5 py-4">
                                        <p class="font-extrabold text-[#111827]">{{ incident.resource }}</p>
                                        <p class="mt-1 text-xs font-bold text-[#98A2B3]">{{ incident.project }} · {{ typeLabel(incident.type) }}</p>
                                    </td>
                                    <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ incident.title }}</td>
                                    <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDateTime(incident.started_at) }} - {{ formatDateTime(incident.resolved_at) }}</td>
                                    <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDuration(incident.duration_seconds) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="px-5 py-8 text-sm text-[#667085]">Инцидентов за выбранный период нет.</p>
                </div>

                <div class="rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] px-5 py-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Журнал уведомлений</h2>
                        <p class="mt-1 text-sm text-[#667085]">Что было отправлено пользователям и по каким каналам.</p>
                    </div>
                    <div v-if="report.notification_logs.length" class="divide-y divide-[#E5E7EB]">
                        <div v-for="log in report.notification_logs" :key="log.id" class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-extrabold text-[#111827]">{{ statusLabel(log.event_type) }}</p>
                                    <p class="mt-1 text-sm text-[#667085]">{{ log.channel_name ?? log.channel }} · {{ formatDateTime(log.sent_at ?? log.created_at) }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(log.status)">{{ statusLabel(log.status) }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="px-5 py-8 text-sm text-[#667085]">Уведомлений за выбранный период нет.</p>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>

<style scoped>
.report-page h2 {
    color: #26332D;
    font-weight: 500;
    letter-spacing: 0;
}

.report-page h2 + p {
    color: #6A7A70;
    font-weight: 400;
}

.report-page .font-semibold {
    font-weight: 500;
}

.report-page .text-lg.font-semibold,
.report-page .text-xl.font-semibold {
    font-weight: 500;
}

.report-page .divide-y p:first-child {
    color: #26332D;
    font-weight: 500;
}

.report-page .divide-y p + p {
    color: #6A7A70;
    font-weight: 400;
}

.report-page .divide-y span {
    font-weight: 500;
}

.report-table {
    color: #52645A;
}

.report-table thead {
    background: #F6FBF8;
}

.report-table th {
    color: #8A9A91;
    font-size: 0.72rem;
    font-weight: 500;
    letter-spacing: 0;
}

.report-table td {
    color: #52645A;
    font-weight: 400;
    vertical-align: middle;
}

.report-table td p:first-child,
.report-table td a {
    color: #26332D;
    font-weight: 500;
}

.report-table td.font-semibold {
    font-weight: 500;
}

.report-table td p + p {
    color: #8A9A91;
    font-weight: 400;
}

.report-table span {
    font-weight: 500;
}

@media print {
    :global(body) {
        background: white;
    }

    :global(.no-print),
    :global(aside),
    :global(header) {
        display: none !important;
    }

    .report-page {
        max-width: none;
        padding: 0;
    }
}
</style>
