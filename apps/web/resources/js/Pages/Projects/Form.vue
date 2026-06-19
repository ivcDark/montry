<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { AlertTriangle, FolderKanban, Search } from '@lucide/vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Organization = { id: string | number; name: string }
type Project = { id: string | number; name: string; comment: string | null; is_default: boolean; resource_ids: number[] }
type Resource = { id: number; name: string; target: string; host: string | null; project_id: number; project_name: string | null }

const props = defineProps<{
    organization: Organization
    project: Project | null
    resources: Resource[]
}>()

const isEditing = computed(() => props.project !== null)
const search = ref('')
const form = useForm({
    name: props.project?.name ?? '',
    comment: props.project?.comment ?? '',
    resource_ids: props.project?.resource_ids ?? [],
})

const filteredResources = computed(() => {
    const query = search.value.trim().toLowerCase()
    if (!query) return props.resources

    return props.resources.filter((resource) => [resource.name, resource.host, resource.target, resource.project_name].filter(Boolean).join(' ').toLowerCase().includes(query))
})

const movedResources = computed(() => props.resources.filter((resource) =>
    form.resource_ids.includes(resource.id)
    && resource.project_id !== Number(props.project?.id ?? 0),
))

function toggleResource(id: number): void {
    if (props.project?.is_default && props.project.resource_ids.includes(id)) return

    form.resource_ids = form.resource_ids.includes(id)
        ? form.resource_ids.filter((resourceId) => resourceId !== id)
        : [...form.resource_ids, id]
}

function submit(): void {
    if (props.project) {
        form.put(`/projects/${props.project.id}`)
        return
    }

    form.post('/projects')
}
</script>

<template>
    <Head :title="isEditing ? 'Редактировать проект' : 'Создать проект'" />

    <DashboardLayout
        :organization="organization"
        active-item="projects"
        :title="isEditing ? 'Редактировать проект' : 'Создать проект'"
        subtitle="Название, комментарий и сайты внутри проекта"
    >
        <div class="mx-auto max-w-4xl px-5 py-8 sm:px-8">
            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(23,59,42,0.05)] sm:p-7">
                    <div class="flex items-start gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#E9F8EF] text-[#1E9B5D]">
                            <FolderKanban class="h-5 w-5" :stroke-width="2" />
                        </span>
                        <div>
                            <h2 class="text-xl font-semibold text-[#173B2A]">Основная информация</h2>
                            <p class="mt-1 text-sm leading-6 text-[#6A7A70]">Проект служит папкой для объединения сайтов одного клиента или направления.</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-5">
                        <label class="block">
                            <span class="text-sm font-semibold text-[#26332D]">Название проекта</span>
                            <input
                                v-model="form.name"
                                type="text"
                                maxlength="255"
                                autofocus
                                class="mt-2 h-11 w-full rounded-xl border border-[#DDEBE3] px-4 text-sm text-[#26332D] outline-none transition focus:border-[#2FA568] focus:ring-2 focus:ring-[#2FA568]/15"
                                placeholder="Например, Клиент — Альфа"
                            >
                            <span v-if="form.errors.name" class="mt-2 block text-sm text-[#D93636]">{{ form.errors.name }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-[#26332D]">Комментарий <span class="font-normal text-[#8A9A91]">необязательно</span></span>
                            <textarea
                                v-model="form.comment"
                                rows="4"
                                maxlength="5000"
                                class="mt-2 w-full resize-y rounded-xl border border-[#DDEBE3] px-4 py-3 text-sm leading-6 text-[#26332D] outline-none transition focus:border-[#2FA568] focus:ring-2 focus:ring-[#2FA568]/15"
                                placeholder="Контакты клиента, особенности проекта или внутренние заметки"
                            />
                            <span v-if="form.errors.comment" class="mt-2 block text-sm text-[#D93636]">{{ form.errors.comment }}</span>
                        </label>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#DDEBE3] bg-white p-5 shadow-[0_10px_28px_rgba(23,59,42,0.05)] sm:p-7">
                    <div>
                        <h2 class="text-xl font-semibold text-[#173B2A]">Сайты проекта</h2>
                        <p class="mt-1 text-sm leading-6 text-[#6A7A70]">
                            Выберите сайты, которые должны находиться в этой папке. Можно сохранить проект без сайтов.
                        </p>
                    </div>

                    <div v-if="resources.length" class="mt-5">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A9A91]" :stroke-width="2" />
                            <input
                                v-model="search"
                                type="search"
                                class="h-10 w-full rounded-xl border border-[#DDEBE3] bg-[#FAFCFB] pl-9 pr-4 text-sm outline-none transition focus:border-[#2FA568] focus:bg-white focus:ring-2 focus:ring-[#2FA568]/15"
                                placeholder="Найти сайт"
                            >
                        </div>

                        <div class="mt-3 max-h-80 overflow-y-auto rounded-xl border border-[#DDEBE3]">
                            <label
                                v-for="resource in filteredResources"
                                :key="resource.id"
                                class="flex cursor-pointer items-start gap-3 border-b border-[#EAF1ED] px-4 py-3 last:border-b-0 hover:bg-[#FAFCFB]"
                                :class="project?.is_default && project.resource_ids.includes(resource.id) ? 'cursor-not-allowed opacity-75' : ''"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-[#B8D0C2] text-[#2FA568] focus:ring-[#2FA568]/25"
                                    :checked="form.resource_ids.includes(resource.id)"
                                    :disabled="project?.is_default && project.resource_ids.includes(resource.id)"
                                    @change="toggleResource(resource.id)"
                                >
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-[#26332D]">{{ resource.name }}</span>
                                    <span class="mt-0.5 block truncate text-xs text-[#6A7A70]">{{ resource.host || resource.target }}</span>
                                </span>
                                <span class="shrink-0 rounded-full bg-[#F3F8F5] px-2.5 py-1 text-[11px] font-medium text-[#52645A]">{{ resource.project_name || 'Без проекта' }}</span>
                            </label>
                        </div>

                        <div v-if="movedResources.length" class="mt-2 flex items-start gap-2 rounded-lg border border-[#F2D49D] bg-[#FFF9ED] px-3 py-2 text-xs leading-5 text-[#8A5A12]">
                            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" :stroke-width="2" />
                            <p>
                                <span class="font-semibold">Будут перенесены:</span>
                                {{ movedResources.map((resource) => `${resource.name} из «${resource.project_name || 'Без проекта'}»`).join('; ') }}.
                            </p>
                        </div>
                    </div>

                    <div v-else class="mt-5 rounded-xl border border-dashed border-[#CFE1D7] bg-[#F8FCFA] p-6 text-center text-sm text-[#6A7A70]">
                        В организации пока нет сайтов. Проект можно создать пустым и добавить сайты позже.
                    </div>
                    <span v-if="form.errors.resource_ids" class="mt-2 block text-sm text-[#D93636]">{{ form.errors.resource_ids }}</span>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link href="/projects" class="inline-flex h-11 items-center justify-center rounded-xl border border-[#DDEBE3] px-5 text-sm font-semibold text-[#52645A] transition hover:bg-[#F6FBF8]">Отмена</Link>
                    <button type="submit" :disabled="form.processing" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#2FA568] px-6 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(47,165,104,0.2)] transition hover:bg-[#248755] disabled:cursor-not-allowed disabled:opacity-60">
                        {{ form.processing ? 'Сохраняем...' : (isEditing ? 'Сохранить изменения' : 'Создать проект') }}
                    </button>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>