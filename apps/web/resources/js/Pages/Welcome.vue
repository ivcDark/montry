<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Montri" />

    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-6 text-white">
        <section class="flex w-full max-w-sm flex-col items-center text-center">
            <h1 class="text-5xl font-semibold tracking-normal">
                Montri
            </h1>

            <Link
                href="/login"
                class="mt-8 inline-flex h-11 items-center justify-center rounded-lg bg-emerald-500 px-6 text-sm font-semibold text-neutral-950 hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-neutral-950"
            >
                Войти
            </Link>
        </section>
    </main>
</template>
