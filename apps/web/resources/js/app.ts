import './bootstrap'
import '../css/app.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

createInertiaApp({
    title: (title) => title ? `${title} - Montri` : 'Montri',

    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', {
            eager: true,
        })

        const page = pages[`./Pages/${name}.vue`] as { default: unknown } | undefined

        if (!page) {
            console.error('Available Inertia pages:', Object.keys(pages))
            throw new Error(`Page not found: ${name}`)
        }

        return page.default
    },

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .mount(el)
    },
})
