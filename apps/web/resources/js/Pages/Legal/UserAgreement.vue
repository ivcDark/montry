<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import MarketingHeader from '@/Components/MarketingHeader.vue'
import MarketingFooter from '@/Components/MarketingFooter.vue'

type Section = {
    title: string
    items: string[]
}

const updatedAt = '17.06.2026'

const sections: Section[] = [
    {
        title: '1. Общие условия',
        items: [
            'Настоящее пользовательское соглашение регулирует использование сайта и личного кабинета Montry.',
            'Используя Montry, пользователь подтверждает, что ознакомился с условиями соглашения и принимает их.',
            'Montry предназначен для мониторинга сайтов, доменов, SSL-сертификатов и связанных технических проверок.',
        ],
    },
    {
        title: '2. Аккаунт пользователя',
        items: [
            'Пользователь отвечает за актуальность данных, указанных при регистрации и в личном кабинете.',
            'Пользователь обязан самостоятельно обеспечивать конфиденциальность доступа к своему аккаунту.',
            'Действия, совершенные через аккаунт пользователя, считаются совершенными самим пользователем.',
        ],
    },
    {
        title: '3. Использование сервиса',
        items: [
            'Пользователь обязуется использовать Montry законно и не нарушать права третьих лиц.',
            'Запрещено использовать сервис для атак, сканирования чужой инфраструктуры без разрешения или иной вредоносной активности.',
            'Исполнитель вправе ограничить доступ к сервису при нарушении условий соглашения или законодательства.',
        ],
    },
    {
        title: '4. Уведомления и результаты проверок',
        items: [
            'Montry стремится своевременно выполнять проверки и отправлять уведомления, но не гарантирует абсолютную бесперебойность сервиса.',
            'Результаты проверок носят информационный характер и помогают пользователю быстрее обнаруживать технические проблемы.',
            'Пользователь самостоятельно принимает решения о действиях с сайтами, доменами и инфраструктурой на основании полученных данных.',
        ],
    },
    {
        title: '5. Документы и оплата',
        items: [
            'Платные услуги оказываются на условиях публичной оферты, размещенной на сайте Montry.',
            'При противоречии между настоящим соглашением и публичной офертой в части платных услуг применяются условия публичной оферты.',
            'Актуальные тарифы и лимиты отображаются на сайте и в личном кабинете пользователя.',
        ],
    },
    {
        title: '6. Изменение соглашения',
        items: [
            'Исполнитель вправе обновлять пользовательское соглашение, размещая новую редакцию на сайте.',
            'Продолжение использования Montry после публикации новой редакции означает согласие пользователя с изменениями.',
        ],
    },
]

const requisites = [
    { label: 'Исполнитель', value: 'Иванов Владимир Юрьевич' },
    { label: 'Статус', value: 'Самозанятый' },
    { label: 'ИНН', value: '562503808625' },
    { label: 'Контактный e-mail', value: 'vladimir@vl-iv.ru', href: 'mailto:vladimir@vl-iv.ru' },
]
</script>

<template>
    <Head title="Пользовательское соглашение" />

    <main class="min-h-screen bg-[#F8FAFC] text-[#111827]">
        <MarketingHeader context-label="Соглашение" />

        <section class="border-b border-[#E5E7EB] bg-white">
            <div class="mx-auto max-w-4xl px-5 py-12 sm:px-8 lg:py-16">
                <Link href="/" class="text-sm font-extrabold text-[#0F6BFF] transition hover:text-[#0757D8]">
                    ← На главную
                </Link>
                <p class="mt-8 text-sm font-extrabold uppercase tracking-normal text-[#12B3A8]">Montry</p>
                <h1 class="mt-3 text-4xl font-extrabold leading-tight text-[#111827] sm:text-5xl">
                    Пользовательское соглашение
                </h1>
                <p class="mt-5 max-w-3xl text-lg leading-8 text-[#667085]">
                    Правила использования сайта и личного кабинета Montry.
                </p>
                <p class="mt-4 text-sm font-semibold text-[#667085]">Редакция от {{ updatedAt }}</p>
            </div>
        </section>

        <section class="mx-auto grid max-w-4xl gap-5 px-5 py-10 sm:px-8">
            <article
                v-for="section in sections"
                :key="section.title"
                class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-[0_18px_60px_rgba(15,23,42,0.05)] sm:p-8"
            >
                <h2 class="text-2xl font-extrabold text-[#111827]">{{ section.title }}</h2>

                <ul class="mt-5 grid gap-3 rounded-2xl bg-[#F8FAFC] px-5 py-4 text-base leading-8 text-[#475467]">
                    <li
                        v-for="item in section.items"
                        :key="item"
                        class="flex gap-3"
                    >
                        <span class="mt-3 h-1.5 w-1.5 shrink-0 rounded-full bg-[#12B3A8]"></span>
                        <span>{{ item }}</span>
                    </li>
                </ul>
            </article>

            <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-[0_18px_60px_rgba(15,23,42,0.05)] sm:p-8">
                <h2 class="text-2xl font-extrabold text-[#111827]">7. Реквизиты</h2>

                <dl class="mt-5 grid gap-3 text-base leading-7 text-[#475467]">
                    <div
                        v-for="requisite in requisites"
                        :key="requisite.label"
                        class="grid gap-1 rounded-2xl bg-[#F8FAFC] px-5 py-4 sm:grid-cols-[220px_1fr] sm:gap-4"
                    >
                        <dt class="font-extrabold text-[#111827]">{{ requisite.label }}</dt>
                        <dd>
                            <a
                                v-if="requisite.href"
                                :href="requisite.href"
                                class="font-semibold text-[#0F6BFF] transition hover:text-[#0757D8]"
                            >
                                {{ requisite.value }}
                            </a>
                            <span v-else>{{ requisite.value }}</span>
                        </dd>
                    </div>
                </dl>
            </article>
        </section>

        <MarketingFooter />
    </main>
</template>
