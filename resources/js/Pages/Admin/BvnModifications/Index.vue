<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    requests: Object,
    filters: Object,
    statuses: Array,
    serviceTypes: Array,
    stats: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const serviceType = ref(props.filters?.serviceType || 'all');

const applyFilters = () => {
    router.get(route('admin.bvn-modifications.index'), {
        search: search.value,
        status: status.value,
        serviceType: serviceType.value,
    }, { preserveState: true, preserveScroll: true });
};

const goToPage = (url) => {
    if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

const statusClass = (s) => {
    const map = {
        modified: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        picked: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    };
    return map[s?.toLowerCase()] || 'bg-gray-100 text-gray-800';
};

const statCards = [
    { key: 'total', label: 'Total Requests', color: 'bg-blue-500' },
    { key: 'pending', label: 'Pending', color: 'bg-yellow-500' },
    { key: 'modified', label: 'Modified', color: 'bg-green-500' },
    { key: 'rejected', label: 'Rejected', color: 'bg-red-500' },
];
</script>

<template>
    <Head title="BVN Modification Management" />
    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">BVN Modification Management</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Review requests, update status and track processing.</p>
                </div>
                <Link :href="route('admin.bvn-prices.index')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                    Set Prices
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="card in statCards" :key="card.key" class="bg-white dark:bg-slate-800 rounded-xl shadow p-4 flex items-center gap-3">
                    <div :class="['w-10 h-10 rounded-lg flex items-center justify-center', card.color]">
                        <span class="text-white font-bold">{{ stats[card.key] }}</span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats[card.key] }}</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input v-model="search" type="text" placeholder="Search by BVN, NIN, user..." @keyup.enter="applyFilters"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select v-model="status" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">All Statuses</option>
                            <option v-for="s in statuses" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service Type</label>
                        <select v-model="serviceType" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">All Types</option>
                            <option v-for="t in serviceTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">BVN</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Service Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <div v-if="r.user" class="text-sm">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ r.user.username || r.user.name }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">{{ r.user.email }}</div>
                                    </div>
                                    <span v-else class="text-sm text-gray-500">Unknown</span>
                                </td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-white">{{ r.bvn }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ r.service_label }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">₦{{ Number(r.amount_charged || 0).toLocaleString() }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['px-2 py-1 text-xs rounded-full font-medium capitalize', statusClass(r.status)]">{{ r.status }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ r.created_at }}</td>
                                <td class="px-6 py-4">
                                    <Link :href="route('admin.bvn-modifications.show', r.id)" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm font-medium">Manage</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="requests.data.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">No requests found.</div>

                <div v-if="requests.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ requests.from }} to {{ requests.to }} of {{ requests.total }} results</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(requests.prev_page_url)" :disabled="!requests.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(requests.next_page_url)" :disabled="!requests.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
