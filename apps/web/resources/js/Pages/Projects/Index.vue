<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import TariffRestriction from '@/Components/TariffRestriction.vue'
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
}

type ProjectAccess = { current: number; limit: number | null; can_create: boolean }

type Project = {
    id: string
    name: string
    is_default: boolean
    resources_count: number
    incidents_count: number
    warnings_count: number
    resources: ProjectResource[]
}

const props = defineProps<{
    organization: Organization
    projects: Project[]
    projectAccess: ProjectAccess
}>()

useAutoRefresh({
    only: ['projects'],
    intervalMs: 20000,
})

const search = ref('')
const statusFilter = ref('all')

const filters = [
    { value: 'all', label: 'Все' },
    { value: 'incidents', label: 'С инцидентами' },
    { value: 'warnings', label: 'С предупреждениями' },
    { value: 'healthy', label: 'Без проблем' },
    { value: 'empty', label: 'Без сайтов' },
]

const filteredProjects = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.projects.filter((project) => {
        const searchable = [
            project.name,
            ...project.resources.map((resource) => `${resource.name} ${resource.host ?? ''} ${resource.target}`),
        ].join(' ').toLowerCase()

        const matchesSearch = !query || searchable.includes(query)
        const matchesFilter = statusFilter.value === 'all'
            || (statusFilter.value === 'incidents' && project.incidents_count > 0)
            || (statusFilter.value === 'warnings' && project.warnings_count > 0)
            || (statusFilter.value === 'healthy' && project.incidents_count === 0 && project.warnings_count === 0)
            || (statusFilter.value === 'empty' && project.resources_count === 0)

        return matchesSearch && matchesFilter
    })
})

const stats = computed(() => ({
    total: props.projects.length,
    resources: props.projects.reduce((sum, project) => sum + project.resources_count, 0),
    incidents: props.projects.reduce((sum, project) => sum + project.incidents_count, 0),
    warnings: props.projects.reduce((sum, project) => sum + project.warnings_count, 0),
}))

function countLabel(count: number, singular: string, few: string, many: string): string {
    const mod10 = count % 10
    const mod100 = count % 100

    if (mod10 === 1 && mod100 !== 11) return singular
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few

    return many
}

function projectKind(project: Project): string {
    if (project.is_default) return 'Основной проект'
    if (project.resources_count === 0) return 'Пустой проект'

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
        subtitle="Папки для сайтов клиентов и внутренних проектов"
        :usage-current="stats.resources"
    >

        <div class="mx-auto max-w-7xl px-5 py-6 sm:px-8">
            <section>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold text-[#173B2A]">Сводка по проектам</h2>
                    <span v-if="stats.incidents" class="rounded-full bg-[#FEECEC] px-3 py-1 text-xs font-semibold text-[#EF4444]">
                        {{ stats.incidents }} активных инцидентов
                    </span>
                    <span v-if="stats.warnings" class="rounded-full bg-[#FFF7E8] px-3 py-1 text-xs font-semibold text-[#B7791F]">
                        {{ stats.warnings }} предупреждений
                    </span>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-lg border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                        <p class="text-sm font-medium text-[#6A7A70]">Всего проектов</p>
                        <p class="mt-2 text-3xl font-semibold text-[#173B2A]">{{ stats.total }}</p>
                        <p class="mt-1 text-sm text-[#6A7A70]">Папки для группировки сайтов</p>
                    </article>
                    <article class="rounded-lg border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                        <p class="text-sm font-medium text-[#6A7A70]">Всего сайтов</p>
                        <p class="mt-2 text-3xl font-semibold text-[#173B2A]">{{ stats.resources }}</p>
                        <p class="mt-1 text-sm text-[#6A7A70]">Распределены по проектам</p>
                    </article>
                    <article class="rounded-lg border border-[#FECACA] bg-[#FFF8F8] p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                        <p class="text-sm font-medium text-[#6A7A70]">Активные инциденты</p>
                        <p class="mt-2 text-3xl font-semibold text-[#EF4444]">{{ stats.incidents }}</p>
                        <p class="mt-1 text-sm text-[#6A7A70]">Требуют внимания</p>
                    </article>
                    <article class="rounded-lg border border-[#FDE6B5] bg-[#FFFCF4] p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                        <p class="text-sm font-medium text-[#6A7A70]">Предупреждения</p>
                        <p class="mt-2 text-3xl font-semibold text-[#B7791F]">{{ stats.warnings }}</p>
                        <p class="mt-1 text-sm text-[#6A7A70]">Открытые warning-инциденты</p>
                    </article>
                </div>
            </section>

            <section class="mt-5 rounded-lg border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-sm">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#8A9A91]">⌕</span>
                        <input
                            v-model="search"
                            type="search"
                            class="h-10 w-full rounded-lg border border-[#DDEBE3] bg-[#FAFCFB] pl-9 pr-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#8A9A91] focus:border-[#2FA568] focus:bg-white focus:ring-2 focus:ring-[#2FA568]/15"
                            placeholder="Найти проект, сайт или домен"
                            aria-label="Поиск по проектам"
                        >
                    </div>
                    <Link
                        v-if="projectAccess.can_create"
                        href="/projects/create"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-[#2FA568] px-4 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(47,165,104,0.18)] transition hover:bg-[#248755]"
                    >
                        + Создать проект
                    </Link>
                    <TariffRestriction
                        v-else
                        action="+ Создать проект"
                        class="shrink-0"
                    />
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
                <div class="border-b border-[#DDEBE3] p-4">
                    <h2 class="text-lg font-semibold text-[#173B2A]">Список проектов</h2>
                    <p class="mt-1 text-sm text-[#6A7A70]">Проекты используются как папки для сайтов. Здесь показана только общая сводка по каждой группе.</p>
                </div>

                <div v-if="filteredProjects.length" class="hidden overflow-x-auto lg:block">
                    <table class="w-full border-separate border-spacing-0 text-left text-sm">
                        <thead class="bg-[#F6FBF8] text-xs font-semibold text-[#6A7A70]">
                            <tr>
                                <th class="px-4 py-3">Проект</th>
                                <th class="px-4 py-3">Сайты</th>
                                <th class="px-4 py-3">Активные инциденты</th>
                                <th class="px-4 py-3">Предупреждения</th>
                                <th class="px-4 py-3 text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="project in filteredProjects" :key="project.id" class="bg-white hover:bg-[#FAFCFB]">
                                <td class="border-t border-[#DDEBE3] px-4 py-4">
                                    <Link :href="openHref(project)" class="font-semibold text-[#173B2A] hover:text-[#1E9B5D]">{{ project.name }}</Link>
                                    <p class="mt-1 text-xs font-medium text-[#6A7A70]">{{ projectKind(project) }}</p>
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-4">
                                    <span class="font-semibold text-[#26332D]">{{ project.resources_count }}</span>
                                    <span class="ml-1 text-[#6A7A70]">{{ countLabel(project.resources_count, 'сайт', 'сайта', 'сайтов') }}</span>
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-4">
                                    <span v-if="project.incidents_count" class="inline-flex rounded-full bg-[#FEECEC] px-3 py-1 text-xs font-semibold text-[#D93636]">{{ project.incidents_count }}</span>
                                    <span v-else class="text-[#8A9A91]">0</span>
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-4">
                                    <span v-if="project.warnings_count" class="inline-flex rounded-full bg-[#FFF7E8] px-3 py-1 text-xs font-semibold text-[#B7791F]">{{ project.warnings_count }}</span>
                                    <span v-else class="text-[#8A9A91]">0</span>
                                </td>
                                <td class="border-t border-[#DDEBE3] px-4 py-4 text-right">
                                    <div class="flex justify-end gap-4">
                                        <Link :href="openHref(project)" class="text-xs font-semibold text-[#1E9B5D] hover:text-[#173B2A]">Открыть сайты</Link>
                                        <Link :href="`/projects/${project.id}/edit`" class="text-xs font-semibold text-[#6A7A70] hover:text-[#173B2A]">Редактировать</Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="filteredProjects.length" class="grid gap-3 p-4 lg:hidden">
                    <article v-for="project in filteredProjects" :key="project.id" class="rounded-lg border border-[#DDEBE3] bg-white p-4 shadow-[0_8px_18px_rgba(23,59,42,0.04)]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-[#173B2A]">{{ project.name }}</h3>
                                <p class="mt-1 text-xs font-medium text-[#6A7A70]">{{ project.resources_count }} {{ countLabel(project.resources_count, 'сайт', 'сайта', 'сайтов') }}</p>
                            </div>
                            <Link :href="`/projects/${project.id}/edit`" class="shrink-0 text-sm font-semibold text-[#1E9B5D]">Изменить</Link>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span :class="project.incidents_count ? 'bg-[#FEECEC] text-[#D93636]' : 'bg-[#F3F8F5] text-[#6A7A70]'" class="rounded-full px-3 py-1 text-xs font-semibold">Инциденты: {{ project.incidents_count }}</span>
                            <span :class="project.warnings_count ? 'bg-[#FFF7E8] text-[#B7791F]' : 'bg-[#F3F8F5] text-[#6A7A70]'" class="rounded-full px-3 py-1 text-xs font-semibold">Предупреждения: {{ project.warnings_count }}</span>
                        </div>
                    </article>
                </div>

                <div v-if="!filteredProjects.length" class="p-10 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-lg bg-[#E9F8EF] text-xl font-semibold text-[#1E9B5D]">＋</div>
                    <h3 class="mt-4 text-lg font-semibold text-[#173B2A]">Проекты не найдены</h3>
                    <p class="mx-auto mt-2 max-w-md leading-7 text-[#6A7A70]">Измените поиск или фильтр либо создайте новый проект для группировки сайтов.</p>
                    <Link v-if="projectAccess.can_create" href="/projects/create" class="mt-6 inline-flex h-10 items-center justify-center rounded-lg bg-[#2FA568] px-4 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(47,165,104,0.18)] transition hover:bg-[#248755]">Создать проект</Link>
                    <TariffRestriction v-else action="Создать проект" class="mx-auto mt-6 w-fit" />
                </div>
            </section>
        </div>
    </DashboardLayout>
</template>