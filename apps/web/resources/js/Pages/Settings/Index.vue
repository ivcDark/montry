<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
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
    }
}

type PageProps = {
    flash?: {
        success?: string | null
        error?: string | null
    }
}

const props = defineProps<{
    organization: Organization
    settings: Settings
}>()

const page = usePage<PageProps>()
const copied = ref(false)

const profileForm = useForm({
    name: props.settings.profile.name,
})

const telegramForm = useForm({
    telegram_notifications_enabled: props.settings.telegram.notifications_enabled,
})

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
    if (props.settings.telegram.is_connected) {
        return 'Подключен'
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
    if (props.settings.telegram.is_connected) {
        return 'bg-[#ECFDF3] text-[#16A34A]'
    }

    if (telegramForm.processing || telegramForm.telegram_notifications_enabled) {
        return 'bg-[#FFF7E8] text-[#B45309]'
    }

    return 'bg-[#F1F5F9] text-[#64748B]'
})

const telegramHelperText = computed(() => {
    if (props.settings.telegram.is_connected) {
        return 'Аккаунт Telegram подключен. Когда отправка инцидентов будет включена, сообщения будут приходить в этот чат.'
    }

    if (shouldShowTelegramConnect.value && props.settings.telegram.setup_url) {
        return 'Нажмите кнопку подтверждения. Telegram откроет бота Montry и передаст код подключения автоматически.'
    }

    if (shouldShowTelegramConnect.value && props.settings.telegram.connection_token) {
        return 'Бот Montry еще не настроен для прямого открытия. Отправьте команду подключения боту вручную.'
    }

    if (telegramForm.processing) {
        return 'Сохраняем настройку и готовим подключение к Telegram.'
    }

    return 'Включите Telegram-уведомления, после сохранения здесь появится кнопка подключения.'
})

function submitProfile(): void {
    profileForm.patch('/settings/profile', {
        preserveScroll: true,
    })
}

function updateTelegramSettings(): void {
    telegramForm.patch('/settings/telegram', {
        preserveScroll: true,
        onError: () => {
            telegramForm.telegram_notifications_enabled = props.settings.telegram.notifications_enabled
        },
    })
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
            <div
                v-if="page.props.flash?.success"
                class="rounded-2xl border border-[#D1FADF] bg-[#ECFDF3] px-5 py-4 text-sm font-bold text-[#15803D]"
            >
                {{ page.props.flash.success }}
            </div>

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

            <section class="rounded-2xl border border-[#E5E7EB] bg-white p-6">
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
                            Подключите Telegram к Montry, чтобы позже получать уведомления об инцидентах в личный чат.
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid gap-5">
                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl bg-[#F8FAFC] p-4">
                        <span>
                            <span class="block text-sm font-extrabold text-[#111827]">Получать уведомления в Telegram</span>
                            <span class="mt-1 block text-sm leading-6 text-[#667085]">Переключатель сохраняется автоматически. После включения появится кнопка подключения.</span>
                        </span>
                        <input
                            v-model="telegramForm.telegram_notifications_enabled"
                            type="checkbox"
                            class="sr-only"
                            :disabled="telegramForm.processing"
                            @change="updateTelegramSettings"
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

                    <div
                        v-if="telegramForm.processing || shouldShowTelegramConnect || settings.telegram.is_connected"
                        class="grid gap-4 rounded-2xl border border-[#E5E7EB] p-4"
                    >
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-extrabold text-[#111827]">Подключение бота</p>
                                <p class="mt-1 max-w-xl text-sm leading-6 text-[#667085]">{{ telegramHelperText }}</p>
                            </div>

                            <a
                                v-if="shouldShowTelegramConnect && settings.telegram.setup_url"
                                :href="settings.telegram.setup_url"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex h-11 items-center justify-center rounded-xl bg-[#111827] px-5 text-sm font-extrabold text-white transition hover:bg-[#0B1220]"
                            >
                                Подтвердить подключение
                            </a>
                        </div>

                        <div v-if="shouldShowTelegramConnect && connectionCommand && !settings.telegram.setup_url" class="grid gap-2">
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

                </div>
            </section>
        </section>
    </DashboardLayout>
</template>
