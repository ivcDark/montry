<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js'
import { Bar, Doughnut } from 'vue-chartjs'
import TariffRestriction from '@/Components/TariffRestriction.vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend)

type Organization = {
    id: number | string
    name: string
}

type Summary = {
    open_incidents: number
    resolved_last_24_hours: number
    downtime_30_days_seconds: number
    warnings: number
}

type Filters = {
    search: string
    period: string
    type: string
    date_from: string
    date_to: string
    project_id: number | null
}

type MonitorTypeOption = {
    value: string
    code?: string
    label: string
    name?: string
    short_label?: string
    sort_order?: number
}

type Incident = {
    id: number
    site_id: number
    monitor_id: number
    site: string
    target: string | null
    project: string
    type: string
    status: string
    severity: string
    title: string
    summary: string | null
    started_at: string | null
    resolved_at: string | null
    duration_seconds: number | null
    current_duration_seconds: number | null
}

type AnalyticsAccess = {
    enabled: boolean
    plan_code: string
    retention_days: number
}

type WeeklyDigestPreference = {
    enabled: boolean
    send_time: string
    timezone: string
}

type AnalyticsProject = {
    id: number
    name: string
    incident_count: number
    downtime_seconds: number
    mttr_seconds: number
    affected_sites: number
}

type AnalyticsSite = {
    id: number
    name: string
    incident_count: number
    downtime_seconds: number
    mttr_seconds: number
    last_incident_at: string | null
}

type IncidentAnalytics = {
    kpi: {
        total_incidents: number
        active_incidents: number
        downtime_seconds: number
        mttr_seconds: number
    }
    comparison: {
        total_incidents_delta: number
        downtime_seconds_delta: number
        mttr_seconds_delta: number
    }
    series: {
        incident_counts: Array<{ date: string; value: number }>
        downtime_seconds: Array<{ date: string; value: number }>
    }
    type_distribution: Record<string, number>
    projects: AnalyticsProject[]
    selected_project_id: number | null
    selected_project: { id: number; name: string } | null
    sites: AnalyticsSite[]
    top_sites: Array<Pick<AnalyticsSite, 'id' | 'name' | 'incident_count' | 'downtime_seconds'>>
}

const props = defineProps<{
    organization: Organization
    summary: Summary
    filters: Filters
    analyticsAccess: AnalyticsAccess
    weeklyDigestPreference: WeeklyDigestPreference
    analytics: IncidentAnalytics | null
    activeIncidents: Incident[]
    resolvedIncidents: Incident[]
    warnings: Incident[]
    monitorTypes: MonitorTypeOption[]
}>()

const search = ref(props.filters.search)
const period = ref(props.filters.period)
const type = ref(props.filters.type)
const dateFrom = ref(props.filters.date_from)
const dateTo = ref(props.filters.date_to)
const projectId = ref(props.filters.project_id ? String(props.filters.project_id) : '')
const weeklyDigestEnabled = ref(props.weeklyDigestPreference.enabled)
const weeklyDigestTime = ref(props.weeklyDigestPreference.send_time)

const totalVisibleItems = computed(() => props.activeIncidents.length + props.resolvedIncidents.length + props.warnings.length)
const selectedProjectId = computed(() => props.analytics?.selected_project_id ? String(props.analytics.selected_project_id) : '')
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
const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
        },
    },
}

const incidentCountChartData = computed(() => ({
    labels: props.analytics?.series.incident_counts.map((point) => shortDate(point.date)) ?? [],
    datasets: [
        {
            label: 'Инциденты',
            data: props.analytics?.series.incident_counts.map((point) => point.value) ?? [],
            backgroundColor: '#0F6BFF',
            borderRadius: 6,
        },
    ],
}))

const downtimeChartData = computed(() => ({
    labels: props.analytics?.series.downtime_seconds.map((point) => shortDate(point.date)) ?? [],
    datasets: [
        {
            label: 'Downtime, мин',
            data: props.analytics?.series.downtime_seconds.map((point) => Math.round(point.value / 60)) ?? [],
            backgroundColor: '#EF4444',
            borderRadius: 6,
        },
    ],
}))

const typeDistributionRows = computed(() => {
    const distribution = props.analytics?.type_distribution ?? {}

    return Object.entries(distribution)
        .map(([code, value]) => ({
            code,
            label: typeLabel(code),
            value,
            sort_order: props.monitorTypes.find((type) => (type.code ?? type.value) === code)?.sort_order ?? 1000,
        }))
        .sort((a, b) => a.sort_order - b.sort_order)
})

const typeDistributionChartData = computed(() => ({
    labels: typeDistributionRows.value.map((row) => row.label),
    datasets: [
        {
            data: typeDistributionRows.value.map((row) => row.value),
            backgroundColor: ['#0F6BFF', '#12B3A8', '#F59E0B', '#7C3AED', '#64748B', '#EF4444', '#10B981', '#F97316'],
        },
    ],
}))

function applyFilters(): void {
    router.get('/incidents', {
        search: search.value || undefined,
        period: period.value,
        type: type.value,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        project_id: projectId.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function resetFilters(): void {
    search.value = ''
    period.value = 'max'
    type.value = 'all'
    dateFrom.value = ''
    dateTo.value = ''
    projectId.value = ''
    applyFilters()
}

function selectProject(id: number): void {
    projectId.value = String(id)
    applyFilters()
}

function handleProjectChange(event: Event): void {
    const target = event.target as HTMLSelectElement
    selectProject(Number(target.value))
}

function saveWeeklyDigestPreference(): void {
    router.put('/incidents/weekly-digest-preference', {
        enabled: weeklyDigestEnabled.value,
        send_time: weeklyDigestTime.value,
    }, {
        preserveScroll: true,
        preserveState: true,
    })
}

function checkNow(incident: Incident): void {
    router.post(`/monitors/${incident.monitor_id}/check-now`, {}, {
        preserveScroll: true,
    })
}

function formatDateTime(value: string | null): string {
    if (!value) return '—'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function formatDuration(seconds: number | null): string {
    if (!seconds) return '—'
    if (seconds < 60) return `${seconds} сек`
    if (seconds < 3600) return `${Math.round(seconds / 60)} мин`
    if (seconds < 86400) return `${Math.round(seconds / 3600)} ч`

    return `${Math.round(seconds / 86400)} дн`
}

function shortDate(value: string): string {
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
    }).format(new Date(value))
}

function signedNumber(value: number): string {
    if (value > 0) return `+${value}`
    return String(value)
}

function typeLabel(value: string): string {
    const option = props.monitorTypes.find((type) => (type.code ?? type.value) === value)

    return option?.short_label
        ?? option?.name
        ?? option?.label
        ?? value.toUpperCase()
}

function statusLabel(value: string): string {
    return value === 'open' ? 'Открыт' : 'Решен'
}

function severityClass(value: string): string {
    if (value === 'warning') return 'bg-[#FFF7E8] text-[#B45309]'
    if (value === 'critical') return 'bg-[#FEECEC] text-[#B42318]'

    return 'bg-[#FEECEC] text-[#EF4444]'
}
</script>

<template>
    <Head title="Инциденты" />

    <DashboardLayout
        :organization="organization"
        active-item="incidents"
        title="Инциденты"
        subtitle="Открытые проблемы, история простоев и предупреждения по мониторингам"
    >
        <template #actions>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#98A2B3]">⌕</span>
                    <input
                        v-model="search"
                        type="search"
                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white pl-10 pr-4 text-sm outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15 sm:w-80"
                        placeholder="Сайт, домен, проект или причина"
                        @keyup.enter="applyFilters"
                    >
                </div>

                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8]"
                    @click="applyFilters"
                >
                    Найти
                </button>
            </div>
        </template>

        <div class="mx-auto grid max-w-7xl gap-6 px-5 py-6 sm:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Открытые инциденты</p>
                    <p class="mt-3 text-4xl font-extrabold" :class="summary.open_incidents ? 'text-[#EF4444]' : 'text-[#16A34A]'">{{ summary.open_incidents }}</p>
                </div>
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Решено за 24 часа</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ summary.resolved_last_24_hours }}</p>
                </div>
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Downtime за 30 дней</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ formatDuration(summary.downtime_30_days_seconds) }}</p>
                </div>
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Предупреждения</p>
                    <p class="mt-3 text-4xl font-extrabold" :class="summary.warnings ? 'text-[#B45309]' : 'text-[#16A34A]'">{{ summary.warnings }}</p>
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_16px_44px_rgba(15,23,42,0.06)] lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-3">
                    <select v-model="period" class="h-11 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-bold text-[#111827] outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" @change="applyFilters">
                        <option value="1">24 часа</option>
                        <option value="7">7 дней</option>
                        <option value="max">Доступный период</option>
                    </select>
                    <select v-model="type" class="h-11 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-bold text-[#111827] outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" @change="applyFilters">
                        <option value="all">Все проверки</option>
                        <option
                            v-for="monitorType in monitorTypes"
                            :key="monitorType.code ?? monitorType.value"
                            :value="monitorType.code ?? monitorType.value"
                        >
                            {{ monitorType.short_label ?? monitorType.name ?? monitorType.label }}
                        </option>
                    </select>
                    <input v-model="dateFrom" type="date" class="h-11 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-bold text-[#111827] outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" @change="applyFilters">
                    <input v-model="dateTo" type="date" class="h-11 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-bold text-[#111827] outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" @change="applyFilters">
                    <button type="button" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#667085] transition hover:border-[#CBD5E1] hover:text-[#111827]" @click="resetFilters">
                        Сбросить
                    </button>
                </div>
                <p class="text-sm font-bold text-[#667085]">Найдено: {{ totalVisibleItems }}</p>
            </section>

            <section v-if="!analyticsAccess.enabled" class="rounded-2xl border border-[#D8E2F0] bg-white p-6 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                <p class="text-sm font-bold text-[#1E9B5D]">Аналитика инцидентов</p>
                <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">История и динамика инцидентов</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#667085]">
                    Смотрите downtime, проблемные проекты и сайты за выбранный период. На текущем тарифе доступен оперативный список без аналитических отчётов.
                </p>
                <TariffRestriction action="Открыть аналитику" class="mt-5 w-fit" />
            </section>

            <section v-else-if="analytics" class="grid gap-6">
                <div class="flex flex-col gap-4 rounded-2xl border border-[#D8E2F0] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)] lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-bold text-[#0F6BFF]">Еженедельный отчет</p>
                        <h2 class="mt-1 text-xl font-extrabold text-[#111827]">Email-итоги по инцидентам</h2>
                        <p class="mt-1 text-sm text-[#667085]">Отправляем по понедельникам за прошлую календарную неделю. Время указано по Москве.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <label class="inline-flex items-center gap-3 text-sm font-extrabold text-[#111827]">
                            <input v-model="weeklyDigestEnabled" type="checkbox" class="h-5 w-5 rounded border-[#CBD5E1] text-[#0F6BFF]" @change="saveWeeklyDigestPreference">
                            Получать отчет
                        </label>
                        <input v-model="weeklyDigestTime" type="time" class="h-11 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-bold text-[#111827] outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" @change="saveWeeklyDigestPreference">
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Инциденты за период</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ analytics.kpi.total_incidents }}</p>
                        <p class="mt-2 text-xs font-bold" :class="analytics.comparison.total_incidents_delta > 0 ? 'text-[#EF4444]' : 'text-[#16A34A]'">
                            {{ signedNumber(analytics.comparison.total_incidents_delta) }} к прошлому периоду
                        </p>
                    </div>
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Активные</p>
                        <p class="mt-3 text-4xl font-extrabold" :class="analytics.kpi.active_incidents ? 'text-[#EF4444]' : 'text-[#16A34A]'">{{ analytics.kpi.active_incidents }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Downtime</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ formatDuration(analytics.kpi.downtime_seconds) }}</p>
                        <p class="mt-2 text-xs font-bold" :class="analytics.comparison.downtime_seconds_delta > 0 ? 'text-[#EF4444]' : 'text-[#16A34A]'">
                            {{ formatDuration(Math.abs(analytics.comparison.downtime_seconds_delta)) }} {{ analytics.comparison.downtime_seconds_delta > 0 ? 'хуже' : 'лучше/без изменений' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">MTTR</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ formatDuration(analytics.kpi.mttr_seconds) }}</p>
                        <p class="mt-2 text-xs font-bold text-[#667085]">Среднее время восстановления</p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[300px_minmax(0,1fr)]">
                    <aside class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-extrabold text-[#111827]">Проекты</h2>
                                <p class="mt-1 text-sm text-[#667085]">Рейтинг по инцидентам</p>
                            </div>
                            <span class="rounded-full bg-[#EEF4FF] px-3 py-1 text-xs font-extrabold text-[#0F6BFF]">{{ analyticsAccess.retention_days }} дн.</span>
                        </div>

                        <select
                            class="mt-4 h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-bold text-[#111827] outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15 xl:hidden"
                            :value="selectedProjectId"
                            @change="handleProjectChange"
                        >
                            <option v-for="project in analytics.projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                        </select>

                        <div class="mt-4 hidden gap-2 xl:grid">
                            <button
                                v-for="project in analytics.projects"
                                :key="project.id"
                                type="button"
                                class="rounded-xl border p-4 text-left transition"
                                :class="project.id === analytics.selected_project_id ? 'border-[#0F6BFF] bg-[#EEF4FF]' : 'border-[#E5E7EB] bg-white hover:border-[#CBD5E1]'"
                                @click="selectProject(project.id)"
                            >
                                <span class="block text-sm font-extrabold text-[#111827]">{{ project.name }}</span>
                                <span class="mt-2 block text-xs font-bold text-[#667085]">{{ project.incident_count }} инц. · {{ formatDuration(project.downtime_seconds) }} · {{ project.affected_sites }} сайт.</span>
                            </button>
                            <p v-if="!analytics.projects.length" class="rounded-xl border border-dashed border-[#CBD5E1] p-4 text-sm text-[#667085]">За период инцидентов по проектам нет.</p>
                        </div>
                    </aside>

                    <div class="grid gap-6">
                        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.6fr)]">
                            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                                <h2 class="text-lg font-extrabold text-[#111827]">Динамика инцидентов</h2>
                                <p class="mt-1 text-sm text-[#667085]">{{ analytics.selected_project?.name ?? 'Все проекты' }}</p>
                                <div class="mt-5 h-64">
                                    <Bar :data="incidentCountChartData" :options="chartOptions" />
                                </div>
                            </div>
                            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                                <h2 class="text-lg font-extrabold text-[#111827]">Типы</h2>
                                <p class="mt-1 text-sm text-[#667085]">Распределение по типам из каталога</p>
                                <div class="mt-5 h-64">
                                    <Doughnut :data="typeDistributionChartData" :options="doughnutOptions" />
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
                            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                                <h2 class="text-lg font-extrabold text-[#111827]">Downtime по дням</h2>
                                <p class="mt-1 text-sm text-[#667085]">Минуты простоя за выбранный период</p>
                                <div class="mt-5 h-64">
                                    <Bar :data="downtimeChartData" :options="chartOptions" />
                                </div>
                            </div>
                            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                                <h2 class="text-lg font-extrabold text-[#111827]">Проблемные сайты</h2>
                                <div v-if="analytics.top_sites.length" class="mt-4 grid gap-3">
                                    <div v-for="site in analytics.top_sites" :key="site.id" class="rounded-xl border border-[#E5E7EB] p-3">
                                        <p class="font-extrabold text-[#111827]">{{ site.name }}</p>
                                        <p class="mt-1 text-xs font-bold text-[#667085]">{{ site.incident_count }} инц. · {{ formatDuration(site.downtime_seconds) }}</p>
                                    </div>
                                </div>
                                <p v-else class="mt-4 text-sm text-[#667085]">За период проблемных сайтов нет.</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                            <div class="border-b border-[#E5E7EB] px-5 py-5">
                                <h2 class="text-lg font-extrabold text-[#111827]">Сайты проекта</h2>
                                <p class="mt-1 text-sm text-[#667085]">Инциденты, downtime и MTTR по выбранному проекту.</p>
                            </div>
                            <div v-if="analytics.sites.length" class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="text-xs uppercase tracking-normal text-[#98A2B3]">
                                        <tr>
                                            <th class="px-5 py-3 font-extrabold">Сайт</th>
                                            <th class="px-5 py-3 font-extrabold">Инциденты</th>
                                            <th class="px-5 py-3 font-extrabold">Downtime</th>
                                            <th class="px-5 py-3 font-extrabold">MTTR</th>
                                            <th class="px-5 py-3 font-extrabold">Последний</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="site in analytics.sites" :key="site.id">
                                            <td class="border-t border-[#E5E7EB] px-5 py-4 font-extrabold text-[#111827]">{{ site.name }}</td>
                                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ site.incident_count }}</td>
                                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDuration(site.downtime_seconds) }}</td>
                                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDuration(site.mttr_seconds) }}</td>
                                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDateTime(site.last_incident_at) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="px-5 py-8 text-sm text-[#667085]">В выбранном проекте за период инцидентов нет.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                <div class="border-b border-[#E5E7EB] px-5 py-5">
                    <h2 class="text-xl font-extrabold text-[#111827]">Активные инциденты</h2>
                    <p class="mt-1 text-sm text-[#667085]">Реальные проблемы, которые требуют внимания сейчас.</p>
                </div>

                <div v-if="activeIncidents.length" class="divide-y divide-[#E5E7EB]">
                    <article v-for="incident in activeIncidents" :key="incident.id" class="grid gap-4 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="severityClass(incident.severity)">{{ statusLabel(incident.status) }}</span>
                                <span class="rounded-full bg-[#EEF4FF] px-3 py-1 text-xs font-extrabold text-[#0F6BFF]">{{ typeLabel(incident.type) }}</span>
                                <span class="text-xs font-bold text-[#98A2B3]">{{ incident.project }}</span>
                            </div>
                            <h3 class="mt-3 text-lg font-extrabold text-[#111827]">{{ incident.site }}</h3>
                            <p class="mt-1 text-sm font-bold text-[#EF4444]">{{ incident.title }}</p>
                            <p v-if="incident.summary" class="mt-2 text-sm leading-6 text-[#667085]">{{ incident.summary }}</p>
                            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-[#667085]">
                                <span>Начало: {{ formatDateTime(incident.started_at) }}</span>
                                <span>Длится: {{ formatDuration(incident.current_duration_seconds) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <a v-if="incident.target" :href="incident.target" target="_blank" rel="noreferrer" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#111827] transition hover:border-[#CBD5E1]">
                                Открыть сайт
                            </a>
                            <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl bg-[#0F6BFF] px-4 text-sm font-extrabold text-white transition hover:bg-[#0757D8]" @click="checkNow(incident)">
                                Проверить сейчас
                            </button>
                            <Link :href="`/sites/${incident.site_id}`" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#111827] transition hover:border-[#CBD5E1]">
                                К сайту
                            </Link>
                        </div>
                    </article>
                </div>
                <p v-else class="px-5 py-8 text-sm text-[#667085]">Открытых инцидентов нет.</p>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)]">
                <div class="rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] px-5 py-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">История инцидентов</h2>
                        <p class="mt-1 text-sm text-[#667085]">Решенные проблемы за выбранный период.</p>
                    </div>

                    <div v-if="resolvedIncidents.length" class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-normal text-[#98A2B3]">
                                <tr>
                                    <th class="px-5 py-3 font-extrabold">Сайт</th>
                                    <th class="px-5 py-3 font-extrabold">Причина</th>
                                    <th class="px-5 py-3 font-extrabold">Период</th>
                                    <th class="px-5 py-3 font-extrabold">Длительность</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="incident in resolvedIncidents" :key="incident.id">
                                    <td class="border-t border-[#E5E7EB] px-5 py-4">
                                        <Link :href="`/sites/${incident.site_id}`" class="font-extrabold text-[#111827] hover:text-[#0F6BFF]">{{ incident.site }}</Link>
                                        <p class="mt-1 text-xs font-semibold text-[#98A2B3]">{{ incident.project }} · {{ typeLabel(incident.type) }}</p>
                                    </td>
                                    <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ incident.title }}</td>
                                    <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDateTime(incident.started_at) }} - {{ formatDateTime(incident.resolved_at) }}</td>
                                    <td class="border-t border-[#E5E7EB] px-5 py-4 font-extrabold text-[#111827]">{{ formatDuration(incident.duration_seconds) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="px-5 py-8 text-sm text-[#667085]">Решенных инцидентов за период нет.</p>
                </div>

                <div class="rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_16px_44px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] px-5 py-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Предупреждения</h2>
                        <p class="mt-1 text-sm text-[#667085]">Не аварии, но требуют внимания до дедлайна.</p>
                    </div>

                    <div v-if="warnings.length" class="divide-y divide-[#E5E7EB]">
                        <article v-for="warning in warnings" :key="warning.id" class="px-5 py-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-[#FFF7E8] px-3 py-1 text-xs font-extrabold text-[#B45309]">Warning</span>
                                <span class="rounded-full bg-[#F2F4F7] px-3 py-1 text-xs font-extrabold text-[#667085]">{{ typeLabel(warning.type) }}</span>
                            </div>
                            <h3 class="mt-3 text-base font-extrabold text-[#111827]">{{ warning.site }}</h3>
                            <p class="mt-1 text-sm font-bold text-[#B45309]">{{ warning.title }}</p>
                            <p v-if="warning.summary" class="mt-2 text-sm leading-6 text-[#667085]">{{ warning.summary }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link :href="`/sites/${warning.site_id}`" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#111827] transition hover:border-[#CBD5E1]">
                                    К сайту
                                </Link>
                                <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#111827] transition hover:border-[#CBD5E1]" @click="checkNow(warning)">
                                    Проверить
                                </button>
                            </div>
                        </article>
                    </div>
                    <p v-else class="px-5 py-8 text-sm text-[#667085]">Активных предупреждений нет.</p>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
