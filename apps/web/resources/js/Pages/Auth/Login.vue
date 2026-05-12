<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Вход" />

    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-4 py-10 text-white sm:px-6">
        <section class="w-full max-w-md rounded-lg border border-white/10 bg-neutral-900 p-6 shadow-2xl sm:p-8">
            <div class="mb-8">
                <Link href="/" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300">
                    Montri
                </Link>

                <h1 class="mt-3 text-2xl font-semibold tracking-normal">
                    Войти в аккаунт
                </h1>

                <p class="mt-2 text-sm text-neutral-400">
                    Используйте email и пароль, указанные при регистрации.
                </p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-neutral-200">
                        Email <span class="text-red-300">*</span>
                    </label>

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        autofocus
                        required
                        inputmode="email"
                        :aria-invalid="Boolean(form.errors.email)"
                        aria-describedby="email-error"
                        class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                    >

                    <p id="email-error" v-if="form.errors.email" class="mt-2 text-sm text-red-300">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-neutral-200">
                        Пароль <span class="text-red-300">*</span>
                    </label>

                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        :aria-invalid="Boolean(form.errors.password)"
                        aria-describedby="password-error"
                        class="h-11 w-full rounded-lg border border-white/10 bg-neutral-950 px-3 text-sm outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                    >

                    <p id="password-error" v-if="form.errors.password" class="mt-2 text-sm text-red-300">
                        {{ form.errors.password }}
                    </p>
                </div>

                <label class="flex items-center gap-3 rounded-lg border border-white/10 bg-neutral-950 px-3 py-3 text-sm text-neutral-300">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="rounded border-white/10 bg-neutral-900 text-emerald-500"
                    >

                    Запомнить меня
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex h-11 w-full items-center justify-center rounded-lg bg-emerald-500 px-4 text-sm font-semibold text-neutral-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span v-if="form.processing">Входим...</span>
                    <span v-else>Войти</span>
                </button>
            </form>

            <p class="mt-6 text-sm text-neutral-400">
                Нет аккаунта?
                <Link href="/register" class="font-medium text-emerald-400 hover:text-emerald-300">
                    Создать аккаунт
                </Link>
            </p>
        </section>
    </main>
</template>
