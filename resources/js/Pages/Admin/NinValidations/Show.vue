<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    validation: Object,
});

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
    <Head title="NIN Validation Details" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">NIN Validation Details</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">View detailed information about this verification</p>
                </div>
                <Link
                    :href="route('admin.nin-validations.index')"
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white text-sm font-medium rounded-lg transition-colors"
                >
                    Back to List
                </Link>
            </div>

            <!-- Validation Info Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Basic Information</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">ID</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ validation.id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">NIN</span>
                            <span class="font-mono font-medium text-gray-900 dark:text-white">{{ validation.nin }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">ID Type</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ validation.id_type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">ID Value</span>
                            <span class="font-mono font-medium text-gray-900 dark:text-white">{{ validation.id_value }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Status</span>
                            <span :class="['px-2 py-0.5 text-xs rounded-full font-medium', getStatusClass(validation.status)]">
                                {{ validation.status }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Verified</span>
                            <span class="font-medium" :class="validation.is_verified ? 'text-green-600' : 'text-red-600'">
                                {{ validation.is_verified ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- User Information -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">User Information</h2>
                    <div v-if="validation.user" class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Name</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ validation.user.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Email</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ validation.user.email }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">User ID</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ validation.user.id }}</span>
                        </div>
                    </div>
                    <div v-else class="text-gray-500">User not found</div>
                </div>

                <!-- Provider & Financial -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Provider & Financial</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Provider</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ getProviderLabel(validation.provider) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Reference</span>
                            <span class="font-mono text-sm text-gray-900 dark:text-white">{{ validation.reference || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Verification Fee</span>
                            <span class="font-medium text-gray-900 dark:text-white">₦{{ Number(validation.verification_fee).toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Old Balance</span>
                            <span class="font-medium text-gray-900 dark:text-white">₦{{ Number(validation.old_balance).toLocaleString() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">New Balance</span>
                            <span class="font-medium text-gray-900 dark:text-white">₦{{ Number(validation.new_balance).toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Timestamps -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Timestamps</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Created At</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ validation.created_at }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Validated At</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ validation.validated_at || '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comment -->
            <div v-if="validation.comment" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Comment</h2>
                <p class="text-gray-600 dark:text-gray-300">{{ validation.comment }}</p>
            </div>

            <!-- Verification Result -->
            <div v-if="validation.result" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Verification Result</h2>
                <pre class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-sm text-gray-700 dark:text-gray-300">{{ JSON.stringify(validation.result, null, 2) }}</pre>
            </div>
        </div>
    </AdminLayout>
</template>
