<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import MarketingHeader from '@/Components/MarketingHeader.vue'
import MarketingFooter from '@/Components/MarketingFooter.vue'

type Article = {
    id: number
    title: string
    slug: string
    excerpt: string
    published_at: string | null
}

defineProps<{
    articles: Article[]
}>()

function formatDate(value: string | null): string {
    if (!value) {
        return 'Без даты'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(value))
}
</script>

<template>
    <Head title="Статьи" />

    <div class="min-h-screen bg-[#F9FCFA] font-sans text-[#26332D]">
        <MarketingHeader context-label="Статьи" />

        <main>
            <section class="border-b border-[#DDEBE3] bg-white py-14 sm:py-16">
                <div class="mx-auto max-w-6xl px-5 sm:px-8">
                    <Link href="/" class="text-sm font-bold text-[#24A869] transition hover:text-[#1D9059]">← На главную</Link>
                    <div class="mt-6 max-w-3xl">
                        <h1 class="text-4xl font-extrabold leading-tight text-[#26332D] sm:text-5xl">Статьи</h1>
                        <p class="mt-5 text-base leading-7 text-[#738479]">
                            Материалы о мониторинге сайтов, SSL-сертификатах, доменах, HTTP-проверках и работе с инцидентами.
                        </p>
                    </div>
                </div>
            </section>

            <section class="py-12 sm:py-14">
                <div class="mx-auto max-w-6xl px-5 sm:px-8">
                    <div v-if="articles.length" class="grid gap-5 md:grid-cols-2">
                        <Link
                            v-for="article in articles"
                            :key="article.id"
                            :href="`/articles/${article.slug}`"
                            class="rounded-2xl border border-[#DDEBE3] bg-white p-6 shadow-[0_12px_32px_rgba(31,68,49,0.06)] transition hover:-translate-y-0.5 hover:border-[#BEE7CE]"
                        >
                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#24A869]">{{ formatDate(article.published_at) }}</p>
                            <h2 class="mt-3 text-2xl font-extrabold leading-tight text-[#26332D]">{{ article.title }}</h2>
                            <p class="mt-3 text-sm leading-6 text-[#738479]">{{ article.excerpt }}</p>
                            <span class="mt-5 inline-flex text-sm font-extrabold text-[#24A869]">Читать статью</span>
                        </Link>
                    </div>

                    <div v-else class="rounded-2xl border border-[#DDEBE3] bg-white p-10 text-center text-[#738479]">
                        Статьи скоро появятся.
                    </div>
                </div>
            </section>
        </main>

        <MarketingFooter />
    </div>
</template>
