<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const reload = () => {
    router.get(
        route('reports.verify-transactions'),
        { search: search.value || undefined, idtype: idtype.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(reload, 350);
});
watch(idtype, reload);

const statusColor = (s) => {
    const k = String(s ?? '').toLowerCase();
    if (['success', 'completed', 'found'].includes(k)) return 'text-green-700 bg-green-100';
    if (['pending', 'processing'].includes(k)) return 'text-yellow-700 bg-yellow-100';
    if (['fail', 'failed', 'not found'].includes(k)) return 'text-red-700 bg-red-100';
    return 'text-gray-700 bg-gray-100';
};
</script>

<template>
    <Head title="NIN/BVN Transactions" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">NIN/BVN Transactions</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Summary cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.total_count }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Total Spent</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(stats.total_spent) }}</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="flex flex-col md:flex-row gap-3">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by ID, value, name, status..."
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <select v-model="idtype" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">All ID Types</option>
                            <option v-for="t in idtypes" :key="t" :value="t">{{ t.toUpperCase() }}</option>
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
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Value</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slip Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prev. Bal</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Bal</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-500">{{ String(t.id).slice(0, 10) }}…</td>
                                        <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full bg-indigo-100 text-indigo-700 uppercase">{{ t.idtype }}</span></td>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ t.idvalue }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ t.name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ t.sliptype }}</td>
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ formatCurrency(t.price) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatCurrency(t.old_balance) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatCurrency(t.new_balance) }}</td>
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
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700 uppercase">{{ t.idtype }}</span>
                                        <p class="font-mono text-sm mt-1">{{ t.idvalue }}</p>
                                    </div>
                                    <span :class="['px-2 py-1 text-xs rounded-full capitalize', statusColor(t.status)]">{{ t.status }}</span>
                                </div>
                                <p class="text-sm text-gray-900">{{ t.name }}</p>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div><p class="text-gray-500">Slip Type</p><p class="font-medium">{{ t.sliptype }}</p></div>
                                    <div><p class="text-gray-500">Amount</p><p class="font-semibold">{{ formatCurrency(t.price) }}</p></div>
                                    <div><p class="text-gray-500">Prev. Bal</p><p class="font-medium">{{ formatCurrency(t.old_balance) }}</p></div>
                                    <div><p class="text-gray-500">New Bal</p><p class="font-medium">{{ formatCurrency(t.new_balance) }}</p></div>
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
