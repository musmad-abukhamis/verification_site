/**
 * Service worker registration.
 *
 * Only in a production build. Under `npm run dev` the assets come from the
 * Vite server on another origin and `/build/` doesn't exist at all, so a
 * worker there caches nothing useful and only gets in the way — including
 * surviving from an earlier `npm run build` on the same origin, which is why
 * dev actively unregisters rather than just skipping.
 *
 * See public/sw.js for what is and isn't cached.
 */
export function registerServiceWorker() {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return;

    if (!import.meta.env.PROD) {
        navigator.serviceWorker
            .getRegistrations?.()
            .then((registrations) => registrations.forEach((registration) => registration.unregister()))
            .catch(() => {});

        return;
    }

    /* After `load`, so registering never competes with the first paint for
       bandwidth on the 3G connections a lot of agents are on. */
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            /* Unsupported, blocked by policy, or served over plain http —
               none of which should surface to the user. The app works
               identically without it. */
        });
    });
}
