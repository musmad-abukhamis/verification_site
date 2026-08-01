import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, Fragment, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import NavigationOverlay from './Components/NavigationOverlay.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        /* The overlay is a sibling of the page, not part of any layout, so it
           covers guest, authenticated and admin screens alike — and survives
           the page swap that a navigation is. Mirrored in ssr.js: both entries
           must render the same tree or hydration mismatches. */
        return createApp({
            render: () => h(Fragment, [h(App, props), h(NavigationOverlay)]),
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
