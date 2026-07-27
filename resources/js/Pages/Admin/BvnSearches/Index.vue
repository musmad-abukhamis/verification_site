<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');

const applyFilters = () => {
    router.get(route('admin.bvn-searches.index'), { search: search.value, status: status.value }, {
        preserveState: true, preserveScroll: true,
    });
};

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true }); };

const statusClass = (s) => s === 'success'
    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';

const statCards = [
    { key: 'total', label: 'Total Searches', color: 'bg-lime-500', money: false },
    { key: 'success', label: 'Successful', color: 'bg-green-500', money: false },
    { key: 'failed', label: 'Failed', color: 'bg-red-500', money: false },
    { key: 'revenue', label: 'Revenue', color: 'bg-emerald-600', money: true },
];
</script>

<template>
    <Head title="BVN Search Logs" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">BVN Search Logs</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">BVN verification (search) activity logged from the user BVN Search service.</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="card in statCards" :key="card.key" class="bg-white dark:bg-slate-800 rounded-xl shadow p-4 flex items-center gap-3">
                    <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-white', card.color]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ card.money ? '₦' + Number(stats[card.key]).toLocaleString() : stats[card.key] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input v-model="search" type="text" placeholder="Search by BVN, name, user..." @keyup.enter="applyFilters"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select v-model="status" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">All</option>
                            <option value="success">Success</option>
                            <option value="fail">Failed</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button @click="applyFilters" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">Filter</button>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">BVN</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Slip</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="l in logs.data" :key="l.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <div v-if="l.user" class="text-sm">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ l.user.username || l.user.name }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">{{ l.user.email }}</div>
                                    </div>
                                    <span v-else class="text-sm text-gray-500">Unknown</span>
                                </td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-white">{{ l.bvn }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ l.name || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ l.slip_type }}</td>
                                <td class="px-6 py-4"><span :class="['px-2 py-1 text-xs rounded-full font-medium capitalize', statusClass(l.status)]">{{ l.status }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">₦{{ Number(l.price || 0).toLocaleString() }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ l.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="logs.data.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">No BVN searches found.</div>
                <div v-if="logs.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} results</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(logs.prev_page_url)" :disabled="!logs.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(logs.next_page_url)" :disabled="!logs.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
