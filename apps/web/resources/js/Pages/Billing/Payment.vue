<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Plan = {
    name: string
    description: string | null
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
        robokassa: RobokassaPayload
    }
}>()

const amount = new Intl.NumberFormat('ru-RU').format(props.payment.amount_cents / 100)
const robokassaFields = computed(() => Object.entries(props.payment.robokassa.fields ?? {}))
const isPaid = computed(() => props.payment.status === 'paid')
const isFailed = computed(() => props.payment.status === 'failed')
const canOpenRobokassa = computed(() => props.payment.robokassa.is_configured && props.payment.robokassa.action !== null && !isPaid.value)
</script>

<template>
    <Head title="Оплата тарифа" />

    <DashboardLayout
        :organization="organization"
        active-item="billing"
        title="Оплата тарифа"
        subtitle="Переход к оплате через Robokassa"
    >
        <section class="mx-auto max-w-3xl px-5 py-8 sm:px-8">
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6">
                <p class="text-sm font-extrabold text-[#12B3A8]">Платеж #{{ payment.id }}</p>
                <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">{{ payment.plan?.name }}</h2>
                <p class="mt-2 text-[#667085]">{{ payment.plan?.description }}</p>
                <p class="mt-6 text-4xl font-extrabold text-[#111827]">{{ amount }} ₽</p>

                <div v-if="payment.robokassa.is_test" class="mt-6 rounded-xl border border-[#FED7AA] bg-[#FFFBF1] p-4 text-sm font-semibold text-[#B45309]">
                    Включен тестовый режим Robokassa. Реальные деньги не списываются, оплата пройдет на тестовой странице Robokassa.
                </div>

                <div v-if="isPaid" class="mt-6 rounded-xl border border-[#BBF7D0] bg-[#F0FDF4] p-4 text-sm font-semibold text-[#15803D]">
                    Платеж уже подтвержден, тариф активирован.
                </div>

                <div v-else-if="isFailed" class="mt-6 rounded-xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm font-semibold text-[#B91C1C]">
                    Платеж завершился ошибкой<span v-if="payment.failure_reason">: {{ payment.failure_reason }}</span>.
                    Вернитесь к тарифам и создайте новый платеж.
                </div>

                <div v-if="!payment.robokassa.is_configured" class="mt-6 rounded-xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm font-semibold text-[#B91C1C]">
                    Robokassa не настроена. Укажите ROBOKASSA_MERCHANT_LOGIN и пароли Robokassa в Laravel env-файле. Для тестовой оплаты нужны ROBOKASSA_MODE=test, ROBOKASSA_TEST_PASSWORD1 и ROBOKASSA_TEST_PASSWORD2.
                </div>

                <p v-if="!isPaid" class="mt-6 text-sm leading-6 text-[#667085]">
                    Нажимая кнопку оплаты, вы принимаете условия
                    <Link href="/offers" class="font-extrabold text-[#0F6BFF] transition hover:text-[#0757D8]">публичной оферты</Link>.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <form
                        v-if="canOpenRobokassa"
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
                            class="flex h-12 items-center justify-center rounded-xl bg-[#0F6BFF] px-6 text-sm font-extrabold text-white transition hover:bg-[#0757D8]"
                        >
                            Перейти к оплате
                        </button>
                    </form>

                    <Link
                        href="/billing"
                        class="flex h-12 items-center justify-center rounded-xl border border-[#E5E7EB] px-6 text-sm font-extrabold text-[#111827] transition hover:border-[#CBD5E1]"
                    >
                        Вернуться к тарифам
                    </Link>
                </div>
            </div>
        </section>
    </DashboardLayout>
</template>
