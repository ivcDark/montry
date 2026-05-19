<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
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
        name: 'Free',
        caption: 'для старта',
        price: '0 ₽',
        description: 'Несколько базовых проверок, чтобы добавить первый сайт и понять пользу.',
        cta: 'Начать бесплатно',
        featured: false,
    },
    {
        name: 'Solo',
        caption: 'для владельца нескольких сайтов',
        price: '490 ₽/мес',
        description: 'Больше мониторов, SSL и домены, Telegram и email-уведомления.',
        cta: 'Выбрать Solo',
        featured: true,
    },
    {
        name: 'Studio',
        caption: 'для веб-студий и фрилансеров',
        price: '1490 ₽/мес',
        description: 'Проекты, клиенты, история инцидентов и мониторинг портфеля сайтов.',
        cta: 'Выбрать Studio',
        featured: false,
    },
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
                        Начните бесплатно, масштабируйтесь по мере роста
                    </h2>
                    <p class="mt-5 text-lg leading-8 text-[#667085]">
                        Три простых тарифа для разных сценариев: от первого сайта до клиентского портфеля.
                    </p>
                </div>

                <div class="mt-12 grid gap-5 lg:grid-cols-3">
                    <article
                        v-for="plan in plans"
                        :key="plan.name"
                        class="relative rounded-3xl border bg-white p-7 shadow-[0_10px_28px_rgba(15,23,42,0.06)]"
                        :class="plan.featured ? 'border-2 border-[#0F6BFF]' : 'border-[#E5E7EB]'"
                    >
                        <span
                            v-if="plan.featured"
                            class="absolute right-6 top-6 inline-flex h-7 items-center rounded-full bg-[#ECFDF3] px-3 text-xs font-extrabold text-[#16A34A]"
                        >
                            Популярный
                        </span>
                        <h3 class="text-2xl font-extrabold text-[#111827]">{{ plan.name }}</h3>
                        <p class="mt-1 text-sm font-semibold text-[#667085]">{{ plan.caption }}</p>
                        <p class="mt-6 text-4xl font-extrabold text-[#111827]">{{ plan.price }}</p>
                        <p class="mt-4 min-h-16 leading-7 text-[#667085]">{{ plan.description }}</p>

                        <ul class="mt-6 space-y-3 text-[#667085]">
                            <li class="flex gap-2"><span class="font-extrabold text-[#16A34A]">✓</span>HTTP/HTTPS-мониторинг</li>
                            <li class="flex gap-2"><span class="font-extrabold text-[#16A34A]">✓</span>SSL и домены</li>
                            <li class="flex gap-2"><span class="font-extrabold text-[#16A34A]">✓</span>Telegram-уведомления</li>
                            <li class="flex gap-2"><span class="font-extrabold text-[#16A34A]">✓</span>История инцидентов</li>
                        </ul>

                        <Link
                            :href="ctaHref"
                            class="mt-8 inline-flex h-12 w-full items-center justify-center rounded-xl px-5 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2"
                            :class="plan.featured ? 'bg-[#0F6BFF] text-white hover:bg-[#0757D8]' : 'border border-[#E5E7EB] bg-white text-[#111827] hover:border-[#CBD5E1]'"
                        >
                            {{ user ? 'Перейти в кабинет' : plan.cta }}
                        </Link>
                    </article>
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
