<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import MarketingHeader from '@/Components/MarketingHeader.vue'
import MarketingFooter from '@/Components/MarketingFooter.vue'

type Article = {
    title: string
    slug: string
    excerpt: string
    body: string
    published_at: string | null
}

const props = defineProps<{
    article: Article
}>()

const paragraphs = props.article.body.split(/\n+/).filter((paragraph) => paragraph.trim() !== '')

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
    <Head :title="article.title" />

    <div class="min-h-screen bg-[#F9FCFA] font-sans text-[#26332D]">
        <MarketingHeader context-label="Статья" />

        <main>
            <article class="mx-auto max-w-3xl px-5 py-12 sm:px-8 sm:py-16">
                <Link href="/articles" class="text-sm font-bold text-[#24A869] transition hover:text-[#1D9059]">← Все статьи</Link>
                <p class="mt-8 text-xs font-bold uppercase tracking-[0.14em] text-[#24A869]">{{ formatDate(article.published_at) }}</p>
                <h1 class="mt-4 text-4xl font-extrabold leading-tight text-[#26332D] sm:text-5xl">{{ article.title }}</h1>
                <p class="mt-5 text-lg leading-8 text-[#52645A]">{{ article.excerpt }}</p>

                <div class="mt-10 rounded-2xl border border-[#DDEBE3] bg-white p-6 shadow-[0_12px_32px_rgba(31,68,49,0.06)] sm:p-8">
                    <p
                        v-for="paragraph in paragraphs"
                        :key="paragraph"
                        class="mb-5 text-base leading-8 text-[#52645A] last:mb-0"
                    >
                        {{ paragraph }}
                    </p>
                </div>
            </article>
        </main>

        <MarketingFooter />
    </div>
</template>
