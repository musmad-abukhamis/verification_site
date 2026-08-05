import { computed, onBeforeUnmount, onMounted, readonly, ref } from 'vue';

/**
 * Everything the app knows about "can this be installed, and should we ask".
 *
 * Two very different platforms hide behind one API here:
 *
 * - **Chromium** (Android, desktop Chrome/Edge) fires `beforeinstallprompt`,
 *   which we hold on to and replay when the user says yes. The browser only
 *   fires it when the site actually qualifies — manifest, icons, service
 *   worker, HTTPS — so its presence is the honest signal that an install is
 *   possible, and we never guess.
 * - **iOS Safari** has no such event and no programmatic install at all. The
 *   only route is Share → Add to Home Screen, so there we detect the platform
 *   and show instructions instead of a button.
 *
 * Firefox and iOS Chrome/Firefox get nothing, which is correct: neither can
 * install, and a prompt they can't act on is just noise.
 */

/* ~2 weeks. Long enough that "Not now" means not now rather than "ask me
   tomorrow", short enough that someone who declines once still discovers the
   app eventually. */
const SNOOZE_DAYS = 14;

const SNOOZE_KEY = 'pwa:install-snoozed-until';
const DECLINED_KEY = 'pwa:install-declined';

/* Module scope, not per-component: `beforeinstallprompt` fires once per page
   load, so the captured event has to outlive any single component and be
   shared by every consumer of this composable. */
const deferred = ref(null);
const installed = ref(false);
const suppressed = ref(false);

let bootstrapped = false;

const isBrowser = () => typeof window !== 'undefined';

/** Reading storage throws in Safari private mode — never let that break a page. */
function readStorage(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        /* Non-fatal: the prompt just reappears next session. */
    }
}

function clearStorage(key) {
    try {
        window.localStorage.removeItem(key);
    } catch {
        /* as above */
    }
}

/** Already running as an installed app — in which case, never ask. */
function isStandalone() {
    if (!isBrowser()) return false;

    return (
        window.matchMedia?.('(display-mode: standalone)').matches === true ||
        window.matchMedia?.('(display-mode: minimal-ui)').matches === true ||
        window.navigator.standalone === true // iOS
    );
}

function isIosSafari() {
    if (!isBrowser()) return false;

    const ua = window.navigator.userAgent;

    const ios =
        /iphone|ipad|ipod/i.test(ua) ||
        /* iPadOS 13+ reports itself as a Mac; the touch points give it away. */
        (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);

    if (!ios) return false;

    /* Every iOS browser is Safari underneath, but only Safari's share sheet
       carries "Add to Home Screen", so the instructions are wrong anywhere
       else. Chrome/Firefox/Edge/Opera on iOS all brand their UA. */
    return !/crios|fxios|edgios|opios|opt\//i.test(ua);
}

function snoozedUntil() {
    const raw = readStorage(SNOOZE_KEY);
    if (!raw) return 0;

    const at = Number(raw);
    return Number.isFinite(at) ? at : 0;
}

/** Wires the window-level listeners exactly once per page load. */
function bootstrap() {
    if (bootstrapped || !isBrowser()) return;
    bootstrapped = true;

    installed.value = isStandalone();
    suppressed.value = readStorage(DECLINED_KEY) === '1' || Date.now() < snoozedUntil();

    /* The event usually fires before Vue has mounted anything, so a small
       inline script in the layout head catches it first and re-announces it as
       `pwa:installable`. Both paths are handled: whichever arrives first wins,
       and the other is a no-op. */
    const adopt = () => {
        deferred.value = window.__pwaInstallEvent ?? deferred.value;
    };

    adopt();
    window.addEventListener('pwa:installable', adopt);

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred.value = event;
    });

    window.addEventListener('appinstalled', () => {
        installed.value = true;
        deferred.value = null;
        window.__pwaInstallEvent = null;
        /* They installed it — a future "not now" shouldn't still be in effect
           if they ever uninstall and come back. */
        clearStorage(SNOOZE_KEY);
        clearStorage(DECLINED_KEY);
    });
}

export function usePwaInstall({ delay = 0 } = {}) {
    /* Gates the toast on a short settle period so it doesn't land on top of a
       page that is still painting. Not part of `canInstall`, so a manual
       "Install app" button elsewhere can ignore it. */
    const elapsed = ref(delay === 0);
    let timer = null;

    onMounted(() => {
        bootstrap();

        if (delay > 0) {
            timer = window.setTimeout(() => {
                elapsed.value = true;
            }, delay);
        }
    });

    onBeforeUnmount(() => {
        if (timer) window.clearTimeout(timer);
    });

    /** 'prompt' — we hold a real event. 'ios' — instructions only. null — no. */
    const method = computed(() => {
        if (installed.value) return null;
        if (deferred.value) return 'prompt';
        if (isIosSafari()) return 'ios';
        return null;
    });

    const canInstall = computed(() => method.value !== null);
    const shouldAsk = computed(() => canInstall.value && !suppressed.value && elapsed.value);

    /**
     * Replays the captured prompt. Resolves to the user's choice, or null on
     * iOS / when there is nothing to replay.
     *
     * The event is single-use: once shown it is discarded either way, because
     * Chromium will not let the same one be shown twice.
     */
    async function promptInstall() {
        const event = deferred.value;
        if (!event) return null;

        deferred.value = null;
        window.__pwaInstallEvent = null;

        try {
            await event.prompt();
            const { outcome } = await event.userChoice;

            /* A dismissed native prompt is a soft no — snooze rather than
               silence it forever, since the toast is now the only way back. */
            if (outcome !== 'accepted') snooze();

            return outcome;
        } catch {
            return null;
        }
    }

    /** "Not now" — hide, and stay hidden for SNOOZE_DAYS. */
    function snooze() {
        suppressed.value = true;
        writeStorage(SNOOZE_KEY, String(Date.now() + SNOOZE_DAYS * 86_400_000));
    }

    /** "Don't ask again" — hide for good on this device. */
    function decline() {
        suppressed.value = true;
        writeStorage(DECLINED_KEY, '1');
    }

    return {
        method,
        canInstall,
        shouldAsk,
        installed: readonly(installed),
        promptInstall,
        snooze,
        decline,
    };
}
