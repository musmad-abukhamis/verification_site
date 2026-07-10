<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

const brandTint = {
    mtn: 'text-yellow-500',
    airtel: 'text-red-600',
    glo: 'text-green-600',
    '9mobile': 'text-emerald-600',
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
        confirmButtonColor: '#2563eb',
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

const stopPolling = () => {
    if (poll) {
        clearInterval(poll);
        poll = null;
    }
};

const showResult = (txn) => {
    const icon = txn.status === 'success' ? 'success' : (txn.status === 'fail' ? 'error' : 'info');
    Swal.fire({ title: txn.status === 'success' ? 'Success' : 'Update', text: txn.message, icon });
};

onMounted(() => {
    const txn = props.transaction;
    if (!txn) return;
    if (txn.terminal) {
        showResult(txn);
    } else {
        poll = setInterval(() => router.reload({ only: ['transaction'] }), 3000);
    }
});

watch(
    () => props.transaction,
    (txn) => {
        if (txn && txn.terminal) {
            stopPolling();
            showResult(txn);
        }
    }
);

onBeforeUnmount(stopPolling);

const statusColor = computed(() => {
    switch (props.transaction?.status) {
        case 'success':
            return 'text-green-600 dark:text-green-400';
        case 'refunded':
        case 'refunded_unconfirmed':
        case 'fail':
            return 'text-red-600 dark:text-red-400';
        default:
            return 'text-blue-600 dark:text-blue-400';
    }
});
</script>

<template>
    <Head title="Buy Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Buy Data</h2>
        </template>

        <div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- ============================ STATUS VIEW =========================== -->
            <div
                v-if="transaction"
                class="rounded-2xl bg-white p-6 shadow-sm dark:bg-gray-800"
            >
                <div class="flex flex-col items-center text-center">
                    <svg
                        v-if="!transaction.terminal"
                        class="mb-4 h-12 w-12 animate-spin text-blue-600"
                        viewBox="0 0 24 24" fill="none"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <h3 class="text-lg font-semibold" :class="statusColor">
                        {{ transaction.terminal ? transaction.status.replace('_', ' ') : 'Processing…' }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ transaction.message }}</p>

                    <dl class="mt-5 w-full space-y-2 rounded-xl bg-gray-50 p-4 text-sm dark:bg-gray-900/40">
                        <div class="flex justify-between"><dt class="text-gray-500">Plan</dt><dd class="font-medium">{{ transaction.plan_name }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Amount</dt><dd class="font-medium">{{ money(transaction.price) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ transaction.phone }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Reference</dt><dd class="font-mono text-xs">{{ transaction.reference }}</dd></div>
                    </dl>

                    <Link
                        :href="route('buy-data')"
                        class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700"
                    >
                        Buy more data
                    </Link>
                </div>
            </div>

            <!-- ============================ PURCHASE FORM ========================= -->
            <div v-else class="space-y-5">
                <!-- balance -->
                <div class="flex items-center justify-between rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 p-5 text-white">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-blue-100">Wallet balance</p>
                        <p class="text-2xl font-bold">{{ money(balance) }}</p>
                    </div>
                    <Link :href="route('wallet.fund')" class="rounded-lg bg-white/15 px-3 py-2 text-sm font-medium hover:bg-white/25">Fund</Link>
                </div>

                <!-- buy again -->
                <button
                    v-if="lastPurchase"
                    type="button"
                    @click="buyAgain"
                    class="flex w-full items-center justify-between rounded-2xl border border-blue-200 bg-blue-50 p-4 text-left dark:border-blue-900 dark:bg-blue-950/40"
                >
                    <span class="text-sm">
                        <span class="font-semibold text-blue-700 dark:text-blue-300">Buy again:</span>
                        {{ lastPurchase.plan_name }} → {{ lastPurchase.phone }}
                    </span>
                    <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">Tap to repeat →</span>
                </button>

                <!-- phone -->
                <div class="rounded-2xl bg-white p-5 shadow-sm dark:bg-gray-800">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Phone number</label>
                    <input
                        v-model="phone"
                        type="tel"
                        inputmode="numeric"
                        placeholder="08012345678"
                        class="w-full rounded-xl border-gray-300 text-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />

                    <!-- beneficiaries -->
                    <div v-if="beneficiaries.length" class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="b in beneficiaries"
                            :key="b.phone"
                            type="button"
                            @click="pickBeneficiary(b)"
                            class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200"
                        >
                            {{ b.label || b.phone }}
                        </button>
                    </div>

                    <!-- suggestion / mismatch -->
                    <p v-if="hint.suggestion.value && !hint.mismatch.value" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ hint.suggestion.value }}
                    </p>
                    <p v-if="hint.mismatch.value" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                        {{ hint.mismatchNote.value }}.
                        <button type="button" class="font-semibold underline" @click="ported = true">Ported number?</button>
                    </p>

                    <!-- ported toggle -->
                    <label class="mt-3 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input v-model="ported" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
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
                        class="flex flex-col items-center gap-1 rounded-xl border p-3 text-xs font-semibold transition"
                        :class="selectedNetwork === n.value
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40'
                            : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'"
                    >
                        <SignalIcon class="h-6 w-6" :class="brandTint[n.value] || 'text-gray-400'" />
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
                        class="rounded-full px-3 py-1 text-xs font-semibold transition"
                        :class="[
                            !t.available
                                ? 'cursor-not-allowed bg-gray-100 text-gray-400 dark:bg-gray-800'
                                : selectedType === t.type
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200',
                        ]"
                    >
                        {{ t.type }}<span v-if="!t.available"> (Unavailable)</span>
                    </button>
                </div>

                <!-- plan cards -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <button
                        v-for="p in filteredPlans"
                        :key="p.id"
                        type="button"
                        @click="selectedPlanId = p.id"
                        class="rounded-xl border p-3 text-left transition"
                        :class="selectedPlanId === p.id
                            ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500 dark:bg-blue-950/40'
                            : 'border-gray-200 bg-white hover:border-blue-300 dark:border-gray-700 dark:bg-gray-800'"
                    >
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ p.name }}</p>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ money(p.price) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ p.validity }}</p>
                    </button>
                    <p v-if="!filteredPlans.length" class="col-span-full py-6 text-center text-sm text-gray-500">
                        No plans available for this selection.
                    </p>
                </div>

                <!-- buy button -->
                <button
                    type="button"
                    :disabled="!canSubmit"
                    @click="submit()"
                    class="w-full rounded-xl bg-blue-600 px-4 py-4 text-center font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300 dark:disabled:bg-gray-700"
                >
                    <span v-if="form.processing">Starting…</span>
                    <span v-else-if="selectedPlan">Buy {{ selectedPlan.name }} — {{ money(selectedPlan.price) }}</span>
                    <span v-else>Buy Data</span>
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
