<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Create account" />

    <main class="min-h-screen bg-neutral-950 text-white flex items-center justify-center px-6">
        <section class="w-full max-w-md rounded-2xl border border-white/10 bg-white/5 p-8 shadow-2xl">
            <div class="mb-8">
                <p class="text-sm font-medium text-emerald-400">Montri</p>
                <h1 class="mt-2 text-2xl font-semibold">Create account</h1>
                <p class="mt-2 text-sm text-neutral-400">
                    Start monitoring your services.
                </p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="name" class="mb-2 block text-sm font-medium">
                        Name
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        autofocus
                        class="w-full rounded-xl border border-white/10 bg-neutral-900 px-4 py-3 text-sm outline-none focus:border-emerald-400"
                    />

                    <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium">
                        Email
                    </label>

                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
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
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-white/10 bg-neutral-900 px-4 py-3 text-sm outline-none focus:border-emerald-400"
                    />

                    <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium">
                        Confirm password
                    </label>

                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-white/10 bg-neutral-900 px-4 py-3 text-sm outline-none focus:border-emerald-400"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-neutral-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span v-if="form.processing">Creating account...</span>
                    <span v-else>Create account</span>
                </button>
            </form>

            <p class="mt-6 text-sm text-neutral-400">
                Already have an account?
                <Link href="/login" class="text-emerald-400 hover:text-emerald-300">
                    Sign in
                </Link>
            </p>
        </section>
    </main>
</template>
