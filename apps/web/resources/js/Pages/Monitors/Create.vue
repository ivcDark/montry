<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Organization = {
    id: string
    name: string
}

type Site = {
    id: string
    name: string
    url: string
    scheme: string
    host: string
    port: number | null
    path: string
}

type MonitorTypeOption = {
    value: string
    label: string
}

const props = defineProps<{
    organization: Organization
    site: Site
    monitorTypes: MonitorTypeOption[]
}>()

const statusCodesText = ref('200')
const warningDaysText = ref('30, 14, 7, 3, 1')
const intervalPresets = [5, 10, 15, 30, 60, 360, 720, 1440]

const form = useForm({
    type: 'http',
    name: 'HTTP check',
    is_enabled: true,
    interval_seconds: 300,
    timeout_ms: 10000,
    settings: {
        method: 'GET',
        url: props.site.url,
        follow_redirects: true,
        verify_ssl: true,
        domain: props.site.host,
        port: props.site.port ?? 443,
        warning_days: [30, 14, 7, 3, 1],
    },
    expected: {
        status_codes: [200],
        max_response_time_ms: 5000,
        valid: true,
        registered: true,
    },
})

const selectedType = computed(() => props.monitorTypes.find((type) => type.value === form.type))

const typeHints: Record<string, { title: string, description: string, result: string }> = {
    http: {
        title: 'HTTP/HTTPS',
        description: 'Проверяет доступность сайта, код ответа и время загрузки.',
        result: 'Ожидаемый результат: 200 OK до 5000 мс',
    },
    ssl: {
        title: 'SSL',
        description: 'Следит за валидностью сертификата и предупреждает до истечения.',
        result: 'Предупреждения: 30, 14, 7, 3, 1 дней',
    },
    domain: {
        title: 'Домен',
        description: 'Проверяет срок регистрации домена и заранее подсвечивает риск.',
        result: 'Предупреждения: 30, 14, 7, 3, 1 дней',
    },
}

const activeHint = computed(() => typeHints[form.type] ?? {
    title: selectedType.value?.label ?? 'Мониторинг',
    description: 'Настройте параметры проверки для выбранного типа.',
    result: 'Проверка будет создана после сохранения.',
})

function selectType(type: string): void {
    form.type = type

    if (type === 'http') {
        form.name = 'HTTP check'
        form.timeout_ms = 10000
        form.settings.method = 'GET'
        form.settings.url = props.site.url
        form.settings.follow_redirects = true
        form.settings.verify_ssl = true
        statusCodesText.value = '200'
        form.expected.max_response_time_ms = 5000
    }

    if (type === 'ssl') {
        form.name = 'SSL certificate check'
        form.timeout_ms = 10000
        form.settings.domain = props.site.host
        form.settings.port = props.site.port ?? 443
        warningDaysText.value = '30, 14, 7, 3, 1'
        form.expected.valid = true
    }

    if (type === 'domain') {
        form.name = 'Domain expiration check'
        form.timeout_ms = 10000
        form.settings.domain = props.site.host
        warningDaysText.value = '30, 14, 7, 3, 1'
        form.expected.registered = true
    }
}

function parseNumberList(value: string): number[] {
    return value
        .split(',')
        .map((item) => Number.parseInt(item.trim(), 10))
        .filter((item) => Number.isInteger(item))
}

function intervalMinutes(seconds: number): number {
    return Math.round(seconds / 60)
}

function setIntervalMinutes(minutes: number): void {
    form.interval_seconds = minutes * 60
}

function intervalText(seconds: number): string {
    const minutes = intervalMinutes(seconds)

    if (minutes === 60) return 'Каждый час'
    if (minutes === 1440) return 'Раз в день'
    if (minutes > 60 && minutes % 60 === 0) return `Каждые ${minutes / 60} ч`

    return `Каждые ${minutes} мин`
}

function requestPayload() {
    if (form.type === 'http') {
        return {
            type: form.type,
            name: form.name,
            is_enabled: form.is_enabled,
            interval_seconds: form.interval_seconds,
            timeout_ms: form.timeout_ms,
            settings: {
                method: form.settings.method,
                url: form.settings.url,
                follow_redirects: form.settings.follow_redirects,
                verify_ssl: form.settings.verify_ssl,
            },
            expected: {
                status_codes: parseNumberList(statusCodesText.value),
                max_response_time_ms: form.expected.max_response_time_ms,
            },
        }
    }

    if (form.type === 'ssl') {
        return {
            type: form.type,
            name: form.name,
            is_enabled: form.is_enabled,
            interval_seconds: form.interval_seconds,
            timeout_ms: form.timeout_ms,
            settings: {
                domain: form.settings.domain,
                port: form.settings.port,
                warning_days: parseNumberList(warningDaysText.value),
            },
            expected: {
                valid: form.expected.valid,
            },
        }
    }

    return {
        type: form.type,
        name: form.name,
        is_enabled: form.is_enabled,
        interval_seconds: form.interval_seconds,
        timeout_ms: form.timeout_ms,
        settings: {
            domain: form.settings.domain,
            warning_days: parseNumberList(warningDaysText.value),
        },
        expected: {
            registered: form.expected.registered,
        },
    }
}

function submit(): void {
    form
        .transform(() => requestPayload())
        .post(`/sites/${props.site.id}/monitors`)
}
</script>

<template>
    <Head title="Создать мониторинг" />

    <DashboardLayout
        :organization="organization"
        active-item="monitors"
        title="Создать мониторинг"
        :subtitle="`${site.name} · ${site.url}`"
    >
        <template #actions>
            <Link
                :href="`/sites/${site.id}`"
                class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
            >
                Назад к сайту
            </Link>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                <aside class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <h2 class="text-xl font-extrabold text-[#111827]">Тип проверки</h2>
                    <p class="mt-1 text-sm leading-6 text-[#667085]">Выберите, что Montry будет проверять для этого сайта.</p>

                    <div class="mt-5 grid gap-3">
                        <button
                            v-for="type in monitorTypes"
                            :key="type.value"
                            type="button"
                            class="rounded-2xl border p-4 text-left transition"
                            :class="form.type === type.value
                                ? 'border-[#0F6BFF] bg-[#EAF2FF] text-[#0F6BFF]'
                                : 'border-[#E5E7EB] bg-white text-[#111827] hover:border-[#0F6BFF]'"
                            @click="selectType(type.value)"
                        >
                            <span class="block text-sm font-extrabold">{{ type.label }}</span>
                            <span class="mt-1 block text-xs font-semibold text-[#667085]">
                                {{ typeHints[type.value]?.description ?? 'Пользовательский тип мониторинга' }}
                            </span>
                        </button>
                    </div>

                    <p v-if="form.errors.type" class="mt-3 text-sm font-semibold text-[#EF4444]">
                        {{ form.errors.type }}
                    </p>

                    <div class="mt-6 rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] p-4">
                        <p class="text-sm font-extrabold text-[#111827]">{{ activeHint.title }}</p>
                        <p class="mt-1 text-sm leading-6 text-[#667085]">{{ activeHint.description }}</p>
                        <span class="mt-4 inline-flex rounded-full bg-[#ECFDF3] px-3 py-1 text-xs font-extrabold text-[#16A34A]">
                            {{ activeHint.result }}
                        </span>
                    </div>
                </aside>

                <form
                    class="rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]"
                    @submit.prevent="submit"
                >
                    <div class="border-b border-[#E5E7EB] p-5">
                        <h2 class="text-xl font-extrabold text-[#111827]">Настройки проверки</h2>
                        <p class="mt-1 text-sm text-[#667085]">Поля уже заполнены рекомендуемыми значениями для MVP.</p>
                    </div>

                    <div class="grid gap-6 p-5">
                        <section class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="monitor-name" class="mb-2 block text-sm font-extrabold text-[#111827]">Название</label>
                                <input
                                    id="monitor-name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                >
                                <p v-if="form.errors.name" class="mt-2 text-xs font-semibold text-[#EF4444]">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label for="interval" class="block text-sm font-extrabold text-[#111827]">Частота проверки</label>
                                    <span class="text-xs font-extrabold text-[#0F6BFF]">{{ intervalText(form.interval_seconds) }}</span>
                                </div>
                                <div class="rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] p-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="minutes in intervalPresets"
                                            :key="minutes"
                                            type="button"
                                            class="h-8 rounded-full px-3 text-xs font-extrabold transition"
                                            :class="intervalMinutes(form.interval_seconds) === minutes ? 'bg-[#0F6BFF] text-white' : 'bg-white text-[#667085] hover:bg-[#EAF2FF] hover:text-[#0F6BFF]'"
                                            @click="setIntervalMinutes(minutes)"
                                        >
                                            {{ minutes === 60 ? '1 час' : minutes === 1440 ? '1 день' : minutes < 60 ? `${minutes} мин` : `${minutes / 60} ч` }}
                                        </button>
                                    </div>
                                    <input
                                        id="interval"
                                        :value="intervalMinutes(form.interval_seconds)"
                                        type="range"
                                        min="5"
                                        max="1440"
                                        step="1"
                                        class="mt-4 w-full accent-[#0F6BFF]"
                                        @input="setIntervalMinutes(Number(($event.target as HTMLInputElement).value))"
                                    >
                                </div>
                                <p v-if="form.errors.interval_seconds" class="mt-2 text-xs font-semibold text-[#EF4444]">{{ form.errors.interval_seconds }}</p>
                            </div>

                            <div>
                                <label for="timeout" class="mb-2 block text-sm font-extrabold text-[#111827]">Таймаут, мс</label>
                                <input
                                    id="timeout"
                                    v-model.number="form.timeout_ms"
                                    type="number"
                                    min="1000"
                                    max="60000"
                                    required
                                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                >
                                <p v-if="form.errors.timeout_ms" class="mt-2 text-xs font-semibold text-[#EF4444]">{{ form.errors.timeout_ms }}</p>
                            </div>

                            <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-[#F8FAFC] px-4 py-3">
                                <span
                                    class="flex h-6 w-11 items-center rounded-full p-1 transition"
                                    :class="form.is_enabled ? 'justify-end bg-[#0F6BFF]' : 'justify-start bg-[#CBD5E1]'"
                                >
                                    <span class="h-4 w-4 rounded-full bg-white shadow-sm" />
                                </span>
                                <input v-model="form.is_enabled" type="checkbox" class="sr-only">
                                <span>
                                    <span class="block text-sm font-extrabold text-[#111827]">Мониторинг включён</span>
                                    <span class="text-xs font-semibold text-[#667085]">Начать проверки после создания</span>
                                </span>
                            </label>
                        </section>

                        <section v-if="form.type === 'http'" class="rounded-3xl border border-[#E5E7EB] bg-[#F8FAFC] p-5">
                            <div class="grid gap-5 md:grid-cols-[150px_minmax(0,1fr)]">
                                <div>
                                    <label for="method" class="mb-2 block text-sm font-extrabold text-[#111827]">Метод</label>
                                    <select
                                        id="method"
                                        v-model="form.settings.method"
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                        <option value="GET">GET</option>
                                        <option value="HEAD">HEAD</option>
                                        <option value="POST">POST</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="url" class="mb-2 block text-sm font-extrabold text-[#111827]">URL</label>
                                    <input
                                        id="url"
                                        v-model="form.settings.url"
                                        type="url"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>
                            </div>

                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="status-codes" class="mb-2 block text-sm font-extrabold text-[#111827]">Ожидаемые коды</label>
                                    <input
                                        id="status-codes"
                                        v-model="statusCodesText"
                                        type="text"
                                        placeholder="200, 204"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div>
                                    <label for="response-time" class="mb-2 block text-sm font-extrabold text-[#111827]">Макс. время ответа, мс</label>
                                    <input
                                        id="response-time"
                                        v-model.number="form.expected.max_response_time_ms"
                                        type="number"
                                        min="1"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.settings.follow_redirects" type="checkbox" class="h-4 w-4 rounded border-[#E5E7EB] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">Следовать редиректам</span>
                                </label>

                                <label class="flex items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-4 py-3">
                                    <input v-model="form.settings.verify_ssl" type="checkbox" class="h-4 w-4 rounded border-[#E5E7EB] text-[#0F6BFF]">
                                    <span class="text-sm font-bold text-[#111827]">Проверять SSL при HTTP</span>
                                </label>
                            </div>
                        </section>

                        <section v-if="form.type === 'ssl' || form.type === 'domain'" class="rounded-3xl border border-[#E5E7EB] bg-[#F8FAFC] p-5">
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label for="domain" class="mb-2 block text-sm font-extrabold text-[#111827]">Домен</label>
                                    <input
                                        id="domain"
                                        v-model="form.settings.domain"
                                        type="text"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>

                                <div v-if="form.type === 'ssl'">
                                    <label for="port" class="mb-2 block text-sm font-extrabold text-[#111827]">Порт</label>
                                    <input
                                        id="port"
                                        v-model.number="form.settings.port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        required
                                        class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                    >
                                </div>
                            </div>

                            <div class="mt-5">
                                <label for="warning-days" class="mb-2 block text-sm font-extrabold text-[#111827]">Дни предупреждений</label>
                                <input
                                    id="warning-days"
                                    v-model="warningDaysText"
                                    type="text"
                                    placeholder="30, 14, 7, 3, 1"
                                    required
                                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm outline-none transition focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15"
                                >
                            </div>
                        </section>

                        <div v-if="form.errors.settings || form.errors.expected" class="rounded-2xl border border-[#FECACA] bg-[#FEECEC] px-4 py-3 text-sm font-semibold text-[#EF4444]">
                            {{ form.errors.settings || form.errors.expected }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#E5E7EB] p-5 sm:flex-row sm:justify-end">
                        <Link
                            :href="`/sites/${site.id}`"
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                        >
                            Отмена
                        </Link>

                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white shadow-[0_10px_28px_rgba(15,107,255,0.18)] transition hover:bg-[#0757D8] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Создаём...</span>
                            <span v-else>Создать мониторинг</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
