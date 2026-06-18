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
    Layers3,
    Minus,
    Plus,
    ShieldCheck,
    Sparkles,
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

type AddonCatalogItem = {
    code: string
    name: string
    description: string
    unit_label: string
    unit_price_cents: number
    currency: string
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
    selectedPlanCode?: string | null
    plans: Plan[]
    usage: {
        sites: number
        monitors: number
        active_monitors: number
        site_limit?: number | null
    }
    addonCatalog?: AddonCatalogItem[]
    currentAddons?: Record<string, { quantity: number, unit_price_cents: number, currency: string }>
    entitlements?: Record<string, any>
}>()

const currentPlanCode = computed(() => props.currentSubscription?.plan.code ?? 'free')
const currentPlan = computed(() => props.currentSubscription?.plan ?? props.plans.find((plan) => plan.code === currentPlanCode.value) ?? null)
const checkoutPlanCode = ref<string | null>(
    props.selectedPlanCode && props.selectedPlanCode !== currentPlanCode.value
        ? props.selectedPlanCode
        : null,
)
const checkoutPlan = computed(() => props.plans.find((plan) => plan.code === checkoutPlanCode.value) ?? null)
const isPlanCheckout = computed(() => checkoutPlan.value !== null)
const selectedDowngradePlan = ref<Plan | null>(null)
const effectiveSiteLimit = computed(() => props.usage.site_limit ?? currentPlan.value?.limits.max_sites?.limit ?? null)
const currentHistoryDays = computed(() => currentPlan.value?.limits.history_retention_days?.days ?? 0)
const siteUsagePercent = computed(() => usagePercent(props.usage.sites, effectiveSiteLimit.value))
const activeMonitorPercent = computed(() => usagePercent(props.usage.active_monitors, props.usage.monitors || null))
const addonDrafts = ref<Record<string, number>>(
    Object.fromEntries(
        (props.addonCatalog ?? []).map((addon) => [
            addon.code,
            checkoutPlanCode.value ? 0 : (props.currentAddons?.[addon.code]?.quantity ?? 0),
        ]),
    ),
)
const desiredAddonQuantities = computed(() => {
    const quantities: Record<string, number> = {}

    for (const addon of props.addonCatalog ?? []) {
        const quantity = addonDraftQuantity(addon.code)

        if (quantity > 0) {
            quantities[addon.code] = quantity
        }
    }

    return quantities
})
const hasAddonChanges = computed(() => {
    for (const addon of props.addonCatalog ?? []) {
        if (addonDraftQuantity(addon.code) !== addonQuantity(addon.code)) {
            return true
        }
    }

    return false
})
const addonMonthlyTotal = computed(() => (props.addonCatalog ?? []).reduce((sum, addon) => (
    sum + addon.unit_price_cents * addonDraftQuantity(addon.code)
), 0))
const currentAddonMonthlyTotal = computed(() => (props.addonCatalog ?? []).reduce((sum, addon) => (
    sum + addon.unit_price_cents * addonQuantity(addon.code)
), 0))
const addonDueNow = computed(() => (props.addonCatalog ?? []).reduce((sum, addon) => {
    if (isPlanCheckout.value) {
        return sum + addon.unit_price_cents * addonDraftQuantity(addon.code)
    }

    const additionalQuantity = addonDraftQuantity(addon.code) - addonQuantity(addon.code)

    return additionalQuantity > 0 ? sum + additionalQuantity * addon.unit_price_cents : sum
}, 0))
const checkoutTotal = computed(() => (checkoutPlan.value?.price_cents ?? 0) + addonMonthlyTotal.value)

function money(plan: Plan): string {
    if (plan.price_cents === 0) {
        return '0 ₽'
    }

    return `${new Intl.NumberFormat('ru-RU').format(plan.price_cents / 100)} ₽`
}

function addonMoney(addon: AddonCatalogItem): string {
    return `+${new Intl.NumberFormat('ru-RU').format(addon.unit_price_cents / 100)} ₽/мес`
}

function addonQuantity(code: string): number {
    return props.currentAddons?.[code]?.quantity ?? 0
}

function addonDraftQuantity(code: string): number {
    return addonDrafts.value[code] ?? 0
}

function changeAddonQuantity(code: string, delta: number): void {
    addonDrafts.value[code] = Math.max(0, Math.min(1000, addonDraftQuantity(code) + delta))
}

function resetAddonDrafts(): void {
    for (const item of props.addonCatalog ?? []) {
        addonDrafts.value[item.code] = isPlanCheckout.value ? 0 : addonQuantity(item.code)
    }
}

function submitAddonChanges(): void {
    if (!isPlanCheckout.value && !hasAddonChanges.value) {
        return
    }

    router.post('/billing/checkout', {
        plan_code: checkoutPlan.value?.code ?? currentPlanCode.value,
        manage_addons: !isPlanCheckout.value,
        addons: desiredAddonQuantities.value,
    })
}

function selectPlan(plan: Plan): void {
    checkoutPlanCode.value = plan.code
    resetAddonDrafts()

    requestAnimationFrame(() => {
        document.getElementById('billing-addons')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
}

function addonStateLabel(addon: AddonCatalogItem): string {
    const current = addonQuantity(addon.code)
    const next = addonDraftQuantity(addon.code)

    if (next > current) {
        return `+${next - current} после оплаты`
    }

    if (next < current) {
        return next === 0 ? 'будет отключено' : `останется ${next}`
    }

    return current > 0 ? `подключено ${current}` : 'не подключено'
}

function formatCents(cents: number): string {
    if (cents === 0) {
        return '0 ₽'
    }

    return `${new Intl.NumberFormat('ru-RU').format(cents / 100)} ₽`
}

function planLimit(plan: Plan, key: string, valueKey: string, fallback: string | number = 'Без лимита'): string | number {
    return plan.limits[key]?.[valueKey] ?? fallback
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

function planFeatures(plan: Plan): string[] {
    const sites = plan.limits.max_sites?.limit
    const historyDays = plan.limits.history_retention_days?.days ?? 0
    const intervalMinutes = Math.max(1, Math.round((plan.limits.minimum_check_interval_seconds?.seconds ?? 300) / 60))

    return [
        sites === null || sites === undefined ? 'Сайты без лимита' : `До ${sites} сайтов`,
        'Базовые проверки включены',
        `История проверок ${historyDays} дней`,
        `Интервал от ${intervalMinutes} минут`,
        formatChannels(plan),
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
    return plan.code === 'solo'
}

function comparisonValue(plan: Plan, key: string): string {
    const limits = plan.limits

    if (key === 'price') {
        return plan.price_cents === 0 ? 'Бесплатно' : `${money(plan)} / мес`
    }

    if (key === 'max_sites') {
        return String(planLimit(plan, 'max_sites', 'limit'))
    }

    if (key === 'checks') {
        return 'HTTP, SSL, домен, DNS, Robots.txt'
    }

    if (key === 'types') {
        return limits.allowed_monitor_types?.types?.includes('*')
            ? 'Все типы'
            : (limits.allowed_monitor_types?.types ?? []).join(', ').toUpperCase()
    }

    if (key === 'history') {
        return `${planLimit(plan, 'history_retention_days', 'days', 0)} дней`
    }

    if (key === 'projects') {
        return limits.can_create_projects?.enabled ? 'Доступны' : 'Недоступны'
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
    { key: 'price', label: 'Стоимость' },
    { key: 'max_sites', label: 'Сайты' },
    { key: 'checks', label: 'Базовые проверки' },
    { key: 'types', label: 'Типы проверок' },
    { key: 'history', label: 'История' },
    { key: 'projects', label: 'Проекты' },
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
            <div class="overflow-hidden rounded-[32px] border border-[#CFE1D7] bg-[#173B2A] shadow-[0_18px_60px_rgba(23,59,42,0.14)]">
                <div class="relative grid gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[minmax(0,1.25fr)_minmax(340px,0.75fr)] lg:px-10 lg:py-10">
                    <div class="pointer-events-none absolute -right-20 -top-28 h-72 w-72 rounded-full bg-[#2FA568]/20 blur-3xl" />
                    <div class="pointer-events-none absolute -bottom-24 left-1/3 h-60 w-60 rounded-full bg-[#DDF6E8]/10 blur-3xl" />

                    <div class="relative">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-2 text-xs font-semibold text-[#CFF2DC]">
                            <Sparkles class="h-4 w-4" :stroke-width="2" />
                            Тарифы Montry
                        </div>
                        <h1 class="mt-5 max-w-3xl text-3xl font-semibold leading-tight text-white sm:text-4xl lg:text-[44px]">
                            Выберите тариф под количество ваших сайтов
                        </h1>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-[#C8D9CF] sm:text-lg">
                            Начните бесплатно и увеличивайте лимиты по мере роста. Базовый мониторинг уже включён, дополнительные проверки подключаются отдельно.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium text-[#E5F4EB]">
                            <span class="inline-flex items-center gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#65D493]" :stroke-width="2" />
                                Без скрытых платежей
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <CheckCircle2 class="h-5 w-5 text-[#65D493]" :stroke-width="2" />
                                Смена тарифа в любой момент
                            </span>
                        </div>
                    </div>

                    <div class="relative rounded-[28px] border border-white/15 bg-white/10 p-5 backdrop-blur sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#9FC6AE]">Текущий тариф</p>
                                <h2 class="mt-2 text-3xl font-semibold text-white">{{ currentPlan?.name ?? 'Free' }}</h2>
                            </div>
                            <span class="rounded-full bg-[#65D493]/15 px-3 py-1.5 text-xs font-semibold text-[#8DE4B0]">Активен</span>
                        </div>

                        <div class="mt-6 space-y-5">
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-[#C8D9CF]">Сайты</span>
                                    <span class="font-semibold text-white">{{ usage.sites }} / {{ limitLabel(effectiveSiteLimit) }}</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10">
                                    <div class="h-full rounded-full bg-[#65D493] transition-all" :style="{ width: `${siteUsagePercent}%` }" />
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-[#C8D9CF]">Активные проверки</span>
                                    <span class="font-semibold text-white">{{ usage.active_monitors }} / {{ usage.monitors }}</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10">
                                    <div class="h-full rounded-full bg-[#A7EAC2] transition-all" :style="{ width: `${activeMonitorPercent}%` }" />
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-white/10 p-3.5">
                                <History class="h-5 w-5 text-[#8DE4B0]" :stroke-width="2" />
                                <p class="mt-2 text-lg font-semibold text-white">{{ currentHistoryDays }} дней</p>
                                <p class="mt-1 text-xs text-[#B7CCBF]">История</p>
                            </div>
                            <div class="rounded-2xl bg-white/10 p-3.5">
                                <Layers3 class="h-5 w-5 text-[#8DE4B0]" :stroke-width="2" />
                                <p class="mt-2 text-lg font-semibold text-white">{{ usage.monitors }}</p>
                                <p class="mt-1 text-xs text-[#B7CCBF]">Всего проверок</p>
                            </div>
                        </div>

                        <p v-if="currentSubscription?.ends_at" class="mt-5 flex items-center gap-2 text-sm text-[#C8D9CF]">
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
                    Во всех тарифах доступны основные типы проверок. Отличаются лимиты сайтов, глубина истории и частота мониторинга.
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
                        plan.code === checkoutPlanCode ? 'border-[#173B2A] ring-2 ring-[#173B2A]/10' : '',
                        isFeatured(plan) && plan.code !== currentPlanCode ? 'ring-1 ring-[#BEE7CE]' : '',
                    ]"
                >
                    <div v-if="isFeatured(plan)" class="absolute right-5 top-5 rounded-full bg-[#E9F8EF] px-3 py-1.5 text-xs font-semibold text-[#178A50]">
                        Популярный
                    </div>

                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-2xl border"
                            :class="plan.code === currentPlanCode ? 'border-[#BEE7CE] bg-[#DDF6E8] text-[#178A50]' : 'border-[#DDEBE3] bg-[#F3F8F5] text-[#52645A]'"
                        >
                            <Globe2 v-if="plan.code === 'free'" class="h-5 w-5" :stroke-width="2" />
                            <Zap v-else-if="isFeatured(plan)" class="h-5 w-5" :stroke-width="2" />
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
                            class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[#173B2A] px-5 text-sm font-semibold text-white transition hover:bg-[#214E38]"
                            @click="selectPlan(plan)"
                        >
                            {{ plan.code === checkoutPlanCode ? `${plan.name} выбран` : `Выбрать ${plan.name}` }}
                            <ArrowRight class="h-4 w-4" :stroke-width="2.25" />
                        </button>
                    </div>
                </article>
            </div>

            <div id="billing-addons" v-if="addonCatalog?.length" class="mt-10 scroll-mt-24 rounded-[30px] border border-[#DDEBE3] bg-white p-6 sm:p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 text-sm font-semibold text-[#1E9B5D]">
                            <Plus class="h-4 w-4" :stroke-width="2.25" />
                            Дополнительные возможности
                        </div>
                        <h2 class="mt-2 text-2xl font-semibold text-[#26332D] sm:text-3xl">
                            {{ isPlanCheckout ? `Настройте тариф ${checkoutPlan?.name}` : 'Расширьте тариф точечно' }}
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6A7A70]">
                            {{ isPlanCheckout
                                ? 'Добавьте нужные платные типы мониторинга. Тариф и выбранные дополнения попадут в один заказ.'
                                : 'Подключайте только те проверки и пакеты, которые нужны сейчас. Уменьшение лимитов применяется без оплаты, а увеличение откроет счет только на добавленное количество.'
                            }}
                        </p>
                    </div>
                    <div class="grid gap-2 rounded-2xl border border-[#DDEBE3] bg-[#F8FCFA] p-4 text-sm sm:min-w-[290px]">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-[#6A7A70]">{{ isPlanCheckout ? `Тариф ${checkoutPlan?.name}` : 'Сейчас' }}</span>
                            <span class="font-semibold text-[#26332D]">{{ formatCents(isPlanCheckout ? (checkoutPlan?.price_cents ?? 0) : currentAddonMonthlyTotal) }} / мес</span>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-[#6A7A70]">{{ isPlanCheckout ? 'Дополнения' : 'После изменений' }}</span>
                            <span class="font-semibold text-[#26332D]">{{ formatCents(addonMonthlyTotal) }} / мес</span>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-t border-[#E5EFE9] pt-2">
                            <span class="text-[#6A7A70]">К оплате сейчас</span>
                            <span class="font-semibold text-[#178A50]">{{ formatCents(isPlanCheckout ? checkoutTotal : addonDueNow) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="addon in addonCatalog"
                        :key="addon.code"
                        class="flex min-h-full flex-col rounded-[22px] border border-[#DDEBE3] bg-[#F8FCFA] p-5 transition hover:border-[#B8D8C5] hover:bg-white"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-[#E9F8EF] text-[#1E9B5D]">
                                <Zap class="h-5 w-5" :stroke-width="2" />
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="!isPlanCheckout && addonDraftQuantity(addon.code) < addonQuantity(addon.code) ? 'bg-[#FFF0CF] text-[#A76500]' : 'bg-[#DDF6E8] text-[#178A50]'"
                            >
                                {{ isPlanCheckout ? (addonDraftQuantity(addon.code) > 0 ? `выбрано ${addonDraftQuantity(addon.code)}` : 'не выбрано') : addonStateLabel(addon) }}
                            </span>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-[#26332D]">{{ addon.name }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-6 text-[#6A7A70]">{{ addon.description }}</p>
                        <p class="mt-5 text-lg font-semibold text-[#173B2A]">{{ addonMoney(addon) }}</p>
                        <div class="mt-4 flex h-11 items-center rounded-2xl border border-[#B8D8C5] bg-white">
                            <button
                                type="button"
                                class="grid h-full w-11 place-items-center rounded-l-2xl text-[#52645A] transition hover:bg-[#F3F8F5] hover:text-[#173B2A] disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="addonDraftQuantity(addon.code) === 0"
                                :aria-label="`Уменьшить ${addon.name}`"
                                @click="changeAddonQuantity(addon.code, -1)"
                            >
                                <Minus class="h-4 w-4" :stroke-width="2.25" />
                            </button>
                            <div class="flex-1 text-center text-sm font-semibold text-[#26332D]">
                                {{ addonDraftQuantity(addon.code) }}
                            </div>
                            <button
                                type="button"
                                class="grid h-full w-11 place-items-center rounded-r-2xl text-[#178A50] transition hover:bg-[#E9F8EF]"
                                :aria-label="`Увеличить ${addon.name}`"
                                @click="changeAddonQuantity(addon.code, 1)"
                            >
                                <Plus class="h-4 w-4" :stroke-width="2.25" />
                            </button>
                        </div>
                    </article>
                </div>

                <div class="mt-6 flex flex-col gap-3 rounded-[22px] border border-[#DDEBE3] bg-[#F8FCFA] p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <p class="text-sm leading-6 text-[#6A7A70]">
                        При отключении лимита сверхлимитные сайты или платные проверки будут приостановлены автоматически. Вернуть их можно после повторного увеличения лимита.
                    </p>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-2xl border border-[#DDEBE3] px-4 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A] disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="isPlanCheckout ? addonMonthlyTotal === 0 : !hasAddonChanges"
                            @click="resetAddonDrafts"
                        >
                            Сбросить
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#173B2A] px-5 text-sm font-semibold text-white transition hover:bg-[#214E38] disabled:cursor-not-allowed disabled:bg-[#AAB8B0]"
                            :disabled="!isPlanCheckout && !hasAddonChanges"
                            @click="submitAddonChanges"
                        >
                            {{ isPlanCheckout || addonDueNow > 0 ? 'Перейти к оплате' : 'Сохранить изменения' }}
                        </button>
                    </div>
                </div>
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
                                <th v-for="plan in plans" :key="plan.code" class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        {{ plan.name }}
                                        <span v-if="plan.code === currentPlanCode" class="rounded-full bg-[#DDF6E8] px-2 py-1 text-[10px] normal-case tracking-normal text-[#178A50]">текущий</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5EFE9]">
                            <tr v-for="row in comparisonRows" :key="row.key" class="transition hover:bg-[#FAFCFB]">
                                <td class="px-6 py-4 font-semibold text-[#26332D] sm:px-8">{{ row.label }}</td>
                                <td v-for="plan in plans" :key="`${row.key}-${plan.code}`" class="px-6 py-4 leading-6 text-[#52645A]">
                                    {{ comparisonValue(plan, row.key) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 rounded-[24px] border border-[#DDEBE3] bg-[#F6FBF8] p-5 text-sm leading-6 text-[#6A7A70] sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>
                    Оплачивая тариф или дополнение, вы принимаете условия
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
