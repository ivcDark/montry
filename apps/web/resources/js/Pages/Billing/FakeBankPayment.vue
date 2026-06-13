<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted } from 'vue'
import {
    CheckCircle2,
    CreditCard,
    LockKeyhole,
    ShieldCheck,
} from '@lucide/vue'
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
    <Head title="Подтверждение платежа" />

    <DashboardLayout
        :organization="organization"
        active-item="billing"
        title="Подтверждение платежа"
        subtitle="Тестовая обработка оплаты"
    >
        <section class="grid min-h-[calc(100vh-108px)] place-items-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-xl overflow-hidden rounded-[32px] border border-[#CFE1D7] bg-white shadow-[0_24px_80px_rgba(23,59,42,0.14)]">
                <div class="relative overflow-hidden bg-[#173B2A] px-6 py-8 text-center sm:px-10 sm:py-10">
                    <div class="pointer-events-none absolute -right-12 -top-16 h-52 w-52 rounded-full bg-[#2FA568]/25 blur-3xl" />
                    <div class="pointer-events-none absolute -bottom-20 -left-16 h-52 w-52 rounded-full bg-[#A7EAC2]/15 blur-3xl" />

                    <div class="relative mx-auto grid h-16 w-16 place-items-center rounded-[22px] border border-white/15 bg-white/10 text-[#8DE4B0]">
                        <CreditCard class="h-8 w-8" :stroke-width="1.8" />
                    </div>
                    <p class="relative mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-[#9FC6AE]">Тестовый банк Montry</p>
                    <h1 class="relative mt-2 text-3xl font-semibold text-white">Подтверждаем платёж</h1>
                    <p class="relative mt-3 text-sm leading-6 text-[#C8D9CF]">
                        Тариф активируется автоматически сразу после проверки тестового платежа.
                    </p>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-5 rounded-[22px] bg-[#F6FBF8] p-5">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.1em] text-[#7A8980]">Тариф</p>
                            <p class="mt-1 text-xl font-semibold text-[#26332D]">{{ payment.plan?.name ?? 'Montry' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium uppercase tracking-[0.1em] text-[#7A8980]">Сумма</p>
                            <p class="mt-1 text-2xl font-semibold text-[#173B2A]">{{ amount }} ₽</p>
                        </div>
                    </div>

                    <div class="mt-7">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-[#52645A]">Обработка платежа</span>
                            <span class="font-semibold text-[#1E9B5D]">Почти готово</span>
                        </div>
                        <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-[#E2ECE6]">
                            <div class="h-full w-2/3 animate-pulse rounded-full bg-[#2FA568]" />
                        </div>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-[#DDEBE3] p-3 text-center">
                            <LockKeyhole class="mx-auto h-5 w-5 text-[#1E9B5D]" :stroke-width="2" />
                            <p class="mt-2 text-xs font-medium text-[#52645A]">Защищено</p>
                        </div>
                        <div class="rounded-2xl border border-[#DDEBE3] p-3 text-center">
                            <ShieldCheck class="mx-auto h-5 w-5 text-[#1E9B5D]" :stroke-width="2" />
                            <p class="mt-2 text-xs font-medium text-[#52645A]">Проверяется</p>
                        </div>
                        <div class="rounded-2xl border border-[#DDEBE3] p-3 text-center">
                            <CheckCircle2 class="mx-auto h-5 w-5 text-[#1E9B5D]" :stroke-width="2" />
                            <p class="mt-2 text-xs font-medium text-[#52645A]">Автоактивация</p>
                        </div>
                    </div>

                    <p class="mt-6 text-center text-xs leading-5 text-[#8A9A91]">
                        Не закрывайте страницу. После подтверждения вы автоматически вернётесь в личный кабинет.
                    </p>
                </div>
            </div>
        </section>
    </DashboardLayout>
</template>
