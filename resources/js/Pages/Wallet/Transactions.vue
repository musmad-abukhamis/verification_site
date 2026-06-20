<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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

const statusColor = (s) => {
    const map = {
        success: 'text-green-700 bg-green-100',
        pending: 'text-yellow-700 bg-yellow-100',
        failed: 'text-red-700 bg-red-100',
    };
    return map[s] || 'text-gray-700 bg-gray-100';
};
</script>

<template>
    <Head title="My Wallet Transactions" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Wallet Transactions</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Summary cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Current Balance</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(wallet.total_balance) }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Total Credit</p>
                        <p class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.total_credit) }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Total Debit</p>
                        <p class="text-2xl font-bold text-red-600">{{ formatCurrency(stats.total_debit) }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.total_count }}</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="flex flex-col md:flex-row gap-3">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search transactions..."
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <select v-model="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">All Status</option>
                            <option value="success">Success</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                        <select v-model="type" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">All Types</option>
                            <option value="credit">Credit</option>
                            <option value="debit">Debit</option>
                        </select>
                    </div>
                </div>

                <!-- Table / cards -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div v-if="transactions.data.length === 0" class="text-center py-10 text-gray-500">
                        No transactions found.
                    </div>

                    <template v-else>
                        <!-- Desktop -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prev. Balance</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Balance</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Direction</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-500">{{ String(t.reference).slice(0, 10) }}…</td>
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ formatCurrency(t.amount) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatCurrency(t.old_balance) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatCurrency(t.new_balance) }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-1 text-xs rounded-full capitalize', t.type === 'credit' ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100']">
                                                {{ t.type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 capitalize">{{ t.fundingtype }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ t.date }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-1 text-xs rounded-full capitalize', statusColor(t.status)]">{{ t.status }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile -->
                        <div class="md:hidden space-y-4">
                            <div v-for="t in transactions.data" :key="t.id" class="border border-gray-200 rounded-lg p-4 space-y-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-mono text-xs text-gray-500">{{ String(t.reference).slice(0, 12) }}…</p>
                                        <p class="font-semibold text-lg">{{ formatCurrency(t.amount) }}</p>
                                    </div>
                                    <span :class="['px-2 py-1 text-xs rounded-full capitalize', statusColor(t.status)]">{{ t.status }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div><p class="text-gray-500">Prev. Balance</p><p class="font-medium">{{ formatCurrency(t.old_balance) }}</p></div>
                                    <div><p class="text-gray-500">New Balance</p><p class="font-medium">{{ formatCurrency(t.new_balance) }}</p></div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span :class="['px-2 py-1 text-xs rounded-full capitalize', t.type === 'credit' ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100']">{{ t.type }}</span>
                                    <span class="text-xs text-gray-500 capitalize">{{ t.fundingtype }}</span>
                                </div>
                                <p class="text-xs text-gray-400 text-right">{{ t.date }}</p>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-gray-500">
                                Showing {{ transactions.from }} to {{ transactions.to }} of {{ transactions.total }} results
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="link in transactions.links"
                                    :key="link.label"
                                    :href="link.url || ''"
                                    :class="[
                                        'px-3 py-1 rounded text-sm',
                                        link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                        !link.url && 'opacity-50 cursor-not-allowed pointer-events-none',
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
