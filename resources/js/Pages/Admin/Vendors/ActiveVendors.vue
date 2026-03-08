<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    activeVendors: Array,
    networks: Array,
    types: Array,
    vendors: Array,
});

const form = useForm({
    configurations: props.activeVendors.map(vendor => ({
        network: vendor.network,
        type: vendor.type,
        vendor_number: vendor.vendor_number
    }))
});

const getVendorName = (vendorNumber) => {
    const vendor = props.vendors.find(v => v.id === vendorNumber);
    return vendor ? vendor.name : `Vendor ${vendorNumber}`;
};

const submit = () => {
    form.post(route('admin.vendors.active.update'), {
        onSuccess: () => {
            // Success handling
        },
    });
};
</script>

<template>
    <Head title="Active Vendors Configuration" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Active Vendors Configuration
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Configure Active Vendors
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Select which vendor should handle each network and data type combination.
                            </p>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Network
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Type
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Active Vendor
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="(config, index) in form.configurations" :key="`${config.network}-${config.type}`">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ config.network.toUpperCase() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ config.type.toUpperCase() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <select
                                                    v-model="form.configurations[index].vendor_number"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                >
                                                    <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">
                                                        {{ vendor.name }}
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex items-center justify-end">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    :class="[
                                        'inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white focus:outline-none focus:ring-2 focus:ring-offset-2',
                                        form.processing 
                                            ? 'bg-gray-400 cursor-not-allowed' 
                                            : 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500'
                                    ]"
                                >
                                    <span v-if="form.processing" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Saving...
                                    </span>
                                    <span v-else>Save Configuration</span>
                                </button>
                            </div>
                        </form>

                        <!-- Current Configuration Summary -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">
                                Current Configuration Summary
                            </h4>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div 
                                    v-for="config in form.configurations" 
                                    :key="`${config.network}-${config.type}`"
                                    class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4"
                                >
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ config.network.toUpperCase() }} - {{ config.type.toUpperCase() }}
                                    </div>
                                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ getVendorName(config.vendor_number) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>