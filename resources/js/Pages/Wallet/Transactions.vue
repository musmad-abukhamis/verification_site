<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatTile from '@/Components/StatTile.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    transactions: Object,
    filters: Object,
    stats: Object,
    wallet: Object,
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? 'all');
const type = ref(props.filters?.type ?? 'all');

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const reload = () => {
    router.get(
        route('wallet.transactions'),
        { search: search.value || undefined, status: status.value, type: type.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(reload, 350);
});
watch([status, type], reload);
</script>

<template>
    <Head title="My Wallet Transactions" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="Wallet"
                title="Wallet transactions"
                description="Every credit and debit against your balance, with the balance before and after each one."
            />

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Current balance"
                    :value="formatCurrency(wallet.total_balance)"
                    tone="brass"
                    icon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                />
                <StatTile
                    label="Total credit"
                    :value="formatCurrency(stats.total_credit)"
                    tone="success"
                    icon="M12 19V6m0 0l-6 6m6-6l6 6"
                />
                <StatTile
                    label="Total debit"
                    :value="formatCurrency(stats.total_debit)"
                    tone="danger"
                    icon="M12 5v13m0 0l6-6m-6 6l-6-6"
                />
                <StatTile
                    label="Transactions"
                    :value="stats.total_count"
                    icon="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
            </div>

            <!-- Filters -->
            <div class="card card-pad">
                <div class="flex flex-col gap-3 md:flex-row">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search by reference…"
                        class="flex-1"
                        aria-label="Search transactions"
                    />
                    <select v-model="status" aria-label="Filter by status">
                        <option value="all">All statuses</option>
                        <option value="success">Success</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                    <select v-model="type" aria-label="Filter by direction">
                        <option value="all">All directions</option>
                        <option value="credit">Credit</option>
                        <option value="debit">Debit</option>
                    </select>
                </div>
            </div>

            <!-- Records -->
            <section class="card overflow-hidden">
                <template v-if="transactions.data.length">
                    <!-- Desktop -->
                    <div class="scroll-slim hidden overflow-x-auto md:block">
                        <table class="min-w-full">
                            <thead class="t-head">
                                <tr>
                                    <th>Reference</th>
                                    <th>Amount</th>
                                    <th>Prev. balance</th>
                                    <th>New balance</th>
                                    <th>Direction</th>
                                    <th>Source</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y rule">
                                <tr v-for="t in transactions.data" :key="t.id" class="t-row">
                                    <td class="font-mono text-ink-500 dark:text-ink-400" :title="t.reference">
                                        {{ String(t.reference).slice(0, 10) }}…
                                    </td>
                                    <td class="font-mono font-semibold text-ink-950 dark:text-white">
                                        {{ formatCurrency(t.amount) }}
                                    </td>
                                    <td class="font-mono text-ink-600 dark:text-ink-300">{{ formatCurrency(t.old_balance) }}</td>
                                    <td class="font-mono text-ink-600 dark:text-ink-300">{{ formatCurrency(t.new_balance) }}</td>
                                    <td><StatusPill :status="t.type" /></td>
                                    <td class="capitalize text-ink-600 dark:text-ink-300">{{ t.fundingtype }}</td>
                                    <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ t.date }}</td>
                                    <td><StatusPill :status="t.status" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile: the same record as a slip, since it is a receipt. -->
                    <div class="divide-y rule md:hidden">
                        <article v-for="t in transactions.data" :key="t.id" class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-mono text-xs text-ink-500 dark:text-ink-400">
                                        {{ String(t.reference).slice(0, 12) }}…
                                    </p>
                                    <p class="mt-1 font-mono text-lg font-semibold text-ink-950 dark:text-white">
                                        {{ formatCurrency(t.amount) }}
                                    </p>
                                </div>
                                <StatusPill :status="t.status" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="eyebrow">Prev. balance</p>
                                    <p class="mt-0.5 font-mono text-sm text-ink-700 dark:text-ink-200">{{ formatCurrency(t.old_balance) }}</p>
                                </div>
                                <div>
                                    <p class="eyebrow">New balance</p>
                                    <p class="mt-0.5 font-mono text-sm text-ink-700 dark:text-ink-200">{{ formatCurrency(t.new_balance) }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <StatusPill :status="t.type" />
                                <span class="text-xs capitalize text-ink-500 dark:text-ink-400">{{ t.fundingtype }} · {{ t.date }}</span>
                            </div>
                        </article>
                    </div>

                    <Pagination :paginator="transactions" label="transactions" />
                </template>

                <EmptyState
                    v-else
                    title="No transactions found"
                    description="Nothing matches these filters. Try clearing the search or widening the status."
                    icon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
