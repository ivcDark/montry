<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Crown } from '@lucide/vue'

withDefaults(defineProps<{
    action?: string
    prefix?: string
    linkText?: string
    compact?: boolean
    overlay?: boolean
}>(), {
    action: '',
    prefix: 'Только на',
    linkText: 'Pro и Team',
    compact: false,
    overlay: false,
})
</script>

<template>
    <div
        class="flex items-center justify-center border border-[#DDEBE3] bg-[#F3F6F4] text-[#8A9A91]"
        :class="[
            compact ? 'gap-2 rounded-lg px-3 py-2' : 'min-h-12 flex-col rounded-lg px-4 py-2',
            overlay ? 'shadow-[0_10px_30px_rgba(38,51,45,0.12)]' : '',
        ]"
    >
        <span class="inline-flex items-center gap-1 text-[11px] font-bold leading-none text-[#A76500]">
            <Crown class="h-3.5 w-3.5" :stroke-width="2.25" />
            {{ prefix }}
            <Link
                href="/billing"
                class="font-extrabold text-[#C87800] underline decoration-2 decoration-[#C87800]/60 underline-offset-2 transition hover:text-[#A76500]"
            >
                {{ linkText }}
            </Link>
        </span>
        <span v-if="action" :class="compact ? 'text-xs' : 'mt-1 text-sm'" class="font-semibold leading-none text-[#8A9A91]">
            {{ action }}
        </span>
    </div>
</template>