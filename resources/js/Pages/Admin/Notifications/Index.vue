<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    notifications: Array,
    stats: Object,
});

const tab = ref('list');
const editingId = ref(null);

const form = useForm({
    title: '',
    message: '',
    isEnabled: true,
    duration: null,
    expiresAt: '',
    userId: '',
});

const statCards = computed(() => [
    { label: 'Total', value: props.stats.total, color: 'bg-blue-500' },
    { label: 'Active', value: props.stats.active, color: 'bg-green-500' },
    { label: 'Expired', value: props.stats.expired, color: 'bg-red-500' },
    { label: 'Global', value: props.stats.global, color: 'bg-indigo-500' },
    { label: 'User-Specific', value: props.stats.userSpecific, color: 'bg-purple-500' },
]);

const startCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    tab.value = 'create';
};

const startEdit = (n) => {
    editingId.value = n.id;
    form.clearErrors();
    form.title = n.title;
    form.message = n.message;
    form.isEnabled = n.isEnabled;
    form.duration = n.duration;
    // datetime-local wants "YYYY-MM-DDTHH:mm"
    form.expiresAt = n.expiresAt ? new Date(n.expiresAt).toISOString().slice(0, 16) : '';
    form.userId = n.userId || '';
    tab.value = 'create';
};

const submit = () => {
    if (editingId.value) {
        form.put(route('admin.notifications.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => { form.reset(); editingId.value = null; tab.value = 'list'; },
        });
    } else {
        form.post(route('admin.notifications.store'), {
            preserveScroll: true,
            onSuccess: () => { form.reset(); tab.value = 'list'; },
        });
    }
};

const toggle = (n) => {
    router.patch(route('admin.notifications.toggle', n.id), {}, { preserveScroll: true });
};

const destroy = (n) => {
    if (!confirm(`Permanently delete the notification "${n.title}"?`)) return;
    router.delete(route('admin.notifications.destroy', n.id), { preserveScroll: true });
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' }) : '-');

const isExpired = (iso) => iso && new Date(iso) < new Date();

const expiryLabel = (iso) => {
    if (!iso) return '—';
    const diff = new Date(iso).getTime() - Date.now();
    if (diff <= 0) return 'Expired';
    const days = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    if (days > 0) return `${days}d ${hours}h left`;
    if (hours > 0) return `${hours}h left`;
    return '< 1h left';
};
</script>

<template>
    <Head title="Notification Management" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notification Management</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Create global or user-specific announcements shown to users.</p>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div v-for="card in statCards" :key="card.label" class="bg-white dark:bg-slate-800 rounded-xl shadow p-4 flex items-center gap-3">
                    <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold', card.color]">{{ card.value }}</div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ card.value }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
                <button @click="tab = 'list'" :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px', tab === 'list' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                    Notification List
                </button>
                <button @click="startCreate" :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px', tab === 'create' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
                    {{ editingId ? 'Edit Notification' : 'Create Notification' }}
                </button>
            </div>

            <!-- List -->
            <div v-show="tab === 'list'" class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Expires</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="n in notifications" :key="n.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white flex items-center gap-2">
                                        {{ n.title }}
                                        <span :class="['w-2 h-2 rounded-full', n.is_global ? 'bg-blue-500' : 'bg-green-500']"></span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[280px]">{{ n.message }}</p>
                                    <p v-if="n.duration" class="text-xs text-gray-400 mt-0.5">Auto-dismiss: {{ n.duration }}s</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 text-xs rounded-full border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">{{ n.is_global ? 'Global' : 'User' }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(n.createdAt) }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span v-if="!n.expiresAt" class="text-gray-400">—</span>
                                    <span v-else :class="isExpired(n.expiresAt) ? 'text-red-600' : 'text-gray-600 dark:text-gray-300'">{{ expiryLabel(n.expiresAt) }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span :class="['px-2 py-0.5 text-xs rounded-full font-medium w-fit', n.isEnabled ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300']">
                                            {{ n.isEnabled ? 'Active' : 'Disabled' }}
                                        </span>
                                        <span v-if="isExpired(n.expiresAt)" class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 w-fit">Expired</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <button @click="toggle(n)" :title="n.isEnabled ? 'Disable' : 'Enable'" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="n.isEnabled" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                            </svg>
                                        </button>
                                        <button @click="startEdit(n)" title="Edit" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="destroy(n)" title="Delete" class="text-red-600 hover:text-red-800 dark:text-red-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="notifications.length === 0" class="py-16 text-center">
                    <p class="text-gray-500 dark:text-gray-400 mb-4">No notifications have been created yet.</p>
                    <button @click="startCreate" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">Create Your First Notification</button>
                </div>
            </div>

            <!-- Create / Edit form -->
            <div v-show="tab === 'create'" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6 max-w-2xl">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <input v-model="form.title" type="text" placeholder="Notification title"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message</label>
                        <textarea v-model="form.message" rows="4" placeholder="Notification message"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
                        <p v-if="form.errors.message" class="mt-1 text-xs text-red-600">{{ form.errors.message }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Duration (seconds)</label>
                            <input v-model.number="form.duration" type="number" min="1" placeholder="Auto-dismiss after (optional)"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                            <p v-if="form.errors.duration" class="mt-1 text-xs text-red-600">{{ form.errors.duration }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expires At</label>
                            <input v-model="form.expiresAt" type="datetime-local"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                            <p v-if="form.errors.expiresAt" class="mt-1 text-xs text-red-600">{{ form.errors.expiresAt }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User ID (optional)</label>
                        <input v-model="form.userId" type="text" placeholder="Leave empty for a global notification"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono" />
                        <p class="mt-1 text-xs text-gray-500">Target a specific user by ID, or leave empty to show to all users.</p>
                        <p v-if="form.errors.userId" class="mt-1 text-xs text-red-600">{{ form.errors.userId }}</p>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Enable Notification</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">When enabled, this notification is visible to users.</p>
                        </div>
                        <button type="button" @click="form.isEnabled = !form.isEnabled"
                            :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors', form.isEnabled ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600']">
                            <span :class="['inline-block h-4 w-4 transform rounded-full bg-white transition-transform', form.isEnabled ? 'translate-x-6' : 'translate-x-1']"></span>
                        </button>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="tab = 'list'; editingId = null; form.reset()" class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : (editingId ? 'Update Notification' : 'Create Notification') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
