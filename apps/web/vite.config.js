import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
            ],
            refresh: true,
        }),

        vue(),

        tailwindcss(),
    ],

    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',

        cors: {
            origin: [
                'http://localhost:8080',
                'http://127.0.0.1:8080',
                /^https:\/\/[a-z0-9-]+\.tunnelmole\.net$/,
            ],
        },

        hmr: {
            host: 'localhost',
            port: 5173,
        },
    },
})
