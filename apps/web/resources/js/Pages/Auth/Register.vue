<script setup lang="ts">
import BrandMark from '@/Components/BrandMark.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
    intendedPlanCode?: string | null
    googleAuthEnabled?: boolean
}>()

const page = usePage<{ errors?: { yandex?: string; google?: string } }>()

const loginHref = computed(() => props.intendedPlanCode ? `/login?plan=${props.intendedPlanCode}` : '/login')
const yandexHref = computed(() => props.intendedPlanCode ? `/auth/yandex/redirect?plan=${props.intendedPlanCode}` : '/auth/yandex/redirect')
const googleHref = computed(() => props.intendedPlanCode ? `/auth/google/redirect?plan=${props.intendedPlanCode}` : '/auth/google/redirect')
const yandexError = computed(() => page.props.errors?.yandex)
const googleError = computed(() => page.props.errors?.google)

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    personal_data_agreement: false,
})

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Регистрация" />

    <main class="min-h-screen bg-[#F3F8F5] font-sans text-[#1F2B24]">
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-5 py-8 sm:px-8 lg:px-10">
            <header class="flex items-center justify-between">
                <Link href="/" class="inline-flex items-center gap-3" aria-label="Montry">
                    <BrandMark class="h-9 w-9" />
                    <span class="text-xl font-bold tracking-normal text-[#163B2A]">Montry</span>
                </Link>

                <Link href="/" class="text-sm font-medium text-[#52645A] transition hover:text-[#24A869]">
                    На главную
                </Link>
            </header>

            <section class="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[minmax(0,1fr)_500px] lg:gap-16 lg:py-12">
                <aside class="hidden min-h-[660px] rounded-[28px] border border-[#CBE6D5] bg-[radial-gradient(circle_at_10%_0%,#E2F8EB_0%,#F8FCFA_44%,#FFFFFF_100%)] p-12 shadow-[0_24px_80px_rgba(31,68,49,0.08)] lg:flex lg:flex-col lg:justify-between">
                    <div>
                        <h1 class="max-w-xl text-[52px] font-extrabold leading-[1.15] tracking-normal text-[#17231C]">
                            Запустите мониторинг за несколько минут
                        </h1>
                        <p class="mt-6 max-w-lg text-lg leading-8 text-[#6A7A70]">
                            Добавьте первый сайт, включите базовые проверки и получайте уведомления без сложной настройки.
                        </p>

                        <ul class="mt-10 grid gap-5 text-base font-semibold text-[#26332D]">
                            <li class="flex items-center gap-4"><span class="h-3 w-3 rounded-full bg-[#2FA568]"></span> 1 сайт бесплатно на старте</li>
                            <li class="flex items-center gap-4"><span class="h-3 w-3 rounded-full bg-[#2FA568]"></span> доступность, SSL, домен, DNS и robots.txt</li>
                            <li class="flex items-center gap-4"><span class="h-3 w-3 rounded-full bg-[#2FA568]"></span> уведомления на почту</li>
                            <li class="flex items-center gap-4"><span class="h-3 w-3 rounded-full bg-[#2FA568]"></span> история проверок и отчеты</li>
                        </ul>
                    </div>

                    <div class="w-full max-w-[380px] rounded-[24px] border border-[#DDEBE3] bg-white p-6 shadow-[0_22px_60px_rgba(31,68,49,0.10)]">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-[#16A85A]"></span>
                                <h2 class="text-xl font-extrabold text-[#26332D]">example.ru</h2>
                            </div>
                            <span class="rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-semibold text-[#1E9B5D]">Работает</span>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-[#DDEBE3] p-4">
                                <p class="text-2xl font-extrabold text-[#2FA568]">99.98%</p>
                                <p class="mt-1 text-xs text-[#6A7A70]">uptime</p>
                            </div>
                            <div class="rounded-2xl border border-[#DDEBE3] p-4">
                                <p class="text-2xl font-extrabold text-[#2FA568]">184 мс</p>
                                <p class="mt-1 text-xs text-[#6A7A70]">ответ</p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 text-sm text-[#6A7A70]">
                            <p class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-[#16A85A]"></span>SSL: действителен</p>
                            <p class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-[#16A85A]"></span>DNS: без ошибок</p>
                            <p class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-[#16A85A]"></span>Robots.txt: найден</p>
                        </div>
                    </div>
                </aside>

                <section class="mx-auto w-full max-w-[500px] rounded-[26px] border border-[#DDEBE3] bg-white p-6 shadow-[0_24px_80px_rgba(31,68,49,0.10)] sm:p-8">
                    <div>
                        <h1 class="text-3xl font-extrabold tracking-normal text-[#17231C]">
                            Создать аккаунт
                        </h1>
                        <p class="mt-4 text-base leading-7 text-[#6A7A70]">
                            Зарегистрируйтесь, чтобы добавить сайт и настроить первые проверки.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-3">
                        <a
                            :href="yandexHref"
                            class="inline-flex h-13 items-center justify-center gap-3 rounded-xl border border-[#F0C8BF] bg-white px-5 text-sm font-semibold text-[#26332D] transition hover:border-[#FF6A4D]/60 hover:bg-[#FFF7F4] focus:outline-none focus:ring-2 focus:ring-[#FF4B2F]/15 focus:ring-offset-2"
                        >
                            <span class="grid h-6 w-6 place-items-center rounded-full bg-[#FC3F1D] text-sm font-bold text-white">Я</span>
                            Зарегистрироваться через Яндекс
                        </a>

                        <a
                            v-if="googleAuthEnabled"
                            :href="googleHref"
                            class="inline-flex h-13 items-center justify-center gap-3 rounded-xl border border-[#DDEBE3] bg-white px-5 text-sm font-semibold text-[#26332D] transition hover:border-[#B8D0C2] hover:bg-[#FBFDFC] focus:outline-none focus:ring-2 focus:ring-[#24A869]/15 focus:ring-offset-2"
                        >
                            <span class="grid h-6 w-6 place-items-center rounded-full border border-[#DDEBE3] text-sm font-bold text-[#4285F4]">G</span>
                            Зарегистрироваться через Google
                        </a>

                        <p v-if="yandexError" class="text-sm font-semibold text-[#D94B4B]">{{ yandexError }}</p>
                        <p v-if="googleError" class="text-sm font-semibold text-[#D94B4B]">{{ googleError }}</p>
                    </div>

                    <div class="my-6 flex items-center gap-4 text-xs font-medium text-[#98A69E]">
                        <span class="h-px flex-1 bg-[#E2ECE6]"></span>
                        или зарегистрируйтесь по email
                        <span class="h-px flex-1 bg-[#E2ECE6]"></span>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="name" class="mb-2 block text-sm font-semibold text-[#26332D]">Имя</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                autofocus
                                required
                                :aria-invalid="Boolean(form.errors.name)"
                                aria-describedby="name-error"
                                class="h-12 w-full rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#98A69E] focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15"
                                placeholder="Иван"
                            >
                            <p id="name-error" v-if="form.errors.name" class="mt-2 text-sm font-semibold text-[#D94B4B]">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-[#26332D]">Email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                inputmode="email"
                                :aria-invalid="Boolean(form.errors.email)"
                                aria-describedby="register-email-error"
                                class="h-12 w-full rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#98A69E] focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15"
                                placeholder="you@example.com"
                            >
                            <p id="register-email-error" v-if="form.errors.email" class="mt-2 text-sm font-semibold text-[#D94B4B]">{{ form.errors.email }}</p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="password" class="mb-2 block text-sm font-semibold text-[#26332D]">Пароль</label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    :aria-invalid="Boolean(form.errors.password)"
                                    aria-describedby="register-password-error"
                                    class="h-12 w-full rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#98A69E] focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15"
                                    placeholder="Минимум 6 символов"
                                >
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-[#26332D]">Повтор пароля</label>
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    class="h-12 w-full rounded-xl border border-[#D4E3DA] bg-white px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#98A69E] focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/15"
                                    placeholder="Повторите пароль"
                                >
                            </div>
                        </div>

                        <p id="register-password-error" v-if="form.errors.password" class="text-sm font-semibold text-[#D94B4B]">{{ form.errors.password }}</p>

                        <label class="flex items-start gap-3 rounded-xl border border-[#DDEBE3] bg-[#F8FCFA] px-4 py-3 text-sm leading-6 text-[#6A7A70]">
                            <input
                                v-model="form.personal_data_agreement"
                                type="checkbox"
                                required
                                class="mt-1 h-4 w-4 rounded border-[#D4E3DA] text-[#24A869] focus:ring-[#24A869]/20"
                            >
                            <span>
                                Я соглашаюсь с
                                <a href="#" class="font-semibold text-[#1E9B5D] transition hover:text-[#167D49]">обработкой персональных данных</a>.
                            </span>
                        </label>
                        <p v-if="form.errors.personal_data_agreement" class="text-sm font-semibold text-[#D94B4B]">
                            {{ form.errors.personal_data_agreement }}
                        </p>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#2FA568] px-5 text-sm font-semibold text-white shadow-[0_12px_26px_rgba(47,165,104,0.20)] transition hover:bg-[#278C58] focus:outline-none focus:ring-2 focus:ring-[#24A869]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span v-if="form.processing">Создаем аккаунт...</span>
                            <span v-else>Создать аккаунт</span>
                        </button>
                    </form>

                    <p class="mt-5 text-sm text-[#6A7A70]">
                        Уже есть аккаунт?
                        <Link :href="loginHref" class="font-semibold text-[#1E9B5D] transition hover:text-[#167D49]">
                            Войти
                        </Link>
                    </p>
                </section>
            </section>
        </div>
    </main>
</template>
