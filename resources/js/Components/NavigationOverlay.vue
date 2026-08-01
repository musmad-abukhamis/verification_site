<script setup>
/**
 * Global page-navigation overlay.
 *
 * Inertia swaps the page component in place, so between clicking a link and
 * the response landing there is nothing on screen but the *old* page — on a
 * slow connection that reads as a dead click. This veils the page and spins
 * until the new one arrives.
 *
 * What it deliberately does not cover:
 * - **Partial reloads** (`only` / `except`). BuyData and NIN/Verify use those
 *   to refresh wallet and transaction props while you sit on the page; a
 *   blocking veil over a background refresh is a bug, not a feature.
 * - **Non-GET visits.** Form submits already disable their own button and
 *   show inline progress. Two competing indicators is worse than one.
 * - **Prefetches and `async` visits**, which are invisible by definition.
 * - Visits that opted out with `:progress="false"`.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/* Nothing is shown for a visit that resolves quicker than this. Most
   navigations here land well inside it, and a veil that flashes up for 80ms
   reads as a glitch rather than as feedback. */
const SHOW_AFTER_MS = 250;

/* Once shown, stay up at least this long. Without it, a visit finishing just
   past SHOW_AFTER_MS produces the exact flash the delay above avoids. */
const MIN_VISIBLE_MS = 400;

const visible = ref(false);

/* Counted, not boolean: a second navigation can start before the first one's
   finish event arrives, and the veil should drop only when the last is done. */
let inFlight = 0;
let showTimer = null;
let hideTimer = null;
let shownAt = 0;

const isPageNavigation = (visit) =>
    visit.method === 'get' &&
    visit.showProgress !== false &&
    !visit.prefetch &&
    !visit.async &&
    !visit.only?.length &&
    !visit.except?.length;

const onStart = (event) => {
    if (!isPageNavigation(event.detail.visit)) return;

    inFlight += 1;

    /* A new navigation cancels the previous one's pending hide, so going
       link → link → link is one continuous veil rather than a strobe. */
    clearTimeout(hideTimer);
    hideTimer = null;

    if (visible.value || showTimer) return;

    showTimer = setTimeout(() => {
        showTimer = null;
        shownAt = Date.now();
        visible.value = true;
    }, SHOW_AFTER_MS);
};

/* `finish` fires whether the visit completed, failed or was cancelled, so it
   is the only hook that can't leave the veil stuck up. Re-testing the same
   predicate is what keeps a background partial reload finishing mid-navigation
   from clearing a veil that isn't its own. */
const onFinish = (event) => {
    if (!isPageNavigation(event.detail.visit)) return;

    inFlight = Math.max(0, inFlight - 1);
    if (inFlight > 0) return;

    clearTimeout(showTimer);
    showTimer = null;

    if (!visible.value) return;

    const remaining = MIN_VISIBLE_MS - (Date.now() - shownAt);
    if (remaining <= 0) {
        visible.value = false;
        return;
    }

    hideTimer = setTimeout(() => {
        hideTimer = null;
        visible.value = false;
    }, remaining);
};

let stopStart;
let stopFinish;

onMounted(() => {
    stopStart = router.on('start', onStart);
    stopFinish = router.on('finish', onFinish);
});

onBeforeUnmount(() => {
    stopStart?.();
    stopFinish?.();
    clearTimeout(showTimer);
    clearTimeout(hideTimer);
});
</script>

<template>
    <!-- No Teleport: nothing above `#app` establishes a containing block, so
         `fixed` already escapes the layout, and staying in the tree keeps the
         SSR and client markup identical. -->
    <Transition
        enter-active-class="transition-opacity duration-150 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            class="fixed inset-0 z-[200] grid cursor-wait place-items-center bg-canvas/70 backdrop-blur-[2px] dark:bg-ink-950/70"
            role="status"
            aria-live="polite"
        >
            <!-- Swallows clicks as well as covering the page: the veil is the
                 one thing stopping an impatient second click from queueing a
                 second navigation. -->
            <svg
                class="motion-essential h-10 w-10 animate-spin"
                viewBox="0 0 40 40"
                fill="none"
                aria-hidden="true"
            >
                <circle
                    class="text-ink-200 dark:text-ink-800"
                    cx="20"
                    cy="20"
                    r="16"
                    stroke="currentColor"
                    stroke-width="4"
                />
                <path
                    class="text-brand-600 dark:text-brand-500"
                    d="M36 20a16 16 0 0 0-16-16"
                    stroke="currentColor"
                    stroke-width="4"
                    stroke-linecap="round"
                />
            </svg>
            <span class="sr-only">Loading page</span>
        </div>
    </Transition>
</template>
