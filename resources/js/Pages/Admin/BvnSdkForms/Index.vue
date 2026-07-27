<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    forms: Object,
    filters: Object,
    statuses: Array,
    stats: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');

const applyFilters = () => {
    router.get(route('admin.bvn-sdk-forms.index'), { search: search.value, status: status.value }, {
        preserveState: true, preserveScroll: true,
    });
};

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true }); };

const statusClass = (s) => {
    const map = {
        onboarded: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        submitted: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
        picked: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    };
    return map[s?.toLowerCase()] || 'bg-gray-100 text-gray-800';
};

const statCards = [
    { key: 'total', label: 'Total Registrations', color: 'bg-violet-500' },
    { key: 'today', label: 'Today', color: 'bg-blue-500' },
    { key: 'onboarded', label: 'Onboarded', color: 'bg-green-500' },
    { key: 'pending', label: 'Pending', color: 'bg-yellow-500' },
];
</script>

<template>
    <Head title="BVN SDK Onboarding Management" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">BVN SDK Onboarding</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">View and manage all BVN SDK onboarding registrations.</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="card in statCards" :key="card.key" class="bg-white dark:bg-slate-800 rounded-xl shadow p-4 flex items-center gap-3">
                    <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold', card.color]">{{ stats[card.key] }}</div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats[card.key] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input v-model="search" type="text" placeholder="Search by name, email, phone, user..." @keyup.enter="applyFilters"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select v-model="status" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="all">All Statuses</option>
                            <option v-for="s in statuses" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Zone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="f in forms.data" :key="f.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ f.firstName }} {{ f.lastName }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ f.user?.username || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ f.email }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ f.phoneNumber }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ f.zone?.replaceAll('-', ' ') }}</td>
                                <td class="px-6 py-4"><span :class="['px-2 py-1 text-xs rounded-full font-medium capitalize', statusClass(f.status)]">{{ f.status }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ f.created_at }}</td>
                                <td class="px-6 py-4"><Link :href="route('admin.bvn-sdk-forms.show', f.id)" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm font-medium">Manage</Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="forms.data.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">No registrations found.</div>
                <div v-if="forms.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ forms.from }} to {{ forms.to }} of {{ forms.total }} results</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(forms.prev_page_url)" :disabled="!forms.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(forms.next_page_url)" :disabled="!forms.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
