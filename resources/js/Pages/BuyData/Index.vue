<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { SignalIcon } from '@heroicons/vue/24/outline';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import Swal from 'sweetalert2';
import { usePhoneNetworkHint } from '@/composables/usePhoneNetworkHint';

const props = defineProps({
    networks: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
    prefixMap: { type: Object, default: () => ({}) },
    balance: { type: Number, default: 0 },
    lastPurchase: { type: Object, default: null },
    beneficiaries: { type: Array, default: () => [] },
    transaction: { type: Object, default: null },
});

/* ---------------------------------------------------------------- selection */
const selectedNetwork = ref(props.lastPurchase?.network || props.networks[0]?.value || 'mtn');
const selectedType = ref('');
const selectedPlanId = ref(null);
const phone = ref('');
const ported = ref(false);
const manualNetwork = ref(false);

// The operators' own colours — the one place non-system colour is allowed,
// because these are marks a user recognises before they read the label.
const brandTint = {
    mtn: 'text-warning-500',
    airtel: 'text-danger-600',
    glo: 'text-success-600',
    '9mobile': 'text-success-700',
};

const money = (n) => '₦' + Number(n ?? 0).toLocaleString('en-NG', { minimumFractionDigits: 0 });

/* ------------------------------------------------------------- prefix hints */
const hint = usePhoneNetworkHint(phone, () => props.prefixMap, selectedNetwork, ported);

// Suggestion only: adopt the detected network until the user manually taps a tab.
watch(
    () => hint.detected.value,
    (detected) => {
        if (detected && !manualNetwork.value && !ported.value) {
            selectedNetwork.value = detected;
        }
    }
);

const pickNetwork = (value) => {
    selectedNetwork.value = value;
    manualNetwork.value = true;
    selectedPlanId.value = null;
};

/* ------------------------------------------------------------------- plans  */
const typesForNetwork = computed(() => {
    const seen = new Map();
    for (const p of props.plans) {
        if (p.network !== selectedNetwork.value) continue;
        if (!seen.has(p.type)) seen.set(p.type, { type: p.type, available: false });
        if (p.available) seen.get(p.type).available = true;
    }
    return [...seen.values()];
});

// Keep a valid type selected whenever the network changes.
watch(
    [selectedNetwork, typesForNetwork],
    () => {
        const types = typesForNetwork.value;
        if (!types.some((t) => t.type === selectedType.value)) {
            selectedType.value = (types.find((t) => t.available) || types[0])?.type || '';
        }
    },
    { immediate: true }
);

const filteredPlans = computed(() =>
    props.plans.filter(
        (p) => p.network === selectedNetwork.value && p.type === selectedType.value
    )
);

const selectedPlan = computed(() =>
    props.plans.find((p) => p.id === selectedPlanId.value) || null
);

/* --------------------------------------------------------------- beneficiary */
const pickBeneficiary = (b) => {
    phone.value = b.phone;
    ported.value = !!b.is_ported;
    selectedNetwork.value = b.network;
    manualNetwork.value = true;
};

/* ----------------------------------------------------------------- purchase */
const form = useForm({
    network: '',
    plan_id: null,
    phone: '',
    ported: false,
    client_ref: '',
});

const cleanPhone = computed(() => phone.value.replace(/\D+/g, ''));
const canSubmit = computed(
    () => selectedPlan.value && cleanPhone.value.length === 11 && !form.processing
);

const uuid = () =>
    (typeof crypto !== 'undefined' && crypto.randomUUID)
        ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
              const r = (Math.random() * 16) | 0;
              return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
          });

const submit = async (plan = selectedPlan.value) => {
    if (!plan || cleanPhone.value.length !== 11) return;

    const suffix = ported.value ? ' (ported number)' : '';
    const result = await Swal.fire({
        title: 'Confirm purchase',
        html: `Buy <b>${plan.name}</b> for <b>${money(plan.price)}</b> to <b>${cleanPhone.value}</b>${suffix}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Buy now',
        confirmButtonColor: '#155EEF',
    });

    if (!result.isConfirmed) return;

    form.network = selectedNetwork.value;
    form.plan_id = plan.id;
    form.phone = cleanPhone.value;
    form.ported = ported.value;
    form.client_ref = uuid();

    form.post(route('buy-data.store'), {
        preserveScroll: true,
        onError: (errors) => {
            Swal.fire({
                title: 'Could not start purchase',
                text: Object.values(errors)[0] || 'Please check the form and try again.',
                icon: 'error',
            });
        },
    });
};

/* ----------------------------------------------------------- "buy again"    */
const buyAgain = () => {
    if (!props.lastPurchase) return;
    selectedNetwork.value = props.lastPurchase.network;
    phone.value = props.lastPurchase.phone;
    manualNetwork.value = true;
    const plan = props.plans.find((p) => p.id === props.lastPurchase.plan_id);
    if (plan) {
        selectedPlanId.value = plan.id;
        selectedType.value = plan.type;
        submit(plan);
    }
};

/* --------------------------------------------------------- status polling   */
let poll = null;
let pollingRef = null;
let failedTicks = 0;
let announcedRef = null;

const stopPolling = () => {
    if (poll) {
        clearInterval(poll);
        poll = null;
    }
    pollingRef = null;
    failedTicks = 0;
};

const showResult = (txn) => {
    if (announcedRef === txn.reference) return;
    announcedRef = txn.reference;
    const icon = txn.status === 'success' ? 'success' : (txn.status === 'fail' ? 'error' : 'info');
    Swal.fire({ title: txn.status === 'success' ? 'Success' : 'Update', text: txn.message, icon });
};

const syncProps = () => router.reload({ only: ['transaction', 'balance', 'lastPurchase'] });

// Poll the lightweight JSON endpoint (not a full Inertia reload) until the
// transaction reaches a terminal state, then sync the page props once.
const startPolling = (reference) => {
    if (poll && pollingRef === reference) return;
    stopPolling();
    pollingRef = reference;
    poll = setInterval(async () => {
        try {
            // Relative URL (absolute=false) so a mis-detected scheme/host
            // behind the production proxy can't break the request.
            const res = await fetch(route('buy-data.status', reference, false), {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const txn = await res.json();
            failedTicks = 0;
            if (txn.terminal) {
                stopPolling();
                showResult(txn);
                syncProps();
            }
        } catch {
            // Transient hiccups just retry; if the JSON endpoint keeps
            // failing (expired session, proxy quirks), periodically fall
            // back to an Inertia partial reload — the transaction watcher
            // below will then announce the terminal state.
            if (++failedTicks % 4 === 0) syncProps();
        }
    }, 1500);
};

const track = (txn) => {
    if (!txn) {
        stopPolling();
        return;
    }
    if (txn.terminal) {
        stopPolling();
        showResult(txn);
    } else {
        startPolling(txn.reference);
    }
};

onMounted(() => track(props.transaction));
watch(() => props.transaction, track);

onBeforeUnmount(stopPolling);

const statusColor = computed(() => {
    switch (props.transaction?.status) {
        case 'success':
            return 'text-success-700 dark:text-success-400';
        case 'refunded':
        case 'refunded_unconfirmed':
            return 'text-refund-700 dark:text-refund-300';
        case 'fail':
            return 'text-danger-700 dark:text-danger-400';
        default:
            return 'text-info-700 dark:text-info-300';
    }
});
</script>

<template>
    <Head title="Buy Data" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- ============================ STATUS VIEW =========================== -->
            <!-- A completed purchase is a receipt, so it is shown as one. -->
            <section v-if="transaction" class="slip">
                <div class="slip-guilloche flex flex-col items-center rounded-t-card px-6 pb-6 pt-8 text-center">
                    <svg
                        v-if="!transaction.terminal"
                        class="mb-4 h-10 w-10 animate-spin text-brand-700 dark:text-brand-300"
                        viewBox="0 0 24 24" fill="none"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>

                    <p class="eyebrow">Data purchase</p>
                    <h1 class="mt-1 font-display text-2xl font-bold capitalize tracking-tight" :class="statusColor">
                        {{ transaction.terminal ? transaction.status.replace('_', ' ') : 'Processing…' }}
                    </h1>
                    <p class="mt-2 max-w-sm text-sm text-ink-500 dark:text-ink-400">{{ transaction.message }}</p>
                </div>

                <dl class="slip-tear divide-y rule">
                    <div class="flex items-center justify-between gap-4 px-6 py-3">
                        <dt class="eyebrow">Plan</dt>
                        <dd class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ transaction.plan_name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-6 py-3">
                        <dt class="eyebrow">Amount</dt>
                        <dd class="font-mono text-sm font-semibold text-ink-950 dark:text-white">{{ money(transaction.price) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-6 py-3">
                        <dt class="eyebrow">Phone</dt>
                        <dd class="font-mono text-sm text-ink-800 dark:text-ink-100">{{ transaction.phone }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-6 py-3">
                        <dt class="eyebrow">Reference</dt>
                        <dd class="truncate font-mono text-xs text-ink-500 dark:text-ink-400">{{ transaction.reference }}</dd>
                    </div>
                </dl>

                <div class="border-t rule p-4">
                    <!--
                        Only offered once the transaction is terminal. Leaving it
                        live during processing invites a second purchase before
                        the first has resolved.
                    -->
                    <Link
                        v-if="transaction.terminal"
                        :href="route('buy-data')"
                        class="btn btn-primary btn-lg w-full"
                    >
                        Buy more data
                    </Link>
                    <button v-else type="button" disabled class="btn btn-secondary btn-lg w-full">
                        Please wait…
                    </button>
                </div>
            </section>

            <!-- ============================ PURCHASE FORM ========================= -->
            <template v-else>
                <PageHeader eyebrow="Data" title="Buy data" description="Pick a number, pick a bundle. Charged to your wallet." />

                <!-- balance -->
                <div class="card flex items-center justify-between gap-4 px-5 py-4">
                    <div>
                        <p class="eyebrow">Wallet balance</p>
                        <p class="mt-1 font-mono text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">
                            {{ money(balance) }}
                        </p>
                    </div>
                    <Link :href="route('wallet.fund')" class="btn btn-accent btn-sm">Fund</Link>
                </div>

                <!-- buy again -->
                <button
                    v-if="lastPurchase"
                    type="button"
                    @click="buyAgain"
                    class="flex w-full items-center justify-between gap-3 rounded-card border border-brass-300 bg-brass-50 p-4 text-left transition hover:bg-brass-100 dark:border-brass-800 dark:bg-brass-950/40 dark:hover:bg-brass-950/70"
                >
                    <span class="min-w-0 text-sm text-ink-700 dark:text-ink-200">
                        <span class="font-semibold text-brass-800 dark:text-brass-300">Buy again:</span>
                        {{ lastPurchase.plan_name }} → <span class="font-mono">{{ lastPurchase.phone }}</span>
                    </span>
                    <span class="shrink-0 text-xs font-semibold text-brass-800 dark:text-brass-300">Repeat →</span>
                </button>

                <!-- Two columns on desktop: what you're sending to on the left,
                     what you're sending on the right. Stacks on mobile. -->
                <div class="grid gap-5 lg:grid-cols-5">
                <div class="space-y-5 lg:col-span-2">

                <!-- phone -->
                <div class="card card-pad">
                    <label for="phone" class="eyebrow">Phone number</label>
                    <input
                        id="phone"
                        v-model="phone"
                        type="tel"
                        inputmode="numeric"
                        placeholder="08012345678"
                        class="mt-1.5 block w-full font-mono text-lg"
                    />

                    <!-- beneficiaries -->
                    <div v-if="beneficiaries.length" class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="b in beneficiaries"
                            :key="b.phone"
                            type="button"
                            @click="pickBeneficiary(b)"
                            class="rounded-full bg-ink-100 px-3 py-1 text-xs font-semibold text-ink-700 transition hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-200 dark:hover:bg-ink-700"
                        >
                            {{ b.label || b.phone }}
                        </button>
                    </div>

                    <!-- suggestion / mismatch -->
                    <p v-if="hint.suggestion.value && !hint.mismatch.value" class="mt-2 text-xs text-ink-500 dark:text-ink-400">
                        {{ hint.suggestion.value }}
                    </p>
                    <p v-if="hint.mismatch.value" class="mt-2 text-xs text-warning-700 dark:text-warning-400">
                        {{ hint.mismatchNote.value }}.
                        <button type="button" class="font-semibold underline" @click="ported = true">Ported number?</button>
                    </p>

                    <!-- ported toggle -->
                    <label class="mt-3 flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                        <input v-model="ported" type="checkbox" />
                        This is a ported number
                    </label>
                </div>

                <!-- network tabs -->
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="n in networks"
                        :key="n.value"
                        type="button"
                        @click="pickNetwork(n.value)"
                        :aria-pressed="selectedNetwork === n.value"
                        class="flex flex-col items-center gap-1.5 rounded-card border p-3 text-xs font-semibold transition"
                        :class="selectedNetwork === n.value
                            ? 'border-brand-700 bg-brand-50 text-brand-900 dark:border-brand-500 dark:bg-brand-950/60 dark:text-white'
                            : 'border-ink-200 bg-white text-ink-600 hover:border-ink-300 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-300'"
                    >
                        <SignalIcon class="h-6 w-6" :class="brandTint[n.value] || 'text-ink-400'" />
                        {{ n.label }}
                    </button>
                </div>

                <!-- type pills -->
                <div v-if="typesForNetwork.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="t in typesForNetwork"
                        :key="t.type"
                        type="button"
                        :disabled="!t.available"
                        @click="t.available && (selectedType = t.type)"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="[
                            !t.available
                                ? 'cursor-not-allowed bg-ink-100 text-ink-400 dark:bg-ink-800 dark:text-ink-500'
                                : selectedType === t.type
                                    ? 'bg-brand-600 text-white dark:bg-brand-600'
                                    : 'bg-ink-100 text-ink-700 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-200 dark:hover:bg-ink-700',
                        ]"
                    >
                        {{ t.type }}<span v-if="!t.available"> (Unavailable)</span>
                    </button>
                </div>

                </div><!-- /left column -->

                <div class="space-y-5 lg:col-span-3">

                <!-- plan cards -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <button
                        v-for="p in filteredPlans"
                        :key="p.id"
                        type="button"
                        @click="selectedPlanId = p.id"
                        :aria-pressed="selectedPlanId === p.id"
                        class="rounded-card border p-3 text-left transition"
                        :class="selectedPlanId === p.id
                            ? 'border-brand-700 bg-brand-50 ring-1 ring-brand-700 dark:border-brand-500 dark:bg-brand-950/60 dark:ring-brand-500'
                            : 'border-ink-200 bg-white hover:border-brand-300 dark:border-ink-800 dark:bg-ink-900 dark:hover:border-brand-700'"
                    >
                        <p class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ p.name }}</p>
                        <p class="mt-0.5 font-mono text-lg font-semibold text-brand-700 dark:text-brand-300">{{ money(p.price) }}</p>
                        <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ p.validity }}</p>
                    </button>

                    <p v-if="!filteredPlans.length" class="col-span-full py-6 text-center text-sm text-ink-500 dark:text-ink-400">
                        No plans available for this selection.
                    </p>
                </div>

                <!-- buy button -->
                <button type="button" :disabled="!canSubmit" @click="submit()" class="btn btn-primary btn-lg w-full">
                    <span v-if="form.processing">Starting…</span>
                    <span v-else-if="selectedPlan">Buy {{ selectedPlan.name }} — {{ money(selectedPlan.price) }}</span>
                    <span v-else>Buy data</span>
                </button>

                </div><!-- /right column -->
                </div><!-- /two-column grid -->
            </template>
        </div>
    </AuthenticatedLayout>
</template>
