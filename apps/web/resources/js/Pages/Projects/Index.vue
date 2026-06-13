<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { useAutoRefresh } from '../../Composables/useAutoRefresh'

type Organization = {
    id: string
    name: string
}

type ProjectResource = {
    id: string
    name: string
    target: string
    host: string | null
    status: string
}

type Project = {
    id: string
    name: string
    color: string | null
    is_default: boolean
    resources_count: number
    monitors_count: number
    status: string
    problem_label: string
    ssl_days: number | null
    domain_days: number | null
    last_incident_at: string | null
    resources: ProjectResource[]
}

const props = defineProps<{
    organization: Organization
    projects: Project[]
}>()

useAutoRefresh({
    only: ['projects'],
    intervalMs: 20000,
})

const search = ref('')
const statusFilter = ref('all')

const filters = [
    { value: 'all', label: 'Все' },
    { value: 'ok', label: 'OK' },
    { value: 'problems', label: 'Есть проблемы' },
    { value: 'ssl', label: 'SSL истекает' },
    { value: 'domain', label: 'Домен истекает' },
    { value: 'paused', label: 'На паузе' },
]

const filteredProjects = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.projects.filter((project) => {
        const searchable = [
            project.name,
            project.status,
            project.problem_label,
            ...project.resources.map((resource) => `${resource.name} ${resource.host ?? ''} ${resource.target}`),
        ].join(' ').toLowerCase()

        const matchesSearch = !query || searchable.includes(query)
        const matchesFilter = statusFilter.value === 'all'
            || (statusFilter.value === 'problems' && ['down', 'warning'].includes(project.status))
            || (statusFilter.value === 'ssl' && expiringSoon(project.ssl_days))
            || (statusFilter.value === 'domain' && expiringSoon(project.domain_days))
            || project.status === statusFilter.value

        return matchesSearch && matchesFilter
    })
})

const stats = computed(() => {
    const projects = props.projects

    return {
        total: projects.length,
        resources: projects.reduce((sum, project) => sum + project.resources_count, 0),
        ok: projects.filter((project) => project.status === 'ok').length,
        problems: projects.filter((project) => ['down', 'warning'].includes(project.status)).length,
        critical: projects.filter((project) => project.status === 'down').length,
        warnings: projects.filter((project) => project.status === 'warning').length,
        expiring: projects.filter((project) => expiringSoon(project.ssl_days) || expiringSoon(project.domain_days)).length,
    }
})

function expiringSoon(days: number | null): boolean {
    return typeof days === 'number' && days <= 30
}

function statusLabel(status: string): string {
    if (status === 'ok') return 'OK'
    if (status === 'down') return 'Down'
    if (status === 'warning') return 'Warning'
    if (status === 'paused') return 'Paused'
    if (status === 'empty') return 'Empty'

    return 'Unknown'
}

function statusClass(status: string): string {
    if (status === 'ok') return 'bg-[#E9F8EF] text-[#1E9B5D]'
    if (status === 'down') return 'bg-[#FEECEC] text-[#EF4444]'
    if (status === 'warning') return 'bg-[#FFF7E8] text-[#F59E0B]'
    if (status === 'paused') return 'bg-[#F1F5F9] text-[#64748B]'

    return 'bg-[#F3F8F5] text-[#52645A]'
}

function rowClass(status: string): string {
    if (status === 'down') return 'bg-[#FFF8F8]'
    if (status === 'warning') return 'bg-[#FFFCF4]'

    return 'bg-white'
}

function daysText(days: number | null): string {
    if (typeof days !== 'number') return '—'

    return `${days} ${dayWord(days)}`
}

function dayWord(days: number): string {
    const mod10 = days % 10
    const mod100 = days % 100

    if (mod10 === 1 && mod100 !== 11) return 'день'
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'дня'

    return 'дней'
}

function formatIncident(value: string | null): string {
    if (!value) return 'Нет инцидентов'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function projectKind(project: Project): string {
    if (project.is_default) return 'Основной проект'
    if (project.resources_count >= 5) return 'Портфель сайтов'
    if (project.resources_count === 1) return 'Один сайт'

    return 'Группа сайтов'
}

function openHref(project: Project): string {
    return project.resources[0] ? `/sites/${project.resources[0].id}` : '/sites/create'
}
</script>

<template>
    <Head title="Проекты" />

    <DashboardLayout
        :organization="organization"
        active-item="projects"
        title="Проекты"
        subtitle="Клиенты и группы сайтов с общим статусом мониторинга"
        :usage-current="stats.resources"
    >
        <template #actions>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#8A9A91]">⌕</span>
                    <input
                        v-model="search"
                        type="search"
                        class="h-10 w-full rounded-lg border border-[#DDEBE3] bg-white pl-9 pr-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#8A9A91] focus:border-[#2FA568] focus:ring-2 focus:ring-[#2FA568]/15 sm:w-72"
                        placeholder="Поиск по проектам"
                    >
                </div>

                <Link
                    href="/projects/create"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-[#2FA568] px-4 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(47,165,104,0.18)] transition hover:bg-[#248755]"
                >
                    + Создать проект
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-6 sm:px-8">
                <section>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-lg font-semibold text-[#173B2A]">Сводка по проектам</h2>
                        <span v-if="stats.critical" class="rounded-full bg-[#FEECEC] px-3 py-1 text-xs font-semibold text-[#EF4444]">{{ stats.critical }} критичный</span>
                        <span v-if="stats.warnings" class="rounded-full bg-[#FFF7E8] px-3 py-1 text-xs font-semibold text-[#B7791F]">{{ stats.warnings }} warning</span>
                        <span v-if="stats.ok" class="rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-semibold text-[#1E9B5D]">{{ stats.ok }} OK</span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-lg border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                            <p class="text-sm font-medium text-[#6A7A70]">Всего проектов</p>
                            <p class="mt-2 text-3xl font-semibold text-[#173B2A]">{{ stats.total }}</p>
                            <p class="mt-1 text-sm text-[#6A7A70]">{{ stats.resources }} сайтов под наблюдением</p>
                        </article>
                        <article class="rounded-lg border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                            <p class="text-sm font-medium text-[#6A7A70]">Проекты без проблем</p>
                            <p class="mt-2 text-3xl font-semibold text-[#1E9B5D]">{{ stats.ok }}</p>
                            <p class="mt-1 text-sm text-[#6A7A70]">Все проверки зеленые</p>
                        </article>
                        <article class="rounded-lg border border-[#FECACA] bg-[#FFF8F8] p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                            <p class="text-sm font-medium text-[#6A7A70]">Проекты с проблемами</p>
                            <p class="mt-2 text-3xl font-semibold text-[#EF4444]">{{ stats.problems }}</p>
                            <p class="mt-1 text-sm text-[#6A7A70]">Нужна реакция команды</p>
                        </article>
                        <article class="rounded-lg border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                            <p class="text-sm font-medium text-[#6A7A70]">SSL/домены истекают</p>
                            <p class="mt-2 text-3xl font-semibold text-[#B7791F]">{{ stats.expiring }}</p>
                            <p class="mt-1 text-sm text-[#6A7A70]">В ближайшие 30 дней</p>
                        </article>
                    </div>
                </section>

                <section class="mt-5 rounded-lg border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-base font-semibold text-[#173B2A]">Фильтры</h2>
                        <p class="text-sm text-[#6A7A70]">Показано {{ filteredProjects.length }} из {{ stats.total }}</p>
                    </div>
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="filter in filters"
                            :key="filter.value"
                            type="button"
                            class="h-8 shrink-0 rounded-full px-3 text-sm font-medium transition"
                            :class="statusFilter === filter.value ? 'bg-[#2FA568] text-white' : 'bg-[#F3F8F5] text-[#52645A] hover:bg-[#E9F8EF] hover:text-[#173B2A]'"
                            @click="statusFilter = filter.value"
                        >
                            {{ filter.label }}
                        </button>
                    </div>
                </section>

                <section class="mt-5 overflow-hidden rounded-lg border border-[#DDEBE3] bg-white shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                    <div class="flex flex-col gap-4 border-b border-[#DDEBE3] p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-[#173B2A]">Список проектов</h2>
                            <p class="mt-1 text-sm text-[#6A7A70]">Компактный список для 20-100 проектов. Критичные строки подсвечены мягким фоном.</p>
                        </div>
                        <div class="flex gap-2">
                            <button class="h-9 rounded-lg border border-[#DDEBE3] px-3 text-xs font-semibold text-[#26332D] transition hover:border-[#B8D0C2] hover:bg-[#F6FBF8]">
                                Экспорт
                            </button>
                            <button class="h-9 rounded-lg border border-[#DDEBE3] px-3 text-xs font-semibold text-[#26332D] transition hover:border-[#B8D0C2] hover:bg-[#F6FBF8]">
                                Настроить
                            </button>
                        </div>
                    </div>

                    <div v-if="filteredProjects.length" class="hidden overflow-x-auto lg:block">
                        <table class="min-w-[1040px] w-full border-separate border-spacing-0 text-left text-sm">
                            <thead class="bg-[#F6FBF8] text-xs font-semibold text-[#6A7A70]">
                            <tr>
                                <th class="px-4 py-3">Название проекта / клиента</th>
                                <th class="px-4 py-3">Сайтов</th>
                                <th class="px-4 py-3">Статус</th>
                                <th class="px-4 py-3">Проблемы</th>
                                <th class="px-4 py-3">SSL</th>
                                <th class="px-4 py-3">Домены</th>
                                <th class="px-4 py-3">Последний инцидент</th>
                                <th class="px-4 py-3 text-right">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr
                                v-for="project in filteredProjects"
                                :key="project.id"
                                :class="rowClass(project.status)"
                            >
                                <td class="border-t border-[#DDEBE3] px-4 py-3">
                                    <Link :href="openHref(project)" class="font-semibold text-[#173B2A] hover:text-[#1E9B5D]">
                                        {{ project.name }}
                                    </Link>
                                    <p class="mt-1 text-xs font-medium text-[#6A7A70]">{{ projectKind(project) }}</p>
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-3 font-semibold text-[#26332D]">
                                    {{ project.resources_count }}
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(project.status)">
                                        {{ statusLabel(project.status) }}
                                    </span>
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-3 text-[#6A7A70]">
                                    {{ project.problem_label }}
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="expiringSoon(project.ssl_days) ? 'bg-[#FFF7E8] text-[#B7791F]' : 'bg-[#F3F8F5] text-[#52645A]'">
                                        {{ daysText(project.ssl_days) }}
                                    </span>
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="expiringSoon(project.domain_days) ? 'bg-[#FFF7E8] text-[#B7791F]' : 'bg-[#F3F8F5] text-[#52645A]'">
                                        {{ daysText(project.domain_days) }}
                                    </span>
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-3 text-[#6A7A70]">
                                    {{ formatIncident(project.last_incident_at) }}
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-3">
                                    <div class="flex justify-end gap-3 text-xs font-semibold">
                                        <Link :href="openHref(project)" class="text-[#1E9B5D] hover:text-[#173B2A]">Открыть</Link>
                                        <Link href="/projects/create" class="text-[#6A7A70] hover:text-[#173B2A]">Изм.</Link>
                                        <span class="text-[#8A9A91]">⋯</span>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="filteredProjects.length" class="grid gap-3 p-4 lg:hidden">
                        <article
                            v-for="project in filteredProjects"
                            :key="project.id"
                            class="rounded-lg border border-[#DDEBE3] p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]"
                            :class="rowClass(project.status)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-semibold text-[#173B2A]">{{ project.name }}</h3>
                                    <p class="mt-1 text-xs font-medium text-[#6A7A70]">{{ project.resources_count }} сайтов · {{ project.problem_label }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(project.status)">
                                    {{ statusLabel(project.status) }}
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-[#F3F8F5] px-3 py-1 text-xs font-semibold text-[#52645A]">
                                    SSL: {{ daysText(project.ssl_days) }}
                                </span>
                                <span class="rounded-full bg-[#F3F8F5] px-3 py-1 text-xs font-semibold text-[#52645A]">
                                    Домен: {{ daysText(project.domain_days) }}
                                </span>
                            </div>

                            <Link :href="openHref(project)" class="mt-4 inline-flex text-sm font-semibold text-[#1E9B5D]">
                                Открыть
                            </Link>
                        </article>
                    </div>

                    <div v-if="!filteredProjects.length" class="p-10 text-center">
                        <div class="mx-auto grid h-12 w-12 place-items-center rounded-lg bg-[#E9F8EF] text-xl font-semibold text-[#1E9B5D]">＋</div>
                        <h3 class="mt-4 text-lg font-semibold text-[#173B2A]">У вас пока нет проектов</h3>
                        <p class="mx-auto mt-2 max-w-md leading-7 text-[#6A7A70]">
                            Создайте первый проект, чтобы сгруппировать сайты клиентов и следить за их состоянием.
                        </p>
                        <Link
                            href="/projects/create"
                            class="mt-6 inline-flex h-10 items-center justify-center rounded-lg bg-[#2FA568] px-4 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(47,165,104,0.18)] transition hover:bg-[#248755]"
                        >
                            Создать проект
                        </Link>
                    </div>
                </section>
        </div>
    </DashboardLayout>
</template>
