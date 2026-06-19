<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import MarketingHeader from '@/Components/MarketingHeader.vue'
import MarketingFooter from '@/Components/MarketingFooter.vue'

type User = {
    id: number | string
    name: string
    email: string
}

type PageProps = {
    auth: {
        user: User | null
    }
    flash?: {
        success?: string | null
    }
}

defineProps<{
    plans?: unknown[]
    articles?: {
        title: string
        slug: string
        excerpt: string
        published_at: string | null
    }[]
}>()

const page = usePage<PageProps>()
const user = page.props.auth.user

const ctaHref = user ? '/dashboard' : '/register'
const ctaLabel = user ? 'Войти в кабинет' : 'Начать бесплатно'
const feedbackSent = ref(false)
const feedbackFallbackMessage = 'Спасибо, ваш вопрос отправлен администратору. Ответ придет на указанную почту.'
const feedbackSuccessMessage = computed(() => page.props.flash?.success ?? (feedbackSent.value ? feedbackFallbackMessage : null))
const feedbackForm = useForm({
    name: '',
    email: '',
    message: '',
})

const manualCards = [
    ['Проверяем сайты 24/7', 'Сайт и домен под присмотром даже ночью, в выходные и праздники.'],
    ['Уведомляем о сбоях', 'Почта и Telegram помогают быстро отреагировать на проблему.'],
    ['Следим за сроками', 'Контролируем SSL, домены и DNS, чтобы не пропустить критичные даты.'],
    ['Формируем отчеты', 'История проверок и отчеты помогают видеть надежность сайтов.'],
]

const baseChecks = [
    ['Доступность сайта', 'HTTP/HTTPS, коды ответа и базовая доступность страниц.'],
    ['SSL', 'Проверяем срок сертификата и предупреждаем заранее.'],
    ['Домен', 'Контролируем срок регистрации и важные доменные риски.'],
    ['DNS', 'Проверяем базовые DNS-записи и ошибки настройки.'],
    ['Robots.txt', 'Проверяем наличие файла и базовую доступность.'],
]

const advancedChecks = [
    ['Sitemap.xml', 'Проверка наличия sitemap.xml и ошибок доступности.'],
    ['API endpoint', 'Проверка API-метода, ожидаемого кода и времени ответа.'],
    ['TCP-порт', 'Контроль доступности TCP-портов: 80, 443, 5432 и других.'],
]

const steps = [
    ['Добавьте сайт', 'Укажите URL, домен и проект клиента.'],
    ['Montry включает базовые проверки', 'Доступность, SSL, домен, DNS и robots.txt активируются автоматически.'],
    ['Настройте уведомления', 'Выберите email или Telegram для важных событий.'],
    ['Следите за состоянием', 'Проверяйте dashboard, инциденты и отчеты.'],
]

const audiences = [
    ['Владельцам сайтов', 'Следить за своим сайтом и узнавать о проблемах раньше посетителей.'],
    ['Фрилансерам', 'Контролировать сайты клиентов и быстрее реагировать на сбои.'],
    ['Веб-студиям', 'Держать все клиентские сайты в одном рабочем списке.'],
    ['Небольшим IT-командам', 'Мониторить важные ресурсы без тяжелой инфраструктуры.'],
]

const plans = [
    {
        name: 'Free',
        price: '0 ₽',
        href: '/register?plan=free',
        description: 'Для старта и базового мониторинга.',
        features: ['5 активных мониторингов', 'HTTP и SSL', 'email-уведомления', 'интервал от 5 минут', 'история 7 дней'],
    },
    {
        name: 'Pro',
        price: '590 ₽ / мес',
        href: '/register?plan=pro',
        description: 'Для специалистов и регулярного мониторинга.',
        featured: true,
        features: ['100 активных мониторингов', 'все типы проверок', 'email и Telegram', 'интервал от 1 минуты', 'история 30 дней'],
    },
    {
        name: 'Team',
        price: '1 490 ₽ / мес',
        href: '/register?plan=team',
        description: 'Для веб-студий, агентств и команд.',
        features: ['500 активных мониторингов', 'все типы проверок', 'до 10 пользователей', 'интервал от 1 минуты', 'история 90 дней'],
    },
]

const faq = [
    ['Что входит в базовый мониторинг сайта?', 'Доступность сайта, SSL, домен, DNS и наличие robots.txt.'],
    ['Можно ли пользоваться бесплатно?', 'Да. На тарифе Free можно добавить 1 сайт и получать email-уведомления.'],
    ['Какие проверки доступны?', 'HTTP и SSL доступны на Free. На Pro и Team включены все типы проверок без отдельных доплат.'],
    ['Можно ли получать уведомления в Telegram?', 'Да. Telegram доступен на тарифах Pro и Team.'],
    ['Подойдет ли Montry для веб-студии?', 'Да. Вы сможете контролировать сайты клиентов, видеть инциденты и формировать отчеты.'],
    ['Что происходит, если сайт перестает работать?', 'Montry сохраняет инцидент, отправляет уведомление и фиксирует восстановление.'],
]

function signupHref(href: string): string {
    return user ? '/billing' : href
}

function submitFeedback(): void {
    feedbackSent.value = false

    feedbackForm.post('/feedback', {
        preserveScroll: true,
        onSuccess: () => {
            feedbackForm.reset()
            feedbackSent.value = true
        },
    })
}
</script>

<template>
    <Head title="Montry — мониторинг сайтов без лишней сложности" />

    <main class="min-h-screen bg-[#F3F8F5] font-sans text-[#26332D]">
        <MarketingHeader />

        <section class="border-b border-[#DDEBE3] bg-[#EEF8F1]">
            <div class="mx-auto grid max-w-6xl gap-10 px-5 pb-16 pt-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_460px] lg:items-center lg:pb-20 lg:pt-16">
                <div>
                    <p class="inline-flex rounded-full bg-white px-3 py-1.5 text-xs font-bold text-[#229A5F] shadow-[0_8px_24px_rgba(31,68,49,0.06)]">
                        База мониторинга сайтов
                    </p>
                    <h1 class="mt-6 max-w-3xl text-[42px] font-extrabold leading-[1.06] tracking-normal text-[#223028] sm:text-[56px] lg:text-[64px]">
                        Мониторинг сайтов без лишней сложности
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-[#6B7D72] sm:text-lg">
                        Следите за доступностью сайта, доменом, DNS и SSL. Получайте понятные уведомления по почте и в Telegram.
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="ctaHref"
                            class="inline-flex h-12 items-center justify-center rounded-xl bg-[#24A869] px-5 text-sm font-semibold text-white shadow-[0_14px_30px_rgba(36,168,105,0.22)] transition hover:bg-[#1D9059] focus:outline-none focus:ring-2 focus:ring-[#24A869]/30 focus:ring-offset-2"
                        >
                            {{ ctaLabel }}
                        </Link>
                        <a
                            href="#pricing"
                            class="inline-flex h-12 items-center justify-center rounded-xl border border-[#D4E3DA] bg-white px-5 text-sm font-semibold text-[#26332D] transition hover:border-[#B8D0C2]"
                        >
                            Посмотреть тарифы
                        </a>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-2 text-xs font-semibold text-[#6B7D72]">
                        <span class="rounded-full bg-white px-3 py-1.5">1 сайт бесплатно</span>
                        <span class="rounded-full bg-white px-3 py-1.5">Базовый мониторинг включен</span>
                        <span class="rounded-full bg-white px-3 py-1.5">Уведомления на почту</span>
                        <span class="rounded-full bg-white px-3 py-1.5">История проверок</span>
                    </div>
                </div>

                <div class="mx-auto w-full max-w-[460px] rounded-3xl border border-[#DDEBE3] bg-white p-6 shadow-[0_28px_70px_rgba(31,68,49,0.13)]">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-xl font-extrabold text-[#26332D]">montry.ru</h2>
                            <p class="mt-2 text-xs font-semibold text-[#8A9A90]">Сайт</p>
                        </div>
                        <span class="rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-semibold text-[#24A869]">Работает</span>
                    </div>

                    <div class="mt-7 grid grid-cols-3 gap-3">
                        <div class="rounded-2xl border border-[#E8F0EB] p-4">
                            <p class="text-2xl font-extrabold text-[#24A869]">99.98%</p>
                            <p class="mt-1 text-xs font-semibold text-[#8A9A90]">uptime</p>
                        </div>
                        <div class="rounded-2xl border border-[#E8F0EB] p-4">
                            <p class="text-2xl font-extrabold text-[#24A869]">184 мс</p>
                            <p class="mt-1 text-xs font-semibold text-[#8A9A90]">ответ</p>
                        </div>
                        <div class="rounded-2xl border border-[#E8F0EB] p-4">
                            <p class="text-2xl font-extrabold text-[#24A869]">0</p>
                            <p class="mt-1 text-xs font-semibold text-[#8A9A90]">проблем</p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-[#E8F0EB] p-4">
                        <div class="space-y-3 text-sm font-semibold text-[#52645A]">
                            <p class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[#24A869]"></span>SSL действителен</p>
                            <p class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[#24A869]"></span>Домен активен</p>
                            <p class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[#24A869]"></span>DNS без ошибок</p>
                            <p class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[#24A869]"></span>Robots.txt найден</p>
                        </div>
                    </div>

                    <div class="mt-6 flex h-16 items-end gap-2">
                        <span
                            v-for="height in [28, 44, 37, 51, 32, 42, 56, 30, 48, 35, 54, 39, 46, 29]"
                            :key="height"
                            class="flex-1 rounded-t-md bg-[#74C99B]"
                            :style="{ height: `${height}px` }"
                        ></span>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Контроль сайта без ручных проверок</h2>
                    <p class="mt-3 text-sm leading-6 text-[#738479]">Montry помогает быстро узнать о сбоях и держать под контролем технические параметры сайта.</p>
                </div>

                <div class="mx-auto mt-9 grid max-w-4xl gap-4 sm:grid-cols-3">
                    <article v-for="[title, text] in manualCards" :key="title" class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.06)]">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-[#E9F8EF] text-sm font-extrabold text-[#24A869]">•</span>
                        <h3 class="mt-5 text-lg font-extrabold text-[#26332D]">{{ title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-[#738479]">{{ text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="checks" class="py-10 sm:py-14">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Базовый мониторинг уже включен</h2>
                    <p class="mt-3 text-sm leading-6 text-[#738479]">Добавьте сайт, и Montry сразу начнет следить за ключевыми техническими параметрами.</p>
                </div>

                <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <article v-for="[title, text] in baseChecks" :key="title" class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-[#E9F8EF] text-sm font-extrabold text-[#24A869]">✓</span>
                        <p class="mt-4 text-xs font-extrabold text-[#24A869]">Включено</p>
                        <h3 class="mt-1 text-base font-extrabold text-[#26332D]">{{ title }}</h3>
                        <p class="mt-2 text-xs leading-5 text-[#738479]">{{ text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="py-14 sm:py-16">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Все типы проверок — без отдельных доплат</h2>
                    <p class="mt-3 text-sm leading-6 text-[#738479]">На Pro и Team доступны Sitemap.xml, API endpoint, TCP-порты и другие расширенные проверки.</p>
                </div>

                <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article v-for="[title, text] in advancedChecks" :key="title" class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.06)]">
                        <p class="inline-flex rounded-full bg-[#FFF4DC] px-3 py-1 text-xs font-bold text-[#C87800]">Pro и Team</p>
                        <h3 class="mt-4 text-lg font-extrabold text-[#26332D]">{{ title }}</h3>
                        <p class="mt-3 text-sm leading-6 text-[#738479]">{{ text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="py-14 sm:py-16">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Запустить мониторинг можно за пару минут</h2>
                    <p class="mt-3 text-sm leading-6 text-[#738479]">Четыре простых шага — от добавления сайта до уведомлений и отчетов.</p>
                </div>

                <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article v-for="([title, text], index) in steps" :key="title" class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-[#24A869] text-sm font-extrabold text-white">{{ index + 1 }}</span>
                        <h3 class="mt-5 text-lg font-extrabold text-[#26332D]">{{ title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-[#738479]">{{ text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="audience" class="py-14 sm:py-16">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div>
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Для тех, кто отвечает за сайты</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-[#738479]">Montry одинаково полезен владельцу сайта и команде, которая отвечает за десятки проектов.</p>
                </div>

                <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article v-for="[title, text] in audiences" :key="title" class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-[#E8F0FF] text-sm font-extrabold text-[#4F7DE8]">•</span>
                        <h3 class="mt-5 text-lg font-extrabold text-[#26332D]">{{ title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-[#738479]">{{ text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Узнавайте о проблемах сразу</h2>
                    <p class="mt-3 text-sm leading-6 text-[#738479]">Montry фиксирует инциденты, сохраняет историю и отправляет уведомления.</p>
                </div>

                <div class="mx-auto mt-9 grid max-w-4xl gap-5 md:grid-cols-[1fr_0.9fr]">
                    <div class="rounded-2xl border border-[#DDEBE3] bg-white p-6 shadow-[0_14px_34px_rgba(31,68,49,0.07)]">
                        <p class="text-sm font-extrabold text-[#EA6A6A]">Активный инцидент</p>
                        <h3 class="mt-3 text-2xl font-extrabold text-[#26332D]">Сайт недоступен</h3>
                        <dl class="mt-5 space-y-3 text-sm text-[#738479]">
                            <div><dt class="font-bold text-[#52645A]">Сайт:</dt><dd>example.ru</dd></div>
                            <div><dt class="font-bold text-[#52645A]">Проверка:</dt><dd>доступность сайта</dd></div>
                            <div><dt class="font-bold text-[#52645A]">Ошибка:</dt><dd>HTTP 500</dd></div>
                            <div><dt class="font-bold text-[#52645A]">Начался:</dt><dd>12 минут назад</dd></div>
                        </dl>
                    </div>

                    <div class="grid gap-4">
                        <div class="flex items-center gap-3 rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                            <span class="h-3 w-3 rounded-full bg-[#24A869]"></span>
                            <p class="font-extrabold text-[#26332D]">Email отправлен</p>
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                            <span class="h-3 w-3 rounded-full bg-[#24A869]"></span>
                            <p class="font-extrabold text-[#26332D]">Telegram отправлен</p>
                        </div>
                        <div class="flex items-center gap-3 rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)]">
                            <span class="h-3 w-3 rounded-full bg-[#24A869]"></span>
                            <p class="font-extrabold text-[#26332D]">Инцидент записан в историю</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="pricing" class="py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Простые тарифы без сложного биллинга</h2>
                    <p class="mt-3 text-sm leading-6 text-[#738479]">Тарифы отличаются количеством мониторингов, интервалом, историей и возможностями для команды.</p>
                </div>

                <div class="mx-auto mt-9 grid max-w-5xl gap-5 lg:grid-cols-3">
                    <article
                        v-for="plan in plans"
                        :key="plan.name"
                        class="relative rounded-2xl border bg-white p-7 shadow-[0_18px_46px_rgba(31,68,49,0.08)]"
                        :class="plan.featured ? 'border-[#24A869] bg-[#F4FFF8] ring-1 ring-[#24A869]' : 'border-[#DDEBE3]'"
                    >
                        <span v-if="plan.featured" class="absolute right-5 top-5 rounded-full border border-[#BEE7CE] bg-white px-3 py-1 text-xs font-semibold text-[#24A869]">Популярный</span>
                        <h3 class="text-2xl font-extrabold text-[#26332D]">{{ plan.name }}</h3>
                        <p class="mt-4 text-2xl font-extrabold text-[#24A869]">{{ plan.price }}</p>
                        <p class="mt-4 min-h-12 text-sm leading-6 text-[#738479]">{{ plan.description }}</p>
                        <ul class="mt-6 space-y-3 text-sm font-semibold text-[#52645A]">
                            <li v-for="feature in plan.features" :key="feature" class="flex gap-2">
                                <span class="text-[#24A869]">✓</span>
                                <span>{{ feature }}</span>
                            </li>
                        </ul>
                        <Link
                            :href="signupHref(plan.href)"
                            class="mt-7 inline-flex h-11 w-full items-center justify-center rounded-xl px-4 text-sm font-semibold transition"
                            :class="plan.featured ? 'bg-[#24A869] text-white hover:bg-[#1D9059]' : 'border border-[#D4E3DA] bg-white text-[#26332D] hover:border-[#B8D0C2]'"
                        >
                            {{ plan.name === 'Free' ? 'Начать бесплатно' : `Выбрать ${plan.name}` }}
                        </Link>
                    </article>
                </div>

            </div>
        </section>

        <section id="articles" class="py-14 sm:py-16">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="mx-auto flex max-w-3xl flex-col items-center text-center">
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Статьи</h2>
                    <p class="mt-3 text-sm leading-6 text-[#738479]">Практичные материалы о мониторинге сайтов, SSL, доменов и работе с инцидентами.</p>
                    <Link href="/articles" class="mt-5 inline-flex h-11 items-center justify-center rounded-xl bg-[#24A869] px-5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(36,168,105,0.18)] transition hover:bg-[#1D9059]">
                        Посмотреть статьи
                    </Link>
                </div>

                <div v-if="articles?.length" class="mt-9 grid gap-4 md:grid-cols-3">
                    <Link
                        v-for="article in articles"
                        :key="article.slug"
                        :href="`/articles/${article.slug}`"
                        class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)] transition hover:-translate-y-0.5 hover:border-[#BEE7CE]"
                    >
                        <p class="text-xs font-semibold text-[#24A869]">Статья</p>
                        <h3 class="mt-3 text-lg font-extrabold text-[#26332D]">{{ article.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-[#738479]">{{ article.excerpt }}</p>
                    </Link>
                </div>
            </div>
        </section>

        <section id="feedback" class="py-14 sm:py-16">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="grid gap-8 rounded-3xl border border-[#DDEBE3] bg-white p-6 shadow-[0_18px_46px_rgba(31,68,49,0.08)] md:grid-cols-[0.85fr_1.15fr] md:p-8 lg:p-10">
                    <div>
                        <p class="text-sm font-bold text-[#24A869]">Обратная связь</p>
                        <h2 class="mt-4 text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">
                            Задайте вопрос или оставьте заявку
                        </h2>
                        <p class="mt-4 text-sm leading-6 text-[#738479]">
                            Напишите, сколько сайтов хотите мониторить, какие проверки важны и куда удобнее получить ответ. Сообщение придет администратору Montry на почту.
                        </p>

                        <div class="mt-6 rounded-2xl bg-[#F3F8F5] p-5">
                            <p class="text-sm font-bold text-[#26332D]">Можно спросить про:</p>
                            <ul class="mt-3 space-y-2 text-sm font-semibold text-[#52645A]">
                                <li class="flex gap-2"><span class="text-[#24A869]">✓</span> тарифы и дополнительные проверки;</li>
                                <li class="flex gap-2"><span class="text-[#24A869]">✓</span> мониторинг сайтов клиентов;</li>
                                <li class="flex gap-2"><span class="text-[#24A869]">✓</span> Telegram/email уведомления;</li>
                                <li class="flex gap-2"><span class="text-[#24A869]">✓</span> индивидуальный набор возможностей.</li>
                            </ul>
                        </div>
                    </div>

                    <form class="grid gap-4" @submit.prevent="submitFeedback">
                        <div
                            v-if="feedbackSuccessMessage"
                            class="rounded-2xl border border-[#BEE7CE] bg-[#E9F8EF] px-5 py-4 text-sm font-semibold leading-6 text-[#1D9059]"
                            role="status"
                            aria-live="polite"
                        >
                            {{ feedbackSuccessMessage }}
                        </div>

                        <label class="block">
                            <span class="text-sm font-semibold text-[#26332D]">Имя</span>
                            <input
                                v-model="feedbackForm.name"
                                type="text"
                                autocomplete="name"
                                required
                                :aria-invalid="Boolean(feedbackForm.errors.name)"
                                class="mt-2 h-12 w-full rounded-xl border border-[#D4E3DA] bg-[#FBFDFC] px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#8A9A90] focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/20"
                                placeholder="Как к вам обращаться"
                            >
                            <p v-if="feedbackForm.errors.name" class="mt-2 text-sm font-semibold text-[#D94B4B]">
                                {{ feedbackForm.errors.name }}
                            </p>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-[#26332D]">Почта</span>
                            <input
                                v-model="feedbackForm.email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                required
                                :aria-invalid="Boolean(feedbackForm.errors.email)"
                                class="mt-2 h-12 w-full rounded-xl border border-[#D4E3DA] bg-[#FBFDFC] px-4 text-sm font-medium text-[#26332D] outline-none transition placeholder:text-[#8A9A90] focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/20"
                                placeholder="name@example.ru"
                            >
                            <p v-if="feedbackForm.errors.email" class="mt-2 text-sm font-semibold text-[#D94B4B]">
                                {{ feedbackForm.errors.email }}
                            </p>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-[#26332D]">Сообщение</span>
                            <textarea
                                v-model="feedbackForm.message"
                                required
                                rows="6"
                                :aria-invalid="Boolean(feedbackForm.errors.message)"
                                class="mt-2 w-full resize-y rounded-xl border border-[#D4E3DA] bg-[#FBFDFC] px-4 py-3 text-sm font-medium leading-6 text-[#26332D] outline-none transition placeholder:text-[#8A9A90] focus:border-[#24A869] focus:ring-2 focus:ring-[#24A869]/20"
                                placeholder="Опишите вопрос, количество сайтов или нужные проверки"
                            ></textarea>
                            <p v-if="feedbackForm.errors.message" class="mt-2 text-sm font-semibold text-[#D94B4B]">
                                {{ feedbackForm.errors.message }}
                            </p>
                        </label>

                        <button
                            type="submit"
                            :disabled="feedbackForm.processing"
                            class="inline-flex h-12 items-center justify-center rounded-xl bg-[#24A869] px-5 text-sm font-semibold text-white shadow-[0_14px_30px_rgba(36,168,105,0.18)] transition hover:bg-[#1D9059] focus:outline-none focus:ring-2 focus:ring-[#24A869]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-max"
                        >
                            <span v-if="feedbackForm.processing">Отправляем...</span>
                            <span v-else>Отправить заявку</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <section class="py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Не нужно проверять сайты вручную</h2>
                    <p class="mt-3 text-sm leading-6 text-[#738479]">Один кабинет показывает статусы, историю инцидентов и отчеты для всех сайтов клиентов.</p>
                </div>

                <div class="mx-auto mt-9 grid max-w-5xl gap-5 lg:grid-cols-[1fr_1.1fr]">
                    <div class="rounded-2xl border border-[#DDEBE3] bg-white p-6 shadow-[0_14px_34px_rgba(31,68,49,0.07)]">
                        <ul class="space-y-4 text-sm font-semibold text-[#52645A]">
                            <li class="flex gap-3"><span class="text-[#24A869]">✓</span> Один кабинет для всех сайтов</li>
                            <li class="flex gap-3"><span class="text-[#24A869]">✓</span> Понятные статусы без технической перегрузки</li>
                            <li class="flex gap-3"><span class="text-[#24A869]">✓</span> История проверок и инцидентов</li>
                            <li class="flex gap-3"><span class="text-[#24A869]">✓</span> Отчеты для себя и клиентов</li>
                            <li class="flex gap-3"><span class="text-[#24A869]">✓</span> Почта и Telegram-уведомления</li>
                            <li class="flex gap-3"><span class="text-[#24A869]">✓</span> Платите только за нужные дополнения</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-[#DDEBE3] bg-white p-6 shadow-[0_14px_34px_rgba(31,68,49,0.07)]">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-bold text-[#8A9A90]">Сайт</p>
                                <h3 class="mt-1 text-xl font-extrabold text-[#26332D]">montry.ru</h3>
                            </div>
                            <span class="rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-semibold text-[#24A869]">Работает</span>
                        </div>
                        <div class="mt-5 grid grid-cols-3 gap-3">
                            <div class="rounded-2xl border border-[#E8F0EB] p-4"><p class="font-extrabold text-[#24A869]">99.98%</p><p class="mt-1 text-xs text-[#8A9A90]">uptime</p></div>
                            <div class="rounded-2xl border border-[#E8F0EB] p-4"><p class="font-extrabold text-[#24A869]">184 мс</p><p class="mt-1 text-xs text-[#8A9A90]">ответ</p></div>
                            <div class="rounded-2xl border border-[#E8F0EB] p-4"><p class="font-extrabold text-[#24A869]">0</p><p class="mt-1 text-xs text-[#8A9A90]">проблем</p></div>
                        </div>
                        <div class="mt-5 rounded-2xl border border-[#E8F0EB] p-4 text-sm font-semibold text-[#52645A]">
                            <p class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[#24A869]"></span>SSL действителен</p>
                            <p class="mt-3 flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[#24A869]"></span>Домен активен</p>
                            <p class="mt-3 flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[#24A869]"></span>DNS без ошибок</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="py-16 sm:py-20">
            <div class="mx-auto max-w-5xl px-5 sm:px-8">
                <div class="grid gap-6 md:grid-cols-[220px_1fr]">
                    <div>
                        <h2 class="text-3xl font-extrabold text-[#26332D]">FAQ</h2>
                        <p class="mt-3 text-sm leading-6 text-[#738479]">Ответы на частые вопросы о мониторинге, тарифах и уведомлениях.</p>
                    </div>
                    <div class="space-y-3">
                        <article v-for="[question, answer] in faq" :key="question" class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_8px_22px_rgba(31,68,49,0.04)]">
                            <h3 class="text-base font-extrabold text-[#26332D]">{{ question }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[#738479]">{{ answer }}</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="pb-16 sm:pb-20">
            <div class="mx-auto max-w-6xl px-5 sm:px-8">
                <div class="grid gap-8 rounded-3xl border border-[#DDEBE3] bg-[#E9F8EF] p-8 shadow-[0_18px_46px_rgba(31,68,49,0.08)] md:grid-cols-[1fr_270px] md:items-center">
                    <div>
                        <h2 class="max-w-xl text-3xl font-extrabold leading-tight text-[#26332D] sm:text-4xl">Начните следить за сайтом уже сегодня</h2>
                        <p class="mt-4 max-w-2xl text-sm leading-6 text-[#52645A]">Добавьте первый сайт бесплатно и получите базовый мониторинг без сложной настройки.</p>
                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <Link :href="ctaHref" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#24A869] px-5 text-sm font-semibold text-white transition hover:bg-[#1D9059]">{{ ctaLabel }}</Link>
                            <a href="#pricing" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#D4E3DA] bg-white px-5 text-sm font-semibold text-[#26332D] transition hover:border-[#B8D0C2]">Посмотреть тарифы</a>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_14px_34px_rgba(31,68,49,0.07)]">
                        <p class="text-xs font-semibold text-[#24A869]">Сайт работает</p>
                        <h3 class="mt-3 text-xl font-extrabold text-[#26332D]">montry.ru</h3>
                        <p class="mt-2 text-xs text-[#8A9A90]">uptime 99.98% · 184 мс</p>
                    </div>
                </div>
            </div>
        </section>

        <MarketingFooter />
    </main>
</template>
