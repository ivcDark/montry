<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

type PlanFormData = {
    code: string
    name: string
    description: string
    price_rubles: string | number
    currency: string
    is_active: boolean
    sort_order: string | number
    max_sites: string | number | null
    max_monitors: string | number | null
    allowed_monitor_types: string
    history_retention_days: string | number | null
    minimum_check_interval_seconds: string | number | null
    notification_channels: string
    can_create_projects: boolean
    manual_checks_per_day: string | number | null
}

type Plan = {
    id: number
    code: string
    name: string
    description: string | null
    price_cents: number
    price_rubles: number
    currency: string
    is_active: boolean
    sort_order: number
    subscriptions_count: number
    active_subscriptions_count: number
    limits: Record<string, any>
    form: PlanFormData
}

const props = defineProps<{
    plans: Plan[]
}>()

const editingPlanId = ref<number | null>(null)

const blankPlanForm = (): PlanFormData => ({
    code: '',
    name: '',
    description: '',
    price_rubles: 0,
    currency: 'RUB',
    is_active: true,
    sort_order: 0,
    max_sites: '',
    max_monitors: '',
    allowed_monitor_types: 'http, ssl, domain',
    history_retention_days: '',
    minimum_check_interval_seconds: 300,
    notification_channels: 'email',
    can_create_projects: false,
    manual_checks_per_day: '',
})

const createForm = useForm<PlanFormData>(blankPlanForm())
const editForm = useForm<PlanFormData>(blankPlanForm())

const stats = computed(() => ({
    total: props.plans.length,
    active: props.plans.filter((plan) => plan.is_active).length,
    paid: props.plans.filter((plan) => plan.price_cents > 0).length,
    subscriptions: props.plans.reduce((sum, plan) => sum + plan.active_subscriptions_count, 0),
}))

const editingPlan = computed(() => props.plans.find((plan) => plan.id === editingPlanId.value) ?? null)

function assignFormValues(target: typeof createForm, values: PlanFormData): void {
    target.code = values.code
    target.name = values.name
    target.description = values.description ?? ''
    target.price_rubles = values.price_rubles
    target.currency = values.currency
    target.is_active = values.is_active
    target.sort_order = values.sort_order
    target.max_sites = values.max_sites ?? ''
    target.max_monitors = values.max_monitors ?? ''
    target.allowed_monitor_types = values.allowed_monitor_types
    target.history_retention_days = values.history_retention_days ?? ''
    target.minimum_check_interval_seconds = values.minimum_check_interval_seconds ?? ''
    target.notification_channels = values.notification_channels
    target.can_create_projects = values.can_create_projects
    target.manual_checks_per_day = values.manual_checks_per_day ?? ''
}

function storePlan(): void {
    createForm.post('/admin/plans', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    })
}

function startEdit(plan: Plan): void {
    editingPlanId.value = plan.id
    editForm.clearErrors()
    assignFormValues(editForm, plan.form)
}

function cancelEdit(): void {
    editingPlanId.value = null
    editForm.reset()
    editForm.clearErrors()
}

function updatePlan(): void {
    if (editingPlanId.value === null) {
        return
    }

    editForm.patch(`/admin/plans/${editingPlanId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingPlanId.value = null
            editForm.reset()
        },
    })
}

function deletePlan(plan: Plan): void {
    if (plan.subscriptions_count > 0) {
        return
    }

    if (!window.confirm(`Удалить тариф «${plan.name}»? Это действие нельзя отменить.`)) {
        return
    }

    router.delete(`/admin/plans/${plan.id}`, {
        preserveScroll: true,
    })
}

function formatMoney(plan: Pick<Plan, 'price_cents' | 'currency'>): string {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: plan.currency,
        maximumFractionDigits: 0,
    }).format(plan.price_cents / 100)
}

function numberOrInfinity(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '∞'
    }

    return String(value)
}

function listText(value: unknown): string {
    if (!Array.isArray(value) || value.length === 0) {
        return 'не задано'
    }

    return value.includes('*') ? 'Все' : value.join(', ')
}
</script>

<template>
    <Head title="Тарифы админки" />

    <AdminLayout
        active-item="plans"
        title="Тарифы"
        subtitle="Создание, удаление и редактирование тарифов, цен и лимитов"
    >
        <template #actions>
            <a
                href="#create-plan"
                class="inline-flex h-11 items-center justify-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white transition hover:bg-[#0757D8]"
            >
                Добавить тариф
            </a>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Всего тарифов</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ stats.total }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Активные</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#16A34A]">{{ stats.active }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Платные</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#0F6BFF]">{{ stats.paid }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Активные подписки</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ stats.subscriptions }}</p>
                </article>
            </section>

            <section class="mt-6 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="border-b border-[#E5E7EB] p-5">
                    <h2 class="text-xl font-extrabold text-[#111827]">Список тарифов</h2>
                    <p class="mt-1 text-sm text-[#667085]">Удалять можно только тарифы, которые еще не использовались в подписках.</p>
                </div>

                <div v-if="plans.length" class="overflow-x-auto">
                    <table class="min-w-[1180px] w-full border-separate border-spacing-0 text-left text-sm">
                        <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                        <tr>
                            <th class="px-5 py-4">Тариф</th>
                            <th class="px-5 py-4">Цена</th>
                            <th class="px-5 py-4">Лимиты</th>
                            <th class="px-5 py-4">Подписки</th>
                            <th class="px-5 py-4">Статус</th>
                            <th class="px-5 py-4 text-right">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="plan in plans" :key="plan.id">
                            <td class="border-t border-[#E5E7EB] px-5 py-4 align-top">
                                <p class="font-extrabold text-[#111827]">{{ plan.name }}</p>
                                <p class="mt-1 text-xs font-semibold text-[#667085]">{{ plan.code }} · порядок {{ plan.sort_order }}</p>
                                <p v-if="plan.description" class="mt-2 max-w-xs text-sm leading-5 text-[#667085]">{{ plan.description }}</p>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 align-top">
                                <p class="text-lg font-extrabold text-[#111827]">{{ formatMoney(plan) }}</p>
                                <p class="mt-1 text-xs font-semibold text-[#667085]">{{ plan.currency }}</p>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 align-top text-[#475467]">
                                <div class="grid gap-1.5 text-xs font-semibold">
                                    <span>Сайты: {{ numberOrInfinity(plan.limits.max_sites?.limit) }}</span>
                                    <span>Мониторы: {{ numberOrInfinity(plan.limits.max_monitors?.limit) }}</span>
                                    <span>Типы: {{ listText(plan.limits.allowed_monitor_types?.types) }}</span>
                                    <span>История: {{ plan.limits.history_retention_days?.days ?? 0 }} дн.</span>
                                    <span>Интервал: {{ plan.limits.minimum_check_interval_seconds?.seconds ?? 300 }} сек.</span>
                                    <span>Уведомления: {{ listText(plan.limits.notification_channels?.channels) }}</span>
                                    <span>Проекты: {{ plan.limits.can_create_projects?.enabled ? 'да' : 'нет' }}</span>
                                    <span>Ручные проверки/день: {{ numberOrInfinity(plan.limits.manual_checks_per_day?.limit) }}</span>
                                </div>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 align-top">
                                <p class="font-extrabold text-[#111827]">{{ plan.active_subscriptions_count }} активных</p>
                                <p class="mt-1 text-xs font-semibold text-[#667085]">{{ plan.subscriptions_count }} всего</p>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 align-top">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-extrabold"
                                    :class="plan.is_active ? 'bg-[#ECFDF3] text-[#16A34A]' : 'bg-[#F1F5F9] text-[#64748B]'"
                                >
                                    {{ plan.is_active ? 'Активен' : 'Отключен' }}
                                </span>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-right align-top">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="h-10 rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                                        @click="startEdit(plan)"
                                    >
                                        Редактировать
                                    </button>
                                    <button
                                        type="button"
                                        class="h-10 rounded-xl px-4 text-sm font-extrabold text-white transition"
                                        :class="plan.subscriptions_count > 0 ? 'cursor-not-allowed bg-[#CBD5E1]' : 'bg-[#EF4444] hover:bg-[#DC2626]'"
                                        :disabled="plan.subscriptions_count > 0"
                                        :title="plan.subscriptions_count > 0 ? 'Тариф используется в подписках' : 'Удалить тариф'"
                                        @click="deletePlan(plan)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-10 text-center text-[#667085]">
                    Тарифы пока не созданы.
                </div>
            </section>

            <section v-if="editingPlan" class="mt-6 rounded-3xl border border-[#BFDBFE] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="flex flex-col gap-3 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-[#0F6BFF]">Редактирование тарифа</p>
                        <h2 class="mt-1 text-2xl font-extrabold text-[#111827]">{{ editingPlan.name }}</h2>
                    </div>
                    <button
                        type="button"
                        class="h-10 rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                        @click="cancelEdit"
                    >
                        Отмена
                    </button>
                </div>

                <form class="mt-5 grid gap-5" @submit.prevent="updatePlan">
                    <div class="grid gap-4 lg:grid-cols-4">
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Код</span>
                            <input v-model="editForm.code" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="pro">
                            <span v-if="editForm.errors.code" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.code }}</span>
                        </label>
                        <label class="grid gap-2 lg:col-span-2">
                            <span class="text-sm font-bold text-[#344054]">Название</span>
                            <input v-model="editForm.name" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="Pro">
                            <span v-if="editForm.errors.name" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.name }}</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Порядок</span>
                            <input v-model="editForm.sort_order" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            <span v-if="editForm.errors.sort_order" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.sort_order }}</span>
                        </label>
                    </div>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-[#344054]">Описание</span>
                        <textarea v-model="editForm.description" rows="3" class="rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="Краткое описание тарифа" />
                        <span v-if="editForm.errors.description" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.description }}</span>
                    </label>

                    <div class="grid gap-4 lg:grid-cols-4">
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Цена, ₽/мес</span>
                            <input v-model="editForm.price_rubles" type="number" min="0" step="0.01" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            <span v-if="editForm.errors.price_rubles" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.price_rubles }}</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Валюта</span>
                            <input v-model="editForm.currency" maxlength="3" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm uppercase outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            <span v-if="editForm.errors.currency" class="text-xs font-bold text-[#EF4444]">{{ editForm.errors.currency }}</span>
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] px-4 py-3 lg:col-span-2">
                            <input v-model="editForm.is_active" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1]">
                            <span class="text-sm font-bold text-[#344054]">Тариф активен и доступен для выбора</span>
                        </label>
                    </div>

                    <div class="rounded-2xl bg-[#F8FAFC] p-4">
                        <h3 class="font-extrabold text-[#111827]">Лимиты</h3>
                        <p class="mt-1 text-sm text-[#667085]">Пустое значение для количества сайтов, мониторов или ручных проверок означает без лимита.</p>
                        <div class="mt-4 grid gap-4 lg:grid-cols-4">
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Сайты</span>
                                <input v-model="editForm.max_sites" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Мониторы</span>
                                <input v-model="editForm.max_monitors" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">История, дней</span>
                                <input v-model="editForm.history_retention_days" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Мин. интервал, сек.</span>
                                <input v-model="editForm.minimum_check_interval_seconds" type="number" min="30" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="grid gap-2 lg:col-span-2">
                                <span class="text-sm font-bold text-[#344054]">Типы мониторинга</span>
                                <input v-model="editForm.allowed_monitor_types" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="http, ssl, domain или *">
                            </label>
                            <label class="grid gap-2 lg:col-span-2">
                                <span class="text-sm font-bold text-[#344054]">Каналы уведомлений</span>
                                <input v-model="editForm.notification_channels" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="email, telegram">
                            </label>
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Ручные проверки/день</span>
                                <input v-model="editForm.manual_checks_per_day" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 lg:col-span-3">
                                <input v-model="editForm.can_create_projects" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1]">
                                <span class="text-sm font-bold text-[#344054]">Можно создавать проекты</span>
                            </label>
                        </div>
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

            <section id="create-plan" class="mt-6 rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="border-b border-[#E5E7EB] pb-5">
                    <p class="text-sm font-bold text-[#0F6BFF]">Новый тариф</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-[#111827]">Добавить тариф</h2>
                </div>

                <form class="mt-5 grid gap-5" @submit.prevent="storePlan">
                    <div class="grid gap-4 lg:grid-cols-4">
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Код</span>
                            <input v-model="createForm.code" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="business">
                            <span v-if="createForm.errors.code" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.code }}</span>
                        </label>
                        <label class="grid gap-2 lg:col-span-2">
                            <span class="text-sm font-bold text-[#344054]">Название</span>
                            <input v-model="createForm.name" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="Business">
                            <span v-if="createForm.errors.name" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.name }}</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Порядок</span>
                            <input v-model="createForm.sort_order" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            <span v-if="createForm.errors.sort_order" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.sort_order }}</span>
                        </label>
                    </div>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-[#344054]">Описание</span>
                        <textarea v-model="createForm.description" rows="3" class="rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="Краткое описание тарифа" />
                    </label>

                    <div class="grid gap-4 lg:grid-cols-4">
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Цена, ₽/мес</span>
                            <input v-model="createForm.price_rubles" type="number" min="0" step="0.01" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            <span v-if="createForm.errors.price_rubles" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.price_rubles }}</span>
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-[#344054]">Валюта</span>
                            <input v-model="createForm.currency" maxlength="3" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm uppercase outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            <span v-if="createForm.errors.currency" class="text-xs font-bold text-[#EF4444]">{{ createForm.errors.currency }}</span>
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] px-4 py-3 lg:col-span-2">
                            <input v-model="createForm.is_active" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1]">
                            <span class="text-sm font-bold text-[#344054]">Тариф активен и доступен для выбора</span>
                        </label>
                    </div>

                    <div class="rounded-2xl bg-[#F8FAFC] p-4">
                        <h3 class="font-extrabold text-[#111827]">Лимиты</h3>
                        <p class="mt-1 text-sm text-[#667085]">Списки вводятся через запятую. Для доступа ко всем типам мониторинга укажите *.</p>
                        <div class="mt-4 grid gap-4 lg:grid-cols-4">
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Сайты</span>
                                <input v-model="createForm.max_sites" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Мониторы</span>
                                <input v-model="createForm.max_monitors" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">История, дней</span>
                                <input v-model="createForm.history_retention_days" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Мин. интервал, сек.</span>
                                <input v-model="createForm.minimum_check_interval_seconds" type="number" min="30" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="grid gap-2 lg:col-span-2">
                                <span class="text-sm font-bold text-[#344054]">Типы мониторинга</span>
                                <input v-model="createForm.allowed_monitor_types" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="http, ssl, domain или *">
                            </label>
                            <label class="grid gap-2 lg:col-span-2">
                                <span class="text-sm font-bold text-[#344054]">Каналы уведомлений</span>
                                <input v-model="createForm.notification_channels" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15" placeholder="email, telegram">
                            </label>
                            <label class="grid gap-2">
                                <span class="text-sm font-bold text-[#344054]">Ручные проверки/день</span>
                                <input v-model="createForm.manual_checks_per_day" type="number" min="0" class="h-11 rounded-xl border border-[#E5E7EB] px-4 text-sm outline-none focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15">
                            </label>
                            <label class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 lg:col-span-3">
                                <input v-model="createForm.can_create_projects" type="checkbox" class="h-4 w-4 rounded border-[#CBD5E1]">
                                <span class="text-sm font-bold text-[#344054]">Можно создавать проекты</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="h-11 rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white transition hover:bg-[#0757D8] disabled:cursor-not-allowed disabled:bg-[#CBD5E1]"
                            :disabled="createForm.processing"
                        >
                            Создать тариф
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>
