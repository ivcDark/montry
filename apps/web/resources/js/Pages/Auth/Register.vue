<script setup lang="ts">
import MarketingHeader from '@/Components/MarketingHeader.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Регистрация" />

    <main class="min-h-screen bg-[#F6F8FB] font-sans text-[#111827]">
        <MarketingHeader context-label="Регистрация" />

        <section class="mx-auto grid min-h-[calc(100vh-80px)] max-w-7xl gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_500px] lg:items-center lg:py-16">
            <div class="hidden lg:block">
                <p class="text-sm font-extrabold text-[#12B3A8]">Быстрый старт</p>
                <h1 class="mt-4 max-w-2xl text-5xl font-extrabold leading-tight tracking-normal text-[#111827]">
                    Добавьте первый сайт и включите мониторинг за несколько минут
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-8 text-[#667085]">
                    Montry подойдет веб-студиям, фрилансерам и малому бизнесу, которым важно не пропускать падения сайтов, SSL и домены.
                </p>

                <div class="mt-10 grid max-w-xl gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">HTTP</p>
                        <p class="mt-3 text-3xl font-extrabold text-[#111827]">200</p>
                        <p class="mt-2 text-sm text-[#667085]">код ответа</p>
                    </div>
                    <div class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">SSL</p>
                        <p class="mt-3 text-3xl font-extrabold text-[#111827]">42</p>
                        <p class="mt-2 text-sm text-[#667085]">дня</p>
                    </div>
                    <div class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <p class="text-sm font-bold text-[#667085]">Domain</p>
                        <p class="mt-3 text-3xl font-extrabold text-[#111827]">128</p>
                        <p class="mt-2 text-sm text-[#667085]">дней</p>
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
                        Создать аккаунт
                    </h1>

                    <p class="mt-3 leading-7 text-[#667085]">
                        Зарегистрируйтесь, чтобы добавить сайт и настроить первые проверки.
                    </p>
                </div>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-bold text-[#111827]">
                            Имя
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            autofocus
                            required
                            :aria-invalid="Boolean(form.errors.name)"
                            aria-describedby="name-error"
                            class="h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                            placeholder="Иван"
                        >

                        <p id="name-error" v-if="form.errors.name" class="mt-2 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-bold text-[#111827]">
                            Email
                        </label>

                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            inputmode="email"
                            :aria-invalid="Boolean(form.errors.email)"
                            aria-describedby="register-email-error"
                            class="h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                            placeholder="you@example.ru"
                        >

                        <p id="register-email-error" v-if="form.errors.email" class="mt-2 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="password" class="mb-2 block text-sm font-bold text-[#111827]">
                                Пароль
                            </label>

                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                required
                                :aria-invalid="Boolean(form.errors.password)"
                                aria-describedby="register-password-error"
                                class="h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                placeholder="Минимум 6 символов"
                            >
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-bold text-[#111827]">
                                Повтор пароля
                            </label>

                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                placeholder="Повторите пароль"
                            >
                        </div>
                    </div>

                    <p id="register-password-error" v-if="form.errors.password" class="text-sm font-semibold text-[#EF4444]">
                        {{ form.errors.password }}
                    </p>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-bold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Создаем аккаунт...</span>
                        <span v-else>Создать аккаунт</span>
                    </button>
                </form>

                <p class="mt-6 text-sm text-[#667085]">
                    Уже есть аккаунт?
                    <Link href="/login" class="font-bold text-[#0F6BFF] hover:text-[#0757D8]">
                        Войти
                    </Link>
                </p>
            </section>
        </section>
    </main>
</template>
