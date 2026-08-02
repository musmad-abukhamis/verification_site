<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AsyncJobSettlement from '@/Components/Admin/AsyncJobSettlement.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    clearance: Object,
    statuses: { type: Array, default: () => ['processing', 'completed', 'failed'] },
});
</script>

<template>
    <Head title="IPE Clearance" />
    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">IPE Clearance</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">{{ clearance.tracking_id }}</p>
                </div>
                <Link
                    :href="route('admin.nin-ipe.index')"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white text-sm font-medium rounded-lg transition-colors"
                >
                    Back to List
                </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Clearance</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Tracking ID</span>
                            <span class="font-mono font-medium text-gray-900 dark:text-white">{{ clearance.tracking_id }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Status</span>
                            <StatusPill :status="clearance.status" />
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Fee charged</span>
                            <span class="font-medium text-gray-900 dark:text-white">₦{{ Number(clearance.fee).toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Submitted</span>
                            <span class="text-gray-900 dark:text-white">{{ clearance.created_at }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Last updated</span>
                            <span class="text-gray-900 dark:text-white">{{ clearance.updated_at }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">User</h2>
                    <div v-if="clearance.user" class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Name</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ clearance.user.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Email</span>
                            <span class="text-gray-900 dark:text-white">{{ clearance.user.email }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Balance before</span>
                            <span class="text-gray-900 dark:text-white">₦{{ Number(clearance.old_balance).toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Balance after</span>
                            <span class="text-gray-900 dark:text-white">₦{{ Number(clearance.new_balance).toLocaleString() }}</span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500">User no longer exists.</div>
                </div>
            </div>

            <div v-if="clearance.comment" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Latest provider comment</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ clearance.comment }}</p>
            </div>

            <div v-if="clearance.result" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Result</h2>
                <pre class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-sm text-gray-700 dark:text-gray-300">{{ clearance.result }}</pre>
            </div>

            <AsyncJobSettlement
                :record="clearance"
                :statuses="statuses"
                recheck-route="admin.nin-ipe.recheck"
                update-route="admin.nin-ipe.update"
                refund-route="admin.nin-ipe.refund"
            />
        </div>
    </AdminLayout>
</template>
