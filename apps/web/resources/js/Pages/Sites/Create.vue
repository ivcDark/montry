<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    url: '',
})

function submit() {
    form.post('/sites', {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Add site" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <header class="border-b border-white/10 px-6 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-400">Montri</p>
                    <h1 class="text-xl font-semibold">Add site</h1>
                </div>

                <Link
                    href="/sites"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                >
                    Back to sites
                </Link>
            </div>
        </header>

        <section class="mx-auto max-w-2xl px-6 py-10">
            <form
                class="rounded-2xl border border-white/10 bg-white/5 p-8"
                @submit.prevent="submit"
            >
                <div class="space-y-6">
                    <div>
                        <label for="url" class="mb-2 block text-sm font-medium">
                            Site URL
                        </label>

                        <input
                            id="url"
                            v-model="form.url"
                            type="text"
                            placeholder="https://example.com"
                            class="w-full rounded-xl border border-white/10 bg-neutral-900 px-4 py-3 text-sm outline-none focus:border-emerald-400"
                        />

                        <p v-if="form.errors.url" class="mt-2 text-sm text-red-400">
                            {{ form.errors.url }}
                        </p>

                        <p class="mt-2 text-sm text-neutral-500">
                            You can enter example.com — Montri will assume HTTPS.
                        </p>
                    </div>

                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium">
                            Display name
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Main website"
                            class="w-full rounded-xl border border-white/10 bg-neutral-900 px-4 py-3 text-sm outline-none focus:border-emerald-400"
                        />

                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">
                            {{ form.errors.name }}
                        </p>

                        <p class="mt-2 text-sm text-neutral-500">
                            Optional. If empty, the hostname will be used.
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-neutral-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span v-if="form.processing">Adding site...</span>
                        <span v-else>Add site</span>
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
