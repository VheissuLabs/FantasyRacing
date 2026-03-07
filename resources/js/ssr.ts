import { createInertiaApp } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import type { DefineComponent } from 'vue'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: (name) => {
                const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true })
                return pages[`./pages/${name}.vue`]
            },
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) }).use(plugin),
        }),
    { cluster: true },
)
