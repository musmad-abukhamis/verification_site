<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    validations: Object,
    filters: Object,
    statuses: Array,
    providers: Array,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const provider = ref(props.filters.provider || '');

const applyFilters = () => {
    router.get(route('admin.nin-validations.index'), {
        search: search.value,
        status: status.value,
        provider: provider.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const getStatusClass = (status) => {
    const classes = {
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};

const getProviderLabel = (provider) => {
    const labels = {
        v1: 'V1 (Prembly)',
        v2: 'V2 (ArewaSmart)',
        demo: 'Demo',
        phone: 'Phone',
    };
    return labels[provider] || provider;
};
</script>

<template>
    <Head title="NIN Validation Management" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">NIN Validations</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Manage and monitor NIN verification records</p>
                </div>
                <Link
                    :href="route('admin.nin-validations.stats')"
                    class="px-4 py-2 bg-lime-600 hover:bg-lime-700 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    View Statistics
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input
                            type="text"
                            v-model="search"
                            placeholder="Search by NIN, name, or email..."
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select
                            v-model="status"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">All Statuses</option>
                            <option v-for="s in statuses" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Provider</label>
                        <select
                            v-model="provider"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">All Providers</option>
                            <option v-for="p in providers" :key="p" :value="p">{{ getProviderLabel(p) }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="applyFilters"
                            class="px-4 py-2 bg-lime-600 hover:bg-lime-700 text-white text-sm font-medium rounded-lg transition-colors"
                        >
                            Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Validations Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">NIN</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ID Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Provider</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Fee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="validation in validations.data" :key="validation.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ validation.id }}</td>
                                <td class="px-6 py-4">
                                    <div v-if="validation.user" class="text-sm">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ validation.user.name }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">{{ validation.user.email }}</div>
                                    </div>
                                    <span v-else class="text-sm text-gray-500">Unknown</span>
                                </td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-white">{{ validation.nin }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ validation.id_type }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ getProviderLabel(validation.provider) }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">₦{{ Number(validation.verification_fee).toLocaleString() }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['px-2 py-1 text-xs rounded-full font-medium', getStatusClass(validation.status)]">
                                        {{ validation.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ validation.created_at }}</td>
                                <td class="px-6 py-4">
                                    <Link
                                        :href="route('admin.nin-validations.show', validation.id)"
                                        class="text-lime-600 hover:text-lime-800 dark:text-lime-400 text-sm font-medium"
                                    >
                                        View Details
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-if="validations.data.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">
                    No validations found.
                </div>

                <!-- Pagination -->
                <div v-if="validations.data.length > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Showing {{ validations.from }} to {{ validations.to }} of {{ validations.total }} results
                    </div>
                    <div class="flex gap-2">
                        <template v-for="link in validations.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1 rounded text-sm',
                                    link.active
                                        ? 'bg-lime-600 text-white'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                :class="[
                                    'px-3 py-1 rounded text-sm bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500 cursor-not-allowed'
                                ]"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
