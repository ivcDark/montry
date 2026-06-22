<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { AlertTriangle, CheckCircle2, Clock3, PauseCircle, XCircle } from '@lucide/vue'
import BrandMark from '@/Components/BrandMark.vue'

type Monitor = {
    id: number
    name: string
    target: string | null
    type: string
    type_label: string
    status: string
    last_check_at: string | null
    response_time_ms: number | null
}
type Incident = {
    id: number
    title: string
    summary: string | null
    resource: string | null
    status: string
    severity: string
    started_at: string | null
    resolved_at: string | null
}
type StatusPage = {
    id: number
    name: string
    slug: string
    description: string | null
    accent_color: string
    overall_status: string
    updated_at: string | null
    monitors: Monitor[]
    incidents: Incident[]
}

const props = withDefaults(defineProps<{ statusPage: StatusPage; isPreview?: boolean }>(), { isPreview: false })

const overall = computed(() => ({
    operational: { title: 'Все системы работают', description: 'Сбоев и деградаций не обнаружено.', icon: CheckCircle2, classes: 'bg-[#EAF8F0] text-[#168A5A] border-[#BFE5CE]' },
    degraded: { title: 'Часть систем работает нестабильно', description: 'Некоторые сервисы могут отвечать медленнее или с ошибками.', icon: AlertTriangle, classes: 'bg-[#FFF7E8] text-[#B7791F] border-[#F4D89A]' },
    outage: { title: 'Обнаружен сбой', description: 'Один или несколько сервисов сейчас недоступны.', icon: XCircle, classes: 'bg-[#FEECEC] text-[#C93636] border-[#F2C5C5]' },
    unknown: { title: 'Состояние уточняется', description: 'Для части сервисов еще нет актуальных результатов.', icon: Clock3, classes: 'bg-[#F3F4F6] text-[#667085] border-[#D7DAE0]' },
}[props.statusPage.overall_status] ?? { title: 'Состояние уточняется', description: 'Ожидаем результаты проверок.', icon: Clock3, classes: 'bg-[#F3F4F6] text-[#667085] border-[#D7DAE0]' }))

function statusMeta(status: string) {
    return {
        operational: { label: 'Работает', icon: CheckCircle2, classes: 'text-[#168A5A] bg-[#EAF8F0]' },
        degraded: { label: 'Нестабильно', icon: AlertTriangle, classes: 'text-[#B7791F] bg-[#FFF7E8]' },
        outage: { label: 'Недоступен', icon: XCircle, classes: 'text-[#C93636] bg-[#FEECEC]' },
        paused: { label: 'Приостановлен', icon: PauseCircle, classes: 'text-[#667085] bg-[#F3F4F6]' },
        unknown: { label: 'Нет данных', icon: Clock3, classes: 'text-[#667085] bg-[#F3F4F6]' },
    }[status] ?? { label: 'Нет данных', icon: Clock3, classes: 'text-[#667085] bg-[#F3F4F6]' }
}

function formatDate(value: string | null): string {
    if (!value) return 'нет данных'
    return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}
</script>

<template>
    <Head :title="statusPage.name" />

    <main class="min-h-screen bg-[#F4F7F5] text-[#26332D]">
        <div v-if="isPreview" class="border-b border-[#F4D89A] bg-[#FFF7E8] px-4 py-3 text-center text-sm font-semibold text-[#8A5B12]">
            Предпросмотр страницы. <Link href="/status-pages" class="underline">Вернуться в кабинет</Link>
        </div>

        <header class="border-b border-[#DDEBE3] bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white" :style="{ backgroundColor: statusPage.accent_color }">
                        <BrandMark class="h-7 w-7 brightness-0 invert" />
                    </span>
                    <span class="truncate text-lg font-semibold text-[#173B2A]">{{ statusPage.name }}</span>
                </div>
                <span class="shrink-0 text-xs text-[#8A9A91]">Обновлено: {{ formatDate(statusPage.updated_at) }}</span>
            </div>
        </header>

        <div class="mx-auto max-w-4xl px-5 py-8 sm:px-8 sm:py-12">
            <section class="rounded-2xl border p-5 sm:p-6" :class="overall.classes">
                <div class="flex items-start gap-4">
                    <component :is="overall.icon" class="mt-0.5 h-6 w-6 shrink-0" :stroke-width="2" />
                    <div>
                        <h1 class="text-xl font-semibold">{{ overall.title }}</h1>
                        <p class="mt-1 text-sm leading-6 opacity-80">{{ overall.description }}</p>
                    </div>
                </div>
            </section>

            <p v-if="statusPage.description" class="mt-6 text-base leading-7 text-[#52645A]">{{ statusPage.description }}</p>

            <section class="mt-8 overflow-hidden rounded-2xl border border-[#DDEBE3] bg-white shadow-[0_10px_28px_rgba(23,59,42,0.04)]">
                <div class="border-b border-[#DDEBE3] px-5 py-4 sm:px-6">
                    <h2 class="text-lg font-semibold text-[#173B2A]">Состояние сервисов</h2>
                </div>
                <div class="divide-y divide-[#E7F0EB]">
                    <div v-for="monitor in statusPage.monitors" :key="monitor.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-[#26332D]">{{ monitor.name }}</h3>
                            <p class="mt-1 truncate text-xs text-[#8A9A91]">{{ monitor.type_label }}<template v-if="monitor.target"> · {{ monitor.target }}</template></p>
                        </div>
                        <div class="flex items-center justify-between gap-4 sm:justify-end">
                            <span v-if="monitor.response_time_ms !== null" class="text-xs font-medium text-[#8A9A91]">{{ monitor.response_time_ms }} мс</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold" :class="statusMeta(monitor.status).classes">
                                <component :is="statusMeta(monitor.status).icon" class="h-3.5 w-3.5" :stroke-width="2" />
                                {{ statusMeta(monitor.status).label }}
                            </span>
                        </div>
                    </div>
                    <p v-if="!statusPage.monitors.length" class="px-6 py-8 text-center text-sm text-[#8A9A91]">Мониторы не добавлены.</p>
                </div>
            </section>

            <section v-if="statusPage.incidents.length" class="mt-8">
                <h2 class="text-lg font-semibold text-[#173B2A]">Последние инциденты</h2>
                <div class="mt-4 grid gap-3">
                    <article v-for="incident in statusPage.incidents" :key="incident.id" class="rounded-2xl border border-[#DDEBE3] bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-[#26332D]">{{ incident.title }}</h3>
                                <p class="mt-1 text-xs text-[#8A9A91]">{{ incident.resource }} · {{ formatDate(incident.started_at) }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="incident.status === 'open' ? 'bg-[#FEECEC] text-[#C93636]' : 'bg-[#EAF8F0] text-[#168A5A]'">
                                {{ incident.status === 'open' ? 'Продолжается' : 'Устранён' }}
                            </span>
                        </div>
                        <p v-if="incident.summary" class="mt-3 text-sm leading-6 text-[#6A7A70]">{{ incident.summary }}</p>
                    </article>
                </div>
            </section>

            <footer class="mt-10 flex items-center justify-center gap-2 text-xs text-[#8A9A91]">
                <BrandMark class="h-5 w-5" />
                Страница работает на Montry
            </footer>
        </div>
    </main>
</template>
