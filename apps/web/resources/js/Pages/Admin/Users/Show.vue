<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

type AdminUser = {
    id: number | string
    name: string
    email: string
    is_admin: boolean
    is_blocked: boolean
    can_block: boolean
    created_at: string | null
}

type Plan = {
    id: number
    code: string
    name: string
    price_cents: number
    currency: string
}

type Organization = {
    id: number
    name: string
    slug: string
    status: string
    role: string
    member_status: string
    projects_count: number
    sites_count: number
    monitors_count: number
    subscription: {
        id: number
        status: string
        plan_id: number
        plan: {
            id: number | null
            code: string | null
            name: string | null
        }
    } | null
}

type Site = {
    id: number
    name: string
    target: string
    host: string
    status: string
    monitors_count: number
    organization: {
        id: number | null
        name: string | null
    }
    project: {
        id: number
        name: string
    } | null
}

type MonitorTypeOption = {
    value: string
    code?: string
    label: string
    name?: string
    short_label?: string
}

type Monitor = {
    id: number
    name: string
    type: string
    status: string
    is_enabled: boolean
    last_check_at: string | null
    next_check_at: string | null
    organization: {
        id: number | null
        name: string | null
    }
    site: {
        id: number | null
        name: string | null
        target: string | null
        host: string | null
    }
    project: {
        id: number
        name: string
    } | null
}

const props = defineProps<{
    adminUser: AdminUser
    organizations: Organization[]
    sites: Site[]
    monitors: Monitor[]
    plans: Plan[]
    monitorTypes: MonitorTypeOption[]
}>()

const planSelections = ref<Record<number, number | null>>(
    Object.fromEntries(props.organizations.map((organization) => [
        organization.id,
        organization.subscription?.plan_id ?? props.plans[0]?.id ?? null,
    ])),
)

const stats = computed(() => ({
    organizations: props.organizations.length,
    sites: props.sites.length,
    monitors: props.monitors.length,
    enabledMonitors: props.monitors.filter((monitor) => monitor.is_enabled).length,
}))

function toggleBlock(): void {
    router.patch(`/admin/users/${props.adminUser.id}/block`, {}, {
        preserveScroll: true,
    })
}

function updatePlan(organization: Organization): void {
    const planId = planSelections.value[organization.id]

    if (!planId) {
        return
    }

    router.patch(`/admin/users/${props.adminUser.id}/organizations/${organization.id}/plan`, {
        plan_id: planId,
    }, {
        preserveScroll: true,
    })
}

function formatDate(value: string | null): string {
    if (!value) return 'нет данных'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function formatMoney(plan: Plan): string {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: plan.currency,
        maximumFractionDigits: 0,
    }).format(plan.price_cents / 100)
}

function statusClass(status: string): string {
    if (['success', 'up', 'active'].includes(status)) return 'bg-[#ECFDF3] text-[#16A34A]'
    if (['failure', 'down', 'blocked'].includes(status)) return 'bg-[#FEECEC] text-[#EF4444]'
    if (['warning', 'degraded', 'past_due'].includes(status)) return 'bg-[#FFF7E8] text-[#F59E0B]'

    return 'bg-[#F1F5F9] text-[#64748B]'
}

function typeLabel(type: string): string {
    const option = props.monitorTypes.find((item) => (item.code ?? item.value) === type)

    return option?.short_label
        ?? option?.name
        ?? option?.label
        ?? type.toUpperCase()
}
</script>

<template>
    <Head :title="`Пользователь ${adminUser.email}`" />

    <AdminLayout
        active-item="users"
        :title="adminUser.name"
        :subtitle="adminUser.email"
    >
        <template #actions>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Link
                    href="/admin/users"
                    class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                >
                    К списку
                </Link>
                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center rounded-xl px-5 text-sm font-extrabold text-white transition"
                    :class="adminUser.is_blocked ? 'bg-[#16A34A] hover:bg-[#15803D]' : 'bg-[#EF4444] hover:bg-[#DC2626]'"
                    :disabled="!adminUser.can_block"
                    :title="adminUser.can_block ? '' : 'Нельзя заблокировать собственный admin аккаунт'"
                    @click="toggleBlock"
                >
                    {{ adminUser.can_block ? (adminUser.is_blocked ? 'Разблокировать' : 'Заблокировать') : 'Текущий admin' }}
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Статус</p>
                    <span
                        class="mt-4 inline-flex rounded-full px-3 py-1 text-xs font-extrabold"
                        :class="adminUser.is_blocked ? 'bg-[#FEECEC] text-[#EF4444]' : 'bg-[#ECFDF3] text-[#16A34A]'"
                    >
                        {{ adminUser.is_blocked ? 'Blocked' : 'Active' }}
                    </span>
                    <p class="mt-4 text-sm text-[#667085]">Создан: {{ formatDate(adminUser.created_at) }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Организации</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ stats.organizations }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Сайты</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#0F6BFF]">{{ stats.sites }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Мониторинги</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ stats.monitors }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Включены</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#16A34A]">{{ stats.enabledMonitors }}</p>
                </article>
            </section>

            <section class="mt-6 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="border-b border-[#E5E7EB] p-5">
                    <h2 class="text-xl font-extrabold text-[#111827]">Организации и тарифы</h2>
                    <p class="mt-1 text-sm text-[#667085]">Тариф меняется для организации, потому что подписка привязана к аккаунту организации.</p>
                </div>

                <div v-if="organizations.length" class="divide-y divide-[#E5E7EB]">
                    <article v-for="organization in organizations" :key="organization.id" class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-extrabold text-[#111827]">{{ organization.name }}</h3>
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(organization.status)">
                                    {{ organization.status }}
                                </span>
                                <span class="rounded-full bg-[#F1F5F9] px-3 py-1 text-xs font-extrabold text-[#64748B]">
                                    {{ organization.role }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-[#667085]">
                                {{ organization.sites_count }} сайтов · {{ organization.monitors_count }} мониторингов · {{ organization.projects_count }} проектов
                            </p>
                            <p class="mt-1 text-sm font-semibold text-[#111827]">
                                Текущий тариф: {{ organization.subscription?.plan?.name ?? 'не задан' }}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <select
                                v-model.number="planSelections[organization.id]"
                                class="h-11 min-w-0 flex-1 rounded-xl border border-[#E5E7EB] bg-white px-3 text-sm font-bold text-[#111827] outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                            >
                                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                    {{ plan.name }} · {{ formatMoney(plan) }}
                                </option>
                            </select>
                            <button
                                type="button"
                                class="h-11 rounded-xl bg-[#0F6BFF] px-4 text-sm font-extrabold text-white transition hover:bg-[#0757D8]"
                                @click="updatePlan(organization)"
                            >
                                Сохранить
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else class="p-10 text-center text-[#667085]">
                    Пользователь пока не состоит в организациях.
                </div>
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-2">
                <div class="overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] p-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Сайты</h2>
                        <p class="mt-1 text-sm text-[#667085]">Первые 100 сайтов в организациях пользователя.</p>
                    </div>

                    <div v-if="sites.length" class="divide-y divide-[#E5E7EB]">
                        <article v-for="site in sites" :key="site.id" class="p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-extrabold text-[#111827]">{{ site.name }}</p>
                                    <p class="mt-1 truncate text-sm font-semibold text-[#667085]">{{ site.target }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(site.status)">
                                    {{ site.status }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-[#667085]">
                                {{ site.organization.name }} · {{ site.project?.name ?? 'Без проекта' }} · {{ site.monitors_count }} мониторингов
                            </p>
                        </article>
                    </div>

                    <div v-else class="p-10 text-center text-[#667085]">
                        Сайтов нет.
                    </div>
                </div>

                <div class="overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-[#E5E7EB] p-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Мониторинги</h2>
                        <p class="mt-1 text-sm text-[#667085]">Первые 100 мониторингов в организациях пользователя.</p>
                    </div>

                    <div v-if="monitors.length" class="overflow-x-auto">
                        <table class="min-w-[720px] w-full border-separate border-spacing-0 text-left text-sm">
                            <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                            <tr>
                                <th class="px-5 py-4">Мониторинг</th>
                                <th class="px-5 py-4">Тип</th>
                                <th class="px-5 py-4">Статус</th>
                                <th class="px-5 py-4">Последняя</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="monitor in monitors" :key="monitor.id">
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <p class="font-extrabold text-[#111827]">{{ monitor.name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-[#667085]">{{ monitor.site.host ?? monitor.site.target }}</p>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 font-semibold text-[#111827]">
                                    {{ typeLabel(monitor.type) }}
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="statusClass(monitor.status)">
                                        {{ monitor.is_enabled ? monitor.status : 'paused' }}
                                    </span>
                                </td>
                                <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                    {{ formatDate(monitor.last_check_at) }}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="p-10 text-center text-[#667085]">
                        Мониторингов нет.
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
