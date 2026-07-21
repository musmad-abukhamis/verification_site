<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    services: Array,
    roles: Array,
});

const editing = ref(null);

const form = useForm({
    price: 0,
    is_active: true,
    overrides: {},
});

const groups = computed(() => [
    { key: 'verification', title: 'Verification Fees', blurb: 'Charged when a lookup runs. Slips are billed separately.' },
    { key: 'slip', title: 'Slip Downloads', blurb: 'Charged when a slip is generated, on top of the verification fee.' },
]);

const inGroup = (key) => props.services.filter((s) => s.group === key);

const startEdit = (service) => {
    editing.value = service.service;
    form.clearErrors();
    form.price = service.price;
    form.is_active = service.is_active;
    // Blank means "no override" — that role pays the base price.
    form.overrides = Object.fromEntries(
        props.roles.map((role) => [role, service.overrides[role] ?? '']),
    );
};

const cancel = () => {
    editing.value = null;
    form.reset();
};

const save = (service) => {
    form.transform((data) => ({
        ...data,
        overrides: Object.fromEntries(
            Object.entries(data.overrides).map(([role, value]) => [
                role,
                value === '' || value === null ? null : Number(value),
            ]),
        ),
    })).put(route('admin.service-prices.update', service.service), {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; form.reset(); },
    });
};

const money = (value) => `₦${Number(value).toLocaleString()}`;

const overrideList = (service) =>
    props.roles
        .filter((role) => service.overrides[role] !== null)
        .map((role) => ({ role, price: service.overrides[role] }));
</script>

<template>
    <Head title="Service Prices" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Service Prices</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Each service has one base price. Add a price against a role to charge that role differently —
                    leave it blank and they pay the base price.
                </p>
            </div>

            <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg">
                {{ $page.props.flash.success }}
            </div>

            <div v-for="group in groups" :key="group.key" class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ group.title }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ group.blurb }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Base Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Role Prices</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-for="service in inGroup(group.key)" :key="service.service">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ service.label }}</div>
                                        <code class="text-xs text-gray-400">{{ service.service }}</code>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div v-if="editing === service.service" class="flex items-center gap-1">
                                            <span class="text-sm text-gray-500">₦</span>
                                            <input
                                                type="number" v-model="form.price" step="0.01" min="0"
                                                class="w-28 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                                            />
                                        </div>
                                        <span v-else class="text-sm font-medium text-gray-900 dark:text-white">{{ money(service.price) }}</span>
                                        <p v-if="editing === service.service && form.errors.price" class="text-xs text-red-500 mt-1">{{ form.errors.price }}</p>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div v-if="editing === service.service" class="flex flex-wrap gap-3">
                                            <label v-for="role in roles" :key="role" class="flex flex-col gap-1">
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ role }}</span>
                                                <input
                                                    type="number" v-model="form.overrides[role]" step="0.01" min="0"
                                                    placeholder="Base"
                                                    class="w-24 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                                                />
                                            </label>
                                        </div>
                                        <div v-else class="flex flex-wrap gap-1">
                                            <span
                                                v-for="entry in overrideList(service)" :key="entry.role"
                                                class="px-2 py-1 text-xs rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200"
                                            >
                                                {{ entry.role }} {{ money(entry.price) }}
                                            </span>
                                            <span v-if="!overrideList(service).length" class="text-xs text-gray-400">Everyone pays base</span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <label v-if="editing === service.service" class="flex items-center gap-2">
                                            <input type="checkbox" v-model="form.is_active" class="rounded" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                        </label>
                                        <span
                                            v-else
                                            :class="['px-2 py-1 text-xs rounded-full font-medium', service.is_active
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200']"
                                        >
                                            {{ service.is_active ? 'Active' : 'Off' }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div v-if="editing === service.service" class="flex gap-2">
                                            <button @click="save(service)" :disabled="form.processing" class="text-green-600 hover:text-green-800 dark:text-green-400 text-sm font-medium disabled:opacity-50">Save</button>
                                            <button @click="cancel" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 text-sm font-medium">Cancel</button>
                                        </div>
                                        <button v-else @click="startEdit(service)" class="text-lime-600 hover:text-lime-800 dark:text-lime-400 text-sm font-medium">Edit</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Switching a service off makes it unavailable to everyone — role prices do not override that.
                The price is kept, so switching it back on restores it.
            </p>
        </div>
    </AdminLayout>
</template>
