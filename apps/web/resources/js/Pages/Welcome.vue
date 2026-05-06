<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Welcome" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <section class="mx-auto flex min-h-screen max-w-5xl flex-col items-center justify-center px-6 text-center">
            <p class="text-sm font-medium text-emerald-400">Montri</p>

            <h1 class="mt-4 text-4xl font-semibold tracking-tight">
                Monitoring for apps, APIs and services.
            </h1>

            <p class="mt-4 max-w-2xl text-neutral-400">
                Modular monolith backend, Vue frontend and Go pollers for checks.
            </p>

            <div class="mt-8 flex gap-3">
                <Link
                    href="/login"
                    class="rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-neutral-950"
                >
                    Sign in
                </Link>

                <Link
                    href="/register"
                    class="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-neutral-200"
                >
                    Create account
                </Link>
            </div>
        </section>
    </main>
</template>
