<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowDown, ArrowUp, ExternalLink, Globe2, Search, Settings2 } from '@lucide/vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Organization = { id: string | number; name: string }
type AvailableMonitor = {
    id: number
    name: string
    resource_name: string
    target: string | null
    type: string
    type_label: string
    enabled: boolean
    status: string
}
type SelectedMonitor = { monitor_id: number; display_name: string | null }
type StatusPage = {
    id: number
    name: string
    slug: string
    description: string | null
    is_published: boolean
    show_incident_history: boolean
    accent_color: string
    monitors: SelectedMonitor[]
}

const props = defineProps<{
    organization: Organization
    statusPage: StatusPage | null
    availableMonitors: AvailableMonitor[]
}>()

const isEditing = computed(() => props.statusPage !== null)
const search = ref('')
const slugTouched = ref(isEditing.value)
const form = useForm({
    name: props.statusPage?.name ?? '',
    slug: props.statusPage?.slug ?? '',
    description: props.statusPage?.description ?? '',
    is_published: props.statusPage?.is_published ?? true,
    show_incident_history: props.statusPage?.show_incident_history ?? true,
    accent_color: props.statusPage?.accent_color ?? '#2FA568',
    monitors: props.statusPage?.monitors ?? [],
})

const filteredMonitors = computed(() => {
    const query = search.value.trim().toLowerCase()
    if (!query) return props.availableMonitors
    return props.availableMonitors.filter((monitor) =>
        [monitor.resource_name, monitor.target, monitor.type_label].filter(Boolean).join(' ').toLowerCase().includes(query),
    )
})
const selectedIds = computed(() => form.monitors.map((monitor) => monitor.monitor_id))
const selectedMonitors = computed(() => form.monitors
    .map((selected) => ({
        selected,
        monitor: props.availableMonitors.find((monitor) => monitor.id === selected.monitor_id),
    }))
    .filter((item) => item.monitor !== undefined))

watch(() => form.name, (name) => {
    if (!slugTouched.value) form.slug = slugify(name)
})

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9а-яё]+/gi, '-')
        .replace(/[а-яё]/gi, '')
        .replace(/^-+|-+$/g, '')
        .slice(0, 100)
}

function toggleMonitor(monitor: AvailableMonitor): void {
    const index = form.monitors.findIndex((selected) => selected.monitor_id === monitor.id)
    if (index >= 0) {
        form.monitors.splice(index, 1)
        return
    }
    form.monitors.push({ monitor_id: monitor.id, display_name: monitor.resource_name })
}

function moveMonitor(index: number, direction: -1 | 1): void {
    const target = index + direction
    if (target < 0 || target >= form.monitors.length) return
    const [item] = form.monitors.splice(index, 1)
    form.monitors.splice(target, 0, item)
}

function submit(): void {
    if (props.statusPage) {
        form.put(`/status-pages/${props.statusPage.id}`)
        return
    }
    form.post('/status-pages')
}
</script>

<template>
    <Head :title="isEditing ? 'Редактировать публичную страницу' : 'Создать публичную страницу'" />

    <DashboardLayout
        :organization="organization"
        active-item="status-pages"
        :title="isEditing ? 'Редактировать публичную страницу' : 'Создать публичную страницу'"
        subtitle="Адрес, оформление и список публичных мониторингов"
    >
        <template v-if="statusPage" #actions>
            <a
                :href="`/status-pages/${statusPage.id}/preview`"
                target="_blank"
                rel="noopener"
                class="inline-flex h-11 items-center gap-2 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
            >
                <ExternalLink class="h-4 w-4" :stroke-width="2" />
                Предпросмотр
            </a>
        </template>

        <div class="mx-auto w-full max-w-5xl px-5 py-8 sm:px-8">
            <form class="grid gap-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(23,59,42,0.05)] sm:p-7">
                    <div class="flex items-start gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#E9F8EF] text-[#1E9B5D]">
                            <Globe2 class="h-5 w-5" :stroke-width="2" />
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-[#173B2A]">Основная информация</h2>
                            <p class="mt-1 text-sm leading-6 text-[#6A7A70]">Название и адрес будут видны всем посетителям страницы.</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5">
                        <label class="block">
                            <span class="text-sm font-semibold text-[#26332D]">Название страницы</span>
                            <input v-model="form.name" type="text" maxlength="255" autofocus class="mt-2 h-11 w-full rounded-xl border border-[#DDEBE3] px-4 text-sm outline-none focus:border-[#2FA568] focus:ring-2 focus:ring-[#2FA568]/15" placeholder="Статус сервисов компании">
                            <span v-if="form.errors.name" class="mt-2 block text-sm text-[#D93636]">{{ form.errors.name }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-[#26332D]">Публичный адрес</span>
                            <div class="mt-2 flex h-11 overflow-hidden rounded-xl border border-[#DDEBE3] focus-within:border-[#2FA568] focus-within:ring-2 focus-within:ring-[#2FA568]/15">
                                <span class="flex items-center border-r border-[#DDEBE3] bg-[#F6FBF8] px-3 text-sm text-[#6A7A70]">/status/</span>
                                <input v-model="form.slug" type="text" maxlength="100" class="min-w-0 flex-1 px-3 text-sm outline-none" placeholder="company-status" @input="slugTouched = true">
                            </div>
                            <p class="mt-2 text-xs text-[#8A9A91]">Латинские буквы, цифры и дефисы. Адрес уникален для всего Montry.</p>
                            <span v-if="form.errors.slug" class="mt-2 block text-sm text-[#D93636]">{{ form.errors.slug }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-[#26332D]">Описание <span class="font-normal text-[#8A9A91]">необязательно</span></span>
                            <textarea v-model="form.description" rows="4" maxlength="2000" class="mt-2 w-full resize-y rounded-xl border border-[#DDEBE3] px-4 py-3 text-sm leading-6 outline-none focus:border-[#2FA568] focus:ring-2 focus:ring-[#2FA568]/15" placeholder="Здесь публикуется актуальное состояние сайтов и сервисов."></textarea>
                            <span v-if="form.errors.description" class="mt-2 block text-sm text-[#D93636]">{{ form.errors.description }}</span>
                        </label>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(23,59,42,0.05)] sm:p-7">
                    <div class="flex items-start gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#E9F8EF] text-[#1E9B5D]">
                            <Settings2 class="h-5 w-5" :stroke-width="2" />
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-[#173B2A]">Публикация и оформление</h2>
                            <p class="mt-1 text-sm leading-6 text-[#6A7A70]">Базовые настройки без custom domain и расширенного брендинга.</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#DDEBE3] p-4">
                            <input v-model="form.is_published" type="checkbox" class="mt-1 h-4 w-4 accent-[#2FA568]">
                            <span>
                                <span class="block text-sm font-semibold text-[#26332D]">Страница опубликована</span>
                                <span class="mt-1 block text-xs leading-5 text-[#6A7A70]">Публичная ссылка доступна без авторизации.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#DDEBE3] p-4">
                            <input v-model="form.show_incident_history" type="checkbox" class="mt-1 h-4 w-4 accent-[#2FA568]">
                            <span>
                                <span class="block text-sm font-semibold text-[#26332D]">Показывать историю инцидентов</span>
                                <span class="mt-1 block text-xs leading-5 text-[#6A7A70]">Открытые и последние инциденты за 90 дней.</span>
                            </span>
                        </label>
                    </div>

                    <label class="mt-4 block max-w-xs">
                        <span class="text-sm font-semibold text-[#26332D]">Акцентный цвет</span>
                        <div class="mt-2 flex h-11 items-center gap-3 rounded-xl border border-[#DDEBE3] px-3">
                            <input v-model="form.accent_color" type="color" class="h-7 w-9 cursor-pointer border-0 bg-transparent p-0">
                            <input v-model="form.accent_color" type="text" maxlength="7" class="min-w-0 flex-1 text-sm font-medium uppercase outline-none">
                        </div>
                        <span v-if="form.errors.accent_color" class="mt-2 block text-sm text-[#D93636]">{{ form.errors.accent_color }}</span>
                    </label>
                </section>

                <section class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(23,59,42,0.05)] sm:p-7">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-[#173B2A]">Мониторы на странице</h2>
                            <p class="mt-1 text-sm leading-6 text-[#6A7A70]">Выберите минимум один монитор. Внутреннее имя можно заменить публичным.</p>
                        </div>
                        <div class="relative w-full sm:max-w-xs">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A9A91]" :stroke-width="2" />
                            <input v-model="search" type="search" class="h-10 w-full rounded-xl border border-[#DDEBE3] pl-9 pr-3 text-sm outline-none focus:border-[#2FA568]" placeholder="Найти монитор">
                        </div>
                    </div>

                    <div class="mt-5 grid gap-5 lg:grid-cols-2">
                        <div>
                            <p class="text-sm font-semibold text-[#26332D]">Доступные</p>
                            <div class="mt-3 max-h-96 overflow-y-auto rounded-xl border border-[#DDEBE3]">
                                <button
                                    v-for="monitor in filteredMonitors"
                                    :key="monitor.id"
                                    type="button"
                                    class="flex w-full items-start gap-3 border-b border-[#E7F0EB] p-3 text-left last:border-b-0 hover:bg-[#F6FBF8]"
                                    @click="toggleMonitor(monitor)"
                                >
                                    <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded border text-xs font-bold" :class="selectedIds.includes(monitor.id) ? 'border-[#2FA568] bg-[#2FA568] text-white' : 'border-[#C8D8CF] text-transparent'">✓</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-[#26332D]">{{ monitor.resource_name }}</span>
                                        <span class="mt-1 block truncate text-xs text-[#6A7A70]">{{ monitor.type_label }} · {{ monitor.target }}</span>
                                    </span>
                                </button>
                                <p v-if="!filteredMonitors.length" class="p-5 text-center text-sm text-[#8A9A91]">Мониторы не найдены.</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-[#26332D]">Порядок на странице</p>
                            <div class="mt-3 grid gap-2">
                                <div v-for="(item, index) in selectedMonitors" :key="item.selected.monitor_id" class="rounded-xl border border-[#DDEBE3] p-3">
                                    <div class="flex items-center gap-2">
                                        <input v-model="item.selected.display_name" type="text" class="h-9 min-w-0 flex-1 rounded-lg border border-[#DDEBE3] px-3 text-sm outline-none focus:border-[#2FA568]" :placeholder="item.monitor?.resource_name">
                                        <button type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEBE3] text-[#6A7A70] disabled:opacity-30" :disabled="index === 0" @click="moveMonitor(index, -1)">
                                            <ArrowUp class="h-4 w-4" :stroke-width="2" />
                                        </button>
                                        <button type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEBE3] text-[#6A7A70] disabled:opacity-30" :disabled="index === selectedMonitors.length - 1" @click="moveMonitor(index, 1)">
                                            <ArrowDown class="h-4 w-4" :stroke-width="2" />
                                        </button>
                                    </div>
                                    <p class="mt-2 text-xs text-[#8A9A91]">{{ item.monitor?.type_label }} · {{ item.monitor?.target }}</p>
                                </div>
                                <p v-if="!selectedMonitors.length" class="rounded-xl border border-dashed border-[#C8D8CF] p-6 text-center text-sm text-[#8A9A91]">Выберите мониторы слева.</p>
                            </div>
                            <span v-if="form.errors.monitors" class="mt-2 block text-sm text-[#D93636]">{{ form.errors.monitors }}</span>
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link href="/status-pages" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#DDEBE3] px-5 text-sm font-semibold text-[#52645A] hover:border-[#B8D0C2]">Отмена</Link>
                    <button type="submit" :disabled="form.processing" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#2FA568] px-5 text-sm font-semibold text-white transition hover:bg-[#248755] disabled:opacity-60">
                        {{ form.processing ? 'Сохраняем…' : (isEditing ? 'Сохранить изменения' : 'Создать страницу') }}
                    </button>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
