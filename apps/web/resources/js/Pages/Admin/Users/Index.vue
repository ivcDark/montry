<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

type AdminUser = {
    id: number | string
    name: string
    email: string
    is_admin: boolean
    is_blocked: boolean
    organizations_count: number
    created_at: string | null
}

const props = defineProps<{
    users: AdminUser[]
}>()

const search = ref('')

const filteredUsers = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (!query) {
        return props.users
    }

    return props.users.filter((user) => {
        return [user.name, user.email]
            .join(' ')
            .toLowerCase()
            .includes(query)
    })
})

const stats = computed(() => ({
    total: props.users.length,
    admins: props.users.filter((user) => user.is_admin).length,
    regular: props.users.filter((user) => !user.is_admin).length,
}))

function formatDate(value: string | null): string {
    if (!value) return 'нет данных'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}
</script>

<template>
    <Head title="Пользователи админки" />

    <AdminLayout
        active-item="users"
        title="Пользователи"
        subtitle="Список учетных записей, администраторов и связей с организациями"
    >
        <template #actions>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#98A2B3]">⌕</span>
                <input
                    v-model="search"
                    type="search"
                    class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-white pl-10 pr-4 text-sm outline-none transition placeholder:text-[#98A2B3] focus:border-[#0F6BFF] focus:ring-2 focus:ring-[#0F6BFF]/15 sm:w-80"
                    placeholder="Поиск по имени или email"
                >
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <section class="grid gap-4 sm:grid-cols-3">
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Всего пользователей</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#111827]">{{ stats.total }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Администраторы</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#0F6BFF]">{{ stats.admins }}</p>
                </article>
                <article class="rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                    <p class="text-sm font-bold text-[#667085]">Обычные аккаунты</p>
                    <p class="mt-3 text-4xl font-extrabold text-[#64748B]">{{ stats.regular }}</p>
                </article>
            </section>

            <section class="mt-6 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_28px_rgba(15,23,42,0.06)]">
                <div class="flex flex-col gap-2 border-b border-[#E5E7EB] p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-extrabold text-[#111827]">Все пользователи</h2>
                        <p class="mt-1 text-sm text-[#667085]">Первые 100 учетных записей, отсортированы по email.</p>
                    </div>
                    <p class="text-sm font-bold text-[#667085]">Показано: {{ filteredUsers.length }}</p>
                </div>

                <div v-if="filteredUsers.length" class="overflow-x-auto">
                    <table class="min-w-[860px] w-full border-separate border-spacing-0 text-left text-sm">
                        <thead class="bg-[#F8FAFC] text-xs font-extrabold text-[#667085]">
                        <tr>
                            <th class="px-5 py-4">Пользователь</th>
                            <th class="px-5 py-4">Роль</th>
                            <th class="px-5 py-4">Доступ</th>
                            <th class="px-5 py-4">Организации</th>
                            <th class="px-5 py-4">Создан</th>
                            <th class="px-5 py-4 text-right">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="user in filteredUsers" :key="user.id">
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <Link :href="`/admin/users/${user.id}`" class="font-extrabold text-[#111827] hover:text-[#0F6BFF]">
                                    {{ user.name }}
                                </Link>
                                <p class="mt-1 text-xs font-semibold text-[#667085]">{{ user.email }}</p>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-extrabold"
                                    :class="user.is_admin ? 'bg-[#EAF2FF] text-[#0F6BFF]' : 'bg-[#F1F5F9] text-[#64748B]'"
                                >
                                    {{ user.is_admin ? 'Admin' : 'User' }}
                                </span>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-extrabold"
                                    :class="user.is_blocked ? 'bg-[#FEECEC] text-[#EF4444]' : 'bg-[#ECFDF3] text-[#16A34A]'"
                                >
                                    {{ user.is_blocked ? 'Blocked' : 'Active' }}
                                </span>
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 font-semibold text-[#111827]">
                                {{ user.organizations_count }}
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-[#667085]">
                                {{ formatDate(user.created_at) }}
                            </td>
                            <td class="border-t border-[#E5E7EB] px-5 py-4 text-right">
                                <Link
                                    :href="`/admin/users/${user.id}`"
                                    class="inline-flex h-9 items-center rounded-xl border border-[#E5E7EB] px-3 text-xs font-extrabold text-[#111827] transition hover:border-[#0F6BFF] hover:text-[#0F6BFF]"
                                >
                                    Открыть
                                </Link>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-10 text-center">
                    <h3 class="text-xl font-extrabold text-[#111827]">Ничего не найдено</h3>
                    <p class="mt-2 text-[#667085]">Попробуйте изменить поисковый запрос.</p>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
