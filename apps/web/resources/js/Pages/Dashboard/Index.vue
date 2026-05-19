<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { useAutoRefresh } from '../../Composables/useAutoRefresh'

type Organization = {
    id: string
    name: string
}

type Summary = {
    total_resources: number
    total_projects: number
    total_monitors: number
    ok_monitors: number
    down_monitors: number
    warning_monitors: number
    ssl_expiring: number
    domain_expiring: number
    latest_check_at: string | null
}

type Problem = {
    id: string
    site_id: string
    site: string
    problem: string
    status: string
    last_check_at: string | null
    action: string
}

type Incident = {
    id: string
    site: string
    reason: string
    duration: string
    status: string
    started_at: string | null
}

type LatestCheck = {
    id: string
    site: string
    type: string
    result: string
    response: string
    checked_at: string | null
    status: string
}

const props = defineProps<{
    organization: Organization
    summary: Summary
    problems: Problem[]
    incidents: Incident[]
    latest_checks: LatestCheck[]
}>()

useAutoRefresh({
    only: ['summary', 'problems', 'incidents', 'latest_checks'],
    intervalMs: 15000,
})

const search = ref('')

const filteredProblems = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (!query) {
        return props.problems
    }

    return props.problems.filter((problem) => `${problem.site} ${problem.problem} ${problem.status}`.toLowerCase().includes(query))
})

const hasProblems = computed(() => props.summary.down_monitors > 0 || props.summary.warning_monitors > 0)

function statusClass(status: string): string {
    if (['success', 'ok', 'closed', 'resolved'].includes(status)) return 'bg-[#ECFDF3] text-[#16A34A]'
    if (['failure', 'down', 'open'].includes(status)) return 'bg-[#FEECEC] text-[#EF4444]'
    if (['warning', 'degraded'].includes(status)) return 'bg-[#FFF7E8] text-[#F59E0B]'

    return 'bg-[#F1F5F9] text-[#64748B]'
}

function statusLabel(status: string): string {
    if (status === 'down' || status === 'failure') return 'Down'
    if (status === 'warning' || status === 'degraded') return 'Warning'
    if (status === 'success' || status === 'ok') return 'OK'
    if (status === 'open') return 'Открыт'
    if (status === 'resolved' || status === 'closed') return 'Закрыт'

    return status
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

function formatTime(value: string | null): string {
    if (!value) return '—'

    return new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}
</script>

<template>
    <Head title="Обзор" />

    <DashboardLayout
        :organization="organization"
        active-item="dashboard"
        title="Обзор"
        subtitle="Состояние сайтов, SSL и доменов на сегодня"
        :usage-current="summary.total_monitors"
    >
        <template #actions>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#98A2B3]">⌕</span>
                    <input
                        v-model="search"
                        type="search"
                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white pl-10 pr-4 text-sm outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15 sm:w-80"
                        placeholder="Поиск сайта, проекта или домена"
                    >
                </div>

                <Link
                    href="/sites/create"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8]"
                >
                    + Добавить мониторинг
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <section>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-extrabold text-[#111827]">Главное</h2>
                    <span v-if="summary.down_monitors" class="rounded-full bg-[#FEECEC] px-3 py-1 text-xs font-extrabold text-[#EF4444]">
                        {{ summary.down_monitors }} критичные проблемы
                    </span>
                    <span v-if="summary.warning_monitors" class="rounded-full bg-[#FFF7E8] px-3 py-1 text-xs font-extrabold text-[#F59E0B]">
                        {{ summary.warning_monitors }} предупреждений
                    </span>
                    <span v-if="summary.ok_monitors" class="rounded-full bg-[#ECFDF3] px-3 py-1 text-xs font-extrabold text-[#16A34A]">
                        {{ summary.ok_monitors }} работают
                    </span>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Все сайты</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ summary.total_resources }}</p>
                        <p class="mt-2 text-sm text-[#667085]">{{ summary.total_projects }} проектов</p>
                    </article>
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Работают</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#16A34A]">{{ summary.ok_monitors }}</p>
                        <p class="mt-2 text-sm text-[#667085]">Последняя проверка {{ formatDateTime(summary.latest_check_at) }}</p>
                    </article>
                    <article class="rounded-3xl border border-[#FECACA] bg-gradient-to-b from-white to-[#FFF8F8] p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Есть проблемы</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#EF4444]">{{ summary.down_monitors }}</p>
                        <p class="mt-2 text-sm text-[#667085]">Сайты требуют реакции</p>
                    </article>
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">SSL истекает</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#F59E0B]">{{ summary.ssl_expiring }}</p>
                        <p class="mt-2 text-sm text-[#667085]">До 14 дней</p>
                    </article>
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Домены истекают</p>
                        <p class="mt-3 text-4xl font-extrabold text-[#F59E0B]">{{ summary.domain_expiring }}</p>
                        <p class="mt-2 text-sm text-[#667085]">До 30 дней</p>
                    </article>
                </div>
            </section>

            <section
                class="mt-6 rounded-3xl border p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]"
                :class="hasProblems ? 'border-[#FECACA] bg-[#FFF8F8]' : 'border-[#D1FADF] bg-white'"
            >
                <div class="flex items-start gap-4">
                    <span
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl text-lg font-extrabold"
                        :class="hasProblems ? 'bg-[#FEECEC] text-[#EF4444]' : 'bg-[#ECFDF3] text-[#16A34A]'"
                    >
                        {{ hasProblems ? '!' : '✓' }}
                    </span>
                    <div>
                        <h2 class="font-extrabold text-[#111827]">{{ hasProblems ? 'Нужна реакция' : 'Когда всё хорошо' }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-[#667085]">
                            {{ hasProblems
                                ? 'Проверьте критичные сайты и предупреждения, чтобы не пропустить простой клиента.'
                                : 'Montry показывает спокойный зелёный статус и не перегружает экран графиками.' }}
                        </p>
                    </div>
                </div>
            </section>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.8fr)]">
                <section class="overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <div class="flex flex-col gap-4 border-b border-[#E5E7EB] p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-xl font-extrabold text-[#111827]">Проблемы сейчас</h2>
                            <p class="mt-1 text-sm text-[#667085]">Главный список для быстрой реакции</p>
                        </div>
                        <button class="h-9 rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]">
                            Проверить сейчас
                        </button>
                    </div>

                    <div v-if="filteredProblems.length" class="overflow-x-auto">
                        <table class="min-w-[720px] w-full border-separate border-spacing-0 text-left text-sm">
                            <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                            <tr>
                                <th class="px-5 py-4">Сайт</th>
                                <th class="px-5 py-4">Тип проблемы</th>
                                <th class="px-5 py-4">Статус</th>
                                <th class="px-5 py-4">Последняя проверка</th>
                                <th class="px-5 py-4 text-right">Действие</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="problem in filteredProblems" :key="problem.id">
                                <td class="border-t border-[#E5E7EB] px-5 py-4 font-extrabold text-[#111827]">{{ problem.site }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ problem.problem }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(problem.status)">
                                        {{ statusLabel(problem.status) }}
                                    </span>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDateTime(problem.last_check_at) }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-right">
                                    <Link :href="`/sites/${problem.site_id}`" class="text-xs font-extrabold text-[#0F6BFF] hover:text-[#0757D8]">
                                        {{ problem.action }}
                                    </Link>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="p-10 text-center">
                        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#ECFDF3] text-2xl font-extrabold text-[#16A34A]">✓</div>
                        <h3 class="mt-5 text-xl font-extrabold text-[#111827]">Критичных проблем нет</h3>
                        <p class="mx-auto mt-2 max-w-md leading-7 text-[#667085]">Все активные проверки сейчас выглядят спокойно.</p>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <h2 class="text-xl font-extrabold text-[#111827]">Быстрые действия</h2>
                    <p class="mt-1 text-sm text-[#667085]">Частые операции в один клик</p>

                    <div class="mt-5 grid gap-3">
                        <Link href="/sites/create" class="flex items-center gap-4 rounded-2xl border border-[#E5E7EB] p-4 transition hover:border-[#0F6BFF] hover:bg-[#F8FAFC]">
                            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-[#EAF2FF] text-xl font-extrabold text-[#0F6BFF]">+</span>
                            <span>
                                <span class="block font-extrabold text-[#111827]">Добавить сайт</span>
                                <span class="text-sm text-[#667085]">Создать новый мониторинг</span>
                            </span>
                        </Link>
                        <Link href="/monitors" class="flex items-center gap-4 rounded-2xl border border-[#E5E7EB] p-4 transition hover:border-[#0F6BFF] hover:bg-[#F8FAFC]">
                            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-[#F1F5F9] text-xl font-extrabold text-[#111827]">↻</span>
                            <span>
                                <span class="block font-extrabold text-[#111827]">Проверить сейчас</span>
                                <span class="text-sm text-[#667085]">Запустить ручную проверку</span>
                            </span>
                        </Link>
                        <Link href="/projects/create" class="flex items-center gap-4 rounded-2xl border border-[#E5E7EB] p-4 transition hover:border-[#0F6BFF] hover:bg-[#F8FAFC]">
                            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-[#ECFDF3] text-xl font-extrabold text-[#16A34A]">□</span>
                            <span>
                                <span class="block font-extrabold text-[#111827]">Создать проект</span>
                                <span class="text-sm text-[#667085]">Сгруппировать сайты клиента</span>
                            </span>
                        </Link>
                    </div>
                </section>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] p-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Последние инциденты</h2>
                        <p class="mt-1 text-sm text-[#667085]">История падений и восстановлений</p>
                    </div>

                    <div v-if="incidents.length" class="overflow-x-auto">
                        <table class="min-w-[640px] w-full border-separate border-spacing-0 text-left text-sm">
                            <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                            <tr>
                                <th class="px-5 py-4">Сайт</th>
                                <th class="px-5 py-4">Причина</th>
                                <th class="px-5 py-4">Длительность</th>
                                <th class="px-5 py-4">Статус</th>
                                <th class="px-5 py-4">Дата</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="incident in incidents" :key="incident.id">
                                <td class="border-t border-[#E5E7EB] px-5 py-4 font-extrabold text-[#111827]">{{ incident.site }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ incident.reason }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ incident.duration }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(incident.status)">
                                        {{ statusLabel(incident.status) }}
                                    </span>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatDateTime(incident.started_at) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="p-8 text-sm text-[#667085]">Инцидентов пока нет.</p>
                </section>

                <section class="overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] p-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Последние проверки</h2>
                        <p class="mt-1 text-sm text-[#667085]">Свежие результаты мониторинга</p>
                    </div>

                    <div v-if="latest_checks.length" class="overflow-x-auto">
                        <table class="min-w-[640px] w-full border-separate border-spacing-0 text-left text-sm">
                            <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                            <tr>
                                <th class="px-5 py-4">Сайт</th>
                                <th class="px-5 py-4">Тип</th>
                                <th class="px-5 py-4">Результат</th>
                                <th class="px-5 py-4">Ответ</th>
                                <th class="px-5 py-4">Время</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="check in latest_checks" :key="check.id">
                                <td class="border-t border-[#E5E7EB] px-5 py-4 font-extrabold text-[#111827]">{{ check.site }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ check.type }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(check.status)">
                                        {{ check.result }}
                                    </span>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ check.response }}</td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">{{ formatTime(check.checked_at) }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="p-8 text-sm text-[#667085]">Проверок пока нет.</p>
                </section>
            </div>
        </div>
    </DashboardLayout>
</template>
