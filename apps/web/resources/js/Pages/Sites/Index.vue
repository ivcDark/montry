<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Organization = {
    id: string
    name: string
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

const search = ref('')
const statusFilter = ref('all')

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
    if (monitor.status === 'success' || monitor.status === 'up') return 'ok'
    if (monitor.status === 'failure' || monitor.status === 'down') return 'down'
    if (monitor.status === 'degraded' || monitor.status === 'warning' || monitor.latest_result?.status === 'warning') return 'warning'

    return 'unknown'
}

function monitorByType(site: Site, type: string): Monitor | null {
    return site.monitors.find((monitor) => monitor.type === type) ?? null
}

function monitorSummary(site: Site, type: string): string {
    const monitor = monitorByType(site, type)

    if (!monitor) return 'Не настроен'
    if (!monitor.is_enabled) return 'На паузе'
    if (monitor.latest_result?.error_message) return monitor.latest_result.error_message

    if (type === 'http') {
        const statusCode = monitor.latest_result?.status_code ? `${monitor.latest_result.status_code}` : statusLabel(monitorStatus(monitor))
        const responseTime = monitor.latest_result?.response_time_ms ? ` · ${monitor.latest_result.response_time_ms} мс` : ''

        return `${statusCode}${responseTime}`
    }

    const days = monitor.latest_result?.normalized_result.days_until_expiration

    if (typeof days === 'number') {
        return `${days} ${dayWord(days)}`
    }

    return statusLabel(monitorStatus(monitor))
}

function dayWord(days: number): string {
    const mod10 = days % 10
    const mod100 = days % 100

    if (mod10 === 1 && mod100 !== 11) return 'день'
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'дня'

    return 'дней'
}

function checkNow(monitor: Monitor | null): void {
    if (!monitor || !monitor.is_enabled) return

    router.post(`/monitors/${monitor.id}/check-now`, {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Сайты" />

    <DashboardLayout
        :organization="organization"
        active-item="sites"
        title="Сайты"
        subtitle="Сайты пользователя и состояние их HTTP, SSL и доменных проверок"
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
                        <p class="mt-2 text-sm text-[#667085]">SSL или домен требуют внимания</p>
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
                        <p class="mt-1 text-sm text-[#667085]">В строке видны общий статус сайта и ключевые проверки: HTTP, SSL и домен.</p>
                    </div>
                    <p class="text-sm font-bold text-[#667085]">Показано: {{ filteredSites.length }}</p>
                </div>

                <div v-if="filteredSites.length" class="hidden overflow-x-auto lg:block">
                    <table class="min-w-[1080px] w-full border-separate border-spacing-0 text-left text-sm">
                        <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                        <tr>
                            <th class="px-5 py-4">Сайт / домен</th>
                            <th class="px-5 py-4">Проект</th>
                            <th class="px-5 py-4">Статус</th>
                            <th class="px-5 py-4">HTTP</th>
                            <th class="px-5 py-4">SSL</th>
                            <th class="px-5 py-4">Домен</th>
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
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="typeClass('http')">{{ monitorSummary(site, 'http') }}</span>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="typeClass('ssl')">{{ monitorSummary(site, 'ssl') }}</span>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="typeClass('domain')">{{ monitorSummary(site, 'domain') }}</span>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 font-extrabold text-[#111827]">
                                {{ site.enabled_monitors_count }} / {{ site.monitors_count }}
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                {{ formatDate(site.last_checked_at) }}
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="h-9 rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF] disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!monitorByType(site, 'http')?.is_enabled"
                                        @click="checkNow(monitorByType(site, 'http'))"
                                    >
                                        Проверить
                                    </button>
                                    <Link
                                        :href="`/sites/${site.id}`"
                                        class="inline-flex h-9 items-center rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
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

                        <div class="mt-4 grid gap-2 sm:grid-cols-3">
                            <span
                                v-for="type in ['http', 'ssl', 'domain']"
                                :key="type"
                                class="rounded-xl bg-[#F8FAFC] px-3 py-2 text-xs font-extrabold text-[#64748B]"
                            >
                                {{ typeLabel(type) }}: {{ monitorSummary(site, type) }}
                            </span>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold text-[#667085]">{{ site.enabled_monitors_count }} / {{ site.monitors_count }} мониторов</p>
                            <Link :href="`/sites/${site.id}`" class="text-sm font-extrabold text-[#0F6BFF] hover:text-[#0757D8]">
                                Открыть
                            </Link>
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
                    >
                        Добавить сайт
                    </Link>
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>
