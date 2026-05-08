<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

type Organization = {
    id: string
    name: string
}

defineProps<{
    organization: Organization
}>()

const form = useForm({
    name: '',
})

function submit(): void {
    form.post('/sites/folders')
}
</script>

<template>
    <Head title="Create folder" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <header class="border-b border-white/10 px-6 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-400">
                        {{ organization.name }}
                    </p>

                    <h1 class="text-xl font-semibold">
                        Create folder
                    </h1>

                    <p class="mt-1 text-sm text-neutral-500">
                        Organize your sites into folders.
                    </p>
                </div>

                <Link
                    href="/sites"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                >
                    Back to sites
                </Link>
            </div>
        </header>

        <section class="mx-auto max-w-3xl px-6 py-10">
            <form
                class="rounded-2xl border border-white/10 bg-white/5 p-6"
                @submit.prevent="submit"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        Folder details
                    </h2>

                    <p class="mt-1 text-sm text-neutral-400">
                        Give this folder a clear name.
                    </p>
                </div>

                <div class="mt-6">
                    <label class="text-sm font-medium text-neutral-300">
                        Name
                    </label>

                    <input
                        v-model="form.name"
                        type="text"
                        autofocus
                        class="mt-2 w-full rounded-xl border border-white/10 bg-neutral-950 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500/50"
                        placeholder="Production"
                    >

                    <p
                        v-if="form.errors.name"
                        class="mt-2 text-sm text-red-400"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3 border-t border-white/10 pt-6">
                    <Link
                        href="/sites"
                        class="rounded-xl border border-white/10 px-4 py-2 text-sm text-neutral-300 hover:bg-white/5"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-medium text-neutral-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Create folder
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
