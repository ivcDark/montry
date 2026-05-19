<script setup lang="ts">
import MarketingHeader from '@/Components/MarketingHeader.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps<{
    email: string
    resendCooldownSeconds: number
}>()

const form = useForm({
    code: '',
})

const resendForm = useForm({})
const secondsLeft = ref(Math.max(0, props.resendCooldownSeconds))
const canResend = computed(() => secondsLeft.value === 0 && !resendForm.processing)

let resendTimer: ReturnType<typeof setInterval> | null = null

function stopResendTimer() {
    if (!resendTimer) {
        return
    }

    clearInterval(resendTimer)
    resendTimer = null
}

function startResendTimer() {
    stopResendTimer()

    if (secondsLeft.value === 0) {
        return
    }

    resendTimer = setInterval(() => {
        secondsLeft.value = Math.max(0, secondsLeft.value - 1)

        if (secondsLeft.value === 0) {
            stopResendTimer()
        }
    }, 1000)
}

onMounted(startResendTimer)
onUnmounted(stopResendTimer)

function submit() {
    form.post('/register/verify-code')
}

function resend() {
    if (!canResend.value) {
        return
    }

    resendForm.post('/register/verify-code/resend', {
        onSuccess: () => {
            secondsLeft.value = Math.max(0, props.resendCooldownSeconds)
            startResendTimer()
        },
    })
}
</script>

<template>
    <Head title="Подтверждение email" />

    <main class="min-h-screen bg-[#F6F8FB] font-sans text-[#111827]">
        <MarketingHeader context-label="Подтверждение" />

        <section class="mx-auto grid min-h-[calc(100vh-80px)] max-w-7xl gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_460px] lg:items-center lg:py-16">
            <div class="hidden lg:block">
                <p class="text-sm font-extrabold text-[#12B3A8]">Email verification</p>
                <h1 class="mt-4 max-w-2xl text-5xl font-extrabold leading-tight tracking-normal text-[#111827]">
                    Подтвердите email, чтобы завершить регистрацию
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-8 text-[#667085]">
                    После подтверждения Montry создаст аккаунт, организацию и стартовый проект для мониторинга.
                </p>
            </div>

            <section class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-[0_24px_64px_rgba(15,23,42,0.12)] sm:p-8">
                <div>
                    <Link href="/" class="inline-flex items-center gap-3" aria-label="Montry">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0F6BFF] text-lg font-extrabold text-white">M</span>
                        <span class="text-2xl font-extrabold tracking-normal text-[#111827]">Montry</span>
                    </Link>

                    <h1 class="mt-8 text-3xl font-extrabold tracking-normal text-[#111827]">
                        Введите код
                    </h1>

                    <p class="mt-3 leading-7 text-[#667085]">
                        Мы отправили 5-значный код на {{ email }}. Код действует 10 минут.
                    </p>
                </div>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="code" class="mb-2 block text-sm font-bold text-[#111827]">
                            Код подтверждения
                        </label>

                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="5"
                            required
                            :aria-invalid="Boolean(form.errors.code)"
                            aria-describedby="code-error"
                            class="h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-center text-lg font-extrabold tracking-normal text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                            placeholder="12345"
                        >

                        <p id="code-error" v-if="form.errors.code" class="mt-2 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-bold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Проверяем...</span>
                        <span v-else>Подтвердить email</span>
                    </button>
                </form>

                <form class="mt-4" @submit.prevent="resend">
                    <button
                        type="submit"
                        :disabled="!canResend"
                        class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-bold text-[#111827] transition enabled:hover:border-[#0F6BFF] enabled:hover:text-[#0F6BFF] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="resendForm.processing">Отправляем...</span>
                        <span v-else>Отправить код повторно</span>
                    </button>
                </form>

                <p v-if="secondsLeft > 0" class="mt-4 text-center text-xs font-semibold text-[#667085]">
                    Повторная отправка доступна через {{ secondsLeft }} секунд.
                </p>

                <p v-else class="mt-4 text-center text-xs font-semibold text-[#667085]">
                    Можно отправить новый код.
                </p>
            </section>
        </section>
    </main>
</template>
