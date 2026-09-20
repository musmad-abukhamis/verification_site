<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import HistoryLayout from '@/Components/HistoryLayout.vue';
import HistoryUserCell from '@/Components/HistoryUserCell.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Wallet history, all users — money in, money spent, money given back.
 *
 * The old Wallet ▸ Transactions screen put all three behind one type dropdown.
 * They answer different questions ("did this payment land", "what did this
 * person spend", "was this reversed"), so they are separate tabs, and each
 * carries its own figures.
 */
const props = defineProps({
    tab: String,
    tabs: Array,
    records: Object,
    stats: Array,
    filters: Object,
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const COPY = {
    funding: {
        description:
            'Every payment into a wallet — bank transfers to virtual accounts, manual funding and admin credits.',
        placeholder: 'Search by user, reference or source…',
        statuses: [
            { value: 'success', label: 'Success' },
            { value: 'pending', label: 'Pending' },
            { value: 'failed', label: 'Failed' },
        ],
        label: 'payments',
        emptyTitle: 'No funding yet',
        emptyDescription: 'Transfers into virtual accounts show up here within moments of settling.',
    },
    debits: {
        description:
            'Every debit — service charges taken as users buy, plus admin debits and resets.',
        placeholder: 'Search by user, reference or reason…',
        statuses: [
            { value: 'success', label: 'Success' },
            { value: 'pending', label: 'Pending' },
            { value: 'failed', label: 'Failed' },
        ],
        label: 'debits',
        emptyTitle: 'No debits yet',
        emptyDescription: 'Charges taken from wallets will be listed here with the balance before and after.',
    },
    refunds: {
        description:
            'Money returned to wallets — reversed service charges and auto-refunded data purchases, read from both ledgers.',
        placeholder: 'Search by user or reference…',
        label: 'refunds',
        emptyTitle: 'No refunds',
        emptyDescription: 'When a service fails after a user was charged, the reversal is recorded here.',
    },
};

const copy = computed(() => COPY[props.tab] ?? COPY.funding);
const isCredit = computed(() => props.tab !== 'debits');
</script>

<template>
    <Head title="Wallet History" />

    <AdminLayout>
        <HistoryLayout
            eyebrow="History"
            title="Wallet history"
            :description="copy.description"
            :tabs="tabs"
            :active="tab"
            route-name="admin.history.wallet"
            :stats="stats"
            :records="records"
            :label="copy.label"
            :filters="filters"
            :search-placeholder="copy.placeholder"
            :statuses="copy.statuses ?? []"
            :empty-title="copy.emptyTitle"
            :empty-description="copy.emptyDescription"
        >
            <template #actions>
                <Link :href="route('admin.wallet.index')" class="btn btn-secondary btn-sm">Fund an account</Link>
            </template>

            <!-- ========================= Funding / Debits ==================== -->
            <template v-if="tab !== 'refunds'">
                <div class="scroll-slim hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>User</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>{{ tab === 'debits' ? 'Reason' : 'Source' }}</th>
                                <th>Prev. balance</th>
                                <th>New balance</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td><HistoryUserCell :user="r.user" /></td>
                                <td class="font-mono text-ink-500 dark:text-ink-400" :title="r.reference">
                                    {{ String(r.reference).slice(0, 12) }}…
                                </td>
                                <td
                                    class="font-mono font-semibold"
                                    :class="isCredit
                                        ? 'text-success-700 dark:text-success-400'
                                        : 'text-ink-950 dark:text-white'"
                                >
                                    {{ isCredit ? '+' : '−' }}{{ formatCurrency(r.amount) }}
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

                <div class="divide-y rule lg:hidden">
                    <article v-for="r in records.data" :key="r.id" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="font-mono text-lg font-semibold"
                                    :class="isCredit
                                        ? 'text-success-700 dark:text-success-400'
                                        : 'text-ink-950 dark:text-white'"
                                >
                                    {{ isCredit ? '+' : '−' }}{{ formatCurrency(r.amount) }}
                                </p>
                                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ r.source }} · {{ r.date }}</p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>

                        <HistoryUserCell :user="r.user" />

                        <p class="font-mono text-xs text-ink-500 dark:text-ink-400">
                            {{ formatCurrency(r.old_balance) }} → {{ formatCurrency(r.new_balance) }}
                        </p>
                    </article>
                </div>
            </template>

            <!-- =============================== Refunds ====================== -->
            <template v-else>
                <div class="scroll-slim hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>User</th>
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
                                <td><HistoryUserCell :user="r.user" /></td>
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

                <div class="divide-y rule lg:hidden">
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

                        <HistoryUserCell :user="r.user" />

                        <p class="font-mono text-xs text-ink-500 dark:text-ink-400">
                            {{ formatCurrency(r.old_balance) }} → {{ formatCurrency(r.new_balance) }}
                        </p>
                    </article>
                </div>
            </template>
        </HistoryLayout>
    </AdminLayout>
</template>
