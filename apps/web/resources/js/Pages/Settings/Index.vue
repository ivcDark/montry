<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import TariffRestriction from '@/Components/TariffRestriction.vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import {
    Bell,
    CheckCircle2,
    Clipboard,
    Mail,
    MessageCircle,
    Save,
    ShieldCheck,
    UserRound,
} from '@lucide/vue'

type Organization = {
    id: string | number
    name: string
}

type Settings = {
    profile: {
        name: string
        email: string
    }
    telegram: {
        notifications_enabled: boolean
        is_connected: boolean
        username: string | null
        connected_at: string | null
        connection_token: string | null
        bot_username: string | null
        setup_url: string | null
        is_available: boolean
    }
}

const props = defineProps<{
    organization: Organization
    settings: Settings
}>()

const copied = ref(false)

const profileForm = useForm({
    name: props.settings.profile.name,
})

const telegramForm = useForm({
    telegram_notifications_enabled: props.settings.telegram.notifications_enabled,
})

const isTelegramAvailable = computed(() => props.settings.telegram.is_available)

const connectionCommand = computed(() => {
    if (!props.settings.telegram.connection_token) {
        return ''
    }

    return `/start ${props.settings.telegram.connection_token}`
})

const shouldShowTelegramConnect = computed(() => (
    telegramForm.telegram_notifications_enabled
    && !props.settings.telegram.is_connected
))

const profileInitial = computed(() => (
    (profileForm.name || props.settings.profile.email || 'M').trim().slice(0, 1).toUpperCase()
))

const profileHasChanges = computed(() => profileForm.name !== props.settings.profile.name)

const telegramConnectedMeta = computed(() => {
    if (!props.settings.telegram.is_connected) {
        return 'Чат еще не привязан'
    }

    if (props.settings.telegram.connected_at) {
        return `Подключен: ${props.settings.telegram.connected_at}`
    }

    return 'Чат подключен'
})

const telegramStatusLabel = computed(() => {
    if (!isTelegramAvailable.value) {
        return 'Недоступно'
    }

    if (props.settings.telegram.is_connected && telegramForm.telegram_notifications_enabled) {
        return 'Подключен'
    }

    if (props.settings.telegram.is_connected && !telegramForm.telegram_notifications_enabled) {
        return 'Подключен, выключен'
    }

    if (telegramForm.processing) {
        return 'Сохраняем'
    }

    if (telegramForm.telegram_notifications_enabled) {
        return 'Ожидает подключения'
    }

    return 'Выключен'
})

const telegramStatusClass = computed(() => {
    if (!isTelegramAvailable.value) {
        return 'bg-[#E5E7EB] text-[#6B7280]'
    }

    if (props.settings.telegram.is_connected && telegramForm.telegram_notifications_enabled) {
        return 'bg-[#ECFDF3] text-[#16A34A]'
    }

    if (telegramForm.processing || telegramForm.telegram_notifications_enabled) {
        return 'bg-[#FFF7E8] text-[#B45309]'
    }

    return 'bg-[#F1F5F9] text-[#64748B]'
})

const telegramHelperText = computed(() => {
    if (props.settings.telegram.is_connected && telegramForm.telegram_notifications_enabled) {
        return 'Аккаунт Telegram подключен. Уведомления об инцидентах будут приходить в этот чат.'
    }

    if (props.settings.telegram.is_connected && !telegramForm.telegram_notifications_enabled) {
        return 'Telegram уже подключен к аккаунту. Нажмите кнопку сохранения, чтобы отключить отправку уведомлений.'
    }

    if (shouldShowTelegramConnect.value && props.settings.telegram.bot_username) {
        return 'Нажмите «Подтвердить». Montri сохранит настройку и откроет нашего Telegram-бота с командой /start и кодом подключения.'
    }

    if (shouldShowTelegramConnect.value && props.settings.telegram.connection_token) {
        return 'Telegram-бот не настроен для прямого открытия. Отправьте команду подключения боту вручную.'
    }

    if (shouldShowTelegramConnect.value) {
        return 'Telegram-бот не настроен. Укажите TELEGRAM_BOT_USERNAME в .env, чтобы открыть бота из настроек.'
    }

    if (telegramForm.processing) {
        return 'Сохраняем настройку и готовим подключение к Telegram.'
    }

    return 'Включите Telegram-уведомления и нажмите «Подтвердить», чтобы перейти в бота Montri.'
})

const telegramSubmitLabel = computed(() => {
    if (telegramForm.processing) {
        return 'Сохраняем'
    }

    if (shouldShowTelegramConnect.value) {
        return 'Подтвердить'
    }

    if (!telegramForm.telegram_notifications_enabled && props.settings.telegram.notifications_enabled) {
        return 'Отключить уведомления'
    }

    return 'Сохранить настройки'
})

function submitProfile(): void {
    profileForm.patch('/settings/profile', {
        preserveScroll: true,
    })
}

function submitTelegramSettings(): void {
    if (!isTelegramAvailable.value) {
        return
    }

    const options = {
        preserveScroll: true,
        onError: () => {
            telegramForm.telegram_notifications_enabled = props.settings.telegram.notifications_enabled
        },
    }

    if (shouldShowTelegramConnect.value) {
        telegramForm.post('/settings/telegram/confirm', options)

        return
    }

    telegramForm.patch('/settings/telegram', options)
}

async function copyConnectionCommand(): Promise<void> {
    if (!connectionCommand.value) {
        return
    }

    await navigator.clipboard.writeText(connectionCommand.value)
    copied.value = true
    window.setTimeout(() => {
        copied.value = false
    }, 1800)
}
</script>

<template>
    <Head title="Настройки" />

    <DashboardLayout
        :organization="organization"
        active-item="settings"
        title="Настройки"
        subtitle="Профиль и каналы уведомлений"
    >
        <section class="mx-auto grid max-w-7xl gap-6 px-5 py-7 sm:px-8">
            <div class="grid gap-4 rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_18px_60px_rgba(23,59,42,0.06)] sm:p-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-center">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal text-[#173B2A] sm:text-3xl">Настройки</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[#6A7A70]">
                        Профиль, доступ и каналы уведомлений для рабочего пространства {{ organization.name }}.
                    </p>
                </div>

                <div class="grid gap-3 rounded-2xl border border-[#E2ECE6] bg-[#F6FBF8] p-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white text-[#1E9B5D] ring-1 ring-[#DDEBE3]">
                            <ShieldCheck class="h-5 w-5" :stroke-width="2" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase text-[#8A9A91]">Аккаунт</p>
                            <p class="truncate text-sm font-semibold text-[#26332D]">{{ settings.profile.email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white text-[#E08600] ring-1 ring-[#DDEBE3]">
                            <Bell class="h-5 w-5" :stroke-width="2" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase text-[#8A9A91]">Telegram</p>
                            <p class="truncate text-sm font-semibold text-[#26332D]">{{ telegramStatusLabel }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(320px,0.92fr)_minmax(0,1.35fr)]">
                <section class="rounded-3xl border border-[#DDEBE3] bg-white p-5 shadow-[0_18px_60px_rgba(23,59,42,0.05)] sm:p-6">
                    <div class="flex items-start gap-4">
                        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[#E9F8EF] text-xl font-semibold text-[#173B2A] ring-1 ring-[#BEE7CE]">
                            {{ profileInitial }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <UserRound class="h-4 w-4 text-[#1E9B5D]" :stroke-width="2" />
                                <p class="text-sm font-semibold text-[#1E9B5D]">Профиль</p>
                            </div>
                            <h2 class="mt-2 text-xl font-semibold text-[#173B2A]">Личные данные</h2>
                            <p class="mt-2 break-words text-sm leading-6 text-[#6A7A70]">{{ settings.profile.email }}</p>
                        </div>
                    </div>

                    <form class="mt-7 grid gap-5" @submit.prevent="submitProfile">
                        <label class="grid gap-2">
                            <span class="text-sm font-semibold text-[#26332D]">Отображаемое имя</span>
                            <input
                                v-model="profileForm.name"
                                type="text"
                                autocomplete="name"
                                class="h-12 rounded-2xl border border-[#CFE1D7] bg-[#FBFEFC] px-4 text-sm font-semibold text-[#173B2A] outline-none transition placeholder:text-[#8A9A91] focus:border-[#1E9B5D] focus:bg-white focus:ring-4 focus:ring-[#1E9B5D]/10"
                            >
                            <span v-if="profileForm.errors.name" class="text-sm font-semibold text-[#EF4444]">
                                {{ profileForm.errors.name }}
                            </span>
                        </label>

                        <div class="rounded-2xl border border-[#E2ECE6] bg-[#F6FBF8] p-4">
                            <div class="flex items-start gap-3">
                                <Mail class="mt-0.5 h-5 w-5 shrink-0 text-[#6A7A70]" :stroke-width="2" />
                                <div>
                                    <p class="text-sm font-semibold text-[#26332D]">Email для входа</p>
                                    <p class="mt-1 break-all text-sm leading-6 text-[#6A7A70]">{{ settings.profile.email }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm font-medium text-[#6A7A70]">
                                {{ profileHasChanges ? 'Есть несохраненные изменения' : 'Профиль актуален' }}
                            </p>
                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-[#173B2A] px-5 text-sm font-semibold text-white transition hover:bg-[#0F2E20] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="profileForm.processing"
                            >
                                <Save class="h-4 w-4" :stroke-width="2" />
                                Сохранить
                            </button>
                        </div>
                    </form>
                </section>

                <section
                    class="relative overflow-hidden rounded-3xl border p-5 shadow-[0_18px_60px_rgba(23,59,42,0.05)] sm:p-6"
                    :class="isTelegramAvailable ? 'border-[#DDEBE3] bg-white' : 'border-[#DDEBE3] bg-[#EEF4F0]'"
                >
                    <div :class="!isTelegramAvailable ? 'pointer-events-none select-none opacity-35 grayscale' : ''">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-[#E9F8EF] text-[#1E9B5D] ring-1 ring-[#BEE7CE]">
                                        <MessageCircle class="h-5 w-5" :stroke-width="2" />
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-[#1E9B5D]">Канал уведомлений</p>
                                        <h2 class="mt-1 text-xl font-semibold text-[#173B2A]">Telegram</h2>
                                    </div>
                                </div>
                                <p class="mt-4 max-w-2xl text-sm leading-6 text-[#6A7A70]">
                                    Подключите личный чат, чтобы получать сообщения об открытии и восстановлении инцидентов без лишнего шума.
                                </p>
                            </div>

                            <span class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-black/5" :class="telegramStatusClass">
                                <CheckCircle2 v-if="settings.telegram.is_connected && telegramForm.telegram_notifications_enabled" class="h-3.5 w-3.5" :stroke-width="2.5" />
                                {{ telegramStatusLabel }}
                            </span>
                        </div>

                        <form class="mt-7 grid gap-5" @submit.prevent="submitTelegramSettings">
                            <label class="flex cursor-pointer flex-col gap-4 rounded-2xl border border-[#E2ECE6] bg-[#F6FBF8] p-4 sm:flex-row sm:items-center sm:justify-between">
                                <span>
                                    <span class="block text-sm font-semibold text-[#173B2A]">Получать уведомления в Telegram</span>
                                    <span class="mt-1 block text-sm leading-6 text-[#6A7A70]">Сначала включите канал, затем подтвердите привязку через бота Montry.</span>
                                </span>
                                <input
                                    v-model="telegramForm.telegram_notifications_enabled"
                                    type="checkbox"
                                    class="sr-only"
                                    :disabled="telegramForm.processing || !isTelegramAvailable"
                                >
                                <span
                                    class="relative h-7 w-12 shrink-0 rounded-full transition"
                                    :class="telegramForm.telegram_notifications_enabled ? 'bg-[#1E9B5D]' : 'bg-[#CFE1D7]'"
                                >
                                    <span
                                        class="absolute top-1 h-5 w-5 rounded-full bg-white shadow transition"
                                        :class="telegramForm.telegram_notifications_enabled ? 'left-6' : 'left-1'"
                                    />
                                </span>
                            </label>

                            <span v-if="telegramForm.errors.telegram_notifications_enabled" class="text-sm font-semibold text-[#EF4444]">
                                {{ telegramForm.errors.telegram_notifications_enabled }}
                            </span>

                            <div class="grid gap-4 rounded-2xl border border-[#E2ECE6] bg-white p-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-[#173B2A]">Состояние подключения</p>
                                        <p class="mt-1 max-w-2xl text-sm leading-6 text-[#6A7A70]">{{ telegramHelperText }}</p>
                                        <p v-if="settings.telegram.username" class="mt-3 text-sm font-semibold text-[#26332D]">
                                            Telegram: {{ settings.telegram.username }}
                                        </p>
                                    </div>
                                    <p class="rounded-full bg-[#F6FBF8] px-3 py-1 text-xs font-semibold text-[#6A7A70]">
                                        {{ telegramConnectedMeta }}
                                    </p>
                                </div>

                                <div v-if="shouldShowTelegramConnect && connectionCommand && !settings.telegram.bot_username" class="grid gap-2">
                                    <span class="text-sm font-semibold text-[#26332D]">Команда для бота</span>
                                    <div class="flex flex-col gap-3 sm:flex-row">
                                        <code class="min-h-11 flex-1 rounded-2xl bg-[#F3F8F5] px-4 py-3 text-sm font-semibold text-[#173B2A] break-all ring-1 ring-[#DDEBE3]">
                                            {{ connectionCommand }}
                                        </code>
                                        <button
                                            type="button"
                                            class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-[#CFE1D7] bg-white px-4 text-sm font-semibold text-[#26332D] transition hover:border-[#9BC9AE] hover:text-[#173B2A]"
                                            @click="copyConnectionCommand"
                                        >
                                            <Clipboard class="h-4 w-4" :stroke-width="2" />
                                            {{ copied ? 'Скопировано' : 'Скопировать' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm font-medium text-[#6A7A70]">
                                    Уведомления приходят только по важным событиям: инцидент открыт или восстановлен.
                                </p>
                                <button
                                    type="submit"
                                    class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl bg-[#1E9B5D] px-5 text-sm font-semibold text-white transition hover:bg-[#177B4A] disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="telegramForm.processing || !isTelegramAvailable"
                                >
                                    <Save class="h-4 w-4" :stroke-width="2" />
                                    {{ telegramSubmitLabel }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <div
                        v-if="!isTelegramAvailable"
                        class="absolute inset-0 flex items-center justify-center px-6 text-center"
                        aria-hidden="true"
                    >
                        <TariffRestriction action="Подключить Telegram" overlay />
                    </div>
                </section>
            </div>
        </section>
    </DashboardLayout>
</template>
