<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HistoryLayout from '@/Components/HistoryLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Wallet history — money in, and money given back.
 *
 * Refunds get their own tab rather than a filter on the funding list: someone
 * chasing a failed purchase is asking one question, and the answer is spread
 * across two ledgers (service refunds and the data module's own), which the
 * server unions before it gets here.
 */
const props = defineProps({
    tab: String,
    tabs: Array,
    records: Object,
    stats: Array,
    filters: Object,
    balance: Number,
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const COPY = {
    funding: {
        description: 'Every payment into your wallet — bank transfers to your virtual account, and credits applied by an admin.',
        placeholder: 'Search by reference or source…',
        label: 'payments',
        emptyTitle: 'No funding yet',
        emptyDescription: 'Transfers into your virtual account will show up here within moments of settling.',
    },
    refunds: {
        description: 'Money returned to your wallet — reversed service charges and auto-refunded data purchases.',
        placeholder: 'Search by reference…',
        label: 'refunds',
        emptyTitle: 'No refunds',
        emptyDescription: 'When a service fails after you were charged, the reversal is recorded here.',
    },
};

const copy = computed(() => COPY[props.tab] ?? COPY.funding);
</script>

<template>
    <Head title="Wallet History" />

    <AuthenticatedLayout>
        <HistoryLayout
            eyebrow="History"
            title="Wallet history"
            :description="copy.description"
            :tabs="tabs"
            :active="tab"
            route-name="history.wallet"
            :stats="stats"
            :records="records"
            :label="copy.label"
            :filters="filters"
            :search-placeholder="copy.placeholder"
            :empty-title="copy.emptyTitle"
            :empty-description="copy.emptyDescription"
        >
            <template #actions>
                <span class="hidden font-mono text-sm font-semibold text-ink-700 dark:text-ink-200 sm:inline">
                    Balance {{ formatCurrency(balance) }}
                </span>
                <Link :href="route('wallet.fund')" class="btn btn-accent btn-sm">Fund wallet</Link>
            </template>

            <!-- =============================== Funding ====================== -->
            <template v-if="tab === 'funding'">
                <div class="scroll-slim hidden overflow-x-auto md:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Source</th>
                                <th>Prev. balance</th>
                                <th>New balance</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td class="font-mono text-ink-500 dark:text-ink-400" :title="r.reference">
                                    {{ String(r.reference).slice(0, 12) }}…
                                </td>
                                <td class="font-mono font-semibold text-success-700 dark:text-success-400">
                                    +{{ formatCurrency(r.amount) }}
                                </td>
                                <td class="text-ink-700 dark:text-ink-200">{{ r.source }}</td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ formatCurrency(r.old_balance) }}</td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ formatCurrency(r.new_balance) }}</td>
                                <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ r.date }}</td>
                                <td><StatusPill :status="r.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y rule md:hidden">
                    <article v-for="r in records.data" :key="r.id" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-lg font-semibold text-success-700 dark:text-success-400">
                                    +{{ formatCurrency(r.amount) }}
                                </p>
                                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ r.source }} · {{ r.date }}</p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>
                        <p class="font-mono text-xs text-ink-500 dark:text-ink-400">
                            {{ formatCurrency(r.old_balance) }} → {{ formatCurrency(r.new_balance) }}
                        </p>
                    </article>
                </div>
            </template>

            <!-- =============================== Refunds ====================== -->
            <template v-else>
                <div class="scroll-slim hidden overflow-x-auto md:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Refunded for</th>
                                <th>Prev. balance</th>
                                <th>New balance</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td class="font-mono text-ink-500 dark:text-ink-400" :title="r.reference">
                                    {{ String(r.reference).slice(0, 12) }}…
                                </td>
                                <td class="font-mono font-semibold text-success-700 dark:text-success-400">
                                    +{{ formatCurrency(r.amount) }}
                                </td>
                                <td class="text-ink-700 dark:text-ink-200">
                                    {{ r.source }}
                                    <span v-if="r.related_id" class="block font-mono text-2xs text-ink-500 dark:text-ink-400">
                                        {{ r.related_id }}
                                    </span>
                                </td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ formatCurrency(r.old_balance) }}</td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ formatCurrency(r.new_balance) }}</td>
                                <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ r.date }}</td>
                                <td><StatusPill status="refund" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y rule md:hidden">
                    <article v-for="r in records.data" :key="r.id" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-lg font-semibold text-success-700 dark:text-success-400">
                                    +{{ formatCurrency(r.amount) }}
                                </p>
                                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ r.source }} · {{ r.date }}</p>
                            </div>
                            <StatusPill status="refund" />
                        </div>
                        <p class="font-mono text-xs text-ink-500 dark:text-ink-400">
                            {{ formatCurrency(r.old_balance) }} → {{ formatCurrency(r.new_balance) }}
                        </p>
                    </article>
                </div>
            </template>
        </HistoryLayout>
    </AuthenticatedLayout>
</template>
