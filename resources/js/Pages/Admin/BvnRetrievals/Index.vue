<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    requests: Object,
    filters: Object,
    statuses: Array,
    stats: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const editing = ref(null);

const editForm = useForm({ status: 'pending', bvn: '', comment: '' });

const openEdit = (r) => {
    editing.value = r;
    editForm.clearErrors();
    editForm.status = props.statuses.includes(r.status) ? r.status : 'pending';
    editForm.bvn = r.bvn || '';
    editForm.comment = r.comment || '';
};

const saveEdit = () => {
    editForm.patch(route('admin.bvn-retrievals.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
};

const destroy = (r) => {
    if (confirm('Delete this request? This cannot be undone.')) {
        router.delete(route('admin.bvn-retrievals.destroy', r.id), { preserveScroll: true });
    }
};

const applyFilters = () => {
    router.get(route('admin.bvn-retrievals.index'), { search: search.value, status: status.value }, {
        preserveState: true, preserveScroll: true,
    });
};

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true }); };

const statusClass = (s) => {
    const map = {
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    };
    return map[s?.toLowerCase()] || 'bg-gray-100 text-gray-800';
};

const statCards = [
    { key: 'total', label: 'Total', color: 'bg-sky-500' },
    { key: 'pending', label: 'Pending', color: 'bg-yellow-500' },
    { key: 'processing', label: 'Processing', color: 'bg-blue-500' },
    { key: 'completed', label: 'Completed', color: 'bg-green-500' },
];
</script>

<template>
    <Head title="BVN Retrieval Management" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">BVN Retrieval Requests</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Review requests, provide the retrieved BVN and update status.</p>
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

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input v-model="search" type="text" placeholder="Search by ticket ID, BVN, NIN, user..." @keyup.enter="applyFilters"
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">BMS ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">NIN</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">BVN</th>
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
                                <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-white">{{ r.ticketId2 || '-' }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-600 dark:text-gray-400">{{ r.nin || '-' }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-white">{{ r.bvn || '—' }}</td>
                                <td class="px-6 py-4"><span :class="['px-2 py-1 text-xs rounded-full font-medium capitalize', statusClass(r.status)]">{{ r.status || 'pending' }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ r.created_at }}</td>
                                <td class="px-6 py-4 text-sm space-x-3 whitespace-nowrap">
                                    <button @click="openEdit(r)" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-medium">Update</button>
                                    <button @click="destroy(r)" class="text-red-600 hover:text-red-800 dark:text-red-400 font-medium">Delete</button>
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

        <!-- Update modal -->
        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="editing = null">
            <div class="fixed inset-0 bg-black/50"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Update BVN Retrieval Request</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Provide the retrieved BVN and update the status.</p>

                <div class="bg-gray-50 dark:bg-gray-700/40 rounded-lg p-3 mb-4 grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500 dark:text-gray-400">Submitted by</span><p class="text-gray-900 dark:text-white">{{ editing.user?.username || editing.user?.name }}</p></div>
                    <div><span class="text-gray-500 dark:text-gray-400">BMS ID</span><p class="font-mono text-gray-900 dark:text-white">{{ editing.ticketId2 || '-' }}</p></div>
                    <div><span class="text-gray-500 dark:text-gray-400">NIN</span><p class="font-mono text-gray-900 dark:text-white">{{ editing.nin || '-' }}</p></div>
                    <div><span class="text-gray-500 dark:text-gray-400">Batch ID</span><p class="font-mono text-gray-900 dark:text-white">{{ editing.batchId || '-' }}</p></div>
                </div>

                <div v-if="editForm.errors.message" class="mb-3 p-2 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded text-sm">{{ editForm.errors.message }}</div>

                <form @submit.prevent="saveEdit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label>
                            <select v-model="editForm.status" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option v-for="s in statuses" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">BVN <span v-if="editForm.status === 'completed'" class="text-red-500">*</span></label>
                            <input v-model="editForm.bvn" type="text" maxlength="11" placeholder="Enter 11-digit BVN" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comment</label>
                        <textarea v-model="editForm.comment" rows="3" placeholder="Add any notes about this request..." class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="editing = null" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300">Cancel</button>
                        <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg disabled:opacity-50">
                            {{ editForm.processing ? 'Updating...' : 'Update Request' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
