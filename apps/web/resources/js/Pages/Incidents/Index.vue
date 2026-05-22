<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

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

const props = defineProps<{
    organization: Organization
    summary: Summary
    filters: Filters
    activeIncidents: Incident[]
    resolvedIncidents: Incident[]
    warnings: Incident[]
}>()

const search = ref(props.filters.search)
const period = ref(props.filters.period)
const type = ref(props.filters.type)

const totalVisibleItems = computed(() => props.activeIncidents.length + props.resolvedIncidents.length + props.warnings.length)

function applyFilters(): void {
    router.get('/incidents', {
        search: search.value || undefined,
        period: period.value,
        type: type.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function resetFilters(): void {
    search.value = ''
    period.value = '30'
    type.value = 'all'
    applyFilters()
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

function typeLabel(value: string): string {
    if (value === 'http') return 'HTTP'
    if (value === 'ssl') return 'SSL'
    if (value === 'domain') return 'Domain'

    return value.toUpperCase()
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
        subtitle="Открытые проблемы, история простоев и предупреждения по SSL и доменам"
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
                        <option value="30">30 дней</option>
                    </select>
                    <select v-model="type" class="h-11 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-bold text-[#111827] outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" @change="applyFilters">
                        <option value="all">Все проверки</option>
                        <option value="http">HTTP</option>
                        <option value="ssl">SSL</option>
                        <option value="domain">Domain</option>
                    </select>
                    <button type="button" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#667085] transition hover:border-[#CBD5E1] hover:text-[#111827]" @click="resetFilters">
                        Сбросить
                    </button>
                </div>
                <p class="text-sm font-bold text-[#667085]">Найдено: {{ totalVisibleItems }}</p>
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
