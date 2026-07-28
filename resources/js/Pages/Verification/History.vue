<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    history: Array,
    filter: String,
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const tabs = [
    { name: 'All', params: {}, key: undefined },
    { name: 'NIN only', params: { type: 'nin' }, key: 'nin' },
    { name: 'BVN only', params: { type: 'bvn' }, key: 'bvn' },
];
</script>

<template>
    <Head title="Verification History" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="Verification"
                title="Verification history"
                description="Every NIN and BVN lookup charged to your wallet."
            >
                <template #actions>
                    <Link :href="route('verification.nin')" class="btn btn-primary">New verification</Link>
                </template>
            </PageHeader>

            <section class="card overflow-hidden">
                <!-- Tabs sit on the card's own edge, so the filter belongs to the
                     table rather than floating above it. -->
                <nav class="scroll-slim flex overflow-x-auto border-b rule px-2" aria-label="Filter by type">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.name"
                        :href="route('verification.history', tab.params)"
                        :aria-current="(filter || undefined) === tab.key ? 'page' : undefined"
                        :class="[
                            '-mb-px whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold transition',
                            (filter || undefined) === tab.key
                                ? 'border-brand-800 text-brand-900 dark:border-brand-400 dark:text-white'
                                : 'border-transparent text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-ink-100',
                        ]"
                    >
                        {{ tab.name }}
                    </Link>
                </nav>

                <div v-if="history.length" class="scroll-slim overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>Type</th>
                                <th>Identity number</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="item in history" :key="item.id" class="t-row">
                                <td class="font-semibold uppercase text-ink-900 dark:text-ink-100">{{ item.type }}</td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ item.identity_number }}</td>
                                <td class="font-mono font-semibold text-ink-950 dark:text-white">{{ formatCurrency(item.amount) }}</td>
                                <td><StatusPill :status="item.status" /></td>
                                <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ item.date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState
                    v-else
                    title="No verifications yet"
                    description="Run a NIN or BVN lookup and every search will be recorded here."
                    icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                >
                    <Link :href="route('verification.nin')" class="btn btn-primary btn-sm">Verify an identity</Link>
                </EmptyState>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
