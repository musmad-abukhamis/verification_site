<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    // { [groupName]: [{ column, label, value }] }
    groups: Object,
});

// Seed the form with every column's current value (blank when null).
const initial = {};
Object.values(props.groups).forEach((fields) => {
    fields.forEach((f) => {
        initial[f.column] = f.value ?? '';
    });
});

const form = useForm(initial);

const submit = () => {
    form.put(route('admin.bvn-prices.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="BVN Service Prices" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">BVN Service Prices</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Set the pricing for all BVN services. Enter amounts in Naira without the currency symbol. Leave blank to disable a service.</p>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

            <form @submit.prevent="submit" class="space-y-6">
                <div v-for="(fields, groupName) in groups" :key="groupName" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ groupName }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="f in fields" :key="f.column">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ f.label }}</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">₦</span>
                                <input
                                    v-model="form[f.column]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0"
                                    class="w-full pl-7 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <p v-if="form.errors[f.column]" class="mt-1 text-xs text-red-600">{{ form.errors[f.column] }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Saved.</span>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Prices' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
