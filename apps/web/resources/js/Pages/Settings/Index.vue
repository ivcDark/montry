<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

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
        <section class="mx-auto grid max-w-5xl gap-6 px-5 py-8 sm:px-8">
            <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-extrabold text-[#12B3A8]">Профиль</p>
                        <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">Личные данные</h2>
                        <p class="mt-2 text-sm leading-6 text-[#667085]">{{ settings.profile.email }}</p>
                    </div>
                </div>

                <form class="mt-6 grid gap-4" @submit.prevent="submitProfile">
                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-[#344054]">Имя</span>
                        <input
                            v-model="profileForm.name"
                            type="text"
                            autocomplete="name"
                            class="h-12 rounded-xl border border-[#D0D5DD] px-4 text-sm font-semibold text-[#111827] outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                        >
                        <span v-if="profileForm.errors.name" class="text-sm font-semibold text-[#EF4444]">
                            {{ profileForm.errors.name }}
                        </span>
                    </label>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white transition hover:bg-[#0757D8] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="profileForm.processing"
                        >
                            Сохранить имя
                        </button>
                    </div>
                </form>
            </section>

            <section
                class="relative overflow-hidden rounded-2xl border p-6"
                :class="isTelegramAvailable ? 'border-[#E5E7EB] bg-white' : 'border-[#E5E7EB] bg-[#F3F4F6]'"
            >
                <div :class="!isTelegramAvailable ? 'pointer-events-none select-none opacity-35 grayscale' : ''">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="text-sm font-extrabold text-[#12B3A8]">Telegram</p>
                                <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="telegramStatusClass">
                                    {{ telegramStatusLabel }}
                                </span>
                            </div>
                            <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">Уведомления в Telegram</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-[#667085]">
                                Подключите Telegram к Montri, чтобы получать уведомления об открытии и восстановлении инцидентов в личный чат.
                            </p>
                        </div>
                    </div>

                    <form class="mt-6 grid gap-5" @submit.prevent="submitTelegramSettings">
                        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl bg-[#F8FAFC] p-4">
                            <span>
                                <span class="block text-sm font-extrabold text-[#111827]">Получать уведомления в Telegram</span>
                                <span class="mt-1 block text-sm leading-6 text-[#667085]">Включите переключатель и нажмите «Подтвердить», чтобы привязать Telegram-чат через нашего бота.</span>
                            </span>
                            <input
                                v-model="telegramForm.telegram_notifications_enabled"
                                type="checkbox"
                                class="sr-only"
                                :disabled="telegramForm.processing || !isTelegramAvailable"
                            >
                            <span
                                class="relative h-7 w-12 shrink-0 rounded-full transition"
                                :class="telegramForm.telegram_notifications_enabled ? 'bg-[#0F6BFF]' : 'bg-[#CBD5E1]'"
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

                        <div
                            v-if="telegramForm.processing || shouldShowTelegramConnect || settings.telegram.is_connected"
                            class="grid gap-4 rounded-2xl border border-[#E5E7EB] p-4"
                        >
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-sm font-extrabold text-[#111827]">Подключение бота</p>
                                    <p class="mt-1 max-w-xl text-sm leading-6 text-[#667085]">{{ telegramHelperText }}</p>
                                    <p v-if="settings.telegram.username" class="mt-2 text-xs font-bold text-[#64748B]">
                                        Подключенный Telegram: {{ settings.telegram.username }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="shouldShowTelegramConnect && connectionCommand && !settings.telegram.bot_username" class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Команда для бота</span>
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <code class="min-h-11 flex-1 rounded-xl bg-[#F1F5F9] px-4 py-3 text-sm font-bold text-[#111827] break-all">
                                        {{ connectionCommand }}
                                    </code>
                                    <button
                                        type="button"
                                        class="inline-flex h-11 items-center justify-center rounded-xl border border-[#D0D5DD] px-4 text-sm font-extrabold text-[#344054] transition hover:border-[#98A2B3]"
                                        @click="copyConnectionCommand"
                                    >
                                        {{ copied ? 'Скопировано' : 'Скопировать' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white transition hover:bg-[#0757D8] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="telegramForm.processing || !isTelegramAvailable"
                            >
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
                    <p class="rounded-xl bg-white/85 px-5 py-3 text-sm font-extrabold text-[#4B5563] shadow-sm ring-1 ring-[#E5E7EB]">
                        Доступно на подписке Pro и Plus
                    </p>
                </div>
            </section>
        </section>
    </DashboardLayout>
</template>
