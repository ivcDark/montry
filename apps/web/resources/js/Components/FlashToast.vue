<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue'

const props = defineProps<{
    message?: string | null
    token?: number
}>()

const visible = ref(false)
let timeoutId: number | null = null

function clearToastTimeout(): void {
    if (timeoutId === null) {
        return
    }

    window.clearTimeout(timeoutId)
    timeoutId = null
}

watch(
    () => [props.message, props.token] as const,
    ([message]) => {
        clearToastTimeout()

        visible.value = Boolean(message)

        if (message) {
            timeoutId = window.setTimeout(() => {
                visible.value = false
                timeoutId = null
            }, 3000)
        }
    },
    { immediate: true },
)

onUnmounted(clearToastTimeout)
</script>

<template>
    <div
        v-if="message && visible"
        class="fixed right-5 top-5 z-50 max-w-sm rounded-2xl border border-[#FECACA] bg-white px-5 py-4 text-sm font-bold text-[#B91C1C] shadow-[0_18px_45px_rgba(15,23,42,0.18)]"
        role="status"
        aria-live="polite"
    >
        {{ message }}
    </div>
</template>
