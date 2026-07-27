<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    plan: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    mappings: { type: Object, default: () => ({}) },
});

const form = useForm({
    code: props.plan?.code ?? '',
    network: props.plan?.network || 'mtn',
    type: props.plan?.type || 'SME',
    name: props.plan?.name || '',
    price: props.plan?.price ?? 0,
    agent_price: props.plan?.agent_price ?? 0,
    api_price: props.plan?.api_price ?? 0,
    validity: props.plan?.validity || '',
    status: props.plan?.status || 'on',
    plan_status: props.plan?.plan_status || 'on',
    mappings: props.vendors.map((v) => ({
        vendor_id: v.id,
        name: v.name,
        external_plan_id: props.mappings[v.id] ?? '',
    })),
});

const submit = () => {
    if (props.plan) {
        form.put(route('admin.dataplan.update', props.plan.id));
    } else {
        form.post(route('admin.dataplan.store'));
    }
};
</script>

<template>
    <Head :title="plan ? 'Edit Plan' : 'New Plan'" />
    <AdminLayout>
        <div class="mx-auto max-w-2xl space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ plan ? 'Edit' : 'New' }} plan</h1>
                <Link :href="route('admin.dataplan.index')" class="text-sm text-gray-500 hover:underline">← Back</Link>
            </div>

            <div class="space-y-4 rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Plan ID (public)</label>
                        <input v-model="form.code" type="number" min="1" max="999" placeholder="auto"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                        <p class="mt-1 text-xs text-gray-400">
                            The id developers send as <code>plan_id</code>. Leave blank to allocate the next free number.
                            Changing it breaks any integration already using the old one.
                        </p>
                        <p v-if="form.errors.code" class="mt-1 text-xs text-red-500">{{ form.errors.code }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Network</label>
                        <select v-model="form.network" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            <option value="mtn">MTN</option><option value="airtel">AIRTEL</option><option value="glo">GLO</option><option value="9mobile">9MOBILE</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Type</label>
                        <input v-model="form.type" placeholder="SME / DATASHARE / GIFTING" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                        <p v-if="form.errors.type" class="text-xs text-red-500">{{ form.errors.type }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Name</label>
                        <input v-model="form.name" placeholder="1GB" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                        <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Validity</label>
                        <input v-model="form.validity" placeholder="30 Days" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">User ₦</label>
                        <input v-model.number="form.price" type="number" step="0.01" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Agent ₦</label>
                        <input v-model.number="form.agent_price" type="number" step="0.01" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">API ₦</label>
                        <input v-model.number="form.api_price" type="number" step="0.01" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Type availability</label>
                        <select v-model="form.status" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"><option value="on">On</option><option value="off">Off (Unavailable)</option></select>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Plan visibility</label>
                        <select v-model="form.plan_status" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"><option value="on">Visible</option><option value="off">Hidden</option></select>
                    </div>
                </div>
            </div>

            <div class="space-y-3 rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Per-vendor plan codes</h2>
                <p class="text-xs text-gray-400">Enter the vendor's external plan id. Leave blank if this plan isn't offered by that vendor.</p>
                <div v-for="(m, i) in form.mappings" :key="m.vendor_id" class="flex items-center gap-3">
                    <span class="w-40 shrink-0 text-sm text-gray-600 dark:text-gray-300">{{ m.name }}</span>
                    <input v-model="form.mappings[i].external_plan_id" placeholder="external plan id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                </div>
                <p v-if="!form.mappings.length" class="text-sm text-gray-400">No vendors configured yet.</p>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="route('admin.dataplan.index')" class="rounded-lg px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Cancel</Link>
                <button @click="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">Save plan</button>
            </div>
        </div>
    </AdminLayout>
</template>
