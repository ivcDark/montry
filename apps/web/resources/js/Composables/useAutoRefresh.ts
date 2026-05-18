import { router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted } from 'vue'

type AutoRefreshOptions = {
    only: string[]
    intervalMs?: number
}

export function useAutoRefresh({ only, intervalMs = 15000 }: AutoRefreshOptions): void {
    let timer: number | undefined
    let isReloading = false

    const refresh = (): void => {
        if (document.hidden || isReloading) {
            return
        }

        router.reload({
            only,
            onStart: () => {
                isReloading = true
            },
            onFinish: () => {
                isReloading = false
            },
        })
    }

    const start = (): void => {
        stop()
        timer = window.setInterval(refresh, intervalMs)
    }

    const stop = (): void => {
        if (timer !== undefined) {
            window.clearInterval(timer)
            timer = undefined
        }
    }

    const handleVisibilityChange = (): void => {
        if (document.hidden) {
            stop()

            return
        }

        refresh()
        start()
    }

    onMounted(() => {
        start()
        document.addEventListener('visibilitychange', handleVisibilityChange)
    })

    onBeforeUnmount(() => {
        stop()
        document.removeEventListener('visibilitychange', handleVisibilityChange)
    })
}
