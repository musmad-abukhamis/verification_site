<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    form: Object,
    statuses: Array,
});

const statusForm = useForm({
    status: ['pending', 'picked', 'onboarded', 'rejected'].includes(props.form.status) ? props.form.status : 'pending',
    comment: props.form.comment || '',
});

const updateStatus = () => {
    statusForm.patch(route('admin.bvn-sdk-forms.status', props.form.id), { preserveScroll: true });
};

const destroy = () => {
    if (confirm('Delete this registration? This cannot be undone.')) {
        router.delete(route('admin.bvn-sdk-forms.destroy', props.form.id));
    }
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'long', day: 'numeric' });
};
</script>

<template>
    <Head title="BVN Onboarding Registration" />
    <AdminLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <Link :href="route('admin.bvn-sdk-forms.index')" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← Back to registrations</Link>

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>
            <div v-if="statusForm.errors.message" class="p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ statusForm.errors.message }}</div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-1">{{ form.firstName }} {{ form.lastName }}</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-mono mb-4">ID: {{ form.id }}</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <dl class="space-y-3 text-sm">
                                <div><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.email }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.phoneNumber }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.address }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">Date of Birth</dt><dd class="font-medium text-gray-900 dark:text-white">{{ formatDate(form.dateOfBirth) }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">Submitted By</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.user?.username }} ({{ form.user?.email }})</dd></div>
                            </dl>
                            <dl class="space-y-3 text-sm">
                                <div><dt class="text-gray-500 dark:text-gray-400">State / LGA</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.stateOfResidence }} / {{ form.lga }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">Zone</dt><dd class="font-medium text-gray-900 dark:text-white capitalize">{{ form.zone?.replaceAll('-', ' ') }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">Agent Location</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.agentLocation }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">BVN</dt><dd class="font-medium text-gray-900 dark:text-white font-mono">{{ form.agentBvn }}</dd></div>
                                <div><dt class="text-gray-500 dark:text-gray-400">Bank / Account</dt><dd class="font-medium text-gray-900 dark:text-white">{{ form.bankName }} — {{ form.accountNumber }} ({{ form.accountName }})</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>

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
                            <textarea v-model="statusForm.comment" rows="4" placeholder="Add a comment..." class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"></textarea>
                        </div>
                        <button type="submit" :disabled="statusForm.processing" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg disabled:opacity-50">
                            {{ statusForm.processing ? 'Saving...' : 'Save Status' }}
                        </button>
                    </form>
                    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button @click="destroy" class="w-full px-4 py-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-sm font-medium rounded-lg hover:bg-red-100">Delete Registration</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
