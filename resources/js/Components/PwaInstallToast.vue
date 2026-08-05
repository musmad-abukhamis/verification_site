<script setup>
/**
 * "Install this app" toast.
 *
 * Sits bottom-centre on a phone and bottom-right on a desktop, and only ever
 * appears when an install is genuinely possible — see `usePwaInstall` for what
 * that means per platform. On Chromium it drives the real prompt; on iOS
 * Safari, which has no programmatic install, it explains the share-sheet route
 * instead of pretending to have a button.
 *
 * Rendered as a sibling of the page in app.js/ssr.js, exactly like
 * NavigationOverlay, so it survives Inertia's page swap and shows on guest,
 * authenticated and admin screens alike.
 *
 * Deliberately quiet about it:
 * - waits a few seconds so it never lands on a page that is still painting
 * - "Not now" buys two weeks of silence, "Don't ask again" buys forever
 * - z-35 sits above page content but below the mobile sidebar's scrim (z-40),
 *   the announcement modal (z-100) and the navigation veil (z-200), so opening
 *   any of those dims or covers the nudge instead of the nudge covering them.
 */
import { ref, watch } from 'vue';
import { usePwaInstall } from '@/composables/usePwaInstall';

/* Long enough for the first meaningful paint and for the eye to settle on the
   page, short enough that it still reads as part of arriving. */
const { method, shouldAsk, promptInstall, snooze, decline } = usePwaInstall({ delay: 4000 });

const busy = ref(false);

/* Own visibility separately from `shouldAsk` so the leave transition can run:
   accepting flips the underlying state to false immediately, and animating out
   from a node that has already been torn down doesn't work. */
const open = ref(false);
watch(shouldAsk, (value) => {
    if (value) open.value = true;
}, { immediate: true });

const install = async () => {
    busy.value = true;
    const outcome = await promptInstall();
    busy.value = false;

    /* Accepted or dismissed, the toast has said its piece. `promptInstall`
       has already snoozed a dismissal; `appinstalled` handles the rest. */
    open.value = false;
    return outcome;
};

const notNow = () => {
    open.value = false;
    snooze();
};

const never = () => {
    open.value = false;
    decline();
};
</script>

<template>
    <!-- No Teleport, for the same reason NavigationOverlay avoids it: nothing
         above #app establishes a containing block, so `fixed` already escapes
         the layout, and staying in the tree keeps SSR and client markup
         identical.

         The card is sized by the fixed wrapper (`sm:w-[22.5rem]`) rather than
         the other way round: a shrink-to-fit fixed box whose only child is
         `w-full` resolves from max-content, which is not worth relying on. -->
    <Transition
        enter-active-class="transition duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]"
        enter-from-class="translate-y-6 opacity-0 sm:translate-y-4"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="open"
            class="fixed inset-x-0 bottom-0 z-[35] flex justify-center px-3
                   pb-[calc(0.75rem+env(safe-area-inset-bottom))]
                   sm:inset-x-auto sm:bottom-6 sm:right-6 sm:w-[22.5rem] sm:px-0 sm:pb-6"
            role="dialog"
            aria-modal="false"
            aria-labelledby="pwa-install-title"
        >
            <div
                class="relative w-full max-w-md overflow-hidden rounded-card border border-ink-200
                       bg-white shadow-pop dark:border-ink-800 dark:bg-ink-900 sm:max-w-[22.5rem]"
            >
                <!-- The spine. Same device the dashboard uses to mark a card as
                     belonging to something: colour on the edge, not the fill. -->
                <span
                    class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-brand-400 via-brand-600 to-brand-800"
                    aria-hidden="true"
                ></span>

                <button
                    type="button"
                    class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-lg text-ink-400
                           transition hover:bg-ink-100 hover:text-ink-700
                           dark:hover:bg-ink-800 dark:hover:text-ink-100"
                    aria-label="Dismiss install prompt"
                    @click="notNow"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>

                <div class="p-4 pl-5">
                    <div class="flex items-start gap-3">
                        <div class="relative shrink-0">
                            <img
                                src="/icons/icon-192.png"
                                alt=""
                                width="44"
                                height="44"
                                class="h-11 w-11 rounded-xl border border-ink-200 bg-white object-contain p-px shadow-sm dark:border-ink-700"
                            />
                            <!-- Reads as "new / available", the same dot the
                                 notification bell uses. -->
                            <span
                                class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-brand-600 ring-2 ring-white dark:ring-ink-900"
                                aria-hidden="true"
                            ></span>
                        </div>

                        <div class="min-w-0 pr-6">
                            <p class="eyebrow">Install app</p>
                            <h2
                                id="pwa-install-title"
                                class="mt-1 font-display text-[0.9375rem] font-semibold leading-snug text-ink-950 dark:text-white"
                            >
                                Keep ABC Services one tap away
                            </h2>
                            <p class="mt-1 text-[13px] leading-relaxed text-ink-500 dark:text-ink-400">
                                <template v-if="method === 'ios'">
                                    Add it to your Home Screen and it opens like
                                    any other app &mdash; full screen, no address bar.
                                </template>
                                <template v-else>
                                    Install it to your device and run verifications
                                    full screen, straight from your home screen.
                                </template>
                            </p>
                        </div>
                    </div>

                    <!-- Quick dots: the dashboard's shorthand for "three facts,
                         no table". -->
                    <ul class="mt-3 flex flex-wrap items-center gap-x-3.5 gap-y-1.5 pl-[3.5rem] text-[11px] font-medium text-ink-500 dark:text-ink-400">
                        <li class="inline-flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-500" aria-hidden="true"></span>
                            Full screen
                        </li>
                        <li class="inline-flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-success-600" aria-hidden="true"></span>
                            Faster loads
                        </li>
                        <li class="inline-flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-brass-500" aria-hidden="true"></span>
                            No browser bar
                        </li>
                    </ul>

                    <!-- iOS: there is no button to give them, only the route. -->
                    <p
                        v-if="method === 'ios'"
                        class="mt-4 flex items-center gap-2 rounded-lg border border-dashed border-ink-300 bg-canvas
                               px-3 py-2.5 text-[12.5px] leading-snug text-ink-600
                               dark:border-ink-700 dark:bg-ink-950 dark:text-ink-300"
                    >
                        <span class="shrink-0">Tap</span>
                        <svg class="h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 15V3" />
                            <path d="M8 7l4-4 4 4" />
                            <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7" />
                        </svg>
                        <span>
                            <span class="sr-only">Share, </span>then
                            <strong class="font-semibold text-ink-900 dark:text-white">Add to Home Screen</strong>
                        </span>
                    </p>

                    <div class="mt-4 flex items-center gap-2">
                        <button
                            v-if="method === 'prompt'"
                            type="button"
                            class="btn btn-primary flex-1 py-2 text-[13px]"
                            :disabled="busy"
                            @click="install"
                        >
                            <svg v-if="!busy" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3v12" />
                                <path d="M8 11l4 4 4-4" />
                                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                            </svg>
                            <svg v-else class="motion-essential h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-30" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                            {{ busy ? 'Opening…' : 'Install' }}
                        </button>

                        <button
                            type="button"
                            class="btn btn-ghost py-2 text-[13px]"
                            :class="method === 'ios' ? 'flex-1' : ''"
                            @click="notNow"
                        >
                            Not now
                        </button>

                        <button
                            type="button"
                            class="rounded-lg px-2 py-2 text-[11.5px] font-medium text-ink-400 underline-offset-2
                                   transition hover:text-ink-600 hover:underline dark:hover:text-ink-200"
                            @click="never"
                        >
                            Don&rsquo;t ask
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
