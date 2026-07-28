<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    wallet: Object,
    recent_transactions: Array,
    reserved_accounts: { type: Array, default: () => [] },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? {});
const copied = ref(null);

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

/**
 * The service launcher. Thirteen tiles in one undifferentiated grid made the
 * user read all of them to find one, so they're clustered by the thing being
 * worked on. Treatment is identical across tiles — colour would imply a
 * ranking that doesn't exist.
 */
const serviceGroups = [
    {
        name: 'Data & wallet',
        items: [
            { name: 'Buy data', route: 'buy-data', icon: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0' },
            { name: 'Fund wallet', route: 'wallet.fund', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
        ],
    },
    {
        name: 'NIN services',
        items: [
            { name: 'NIN verify', route: 'nin.verify.index', icon: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2' },
            { name: 'NIN validation', route: 'nin.validation.index', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
            { name: 'IPE clearance', route: 'nin.ipe.index', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        ],
    },
    {
        name: 'BVN services',
        items: [
            { name: 'BVN search', route: 'bvn-search.index', icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' },
            { name: 'Modification', route: 'bvn-modification.index', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
            { name: 'Onboarding', route: 'bvn-sdk-form.index', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            { name: 'Retrieval', route: 'bvn-retrieval.index', icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' },
            { name: 'ID card', route: 'idcard.index', icon: 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z' },
            { name: 'Enrollment records', route: 'bvn-records.index', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        ],
    },
    {
        name: 'Records & account',
        items: [
            { name: 'Verify history', route: 'verification.history', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
            { name: 'Profile', route: 'profile.edit', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
        ],
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="space-y-6 lg:space-y-8">
            <PageHeader
                eyebrow="Overview"
                :title="`Welcome back, ${authUser.username || authUser.name}`"
                description="Your balance, your accounts and everything you've run recently — all on one page."
            >
                <template #actions>
                    <Link :href="route('wallet.fund')" class="btn btn-accent">Fund wallet</Link>
                    <Link :href="route('buy-data')" class="btn btn-secondary">Buy data</Link>
                </template>
            </PageHeader>

            <!-- ============================================================
                 Balance + funding accounts
                 ============================================================ -->
            <div class="grid gap-5 lg:grid-cols-3">
                <!-- The balance is a receipt, so it gets the slip treatment:
                     total above the tear, the split below it. -->
                <section class="slip flex flex-col">
                    <div class="slip-guilloche rounded-t-card px-5 pb-5 pt-5">
                        <p class="eyebrow">Total balance</p>
                        <p class="mt-2 font-mono text-3xl font-semibold tracking-tight text-ink-950 dark:text-white">
                            {{ formatCurrency(wallet.total_balance) }}
                        </p>
                    </div>

                    <div class="slip-tear mt-auto grid grid-cols-2">
                        <div class="px-5 py-4">
                            <p class="eyebrow">Main</p>
                            <p class="mt-1 font-mono text-base font-semibold text-ink-900 dark:text-ink-100">
                                {{ formatCurrency(wallet.balance) }}
                            </p>
                        </div>
                        <div class="border-l border-dashed border-ink-300 px-5 py-4 dark:border-ink-700">
                            <p class="eyebrow">Bonus</p>
                            <p class="mt-1 font-mono text-base font-semibold text-ink-900 dark:text-ink-100">
                                {{ formatCurrency(wallet.bonus_balance) }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Virtual accounts -->
                <section class="card lg:col-span-2">
                    <div class="flex items-center justify-between gap-3 border-b rule px-5 py-4">
                        <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">
                            Your funding accounts
                        </h2>
                        <Link :href="route('wallet.fund')" class="link text-sm">Manage</Link>
                    </div>

                    <div v-if="reserved_accounts.length" class="grid gap-4 p-5 sm:grid-cols-2">
                        <div
                            v-for="acct in reserved_accounts"
                            :key="acct.account_number"
                            class="rounded-lg border rule p-4"
                        >
                            <p class="eyebrow">{{ acct.bank }}</p>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <span class="font-mono text-lg font-semibold tracking-wide text-ink-950 dark:text-white">
                                    {{ acct.account_number }}
                                </span>
                                <button type="button" class="btn btn-secondary btn-sm" @click="copy(acct.account_number)">
                                    {{ copied === acct.account_number ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                            <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">{{ acct.account_name }}</p>
                        </div>
                    </div>

                    <EmptyState
                        v-else
                        title="No funding account yet"
                        description="Generate a dedicated account number and any transfer into it lands in your wallet automatically."
                        icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                    >
                        <Link :href="route('wallet.fund')" class="btn btn-primary btn-sm">Set one up</Link>
                    </EmptyState>
                </section>
            </div>

            <!-- ============================================================
                 Services
                 ============================================================ -->
            <section class="card">
                <div class="border-b rule px-5 py-4">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Services</h2>
                    <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Everything your account can run, grouped by what you're working on.</p>
                </div>

                <div class="divide-y rule">
                    <div v-for="group in serviceGroups" :key="group.name" class="p-5">
                        <!-- Group label with a rule running to the count, so the
                             eye is carried across the row instead of stopping. -->
                        <div class="flex items-center gap-3">
                            <p class="eyebrow">{{ group.name }}</p>
                            <span class="h-px flex-1 bg-ink-200 dark:bg-ink-800" aria-hidden="true"></span>
                            <span class="font-mono text-2xs text-ink-400 dark:text-ink-500">{{ group.items.length }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            <Link
                                v-for="service in group.items"
                                :key="service.route"
                                :href="route(service.route)"
                                class="group relative flex flex-col items-start gap-3 overflow-hidden rounded-xl border border-ink-200 bg-white p-4
                                       border-t-4 border-t-brand-600
                                       transition duration-200 ease-out
                                       hover:-translate-y-0.5 hover:border-brand-300 hover:border-t-brand-800 hover:shadow-lift
                                       dark:border-ink-800 dark:border-t-brand-500 dark:bg-ink-900
                                       dark:hover:border-brand-700 dark:hover:border-t-brand-400"
                            >
                                <span
                                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-800 ring-1 ring-inset ring-brand-100
                                           transition-colors duration-200
                                           group-hover:bg-brand-800 group-hover:text-white group-hover:ring-brand-800
                                           dark:bg-brand-950 dark:text-brand-300 dark:ring-brand-900 dark:group-hover:bg-brand-700 dark:group-hover:ring-brand-700"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="service.icon" />
                                    </svg>
                                </span>

                                <span class="flex w-full items-center justify-between gap-1.5">
                                    <span class="text-sm font-semibold leading-snug text-ink-800 transition-colors group-hover:text-brand-900 dark:text-ink-200 dark:group-hover:text-white">
                                        {{ service.name }}
                                    </span>
                                    <svg
                                        class="h-4 w-4 shrink-0 -translate-x-1 text-brand-700 opacity-0 transition-all duration-200 group-hover:translate-x-0 group-hover:opacity-100 dark:text-brand-300"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================
                 Recent transactions
                 ============================================================ -->
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b rule px-5 py-4">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">
                        Recent transactions
                    </h2>
                    <Link :href="route('wallet.transactions')" class="link text-sm">View all</Link>
                </div>

                <div v-if="recent_transactions.length" class="scroll-slim overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>Reference</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="transaction in recent_transactions" :key="transaction.id" class="t-row">
                                <td class="font-mono text-ink-900 dark:text-ink-100">{{ transaction.reference }}</td>
                                <td class="text-ink-600 dark:text-ink-300">{{ getTypeLabel(transaction.type) }}</td>
                                <td class="font-mono font-semibold text-ink-950 dark:text-white">
                                    {{ formatCurrency(transaction.amount) }}
                                </td>
                                <td><StatusPill :status="transaction.status" /></td>
                                <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ transaction.date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState
                    v-else
                    title="Nothing here yet"
                    description="Your purchases, verifications and wallet movements will appear here as you work."
                    icon="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                >
                    <Link :href="route('buy-data')" class="btn btn-primary btn-sm">Buy data</Link>
                </EmptyState>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
