<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    request: Object,
});

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'long', day: 'numeric' });
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

const fullName = (f, m, l) => [f, m, l].filter(Boolean).join(' ') || '-';
</script>

<template>
    <Head title="BVN Modification Request" />
    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <Link :href="route('bvn-modification.requests')" class="inline-flex items-center gap-1 text-sm text-emerald-600 dark:text-emerald-400 hover:underline">
                ← Back to requests
            </Link>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">BVN Modification Request Details</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">Request ID: {{ request.id }}</p>
                    </div>
                    <span :class="['inline-flex px-3 py-1 text-xs rounded-full font-medium capitalize', statusClass(request.status)]">{{ request.status }}</span>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Request info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Request Information</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">BVN</dt><dd class="font-mono text-gray-900 dark:text-white">{{ request.bvn }}</dd></div>
                            <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">NIN</dt><dd class="font-mono text-gray-900 dark:text-white">{{ request.nin }}</dd></div>
                            <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">Service Type</dt><dd class="text-gray-900 dark:text-white">{{ request.service_label }}</dd></div>
                            <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">Amount Charged</dt><dd class="text-gray-900 dark:text-white">₦{{ Number(request.amount_charged || 0).toLocaleString() }}</dd></div>
                            <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">Submitted On</dt><dd class="text-gray-900 dark:text-white">{{ formatDate(request.created_at) }}</dd></div>
                            <div v-if="request.user" class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">Submitted By</dt><dd class="text-gray-900 dark:text-white">{{ request.user.username }} ({{ request.user.email }})</dd></div>
                            <div class="flex justify-between items-center pt-1">
                                <dt class="font-medium text-gray-600 dark:text-gray-400">NIN Slip</dt>
                                <dd>
                                    <a :href="route('bvn-modification.slip', request.id)" target="_blank"
                                        class="inline-flex items-center gap-1 px-3 py-1 text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300 rounded hover:bg-emerald-200">View Slip</a>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Modification details -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Modification Details</h3>

                        <div v-if="request.needs_name" class="mb-4">
                            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Name</h4>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div><p class="text-gray-500 dark:text-gray-400">Old Name</p><p class="text-gray-900 dark:text-white">{{ fullName(request.old_first_name, request.old_middle_name, request.old_last_name) }}</p></div>
                                <div><p class="text-gray-500 dark:text-gray-400">New Name</p><p class="text-gray-900 dark:text-white">{{ fullName(request.new_first_name, request.new_middle_name, request.new_last_name) }}</p></div>
                            </div>
                        </div>

                        <div v-if="request.needs_dob" class="mb-4">
                            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Date of Birth</h4>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div><p class="text-gray-500 dark:text-gray-400">Old DOB</p><p class="text-gray-900 dark:text-white">{{ formatDate(request.old_dob) }}</p></div>
                                <div><p class="text-gray-500 dark:text-gray-400">New DOB</p><p class="text-gray-900 dark:text-white">{{ formatDate(request.new_dob) }}</p></div>
                            </div>
                        </div>

                        <div v-if="request.needs_phone" class="mb-4">
                            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</h4>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div><p class="text-gray-500 dark:text-gray-400">Old Phone</p><p class="text-gray-900 dark:text-white">{{ request.old_phone_number || '-' }}</p></div>
                                <div><p class="text-gray-500 dark:text-gray-400">New Phone</p><p class="text-gray-900 dark:text-white">{{ request.new_phone_number || '-' }}</p></div>
                            </div>
                        </div>

                        <div v-if="request.comment">
                            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Comment</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ request.comment }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
