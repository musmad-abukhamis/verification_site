<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    pricing: Object,
    verificationMethods: Object,
});

const pricingForm = useForm({
    nin_price: props.pricing.nin_price,
    bvn_price: props.pricing.bvn_price,
});

const methodsForm = useForm({
    methods: props.verificationMethods,
});

const updatePricing = () => {
    pricingForm.post(route('admin.settings.pricing'));
};

const updateMethods = () => {
    methodsForm.post(route('admin.settings.verification-methods'));
};
</script>

<template>
    <Head title="Settings" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Settings</h1>
            </div>

            <!-- Pricing Settings -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Pricing</h2>
                <form @submit.prevent="updatePricing" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                NIN Verification Price (₦)
                            </label>
                            <input
                                v-model="pricingForm.nin_price"
                                type="number"
                                min="0"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                BVN Verification Price (₦)
                            </label>
                            <input
                                v-model="pricingForm.bvn_price"
                                type="number"
                                min="0"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="pricingForm.processing"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Update Pricing
                        </button>
                    </div>
                </form>
            </div>

            <!-- Verification Methods Settings -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Verification Methods</h2>
                <form @submit.prevent="updateMethods" class="space-y-4">
                    <div class="space-y-4">
                        <div v-for="(config, method) in methodsForm.methods" :key="method" class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <div>
                                <h3 class="font-medium text-gray-900 dark:text-white">{{ config.label }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Status: {{ config.active ? 'Active' : 'Inactive' }}
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="methodsForm.methods[method].active"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="methodsForm.processing"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Update Methods
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
