<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ExternalLink, FilePlus2, Globe2, Pencil, Trash2 } from '@lucide/vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Organization = { id: string | number; name: string }
type StatusPage = {
    id: number
    name: string
    slug: string
    description: string | null
    is_published: boolean
    accent_color: string
    monitors_count: number
    updated_at: string | null
}

defineProps<{
    organization: Organization
    statusPages: StatusPage[]
}>()

function removePage(statusPage: StatusPage): void {
    if (!window.confirm(`Удалить публичную страницу «${statusPage.name}»?`)) return
    router.delete(`/status-pages/${statusPage.id}`)
}

function formatDate(value: string | null): string {
    if (!value) return '—'
    return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}
</script>

<template>
    <Head title="Публичные страницы" />

    <DashboardLayout
        :organization="organization"
        active-item="status-pages"
        title="Публичные страницы"
        subtitle="Статус сервисов для клиентов и посетителей"
    >
        <template #actions>
            <Link
                href="/status-pages/create"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#2FA568] px-4 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(47,165,104,0.18)] transition hover:bg-[#248755]"
            >
                <FilePlus2 class="h-4 w-4" :stroke-width="2" />
                Создать страницу
            </Link>
        </template>

        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8">
            <section v-if="statusPages.length" class="overflow-hidden rounded-2xl border border-[#DDEBE3] bg-white shadow-[0_10px_28px_rgba(23,59,42,0.05)]">
                <div class="border-b border-[#DDEBE3] px-5 py-5 sm:px-6">
                    <h1 class="text-xl font-semibold text-[#173B2A]">Ваши status pages</h1>
                    <p class="mt-1 text-sm leading-6 text-[#6A7A70]">Публикуйте только те мониторинги, которые должны видеть клиенты.</p>
                </div>

                <div class="divide-y divide-[#DDEBE3]">
                    <article v-for="statusPage in statusPages" :key="statusPage.id" class="p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                <span
                                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white"
                                    :style="{ backgroundColor: statusPage.accent_color }"
                                >
                                    <Globe2 class="h-5 w-5" :stroke-width="2" />
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-lg font-semibold text-[#173B2A]">{{ statusPage.name }}</h2>
                                        <span
                                            class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="statusPage.is_published ? 'bg-[#E9F8EF] text-[#168A5A]' : 'bg-[#F3F4F6] text-[#667085]'"
                                        >
                                            {{ statusPage.is_published ? 'Опубликована' : 'Черновик' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 truncate text-sm font-medium text-[#1E8C57]">/status/{{ statusPage.slug }}</p>
                                    <p v-if="statusPage.description" class="mt-2 line-clamp-2 max-w-2xl text-sm leading-6 text-[#6A7A70]">{{ statusPage.description }}</p>
                                    <p class="mt-2 text-xs text-[#8A9A91]">
                                        Мониторов: {{ statusPage.monitors_count }} · Изменена {{ formatDate(statusPage.updated_at) }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                <a
                                    v-if="statusPage.is_published"
                                    :href="`/status/${statusPage.slug}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-[#DDEBE3] px-3.5 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                                >
                                    <ExternalLink class="h-4 w-4" :stroke-width="2" />
                                    Открыть
                                </a>
                                <Link
                                    :href="`/status-pages/${statusPage.id}/edit`"
                                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-[#DDEBE3] px-3.5 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                                >
                                    <Pencil class="h-4 w-4" :stroke-width="2" />
                                    Изменить
                                </Link>
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-xl border border-[#F3D2D2] text-[#C24141] transition hover:bg-[#FFF5F5]"
                                    aria-label="Удалить страницу"
                                    @click="removePage(statusPage)"
                                >
                                    <Trash2 class="h-4 w-4" :stroke-width="2" />
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section v-else class="rounded-2xl border border-dashed border-[#BFD6C8] bg-white px-6 py-14 text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#E9F8EF] text-[#1E9B5D]">
                    <Globe2 class="h-6 w-6" :stroke-width="2" />
                </span>
                <h1 class="mt-5 text-xl font-semibold text-[#173B2A]">Публичных страниц пока нет</h1>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-[#6A7A70]">
                    Создайте страницу состояния, выберите мониторы и отправьте клиентам постоянную публичную ссылку.
                </p>
                <Link href="/status-pages/create" class="mt-6 inline-flex h-11 items-center gap-2 rounded-xl bg-[#2FA568] px-5 text-sm font-semibold text-white transition hover:bg-[#248755]">
                    <FilePlus2 class="h-4 w-4" :stroke-width="2" />
                    Создать первую страницу
                </Link>
            </section>
        </div>
    </DashboardLayout>
</template>
