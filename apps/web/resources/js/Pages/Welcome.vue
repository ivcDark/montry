<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { onBeforeUnmount, ref } from 'vue'
import MarketingHeader from '@/Components/MarketingHeader.vue'

type User = {
    id: number | string
    name: string
    email: string
}

type PageProps = {
    auth: {
        user: User | null
    }
}

const page = usePage<PageProps>()
const user = page.props.auth.user

const problems = [
    {
        icon: '!',
        title: 'Клиент узнает о падении сайта раньше вас',
        description: 'Неприятная ситуация для студии, фрилансера или владельца бизнеса.',
    },
    {
        icon: 'S',
        title: 'SSL внезапно истек',
        description: 'Браузер показывает предупреждение, заявки падают, доверие теряется.',
    },
    {
        icon: 'D',
        title: 'Домен забыли продлить',
        description: 'Сайт и почта могут перестать работать в самый неподходящий момент.',
    },
    {
        icon: 'L',
        title: 'Нет единого списка всех клиентских сайтов',
        description: 'Проверки вручную занимают время и все равно не дают уверенности.',
    },
]

const features = [
    {
        icon: 'H',
        title: 'HTTP/HTTPS-мониторинг',
        description: 'Проверяйте доступность сайта, код ответа и время ответа.',
    },
    {
        icon: 'S',
        title: 'SSL-мониторинг',
        description: 'Заранее узнавайте, когда сертификат скоро истечет.',
    },
    {
        icon: 'D',
        title: 'Мониторинг доменов',
        description: 'Следите за сроком домена и продлевайте вовремя.',
    },
    {
        icon: 'T',
        title: 'Telegram и email',
        description: 'Получайте уведомления там, где удобно реагировать быстро.',
    },
    {
        icon: 'I',
        title: 'История инцидентов',
        description: 'Смотрите, что произошло, когда началось и когда восстановилось.',
    },
    {
        icon: 'P',
        title: 'Проекты и клиенты',
        description: 'Группируйте сайты по клиентам, проектам или направлениям.',
    },
]

const audiences = [
    ['Веб-студии', 'Контроль всех клиентских сайтов'],
    ['Фрилансеры', 'Мониторинг проектов без рутины'],
    ['SEO-специалисты', 'Доступность и технические риски'],
    ['Интернет-магазины', 'Заказы не теряются из-за падений'],
    ['Малый бизнес', 'Понятно, что происходит с сайтом'],
]

const plans = [
    {
        code: 'free',
        name: 'Free',
        caption: 'для знакомства',
        price: '0 ₽',
        description: 'Для знакомства с сервисом и личных сайтов.',
        cta: 'Начать бесплатно',
        badge: '',
        featured: false,
        agency: false,
        highlights: [
            '3 сайта и 9 мониторингов',
            'HTTP-интервал от 15 минут',
            'HTTP/HTTPS, SSL и Domain',
            'Telegram и email-уведомления',
            'Ручная проверка',
            'История 7 дней и 1 проект',
        ],
    },
    {
        code: 'pro',
        name: 'Pro',
        caption: 'для специалистов',
        price: '390 ₽/мес',
        description: 'Для фрилансеров, SEO-специалистов и владельцев нескольких сайтов.',
        cta: 'Выбрать Pro',
        badge: 'Популярный',
        featured: true,
        agency: false,
        highlights: [
            '15 сайтов и 45 мониторингов',
            'HTTP-интервал от 5 минут',
            'HTTP/HTTPS, SSL и Domain',
            'Telegram и email-уведомления',
            'История 30 дней и 5 проектов',
            'Отчёты и 2 получателя уведомлений',
        ],
    },
    {
        code: 'plus',
        name: 'Plus',
        caption: 'для агентств',
        price: '1 190 ₽/мес',
        description: 'Для веб-студий, агентств и большого числа клиентских сайтов.',
        cta: 'Выбрать Plus',
        badge: 'Для веб-студий',
        featured: false,
        agency: true,
        highlights: [
            '60 сайтов и 180 мониторингов',
            'HTTP-интервал от 1 минуты',
            'HTTP/HTTPS, SSL и Domain',
            'Telegram и email-уведомления',
            'История 180 дней и 25 проектов',
            'Отчёты и 10 получателей уведомлений',
        ],
    },
]

const comparisonRows = [
    { feature: 'Сайты', free: '3', pro: '15', plus: '60' },
    { feature: 'Мониторинги', free: '9', pro: '45', plus: '180' },
    { feature: 'Минимальный интервал мониторинга', free: 'от 15 минут', pro: 'от 5 минут', plus: 'от 1 минуты' },
    { feature: 'HTTP/HTTPS мониторинг', free: 'да', pro: 'да', plus: 'да' },
    { feature: 'SSL мониторинг', free: 'да', pro: 'да', plus: 'да' },
    { feature: 'Domain мониторинг', free: 'да', pro: 'да', plus: 'да' },
    { feature: 'Telegram-уведомления', free: 'да', pro: 'да', plus: 'да' },
    { feature: 'Email-уведомления', free: 'да', pro: 'да', plus: 'да' },
    { feature: 'Ручная проверка', free: 'да', pro: 'да', plus: 'да' },
    { feature: 'История проверок', free: '7 дней', pro: '30 дней', plus: '180 дней' },
    { feature: 'Проекты', free: '1', pro: '5', plus: '25' },
    { feature: 'Отчёты', free: 'нет', pro: 'да', plus: 'да' },
    { feature: 'Получатели уведомлений', free: '1', pro: '2', plus: '10' },
    { feature: 'Командный доступ', free: 'нет', pro: 'нет', plus: 'позже' },
    { feature: 'White label отчёты', free: 'нет', pro: 'нет', plus: 'позже' },
    { feature: 'Приоритетная поддержка', free: 'нет', pro: 'нет', plus: 'позже' },
]

const faq = [
    {
        question: 'Что можно добавить в Montry?',
        answer: 'Сайт, домен или клиентский проект. Для MVP основной сценарий - добавить сайт и включить проверки доступности, SSL и домена.',
    },
    {
        question: 'Куда приходят уведомления?',
        answer: 'В Telegram и на email. Акцент сделан на Telegram, чтобы быстро увидеть проблему и среагировать.',
    },
    {
        question: 'Montry подходит веб-студиям?',
        answer: 'Да. Можно вести сайты клиентов, видеть проблемные проекты и узнавать о падениях раньше клиента.',
    },
    {
        question: 'Нужно ли разбираться в сложном мониторинге?',
        answer: 'Нет. Интерфейс показывает простые статусы: OK, Warning, Down и Paused.',
    },
]

const ctaHref = user ? '/dashboard' : '/register'
const ctaLabel = user ? 'Перейти в кабинет' : 'Начать бесплатно'
const feedbackForm = useForm({
    name: '',
    email: '',
    message: '',
})
const isFeedbackModalVisible = ref(false)
let feedbackModalTimer: ReturnType<typeof setTimeout> | null = null

function planHref(planCode: string): string {
    return user ? '/billing' : `/register?plan=${planCode}`
}

function comparisonValueClass(value: string): string {
    if (value === 'да') {
        return 'font-extrabold text-[#16A34A]'
    }

    if (value === 'нет') {
        return 'font-semibold text-[#98A2B3]'
    }

    if (value === 'позже') {
        return 'font-extrabold text-[#F59E0B]'
    }

    return 'font-semibold text-[#475467]'
}

function scrollToFeedbackForm(): void {
    document.getElementById('feedback-form')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    })
}

function clearFeedbackModalTimer(): void {
    if (feedbackModalTimer) {
        clearTimeout(feedbackModalTimer)
        feedbackModalTimer = null
    }
}

function closeFeedbackModal(): void {
    isFeedbackModalVisible.value = false
    clearFeedbackModalTimer()
}

function showFeedbackSuccessModal(): void {
    isFeedbackModalVisible.value = true
    clearFeedbackModalTimer()

    feedbackModalTimer = setTimeout(() => {
        isFeedbackModalVisible.value = false
        feedbackModalTimer = null
    }, 10000)
}

function submitFeedbackForm(): void {
    feedbackForm.post('/feedback', {
        preserveScroll: true,
        onSuccess: () => {
            feedbackForm.reset()
            showFeedbackSuccessModal()
        },
    })
}

onBeforeUnmount(() => {
    clearFeedbackModalTimer()
})
</script>

<template>
    <Head title="Montry" />

    <main class="min-h-screen bg-[#F6F8FB] font-sans text-[#111827]">
        <MarketingHeader />

        <section class="relative overflow-hidden">
            <div class="mx-auto grid max-w-7xl gap-12 px-5 pb-20 pt-16 sm:px-8 lg:grid-cols-[minmax(0,1fr)_520px] lg:items-center lg:pb-28 lg:pt-20">
                <div>
                    <h1 class="max-w-3xl text-5xl font-extrabold leading-[1.05] tracking-normal text-[#111827] sm:text-6xl lg:text-[64px]">
                        Следите за сайтами, SSL и доменами в одном месте
                    </h1>

                    <p class="mt-7 max-w-2xl text-lg leading-8 text-[#667085] sm:text-[21px]">
                        Montry предупредит вас в Telegram и на email, если сайт упал, SSL скоро истекает или домен пора продлить.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="ctaHref"
                            class="inline-flex h-14 items-center justify-center rounded-[14px] bg-[#0F6BFF] px-6 text-base font-bold text-white shadow-[0_18px_42px_rgba(15,107,255,0.2)] transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2"
                        >
                            {{ ctaLabel }}
                        </Link>

                        <a
                            href="#features"
                            class="inline-flex h-14 items-center justify-center rounded-[14px] border border-[#E5E7EB] bg-white px-6 text-base font-bold text-[#111827] shadow-[0_10px_28px_rgba(15,23,42,0.06)] transition hover:border-[#CBD5E1]"
                        >
                            Посмотреть возможности
                        </a>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <span class="rounded-full bg-white px-4 py-2 text-sm font-bold text-[#EF4444] shadow-[0_10px_28px_rgba(15,23,42,0.06)]">Сайт упал</span>
                        <span class="rounded-full bg-white px-4 py-2 text-sm font-bold text-[#F59E0B] shadow-[0_10px_28px_rgba(15,23,42,0.06)]">SSL истекает</span>
                        <span class="rounded-full bg-white px-4 py-2 text-sm font-bold text-[#12B3A8] shadow-[0_10px_28px_rgba(15,23,42,0.06)]">Домен пора продлить</span>
                    </div>
                </div>

                <div class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_24px_64px_rgba(15,23,42,0.12)] sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-[#667085]">Обзор сайтов</p>
                            <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">Все под контролем</h2>
                        </div>
                        <span class="inline-flex h-8 items-center rounded-full bg-[#ECFDF3] px-3 text-xs font-extrabold text-[#16A34A]">Live</span>
                    </div>

                    <div class="mt-7 grid grid-cols-3 gap-3">
                        <div class="rounded-2xl bg-[#F8FAFC] p-4">
                            <p class="text-xs font-bold text-[#667085]">Работает</p>
                            <p class="mt-3 text-3xl font-extrabold text-[#111827]">24</p>
                        </div>
                        <div class="rounded-2xl bg-[#FEECEC] p-4">
                            <p class="text-xs font-bold text-[#EF4444]">Проблемы</p>
                            <p class="mt-3 text-3xl font-extrabold text-[#111827]">2</p>
                        </div>
                        <div class="rounded-2xl bg-[#FFF7E8] p-4">
                            <p class="text-xs font-bold text-[#F59E0B]">SSL скоро</p>
                            <p class="mt-3 text-3xl font-extrabold text-[#111827]">3</p>
                        </div>
                    </div>

                    <div class="mt-7">
                        <p class="text-sm font-extrabold text-[#111827]">Последние инциденты</p>
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center gap-3 rounded-2xl border border-[#FECACA] bg-[#FFF8F8] p-4">
                                <span class="inline-flex h-7 items-center rounded-full bg-[#FEECEC] px-3 text-xs font-extrabold text-[#EF4444]">Down</span>
                                <div class="min-w-0">
                                    <p class="font-bold text-[#111827]">client-shop.ru</p>
                                    <p class="text-sm text-[#667085]">502 Bad Gateway</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 rounded-2xl border border-[#FED7AA] bg-[#FFFBF1] p-4">
                                <span class="inline-flex h-7 items-center rounded-full bg-[#FFF7E8] px-3 text-xs font-extrabold text-[#F59E0B]">Warning</span>
                                <div class="min-w-0">
                                    <p class="font-bold text-[#111827]">studio-site.ru</p>
                                    <p class="text-sm text-[#667085]">SSL истекает: 9 дней</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 rounded-2xl border border-[#BBF7D0] bg-[#F6FEF9] p-4">
                                <span class="inline-flex h-7 items-center rounded-full bg-[#ECFDF3] px-3 text-xs font-extrabold text-[#16A34A]">OK</span>
                                <div class="min-w-0">
                                    <p class="font-bold text-[#111827]">landing.ru</p>
                                    <p class="text-sm text-[#667085]">Сайт восстановился</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-extrabold text-[#12B3A8]">Почему это важно</p>
                    <h2 class="mt-4 text-4xl font-extrabold leading-tight text-[#111827] sm:text-5xl">
                        Проблемы, которые Montry помогает не пропустить
                    </h2>
                    <p class="mt-5 text-lg leading-8 text-[#667085]">
                        Когда сайтов много, мелкие риски быстро превращаются в неудобные разговоры с клиентами.
                    </p>
                </div>

                <div class="mt-10 grid gap-4 md:grid-cols-2">
                    <article
                        v-for="problem in problems"
                        :key="problem.title"
                        class="rounded-3xl border border-[#FECACA] bg-gradient-to-b from-white to-[#FFF8F8] p-6 shadow-[0_10px_28px_rgba(15,23,42,0.06)]"
                    >
                        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-[#FEECEC] text-base font-extrabold text-[#EF4444]">
                            {{ problem.icon }}
                        </div>
                        <h3 class="mt-5 text-xl font-extrabold text-[#111827]">{{ problem.title }}</h3>
                        <p class="mt-3 leading-7 text-[#667085]">{{ problem.description }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="features" class="py-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-sm font-extrabold text-[#12B3A8]">Возможности</p>
                    <h2 class="mt-4 text-4xl font-extrabold leading-tight text-[#111827] sm:text-5xl">
                        Все базовое для спокойного мониторинга
                    </h2>
                    <p class="mt-5 text-lg leading-8 text-[#667085]">
                        Без сложного enterprise-интерфейса: только важные статусы, уведомления и история.
                    </p>
                </div>

                <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="feature in features"
                        :key="feature.title"
                        class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-[0_10px_28px_rgba(15,23,42,0.06)]"
                    >
                        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-[#EAF2FF] text-base font-extrabold text-[#0F6BFF]">
                            {{ feature.icon }}
                        </div>
                        <h3 class="mt-5 text-xl font-extrabold text-[#111827]">{{ feature.title }}</h3>
                        <p class="mt-3 leading-7 text-[#667085]">{{ feature.description }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="audience" class="bg-white py-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-sm font-extrabold text-[#12B3A8]">Для кого</p>
                    <h2 class="mt-4 text-4xl font-extrabold leading-tight text-[#111827] sm:text-5xl">
                        Montry подходит тем, кто отвечает за сайты
                    </h2>
                    <p class="mt-5 text-lg leading-8 text-[#667085]">
                        Один понятный сервис для команд и специалистов без лишней сложности.
                    </p>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <article
                        v-for="[title, description] in audiences"
                        :key="title"
                        class="rounded-3xl border border-[#E5E7EB] bg-[#F8FAFC] p-5 text-center"
                    >
                        <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white text-sm font-extrabold text-[#0F6BFF] shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                            {{ title.slice(0, 1) }}
                        </div>
                        <h3 class="mt-5 text-lg font-extrabold text-[#111827]">{{ title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-[#667085]">{{ description }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="pricing" class="py-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-sm font-extrabold text-[#12B3A8]">Тарифы</p>
                    <h2 class="mt-4 text-4xl font-extrabold leading-tight text-[#111827] sm:text-5xl">
                        Простые тарифы для сайтов, SSL и доменов
                    </h2>
                    <p class="mt-5 text-lg leading-8 text-[#667085]">
                        Начните бесплатно, а когда сайтов станет больше — перейдите на Pro или Plus.
                    </p>
                </div>

                <div class="mt-12 grid gap-5 lg:grid-cols-3">
                    <article
                        v-for="plan in plans"
                        :key="plan.name"
                        class="relative flex rounded-3xl border bg-white p-7 shadow-[0_10px_28px_rgba(15,23,42,0.06)]"
                        :class="plan.featured || plan.agency ? 'border-2 border-[#0F6BFF]' : 'border-[#E5E7EB]'"
                    >
                        <div class="flex w-full flex-col">
                            <div class="flex min-h-8 items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-2xl font-extrabold text-[#111827]">{{ plan.name }}</h3>
                                    <p class="mt-1 text-sm font-semibold text-[#667085]">{{ plan.caption }}</p>
                                </div>
                                <span
                                    v-if="plan.badge"
                                    class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-extrabold"
                                    :class="plan.agency ? 'bg-[#EAF2FF] text-[#0F6BFF]' : 'bg-[#ECFDF3] text-[#16A34A]'"
                                >
                                    {{ plan.badge }}
                                </span>
                            </div>

                            <p class="mt-6 text-4xl font-extrabold text-[#111827]">{{ plan.price }}</p>
                            <p class="mt-4 min-h-20 leading-7 text-[#667085]">{{ plan.description }}</p>

                            <ul class="mt-6 space-y-3 text-sm font-semibold text-[#475467]">
                                <li
                                    v-for="highlight in plan.highlights"
                                    :key="highlight"
                                    class="flex gap-2 leading-6"
                                >
                                    <span class="font-extrabold text-[#16A34A]">✓</span>
                                    <span>{{ highlight }}</span>
                                </li>
                            </ul>

                            <Link
                                :href="planHref(plan.code)"
                                class="mt-8 inline-flex h-12 w-full items-center justify-center rounded-xl px-5 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2"
                                :class="plan.featured || plan.agency ? 'bg-[#0F6BFF] text-white hover:bg-[#0757D8]' : 'border border-[#E5E7EB] bg-white text-[#111827] hover:border-[#CBD5E1]'"
                            >
                                {{ plan.cta }}
                            </Link>
                        </div>
                    </article>
                </div>

                <div class="mt-16">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="text-sm font-extrabold text-[#12B3A8]">Сравнение тарифов</p>
                        <h2 class="mt-4 text-4xl font-extrabold leading-tight text-[#111827] sm:text-5xl">
                            Сравнение возможностей
                        </h2>
                    </div>

                    <div class="mt-10 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[820px] text-left text-sm">
                                <thead class="bg-[#F8FAFC] text-xs font-extrabold uppercase tracking-normal text-[#667085]">
                                <tr>
                                    <th class="px-6 py-4">Возможность</th>
                                    <th class="px-6 py-4">Free</th>
                                    <th class="px-6 py-4">Pro</th>
                                    <th class="px-6 py-4">Plus</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E5E7EB]">
                                <tr v-for="row in comparisonRows" :key="row.feature">
                                    <td class="px-6 py-4 font-bold text-[#111827]">{{ row.feature }}</td>
                                    <td class="px-6 py-4" :class="comparisonValueClass(row.free)">{{ row.free }}</td>
                                    <td class="px-6 py-4" :class="comparisonValueClass(row.pro)">{{ row.pro }}</td>
                                    <td class="px-6 py-4" :class="comparisonValueClass(row.plus)">{{ row.plus }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mx-auto mt-10 max-w-3xl rounded-3xl border border-[#E5E7EB] bg-white p-6 text-center shadow-[0_10px_28px_rgba(15,23,42,0.06)] sm:p-8">
                        <p class="text-base font-semibold leading-7 text-[#667085] sm:text-lg sm:leading-8">
                            Если у вас много сайтов, особые лимиты или нужен индивидуальный набор возможностей — напишите нам, и мы подберём тариф под вашу задачу.
                        </p>
                        <button
                            type="button"
                            class="mt-6 inline-flex h-12 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-bold text-white transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2"
                            @click="scrollToFeedbackForm"
                        >
                            Обсудить индивидуальный тариф
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="bg-white py-20">
            <div class="mx-auto max-w-5xl px-5 sm:px-8">
                <div class="text-center">
                    <p class="text-sm font-extrabold text-[#12B3A8]">FAQ</p>
                    <h2 class="mt-4 text-4xl font-extrabold leading-tight text-[#111827] sm:text-5xl">
                        Частые вопросы
                    </h2>
                    <p class="mt-5 text-lg leading-8 text-[#667085]">
                        Коротко о том, как работает Montry и что пользователь получает после регистрации.
                    </p>
                </div>

                <div class="mt-10 divide-y divide-[#E5E7EB] rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <article
                        v-for="item in faq"
                        :key="item.question"
                        class="grid gap-3 p-6 sm:grid-cols-[minmax(0,0.45fr)_minmax(0,1fr)] sm:gap-8"
                    >
                        <h3 class="text-lg font-extrabold text-[#111827]">{{ item.question }}</h3>
                        <p class="leading-7 text-[#667085]">{{ item.answer }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="feedback-form" class="bg-white pb-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="grid gap-8 rounded-3xl border border-[#E5E7EB] bg-[#F8FAFC] p-6 shadow-[0_10px_28px_rgba(15,23,42,0.06)] sm:p-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-start lg:p-10">
                    <div>
                        <p class="text-sm font-extrabold text-[#12B3A8]">Обратная связь</p>
                        <h2 class="mt-4 text-4xl font-extrabold leading-tight text-[#111827] sm:text-5xl">
                            Напишите нам
                        </h2>
                        <p class="mt-5 text-lg leading-8 text-[#667085]">
                            Расскажите, сколько сайтов нужно мониторить, какие лимиты важны и какие возможности нужны вашей команде. Мы вернёмся с подходящим вариантом тарифа.
                        </p>
                    </div>

                    <form class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-[0_10px_28px_rgba(15,23,42,0.06)]" @submit.prevent="submitFeedbackForm">
                        <div class="space-y-5">
                            <label class="block">
                                <span class="text-sm font-bold text-[#111827]">Имя</span>
                                <input
                                    v-model="feedbackForm.name"
                                    type="text"
                                    required
                                    :aria-invalid="Boolean(feedbackForm.errors.name)"
                                    aria-describedby="feedback-name-error"
                                    class="mt-2 h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-semibold text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/20"
                                    placeholder="Как к вам обращаться"
                                >
                                <p id="feedback-name-error" v-if="feedbackForm.errors.name" class="mt-2 text-sm font-semibold text-[#EF4444]">
                                    {{ feedbackForm.errors.name }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold text-[#111827]">Почта</span>
                                <input
                                    v-model="feedbackForm.email"
                                    type="email"
                                    required
                                    inputmode="email"
                                    :aria-invalid="Boolean(feedbackForm.errors.email)"
                                    aria-describedby="feedback-email-error"
                                    class="mt-2 h-12 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-semibold text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/20"
                                    placeholder="name@example.ru"
                                >
                                <p id="feedback-email-error" v-if="feedbackForm.errors.email" class="mt-2 text-sm font-semibold text-[#EF4444]">
                                    {{ feedbackForm.errors.email }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold text-[#111827]">Текст обращения</span>
                                <textarea
                                    v-model="feedbackForm.message"
                                    required
                                    rows="5"
                                    :aria-invalid="Boolean(feedbackForm.errors.message)"
                                    aria-describedby="feedback-message-error"
                                    class="mt-2 w-full resize-y rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-sm font-semibold leading-6 text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/20"
                                    placeholder="Опишите задачу, количество сайтов и нужные возможности"
                                ></textarea>
                                <p id="feedback-message-error" v-if="feedbackForm.errors.message" class="mt-2 text-sm font-semibold text-[#EF4444]">
                                    {{ feedbackForm.errors.message }}
                                </p>
                            </label>
                        </div>

                        <button
                            type="submit"
                            :disabled="feedbackForm.processing"
                            class="mt-6 inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-bold text-white transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                        >
                            <span v-if="feedbackForm.processing">Отправляем...</span>
                            <span v-else>Отправить</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <section class="py-20">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-[minmax(0,1fr)_390px] lg:items-center">
                <div class="rounded-3xl bg-[#0B1220] p-8 text-white shadow-[0_24px_64px_rgba(15,23,42,0.18)] sm:p-12">
                    <h2 class="max-w-3xl text-4xl font-extrabold leading-tight sm:text-5xl">
                        Добавьте первый сайт за 1 минуту
                    </h2>
                    <p class="mt-5 max-w-2xl text-lg leading-8 text-[#CBD5E1]">
                        Зарегистрируйтесь, добавьте сайт и включите уведомления в Telegram или на email. Montry начнет следить за доступностью, SSL и доменом.
                    </p>
                    <Link
                        :href="ctaHref"
                        class="mt-8 inline-flex h-14 items-center justify-center rounded-[14px] bg-[#0F6BFF] px-6 text-base font-bold text-white transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-white/30 focus:ring-offset-2 focus:ring-offset-[#0B1220]"
                    >
                        {{ ctaLabel }}
                    </Link>
                </div>

                <div class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-[0_24px_64px_rgba(15,23,42,0.12)]">
                    <p class="text-sm font-bold text-[#667085]">Проверка сайта</p>
                    <h3 class="mt-2 text-2xl font-extrabold text-[#111827]">client-site.ru</h3>
                    <div class="mt-6 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl bg-[#F8FAFC] p-4">
                            <span class="font-bold text-[#111827]">Доступность</span>
                            <span class="rounded-full bg-[#ECFDF3] px-3 py-1 text-xs font-extrabold text-[#16A34A]">OK</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-[#F8FAFC] p-4">
                            <span class="font-bold text-[#111827]">SSL-сертификат</span>
                            <span class="rounded-full bg-[#ECFDF3] px-3 py-1 text-xs font-extrabold text-[#16A34A]">42 дня</span>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-[#F8FAFC] p-4">
                            <span class="font-bold text-[#111827]">Домен</span>
                            <span class="rounded-full bg-[#ECFDF3] px-3 py-1 text-xs font-extrabold text-[#16A34A]">128 дней</span>
                        </div>
                    </div>
                    <p class="mt-5 text-sm font-semibold text-[#667085]">Последняя проверка: только что</p>
                </div>
            </div>
        </section>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isFeedbackModalVisible"
                class="fixed inset-0 z-50 grid place-items-center bg-[#0B1220]/55 px-5 py-8"
                role="dialog"
                aria-modal="true"
                aria-labelledby="feedback-modal-title"
            >
                <div class="w-full max-w-md rounded-3xl border border-[#E5E7EB] bg-white p-6 text-center shadow-[0_24px_64px_rgba(15,23,42,0.18)] sm:p-8">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#ECFDF3] text-xl font-extrabold text-[#16A34A]">
                        ✓
                    </div>
                    <h2 id="feedback-modal-title" class="mt-5 text-2xl font-extrabold leading-tight text-[#111827]">
                        Спасибо, Ваш вопрос отправлен администратору
                    </h2>
                    <button
                        type="button"
                        class="mt-6 inline-flex h-12 items-center justify-center rounded-xl bg-[#0F6BFF] px-7 text-sm font-bold text-white transition hover:bg-[#0757D8] focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2"
                        @click="closeFeedbackModal"
                    >
                        Ок
                    </button>
                </div>
            </div>
        </Transition>

        <footer class="border-t border-[#E5E7EB] bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-8 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0F6BFF] text-lg font-extrabold text-white">M</span>
                        <span class="text-2xl font-extrabold text-[#111827]">Montry</span>
                    </div>
                    <p class="mt-3 max-w-md text-sm leading-6 text-[#667085]">
                        Мониторинг сайтов, SSL и доменов для веб-студий и бизнеса.
                    </p>
                </div>

                <div class="flex flex-wrap gap-5 text-sm font-semibold text-[#667085]">
                    <a href="/#features" class="transition hover:text-[#111827]">Возможности</a>
                    <a href="/#audience" class="transition hover:text-[#111827]">Для кого</a>
                    <a href="/#pricing" class="transition hover:text-[#111827]">Тарифы</a>
                    <a href="/#faq" class="transition hover:text-[#111827]">FAQ</a>
                    <Link v-if="!user" href="/login" class="transition hover:text-[#111827]">Войти</Link>
                </div>

                <p class="text-sm font-semibold text-[#667085]">© 2026 Montry</p>
            </div>
        </footer>
    </main>
</template>
