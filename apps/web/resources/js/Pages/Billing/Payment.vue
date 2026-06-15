<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    CircleX,
    Clock3,
    CreditCard,
    ExternalLink,
    FileText,
    LockKeyhole,
    ReceiptText,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Plan = {
    name: string
    description: string | null
    price_cents?: number
    limits?: Record<string, any>
}

type PaymentItem = {
    code: string
    name: string
    quantity: number
    unit_price_cents: number
    amount_cents: number
}

type RobokassaPayload = {
    is_configured: boolean
    is_test: boolean
    allow_test_confirmation: boolean
    action: string | null
    method: string
    fields: Record<string, string | number | null>
    result_url: string
    success_url: string
    fail_url: string
}

type YooKassaPayload = {
    is_configured: boolean
    is_test: boolean
    checkout_url: string
    webhook_url: string
}

const props = defineProps<{
    organization: { id: string | number; name: string }
    payment: {
        id: number
        status: string
        amount_cents: number
        currency: string
        provider: string | null
        failed_at: string | null
        failure_code: string | null
        failure_reason: string | null
        plan: Plan | null
        items: PaymentItem[]
        robokassa: RobokassaPayload
        yookassa: YooKassaPayload
    }
}>()

function formatMoney(cents: number): string {
    return new Intl.NumberFormat('ru-RU').format(cents / 100)
}

const amount = formatMoney(props.payment.amount_cents)
const robokassaFields = computed(() => Object.entries(props.payment.robokassa.fields ?? {}))
const isPaid = computed(() => props.payment.status === 'paid')
const isFailed = computed(() => props.payment.status === 'failed')
const isPending = computed(() => !isPaid.value && !isFailed.value)
const activeProvider = computed(() => props.payment.provider || 'robokassa')
const canOpenRobokassa = computed(() => activeProvider.value === 'robokassa' && props.payment.robokassa.is_configured && props.payment.robokassa.action !== null && !isPaid.value)
const canOpenYooKassa = computed(() => activeProvider.value === 'yookassa' && props.payment.yookassa.is_configured && !isPaid.value)
const providerLabel = computed(() => {
    if (activeProvider.value === 'robokassa') return 'Robokassa'
    if (activeProvider.value === 'yookassa') return 'ЮKassa'
    return activeProvider.value
})
const providerIsTest = computed(() => activeProvider.value === 'yookassa' ? props.payment.yookassa.is_test : props.payment.robokassa.is_test)
const providerIsConfigured = computed(() => activeProvider.value === 'yookassa' ? props.payment.yookassa.is_configured : props.payment.robokassa.is_configured)
const csrfToken = computed(() => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '')
const statusLabel = computed(() => {
    if (isPaid.value) return 'Оплачен'
    if (isFailed.value) return 'Ошибка оплаты'
    return 'Ожидает оплаты'
})

const statusClasses = computed(() => {
    if (isPaid.value) return 'border-[#BEE7CE] bg-[#E9F8EF] text-[#178A50]'
    if (isFailed.value) return 'border-[#F4CACA] bg-[#FFF1F1] text-[#C52A31]'
    return 'border-[#F0D7A9] bg-[#FFF8E9] text-[#B36D00]'
})

function planFeature(label: string, fallback: string): string {
    const limits = props.payment.plan?.limits ?? {}

    if (label === 'sites') {
        return `До ${limits.max_sites?.limit ?? fallback} сайтов`
    }

    if (label === 'history') {
        return `История ${limits.history_retention_days?.days ?? fallback} дней`
    }

    if (label === 'interval') {
        const seconds = limits.minimum_check_interval_seconds?.seconds
        return seconds ? `Проверки от ${Math.max(1, Math.round(seconds / 60))} минут` : fallback
    }

    return fallback
}
</script>

<template>
    <Head title="Подтверждение оплаты" />

    <DashboardLayout
        :organization="organization"
        active-item="billing"
        title="Подтверждение оплаты"
        subtitle="Проверка тарифа перед переходом к оплате"
    >
        <section class="mx-auto max-w-6xl px-5 py-8 sm:px-8 lg:py-10">
            <Link href="/billing" class="inline-flex items-center gap-2 text-sm font-medium text-[#6A7A70] transition hover:text-[#173B2A]">
                <ArrowLeft class="h-4 w-4" :stroke-width="2.25" />
                Вернуться к тарифам
            </Link>

            <div class="mt-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-[#E9F8EF] px-3.5 py-2 text-xs font-semibold text-[#178A50]">
                        <ReceiptText class="h-4 w-4" :stroke-width="2" />
                        Платёж №{{ payment.id }}
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold text-[#26332D] sm:text-4xl">Подтвердите выбранный тариф</h1>
                    <p class="mt-3 max-w-2xl text-base leading-7 text-[#6A7A70]">
                        Проверьте состав заказа. После нажатия кнопки вы перейдёте на защищённую страницу платёжного сервиса.
                    </p>
                </div>

                <div class="inline-flex w-fit items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold" :class="statusClasses">
                    <CheckCircle2 v-if="isPaid" class="h-4 w-4" :stroke-width="2.25" />
                    <CircleX v-else-if="isFailed" class="h-4 w-4" :stroke-width="2.25" />
                    <Clock3 v-else class="h-4 w-4" :stroke-width="2.25" />
                    {{ statusLabel }}
                </div>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)]">
                <div class="space-y-6">
                    <div class="rounded-[30px] border border-[#DDEBE3] bg-white p-6 sm:p-8">
                        <div class="flex flex-col gap-5 border-b border-[#E5EFE9] pb-6 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex gap-4">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#E9F8EF] text-[#1E9B5D]">
                                    <Sparkles class="h-6 w-6" :stroke-width="2" />
                                </span>
                                <div>
                                    <p class="text-sm font-medium text-[#6A7A70]">Тариф</p>
                                    <h2 class="mt-1 text-2xl font-semibold text-[#26332D]">{{ payment.plan?.name ?? 'Montry' }}</h2>
                                    <p class="mt-2 max-w-xl text-sm leading-6 text-[#6A7A70]">
                                        {{ payment.plan?.description || 'Мониторинг сайтов, технических параметров и своевременные уведомления.' }}
                                    </p>
                                </div>
                            </div>
                            <div class="shrink-0 sm:text-right">
                                <p class="text-3xl font-semibold tracking-tight text-[#173B2A]">{{ amount }} ₽</p>
                                <p class="mt-1 text-xs font-medium text-[#7A8980]">за расчётный период</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-[#F6FBF8] p-4">
                                <Check class="h-5 w-5 text-[#1E9B5D]" :stroke-width="2.25" />
                                <p class="mt-3 text-sm font-semibold text-[#26332D]">{{ planFeature('sites', 'нескольких') }}</p>
                            </div>
                            <div class="rounded-2xl bg-[#F6FBF8] p-4">
                                <Check class="h-5 w-5 text-[#1E9B5D]" :stroke-width="2.25" />
                                <p class="mt-3 text-sm font-semibold text-[#26332D]">{{ planFeature('history', '30') }}</p>
                            </div>
                            <div class="rounded-2xl bg-[#F6FBF8] p-4">
                                <Check class="h-5 w-5 text-[#1E9B5D]" :stroke-width="2.25" />
                                <p class="mt-3 text-sm font-semibold text-[#26332D]">{{ planFeature('interval', 'Регулярные проверки') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[26px] border border-[#DDEBE3] bg-[#F8FCFA] p-6">
                        <h2 class="flex items-center gap-2 text-lg font-semibold text-[#26332D]">
                            <ShieldCheck class="h-5 w-5 text-[#1E9B5D]" :stroke-width="2" />
                            Как пройдёт оплата
                        </h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-3">
                            <div class="flex gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#173B2A] text-xs font-semibold text-white">1</span>
                                <p class="pt-1 text-sm leading-6 text-[#52645A]">Переход на защищённую страницу {{ providerLabel }}</p>
                            </div>
                            <div class="flex gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#E9F8EF] text-xs font-semibold text-[#178A50]">2</span>
                                <p class="pt-1 text-sm leading-6 text-[#52645A]">Подтверждение платежа удобным способом</p>
                            </div>
                            <div class="flex gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#E9F8EF] text-xs font-semibold text-[#178A50]">3</span>
                                <p class="pt-1 text-sm leading-6 text-[#52645A]">Автоматическая активация тарифа</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="providerIsTest" class="flex gap-3 rounded-[22px] border border-[#F0D7A9] bg-[#FFF8E9] p-5 text-sm leading-6 text-[#8A5A12]">
                        <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-[#C87800]" :stroke-width="2" />
                        <p><strong class="font-semibold">Тестовый режим.</strong> Реальные деньги не списываются. Оплата пройдёт на тестовой странице {{ providerLabel }}.</p>
                    </div>

                    <div v-if="!providerIsConfigured" class="flex gap-3 rounded-[22px] border border-[#F4CACA] bg-[#FFF1F1] p-5 text-sm leading-6 text-[#9D282E]">
                        <CircleX class="mt-0.5 h-5 w-5 shrink-0 text-[#D43B42]" :stroke-width="2" />
                        <p>
                            <strong class="font-semibold">Платёжный сервис не настроен.</strong>
                            Укажите параметры {{ providerLabel }} в Laravel env-файле.
                        </p>
                    </div>
                </div>

                <aside class="lg:sticky lg:top-28 lg:self-start">
                    <div class="overflow-hidden rounded-[30px] border border-[#CFE1D7] bg-white shadow-[0_20px_60px_rgba(23,59,42,0.11)]">
                        <div class="bg-[#173B2A] px-6 py-6 text-white">
                            <p class="text-sm font-medium text-[#BFD2C6]">Итого к оплате</p>
                            <div class="mt-2 flex items-end gap-2">
                                <p class="text-4xl font-semibold tracking-tight">{{ amount }} ₽</p>
                            </div>
                            <p class="mt-3 text-sm text-[#C8D9CF]">Тариф {{ payment.plan?.name ?? 'Montry' }}</p>
                        </div>

                        <div class="p-6">
                            <div class="space-y-3 border-b border-[#E5EFE9] pb-5 text-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-[#26332D]">Тариф {{ payment.plan?.name ?? 'Montry' }}</p>
                                        <p class="mt-1 text-xs text-[#7A8980]">Основной тарифный план</p>
                                    </div>
                                    <span class="shrink-0 font-semibold text-[#26332D]">{{ formatMoney(payment.plan?.price_cents ?? payment.amount_cents) }} ₽</span>
                                </div>
                                <div v-for="item in payment.items" :key="item.code" class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-[#52645A]">{{ item.name }}</p>
                                        <p class="mt-1 text-xs text-[#8A9A91]">{{ item.quantity }} × {{ formatMoney(item.unit_price_cents) }} ₽</p>
                                    </div>
                                    <span class="shrink-0 font-semibold text-[#26332D]">{{ formatMoney(item.amount_cents) }} ₽</span>
                                </div>
                            </div>

                            <div v-if="isPaid" class="mt-5 rounded-2xl border border-[#BEE7CE] bg-[#E9F8EF] p-4 text-sm leading-6 text-[#177A49]">
                                <div class="flex items-center gap-2 font-semibold">
                                    <CheckCircle2 class="h-5 w-5" :stroke-width="2.25" />
                                    Платёж подтверждён
                                </div>
                                <p class="mt-2">Тариф уже активирован и доступен для организации.</p>
                            </div>

                            <div v-else-if="isFailed" class="mt-5 rounded-2xl border border-[#F4CACA] bg-[#FFF1F1] p-4 text-sm leading-6 text-[#9D282E]">
                                <div class="flex items-center gap-2 font-semibold">
                                    <CircleX class="h-5 w-5" :stroke-width="2.25" />
                                    Оплата не завершена
                                </div>
                                <p class="mt-2">
                                    {{ payment.failure_reason || 'Попробуйте повторно выбрать тариф и создать новый платёж.' }}
                                </p>
                            </div>

                            <template v-else>
                                <form
                                    v-if="canOpenRobokassa"
                                    class="mt-5"
                                    :action="payment.robokassa.action ?? ''"
                                    :method="payment.robokassa.method"
                                >
                                    <input
                                        v-for="[name, value] in robokassaFields"
                                        :key="name"
                                        type="hidden"
                                        :name="name"
                                        :value="value ?? ''"
                                    >
                                    <button
                                        type="submit"
                                        class="flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl bg-[#2FA568] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[#278F59]"
                                    >
                                        Перейти к оплате
                                        <ExternalLink class="h-4 w-4" :stroke-width="2.25" />
                                    </button>
                                </form>

                                <form
                                    v-else-if="canOpenYooKassa"
                                    class="mt-5"
                                    :action="payment.yookassa.checkout_url"
                                    method="POST"
                                >
                                    <input type="hidden" name="_token" :value="csrfToken">
                                    <button
                                        type="submit"
                                        class="flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl bg-[#2FA568] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[#278F59]"
                                    >
                                        Перейти к оплате
                                        <ExternalLink class="h-4 w-4" :stroke-width="2.25" />
                                    </button>
                                </form>

                                <div
                                    v-else
                                    class="mt-5 flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl bg-[#E8EEEA] px-6 py-3.5 text-sm font-semibold text-[#8A9A91]"
                                >
                                    <CreditCard class="h-4 w-4" :stroke-width="2" />
                                    Оплата недоступна
                                </div>
                            </template>

                            <Link
                                v-if="isPaid"
                                href="/sites"
                                class="mt-4 flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[#173B2A] px-5 text-sm font-semibold text-white transition hover:bg-[#214E38]"
                            >
                                Перейти к сайтам
                                <ArrowRight class="h-4 w-4" :stroke-width="2.25" />
                            </Link>

                            <Link
                                v-else-if="isFailed"
                                href="/billing"
                                class="mt-4 flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[#173B2A] px-5 text-sm font-semibold text-white transition hover:bg-[#214E38]"
                            >
                                Выбрать тариф снова
                                <ArrowRight class="h-4 w-4" :stroke-width="2.25" />
                            </Link>

                            <div class="mt-5 space-y-3 border-t border-[#E5EFE9] pt-5 text-sm text-[#6A7A70]">
                                <p class="flex items-center gap-2">
                                    <LockKeyhole class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Защищённое соединение
                                </p>
                                <p class="flex items-center gap-2">
                                    <CreditCard class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Данные карты не хранятся в Montry
                                </p>
                                <p class="flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Электронный чек после оплаты
                                </p>
                            </div>

                            <p v-if="isPending" class="mt-5 text-xs leading-5 text-[#7A8980]">
                                Нажимая кнопку, вы принимаете условия
                                <Link href="/offers" class="font-semibold text-[#178A50] transition hover:text-[#126F41]">публичной оферты</Link>.
                            </p>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </DashboardLayout>
</template>
