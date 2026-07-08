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

const applyFilters = () => {
    router.get(route('admin.idcard.index'), {
        search: search.value,
        status: status.value,
    }, { preserveState: true, preserveScroll: true });
};

const goToPage = (url) => {
    if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

const statusClass = (s) => {
    const map = {
        approved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    };
    return map[s?.toLowerCase()] || 'bg-gray-100 text-gray-800';
};

const statCards = [
    { key: 'total', label: 'Total Applications', color: 'bg-blue-500' },
    { key: 'pending', label: 'Pending', color: 'bg-yellow-500' },
    { key: 'approved', label: 'Approved', color: 'bg-green-500' },
    { key: 'rejected', label: 'Rejected', color: 'bg-red-500' },
];

// ---- Manage modal (details + status update + delete) ----
const selected = ref(null);

const statusForm = useForm({
    status: 'pending',
    comment: '',
});

const openManage = (r) => {
    selected.value = r;
    statusForm.clearErrors();
    statusForm.status = r.status || 'pending';
    statusForm.comment = r.comment || '';
};

const closeManage = () => {
    selected.value = null;
};

const submitStatus = () => {
    statusForm.patch(route('admin.idcard.status', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => closeManage(),
    });
};

const destroy = (r) => {
    if (!confirm('This action cannot be undone. Permanently delete this ID card application?')) return;
    router.delete(route('admin.idcard.destroy', r.id), {
        preserveScroll: true,
        onSuccess: () => closeManage(),
    });
};
</script>

<template>
    <Head title="ID Card Management" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">ID Card Management</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Review applications, update status, and download passport images.</p>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

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
                        <input v-model="search" type="text" placeholder="Search by name, email, agent ID, user..." @keyup.enter="applyFilters"
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

            <!-- Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Submitted By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Full Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Agent ID</th>
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
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ r.fullname }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-700 dark:text-gray-300">{{ r.agentId }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">₦{{ Number(r.amount_charged || 0).toLocaleString() }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['px-2 py-1 text-xs rounded-full font-medium capitalize', statusClass(r.status)]">{{ r.status }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ r.created_at }}</td>
                                <td class="px-6 py-4">
                                    <button @click="openManage(r)" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm font-medium">Manage</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="requests.data.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">No applications found.</div>

                <div v-if="requests.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ requests.from }} to {{ requests.to }} of {{ requests.total }} results</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(requests.prev_page_url)" :disabled="!requests.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(requests.next_page_url)" :disabled="!requests.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manage Modal -->
        <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50" @click.self="closeManage">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">ID Card Application</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Submitted on {{ selected.created_at }}</p>
                    </div>
                    <button @click="closeManage" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-5">
                    <!-- Details -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-medium text-gray-500">Submitted By</label>
                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ selected.user?.username || selected.user?.name || 'Unknown' }}</p>
                            <p class="text-xs text-gray-500">{{ selected.user?.email }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Full Name</label>
                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ selected.fullname }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Email</label>
                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ selected.email }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Agent ID</label>
                            <p class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ selected.agentId }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Amount Charged</label>
                            <p class="text-sm text-gray-900 dark:text-gray-100">₦{{ selected.amount_charged }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500">Balance Change</label>
                            <p class="text-sm text-gray-900 dark:text-gray-100">₦{{ selected.old_balance }} → ₦{{ selected.new_balance }}</p>
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-medium text-gray-500">Passport Photo</label>
                            <div class="mt-2 flex items-end gap-3">
                                <img :src="selected.image_url" alt="Passport" class="w-32 h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-600" />
                                <a :href="selected.image_url" :download="`passport_${selected.fullname.replace(/\s+/g, '_')}_${selected.agentId}.jpg`"
                                    class="inline-flex items-center gap-2 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Download
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Status update -->
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-4">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Update Status</h4>
                        <div v-if="statusForm.errors.message" class="p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ statusForm.errors.message }}</div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label>
                            <select v-model="statusForm.status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option v-for="s in statuses" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Comment <span v-if="statusForm.status === 'rejected'" class="text-red-500">*</span>
                            </label>
                            <textarea v-model="statusForm.comment" rows="3" placeholder="Add any notes or comments about this application..."
                                :class="['w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white',
                                    statusForm.status === 'rejected' && !statusForm.comment.trim() ? 'border-red-500' : '']"></textarea>
                            <p v-if="statusForm.status === 'rejected' && !statusForm.comment.trim()" class="mt-1 text-xs text-red-500">Comment is required when rejecting an application.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 p-5 border-t border-gray-200 dark:border-gray-700">
                    <button @click="destroy(selected)" class="inline-flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:text-red-700 border border-red-200 dark:border-red-900 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete
                    </button>
                    <div class="flex gap-2">
                        <button @click="closeManage" class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300">Cancel</button>
                        <button @click="submitStatus" :disabled="statusForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-50">
                            <svg v-if="statusForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ statusForm.processing ? 'Updating...' : 'Update Application' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
