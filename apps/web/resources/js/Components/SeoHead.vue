<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    title: string
    description: string
    path: string
    indexable?: boolean
    ogType?: string
}>(), {
    indexable: true,
    ogType: 'website',
})

type PageProps = {
    appUrl: string
    seoIndexable: boolean
}

const page = usePage<PageProps>()

const baseUrl = computed(() => page.props.appUrl.replace(/\/$/, ''))
const canonical = computed(() => {
    const normalizedPath = props.path === '/' ? '/' : props.path.replace(/\/$/, '')

    return normalizedPath === '/' ? `${baseUrl.value}/` : `${baseUrl.value}${normalizedPath}`
})
const fullTitle = computed(() => `${props.title} - Montry`)
const robots = computed(() => (
    props.indexable && page.props.seoIndexable
        ? 'index, follow'
        : 'noindex, nofollow'
))
</script>

<template>
    <Head :title="title">
        <meta head-key="description" name="description" :content="description">
        <meta head-key="robots" name="robots" :content="robots">
        <link head-key="canonical" rel="canonical" :href="canonical">
        <meta head-key="og:title" property="og:title" :content="fullTitle">
        <meta head-key="og:description" property="og:description" :content="description">
        <meta head-key="og:url" property="og:url" :content="canonical">
        <meta head-key="og:type" property="og:type" :content="ogType">
        <meta head-key="og:site_name" property="og:site_name" content="Montry">
        <meta head-key="og:locale" property="og:locale" content="ru_RU">
        <meta head-key="twitter:card" name="twitter:card" content="summary">
        <meta head-key="twitter:title" name="twitter:title" :content="fullTitle">
        <meta head-key="twitter:description" name="twitter:description" :content="description">
    </Head>
</template>
