<script setup lang="ts">
import MarketingHeader from '@/Components/MarketingHeader.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
    intendedPlanCode?: string | null
}>()

const page = usePage<{ errors?: { yandex?: string } }>()

const registerHref = computed(() => props.intendedPlanCode ? `/register?plan=${props.intendedPlanCode}` : '/register')
const yandexHref = computed(() => props.intendedPlanCode ? `/auth/yandex/redirect?plan=${props.intendedPlanCode}` : '/auth/yandex/redirect')
const yandexError = computed(() => page.props.errors?.yandex)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Вход" />

    <main class="min-h-screen bg-[#F6F8FB] font-sans text-[#111827]">
        <MarketingHeader context-label="Авторизация" />

        <section class="mx-auto grid min-h-[calc(100vh-80px)] max-w-7xl gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_460px] lg:items-center lg:py-16">
            <div class="hidden lg:block">
                <p class="text-sm font-extrabold text-[#12B3A8]">Montry account</p>
                <h1 class="mt-4 max-w-2xl text-5xl font-extrabold leading-tight tracking-normal text-[#111827]">
                    Вернитесь к мониторингу сайтов, SSL и доменов
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-8 text-[#667085]">
                    Войдите в аккаунт, чтобы увидеть статусы проектов, последние проверки и открытые инциденты.
                </p>

                <div class="mt-10 grid max-w-xl gap-4">
                    <div class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-extrabold text-[#111827]">client-shop.ru</p>
                                <p class="mt-1 text-sm text-[#667085]">HTTP/HTTPS мониторинг</p>
                            </div>
                            <span class="rounded-full bg-[#FEECEC] px-3 py-1 text-xs font-extrabold text-[#EF4444]">Down</span>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-extrabold text-[#111827]">studio-site.ru</p>
                                <p class="mt-1 text-sm text-[#667085]">SSL-сертификат</p>
                            </div>
                            <span class="rounded-full bg-[#FFF7E8] px-3 py-1 text-xs font-extrabold text-[#F59E0B]">9 дней</span>
                        </div>
                    </div>
                </div>
            </div>

            <section class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-[0_24px_64px_rgba(15,23,42,0.12)] sm:p-8">
                <div>
                    <Link href="/" class="inline-flex items-center gap-3" aria-label="Montry">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0F6BFF] text-lg font-extrabold text-white">M</span>
                        <span class="text-2xl font-extrabold tracking-normal text-[#111827]">Montry</span>
                    </Link>

                    <h1 class="mt-8 text-3xl font-extrabold tracking-normal text-[#111827]">
                        Войти в аккаунт
                    </h1>

                    <p class="mt-3 leading-7 text-[#667085]">
                        Используйте email и пароль, указанные при регистрации.
                    </p>
                </div>

                <div class="mt-8">
                    <a
                        :href="yandexHref"
                        class="inline-flex h-12 w-full items-center justify-center gap-3 rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] shadow-[0_10px_28px_rgba(15,23,42,0.06)] transition hover:border-[#0F6BFF]/40 hover:bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/20 focus:ring-offset-2"
                    >
                        <span class="grid h-6 w-6 place-items-center rounded-lg bg-[#FC3F1D] text-sm font-black text-white">Я</span>
                        Войти через Яндекс
                    </a>

                    <p v-if="yandexError" class="mt-3 text-sm font-semibold text-[#EF4444]">
                        {{ yandexError }}
                    </p>
                </div>

                <div class="my-6 flex items-center gap-4 text-xs font-bold uppercase tracking-[0.18em] text-[#98A2B3]">
                    <span class="h-px flex-1 bg-[#E5E7EB]"></span>
                    или
                    <span class="h-px flex-1 bg-[#E5E7EB]"></span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="email" class="mb-2 block text-sm font-bold text-[#111827]">
                            Email
                        </label>

                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            autofocus
                            required
                            inputmode="email"
                            :aria-invalid="Boolean(form.errors.email)"
                            aria-describedby="email-error"
                            class="h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                            placeholder="you@example.ru"
                        >

                        <p id="email-error" v-if="form.errors.email" class="mt-2 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-bold text-[#111827]">
                            Пароль
                        </label>

                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            :aria-invalid="Boolean(form.errors.password)"
                            aria-describedby="password-error"
                            class="h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                            placeholder="Введите пароль"
                        >

                        <p id="password-error" v-if="form.errors.password" class="mt-2 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <label class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAFC] px-4 py-3 text-sm font-semibold text-[#667085]">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4 w-4 rounded border-[#E5E7EB] text-[#0F6BFF] focus:ring-[#0F6BFF]/20"
                        >

                        Запомнить меня
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-bold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Входим...</span>
                        <span v-else>Войти</span>
                    </button>
                </form>

                <p class="mt-6 text-sm text-[#667085]">
                    Нет аккаунта?
                    <Link :href="registerHref" class="font-bold text-[#0F6BFF] hover:text-[#0757D8]">
                        Создать аккаунт
                    </Link>
                </p>
            </section>
        </section>
    </main>
</template>
