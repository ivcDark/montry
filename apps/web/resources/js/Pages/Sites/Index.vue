<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import {
    AlertTriangle,
    Check,
    CircleAlert,
    ExternalLink,
    FileDown,
    Globe2,
    LayoutGrid,
    LoaderCircle,
    Minus,
    Pause,
    Plus,
    RotateCw,
    Search,
    Table2,
    X,
} from '@lucide/vue'
import FlashToast from '@/Components/FlashToast.vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { useAutoRefresh } from '../../Composables/useAutoRefresh'

type Organization = {
    id: string
    name: string
}

type BillingUsage = {
    current: number
    limit: number | null
}

type PageProps = {
    billing?: {
        sites: BillingUsage
    } | null
}

type Project = {
    id: string
    name: string
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
    last_check_at: string | null
    next_check_at: string | null
    latest_result: LatestResult | null
}

type Site = {
    id: string
    name: string
    url: string
    host: string | null
    status: string
    raw_status: string
    problem_label: string
    monitors_count: number
    enabled_monitors_count: number
    last_checked_at: string | null
    project: Project | null
    monitors: Monitor[]
}

const props = defineProps<{
    organization: Organization
    sites: Site[]
}>()

const page = usePage<PageProps>()

useAutoRefresh({
    only: ['sites'],
    intervalMs: 20000,
})

const search = ref('')
const statusFilter = ref('all')
const projectFilter = ref('all')
const problemFilter = ref('all')
const sortMode = ref('problem_first')
const viewMode = ref<'table' | 'cards'>('table')
const checkingSiteIds = ref<string[]>([])
const checkingStartedFrom = ref<Record<string, string | null>>({})
const checkingTimeouts = ref<Record<string, ReturnType<typeof setTimeout>>>({})
const siteLimitToastToken = ref(0)
const siteLimitToastMessage = ref<string | null>(null)

const filters = [
    { value: 'all', label: 'Все' },
    { value: 'ok', label: 'Работают' },
    { value: 'down', label: 'Ошибки' },
    { value: 'warning', label: 'Предупреждения' },
    { value: 'paused', label: 'Приостановлены' },
]

const projectOptions = computed(() => {
    const projects = new Map<string, string>()

    props.sites.forEach((site) => {
        if (site.project) {
            projects.set(site.project.id, site.project.name)
        }
    })

    return Array.from(projects.entries()).map(([id, name]) => ({ id, name }))
})

const filteredSites = computed(() => {
    const query = search.value.trim().toLowerCase()

    const filtered = props.sites.filter((site) => {
        const searchable = [
            site.name,
            site.url,
            site.host,
            site.project?.name,
            site.problem_label,
            ...site.monitors.map((monitor) => `${monitor.name} ${monitor.type} ${monitor.status}`),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        const matchesSearch = !query || searchable.includes(query)
        const matchesStatus = statusFilter.value === 'all' || site.status === statusFilter.value
        const matchesProject = projectFilter.value === 'all' || site.project?.id === projectFilter.value
        const matchesProblem = problemFilter.value === 'all'
            || (problemFilter.value === 'problem' && ['down', 'warning'].includes(site.status))
            || (problemFilter.value === 'clean' && site.status === 'ok')

        return matchesSearch && matchesStatus && matchesProject && matchesProblem
    })

    return [...filtered].sort((first, second) => {
        if (sortMode.value === 'name') {
            return first.name.localeCompare(second.name, 'ru')
        }

        if (sortMode.value === 'recent') {
            return dateValue(second.last_checked_at) - dateValue(first.last_checked_at)
        }

        return statusPriority(first.status) - statusPriority(second.status)
    })
})

const stats = computed(() => {
    const sites = props.sites

    return {
        total: sites.length,
        monitors: sites.reduce((sum, site) => sum + site.monitors_count, 0),
        ok: sites.filter((site) => site.status === 'ok').length,
        down: sites.filter((site) => site.status === 'down').length,
        warning: sites.filter((site) => site.status === 'warning').length,
        paused: sites.filter((site) => site.status === 'paused').length,
    }
})

const problemSitesCount = computed(() => stats.value.down + stats.value.warning)
const hasProblemSites = computed(() => problemSitesCount.value > 0)
const hasActiveFilters = computed(() => {
    return search.value.trim() !== ''
        || statusFilter.value !== 'all'
        || projectFilter.value !== 'all'
        || problemFilter.value !== 'all'
        || sortMode.value !== 'problem_first'
})
const isSiteLimitExhausted = computed(() => {
    const usage = page.props.billing?.sites

    return usage?.limit !== null
        && usage?.limit !== undefined
        && usage.current >= usage.limit
})

watch(
    () => props.sites,
    (sites) => {
        checkingSiteIds.value.forEach((id) => {
            const refreshedSite = sites.find((site) => site.id === id)

            if (!refreshedSite || refreshedSite.last_checked_at !== checkingStartedFrom.value[id]) {
                stopChecking(id)
            }
        })
    },
)

onUnmounted(() => {
    Object.values(checkingTimeouts.value).forEach(clearTimeout)
})

function statusPriority(status: string): number {
    if (status === 'down') return 0
    if (status === 'warning') return 1
    if (status === 'ok') return 2
    if (status === 'paused') return 3
    if (status === 'empty') return 4

    return 5
}

function dateValue(value: string | null): number {
    return value ? new Date(value).getTime() : 0
}

function statusLabel(status: string): string {
    if (status === 'ok') return 'Работает'
    if (status === 'down') return 'Ошибка'
    if (status === 'warning') return 'Предупреждение'
    if (status === 'paused') return 'На паузе'
    if (status === 'empty') return 'Нет проверок'

    return 'Неизвестно'
}

function statusClass(status: string): string {
    if (status === 'ok') return 'bg-[#E9F8EF] text-[#159653]'
    if (status === 'down') return 'bg-[#FEECEC] text-[#E11D25]'
    if (status === 'warning') return 'bg-[#FFF7E8] text-[#D97706]'
    if (status === 'paused') return 'bg-[#ECEFF1] text-[#64706A]'

    return 'bg-[#F3F8F5] text-[#52645A]'
}

function rowClass(status: string): string {
    if (status === 'down') return 'bg-[#FFF8F8]'
    if (status === 'warning') return 'bg-[#FFFCF4]'
    if (status === 'paused') return 'bg-[#F6F7F7]'

    return 'bg-white'
}

function rowAccentClass(status: string): string {
    if (status === 'down') return 'bg-[#E11D25]'
    if (status === 'warning') return 'bg-[#E08600]'
    if (status === 'paused') return 'bg-[#8A948F]'

    return 'bg-[#159653]'
}

function statusIconBoxClass(status: string): string {
    if (status === 'down') return 'border-[#FFC7C7] bg-[#FEECEC] text-[#E11D25] [animation:pulse_2.8s_cubic-bezier(0.4,0,0.6,1)_infinite]'
    if (status === 'warning') return 'border-[#F7D59A] bg-[#FFF7E8] text-[#D97706]'
    if (status === 'paused') return 'border-[#D7DDDA] bg-[#ECEFF1] text-[#64706A]'

    return 'border-[#BFEBD0] bg-[#E9F8EF] text-[#159653]'
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

function monitorStatus(monitor: Monitor): string {
    if (!monitor.is_enabled || monitor.status === 'paused') return 'paused'
    if (monitor.latest_result?.status === 'success') return 'ok'
    if (monitor.latest_result?.status === 'failure') return 'down'
    if (monitor.latest_result?.status === 'warning') return 'warning'
    if (monitor.status === 'success' || monitor.status === 'up') return 'ok'
    if (monitor.status === 'failure' || monitor.status === 'down') return 'down'
    if (monitor.status === 'degraded' || monitor.status === 'warning') return 'warning'

    return 'unknown'
}

function monitorTypeLabel(type: string): string {
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

function monitorBadgeClass(monitor: Monitor): string {
    const status = monitorStatus(monitor)

    if (status === 'ok') return 'bg-[#E9F8EF] text-[#159653]'
    if (status === 'down') return 'bg-[#FEECEC] text-[#E11D25]'
    if (status === 'warning') return 'bg-[#FFF7E8] text-[#D97706]'

    return 'bg-[#ECEFF1] text-[#64706A]'
}

function successfulMonitorsCount(site: Site): number {
    return site.monitors.filter((monitor) => monitorStatus(monitor) === 'ok').length
}

function monitorsSummaryClass(site: Site): string {
    if (site.monitors_count === 0) return 'text-[#64736A]'

    return successfulMonitorsCount(site) === site.monitors_count
        ? 'text-[#159653]'
        : site.status === 'warning'
            ? 'text-[#D97706]'
            : 'text-[#E11D25]'
}

function successRateText(site: Site): string {
    if (site.monitors_count === 0) return '-'

    return `${Math.round((successfulMonitorsCount(site) / site.monitors_count) * 100)}%`
}

function responseTime(site: Site): number | null {
    return site.monitors
        .map((monitor) => monitor.latest_result?.response_time_ms)
        .find((value): value is number => typeof value === 'number') ?? null
}

function responseText(site: Site): string {
    const response = responseTime(site)

    return response === null ? '-' : `${response} мс`
}

function problemText(site: Site): string {
    if (site.status === 'ok') return 'Нет проблем'
    if (site.status === 'paused') return 'Мониторинг приостановлен'
    if (site.status === 'empty') return 'Мониторинги не настроены'

    return site.problem_label
}

function tinyBars(site: Site): number[] {
    const seed = site.name.length + site.url.length

    return Array.from({ length: 10 }, (_, index) => 12 + ((seed + index * 7) % 20))
}

function monitorIcon(monitor: Monitor): typeof Check {
    const status = monitorStatus(monitor)

    if (status === 'ok') return Check
    if (status === 'paused') return Minus
    if (status === 'warning') return AlertTriangle

    return X
}

function siteStatusIcon(status: string): typeof Check {
    if (status === 'ok') return Check
    if (status === 'down') return X
    if (status === 'warning') return AlertTriangle
    if (status === 'paused') return Pause

    return CircleAlert
}

function isChecking(site: Site): boolean {
    return checkingSiteIds.value.includes(site.id)
}

function stopChecking(siteId: string): void {
    checkingSiteIds.value = checkingSiteIds.value.filter((id) => id !== siteId)

    const nextStartedFrom = { ...checkingStartedFrom.value }
    delete nextStartedFrom[siteId]
    checkingStartedFrom.value = nextStartedFrom

    const timeout = checkingTimeouts.value[siteId]

    if (timeout) {
        clearTimeout(timeout)
    }

    const nextTimeouts = { ...checkingTimeouts.value }
    delete nextTimeouts[siteId]
    checkingTimeouts.value = nextTimeouts
}

function checkNow(site: Site): void {
    if (site.enabled_monitors_count === 0 || isChecking(site)) return

    router.post(`/sites/${site.id}/check-now`, {}, {
        preserveScroll: true,
        onStart: () => {
            checkingSiteIds.value = [...checkingSiteIds.value, site.id]
            checkingStartedFrom.value = {
                ...checkingStartedFrom.value,
                [site.id]: site.last_checked_at,
            }
            checkingTimeouts.value = {
                ...checkingTimeouts.value,
                [site.id]: setTimeout(() => stopChecking(site.id), 30000),
            }
        },
        onError: () => {
            stopChecking(site.id)
        },
        onCancel: () => {
            stopChecking(site.id)
        },
    })
}

function checkAllVisible(): void {
    filteredSites.value.forEach((site) => {
        if (!isChecking(site) && site.enabled_monitors_count > 0) {
            checkNow(site)
        }
    })
}

function showProblemSites(): void {
    problemFilter.value = 'all'
    statusFilter.value = 'down'
}

function selectStatusFilter(value: string): void {
    statusFilter.value = value

    if (value === 'all') {
        problemFilter.value = 'all'
    }
}

function resetFilters(): void {
    search.value = ''
    statusFilter.value = 'all'
    projectFilter.value = 'all'
    problemFilter.value = 'all'
    sortMode.value = 'problem_first'
}

function showSiteLimitToast(): void {
    siteLimitToastMessage.value = 'Лимит по сайтам исчерпан. Повысьте тариф для добавления сайта.'
    siteLimitToastToken.value += 1
}

function handleCreateSiteClick(event: MouseEvent): void {
    if (!isSiteLimitExhausted.value) {
        return
    }

    event.preventDefault()
    showSiteLimitToast()
}
</script>

<template>
    <Head title="Сайты" />
    <FlashToast :message="siteLimitToastMessage" :token="siteLimitToastToken" variant="error" />

    <DashboardLayout
        :organization="organization"
        active-item="sites"
        title="Сайты"
        subtitle="Контролируйте доступность, SSL, домены, DNS и технические проверки ваших сайтов."
        :usage-current="stats.monitors"
    >
        <template #header-actions>
            <span
                v-if="hasProblemSites"
                class="hidden h-10 items-center rounded-full bg-[#FFF4F4] px-4 text-sm font-medium text-[#E11D25] lg:inline-flex"
            >
                {{ problemSitesCount }} требуют внимания
            </span>
            <Link
                href="/sites/create"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#2FA568] px-5 text-sm font-medium text-white shadow-[0_12px_26px_rgba(47,165,104,0.18)] transition hover:bg-[#278C58]"
                @click="handleCreateSiteClick"
            >
                <Plus class="h-4 w-4" :stroke-width="2" />
                Добавить сайт
            </Link>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-5 sm:px-8 lg:py-6">
            <section class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold leading-tight tracking-normal text-[#17231C] sm:text-3xl">Сайты</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6A7A70] sm:text-base">
                        Контролируйте доступность, SSL, домены, DNS и технические проверки ваших сайтов.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] transition hover:border-[#B8D0C2]"
                        @click="checkAllVisible"
                    >
                        <RotateCw class="mr-2 h-4 w-4" :stroke-width="2" />
                        Запустить проверку всех
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#52645A] transition hover:border-[#B8D0C2]"
                    >
                        <FileDown class="mr-2 h-4 w-4" :stroke-width="2" />
                        Экспорт / Отчет
                    </button>
                </div>
            </section>

            <section class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                    <p class="text-3xl font-bold text-[#2FA568]">{{ stats.total }}</p>
                    <p class="mt-2 text-sm font-medium text-[#6A7A70]">Всего сайтов</p>
                </article>
                <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                    <p class="text-3xl font-bold text-[#2FA568]">{{ stats.ok }}</p>
                    <p class="mt-2 text-sm font-medium text-[#6A7A70]">Работают</p>
                </article>
                <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                    <p class="text-3xl font-bold text-[#D97706]">{{ stats.warning }}</p>
                    <p class="mt-2 text-sm font-medium text-[#6A7A70]">С предупреждениями</p>
                </article>
                <article class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                    <p class="text-3xl font-bold text-[#E11D25]">{{ stats.down }}</p>
                    <p class="mt-2 text-sm font-medium text-[#6A7A70]">С ошибками</p>
                </article>
            </section>

            <section
                v-if="hasProblemSites"
                class="mt-4 flex flex-col gap-3 rounded-2xl border border-[#FFB8B8] bg-[#FFF4F4] p-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex gap-3">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[#FEECEC] text-[#E11D25]">
                        <AlertTriangle class="h-4 w-4" :stroke-width="2" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-[#E11D25]">Есть сайты с ошибками</h2>
                        <p class="mt-1 text-sm leading-6 text-[#6A7A70]">
                            {{ problemSitesCount }} сайт(ов) сейчас требует внимания. Откройте проблемные сайты, чтобы посмотреть детали инцидента.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center rounded-xl bg-[#E11D25] px-4 text-sm font-medium text-white transition hover:bg-[#C9151C]"
                    @click="showProblemSites"
                >
                    <AlertTriangle class="mr-2 h-4 w-4" :stroke-width="2" />
                    Показать проблемные сайты
                </button>
            </section>

            <section class="mt-7 rounded-3xl border border-[#DDEBE3] bg-white p-4 shadow-[0_10px_28px_rgba(31,68,49,0.05)] sm:p-5">
                <div class="grid gap-3 xl:grid-cols-[minmax(260px,1fr)_170px_170px_210px_auto]">
                    <label class="relative block">
                        <Search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A9A91]" :stroke-width="2" />
                        <input
                            v-model="search"
                            type="search"
                            class="h-12 w-full rounded-xl border border-[#D4E3DA] bg-white pl-11 pr-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#98A69E] focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15"
                            placeholder="Поиск по домену или названию"
                        >
                    </label>

                    <select
                        v-model="projectFilter"
                        class="h-12 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#52645A] outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15"
                    >
                        <option value="all">Проект / клиент</option>
                        <option v-for="project in projectOptions" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>

                    <select
                        v-model="problemFilter"
                        class="h-12 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#52645A] outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15"
                    >
                        <option value="all">Тип проблемы</option>
                        <option value="problem">Есть проблема</option>
                        <option value="clean">Без проблем</option>
                    </select>

                    <select
                        v-model="sortMode"
                        class="h-12 rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#52645A] outline-none transition focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15"
                    >
                        <option value="problem_first">Сначала с ошибками</option>
                        <option value="recent">Сначала свежие</option>
                        <option value="name">По названию</option>
                    </select>

                    <div class="flex rounded-full bg-[#EEF4F0] p-1">
                        <button
                            type="button"
                            class="h-10 rounded-full px-4 text-sm font-medium transition"
                            :class="viewMode === 'table' ? 'bg-white text-[#173B2A] shadow-sm' : 'text-[#6A7A70]'"
                            @click="viewMode = 'table'"
                        >
                            <Table2 class="mr-1.5 inline h-4 w-4 align-[-3px]" :stroke-width="2" />
                            Таблица
                        </button>
                        <button
                            type="button"
                            class="h-10 rounded-full px-4 text-sm font-medium transition"
                            :class="viewMode === 'cards' ? 'bg-white text-[#173B2A] shadow-sm' : 'text-[#6A7A70]'"
                            @click="viewMode = 'cards'"
                        >
                            <LayoutGrid class="mr-1.5 inline h-4 w-4 align-[-3px]" :stroke-width="2" />
                            Карточки
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="filter in filters"
                            :key="filter.value"
                            type="button"
                            class="h-9 shrink-0 rounded-full px-4 text-sm font-medium transition"
                            :class="statusFilter === filter.value ? 'bg-[#E9F8EF] text-[#173B2A]' : 'text-[#6A7A70] hover:bg-[#F3F8F5]'"
                            @click="selectStatusFilter(filter.value)"
                        >
                            {{ filter.label }}
                        </button>
                    </div>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-full border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#52645A] transition hover:border-[#24A869] hover:text-[#173B2A]"
                        @click="resetFilters"
                    >
                        Сбросить фильтры
                    </button>
                </div>
            </section>

            <section
                v-if="filteredSites.length && viewMode === 'table'"
                class="mt-8 overflow-hidden rounded-3xl border border-[#DDEBE3] bg-white shadow-[0_16px_44px_rgba(31,68,49,0.06)]"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1020px] table-fixed border-separate border-spacing-0 text-left text-sm">
                        <colgroup>
                            <col class="w-[230px]">
                            <col class="w-[210px]">
                            <col class="w-[210px]">
                            <col class="w-[110px]">
                            <col class="w-[100px]">
                            <col class="w-[110px]">
                            <col class="w-[120px]">
                        </colgroup>
                        <thead class="bg-[#FBFDFC] text-xs font-semibold text-[#6A7A70]">
                            <tr>
                                <th class="px-5 py-4">Сайт</th>
                                <th class="px-5 py-4">Статус</th>
                                <th class="px-5 py-4">Проверки</th>
                                <th class="px-5 py-4">Успешность</th>
                                <th class="px-5 py-4">Ответ</th>
                                <th class="px-5 py-4">Последняя</th>
                                <th class="px-5 py-4">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="site in filteredSites"
                                :key="site.id"
                                :class="rowClass(site.status)"
                            >
                                <td class="relative border-t border-[#DDEBE3] px-5 py-4">
                                    <span class="absolute bottom-0 left-0 top-0 w-1" :class="rowAccentClass(site.status)"></span>
                                    <div class="flex items-start gap-3">
                                        <span
                                            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border"
                                            :class="isChecking(site) ? 'border-[#BFEBD0] bg-white text-[#24A869]' : statusIconBoxClass(site.status)"
                                        >
                                            <LoaderCircle v-if="isChecking(site)" class="h-4 w-4 animate-spin" :stroke-width="2.2" />
                                            <component v-else :is="siteStatusIcon(site.status)" class="h-4 w-4" :stroke-width="2.2" />
                                        </span>
                                        <div class="min-w-0">
                                            <Link :href="`/sites/${site.id}`" class="font-semibold text-[#17231C] hover:text-[#1E9B5D]">
                                                {{ site.name }}
                                            </Link>
                                            <p class="mt-1 max-w-44 truncate text-sm text-[#6A7A70]">{{ site.host ?? site.url }}</p>
                                            <p class="mt-2 inline-flex rounded-full bg-[#EEF4F0] px-3 py-1 text-xs font-medium text-[#52645A]">
                                                Проект: {{ site.project?.name ?? 'Без проекта' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="border-t border-[#DDEBE3] px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-medium" :class="statusClass(site.status)">
                                        <component :is="siteStatusIcon(site.status)" class="mr-1 inline h-3.5 w-3.5 align-[-3px]" :stroke-width="2.2" />
                                        {{ statusLabel(site.status) }}
                                    </span>
                                    <p
                                        class="mt-1.5 text-xs font-medium leading-5"
                                        :class="site.status === 'ok' ? 'text-[#159653]' : site.status === 'warning' ? 'text-[#D97706]' : site.status === 'down' ? 'text-[#E11D25]' : 'text-[#6A7A70]'"
                                    >
                                        {{ problemText(site) }}
                                    </p>
                                </td>

                                <td class="border-t border-[#DDEBE3] px-5 py-4">
                                    <p class="font-semibold" :class="monitorsSummaryClass(site)">
                                        {{ successfulMonitorsCount(site) }}/{{ site.monitors_count }} успешно
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <span
                                            v-for="monitor in site.monitors.slice(0, 5)"
                                            :key="monitor.id"
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="monitorBadgeClass(monitor)"
                                        >
                                            <component :is="monitorIcon(monitor)" class="h-3.5 w-3.5" :stroke-width="2.2" />
                                            {{ monitorTypeLabel(monitor.type) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="border-t border-[#DDEBE3] px-5 py-4">
                                    <p class="font-semibold" :class="site.status === 'down' ? 'text-[#E11D25]' : 'text-[#159653]'">{{ successRateText(site) }}</p>
                                    <div class="mt-2 flex h-7 items-end gap-1">
                                        <span
                                            v-for="(height, index) in tinyBars(site)"
                                            :key="index"
                                            class="w-1.5 rounded-t-full"
                                            :class="site.status === 'down' ? 'bg-[#EF6B6B]' : site.status === 'warning' ? 'bg-[#F3A83B]' : 'bg-[#62C98F]'"
                                            :style="{ height: `${height}px` }"
                                        ></span>
                                    </div>
                                </td>

                                <td class="border-t border-[#DDEBE3] px-5 py-4">
                                    <p class="font-semibold text-[#26332D]">{{ responseText(site) }}</p>
                                </td>

                                <td class="whitespace-nowrap border-t border-[#DDEBE3] px-5 py-4 text-[#6A7A70]">
                                    {{ relativeDate(site.last_checked_at) }}
                                </td>

                                <td class="border-t border-[#DDEBE3] px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#D4E3DA] text-xs font-medium text-[#26332D] transition enabled:hover:border-[#24A869] enabled:hover:text-[#1E9B5D] disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="site.enabled_monitors_count === 0 || isChecking(site)"
                                            title="Проверить сейчас"
                                            @click="checkNow(site)"
                                        >
                                            <span
                                                v-if="isChecking(site)"
                                                class="h-4 w-4 animate-spin rounded-full border-2 border-[#24A869]/25 border-t-[#24A869]"
                                                aria-hidden="true"
                                            />
                                            <RotateCw v-else class="h-4 w-4" :stroke-width="2" />
                                        </button>
                                        <Link
                                            :href="`/sites/${site.id}`"
                                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#D4E3DA] px-4 text-sm font-medium text-[#26332D] transition hover:border-[#24A869] hover:text-[#1E9B5D]"
                                        >
                                            <ExternalLink class="h-4 w-4" :stroke-width="2" />
                                            Открыть
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-if="filteredSites.length && viewMode === 'cards'"
                class="mt-8 grid gap-4 lg:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="site in filteredSites"
                    :key="site.id"
                    class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]"
                    :class="rowClass(site.status)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-lg font-semibold text-[#17231C]">{{ site.name }}</h3>
                            <p class="mt-1 truncate text-sm text-[#6A7A70]">{{ site.host ?? site.url }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium" :class="statusClass(site.status)">
                            <component :is="siteStatusIcon(site.status)" class="mr-1 inline h-3.5 w-3.5 align-[-3px]" :stroke-width="2.2" />
                            {{ statusLabel(site.status) }}
                        </span>
                    </div>

                    <p class="mt-4 text-sm font-medium" :class="site.status === 'ok' ? 'text-[#159653]' : site.status === 'warning' ? 'text-[#D97706]' : site.status === 'down' ? 'text-[#E11D25]' : 'text-[#6A7A70]'">
                        {{ problemText(site) }}
                    </p>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <div class="rounded-2xl bg-[#F3F8F5] p-3">
                            <p class="text-sm font-semibold" :class="monitorsSummaryClass(site)">{{ successfulMonitorsCount(site) }}/{{ site.monitors_count }}</p>
                            <p class="mt-1 text-xs text-[#6A7A70]">проверки</p>
                        </div>
                        <div class="rounded-2xl bg-[#F3F8F5] p-3">
                            <p class="text-sm font-semibold text-[#26332D]">{{ responseText(site) }}</p>
                            <p class="mt-1 text-xs text-[#6A7A70]">ответ</p>
                        </div>
                        <div class="rounded-2xl bg-[#F3F8F5] p-3">
                            <p class="text-sm font-semibold text-[#26332D]">{{ successRateText(site) }}</p>
                            <p class="mt-1 text-xs text-[#6A7A70]">успех</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        <span
                            v-for="monitor in site.monitors.slice(0, 5)"
                            :key="monitor.id"
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="monitorBadgeClass(monitor)"
                        >
                            <component :is="monitorIcon(monitor)" class="h-3.5 w-3.5" :stroke-width="2.2" />
                            {{ monitorTypeLabel(monitor.type) }}
                        </span>
                    </div>

                    <div class="mt-5 flex items-center justify-between gap-3">
                        <p class="text-xs font-medium text-[#6A7A70]">{{ relativeDate(site.last_checked_at) }}</p>
                        <Link :href="`/sites/${site.id}`" class="text-sm font-medium text-[#1E9B5D] hover:text-[#167D49]">Открыть</Link>
                    </div>
                </article>
            </section>

            <section v-if="!filteredSites.length" class="mt-8 rounded-3xl border border-[#DDEBE3] bg-white p-10 text-center shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#E9F8EF] text-[#1E9B5D]">
                    <Globe2 class="h-6 w-6" :stroke-width="2" />
                </div>
                <h3 class="mt-5 text-xl font-semibold text-[#17231C]">Сайты не найдены</h3>
                <p class="mx-auto mt-2 max-w-md leading-7 text-[#6A7A70]">
                    Добавьте первый сайт или измените фильтры, чтобы увидеть состояние мониторинга.
                </p>
                <Link
                    href="/sites/create"
                    class="mt-6 inline-flex h-11 items-center justify-center rounded-xl bg-[#2FA568] px-5 text-sm font-medium text-white transition hover:bg-[#278C58]"
                    @click="handleCreateSiteClick"
                >
                    Добавить сайт
                </Link>
            </section>
        </div>
    </DashboardLayout>
</template>
