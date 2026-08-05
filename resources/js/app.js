import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, Fragment, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import NavigationOverlay from './Components/NavigationOverlay.vue';
import PwaInstallToast from './Components/PwaInstallToast.vue';
import { registerServiceWorker } from './pwa';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        /* The overlay and the install toast are siblings of the page, not part
           of any layout, so they cover guest, authenticated and admin screens
           alike — and survive the page swap that a navigation is. Mirrored in
           ssr.js: both entries must render the same tree or hydration
           mismatches. */
        return createApp({
            render: () => h(Fragment, [h(App, props), h(NavigationOverlay), h(PwaInstallToast)]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    /* Kept alongside the overlay: the bar is the only feedback during the
       250ms before the veil appears, which is most navigations. */
    progress: {
        color: '#155EEF', // brand-600 — the same blue every other in-flight action uses
    },
});

/* After createInertiaApp, not before: registering the worker is the least
   urgent thing on the page and shouldn't delay the first render. */
registerServiceWorker();
