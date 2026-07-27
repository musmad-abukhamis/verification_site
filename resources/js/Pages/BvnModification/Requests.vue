<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    requests: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const serviceType = ref(props.filters?.serviceType || 'all');
const dateFrom = ref(props.filters?.dateFrom || '');
const dateTo = ref(props.filters?.dateTo || '');
const showAdvanced = ref(false);

const applyFilters = () => {
    router.get(route('bvn-modification.requests'), {
        search: search.value,
        status: status.value,
        serviceType: serviceType.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value,
    }, { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    search.value = '';
    status.value = 'all';
    serviceType.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

const goToPage = (url) => {
    if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusClass = (s) => {
    const map = {
        modified: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        picked: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[s?.toLowerCase()] || map.pending;
};
</script>

<template>
    <Head title="My BVN Modification Requests" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My BVN Modification Requests</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">View and track your submitted BVN modification requests.</p>
                </div>
                <Link :href="route('bvn-modification.index')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                    New Request
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input v-model="search" type="text" placeholder="Search by BVN, NIN..." @keyup.enter="applyFilters"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select v-model="status" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="modified">Modified</option>
                            <option value="rejected">Rejected</option>
                            <option value="picked">Picked</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service Type</label>
                        <select v-model="serviceType" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">All types</option>
                            <option value="modify-name">Name Modification</option>
                            <option value="modify-dob">DOB Modification</option>
                            <option value="modify-name-dob">Name & DOB Modification</option>
                            <option value="modify-phone">Phone Modification</option>
                            <option value="modify-name-dob-phone">Name, DOB & Phone Modification</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button @click="applyFilters" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg">Search</button>
                        <button @click="showAdvanced = !showAdvanced" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300">Filters</button>
                    </div>
                </div>
                <div v-if="showAdvanced" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 p-4 bg-gray-50 dark:bg-gray-700/40 rounded-lg">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
                        <input v-model="dateFrom" type="date" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
                        <input v-model="dateTo" type="date" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div class="flex items-end">
                        <button @click="clearFilters" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300">Clear All Filters</button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BVN</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prev. Bal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Bal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ r.bvn }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ r.service_label }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 max-w-[160px] truncate">{{ r.comment || 'No comment' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(r.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium capitalize', statusClass(r.status)]">{{ r.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ r.old_balance ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ r.new_balance ?? 'N/A' }}</td>
                                <td class="px-4 py-3">
                                    <Link :href="route('bvn-modification.show', r.id)" class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 text-sm font-medium">View Details</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="requests.data.length === 0" class="py-10 text-center text-gray-400">No BVN modification requests found.</div>

                <div v-if="requests.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ requests.from }}–{{ requests.to }} of {{ requests.total }}</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(requests.prev_page_url)" :disabled="!requests.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(requests.next_page_url)" :disabled="!requests.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
