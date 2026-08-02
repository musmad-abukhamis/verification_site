<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    wallet: Object,
    recent_transactions: Array,
    reserved_accounts: { type: Array, default: () => [] },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? {});
const copied = ref(null);

/**
 * The dashboard runs its own canvas — cool grey, not the sage the rest of
 * the app sits on. Painting <body> is the only way to reach behind the
 * layout's <main>, and removing it on unmount means the sage canvas is back
 * the instant you navigate away.
 */
onMounted(() => document.body.classList.add('dash-canvas'));
onUnmounted(() => document.body.classList.remove('dash-canvas'));

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const copy = async (value) => {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = value;
        setTimeout(() => (copied.value = null), 1500);
    } catch (e) {
        // clipboard unavailable; ignore
    }
};

const getTypeLabel = (type) => {
    const labels = {
        airtime: 'Airtime Purchase',
        data: 'Data Purchase',
        nin_verification: 'NIN Verification',
        bvn_verification: 'BVN Verification',
        wallet_funding: 'Wallet Funding',
        refund: 'Refund',
    };
    return labels[type] || type;
};

/** Mirrors StatusPill's vocabulary — several tables spell these differently. */
const TONES = {
    success: 'success', successful: 'success', completed: 'success', complete: 'success',
    approved: 'success', delivered: 'success', active: 'success', verified: 'success',
    credit: 'success', paid: 'success',
    pending: 'pending', queued: 'pending', awaiting: 'pending', submitted: 'pending',
    unconfirmed: 'pending',
    processing: 'info', inprogress: 'info', in_progress: 'info', running: 'info', sent: 'info',
    fail: 'danger', failed: 'danger', error: 'danger', rejected: 'danger', declined: 'danger',
    cancelled: 'danger', canceled: 'danger', inactive: 'danger', debit: 'danger',
    refunded: 'refund', refunded_unconfirmed: 'refund', refund: 'refund', reversed: 'refund',
};

const toneOf = (status) => TONES[String(status ?? '').toLowerCase().trim()] ?? 'neutral';
const statusText = (status) => String(status ?? '').replace(/_/g, ' ') || '—';

/* ---- Icons ----------------------------------------------------------
   Inline stroke paths, drawn at whatever size the class calls for. Kept
   here rather than in a component so a card can hand the same path to both
   its arrow button and its 104px watermark. */
const ICON = {
    /* Quick dots */
    verify: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    data: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
    fund: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',

    /* Service cards. Each one is the metaphor for what the service does,
       not for the family it belongs to — the spine colour already says
       that, and thirteen fingerprints would tell you nothing apart. */
    validation: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    clearance: 'M8 11V7a4 4 0 018 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z',
    search: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    bvn: 'M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4',
    edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    enrol: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
    retrieve: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
    idcard: 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z',
    records: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',

    /* Chrome */
    bolt: 'M13 10V3L4 14h7v7l9-11h-7z',
    arrow: 'M9 5l7 7-7 7',
};

/**
 * The three quick dots. This app has no airtime product, so the third slot
 * goes to wallet funding — the other action people arrive here to take —
 * and keeps the purple of the spec's third dot.
 */
const quickDots = [
    { key: 'verify', tone: 'qd-verify', label: 'Verify', route: 'nin.verify.index', icon: ICON.verify },
    { key: 'data', tone: 'qd-data', label: 'Data', route: 'buy-data', icon: ICON.data },
    { key: 'fund', tone: 'qd-fund', label: 'Fund', route: 'wallet.fund', icon: ICON.fund },
];

/**
 * One card per service. The spine colour is what carries the family, so
 * nothing has to be boxed together to show that Search and Retrieval are
 * both BVN work.
 *
 * BVN Modification, Onboarding, Retrieval and ID Card all write a pending
 * record for an admin to fulfil by hand rather than returning a result there
 * and then. That used to be flagged with a "Needs review" chip; the chip was
 * dropped on 2026-08-01 at the user's request — on a 2-up phone it was the
 * widest thing in the card and wrapped to its own line. Each of those pages
 * says so itself when you land on it.
 */
/* `data` and `account` currently have no cards — the grid is NIN and BVN work
   only. Both are kept so a future service can join a family that already has
   a colour, rather than inventing a fifth. */
const FAMILY = {
    data: { label: 'Data', colour: '#0f9d58' },
    nin: { label: 'NIN', colour: '#1d6ef5' },
    bvn: { label: 'BVN', colour: '#7a3ff2' },
    account: { label: 'Account', colour: '#f79009' },
};

/*
 * Buy data and Fund wallet are quick dots and so stay out of the grid; Wallet
 * history is the "View all" on Recent activity; Profile lives in the account
 * menu in the header. Listing them twice would only make the grid longer
 * without making anything easier to reach.
 *
 * NIN verify is the deliberate exception, added 2026-08-02. It is also the
 * first quick dot, but the dot is a bare icon: this grid is where the two
 * verification services read as a pair, and having BVN verify sitting alone
 * there implied NIN verification lived somewhere else entirely.
 *
 * BVN search left the grid in the same change. It renders the same record as
 * BVN verify with a slip attached, so two adjacent cards were asking people to
 * pick between things they could not tell apart. It is still in the sidebar.
 *
 * Data pricing, My purchases, Verify history and API access were removed on
 * 2026-08-01 for a different reason: the grid is for things you *do*, and those
 * four are things you *read*. They're all still one click away in the sidebar.
 * Don't add them back.
 */
const SERVICES = [
    // The two instant lookups lead: they are the most-used services and the
    // only ones that answer while the user is still on the page. Everything
    // below them either takes hours to come back or is a longer form to fill.
    { title: 'NIN verify', subtitle: 'Check a NIN against the register.', family: 'nin', icon: ICON.verify, route: 'nin.verify.index' },
    { title: 'BVN verify', subtitle: 'Check a BVN against the register.', family: 'bvn', icon: ICON.bvn, route: 'bvn-verify.index' },

    { title: 'NIN validation', subtitle: 'Submit a NIN for validation (3–7 days).', family: 'nin', icon: ICON.validation, route: 'nin.validation.index' },
    { title: 'IPE clearance', subtitle: 'Clear a record stuck in IPE (30min–3hrs).', family: 'nin', icon: ICON.clearance, route: 'nin.ipe.index' },

    { title: 'BVN modification', subtitle: 'Correct a name, date or phone.', family: 'bvn', icon: ICON.edit, route: 'bvn-modification.index' },
    { title: 'BVN onboarding', subtitle: 'Enrol someone new by SDK form.', family: 'bvn', icon: ICON.enrol, route: 'bvn-sdk-form.index' },
    { title: 'BVN retrieval', subtitle: 'Recover a BVN from a BMS ticket.', family: 'bvn', icon: ICON.retrieve, route: 'bvn-retrieval.index' },
    { title: 'ID card', subtitle: 'Order a printed agent ID card.', family: 'bvn', icon: ICON.idcard, route: 'idcard.index' },
    { title: 'Enrollment records', subtitle: 'Search uploaded enrollment data.', family: 'bvn', icon: ICON.records, route: 'bvn-records.index' },
];

const services = SERVICES.map((service) => ({
    ...service,
    colour: FAMILY[service.family].colour,
    familyLabel: FAMILY[service.family].label,
}));

const primaryAccount = computed(() => props.reserved_accounts?.[0] ?? null);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="dash space-y-3 sm:space-y-4">
            <!-- ============================================================
                 Greeting
                 ============================================================ -->
            <header class="px-1">
                <p class="dash-eyebrow">Overview</p>
                <h1 class="dash-h mt-1 text-xl font-bold sm:text-2xl" style="color: var(--d-ink)">
                    Welcome back, {{ authUser.username || authUser.name }}
                </h1>
            </header>

            <!-- `grid-cols-1` is not redundant with plain `grid`. Without a
                 column template this grid gets one *implicit* track, sized
                 `auto` — which never shrinks below its content's min-content
                 width, so the unbreakable balance figure widened the track and
                 both panels in it stretched past the viewport. `grid-cols-1`
                 is `repeat(1, minmax(0, 1fr))`, and that `0` is the fix. -->
            <div class="grid grid-cols-1 gap-3 sm:gap-4 lg:grid-cols-3">
                <!-- ========================================================
                     Balance + funding accounts — the first thing on the page
                     in every layout, so DOM order and visual order agree and
                     no `order-*` juggling is needed.
                     ======================================================== -->
                <section class="dash-panel lg:col-span-2">
                    <!-- Every box down this branch carries `min-w-0`. A flex item
                         defaults to `min-width: auto`, and a ₦ figure has no break
                         opportunity in it, so without these the figure sets a floor
                         on the row's width that the panel can't get under. -->
                    <div class="flex min-w-0 flex-wrap items-end justify-between gap-x-6 gap-y-4">
                        <div class="min-w-0">
                            <p class="dash-eyebrow">Total balance</p>
                            <p class="dash-figure mt-1.5 text-[1.75rem] sm:text-[2rem]">
                                {{ formatCurrency(wallet.total_balance) }}
                            </p>
                        </div>

                        <div class="flex min-w-0 gap-6">
                            <div class="min-w-0">
                                <p class="dash-eyebrow">Main</p>
                                <p class="dash-figure mt-1 text-sm">{{ formatCurrency(wallet.balance) }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="dash-eyebrow">Bonus</p>
                                <p class="dash-figure mt-1 text-sm">{{ formatCurrency(wallet.bonus_balance) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-dashed pt-4" style="border-color: var(--d-line)">
                        <div class="flex items-center justify-between gap-3">
                            <p class="dash-eyebrow">Funding accounts</p>
                            <Link
                                :href="route('wallet.fund')"
                                class="text-xs font-semibold"
                                style="color: #1d6ef5"
                            >
                                Manage
                            </Link>
                        </div>

                        <div v-if="reserved_accounts.length" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div
                                v-for="acct in reserved_accounts"
                                :key="acct.account_number"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5"
                                style="background: var(--d-chip)"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="dash-figure text-base tracking-wide">{{ acct.account_number }}</p>
                                    <p class="truncate text-[11px]" style="color: var(--d-muted)">
                                        {{ acct.bank }} · {{ acct.account_name }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="ws-chip"
                                    style="--svc: #1d6ef5; background: var(--d-card)"
                                    @click="copy(acct.account_number)"
                                >
                                    {{ copied === acct.account_number ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                        </div>

                        <p v-else class="mt-2 text-xs" style="color: var(--d-muted)">
                            You don't have a funding account yet. Set one up to top up by transfer.
                        </p>
                    </div>
                </section>

                <!-- ========================================================
                     Quick dots
                     ======================================================== -->
                <section class="dash-panel">
                    <div class="qd-row">
                        <Link
                            v-for="dot in quickDots"
                            :key="dot.key"
                            :href="route(dot.route)"
                            class="qd"
                            :class="dot.tone"
                        >
                            <span class="qd-circle">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        :d="dot.icon"
                                    />
                                </svg>
                            </span>
                            <span class="qd-label">{{ dot.label }}</span>
                        </Link>
                    </div>

                    <!-- The strip under the dashed rule. Points at the fastest
                         way to get money in, using the account they already
                         have rather than a promotion this app doesn't run. -->
                    <div class="qd-strip">
                        <span class="qd-strip-badge">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="ICON.bolt" />
                            </svg>
                        </span>

                        <p v-if="primaryAccount" class="qd-strip-text">
                            Transfer to <b>{{ primaryAccount.account_number }}</b> and your wallet credits instantly.
                        </p>
                        <p v-else class="qd-strip-text">
                            <Link :href="route('wallet.fund')"><b>Set up an account number</b></Link>
                            and any transfer lands in your wallet automatically.
                        </p>
                    </div>
                </section>
            </div>

            <!-- ============================================================
                 Watermark spine — service families
                 ============================================================ -->
            <div class="grid grid-cols-2 gap-2.5 sm:gap-3 md:grid-cols-3">
                <!-- The whole card is the link — nothing inside it is
                     separately clickable, so the tap target is the card. -->
                <Link
                    v-for="service in services"
                    :key="service.route"
                    :href="route(service.route)"
                    class="ws"
                    :style="{ '--svc': service.colour }"
                >
                    <svg class="ws-mark" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="service.icon" />
                    </svg>

                    <span class="ws-body">
                        <span class="ws-title">{{ service.title }}</span>
                        <span class="ws-sub">{{ service.subtitle }}</span>

                        <span class="ws-chips">
                            <span class="ws-chip">{{ service.familyLabel }}</span>
                        </span>
                    </span>

                    <span class="ws-go">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="ICON.arrow" />
                        </svg>
                    </span>
                </Link>
            </div>

            <!-- ============================================================
                 Recent activity
                 ============================================================ -->
            <section class="dash-panel">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="dash-h text-sm font-bold" style="color: var(--d-ink)">Recent activity</h2>
                    <Link
                        :href="route('wallet.transactions')"
                        class="text-xs font-semibold"
                        style="color: #1d6ef5"
                    >
                        View all
                    </Link>
                </div>

                <div v-if="recent_transactions.length" class="dash-divide mt-1">
                    <div
                        v-for="transaction in recent_transactions"
                        :key="transaction.id"
                        class="flex items-center gap-3 py-3"
                    >
                        <span class="dash-dot" :class="`dash-dot-${toneOf(transaction.status)}`" aria-hidden="true"></span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold" style="color: var(--d-ink)">
                                {{ getTypeLabel(transaction.type) }}
                            </p>
                            <p class="truncate text-[11px]" style="color: var(--d-muted)">
                                {{ transaction.reference }} · {{ transaction.date }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="dash-figure text-[13px]">{{ formatCurrency(transaction.amount) }}</p>
                            <span class="dash-tag mt-1" :class="`dash-tag-${toneOf(transaction.status)}`">
                                {{ statusText(transaction.status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-else class="py-8 text-center">
                    <p class="text-sm font-semibold" style="color: var(--d-ink)">Nothing here yet</p>
                    <p class="mx-auto mt-1 max-w-xs text-xs" style="color: var(--d-muted)">
                        Your purchases, verifications and wallet movements show up here as you work.
                    </p>
                    <Link
                        :href="route('buy-data')"
                        class="qd-label mt-3 inline-block rounded-lg px-3 py-2"
                        style="background: linear-gradient(145deg, #22c55e, #0f9d58); color: #fff"
                    >
                        Buy data
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
