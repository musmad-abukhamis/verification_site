<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    transactions: Object,
    filters: Object,
    idtypes: Array,
    stats: Object,
});

const search = ref(props.filters?.search ?? '');
const idtype = ref(props.filters?.idtype ?? 'all');
let searchTimeout;

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const reload = () => {
    router.get(
        route('admin.reports.verify-transactions'),
        { search: search.value || undefined, idtype: idtype.value },
        { preserveState: true, replace: true },
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(reload, 300);
});
watch(idtype, reload);

const statusColor = (s) => {
    const k = String(s ?? '').toLowerCase();
    if (['success', 'completed', 'found'].includes(k)) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    if (['pending', 'processing'].includes(k)) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
    if (['fail', 'failed', 'not found'].includes(k)) return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
};
</script>

<template>
    <Head title="NIN/BVN Transactions" />

    <AdminLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">NIN/BVN Transactions</h1>

            <!-- Summary -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Verifications</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_count.toLocaleString() }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Revenue (success)</p>
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ formatCurrency(stats.total_revenue) }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="flex flex-col md:flex-row gap-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by ID, value, name, status..."
                        class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                    <select v-model="idtype" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="all">All ID Types</option>
                        <option v-for="t in idtypes" :key="t" :value="t">{{ t.toUpperCase() }}</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Slip / Channel</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="transactions.data.length === 0">
                            <td colspan="9" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No transactions found.</td>
                        </tr>
                        <tr v-for="t in transactions.data" :key="t.id">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-gray-300">{{ String(t.id).slice(0, 10) }}…</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ t.user }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 uppercase">{{ t.idtype }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-700 dark:text-gray-300">{{ t.idvalue }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ t.name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ t.sliptype }}<span v-if="t.channel" class="text-gray-400"> · {{ t.channel }}</span></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ formatCurrency(t.price) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full capitalize', statusColor(t.status)]">{{ t.status }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ t.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex justify-center">
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in transactions.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium',
                            link.active ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                            !link.url && 'opacity-50 cursor-not-allowed pointer-events-none',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
