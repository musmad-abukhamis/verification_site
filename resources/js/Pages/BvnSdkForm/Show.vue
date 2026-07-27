<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    form: Object,
});

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'long', day: 'numeric' });
};

const statusClass = (s) => {
    const map = {
        onboarded: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        submitted: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
        picked: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[s?.toLowerCase()] || map.pending;
};
</script>

<template>
    <Head title="BVN Onboarding Details" />
    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <Link :href="route('bvn-sdk-form.submissions')" class="inline-flex items-center gap-1 text-sm text-violet-600 dark:text-violet-400 hover:underline">← Back to submissions</Link>

            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ form.firstName }} {{ form.lastName }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-mono">ID: {{ form.id }}</p>
                </div>
                <span :class="['inline-flex px-3 py-1 text-xs rounded-full font-medium capitalize', statusClass(form.status)]">{{ form.status }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Personal Information</h3>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.email }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Phone Number</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.phoneNumber }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.address }}</dd></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><dt class="text-gray-500 dark:text-gray-400">Date of Birth</dt><dd class="font-medium text-gray-900 dark:text-white">{{ formatDate(form.dateOfBirth) }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Registered</dt><dd class="font-medium text-gray-900 dark:text-white">{{ formatDate(form.created_at) }}</dd></div>
                        </div>
                    </dl>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Location & Banking</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-4">
                            <div><dt class="text-gray-500 dark:text-gray-400">State</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.stateOfResidence }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">LGA</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.lga }}</dd></div>
                        </div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Zone</dt><dd class="font-medium text-gray-900 dark:text-white capitalize">{{ form.zone?.replaceAll('-', ' ') }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Agent Location</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.agentLocation }}</dd></div>
                        <div class="pt-3 border-t border-gray-100 dark:border-gray-700"><dt class="text-gray-500 dark:text-gray-400">BVN</dt><dd class="font-medium text-gray-900 dark:text-white font-mono">{{ form.agentBvn }}</dd></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><dt class="text-gray-500 dark:text-gray-400">Bank</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.bankName }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Account No.</dt><dd class="font-medium text-gray-900 dark:text-white font-mono">{{ form.accountNumber }}</dd></div>
                        </div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Account Name</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.accountName }}</dd></div>
                    </dl>
                </div>
            </div>

            <div v-if="form.comment" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Admin Comment</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ form.comment }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
