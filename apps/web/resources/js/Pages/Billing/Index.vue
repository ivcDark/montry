<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Plan = {
    code: string
    name: string
    description: string | null
    price_cents: number
    currency: string
    sort_order: number
    limits: Record<string, any>
}

type Subscription = {
    status: string
    starts_at?: string | null
    ends_at?: string | null
    plan: Plan
}

const props = defineProps<{
    organization: { id: string | number; name: string }
    currentSubscription: Subscription | null
    scheduledSubscription: Subscription | null
    plans: Plan[]
    usage: {
        sites: number
        monitors: number
        active_monitors: number
    }
}>()

const currentPlanCode = computed(() => props.currentSubscription?.plan.code ?? 'free')
const selectedDowngradePlan = ref<Plan | null>(null)

function money(plan: Plan): string {
    if (plan.price_cents === 0) {
        return '0 ₽'
    }

    return `${new Intl.NumberFormat('ru-RU').format(plan.price_cents / 100)} ₽/мес`
}

function planLimit(plan: Plan, key: string, valueKey: string, fallback: string | number = 'без лимита'): string | number {
    return plan.limits[key]?.[valueKey] ?? fallback
}

function limitText(plan: Plan): string[] {
    const limits = plan.limits
    const monitorTypes = limits.allowed_monitor_types?.types?.includes('*')
        ? 'все доступные типы'
        : (limits.allowed_monitor_types?.types ?? []).join(', ').toUpperCase()

    return [
        `Сайты: до ${limits.max_sites?.limit ?? 'без лимита'}`,
        `Мониторы: ${limits.max_monitors?.limit ?? 'без лимита'}`,
        `Типы: ${monitorTypes}`,
        `История: ${limits.history_retention_days?.days ?? 0} дней`,
        `Интервал: от ${Math.round((limits.minimum_check_interval_seconds?.seconds ?? 300) / 60)} мин`,
        `Уведомления: ${(limits.notification_channels?.channels ?? []).join(', ')}`,
    ]
}

function isDowngrade(plan: Plan): boolean {
    const currentPlan = props.currentSubscription?.plan

    if (!currentPlan || plan.code === currentPlan.code) {
        return false
    }

    if (plan.sort_order !== currentPlan.sort_order) {
        return plan.sort_order < currentPlan.sort_order
    }

    return plan.price_cents < currentPlan.price_cents
}

function comparisonValue(plan: Plan, key: string): string {
    const limits = plan.limits

    if (key === 'price') {
        return money(plan)
    }

    if (key === 'max_sites') {
        return String(planLimit(plan, 'max_sites', 'limit'))
    }

    if (key === 'max_monitors') {
        return String(planLimit(plan, 'max_monitors', 'limit'))
    }

    if (key === 'types') {
        return limits.allowed_monitor_types?.types?.includes('*')
            ? 'Все'
            : (limits.allowed_monitor_types?.types ?? []).join(', ').toUpperCase()
    }

    if (key === 'history') {
        return `${planLimit(plan, 'history_retention_days', 'days', 0)} дней`
    }

    if (key === 'projects') {
        return limits.can_create_projects?.enabled ? 'Да' : 'Нет'
    }

    return ''
}

function openDowngradeModal(plan: Plan): void {
    selectedDowngradePlan.value = plan
}

function closeDowngradeModal(): void {
    selectedDowngradePlan.value = null
}

function confirmDowngrade(): void {
    if (!selectedDowngradePlan.value) {
        return
    }

    router.post('/billing/schedule-downgrade', {
        plan_code: selectedDowngradePlan.value.code,
    }, {
        onFinish: () => closeDowngradeModal(),
    })
}

const comparisonRows = [
    { key: 'price', label: 'Стоимость' },
    { key: 'max_sites', label: 'Сайты' },
    { key: 'max_monitors', label: 'Мониторы' },
    { key: 'types', label: 'Типы проверок' },
    { key: 'history', label: 'История' },
    { key: 'projects', label: 'Проекты' },
]
</script>

<template>
    <Head title="Тариф" />

    <DashboardLayout
        :organization="organization"
        active-item="billing"
        title="Тариф"
        subtitle="Управление лимитами мониторинга и оплатой"
        :usage-current="usage.monitors"
        :usage-limit="currentSubscription?.plan.limits.max_monitors?.limit ?? 50"
    >
        <section class="mx-auto grid max-w-7xl gap-6 px-5 py-8 sm:px-8">
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6">
                <p class="text-sm font-extrabold text-[#12B3A8]">Текущий тариф</p>
                <div class="mt-2 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="text-2xl font-extrabold text-[#111827]">
                            {{ currentSubscription?.plan.name ?? 'Free' }}
                        </h2>
                        <p class="mt-2 text-[#667085]">
                            {{ usage.sites }} сайтов · {{ usage.active_monitors }} активных из {{ usage.monitors }} мониторингов
                        </p>
                    </div>
                    <p v-if="currentSubscription?.ends_at" class="text-sm font-bold text-[#667085]">
                        Действует до {{ new Date(currentSubscription.ends_at).toLocaleDateString('ru-RU') }}
                    </p>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-bold text-[#667085]">Сайты</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#111827]">
                            {{ usage.sites }} / {{ currentSubscription?.plan.limits.max_sites?.limit ?? '∞' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-bold text-[#667085]">Активные мониторы</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#111827]">
                            {{ usage.active_monitors }} / {{ currentSubscription?.plan.limits.max_monitors?.limit ?? '∞' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-[#F8FAFC] p-4">
                        <p class="text-xs font-bold text-[#667085]">История</p>
                        <p class="mt-2 text-2xl font-extrabold text-[#111827]">
                            {{ currentSubscription?.plan.limits.history_retention_days?.days ?? 0 }} дней
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="scheduledSubscription" class="rounded-2xl border border-[#FED7AA] bg-[#FFFBF1] p-6">
                <p class="text-sm font-extrabold text-[#F59E0B]">Запланирована смена тарифа</p>
                <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">
                    {{ scheduledSubscription.plan.name }}
                </h2>
                <p class="mt-2 text-[#667085]">
                    Подключится {{ scheduledSubscription.starts_at ? new Date(scheduledSubscription.starts_at).toLocaleString('ru-RU') : 'после окончания текущего тарифа' }}.
                    Если лимиты уменьшатся, лишние мониторинги будут приостановлены автоматически.
                </p>
            </div>

            <div class="grid gap-5 lg:grid-cols-3">
                <article
                    v-for="plan in plans"
                    :key="plan.code"
                    class="rounded-2xl border bg-white p-6"
                    :class="plan.code === currentPlanCode ? 'border-[#0F6BFF]' : 'border-[#E5E7EB]'"
                >
                    <p v-if="plan.code === currentPlanCode" class="text-xs font-extrabold uppercase tracking-normal text-[#0F6BFF]">
                        Активен
                    </p>
                    <h3 class="mt-2 text-2xl font-extrabold text-[#111827]">{{ plan.name }}</h3>
                    <p class="mt-2 min-h-12 text-sm leading-6 text-[#667085]">{{ plan.description }}</p>
                    <p class="mt-5 text-3xl font-extrabold text-[#111827]">{{ money(plan) }}</p>

                    <ul class="mt-5 grid gap-3 text-sm font-semibold text-[#475467]">
                        <li v-for="item in limitText(plan)" :key="item">{{ item }}</li>
                    </ul>

                    <button
                        v-if="plan.code !== currentPlanCode && isDowngrade(plan)"
                        type="button"
                        class="mt-6 flex h-11 w-full items-center justify-center rounded-xl border border-[#FED7AA] bg-[#FFFBF1] px-4 text-sm font-extrabold text-[#B45309] transition hover:border-[#FDBA74]"
                        @click="openDowngradeModal(plan)"
                    >
                        Понизить тариф
                    </button>

                    <Link
                        v-else-if="plan.code !== currentPlanCode"
                        href="/billing/checkout"
                        method="post"
                        as="button"
                        :data="{ plan_code: plan.code }"
                        class="mt-6 flex h-11 w-full items-center justify-center rounded-xl bg-[#0F6BFF] px-4 text-sm font-extrabold text-white transition hover:bg-[#0757D8]"
                    >
                        Выбрать тариф
                    </Link>
                </article>
            </div>

            <p class="text-sm leading-6 text-[#667085]">
                Выбирая платный тариф, вы переходите к оформлению платежа и принимаете условия
                <Link href="/offers" class="font-extrabold text-[#0F6BFF] transition hover:text-[#0757D8]">публичной оферты</Link>.
            </p>

            <div class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white">
                <div class="border-b border-[#E5E7EB] p-6">
                    <p class="text-sm font-extrabold text-[#12B3A8]">Сравнение тарифов</p>
                    <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">Лимиты и возможности</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-[#F8FAFC] text-xs font-extrabold uppercase tracking-normal text-[#667085]">
                            <tr>
                                <th class="px-6 py-4">Параметр</th>
                                <th v-for="plan in plans" :key="plan.code" class="px-6 py-4">{{ plan.name }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr v-for="row in comparisonRows" :key="row.key">
                                <td class="px-6 py-4 font-bold text-[#111827]">{{ row.label }}</td>
                                <td v-for="plan in plans" :key="`${row.key}-${plan.code}`" class="px-6 py-4 text-[#475467]">
                                    {{ comparisonValue(plan, row.key) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div
            v-if="selectedDowngradePlan"
            class="fixed inset-0 z-50 grid place-items-center bg-[#111827]/40 px-5"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-[0_24px_64px_rgba(15,23,42,0.24)]">
                <p class="text-sm font-extrabold text-[#F59E0B]">Понижение тарифа</p>
                <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">
                    Перейти на {{ selectedDowngradePlan.name }}?
                </h2>
                <p class="mt-3 leading-7 text-[#667085]">
                    При понижении тарифа лимиты будут уменьшены. Лишние мониторинги будут приостановлены после
                    {{ currentSubscription?.ends_at ? new Date(currentSubscription.ends_at).toLocaleString('ru-RU') : 'окончания текущего тарифа' }}.
                    Активными останутся самые старые мониторинги по дате создания.
                </p>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#CBD5E1]"
                        @click="closeDowngradeModal"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white transition hover:bg-[#0757D8]"
                        @click="confirmDowngrade"
                    >
                        Подтвердить
                    </button>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
