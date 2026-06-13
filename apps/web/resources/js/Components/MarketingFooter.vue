<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import BrandMark from '@/Components/BrandMark.vue'

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
    <footer class="bg-[#123D2B] text-white">
        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[1.1fr_2fr_auto] lg:items-start">
            <div>
                <div class="flex items-center gap-3">
                    <BrandMark class="h-8 w-8" />
                    <span class="text-lg font-extrabold">Montry</span>
                </div>
                <p class="mt-5 max-w-xs text-sm leading-6 text-white/70">
                    Мониторинг сайтов, SSL, доменов, DNS и важных технических проверок.
                </p>
            </div>

            <div class="grid gap-8 text-sm sm:grid-cols-3">
                <div>
                    <p class="font-extrabold text-white">Montry</p>
                    <div class="mt-4 grid gap-3 text-white/70">
                        <a href="/#features" class="transition hover:text-white">Возможности</a>
                        <a href="/#checks" class="transition hover:text-white">Проверки</a>
                        <a href="/#pricing" class="transition hover:text-white">Тарифы</a>
                    </div>
                </div>

                <div>
                    <p class="font-extrabold text-white">Документы</p>
                    <div class="mt-4 grid gap-3 text-white/70">
                        <Link href="/offers" class="transition hover:text-white">Публичная оферта</Link>
                        <a href="/#faq" class="transition hover:text-white">FAQ</a>
                        <Link href="/articles" class="transition hover:text-white">Статьи</Link>
                        <a href="/#feedback" class="transition hover:text-white">Связаться</a>
                    </div>
                </div>

                <div>
                    <p class="font-extrabold text-white">Контакты</p>
                    <div class="mt-4 grid gap-3 text-white/70">
                        <a href="mailto:vladimir@vl-iv.ru" class="transition hover:text-white">vladimir@vl-iv.ru</a>
                        <p>Иванов Владимир Юрьевич</p>
                        <p>ИНН 562503808625</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 text-sm font-semibold text-white/70">
                <p>© 2026 Montry</p>
                <Link v-if="!user" href="/login" class="transition hover:text-white">Войти</Link>
            </div>
        </div>
    </footer>
</template>
