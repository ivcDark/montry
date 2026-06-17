<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import BrandMark from '@/Components/BrandMark.vue'
import FlashToast from '@/Components/FlashToast.vue'
import {
    Activity,
    BarChart3,
    BookOpen,
    CreditCard,
    FolderKanban,
    Globe2,
    HelpCircle,
    Lightbulb,
    LifeBuoy,
    LogOut,
    Mail,
    Send,
    Settings,
    X,
} from '@lucide/vue'

type Organization = {
    id: string
    name: string
}

type User = {
    id: number | string
    name: string
    email: string
}

type BillingUsage = {
    current: number
    limit: number | null
}

type BillingSummary = {
    plan: {
        name: string
    } | null
    monitors: BillingUsage
    sites: BillingUsage
}

type PageProps = {
    auth: {
        user: User | null
    }
    flash?: {
        success?: string | null
        error?: string | null
        token?: string | null
    }
    billing?: BillingSummary | null
}

type NavigationItem = {
    key: string
    label: string
    href: string
    icon: typeof Globe2
}

withDefaults(defineProps<{
    organization: Organization
    activeItem: string
    title: string
    subtitle: string
    usageCurrent?: number
    usageLimit?: number
}>(), {
    usageCurrent: 0,
    usageLimit: 50,
})

const navigation: NavigationItem[] = [
    { key: 'sites', label: 'Сайты', href: '/sites', icon: Globe2 },
    { key: 'projects', label: 'Проекты', href: '/projects', icon: FolderKanban },
    { key: 'reports', label: 'Отчеты', href: '/reports', icon: BarChart3 },
    { key: 'settings', label: 'Настройки', href: '/settings', icon: Settings },
]

const page = usePage<PageProps>()
const user = page.props.auth.user
const billingSummary = computed(() => page.props.billing ?? null)
const toastMessage = computed(() => page.props.flash?.error ?? page.props.flash?.success ?? null)
const toastVariant = computed<'success' | 'error'>(() => page.props.flash?.error ? 'error' : 'success')
const supportModalOpen = ref(false)
const ideaModalOpen = ref(false)
const currentYear = new Date().getFullYear()

const planName = computed(() => billingSummary.value?.plan?.name ?? 'Free')
const monitorsCurrent = computed(() => billingSummary.value?.monitors.current ?? 0)
const monitorsLimit = computed(() => billingSummary.value?.monitors.limit ?? null)
const sitesCurrent = computed(() => billingSummary.value?.sites.current ?? 0)
const sitesLimit = computed(() => billingSummary.value?.sites.limit ?? null)
const userInitial = computed(() => (user?.name || user?.email || 'M').trim().slice(0, 1).toUpperCase())
const supportForm = useForm({
    name: user?.name ?? '',
    email: user?.email ?? '',
    subject: '',
    message: '',
    source: 'account',
})
const ideaForm = useForm({
    title: '',
    description: '',
    type: 'feature',
})

function usagePercent(current: number, limit: number | null): number {
    if (limit === null) {
        return 100
    }

    if (limit <= 0) {
        return 0
    }

    return Math.min((current / limit) * 100, 100)
}

function limitLabel(limit: number | null): string {
    return limit === null ? '∞' : String(limit)
}

function openSupportModal(): void {
    supportModalOpen.value = true
}

function closeSupportModal(): void {
    if (supportForm.processing) {
        return
    }

    supportModalOpen.value = false
    supportForm.clearErrors()
}

function submitSupportRequest(): void {
    supportForm.post('/feedback', {
        preserveScroll: true,
        onSuccess: () => {
            supportModalOpen.value = false
            supportForm.reset('subject', 'message')
        },
    })
}

function openIdeaModal(): void {
    ideaModalOpen.value = true
}

function closeIdeaModal(): void {
    if (ideaForm.processing) {
        return
    }

    ideaModalOpen.value = false
    ideaForm.clearErrors()
}

function submitProductIdea(): void {
    ideaForm.post('/product-ideas', {
        preserveScroll: true,
        onSuccess: () => {
            ideaModalOpen.value = false
            ideaForm.reset()
            ideaForm.type = 'feature'
        },
    })
}
</script>

<template>
    <main class="min-h-screen bg-[#F3F8F5] font-sans text-[#26332D] lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
        <FlashToast
            :message="toastMessage"
            :token="page.props.flash?.token"
            :variant="toastVariant"
        />

        <aside class="sticky top-0 hidden h-screen self-start overflow-y-auto border-r border-[#DDEBE3] bg-white px-5 py-7 lg:flex lg:flex-col">
            <div>
                <Link href="/" class="flex items-center gap-3">
                    <BrandMark class="h-9 w-9" />
                    <span class="text-2xl font-bold tracking-normal text-[#173B2A]">Montry</span>
                </Link>

                <nav class="mt-12 grid gap-2" aria-label="Основная навигация">
                    <Link
                        v-for="item in navigation"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition"
                        :class="activeItem === item.key ? 'bg-[#E9F8EF] text-[#173B2A]' : 'text-[#6A7A70] hover:bg-[#F3F8F5] hover:text-[#173B2A]'"
                    >
                        <span
                            class="grid h-8 w-8 place-items-center rounded-xl border"
                            :class="activeItem === item.key ? 'border-[#BEE7CE] bg-[#DDF6E8] text-[#1E9B5D]' : 'border-[#CFE1D7] bg-[#F3F8F5] text-[#8A9A91]'"
                        >
                            <component :is="item.icon" class="h-4 w-4" :stroke-width="2" />
                        </span>
                        {{ item.label }}
                    </Link>
                </nav>
            </div>

            <div class="mt-auto rounded-3xl border border-[#DDEBE3] bg-[#F6FBF8] p-4">
                <p class="text-lg font-semibold text-[#26332D]">Тариф {{ planName }}</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-[#6A7A70]">Сайты</span>
                            <span class="font-medium text-[#26332D]">{{ sitesCurrent }} / {{ limitLabel(sitesLimit) }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-[#E2ECE6]">
                            <div class="h-full rounded-full bg-[#2FA568]" :style="{ width: `${usagePercent(sitesCurrent, sitesLimit)}%` }" />
                        </div>
                    </div>
                </div>

                <Link
                    href="/billing"
                    class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-2xl bg-[#E7F5ED] px-4 text-sm font-medium text-[#173B2A] transition hover:bg-[#D8F0E3]"
                >
                    Управлять
                </Link>

                <button
                    type="button"
                    class="mt-3 inline-flex h-11 w-full items-center justify-center gap-2 rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm font-medium text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                    @click="openSupportModal"
                >
                    <HelpCircle class="h-4 w-4" :stroke-width="2" />
                    Нужна помощь?
                </button>

                <button
                    type="button"
                    class="mt-3 inline-flex h-11 w-full items-center justify-center gap-2 rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm font-medium text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                    @click="openIdeaModal"
                >
                    <Lightbulb class="h-4 w-4" :stroke-width="2" />
                    Есть идея?
                </button>
            </div>
        </aside>

        <section class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-[#DDEBE3] bg-white/95 px-5 py-4 backdrop-blur sm:px-8">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3 lg:hidden">
                            <BrandMark class="h-8 w-8" />
                            <span class="text-xl font-bold text-[#173B2A]">Montry</span>
                        </div>
                        <p class="mt-2 truncate text-sm font-medium text-[#6A7A70] lg:mt-0">{{ organization.name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-[#E9F8EF] px-3 py-1 text-xs font-medium text-[#1E9B5D]">Тариф {{ planName }}</span>
                            <span class="rounded-full bg-[#F3F8F5] px-3 py-1 text-xs font-medium text-[#52645A]">Сайты: {{ sitesCurrent }} / {{ limitLabel(sitesLimit) }}</span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                        <slot name="header-actions" />
                        <slot name="actions" />

                        <div class="hidden h-11 items-center gap-2 rounded-full border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#52645A] sm:flex">
                            <Activity class="h-4 w-4 text-[#E08600]" :stroke-width="2" />
                            Проверки активны
                        </div>

                        <div class="grid h-11 w-11 place-items-center rounded-full border border-[#DDEBE3] bg-[#E9F8EF] text-sm font-semibold text-[#173B2A]">
                            {{ userInitial }}
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="hidden h-10 items-center justify-center gap-2 rounded-xl border border-[#DDEBE3] bg-white px-4 text-sm font-medium text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A] sm:inline-flex"
                        >
                            <LogOut class="h-4 w-4" :stroke-width="2" />
                            Выйти
                        </Link>
                    </div>
                </div>

                <nav class="mx-auto mt-4 flex max-w-7xl gap-2 overflow-x-auto pb-1 lg:hidden" aria-label="Основная навигация">
                    <Link
                        v-for="item in navigation"
                        :key="item.key"
                        :href="item.href"
                        class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="activeItem === item.key ? 'bg-[#E9F8EF] text-[#173B2A]' : 'bg-white text-[#6A7A70] hover:bg-[#F3F8F5]'"
                    >
                        <component :is="item.icon" class="mr-1.5 inline h-4 w-4 align-[-3px]" :stroke-width="2" />
                        {{ item.label }}
                    </Link>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="shrink-0 rounded-full bg-white px-4 py-2 text-sm font-medium text-[#6A7A70] transition hover:bg-[#F3F8F5]"
                    >
                        <LogOut class="mr-1.5 inline h-4 w-4 align-[-3px]" :stroke-width="2" />
                        Выйти
                    </Link>

                    <button
                        type="button"
                        class="shrink-0 rounded-full bg-white px-4 py-2 text-sm font-medium text-[#6A7A70] transition hover:bg-[#F3F8F5]"
                        @click="openSupportModal"
                    >
                        <HelpCircle class="mr-1.5 inline h-4 w-4 align-[-3px]" :stroke-width="2" />
                        Помощь
                    </button>

                    <button
                        type="button"
                        class="shrink-0 rounded-full bg-white px-4 py-2 text-sm font-medium text-[#6A7A70] transition hover:bg-[#F3F8F5]"
                        @click="openIdeaModal"
                    >
                        <Lightbulb class="mr-1.5 inline h-4 w-4 align-[-3px]" :stroke-width="2" />
                        Идея
                    </button>
                </nav>
            </header>

            <slot />

            <footer class="border-t border-[#DDEBE3] bg-white/70 px-5 py-8 sm:px-8">
                <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[1.2fr_2fr]">
                    <div>
                        <div class="flex items-center gap-3">
                            <BrandMark class="h-8 w-8" />
                            <p class="text-lg font-bold text-[#173B2A]">Montry</p>
                        </div>

                        <p class="mt-5 text-sm text-[#6A7A70]">© {{ currentYear }} Montry</p>
                    </div>

                    <div class="grid gap-6 text-sm sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p class="font-bold text-[#26332D]">Кабинет</p>
                            <div class="mt-4 grid gap-3">
                                <Link href="/sites/create" class="inline-flex items-center gap-2 font-medium text-[#52645A] transition hover:text-[#173B2A]">
                                    <Globe2 class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Добавить сайт
                                </Link>
                                <Link href="/billing" class="inline-flex items-center gap-2 font-medium text-[#52645A] transition hover:text-[#173B2A]">
                                    <CreditCard class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Тариф и лимиты
                                </Link>
                                <Link href="/settings" class="inline-flex items-center gap-2 font-medium text-[#52645A] transition hover:text-[#173B2A]">
                                    <Settings class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Настройки
                                </Link>
                            </div>
                        </div>

                        <div>
                            <p class="font-bold text-[#26332D]">Документы</p>
                            <div class="mt-4 grid gap-3">
                                <Link href="/offers" class="inline-flex items-center gap-2 font-medium text-[#52645A] transition hover:text-[#173B2A]">
                                    <BookOpen class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Публичная оферта
                                </Link>
                                <Link href="/user-agreement" class="inline-flex items-center gap-2 font-medium text-[#52645A] transition hover:text-[#173B2A]">
                                    <BookOpen class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Пользовательское соглашение
                                </Link>
                                <Link href="/articles" class="inline-flex items-center gap-2 font-medium text-[#52645A] transition hover:text-[#173B2A]">
                                    <BookOpen class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Статьи
                                </Link>
                            </div>
                        </div>

                        <div>
                            <p class="font-bold text-[#26332D]">Связь</p>
                            <div class="mt-4 grid gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 text-left font-medium text-[#52645A] transition hover:text-[#173B2A]"
                                    @click="openSupportModal"
                                >
                                    <LifeBuoy class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Написать в поддержку
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 text-left font-medium text-[#52645A] transition hover:text-[#173B2A]"
                                    @click="openIdeaModal"
                                >
                                    <Lightbulb class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    Предложить идею
                                </button>
                                <a href="mailto:vladimir@vl-iv.ru" class="inline-flex items-center gap-2 font-medium text-[#52645A] transition hover:text-[#173B2A]">
                                    <Mail class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                    vladimir@vl-iv.ru
                                </a>
                            </div>
                        </div>

                        <div>
                            <p class="font-bold text-[#26332D]">Реквизиты</p>
                            <div class="mt-4 grid gap-2 leading-6 text-[#52645A]">
                                <p class="font-medium">Иванов Владимир Юрьевич</p>
                                <p>Самозанятый</p>
                                <p>ИНН 562503808625</p>
                                <a href="mailto:vladimir@vl-iv.ru" class="font-medium text-[#1E9B5D] transition hover:text-[#173B2A]">
                                    vladimir@vl-iv.ru
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </section>

        <div
            v-if="supportModalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-[#14231B]/45 px-4 py-4 backdrop-blur-sm sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="support-modal-title"
            @click.self="closeSupportModal"
        >
            <form
                class="w-full max-w-xl rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_24px_70px_rgba(23,59,42,0.18)] sm:p-6"
                @submit.prevent="submitSupportRequest"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-[#1E9B5D]">Техподдержка Montry</p>
                        <h2 id="support-modal-title" class="mt-1 text-2xl font-bold text-[#26332D]">Чем помочь?</h2>
                        <p class="mt-2 text-sm leading-6 text-[#6A7A70]">
                            Опишите вопрос или проблему. Мы получим данные аккаунта вместе с обращением.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-[#DDEBE3] bg-white text-[#6A7A70] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                        aria-label="Закрыть форму"
                        @click="closeSupportModal"
                    >
                        <X class="h-5 w-5" :stroke-width="2" />
                    </button>
                </div>

                <div class="mt-5 rounded-2xl bg-[#F6FBF8] px-4 py-3 text-sm text-[#52645A]">
                    <span class="font-semibold text-[#26332D]">{{ supportForm.name }}</span>
                    <span class="mx-2 text-[#9AA9A0]">·</span>
                    <span>{{ supportForm.email }}</span>
                </div>

                <input type="hidden" name="name" v-model="supportForm.name" />
                <input type="hidden" name="email" v-model="supportForm.email" />
                <input type="hidden" name="source" v-model="supportForm.source" />

                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-[#26332D]">Тема</span>
                    <input
                        v-model="supportForm.subject"
                        type="text"
                        class="mt-2 h-12 w-full rounded-2xl border border-[#DDEBE3] bg-white px-4 text-sm text-[#26332D] outline-none transition placeholder:text-[#9AA9A0] focus:border-[#24A869] focus:ring-4 focus:ring-[#24A869]/10"
                        placeholder="Например: не приходит уведомление"
                        :aria-invalid="Boolean(supportForm.errors.subject)"
                    />
                    <span v-if="supportForm.errors.subject" class="mt-2 block text-sm font-semibold text-[#D94B4B]">
                        {{ supportForm.errors.subject }}
                    </span>
                </label>

                <label class="mt-4 block">
                    <span class="text-sm font-semibold text-[#26332D]">Сообщение</span>
                    <textarea
                        v-model="supportForm.message"
                        rows="6"
                        class="mt-2 w-full resize-none rounded-2xl border border-[#DDEBE3] bg-white px-4 py-3 text-sm leading-6 text-[#26332D] outline-none transition placeholder:text-[#9AA9A0] focus:border-[#24A869] focus:ring-4 focus:ring-[#24A869]/10"
                        placeholder="Что случилось, на каком сайте, что вы ожидали увидеть?"
                        :aria-invalid="Boolean(supportForm.errors.message)"
                    />
                    <span v-if="supportForm.errors.message" class="mt-2 block text-sm font-semibold text-[#D94B4B]">
                        {{ supportForm.errors.message }}
                    </span>
                </label>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center rounded-2xl border border-[#DDEBE3] bg-white px-5 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                        @click="closeSupportModal"
                    >
                        Отмена
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-[#24A869] px-5 text-sm font-semibold text-white shadow-[0_14px_30px_rgba(36,168,105,0.22)] transition hover:bg-[#1D9059] disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="supportForm.processing"
                    >
                        <Send class="h-4 w-4" :stroke-width="2" />
                        {{ supportForm.processing ? 'Отправляем...' : 'Отправить' }}
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="ideaModalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-[#14231B]/45 px-4 py-4 backdrop-blur-sm sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="idea-modal-title"
            @click.self="closeIdeaModal"
        >
            <form
                class="w-full max-w-xl rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_24px_70px_rgba(23,59,42,0.18)] sm:p-6"
                @submit.prevent="submitProductIdea"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-[#1E9B5D]">Идея для Montry</p>
                        <h2 id="idea-modal-title" class="mt-1 text-2xl font-bold text-[#26332D]">Что стоит улучшить?</h2>
                        <p class="mt-2 text-sm leading-6 text-[#6A7A70]">
                            Предложите фичу, доработку или сообщите о баге. Мы сохраним идею в базе и вернемся к ней при планировании.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-[#DDEBE3] bg-white text-[#6A7A70] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                        aria-label="Закрыть форму идеи"
                        @click="closeIdeaModal"
                    >
                        <X class="h-5 w-5" :stroke-width="2" />
                    </button>
                </div>

                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-[#26332D]">Короткое название</span>
                    <input
                        v-model="ideaForm.title"
                        type="text"
                        class="mt-2 h-12 w-full rounded-2xl border border-[#DDEBE3] bg-white px-4 text-sm text-[#26332D] outline-none transition placeholder:text-[#9AA9A0] focus:border-[#24A869] focus:ring-4 focus:ring-[#24A869]/10"
                        placeholder="Например: групповые проверки сайтов"
                        :aria-invalid="Boolean(ideaForm.errors.title)"
                    />
                    <span v-if="ideaForm.errors.title" class="mt-2 block text-sm font-semibold text-[#D94B4B]">
                        {{ ideaForm.errors.title }}
                    </span>
                </label>

                <div class="mt-4">
                    <span class="text-sm font-semibold text-[#26332D]">Тип</span>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button
                            v-for="ideaType in [
                                { value: 'feature', label: 'Фича' },
                                { value: 'improvement', label: 'Доработка' },
                                { value: 'bug', label: 'Баг' },
                                { value: 'other', label: 'Другое' },
                            ]"
                            :key="ideaType.value"
                            type="button"
                            class="h-11 rounded-2xl border px-3 text-sm font-semibold transition"
                            :class="ideaForm.type === ideaType.value ? 'border-[#24A869] bg-[#E9F8EF] text-[#173B2A]' : 'border-[#DDEBE3] bg-white text-[#6A7A70] hover:border-[#B8D0C2]'"
                            @click="ideaForm.type = ideaType.value"
                        >
                            {{ ideaType.label }}
                        </button>
                    </div>
                    <span v-if="ideaForm.errors.type" class="mt-2 block text-sm font-semibold text-[#D94B4B]">
                        {{ ideaForm.errors.type }}
                    </span>
                </div>

                <label class="mt-4 block">
                    <span class="text-sm font-semibold text-[#26332D]">Текст идеи</span>
                    <textarea
                        v-model="ideaForm.description"
                        rows="5"
                        class="mt-2 w-full resize-none rounded-2xl border border-[#DDEBE3] bg-white px-4 py-3 text-sm leading-6 text-[#26332D] outline-none transition placeholder:text-[#9AA9A0] focus:border-[#24A869] focus:ring-4 focus:ring-[#24A869]/10"
                        placeholder="Что добавить или изменить? Как это должно работать?"
                        :aria-invalid="Boolean(ideaForm.errors.description)"
                    />
                    <span v-if="ideaForm.errors.description" class="mt-2 block text-sm font-semibold text-[#D94B4B]">
                        {{ ideaForm.errors.description }}
                    </span>
                </label>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center rounded-2xl border border-[#DDEBE3] bg-white px-5 text-sm font-semibold text-[#52645A] transition hover:border-[#B8D0C2] hover:text-[#173B2A]"
                        @click="closeIdeaModal"
                    >
                        Отмена
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-[#24A869] px-5 text-sm font-semibold text-white shadow-[0_14px_30px_rgba(36,168,105,0.22)] transition hover:bg-[#1D9059] disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="ideaForm.processing"
                    >
                        <Send class="h-4 w-4" :stroke-width="2" />
                        {{ ideaForm.processing ? 'Сохраняем...' : 'Сохранить идею' }}
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
