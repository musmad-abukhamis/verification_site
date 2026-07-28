<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    transactions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');

let timer = null;
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('data-transactions.index'), { search: search.value, status: status.value }, {
            preserveState: true,
            replace: true,
            only: ['transactions', 'filters'],
        });
    }, 300);
});

const money = (n) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(n ?? 0));
</script>

<template>
    <Head title="My Data Purchases" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="Data"
                title="My data purchases"
                description="Every bundle you've sent, with the vendor reference for anything you need to dispute."
            >
                <template #actions>
                    <Link :href="route('buy-data')" class="btn btn-primary">Buy data</Link>
                </template>
            </PageHeader>

            <div class="card card-pad">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search reference, plan, phone…"
                        class="w-full"
                        aria-label="Search purchases"
                    />
                    <select v-model="status" aria-label="Filter by status">
                        <option value="all">All statuses</option>
                        <option value="success">Success</option>
                        <option value="processing">Processing</option>
                        <option value="pending">Pending</option>
                        <option value="fail">Failed</option>
                        <option value="refunded">Refunded</option>
                        <option value="refunded_unconfirmed">Refunded (unconfirmed)</option>
                    </select>
                </div>
            </div>

            <section class="card overflow-hidden">
                <template v-if="transactions.data.length">
                    <!-- Desktop -->
                    <div class="scroll-slim hidden overflow-x-auto md:block">
                        <table class="min-w-full">
                            <thead class="t-head">
                                <tr>
                                    <th>Plan</th>
                                    <th>Phone</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y rule">
                                <tr v-for="t in transactions.data" :key="t.reference" class="t-row">
                                    <td>
                                        <p class="font-semibold text-ink-900 dark:text-ink-100">
                                            {{ t.network?.toUpperCase() }} · {{ t.plan_name }}
                                        </p>
                                        <p class="mt-0.5 font-mono text-xs text-ink-400 dark:text-ink-500">{{ t.reference }}</p>
                                    </td>
                                    <td class="whitespace-nowrap font-mono text-ink-600 dark:text-ink-300">{{ t.phone }}</td>
                                    <td class="whitespace-nowrap font-mono font-semibold text-ink-950 dark:text-white">
                                        {{ money(t.price) }}
                                    </td>
                                    <td><StatusPill :status="t.status" /></td>
                                    <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ t.date }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile -->
                    <div class="divide-y rule md:hidden">
                        <article v-for="t in transactions.data" :key="t.reference" class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink-900 dark:text-ink-100">
                                        {{ t.network?.toUpperCase() }} · {{ t.plan_name }}
                                    </p>
                                    <p class="mt-0.5 truncate font-mono text-xs text-ink-400 dark:text-ink-500">{{ t.reference }}</p>
                                </div>
                                <StatusPill :status="t.status" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="eyebrow">Phone</p>
                                    <p class="mt-0.5 font-mono text-sm text-ink-800 dark:text-ink-100">{{ t.phone }}</p>
                                </div>
                                <div>
                                    <p class="eyebrow">Amount</p>
                                    <p class="mt-0.5 font-mono text-sm font-semibold text-ink-950 dark:text-white">{{ money(t.price) }}</p>
                                </div>
                            </div>

                            <p class="text-right text-xs text-ink-400 dark:text-ink-500">{{ t.date }}</p>
                        </article>
                    </div>

                    <Pagination :paginator="transactions" label="purchases" />
                </template>

                <EmptyState
                    v-else
                    title="No purchases yet"
                    description="Bundles you send will be listed here with their vendor reference and delivery status."
                    icon="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"
                >
                    <Link :href="route('buy-data')" class="btn btn-primary btn-sm">Buy data</Link>
                </EmptyState>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
