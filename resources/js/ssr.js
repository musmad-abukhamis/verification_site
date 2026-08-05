import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, Fragment, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import NavigationOverlay from './Components/NavigationOverlay.vue';
import PwaInstallToast from './Components/PwaInstallToast.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob('./Pages/**/*.vue'),
            ),
        setup({ App, props, plugin }) {
            /* Both render to nothing on the server — each is behind a `v-if`
               that only a client-side event flips — but they have to be in the
               tree so the client hydrates the same shape. See app.js. */
            return createSSRApp({
                render: () => h(Fragment, [h(App, props), h(NavigationOverlay), h(PwaInstallToast)]),
            })
                .use(plugin)
                .use(ZiggyVue, {
                    ...page.props.ziggy,
                    location: new URL(page.props.ziggy.location),
                });
        },
    }),
);
