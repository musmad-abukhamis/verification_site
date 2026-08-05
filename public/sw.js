/**
 * ABC Services — service worker.
 *
 * Deliberately conservative. This app puts NIN and BVN records, wallet
 * balances and transaction history on screen, so the rule is:
 *
 *   **Nothing that depends on who is logged in is ever written to the cache.**
 *
 * That means no HTML, no Inertia JSON, no API responses — a shared or stolen
 * device must not be able to read a previous agent's lookups out of the Cache
 * Storage. What is cached is exactly the shell: content-hashed Vite bundles,
 * static images, icons and webfonts. All of it is public and immutable, which
 * is also what makes cache-first safe for it.
 *
 * A navigation therefore always hits the network; if the network is gone the
 * user gets the offline card instead of the browser's error page.
 *
 * Bump CACHE_VERSION whenever this file or the precache list changes — old
 * caches are dropped on activate.
 *
 * Updates install silently (`skipWaiting` + `claim`) rather than waiting for
 * every tab to close. There is no stale-asset hazard in doing so, because the
 * only thing this worker serves from cache is content-hashed: a page that
 * suddenly has a new worker still asks for the same filenames it was built
 * against, and gets them.
 */

const CACHE_VERSION = 'v1';
const SHELL_CACHE = `abc-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `abc-assets-${CACHE_VERSION}`;
const FONT_CACHE = `abc-fonts-${CACHE_VERSION}`;

const OFFLINE_URL = '/offline.html';

/* Small and stable: the offline card plus the art it references. Everything
   else fills the asset cache lazily as it is requested. */
const PRECACHE = [
    OFFLINE_URL,
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

/* Cache-first, same-origin. Every prefix here is either content-hashed by Vite
   or a static file that changes only on deploy. */
const ASSET_PREFIXES = ['/build/', '/icons/', '/images/'];
const ASSET_FILES = ['/favicon.ico', '/manifest.webmanifest'];

const FONT_ORIGIN = 'https://fonts.bunny.net';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            /* `cache: 'reload'` so a redeploy can't precache a copy the HTTP
               cache is still holding from the previous version. */
            .then((cache) =>
                cache.addAll(PRECACHE.map((url) => new Request(url, { cache: 'reload' }))),
            )
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    const keep = new Set([SHELL_CACHE, ASSET_CACHE, FONT_CACHE]);

    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => !keep.has(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

const isAsset = (url) =>
    ASSET_PREFIXES.some((prefix) => url.pathname.startsWith(prefix)) ||
    ASSET_FILES.includes(url.pathname);

/**
 * Cache-first with a background fill. Safe only for immutable, public files —
 * see the note at the top of this file before adding anything here.
 */
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const hit = await cache.match(request);
    if (hit) return hit;

    const response = await fetch(request);

    /* `response.ok` is false for opaque cross-origin responses (status 0),
       which is what the font CDN returns — those are still worth keeping, so
       they are allowed through explicitly. A same-origin 404 is not. */
    if (response.ok || response.type === 'opaque') {
        cache.put(request, response.clone());
    }

    return response;
}

/**
 * Network-only, with the offline card as the failure branch. Nothing here is
 * ever written to a cache.
 */
async function networkOnlyWithOfflineCard(request) {
    try {
        return await fetch(request);
    } catch {
        const cache = await caches.open(SHELL_CACHE);
        const offline = await cache.match(OFFLINE_URL);
        return (
            offline ??
            new Response('You are offline.', {
                status: 503,
                headers: { 'Content-Type': 'text/plain; charset=utf-8' },
            })
        );
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    /* Anything that mutates state goes straight to the network. A queued
       replay would be worse than a visible failure: these are real purchases
       and wallet debits. */
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin === FONT_ORIGIN) {
        event.respondWith(cacheFirst(request, FONT_CACHE));
        return;
    }

    if (url.origin !== self.location.origin) return;

    if (isAsset(url)) {
        event.respondWith(cacheFirst(request, ASSET_CACHE));
        return;
    }

    /* Full document loads only. Inertia's own XHR visits are mode 'cors' and
       fall through untouched, which is what keeps page *data* off the disk
       while still giving a real offline screen. */
    if (request.mode === 'navigate') {
        event.respondWith(networkOnlyWithOfflineCard(request));
    }
});
