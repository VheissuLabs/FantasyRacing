import { createInertiaApp, router } from '@inertiajs/vue3'
import { configureEcho } from '@laravel/echo-vue'
import type { DefineComponent } from 'vue'
import { createApp, h } from 'vue'
import '../css/app.css'
import { initializeTheme } from './composables/useAppearance'

configureEcho({
    broadcaster: 'reverb',
})

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true })
        return pages[`./pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
    progress: {
        color: '#4B5563',
    },
})

router.on('before', (event) => {
    event.detail.visit.headers['X-Timezone'] =
        Intl.DateTimeFormat().resolvedOptions().timeZone
})

initializeTheme()
