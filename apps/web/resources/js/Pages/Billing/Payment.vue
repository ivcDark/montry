<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Plan = {
    name: string
    description: string | null
}

const props = defineProps<{
    organization: { id: string | number; name: string }
    payment: {
        id: number
        status: string
        amount_cents: number
        currency: string
        plan: Plan | null
    }
}>()

const amount = new Intl.NumberFormat('ru-RU').format(props.payment.amount_cents / 100)
</script>

<template>
    <Head title="Оплата тарифа" />

    <DashboardLayout
        :organization="organization"
        active-item="billing"
        title="Оплата тарифа"
        subtitle="Подтверждение платежа для MVP-сценария"
    >
        <section class="mx-auto max-w-3xl px-5 py-8 sm:px-8">
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6">
                <p class="text-sm font-extrabold text-[#12B3A8]">Платеж #{{ payment.id }}</p>
                <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">{{ payment.plan?.name }}</h2>
                <p class="mt-2 text-[#667085]">{{ payment.plan?.description }}</p>
                <p class="mt-6 text-4xl font-extrabold text-[#111827]">{{ amount }} ₽</p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Link
                        :href="`/billing/payments/${payment.id}/fake-bank`"
                        class="flex h-12 items-center justify-center rounded-xl bg-[#0F6BFF] px-6 text-sm font-extrabold text-white transition hover:bg-[#0757D8]"
                    >
                        Перейти к оплате
                    </Link>
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
