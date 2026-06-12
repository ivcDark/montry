<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

type ArticleFormData = {
    title: string
    slug: string
    excerpt: string
    body: string
    is_published: boolean
    published_at: string
    sort_order: string | number
}

type Article = {
    id: number
    title: string
    slug: string
    excerpt: string
    body: string
    is_published: boolean
    published_at: string | null
    sort_order: number
    created_at: string | null
    updated_at: string | null
    form: ArticleFormData
}

type PageProps = {
    flash?: {
        success?: string
    }
}

const props = defineProps<{
    articles: Article[]
}>()

const page = usePage<PageProps>()
const editingArticleId = ref<number | null>(null)

const blankArticleForm = (): ArticleFormData => ({
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    is_published: true,
    published_at: '',
    sort_order: 0,
})

const createForm = useForm<ArticleFormData>(blankArticleForm())
const editForm = useForm<ArticleFormData>(blankArticleForm())

const stats = computed(() => ({
    total: props.articles.length,
    published: props.articles.filter((article) => article.is_published).length,
    hidden: props.articles.filter((article) => !article.is_published).length,
}))

const editingArticle = computed(() => props.articles.find((article) => article.id === editingArticleId.value) ?? null)

function assignFormValues(target: typeof createForm, values: ArticleFormData): void {
    target.title = values.title
    target.slug = values.slug
    target.excerpt = values.excerpt
    target.body = values.body
    target.is_published = values.is_published
    target.published_at = values.published_at ?? ''
    target.sort_order = values.sort_order
}

function storeArticle(): void {
    createForm.post('/admin/articles', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    })
}

function startEdit(article: Article): void {
    editingArticleId.value = article.id
    editForm.clearErrors()
    assignFormValues(editForm, article.form)
}

function cancelEdit(): void {
    editingArticleId.value = null
    editForm.reset()
    editForm.clearErrors()
}

function updateArticle(): void {
    if (editingArticleId.value === null) {
        return
    }

    editForm.patch(`/admin/articles/${editingArticleId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingArticleId.value = null
            editForm.reset()
        },
    })
}

function toggleArticle(article: Article): void {
    router.patch(`/admin/articles/${article.id}/toggle`, {}, {
        preserveScroll: true,
    })
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'не задано'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}
</script>

<template>
    <Head title="Статьи админки" />

    <AdminLayout
        active-item="articles"
        title="Статьи"
        subtitle="Публикация, редактирование и скрытие материалов на сайте"
    >
        <template #actions>
            <a
                href="#create-article"
                class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white transition hover:bg-[#0757D8]"
            >
                Добавить статью
            </a>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <div v-if="page.props.flash?.success" class="mb-5 rounded-2xl border border-[#BBF7D0] bg-[#F0FDF4] px-5 py-4 text-sm font-bold text-[#15803D]">
                {{ page.props.flash.success }}
            </div>

            <section class="grid gap-4 sm:grid-cols-3">
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Всего статей</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ stats.total }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Опубликованы</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#16A34A]">{{ stats.published }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Скрыты</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#64748B]">{{ stats.hidden }}</p>
                </article>
            </section>

            <section class="mt-6 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="border-b border-[#E5E7EB] p-5">
                    <h2 class="text-xl font-extrabold text-[#111827]">Список статей</h2>
                    <p class="mt-1 text-sm text-[#667085]">На сайте видны только опубликованные статьи с наступившей датой публикации.</p>
                </div>

                <div v-if="articles.length" class="overflow-x-auto">
                    <table class="min-w-[980px] w-full border-separate border-spacing-0 text-left text-sm">
                        <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                        <tr>
                            <th class="px-5 py-4">Статья</th>
                            <th class="px-5 py-4">Публикация</th>
                            <th class="px-5 py-4">Статус</th>
                            <th class="px-5 py-4 text-right">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="article in articles" :key="article.id">
                            <td class="border-t border-[#E5E7EB] px-5 py-4 align-top">
                                <p class="font-extrabold text-[#111827]">{{ article.title }}</p>
                                <p class="mt-1 text-xs font-semibold text-[#667085]">/{{ article.slug }} · порядок {{ article.sort_order }}</p>
                                <p class="mt-2 max-w-xl text-sm leading-5 text-[#667085]">{{ article.excerpt }}</p>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 align-top text-[#475467]">
                                {{ formatDate(article.published_at) }}
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 align-top">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-extrabold"
                                    :class="article.is_published ? 'bg-[#ECFDF3] text-[#16A34A]' : 'bg-[#F1F5F9] text-[#64748B]'"
                                >
                                    {{ article.is_published ? 'Отображается' : 'Скрыта' }}
                                </span>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-right align-top">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="h-10 rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                                        @click="startEdit(article)"
                                    >
                                        Редактировать
                                    </button>
                                    <button
                                        type="button"
                                        class="h-10 rounded-xl px-4 text-sm font-extrabold text-white transition"
                                        :class="article.is_published ? 'bg-[#64748B] hover:bg-[#475569]' : 'bg-[#16A34A] hover:bg-[#15803D]'"
                                        @click="toggleArticle(article)"
                                    >
                                        {{ article.is_published ? 'Скрыть' : 'Показать' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-10 text-center text-[#667085]">
                    Статьи пока не созданы.
                </div>
            </section>

            <section v-if="editingArticle" class="mt-6 rounded-3xl border border-[#BFDBFE] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="flex flex-col gap-3 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-[#0F6BFF]">Редактирование статьи</p>
                        <h2 class="mt-1 text-2xl font-extrabold text-[#111827]">{{ editingArticle.title }}</h2>
                    </div>
                    <button
                        type="button"
                        class="h-10 rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                        @click="cancelEdit"
                    >
                        Отмена
                    </button>
                </div>

                <form class="mt-5 grid gap-5" @submit.prevent="updateArticle">
                    <div class="grid gap-4 lg:grid-cols-[1fr_0.7fr_160px]">
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Заголовок</span>
                            <input v-model="editForm.title" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            <span v-if="editForm.errors.title" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.title }}</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Slug</span>
                            <input v-model="editForm.slug" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            <span v-if="editForm.errors.slug" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.slug }}</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Порядок</span>
                            <input v-model="editForm.sort_order" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                        </label>
                    </div>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-[#344054]">Анонс</span>
                        <textarea v-model="editForm.excerpt" rows="3" class="rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" />
                        <span v-if="editForm.errors.excerpt" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.excerpt }}</span>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-[#344054]">Текст</span>
                        <textarea v-model="editForm.body" rows="10" class="rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm leading-6 outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" />
                        <span v-if="editForm.errors.body" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.body }}</span>
                    </label>

                    <div class="grid gap-4 lg:grid-cols-[1fr_220px]">
                        <label class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] px-4 py-3">
                            <input v-model="editForm.is_published" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1]">
                            <span class="text-sm font-bold text-[#344054]">Отображать статью на сайте</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Дата публикации</span>
                            <input v-model="editForm.published_at" type="datetime-local" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="h-11 rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white transition hover:bg-[#0757D8] disabled:cursor-not-allowed disabled:bg-[#CBD5E1]"
                            :disabled="editForm.processing"
                        >
                            Сохранить изменения
                        </button>
                    </div>
                </form>
            </section>

            <section id="create-article" class="mt-6 rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="border-b border-[#E5E7EB] pb-5">
                    <p class="text-sm font-bold text-[#0F6BFF]">Новая статья</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-[#111827]">Добавить статью</h2>
                </div>

                <form class="mt-5 grid gap-5" @submit.prevent="storeArticle">
                    <div class="grid gap-4 lg:grid-cols-[1fr_0.7fr_160px]">
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Заголовок</span>
                            <input v-model="createForm.title" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="Как мониторинг помогает бизнесу">
                            <span v-if="createForm.errors.title" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.title }}</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Slug</span>
                            <input v-model="createForm.slug" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="monitoring-pomogaet-biznesu">
                            <span v-if="createForm.errors.slug" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.slug }}</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Порядок</span>
                            <input v-model="createForm.sort_order" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                        </label>
                    </div>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-[#344054]">Анонс</span>
                        <textarea v-model="createForm.excerpt" rows="3" class="rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="Короткое описание для списка статей" />
                        <span v-if="createForm.errors.excerpt" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.excerpt }}</span>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-[#344054]">Текст</span>
                        <textarea v-model="createForm.body" rows="10" class="rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm leading-6 outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="Основной текст статьи" />
                        <span v-if="createForm.errors.body" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.body }}</span>
                    </label>

                    <div class="grid gap-4 lg:grid-cols-[1fr_220px]">
                        <label class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] px-4 py-3">
                            <input v-model="createForm.is_published" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1]">
                            <span class="text-sm font-bold text-[#344054]">Сразу отображать на сайте</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Дата публикации</span>
                            <input v-model="createForm.published_at" type="datetime-local" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="h-11 rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white transition hover:bg-[#0757D8] disabled:cursor-not-allowed disabled:bg-[#CBD5E1]"
                            :disabled="createForm.processing"
                        >
                            Создать статью
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>
