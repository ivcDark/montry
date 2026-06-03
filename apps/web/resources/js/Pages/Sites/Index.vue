<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
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
    flash?: {
        error?: string | null
    }
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
const checkingSiteIds = ref<string[]>([])
const checkingStartedFrom = ref<Record<string, string | null>>({})
const checkingTimeouts = ref<Record<string, ReturnType<typeof setTimeout>>>({})
const siteLimitToastToken = ref(0)
const siteLimitToastMessage = ref<string | null>(page.props.flash?.error ?? null)

const filters = [
    { value: 'all', label: 'Все' },
    { value: 'ok', label: 'OK' },
    { value: 'down', label: 'Down' },
    { value: 'warning', label: 'Warning' },
    { value: 'paused', label: 'На паузе' },
    { value: 'empty', label: 'Без мониторингов' },
]

const filteredSites = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.sites.filter((site) => {
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

        return matchesSearch && matchesStatus
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
        empty: sites.filter((site) => site.status === 'empty').length,
    }
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

function statusLabel(status: string): string {
    if (status === 'ok') return 'OK'
    if (status === 'down') return 'Down'
    if (status === 'warning') return 'Warning'
    if (status === 'paused') return 'Paused'
    if (status === 'empty') return 'Empty'

    return 'Unknown'
}

function statusClass(status: string): string {
    if (status === 'ok') return 'bg-[#ECFDF3] text-[#16A34A]'
    if (status === 'down') return 'bg-[#FEECEC] text-[#EF4444]'
    if (status === 'warning') return 'bg-[#FFF7E8] text-[#F59E0B]'
    if (status === 'paused') return 'bg-[#F1F5F9] text-[#64748B]'

    return 'bg-[#EAF2FF] text-[#0F6BFF]'
}

function rowClass(status: string): string {
    if (status === 'down') return 'bg-[#FFF8F8]'
    if (status === 'warning') return 'bg-[#FFFCF4]'

    return 'bg-white'
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

function successfulMonitorsCount(site: Site): number {
    return site.monitors.filter((monitor) => monitorStatus(monitor) === 'ok').length
}

function monitorsSummaryClass(site: Site): string {
    if (site.monitors_count === 0) return 'text-[#64748B]'

    return successfulMonitorsCount(site) === site.monitors_count
        ? 'text-[#16A34A]'
        : 'text-[#EF4444]'
}

function monitorsSummaryTitle(site: Site): string {
    if (site.monitors_count === 0) return 'Мониторинги не настроены'

    const successful = successfulMonitorsCount(site)

    return successful === site.monitors_count
        ? 'Все мониторы успешно отработали в последней проверке'
        : `${successful} из ${site.monitors_count} мониторов успешно отработали в последней проверке`
}

function isChecking(site: Site): boolean {
    return checkingSiteIds.value.includes(site.id)
}

function isSiteDisabled(site: Site): boolean {
    return site.monitors_count > 0 && site.enabled_monitors_count === 0
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
    <FlashToast :message="siteLimitToastMessage" :token="siteLimitToastToken" />

    <DashboardLayout
        :organization="organization"
        active-item="sites"
        title="Сайты"
        subtitle="Сайты пользователя и состояние их мониторингов"
        :usage-current="stats.monitors"
    >
        <template #actions>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#98A2B3]">⌕</span>
                    <input
                        v-model="search"
                        type="search"
                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white pl-10 pr-4 text-sm outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15 sm:w-80"
                        placeholder="Поиск по сайту, домену или проекту"
                    >
                </div>

                <Link
                    href="/sites/create"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8]"
                    @click="handleCreateSiteClick"
                >
                    + Добавить сайт
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <section>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-extrabold text-[#111827]">Сводка по сайтам</h2>
                    <span v-if="stats.down" class="rounded-full bg-[#FEECEC] px-3 py-1 text-xs font-extrabold text-[#EF4444]">{{ stats.down }} Down</span>
                    <span v-if="stats.warning" class="rounded-full bg-[#FFF7E8] px-3 py-1 text-xs font-extrabold text-[#F59E0B]">{{ stats.warning }} Warning</span>
                    <span v-if="stats.paused" class="rounded-full bg-[#F1F5F9] px-3 py-1 text-xs font-extrabold text-[#64748B]">{{ stats.paused }} Paused</span>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Всего сайтов</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ stats.total }}</p>
                        <p class="mt-2 text-sm text-[#667085]">{{ stats.monitors }} мониторингов настроено</p>
                    </article>
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Работают</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#16A34A]">{{ stats.ok }}</p>
                        <p class="mt-2 text-sm text-[#667085]">Все проверки зеленые</p>
                    </article>
                    <article class="rounded-3xl border border-[#FECACA] bg-gradient-to-b from-white to-[#FFF8F8] p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Есть проблемы</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#EF4444]">{{ stats.down }}</p>
                        <p class="mt-2 text-sm text-[#667085]">Сайт недоступен или проверка упала</p>
                    </article>
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Предупреждения</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#F59E0B]">{{ stats.warning }}</p>
                        <p class="mt-2 text-sm text-[#667085]">Часть проверок требует внимания</p>
                    </article>
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Без мониторингов</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#0F6BFF]">{{ stats.empty }}</p>
                        <p class="mt-2 text-sm text-[#667085]">Нужно добавить проверки</p>
                    </article>
                </div>
            </section>

            <section class="mt-6 rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <h2 class="text-lg font-extrabold text-[#111827]">Фильтры</h2>
                <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="filter in filters"
                        :key="filter.value"
                        type="button"
                        class="h-9 shrink-0 rounded-full px-4 text-sm font-extrabold transition"
                        :class="statusFilter === filter.value ? 'bg-[#0F6BFF] text-white' : 'bg-[#F8FAFC] text-[#667085] hover:bg-[#EAF2FF] hover:text-[#0F6BFF]'"
                        @click="statusFilter = filter.value"
                    >
                        {{ filter.label }}
                    </button>
                </div>
            </section>

            <section class="mt-6 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="flex flex-col gap-4 border-b border-[#E5E7EB] p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-[#111827]">Список сайтов</h2>
                        <p class="mt-1 text-sm text-[#667085]">В строке видны общий статус сайта и сколько мониторингов успешно отработали в последней проверке.</p>
                    </div>
                    <p class="text-sm font-bold text-[#667085]">Показано: {{ filteredSites.length }}</p>
                </div>

                <div v-if="filteredSites.length" class="hidden overflow-x-auto lg:block">
                    <table class="min-w-[1130px] w-full table-fixed border-separate border-spacing-0 text-left text-sm">
                        <colgroup>
                            <col class="w-[240px]">
                            <col class="w-[150px]">
                            <col class="w-[180px]">
                            <col class="w-[140px]">
                            <col class="w-[150px]">
                            <col class="w-[270px]">
                        </colgroup>
                        <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                        <tr>
                            <th class="px-5 py-4">Сайт / домен</th>
                            <th class="px-5 py-4">Проект</th>
                            <th class="px-5 py-4">Статус</th>
                            <th class="px-5 py-4">Мониторы</th>
                            <th class="px-5 py-4">Последняя проверка</th>
                            <th class="px-5 py-4 text-right">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr
                            v-for="site in filteredSites"
                            :key="site.id"
                            :class="rowClass(site.status)"
                        >
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <Link :href="`/sites/${site.id}`" class="font-extrabold text-[#111827] hover:text-[#0F6BFF]">
                                    {{ site.name }}
                                </Link>
                                <p class="mt-1 max-w-64 truncate text-xs font-semibold text-[#667085]">{{ site.host ?? site.url }}</p>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                {{ site.project?.name ?? 'Без проекта' }}
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(site.status)">
                                    {{ statusLabel(site.status) }}
                                </span>
                                <p class="mt-2 text-xs font-semibold text-[#667085]">{{ site.problem_label }}</p>
                            </td>
                            <td
                                class="whitespace-nowrap border-t border-[#E5E7EB] px-5 py-4 text-base font-extrabold"
                                :class="monitorsSummaryClass(site)"
                                :title="monitorsSummaryTitle(site)"
                            >
                                {{ successfulMonitorsCount(site) }} / {{ site.monitors_count }}
                            </td>
                            <td class="whitespace-nowrap border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                {{ formatDate(site.last_checked_at) }}
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-[128px] shrink-0 items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition enabled:hover:border-[#0F6BFF] enabled:hover:text-[#0F6BFF] disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="site.enabled_monitors_count === 0 || isChecking(site)"
                                        @click="checkNow(site)"
                                    >
                                        <span
                                            v-if="isChecking(site)"
                                            class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-[#0F6BFF]/25 border-t-[#0F6BFF]"
                                            aria-hidden="true"
                                        />
                                        <span>{{ isSiteDisabled(site) ? 'Отключен' : isChecking(site) ? 'Проверяем...' : 'Проверить' }}</span>
                                    </button>
                                    <Link
                                        :href="`/sites/${site.id}`"
                                        class="inline-flex h-9 w-[84px] shrink-0 items-center justify-center rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                                    >
                                        Открыть
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="filteredSites.length" class="grid gap-3 p-4 lg:hidden">
                    <article
                        v-for="site in filteredSites"
                        :key="site.id"
                        class="rounded-2xl border border-[#E5E7EB] p-4 shadow-[0_10px_28px_rgba(15,23,42,0.05)]"
                        :class="rowClass(site.status)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-extrabold text-[#111827]">{{ site.name }}</h3>
                                <p class="mt-1 truncate text-xs font-semibold text-[#667085]">{{ site.host ?? site.url }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(site.status)">
                                {{ statusLabel(site.status) }}
                            </span>
                        </div>

                        <p class="mt-3 text-sm font-semibold text-[#667085]">{{ site.problem_label }}</p>

                        <div class="mt-4 grid gap-2 text-xs font-semibold text-[#667085]">
                            <p>Последняя проверка: {{ formatDate(site.last_checked_at) }}</p>
                            <p
                                class="text-sm font-extrabold"
                                :class="monitorsSummaryClass(site)"
                                :title="monitorsSummaryTitle(site)"
                            >
                                Мониторы: {{ successfulMonitorsCount(site) }} / {{ site.monitors_count }} успешно
                            </p>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold text-[#667085]">{{ site.project?.name ?? 'Без проекта' }}</p>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-[128px] shrink-0 items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-3 text-xs font-extrabold text-[#111827] transition enabled:hover:border-[#0F6BFF] enabled:hover:text-[#0F6BFF] disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="site.enabled_monitors_count === 0 || isChecking(site)"
                                    @click="checkNow(site)"
                                >
                                    <span
                                        v-if="isChecking(site)"
                                        class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-[#0F6BFF]/25 border-t-[#0F6BFF]"
                                        aria-hidden="true"
                                    />
                                    <span>{{ isSiteDisabled(site) ? 'Отключен' : isChecking(site) ? 'Проверяем...' : 'Проверить' }}</span>
                                </button>
                                <Link :href="`/sites/${site.id}`" class="text-sm font-extrabold text-[#0F6BFF] hover:text-[#0757D8]">
                                    Открыть
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-if="!filteredSites.length" class="p-10 text-center">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#EAF2FF] text-2xl font-extrabold text-[#0F6BFF]">＋</div>
                    <h3 class="mt-5 text-xl font-extrabold text-[#111827]">Сайты не найдены</h3>
                    <p class="mx-auto mt-2 max-w-md leading-7 text-[#667085]">
                        Добавьте первый сайт или измените фильтр, чтобы увидеть состояние мониторинга.
                    </p>
                    <Link
                        href="/sites/create"
                        class="mt-6 inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8]"
                        @click="handleCreateSiteClick"
                    >
                        Добавить сайт
                    </Link>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
