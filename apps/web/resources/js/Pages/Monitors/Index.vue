<script setup lang="ts">
import { computed, ref } from 'vue'
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

type Resource = {
    id: string
    name: string
    target: string
    host: string
    status: string
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
    site_id: string
    type: string
    name: string
    status: string
    is_enabled: boolean
    interval_seconds: number | null
    timeout_ms: number | null
    last_check_at: string | null
    next_check_at: string | null
    check_in_progress_until: string | null
    is_checking: boolean
    settings: Record<string, unknown>
    expected: Record<string, unknown>
    project: Project | null
    resource: Resource | null
    latest_result: LatestResult | null
}

const props = defineProps<{
    organization: Organization
    monitors: Monitor[]
}>()

useAutoRefresh({
    only: ['monitors'],
    intervalMs: 20000,
})

const search = ref('')
const typeFilter = ref('all')
const statusFilter = ref('all')

const typeFilters = [
    { value: 'all', label: 'Все' },
    { value: 'http', label: 'HTTP/HTTPS' },
    { value: 'ssl', label: 'SSL' },
    { value: 'domain', label: 'Домены' },
]

const statusFilters = [
    { value: 'all', label: 'Все статусы' },
    { value: 'ok', label: 'OK' },
    { value: 'warning', label: 'Warning' },
    { value: 'down', label: 'Down' },
    { value: 'checking', label: 'Checking' },
    { value: 'paused', label: 'Paused' },
]

const filteredMonitors = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.monitors.filter((monitor) => {
        const searchable = [
            monitor.name,
            monitor.resource?.name,
            monitor.resource?.target,
            monitor.resource?.host,
            monitor.project?.name,
            monitor.type,
            statusLabel(monitor),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        const matchesSearch = !query || searchable.includes(query)
        const matchesType = typeFilter.value === 'all' || monitor.type === typeFilter.value
        const matchesStatus = statusFilter.value === 'all' || statusKey(monitor) === statusFilter.value

        return matchesSearch && matchesType && matchesStatus
    })
})

const stats = computed(() => {
    const monitors = props.monitors

    return {
        total: monitors.length,
        ok: monitors.filter((monitor) => statusKey(monitor) === 'ok').length,
        down: monitors.filter((monitor) => statusKey(monitor) === 'down').length,
        warning: monitors.filter((monitor) => statusKey(monitor) === 'warning').length,
        checking: monitors.filter((monitor) => statusKey(monitor) === 'checking').length,
        paused: monitors.filter((monitor) => statusKey(monitor) === 'paused').length,
    }
})

function statusKey(monitor: Monitor): string {
    if (!monitor.is_enabled || monitor.status === 'paused') return 'paused'
    if (monitor.is_checking) return 'checking'
    if (monitor.status === 'success' || monitor.status === 'up') return 'ok'
    if (monitor.status === 'failure' || monitor.status === 'down') return 'down'
    if (monitor.status === 'degraded' || monitor.latest_result?.status === 'warning') return 'warning'

    return 'unknown'
}

function statusLabel(monitor: Monitor): string {
    const key = statusKey(monitor)

    if (key === 'ok') return 'OK'
    if (key === 'down') return 'Down'
    if (key === 'warning') return 'Warning'
    if (key === 'checking') return 'Checking'
    if (key === 'paused') return 'Paused'

    return 'Unknown'
}

function statusClass(monitor: Monitor): string {
    const key = statusKey(monitor)

    if (key === 'ok') return 'bg-[#ECFDF3] text-[#16A34A]'
    if (key === 'down') return 'bg-[#FEECEC] text-[#EF4444]'
    if (key === 'warning') return 'bg-[#FFF7E8] text-[#F59E0B]'
    if (key === 'checking') return 'bg-[#EAF2FF] text-[#0F6BFF]'
    if (key === 'paused') return 'bg-[#F1F5F9] text-[#64748B]'

    return 'bg-[#F3E8FF] text-[#7C3AED]'
}

function typeLabel(type: string): string {
    return {
        http: 'HTTP/HTTPS',
        ssl: 'SSL',
        domain: 'Domain',
        dns: 'DNS',
        robots_txt: 'Robots.txt',
        sitemap_xml: 'Sitemap.xml',
        api_endpoint: 'API endpoint',
        tcp_port: 'TCP-порт',
    }[type] ?? type.toUpperCase()
}

function typeClass(type: string): string {
    if (type === 'http' || type === 'api_endpoint') return 'bg-[#EAF2FF] text-[#0F6BFF]'
    if (type === 'ssl') return 'bg-[#ECFDF3] text-[#16A34A]'
    if (type === 'domain') return 'bg-[#FFF7E8] text-[#F59E0B]'
    if (type === 'dns') return 'bg-[#F3E8FF] text-[#7C3AED]'

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

function formatInterval(seconds: number | null): string {
    if (!seconds) return 'по умолчанию'
    if (seconds < 60) return `${seconds} сек`
    if (seconds < 3600) return `${Math.round(seconds / 60)} мин`
    if (seconds < 86400) return `${Math.round(seconds / 3600)} ч`

    return `${Math.round(seconds / 86400)} день`
}

function resultText(monitor: Monitor): string {
    const result = monitor.latest_result

    if (monitor.is_checking) return 'идет проверка'
    if (!result) return 'нет результата'
    if (result.error_message) return result.error_message

    if (monitor.type === 'http') {
        const statusCode = result.status_code ? `${result.status_code}` : 'HTTP'
        const responseTime = result.response_time_ms ? ` · ${result.response_time_ms} мс` : ''

        return `${statusCode}${responseTime}`
    }

    if (monitor.type === 'ssl') {
        const days = result.normalized_result.days_until_expiration

        return typeof days === 'number' ? `истекает через ${days} дней` : statusLabel(monitor)
    }

    if (monitor.type === 'domain') {
        const days = result.normalized_result.days_until_expiration

        return typeof days === 'number' ? `истекает через ${days} дней` : statusLabel(monitor)
    }

    return result.status
}

function targetText(monitor: Monitor): string {
    if (monitor.type === 'http') {
        return String(monitor.settings.url ?? monitor.resource?.target ?? monitor.resource?.host ?? monitor.name)
    }

    return String(monitor.settings.domain ?? monitor.resource?.host ?? monitor.resource?.target ?? monitor.name)
}

function checkNow(monitor: Monitor): void {
    if (!monitor.is_enabled || monitor.is_checking) {
        return
    }

    router.post(`/monitors/${monitor.id}/check-now`, {}, {
        preserveScroll: true,
    })
}

function toggleMonitor(monitor: Monitor): void {
    router.patch(`/sites/${monitor.site_id}/monitors/${monitor.id}/toggle`, {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Мониторинги" />

    <DashboardLayout
        :organization="organization"
        active-item="monitors"
        title="Мониторинги"
        subtitle="Активные проверки доступности, SSL и доменов"
        :usage-current="stats.total"
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
                    href="/sites"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8]"
                >
                    + Добавить мониторинг
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
                <section>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-extrabold text-[#111827]">Сводка мониторингов</h2>
                        <span v-if="stats.down" class="rounded-full bg-[#FEECEC] px-3 py-1 text-xs font-extrabold text-[#EF4444]">{{ stats.down }} Down</span>
                        <span v-if="stats.warning" class="rounded-full bg-[#FFF7E8] px-3 py-1 text-xs font-extrabold text-[#F59E0B]">{{ stats.warning }} Warning</span>
                        <span v-if="stats.checking" class="rounded-full bg-[#EAF2FF] px-3 py-1 text-xs font-extrabold text-[#0F6BFF]">{{ stats.checking }} Checking</span>
                        <span v-if="stats.paused" class="rounded-full bg-[#F1F5F9] px-3 py-1 text-xs font-extrabold text-[#64748B]">{{ stats.paused }} Paused</span>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                        <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                            <p class="text-sm font-bold text-[#667085]">Всего мониторингов</p>
                            <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ stats.total }}</p>
                            <p class="mt-2 text-sm text-[#667085]">HTTP, SSL и домены</p>
                        </article>
                        <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                            <p class="text-sm font-bold text-[#667085]">Работают</p>
                            <p class="mt-3 text-4xl font-extrabold text-[#16A34A]">{{ stats.ok }}</p>
                            <p class="mt-2 text-sm text-[#667085]">Проверки зеленые</p>
                        </article>
                        <article class="rounded-3xl border border-[#FECACA] bg-gradient-to-b from-white to-[#FFF8F8] p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                            <p class="text-sm font-bold text-[#667085]">Есть проблемы</p>
                            <p class="mt-3 text-4xl font-extrabold text-[#EF4444]">{{ stats.down }}</p>
                            <p class="mt-2 text-sm text-[#667085]">Требует реакции</p>
                        </article>
                        <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                            <p class="text-sm font-bold text-[#667085]">Предупреждения</p>
                            <p class="mt-3 text-4xl font-extrabold text-[#F59E0B]">{{ stats.warning }}</p>
                            <p class="mt-2 text-sm text-[#667085]">SSL и домены</p>
                        </article>
                        <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                            <p class="text-sm font-bold text-[#667085]">Проверяются</p>
                            <p class="mt-3 text-4xl font-extrabold text-[#0F6BFF]">{{ stats.checking }}</p>
                            <p class="mt-2 text-sm text-[#667085]">В работе у poller</p>
                        </article>
                        <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                            <p class="text-sm font-bold text-[#667085]">На паузе</p>
                            <p class="mt-3 text-4xl font-extrabold text-[#64748B]">{{ stats.paused }}</p>
                            <p class="mt-2 text-sm text-[#667085]">Временно выключены</p>
                        </article>
                    </div>
                </section>

                <section class="mt-6 rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <h2 class="text-lg font-extrabold text-[#111827]">Фильтры</h2>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="filter in typeFilters"
                            :key="filter.value"
                            type="button"
                            class="h-9 rounded-full px-4 text-sm font-extrabold transition"
                            :class="typeFilter === filter.value ? 'bg-[#0F6BFF] text-white' : 'bg-[#F8FAFC] text-[#667085] hover:bg-[#EAF2FF] hover:text-[#0F6BFF]'"
                            @click="typeFilter = filter.value"
                        >
                            {{ filter.label }}
                        </button>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="filter in statusFilters"
                            :key="filter.value"
                            type="button"
                            class="h-9 rounded-full px-4 text-sm font-extrabold transition"
                            :class="statusFilter === filter.value ? 'bg-[#111827] text-white' : 'bg-[#F8FAFC] text-[#667085] hover:bg-[#F1F5F9] hover:text-[#111827]'"
                            @click="statusFilter = filter.value"
                        >
                            {{ filter.label }}
                        </button>
                    </div>
                </section>

                <section class="mt-6 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <div class="flex flex-col gap-4 border-b border-[#E5E7EB] p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-xl font-extrabold text-[#111827]">Все мониторинги</h2>
                            <p class="mt-1 text-sm text-[#667085]">Проблемные строки подсвечены, ручная проверка доступна прямо из таблицы.</p>
                        </div>
                        <p class="text-sm font-bold text-[#667085]">Показано: {{ filteredMonitors.length }}</p>
                    </div>

                    <div v-if="filteredMonitors.length" class="overflow-x-auto">
                        <table class="min-w-[1080px] w-full border-separate border-spacing-0 text-left text-sm">
                            <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                            <tr>
                                <th class="px-5 py-4">Сайт / домен</th>
                                <th class="px-5 py-4">Проект</th>
                                <th class="px-5 py-4">Тип</th>
                                <th class="px-5 py-4">Статус</th>
                                <th class="px-5 py-4">Последняя</th>
                                <th class="px-5 py-4">Ответ</th>
                                <th class="px-5 py-4">Интервал</th>
                                <th class="px-5 py-4">Следующая</th>
                                <th class="px-5 py-4 text-right">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr
                                v-for="monitor in filteredMonitors"
                                :key="monitor.id"
                                class="border-b border-[#E5E7EB]"
                                :class="statusKey(monitor) === 'down' ? 'bg-[#FFF8F8]' : statusKey(monitor) === 'checking' ? 'bg-[#F7FBFF]' : ''"
                            >
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <Link :href="`/sites/${monitor.site_id}`" class="font-extrabold text-[#111827] hover:text-[#0F6BFF]">
                                        {{ targetText(monitor) }}
                                    </Link>
                                    <p class="mt-1 text-xs font-semibold text-[#667085]">{{ monitor.name }}</p>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                    {{ monitor.project?.name ?? 'Без проекта' }}
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="typeClass(monitor.type)">
                                        {{ typeLabel(monitor.type) }}
                                    </span>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(monitor)">
                                        {{ statusLabel(monitor) }}
                                    </span>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                    {{ formatDate(monitor.last_check_at) }}
                                </td>
                                <td class="max-w-56 truncate border-t border-[#E5E7EB] px-5 py-4 font-semibold text-[#111827]">
                                    {{ resultText(monitor) }}
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                    {{ formatInterval(monitor.interval_seconds) }}
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                    {{ formatDate(monitor.next_check_at) }}
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 min-w-[104px] items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF] disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="!monitor.is_enabled || monitor.is_checking"
                                            @click="checkNow(monitor)"
                                        >
                                            <span
                                                v-if="monitor.is_checking"
                                                class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-[#0F6BFF]/25 border-t-[#0F6BFF]"
                                                aria-hidden="true"
                                            />
                                            <span>{{ monitor.is_checking ? 'Проверяем...' : 'Проверить' }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="h-9 rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                                            @click="toggleMonitor(monitor)"
                                        >
                                            {{ monitor.is_enabled ? 'Пауза' : 'Включить' }}
                                        </button>
                                        <Link
                                            :href="`/sites/${monitor.site_id}/monitors/${monitor.id}/edit`"
                                            class="inline-flex h-9 items-center rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                                        >
                                            Настроить
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="p-10 text-center">
                        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#EAF2FF] text-2xl font-extrabold text-[#0F6BFF]">＋</div>
                        <h3 class="mt-5 text-xl font-extrabold text-[#111827]">Мониторинги еще не добавлены</h3>
                        <p class="mx-auto mt-2 max-w-md leading-7 text-[#667085]">
                            Добавьте первый сайт, чтобы Montry начал проверять доступность, SSL и срок домена.
                        </p>
                        <Link
                            href="/sites"
                            class="mt-6 inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8]"
                        >
                            Добавить мониторинг
                        </Link>
                    </div>
                </section>
        </div>
    </DashboardLayout>
</template>
