<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    wallet: Object,
    recent_transactions: Array,
    reserved_accounts: { type: Array, default: () => [] },
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const getTypeLabel = (type) => {
    const labels = {
        airtime: 'Airtime Purchase',
        data: 'Data Purchase',
        nin_verification: 'NIN Verification',
        bvn_verification: 'BVN Verification',
        wallet_funding: 'Wallet Funding',
        refund: 'Refund',
        credit: 'Credit',
        debit: 'Debit',
    };
    return labels[type] || type;
};
</script>

<template>
    <Head title="My Wallet" />

    <AuthenticatedLayout>
        <div class="space-y-6 lg:space-y-8">
            <PageHeader
                eyebrow="Wallet"
                title="My wallet"
                description="What you hold, where it came from and what it has been spent on."
            >
                <template #actions>
                    <Link :href="route('wallet.fund')" class="btn btn-accent">Fund wallet</Link>
                    <Link :href="route('wallet.transactions')" class="btn btn-secondary">All transactions</Link>
                </template>
            </PageHeader>

            <div class="grid gap-5 lg:grid-cols-3">
                <!-- The balance slip: total above the tear, the split below. -->
                <section class="slip flex flex-col">
                    <div class="slip-guilloche rounded-t-card px-5 py-5">
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

                <!-- Funding accounts -->
                <section class="card lg:col-span-2">
                    <div class="flex items-center justify-between gap-3 border-b rule px-5 py-4">
                        <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">
                            Funding accounts
                        </h2>
                        <Link :href="route('wallet.fund')" class="link text-sm">Manage</Link>
                    </div>

                    <div v-if="reserved_accounts.length" class="grid gap-4 p-5 sm:grid-cols-2">
                        <div v-for="acct in reserved_accounts" :key="acct.account_number" class="rounded-lg border rule p-4">
                            <p class="eyebrow">{{ acct.bank }}</p>
                            <p class="mt-2 font-mono text-lg font-semibold tracking-wide text-ink-950 dark:text-white">
                                {{ acct.account_number }}
                            </p>
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

            <!-- Recent transactions -->
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
                    description="Fund your wallet and your credits and debits will be listed here."
                    icon="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                >
                    <Link :href="route('wallet.fund')" class="btn btn-accent btn-sm">Fund wallet</Link>
                </EmptyState>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
