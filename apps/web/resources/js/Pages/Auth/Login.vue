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
    <Head title="Sign in" />

    <main class="min-h-screen bg-neutral-950 text-white flex items-center justify-center px-6">
        <section class="w-full max-w-md rounded-2xl border border-white/10 bg-white/5 p-8 shadow-2xl">
            <div class="mb-8">
                <p class="text-sm font-medium text-emerald-400">Montri</p>
                <h1 class="mt-2 text-2xl font-semibold">Sign in</h1>
                <p class="mt-2 text-sm text-neutral-400">
                    Continue monitoring your services.
                </p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium">
                        Email
                    </label>

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        autofocus
                        class="w-full rounded-xl border border-white/10 bg-neutral-900 px-4 py-3 text-sm outline-none focus:border-emerald-400"
                    />

                    <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium">
                        Password
                    </label>

                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full rounded-xl border border-white/10 bg-neutral-900 px-4 py-3 text-sm outline-none focus:border-emerald-400"
                    />

                    <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">
                        {{ form.errors.password }}
                    </p>
                </div>

                <label class="flex items-center gap-3 text-sm text-neutral-300">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="rounded border-white/10 bg-neutral-900"
                    />

                    Remember me
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-neutral-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span v-if="form.processing">Signing in...</span>
                    <span v-else>Sign in</span>
                </button>
            </form>

            <p class="mt-6 text-sm text-neutral-400">
                No account yet?
                <Link href="/register" class="text-emerald-400 hover:text-emerald-300">
                    Create one
                </Link>
            </p>
        </section>
    </main>
</template>
