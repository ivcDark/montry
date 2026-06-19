<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArrowRight,
    CalendarClock,
    Check,
    CheckCircle2,
    Clock3,
    Globe2,
    History,
    Minus,
    ShieldCheck,
    Sparkles,
    UsersRound,
    X,
    Zap,
} from '@lucide/vue'
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
    checkoutNotice?: string | null
    plans: Plan[]
    usage: {
        sites: number
        monitors: number
        active_monitors: number
        site_limit?: number | null
    }
}>()

const currentPlanCode = computed(() => props.currentSubscription?.plan.code ?? 'free')
const currentPlan = computed(() => props.currentSubscription?.plan ?? props.plans.find((plan) => plan.code === currentPlanCode.value) ?? null)
const selectedDowngradePlan = ref<Plan | null>(null)
const effectiveSiteLimit = computed(() => props.usage.site_limit ?? currentPlan.value?.limits.max_sites?.limit ?? null)
const currentMonitorLimit = computed(() => currentPlan.value?.limits.max_monitors?.limit ?? null)
const currentHistoryDays = computed(() => currentPlan.value?.limits.history_retention_days?.days ?? 0)
const currentIntervalMinutes = computed(() => Math.max(1, Math.round((currentPlan.value?.limits.minimum_check_interval_seconds?.seconds ?? 300) / 60)))
const currentMonitorTypesLabel = computed(() => {
    const types = currentPlan.value?.limits.allowed_monitor_types?.types ?? []

    return types.length > 2 || types.includes('*') ? 'Все типы' : 'HTTP и SSL'
})
const currentChannelsLabel = computed(() => currentPlan.value ? formatChannels(currentPlan.value) : 'Email')
const monitorUsagePercent = computed(() => usagePercent(props.usage.active_monitors, currentMonitorLimit.value))

function money(plan: Plan): string {
    if (plan.price_cents === 0) {
        return '0 ₽'
    }

    return `${new Intl.NumberFormat('ru-RU').format(plan.price_cents / 100)} ₽`
}

function selectPlan(plan: Plan): void {
    router.post('/billing/checkout', {
        plan_code: plan.code,
    })
}

function formatChannels(plan: Plan): string {
    const labels: Record<string, string> = {
        email: 'Email',
        telegram: 'Telegram',
        webhook: 'Webhook',
    }
    const channels = plan.limits.notification_channels?.channels ?? []

    if (!channels.length) {
        return 'Не включены'
    }

    return channels.map((channel: string) => labels[channel] ?? channel).join(' и ')
}

function pluralize(value: number, forms: [string, string, string]): string {
    const normalized = Math.abs(value) % 100
    const lastDigit = normalized % 10

    if (normalized > 10 && normalized < 20) {
        return forms[2]
    }

    if (lastDigit === 1) {
        return forms[0]
    }

    if (lastDigit >= 2 && lastDigit <= 4) {
        return forms[1]
    }

    return forms[2]
}

const publicPagesByPlan: Record<string, number> = {
    free: 1,
    pro: 10,
    team: 30,
}

const usersByPlan: Record<string, number> = {
    free: 1,
    pro: 1,
    team: 10,
}

function planFeatures(plan: Plan): string[] {
    const monitors = plan.limits.max_monitors?.limit
    const historyDays = plan.limits.history_retention_days?.days ?? 0
    const intervalMinutes = Math.max(1, Math.round((plan.limits.minimum_check_interval_seconds?.seconds ?? 300) / 60))
    const publicPages = plan.limits.max_public_status_pages?.limit ?? publicPagesByPlan[plan.code] ?? 0
    const users = plan.limits.max_organization_users?.limit ?? usersByPlan[plan.code] ?? 1

    return [
        monitors === null || monitors === undefined ? 'Мониторинги без лимита' : `До ${monitors} мониторингов`,
        `Интервал от ${intervalMinutes} ${pluralize(intervalMinutes, ['минуты', 'минут', 'минут'])}`,
        `Оповещения: ${formatChannels(plan)}`,
        `История проверок: ${historyDays} ${pluralize(historyDays, ['день', 'дня', 'дней'])}`,
        `До ${publicPages} ${pluralize(publicPages, ['публичной страницы', 'публичных страниц', 'публичных страниц'])}`,
        users > 1 ? `До ${users} пользователей` : '1 пользователь',
    ]
}

function usagePercent(current: number, limit: number | null): number {
    if (limit === null) {
        return current > 0 ? 100 : 0
    }

    if (limit <= 0) {
        return 0
    }

    return Math.min((current / limit) * 100, 100)
}

function limitLabel(limit: number | null): string {
    return limit === null ? '∞' : String(limit)
}

function isDowngrade(plan: Plan): boolean {
    const activePlan = props.currentSubscription?.plan

    if (!activePlan || plan.code === activePlan.code) {
        return false
    }

    if (plan.sort_order !== activePlan.sort_order) {
        return plan.sort_order < activePlan.sort_order
    }

    return plan.price_cents < activePlan.price_cents
}

function isFeatured(plan: Plan): boolean {
    return plan.code === 'pro'
}

function comparisonValue(plan: Plan, key: string): string {
    const limits = plan.limits
    const intervalMinutes = Math.max(1, Math.round((limits.minimum_check_interval_seconds?.seconds ?? 300) / 60))
    const historyDays = limits.history_retention_days?.days ?? 0
    const monitorTypes = limits.allowed_monitor_types?.types ?? []
    const publicPages = limits.max_public_status_pages?.limit ?? publicPagesByPlan[plan.code] ?? 0
    const users = limits.max_organization_users?.limit ?? usersByPlan[plan.code] ?? 1

    if (key === 'price') {
        return plan.price_cents === 0 ? '0 ₽' : `${money(plan)} / месяц`
    }

    if (key === 'monitors') {
        return String(limits.max_monitors?.limit ?? 'Без ограничений')
    }

    if (key === 'interval') {
        return `${intervalMinutes} ${pluralize(intervalMinutes, ['минута', 'минуты', 'минут'])}`
    }

    if (key === 'history') {
        return `${historyDays} ${pluralize(historyDays, ['день', 'дня', 'дней'])}`
    }

    if (key === 'types') {
        return monitorTypes.length > 2 || monitorTypes.includes('*') ? 'Все типы' : 'HTTP, SSL'
    }

    if (key === 'notifications') {
        return formatChannels(plan).replace(' и ', ', ')
    }

    if (key === 'users') {
        return users > 1 ? `До ${users}` : '1'
    }

    if (key === 'projects') {
        return plan.code === 'free' ? '1' : 'Без ограничений'
    }

    if (key === 'public_pages') {
        return plan.code === 'free' ? '1' : `До ${publicPages}`
    }

    if (key === 'reports') {
        return plan.code === 'free' ? '—' : 'Email, PDF'
    }

    return ''
}

function formatDate(value: string | null | undefined, withTime = false): string {
    if (!value) {
        return 'после окончания текущего периода'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(new Date(value))
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
    { key: 'price', label: 'Цена в месяц' },
    { key: 'monitors', label: 'Активные мониторинги' },
    { key: 'interval', label: 'Минимальный интервал' },
    { key: 'history', label: 'История' },
    { key: 'types', label: 'Типы мониторинга' },
    { key: 'notifications', label: 'Уведомления' },
    { key: 'users', label: 'Пользователи' },
    { key: 'projects', label: 'Проекты' },
    { key: 'public_pages', label: 'Публичные страницы' },
    { key: 'reports', label: 'Отчёты' },
]
</script>

<template>
    <Head title="Тарифы" />

    <DashboardLayout
        :organization="organization"
        active-item="billing"
        title="Тарифы"
        subtitle="Управление лимитами мониторинга и оплатой"
        :usage-current="usage.sites"
        :usage-limit="effectiveSiteLimit ?? undefined"
    >
        <section class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:py-10">
            <div class="mx-auto max-w-6xl overflow-hidden rounded-[28px] border border-[#CFE1D7] bg-[#173B2A] shadow-[0_18px_60px_rgba(23,59,42,0.14)]">
                <div class="relative grid gap-5 px-6 py-6 sm:px-8 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.55fr)] lg:px-9 lg:py-6">
                    <div class="pointer-events-none absolute -right-20 -top-28 h-72 w-72 rounded-full bg-[#2FA568]/20 blur-3xl" />
                    <div class="pointer-events-none absolute -bottom-24 left-1/3 h-60 w-60 rounded-full bg-[#DDF6E8]/10 blur-3xl" />

                    <div class="relative flex flex-col justify-center">
                        <div class="inline-flex w-fit items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-[#CFF2DC]">
                            <Sparkles class="h-4 w-4" :stroke-width="2" />
                            Тарифы Montry
                        </div>
                        <h1 class="mt-4 max-w-2xl text-3xl font-semibold leading-tight text-white sm:text-[34px]">
                            Мониторинг без доплат за отдельные проверки
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-[#C8D9CF] sm:text-[15px]">
                            Free подходит для старта, Pro — для регулярного мониторинга, Team — для студий и команд с большим количеством проектов.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-[#E5F4EB]">
                            <span class="inline-flex items-center gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#65D493]" :stroke-width="2" />
                                Без отдельных платных дополнений
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#65D493]" :stroke-width="2" />
                                Смена тарифа в любой момент
                            </span>
                        </div>
                    </div>

                    <div class="relative rounded-[22px] border border-white/15 bg-white/10 p-5 backdrop-blur">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#9FC6AE]">Текущий тариф</p>
                                <h2 class="mt-1 text-2xl font-semibold text-white">{{ currentPlan?.name ?? 'Free' }}</h2>
                            </div>
                            <span class="rounded-full bg-[#65D493]/15 px-3 py-1.5 text-xs font-semibold text-[#8DE4B0]">Активен</span>
                        </div>

                        <div class="mt-4">
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-[#C8D9CF]">Активные мониторинги</span>
                                <span class="font-semibold text-white">{{ usage.active_monitors }} / {{ limitLabel(currentMonitorLimit) }}</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-[#65D493] transition-all" :style="{ width: `${monitorUsagePercent}%` }" />
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="flex items-center gap-3 rounded-2xl bg-white/10 p-3">
                                <History class="h-5 w-5 shrink-0 text-[#8DE4B0]" :stroke-width="2" />
                                <div>
                                    <p class="font-semibold text-white">{{ currentHistoryDays }} дней</p>
                                    <p class="text-xs text-[#B7CCBF]">История</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl bg-white/10 p-3">
                                <Clock3 class="h-5 w-5 shrink-0 text-[#8DE4B0]" :stroke-width="2" />
                                <div>
                                    <p class="font-semibold text-white">от {{ currentIntervalMinutes }} мин.</p>
                                    <p class="text-xs text-[#B7CCBF]">Интервал</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-4 text-xs text-[#B7CCBF]">
                            <span>{{ currentMonitorTypesLabel }}</span>
                            <span>{{ currentChannelsLabel }}</span>
                        </div>

                        <p v-if="currentSubscription?.ends_at" class="mt-4 flex items-center gap-2 text-sm text-[#C8D9CF]">
                            <CalendarClock class="h-4 w-4" :stroke-width="2" />
                            Оплачен до {{ formatDate(currentSubscription.ends_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="scheduledSubscription"
                class="mt-6 flex flex-col gap-4 rounded-[24px] border border-[#F2D49D] bg-[#FFF9ED] p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
            >
                <div class="flex gap-4">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#FFF0CF] text-[#C87800]">
                        <Clock3 class="h-5 w-5" :stroke-width="2" />
                    </span>
                    <div>
                        <p class="font-semibold text-[#7A4A00]">Запланирован переход на {{ scheduledSubscription.plan.name }}</p>
                        <p class="mt-1 text-sm leading-6 text-[#8A6A35]">
                            Новый тариф подключится {{ formatDate(scheduledSubscription.starts_at, true) }}. При уменьшении лимитов лишние мониторинги будут приостановлены автоматически.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-10 text-center">
                <p class="text-sm font-semibold text-[#1E9B5D]">Простые тарифы без лишних условий</p>
                <h2 class="mt-2 text-3xl font-semibold text-[#26332D]">Найдите подходящий объём мониторинга</h2>
                <p class="mx-auto mt-3 max-w-2xl text-base leading-7 text-[#6A7A70]">
                    Сравните лимиты мониторингов, частоту проверок, каналы оповещений, глубину истории и возможности для команды.
                </p>
            </div>

            <div class="mt-7 grid items-stretch gap-5 lg:grid-cols-3">
                <article
                    v-for="plan in plans"
                    :key="plan.code"
                    class="relative flex min-h-full flex-col overflow-hidden rounded-[28px] border bg-white p-6 transition duration-200 sm:p-7"
                    :class="[
                        plan.code === currentPlanCode
                            ? 'border-[#2FA568] shadow-[0_18px_50px_rgba(47,165,104,0.14)]'
                            : 'border-[#DDEBE3] hover:-translate-y-1 hover:border-[#B8D8C5] hover:shadow-[0_18px_45px_rgba(23,59,42,0.09)]',
                        isFeatured(plan) ? 'border-[#21A663] bg-gradient-to-b from-[#F1FCF5] via-white to-white shadow-[0_24px_65px_rgba(30,155,93,0.22)] ring-2 ring-[#21A663]/20 lg:-translate-y-2 lg:hover:-translate-y-3' : '',
                    ]"
                >
                    <div v-if="isFeatured(plan)" class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-[#49D889] via-[#1E9B5D] to-[#0F6F40]"></div>

                    <div v-if="isFeatured(plan)" class="absolute right-5 top-5 inline-flex items-center gap-1.5 rounded-full bg-[#173B2A] px-3.5 py-2 text-xs font-semibold text-white shadow-[0_8px_20px_rgba(23,59,42,0.22)]">
                        <Sparkles class="h-3.5 w-3.5 text-[#8DE4B0]" :stroke-width="2.25" />
                        Популярный выбор
                    </div>

                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-2xl border"
                            :class="isFeatured(plan)
                                ? 'border-[#A9E2C0] bg-[#DDF6E8] text-[#178A50] shadow-[0_8px_20px_rgba(30,155,93,0.14)]'
                                : plan.code === currentPlanCode
                                    ? 'border-[#BEE7CE] bg-[#DDF6E8] text-[#178A50]'
                                    : 'border-[#DDEBE3] bg-[#F3F8F5] text-[#52645A]'"
                        >
                            <Globe2 v-if="plan.code === 'free'" class="h-5 w-5" :stroke-width="2" />
                            <Zap v-else-if="isFeatured(plan)" class="h-5 w-5" :stroke-width="2" />
                            <UsersRound v-else-if="plan.code === 'team'" class="h-5 w-5" :stroke-width="2" />
                            <ShieldCheck v-else class="h-5 w-5" :stroke-width="2" />
                        </span>
                        <div>
                            <p v-if="plan.code === currentPlanCode" class="text-xs font-semibold uppercase tracking-[0.12em] text-[#1E9B5D]">Текущий тариф</p>
                            <h3 class="text-2xl font-semibold text-[#26332D]">{{ plan.name }}</h3>
                        </div>
                    </div>

                    <p class="mt-5 min-h-[52px] text-sm leading-6 text-[#6A7A70]">
                        {{ plan.description || 'Надёжный мониторинг сайтов и важных технических параметров.' }}
                    </p>

                    <div class="mt-6 flex items-end gap-2 border-b border-[#E5EFE9] pb-6">
                        <p class="text-4xl font-semibold tracking-tight text-[#173B2A]">{{ money(plan) }}</p>
                        <p v-if="plan.price_cents > 0" class="pb-1 text-sm font-medium text-[#7A8980]">/ месяц</p>
                        <p v-else class="pb-1 text-sm font-medium text-[#7A8980]">навсегда</p>
                    </div>

                    <ul class="mt-6 flex-1 space-y-3.5">
                        <li v-for="item in planFeatures(plan)" :key="item" class="flex items-start gap-3 text-sm leading-6 text-[#52645A]">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#E9F8EF] text-[#1E9B5D]">
                                <Check class="h-3.5 w-3.5" :stroke-width="2.5" />
                            </span>
                            {{ item }}
                        </li>
                    </ul>

                    <div class="mt-7">
                        <div
                            v-if="plan.code === currentPlanCode"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[#E9F8EF] px-5 text-sm font-semibold text-[#178A50]"
                        >
                            <CheckCircle2 class="h-5 w-5" :stroke-width="2" />
                            Подключён
                        </div>

                        <button
                            v-else-if="isDowngrade(plan)"
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl border border-[#E8D2A8] bg-[#FFF9ED] px-5 text-sm font-semibold text-[#A76500] transition hover:border-[#D6B97E] hover:bg-[#FFF4DC]"
                            @click="openDowngradeModal(plan)"
                        >
                            <Minus class="h-4 w-4" :stroke-width="2.25" />
                            Понизить тариф
                        </button>

                        <button
                            v-else
                            type="button"
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl px-5 text-sm font-semibold text-white transition"
                            :class="isFeatured(plan)
                                ? 'bg-[#1E9B5D] shadow-[0_12px_28px_rgba(30,155,93,0.28)] hover:bg-[#178A50] hover:shadow-[0_14px_32px_rgba(30,155,93,0.34)]'
                                : 'bg-[#173B2A] hover:bg-[#214E38]'"
                            @click="selectPlan(plan)"
                        >
                            {{ isFeatured(plan) ? `Подключить ${plan.name}` : `Выбрать ${plan.name}` }}
                            <ArrowRight class="h-4 w-4" :stroke-width="2.25" />
                        </button>
                    </div>
                </article>
            </div>

            <div
                v-if="checkoutNotice"
                class="mt-10 flex gap-3 rounded-[22px] border border-[#F0D7A9] bg-[#FFF8E9] p-5 text-sm leading-6 text-[#8A5A12]"
            >
                <Clock3 class="mt-0.5 h-5 w-5 shrink-0 text-[#C87800]" :stroke-width="2" />
                <p>{{ checkoutNotice }}</p>
            </div>

            <div class="mt-10 overflow-hidden rounded-[30px] border border-[#DDEBE3] bg-white">
                <div class="border-b border-[#DDEBE3] px-6 py-6 sm:px-8">
                    <p class="text-sm font-semibold text-[#1E9B5D]">Подробное сравнение</p>
                    <h2 class="mt-2 text-2xl font-semibold text-[#26332D] sm:text-3xl">Лимиты и возможности тарифов</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <thead class="bg-[#F6FBF8] text-xs font-semibold uppercase tracking-[0.08em] text-[#6A7A70]">
                            <tr>
                                <th class="px-6 py-4 sm:px-8">Параметр</th>
                                <th
                                    v-for="plan in plans"
                                    :key="plan.code"
                                    class="px-6 py-4"
                                    :class="isFeatured(plan) ? 'border-x border-[#CDE9D8] bg-[#EAF8F0]' : ''"
                                >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span :class="isFeatured(plan) ? 'font-bold text-[#173B2A]' : ''">{{ plan.name }}</span>
                                        <span v-if="isFeatured(plan)" class="inline-flex items-center gap-1 rounded-full bg-[#173B2A] px-2.5 py-1 text-[10px] font-semibold normal-case tracking-normal text-white">
                                            <Sparkles class="h-3 w-3 text-[#8DE4B0]" :stroke-width="2.25" />
                                            популярный
                                        </span>
                                        <span v-if="plan.code === currentPlanCode" class="rounded-full bg-[#DDF6E8] px-2 py-1 text-[10px] normal-case tracking-normal text-[#178A50]">текущий</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5EFE9]">
                            <tr v-for="row in comparisonRows" :key="row.key" class="transition hover:bg-[#FAFCFB]">
                                <td class="px-6 py-4 font-semibold text-[#26332D] sm:px-8">{{ row.label }}</td>
                                <td
                                    v-for="plan in plans"
                                    :key="`${row.key}-${plan.code}`"
                                    class="px-6 py-4 leading-6 text-[#52645A]"
                                    :class="isFeatured(plan) ? 'border-x border-[#E0F0E6] bg-[#F5FBF7] font-medium text-[#294A38]' : ''"
                                >
                                    {{ comparisonValue(plan, row.key) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 rounded-[24px] border border-[#DDEBE3] bg-[#F6FBF8] p-5 text-sm leading-6 text-[#6A7A70] sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>
                    Оплачивая тариф, вы принимаете условия
                    <Link href="/offers" class="font-semibold text-[#178A50] transition hover:text-[#126F41]">публичной оферты</Link>.
                </p>
                <span class="inline-flex shrink-0 items-center gap-2 font-medium text-[#52645A]">
                    <ShieldCheck class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                    Безопасная оплата
                </span>
            </div>
        </section>

        <div
            v-if="selectedDowngradePlan"
            class="fixed inset-0 z-50 grid place-items-center bg-[#173B2A]/55 px-5 py-8 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            @click.self="closeDowngradeModal"
        >
            <div class="w-full max-w-lg rounded-[28px] border border-[#DDEBE3] bg-white p-6 shadow-[0_30px_90px_rgba(23,59,42,0.28)] sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-[#FFF4DC] text-[#B36D00]">
                        <CalendarClock class="h-6 w-6" :stroke-width="2" />
                    </span>
                    <button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-full text-[#7A8980] transition hover:bg-[#F3F8F5] hover:text-[#26332D]"
                        aria-label="Закрыть"
                        @click="closeDowngradeModal"
                    >
                        <X class="h-5 w-5" :stroke-width="2" />
                    </button>
                </div>

                <p class="mt-5 text-sm font-semibold text-[#B36D00]">Понижение тарифа</p>
                <h2 class="mt-2 text-2xl font-semibold text-[#26332D]">
                    Перейти на {{ selectedDowngradePlan.name }}?
                </h2>
                <p class="mt-3 leading-7 text-[#6A7A70]">
                    Новый тариф включится после {{ currentSubscription?.ends_at ? formatDate(currentSubscription.ends_at, true) : 'окончания текущего периода' }}.
                    Если новые лимиты окажутся ниже текущего использования, лишние мониторинги будут приостановлены. Активными останутся самые старые.
                </p>

                <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center rounded-2xl border border-[#DDEBE3] px-5 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                        @click="closeDowngradeModal"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center rounded-2xl bg-[#173B2A] px-5 text-sm font-semibold text-white transition hover:bg-[#214E38]"
                        @click="confirmDowngrade"
                    >
                        Запланировать переход
                    </button>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
