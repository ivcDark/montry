<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

type DeadLetter = {
    id: number
    event_id: string
    source: string
    type: string
    status: string
    recoverable: boolean
    organization_id: number | null
    subject_type: string | null
    subject_id: string | null
    error_class: string | null
    error_message: string | null
    attempts: number
    max_attempts: number | null
    failed_at: string | null
    last_retry_at: string | null
    resolved_at: string | null
    correlation_id: string | null
}

const props = defineProps<{
    deadLetters: DeadLetter[]
    filters: {
        status: string
        source: string
    }
    stats: {
        open: number
        retrying: number
        resolved: number
        recoverable_open: number
    }
}>()

function applyFilter(key: 'status' | 'source', value: string) {
    router.get('/admin/dead-letters', {
        ...props.filters,
        [key]: value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function formatDate(value: string | null): string {
    if (!value) return '-'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}
</script>

<template>
    <AdminLayout
        active-item="dead_letters"
        title="Dead letters"
        subtitle="Постоянные ошибки побочных процессов и ручной контроль retry."
    >
        <section class="px-5 py-6 sm:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-5">
                        <p class="text-sm font-bold text-[#667085]">Open</p>
                        <p class="mt-2 text-3xl font-extrabold text-[#111827]">{{ stats.open }}</p>
                    </div>
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-5">
                        <p class="text-sm font-bold text-[#667085]">Recoverable</p>
                        <p class="mt-2 text-3xl font-extrabold text-[#111827]">{{ stats.recoverable_open }}</p>
                    </div>
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-5">
                        <p class="text-sm font-bold text-[#667085]">Retrying</p>
                        <p class="mt-2 text-3xl font-extrabold text-[#111827]">{{ stats.retrying }}</p>
                    </div>
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-5">
                        <p class="text-sm font-bold text-[#667085]">Resolved</p>
                        <p class="mt-2 text-3xl font-extrabold text-[#111827]">{{ stats.resolved }}</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="status in ['', 'open', 'retrying', 'resolved']"
                            :key="status || 'all-statuses'"
                            type="button"
                            class="h-9 rounded-lg px-3 text-sm font-extrabold transition"
                            :class="filters.status === status ? 'bg-[#0F6BFF] text-white' : 'bg-[#F6F8FB] text-[#667085] hover:text-[#111827]'"
                            @click="applyFilter('status', status)"
                        >
                            {{ status || 'Все статусы' }}
                        </button>
                    </div>

                    <select
                        :value="filters.source"
                        class="h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm font-bold text-[#111827] outline-none"
                        @change="applyFilter('source', ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">Все источники</option>
                        <option value="notifications">notifications</option>
                        <option value="clickhouse">clickhouse</option>
                        <option value="poller">poller</option>
                    </select>
                </div>

                <div class="mt-6 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white">
                    <table class="min-w-full divide-y divide-[#E5E7EB] text-sm">
                        <thead class="bg-[#F6F8FB] text-left text-xs font-extrabold uppercase text-[#667085]">
                            <tr>
                                <th class="px-4 py-3">Ошибка</th>
                                <th class="px-4 py-3">Статус</th>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3">Attempts</th>
                                <th class="px-4 py-3">Failed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr v-for="item in deadLetters" :key="item.id">
                                <td class="max-w-xl px-4 py-4">
                                    <p class="font-extrabold text-[#111827]">{{ item.source }} · {{ item.type }}</p>
                                    <p class="mt-1 truncate text-xs font-semibold text-[#667085]">{{ item.error_class || 'error' }}</p>
                                    <p class="mt-1 truncate text-xs text-[#667085]">{{ item.error_message || '-' }}</p>
                                    <p v-if="item.correlation_id" class="mt-1 truncate text-xs text-[#98A2B3]">{{ item.correlation_id }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full bg-[#F6F8FB] px-3 py-1 text-xs font-extrabold text-[#111827]">{{ item.status }}</span>
                                    <p class="mt-2 text-xs font-bold" :class="item.recoverable ? 'text-[#0F6BFF]' : 'text-[#667085]'">
                                        {{ item.recoverable ? 'recoverable' : 'manual review' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-xs font-semibold text-[#667085]">
                                    <p>{{ item.subject_type || '-' }}</p>
                                    <p>{{ item.subject_id || '-' }}</p>
                                    <p v-if="item.organization_id">org #{{ item.organization_id }}</p>
                                </td>
                                <td class="px-4 py-4 font-bold text-[#111827]">
                                    {{ item.attempts }} / {{ item.max_attempts || '-' }}
                                </td>
                                <td class="px-4 py-4 text-xs font-semibold text-[#667085]">
                                    {{ formatDate(item.failed_at) }}
                                </td>
                            </tr>
                            <tr v-if="deadLetters.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center">
                                    <p class="text-lg font-extrabold text-[#111827]">Dead letters нет</p>
                                    <p class="mt-1 text-sm text-[#667085]">Постоянные ошибки появятся здесь после исчерпания retry.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 text-sm text-[#667085]">
                    Recoverable записи можно вернуть в работу командой
                    <code class="rounded bg-white px-2 py-1 font-bold text-[#111827]">make artisan cmd="observability:retry-dead-letter --all"</code>.
                </p>

                <Link href="/admin/users" class="mt-6 inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] bg-white px-4 text-sm font-extrabold text-[#111827]">
                    Назад к пользователям
                </Link>
            </div>
        </section>
    </AdminLayout>
</template>

