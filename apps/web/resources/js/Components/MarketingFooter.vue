<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'

type User = {
    id: number | string
    name: string
    email: string
}

type PageProps = {
    auth: {
        user: User | null
    }
}

const page = usePage<PageProps>()
const user = page.props.auth.user
</script>

<template>
    <footer class="border-t border-[#E5E7EB] bg-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-5 py-8 sm:px-8 lg:grid-cols-[1.2fr_1fr_auto] lg:items-start">
            <div>
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0F6BFF] text-lg font-extrabold text-white">M</span>
                    <span class="text-2xl font-extrabold text-[#111827]">Montry</span>
                </div>
                <p class="mt-3 max-w-md text-sm leading-6 text-[#667085]">
                    Мониторинг сайтов, SSL и доменов для веб-студий и бизнеса.
                </p>
            </div>

            <div class="grid gap-4 text-sm font-semibold text-[#667085]">
                <div class="flex flex-wrap gap-x-5 gap-y-3">
                    <a href="/#features" class="transition hover:text-[#111827]">Возможности</a>
                    <a href="/#audience" class="transition hover:text-[#111827]">Для кого</a>
                    <a href="/#pricing" class="transition hover:text-[#111827]">Тарифы</a>
                    <a href="/#faq" class="transition hover:text-[#111827]">FAQ</a>
                    <Link href="/offers" class="transition hover:text-[#111827]">Оферта</Link>
                    <Link v-if="!user" href="/login" class="transition hover:text-[#111827]">Войти</Link>
                </div>

                <div class="grid gap-1 leading-6">
                    <p class="font-extrabold text-[#111827]">Исполнитель</p>
                    <p>Иванов Владимир Юрьевич</p>
                    <p>ИНН 562503808625</p>
                    <p>
                        <a href="mailto:vladimir@vl-iv.ru" class="transition hover:text-[#111827]">vladimir@vl-iv.ru</a>
                    </p>
                </div>
            </div>

            <p class="text-sm font-semibold text-[#667085]">© 2026 Montry</p>
        </div>
    </footer>
</template>
