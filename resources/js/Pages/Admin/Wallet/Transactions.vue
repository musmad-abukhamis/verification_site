<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    transactions: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const type = ref(props.filters?.type || 'all');

const applyFilters = () => {
    router.get(route('admin.wallet.transactions'), {
        search: search.value, status: status.value, type: type.value,
    }, { preserveState: true, preserveScroll: true });
};

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true }); };

const fmt = (n) => '₦' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const typeBadge = (t) => t === 'credit'
    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';

const statusBadge = (s) => {
    const map = {
        success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        refund: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return map[s?.toLowerCase()] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};
</script>

<template>
    <Head title="Wallet Transactions" />
    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Wallet Transactions</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Every wallet movement (funding, charges, refunds).</p>
                </div>
                <Link :href="route('admin.wallet.index')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg whitespace-nowrap">
                    Account Funding
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Credit</p>
                        <p class="text-2xl font-bold text-green-600">{{ fmt(stats.total_credit) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/40 flex items-center justify-center text-green-600">▲</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Debit</p>
                        <p class="text-2xl font-bold text-red-600">{{ fmt(stats.total_debit) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/40 flex items-center justify-center text-red-600">▼</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_count.toLocaleString() }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600">₦</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input v-model="search" type="text" placeholder="Reference, user, type..." @keyup.enter="applyFilters"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Direction</label>
                        <select v-model="type" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">All</option>
                            <option value="credit">Credit</option>
                            <option value="debit">Debit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select v-model="status" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">All</option>
                            <option value="success">Success</option>
                            <option value="pending">Pending</option>
                            <option value="refund">Refund</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button @click="applyFilters" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">Filter</button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Prev. Bal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">New Bal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Direction</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Source</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-500 dark:text-gray-400">{{ String(t.id).slice(0, 10) }}…</td>
                                <td class="px-6 py-4 text-sm">
                                    <span v-if="t.user" class="text-gray-900 dark:text-white">{{ t.user.username || t.user.name }}</span>
                                    <span v-else class="text-gray-500">N/A</span>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">{{ fmt(t.amount) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ fmt(t.old_balance) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ fmt(t.new_balance) }}</td>
                                <td class="px-6 py-4"><span :class="['px-2 py-1 text-xs rounded-full font-medium capitalize', typeBadge(t.type)]">{{ t.type }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ t.fundingtype }}</td>
                                <td class="px-6 py-4"><span :class="['px-2 py-1 text-xs rounded-full font-medium capitalize', statusBadge(t.status)]">{{ t.status }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ t.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="transactions.data.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">No wallet transactions found.</div>
                <div v-if="transactions.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ transactions.from }} to {{ transactions.to }} of {{ transactions.total }} results</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(transactions.prev_page_url)" :disabled="!transactions.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(transactions.next_page_url)" :disabled="!transactions.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
