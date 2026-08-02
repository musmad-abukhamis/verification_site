<script setup>
/**
 * Admin queue for IPE clearances.
 *
 * Clearance is supposed to settle within three hours, so the "stalled" filter is
 * the one an admin lives in: everything still open past that window is work that
 * needs chasing, not work that is merely in progress.
 */
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    clearances: Object,
    filters: Object,
    statuses: Array,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const stalled = ref(Boolean(props.filters?.stalled));

const applyFilters = () => {
    router.get(route('admin.nin-ipe.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        stalled: stalled.value ? 1 : undefined,
    }, { preserveState: true, replace: true });
};

watch([status, stalled], applyFilters);
</script>

<template>
    <Head title="IPE Clearances" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">IPE Clearances</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Submitted clearances and their progress. Clearance normally completes in 30 minutes to 3 hours.
                </p>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="search"
                        placeholder="Tracking ID, user or comment…"
                        class="flex-1 min-w-[16rem] rounded-md border-gray-300 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-200 text-sm"
                    />
                    <select
                        v-model="status"
                        aria-label="Filter by status"
                        class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-200 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input v-model="stalled" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" />
                        Overdue only (open &gt; 3h)
                    </label>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-slate-900">
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Tracking ID</th>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Provider</th>
                                <th class="px-4 py-3">Fee</th>
                                <th class="px-4 py-3">Submitted</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <tr v-for="row in clearances.data" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">{{ row.tracking_id }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    <span v-if="row.user">{{ row.user.name }}</span>
                                    <span v-else class="text-gray-400">deleted user</span>
                                </td>
                                <td class="px-4 py-3">
                                    <StatusPill :status="row.status" />
                                    <span v-if="row.refunded_at" class="ml-2 text-xs text-amber-600 dark:text-amber-400">refunded</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ row.provider || '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">₦{{ Number(row.fee).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ row.created_at }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('admin.nin-ipe.show', row.id)"
                                        class="text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium"
                                    >
                                        Manage
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState
                    v-if="!clearances.data.length"
                    title="No clearances"
                    description="Nothing matches these filters."
                />
            </div>

            <Pagination :paginator="clearances" label="clearances" />
        </div>
    </AdminLayout>
</template>
