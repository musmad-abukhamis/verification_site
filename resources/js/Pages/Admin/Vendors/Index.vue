<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    vendors: { type: Array, default: () => [] },
    drivers: { type: Array, default: () => [] },
});

const editingId = ref(null);
const showForm = ref(false);

const form = useForm({
    name: '',
    base_url: '',
    driver: 'token_style_a',
    priority: 100,
    is_active: true,
    credentials: { key: '', client_id: '', client_secret: '', token_url: '' },
});

const isOauth = computed(() => form.driver === 'oauth');

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (v) => {
    editingId.value = v.id;
    form.name = v.name;
    form.base_url = v.base_url;
    form.driver = v.driver;
    form.priority = v.priority;
    form.is_active = v.is_active;
    form.credentials = { key: '', client_id: '', client_secret: '', token_url: '' };
    form.clearErrors();
    showForm.value = true;
};

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    if (editingId.value) {
        form.put(route('admin.vendors.update', editingId.value), opts);
    } else {
        form.post(route('admin.vendors.store'), opts);
    }
};

const toggle = (v) => router.patch(route('admin.vendors.toggle', v.id), {}, { preserveScroll: true });

const destroy = (v) => {
    if (confirm(`Delete vendor "${v.name}"? Its mappings and routes will be removed.`)) {
        router.delete(route('admin.vendors.destroy', v.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Vendors" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Vendors</h1>
                <button @click="openCreate" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">+ New vendor</button>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Driver</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Routes / Plans</th>
                            <th class="px-4 py-3">Active</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                        <tr v-for="v in vendors" :key="v.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ v.name }}</p>
                                <p class="text-xs text-gray-400">{{ v.base_url }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ v.driver }}</td>
                            <td class="px-4 py-3">{{ v.priority }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ v.routes_count }} / {{ v.plan_mappings_count }}</td>
                            <td class="px-4 py-3">
                                <button @click="toggle(v)" class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="v.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700'">
                                    {{ v.is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button @click="openEdit(v)" class="mr-3 text-blue-600 hover:underline">Edit</button>
                                <button @click="destroy(v)" class="text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!vendors.length"><td colspan="6" class="px-4 py-8 text-center text-gray-500">No vendors yet.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Form panel -->
            <div v-if="showForm" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" @click.self="showForm = false">
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ editingId ? 'Edit' : 'New' }} vendor</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-300">Name</label>
                            <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-300">Base URL</label>
                            <input v-model="form.base_url" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            <p v-if="form.errors.base_url" class="text-xs text-red-500">{{ form.errors.base_url }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm text-gray-600 dark:text-gray-300">Driver</label>
                                <select v-model="form.driver" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                    <option v-for="d in drivers" :key="d.value" :value="d.value">{{ d.value }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm text-gray-600 dark:text-gray-300">Priority</label>
                                <input v-model.number="form.priority" type="number" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            </div>
                        </div>

                        <!-- credentials -->
                        <div v-if="!isOauth">
                            <label class="text-sm text-gray-600 dark:text-gray-300">API key <span v-if="editingId" class="text-xs text-gray-400">(leave blank to keep)</span></label>
                            <input v-model="form.credentials.key" type="password" autocomplete="off" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                        <template v-else>
                            <input v-model="form.credentials.client_id" placeholder="client_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            <input v-model="form.credentials.client_secret" type="password" autocomplete="off" placeholder="client_secret (blank = keep)" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            <input v-model="form.credentials.token_url" placeholder="token_url" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                        </template>

                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600" /> Active
                        </label>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="showForm = false" class="rounded-lg px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Cancel</button>
                        <button @click="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
