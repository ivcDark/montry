<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import MarketingHeader from '@/Components/MarketingHeader.vue'
import MarketingFooter from '@/Components/MarketingFooter.vue'
import SeoHead from '@/Components/SeoHead.vue'

type Article = {
    id?: number
    title: string
    slug: string
    excerpt: string
    body: string
    published_at: string | null
}

type RelatedArticle = {
    id: number
    title: string
    slug: string
    excerpt: string
    published_at: string | null
}

type BodyBlock =
    | { type: 'heading'; text: string }
    | { type: 'paragraph'; text: string }
    | { type: 'list'; items: string[] }

const props = defineProps<{
    article: Article
    related?: RelatedArticle[]
}>()

const page = usePage<{ appUrl: string }>()
const appUrl = computed(() => page.props.appUrl.replace(/\/$/, ''))

const blocks = computed(() => parseBody(props.article.body))

const jsonLd = computed(() => {
    const articleUrl = `${appUrl.value}/articles/${props.article.slug}`

    return [
        {
            '@context': 'https://schema.org',
            '@type': 'Article',
            headline: props.article.title,
            description: props.article.excerpt,
            datePublished: props.article.published_at,
            inLanguage: 'ru-RU',
            mainEntityOfPage: articleUrl,
            author: {
                '@type': 'Organization',
                name: 'Montry',
                url: `${appUrl.value}/`,
            },
            publisher: {
                '@type': 'Organization',
                name: 'Montry',
                url: `${appUrl.value}/`,
                logo: {
                    '@type': 'ImageObject',
                    url: `${appUrl.value}/images/og-default.png`,
                },
            },
            image: `${appUrl.value}/images/og-default.png`,
        },
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
                {
                    '@type': 'ListItem',
                    position: 3,
                    name: props.article.title,
                    item: articleUrl,
                },
            ],
        },
    ]
})

function parseBody(body: string): BodyBlock[] {
    const lines = body.replace(/\r\n/g, '\n').split('\n')
    const result: BodyBlock[] = []
    let paragraphLines: string[] = []
    let listItems: string[] = []

    const flushParagraph = () => {
        if (paragraphLines.length === 0) {
            return
        }

        result.push({ type: 'paragraph', text: paragraphLines.join(' ').trim() })
        paragraphLines = []
    }

    const flushList = () => {
        if (listItems.length === 0) {
            return
        }

        result.push({ type: 'list', items: [...listItems] })
        listItems = []
    }

    for (const rawLine of lines) {
        const line = rawLine.trim()

        if (line === '') {
            flushParagraph()
            flushList()
            continue
        }

        if (line.startsWith('## ')) {
            flushParagraph()
            flushList()
            result.push({ type: 'heading', text: line.slice(3).trim() })
            continue
        }

        if (line.startsWith('- ')) {
            flushParagraph()
            listItems.push(line.slice(2).trim())
            continue
        }

        flushList()
        paragraphLines.push(line)
    }

    flushParagraph()
    flushList()

    return result
}

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
        :title="article.title"
        :description="article.excerpt"
        :path="`/articles/${article.slug}`"
        og-type="article"
        :json-ld="jsonLd"
    />

    <div class="min-h-screen bg-[#F9FCFA] font-sans text-[#26332D]">
        <MarketingHeader context-label="Статья" />

        <main>
            <article class="mx-auto max-w-3xl px-5 py-12 sm:px-8 sm:py-16">
                <nav class="text-sm font-semibold text-[#738479]" aria-label="Хлебные крошки">
                    <Link href="/" class="transition hover:text-[#24A869]">Главная</Link>
                    <span class="mx-2 text-[#B8C9BF]">/</span>
                    <Link href="/articles" class="transition hover:text-[#24A869]">Статьи</Link>
                    <span class="mx-2 text-[#B8C9BF]">/</span>
                    <span class="text-[#26332D]">{{ article.title }}</span>
                </nav>

                <time
                    v-if="article.published_at"
                    class="mt-8 block text-xs font-bold uppercase tracking-[0.14em] text-[#24A869]"
                    :datetime="article.published_at"
                >
                    {{ formatDate(article.published_at) }}
                </time>
                <p v-else class="mt-8 text-xs font-bold uppercase tracking-[0.14em] text-[#24A869]">Без даты</p>

                <h1 class="mt-4 text-4xl font-extrabold leading-tight text-[#26332D] sm:text-5xl">{{ article.title }}</h1>
                <p class="mt-5 text-lg leading-8 text-[#52645A]">{{ article.excerpt }}</p>

                <div class="mt-10 rounded-2xl border border-[#DDEBE3] bg-white p-6 shadow-[0_12px_32px_rgba(31,68,49,0.06)] sm:p-8">
                    <template v-for="(block, index) in blocks" :key="`${block.type}-${index}`">
                        <h2
                            v-if="block.type === 'heading'"
                            class="mb-4 mt-8 text-2xl font-bold text-[#26332D] first:mt-0"
                        >
                            {{ block.text }}
                        </h2>
                        <p
                            v-else-if="block.type === 'paragraph'"
                            class="mb-5 text-base leading-8 text-[#52645A] last:mb-0"
                        >
                            {{ block.text }}
                        </p>
                        <ul
                            v-else
                            class="mb-5 list-disc space-y-2 pl-5 text-base leading-8 text-[#52645A] last:mb-0"
                        >
                            <li v-for="item in block.items" :key="item">{{ item }}</li>
                        </ul>
                    </template>
                </div>

                <div class="mt-10 rounded-2xl border border-[#BEE7CE] bg-[#E9F8EF] p-6 sm:p-8">
                    <h2 class="text-2xl font-bold text-[#26332D]">Попробуйте мониторинг сайтов в Montry</h2>
                    <p class="mt-3 text-sm leading-6 text-[#52645A]">
                        Добавьте сайт бесплатно: проверка доступности, мониторинг SSL и срока домена, уведомления о падении сайта.
                    </p>
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                        <Link
                            href="/register"
                            class="inline-flex h-11 items-center justify-center rounded-xl bg-[#24A869] px-5 text-sm font-semibold text-white transition hover:bg-[#1D9059]"
                        >
                            Попробовать Montry
                        </Link>
                        <Link
                            href="/#pricing"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-[#D4E3DA] bg-white px-5 text-sm font-semibold text-[#26332D] transition hover:border-[#B8D0C2]"
                        >
                            Тарифы
                        </Link>
                    </div>
                </div>

                <section v-if="related?.length" class="mt-12">
                    <h2 class="text-2xl font-bold text-[#26332D]">Читайте также</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <Link
                            v-for="item in related"
                            :key="item.slug"
                            :href="`/articles/${item.slug}`"
                            class="rounded-2xl border border-[#DDEBE3] bg-white p-4 shadow-[0_10px_28px_rgba(31,68,49,0.05)] transition hover:-translate-y-0.5 hover:border-[#BEE7CE]"
                        >
                            <h3 class="text-base font-semibold text-[#26332D]">{{ item.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[#738479]">{{ item.excerpt }}</p>
                        </Link>
                    </div>
                </section>
            </article>
        </main>

        <MarketingFooter />
    </div>
</template>
