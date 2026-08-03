<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import MarketingHeader from '@/Components/MarketingHeader.vue'
import MarketingFooter from '@/Components/MarketingFooter.vue'
import SeoHead from '@/Components/SeoHead.vue'

type Article = {
    id: number
    title: string
    slug: string
    excerpt: string
    published_at: string | null
}

const props = defineProps<{
    articles: Article[]
}>()

const page = usePage<{ appUrl: string }>()
const appUrl = computed(() => page.props.appUrl.replace(/\/$/, ''))

const jsonLd = computed(() => ([
    {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            {
                '@type': 'ListItem',
                position: 1,
                name: 'Главная',
                item: `${appUrl.value}/`,
            },
            {
                '@type': 'ListItem',
                position: 2,
                name: 'Статьи',
                item: `${appUrl.value}/articles`,
            },
        ],
    },
    {
        '@context': 'https://schema.org',
        '@type': 'CollectionPage',
        name: 'Статьи о мониторинге сайтов',
        url: `${appUrl.value}/articles`,
        description: 'Материалы о мониторинге сайтов, SSL, доменах, уведомлениях о падении сайта и работе веб-студий.',
        mainEntity: {
            '@type': 'ItemList',
            itemListElement: props.articles.map((article, index) => ({
                '@type': 'ListItem',
                position: index + 1,
                name: article.title,
                url: `${appUrl.value}/articles/${article.slug}`,
            })),
        },
    },
]))

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
    <SeoHead
        title="Статьи о мониторинге сайтов"
        description="Статьи о мониторинге сайтов онлайн, проверке доступности, SSL, сроке домена, уведомлениях о падении сайта и контроле клиентских проектов веб-студии."
        path="/articles"
        :json-ld="jsonLd"
    />

    <div class="min-h-screen bg-[#F9FCFA] font-sans text-[#26332D]">
        <MarketingHeader context-label="Статьи" />

        <main>
            <section class="border-b border-[#DDEBE3] bg-white py-14 sm:py-16">
                <div class="mx-auto max-w-6xl px-5 sm:px-8">
                    <nav class="text-sm font-semibold text-[#738479]" aria-label="Хлебные крошки">
                        <Link href="/" class="transition hover:text-[#24A869]">Главная</Link>
                        <span class="mx-2 text-[#B8C9BF]">/</span>
                        <span class="text-[#26332D]">Статьи</span>
                    </nav>

                    <div class="mt-6 max-w-3xl">
                        <h1 class="text-4xl font-extrabold leading-tight text-[#26332D] sm:text-5xl">Статьи о мониторинге сайтов</h1>
                        <p class="mt-5 text-base leading-7 text-[#738479]">
                            Практичные материалы о мониторинге сайтов онлайн: проверка доступности, мониторинг SSL и срока домена,
                            уведомления о падении сайта, инциденты и контроль клиентских проектов для веб-студий.
                        </p>
                    </div>
                </div>
            </section>

            <section class="py-12 sm:py-14">
                <div class="mx-auto grid max-w-6xl gap-4 px-5 sm:px-8 md:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="article in articles"
                        :key="article.slug"
                        :href="`/articles/${article.slug}`"
                        class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(31,68,49,0.05)] transition hover:-translate-y-0.5 hover:border-[#BEE7CE]"
                    >
                        <p class="text-xs font-semibold text-[#24A869]">{{ formatDate(article.published_at) }}</p>
                        <h2 class="mt-3 text-lg font-semibold text-[#26332D]">{{ article.title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-[#738479]">{{ article.excerpt }}</p>
                    </Link>
                </div>
            </section>
        </main>

        <MarketingFooter />
    </div>
</template>
