<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted } from 'vue'
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
let autoConfirmTimer: ReturnType<typeof window.setTimeout> | null = null

onMounted(() => {
    autoConfirmTimer = window.setTimeout(() => {
        router.post(`/billing/payments/${props.payment.id}/confirm`, {}, { replace: true })
        autoConfirmTimer = null
    }, 1000)
})

onBeforeUnmount(() => {
    if (autoConfirmTimer !== null) {
        window.clearTimeout(autoConfirmTimer)
        autoConfirmTimer = null
    }
})
</script>

<template>
    <Head title="Оплата" />

    <DashboardLayout
        :organization="organization"
        active-item="billing"
        title="Оплата"
        subtitle="Фиктивная страница оплаты для MVP"
    >
        <section class="mx-auto max-w-3xl px-5 py-8 sm:px-8">
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6">
                <p class="text-sm font-extrabold text-[#12B3A8]">Банк Montry</p>
                <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">{{ payment.plan?.name }}</h2>
                <p class="mt-2 text-[#667085]">Платеж обрабатывается. После подтверждения тариф включится автоматически.</p>
                <p class="mt-6 text-4xl font-extrabold text-[#111827]">{{ amount }} ₽</p>
                <div class="mt-8 h-2 overflow-hidden rounded-full bg-[#E5E7EB]">
                    <div class="h-full w-2/3 animate-pulse rounded-full bg-[#0F6BFF]" />
                </div>
            </div>
        </section>
    </DashboardLayout>
</template>
