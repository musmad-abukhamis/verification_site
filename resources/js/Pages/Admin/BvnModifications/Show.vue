<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    request: Object,
    statuses: Array,
});

const statusForm = useForm({
    status: props.request.status,
    comment: props.request.comment || '',
});

const updateStatus = () => {
    statusForm.patch(route('admin.bvn-modifications.status', props.request.id), {
        preserveScroll: true,
    });
};

const destroy = () => {
    if (confirm('Delete this request? This cannot be undone.')) {
        router.delete(route('admin.bvn-modifications.destroy', props.request.id));
    }
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'long', day: 'numeric' });
};

const fullName = (f, m, l) => [f, m, l].filter(Boolean).join(' ') || '-';
</script>

<template>
    <Head title="BVN Modification Request" />
    <AdminLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <Link :href="route('admin.bvn-modifications.index')" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                ← Back to requests
            </Link>

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>
            <div v-if="statusForm.errors.message" class="p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ statusForm.errors.message }}</div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Details -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Request Details</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">ID: {{ request.id }}</p>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Request Information</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">BVN</dt><dd class="font-mono text-gray-900 dark:text-white">{{ request.bvn }}</dd></div>
                                <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">NIN</dt><dd class="font-mono text-gray-900 dark:text-white">{{ request.nin }}</dd></div>
                                <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">Service Type</dt><dd class="text-gray-900 dark:text-white">{{ request.service_label }}</dd></div>
                                <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">Amount</dt><dd class="text-gray-900 dark:text-white">₦{{ Number(request.amount_charged || 0).toLocaleString() }}</dd></div>
                                <div class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">Submitted On</dt><dd class="text-gray-900 dark:text-white">{{ formatDate(request.created_at) }}</dd></div>
                                <div v-if="request.user" class="flex justify-between"><dt class="font-medium text-gray-600 dark:text-gray-400">Submitted By</dt><dd class="text-gray-900 dark:text-white">{{ request.user.username }} ({{ request.user.email }})</dd></div>
                                <div class="flex justify-between items-center pt-1">
                                    <dt class="font-medium text-gray-600 dark:text-gray-400">NIN Slip</dt>
                                    <dd><a :href="route('bvn-modification.slip', request.id)" target="_blank" class="inline-flex items-center gap-1 px-3 py-1 text-xs bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300 rounded hover:bg-indigo-200">View Slip</a></dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Modification Details</h3>
                            <div v-if="request.needs_name" class="mb-4">
                                <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Name</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div><p class="text-gray-500 dark:text-gray-400">Old</p><p class="text-gray-900 dark:text-white">{{ fullName(request.old_first_name, request.old_middle_name, request.old_last_name) }}</p></div>
                                    <div><p class="text-gray-500 dark:text-gray-400">New</p><p class="text-gray-900 dark:text-white">{{ fullName(request.new_first_name, request.new_middle_name, request.new_last_name) }}</p></div>
                                </div>
                            </div>
                            <div v-if="request.needs_dob" class="mb-4">
                                <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Date of Birth</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div><p class="text-gray-500 dark:text-gray-400">Old</p><p class="text-gray-900 dark:text-white">{{ formatDate(request.old_dob) }}</p></div>
                                    <div><p class="text-gray-500 dark:text-gray-400">New</p><p class="text-gray-900 dark:text-white">{{ formatDate(request.new_dob) }}</p></div>
                                </div>
                            </div>
                            <div v-if="request.needs_phone" class="mb-4">
                                <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div><p class="text-gray-500 dark:text-gray-400">Old</p><p class="text-gray-900 dark:text-white">{{ request.old_phone_number || '-' }}</p></div>
                                    <div><p class="text-gray-500 dark:text-gray-400">New</p><p class="text-gray-900 dark:text-white">{{ request.new_phone_number || '-' }}</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status panel -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6 h-fit">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Update Status</h3>
                    <form @submit.prevent="updateStatus" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select v-model="statusForm.status" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option v-for="s in statuses" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Comment <span class="text-xs text-gray-400">(required when rejecting)</span></label>
                            <textarea v-model="statusForm.comment" rows="4" placeholder="Add a comment for the user..."
                                class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"></textarea>
                        </div>
                        <button type="submit" :disabled="statusForm.processing"
                            class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg disabled:opacity-50">
                            {{ statusForm.processing ? 'Saving...' : 'Save Status' }}
                        </button>
                    </form>

                    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button @click="destroy" class="w-full px-4 py-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-sm font-medium rounded-lg hover:bg-red-100">
                            Delete Request
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
