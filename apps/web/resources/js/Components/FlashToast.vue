<script setup lang="ts">
import { CircleAlert, CircleCheck } from '@lucide/vue'
import { computed, onUnmounted, ref, watch } from 'vue'

type ToastVariant = 'success' | 'error'

const props = withDefaults(defineProps<{
    message?: string | null
    token?: string | number | null
    variant?: ToastVariant
}>(), {
    message: null,
    token: null,
    variant: 'error',
})

const visible = ref(false)
let timeoutId: number | null = null

const icon = computed(() => props.variant === 'success' ? CircleCheck : CircleAlert)
const shellClass = computed(() => props.variant === 'success'
    ? 'border-[#BFEBD0] bg-white/95 text-[#173B2A] shadow-[0_18px_46px_rgba(31,68,49,0.16)]'
    : 'border-[#F5C2C2] bg-white/95 text-[#5C2525] shadow-[0_18px_46px_rgba(107,35,35,0.14)]')
const iconClass = computed(() => props.variant === 'success'
    ? 'bg-[#E9F8EF] text-[#159653]'
    : 'bg-[#FEECEC] text-[#E11D25]')

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
            }, 1800)
        }
    },
    { immediate: true },
)

onUnmounted(clearToastTimeout)
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="translate-y-[-8px] scale-[0.98] opacity-0"
            enter-to-class="translate-y-0 scale-100 opacity-100"
            leave-active-class="transition-all duration-500 ease-in-out"
            leave-from-class="translate-y-0 scale-100 opacity-100"
            leave-to-class="translate-y-[-6px] scale-[0.98] opacity-0"
        >
            <div
                v-if="message && visible"
                class="fixed left-4 right-4 top-4 z-[100] flex min-h-16 items-center gap-3 rounded-2xl border px-4 py-3.5 backdrop-blur sm:left-auto sm:right-6 sm:top-6 sm:w-full sm:max-w-[420px]"
                :class="shellClass"
                role="status"
                aria-live="polite"
            >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" :class="iconClass">
                    <component :is="icon" class="h-5 w-5" :stroke-width="2.2" />
                </span>

                <p class="min-w-0 text-sm font-semibold leading-5">
                    {{ message }}
                </p>
            </div>
        </Transition>
    </Teleport>
</template>
