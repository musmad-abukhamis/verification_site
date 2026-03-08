<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    plan: Object,
});

const form = useForm({
    network: props.plan.network,
    name: props.plan.name,
    price: props.plan.price,
    agentPrice: props.plan.agentPrice,
    apiPrice: props.plan.apiPrice,
    type: props.plan.type,
    validity: props.plan.validity,
    status: props.plan.status,
    planStatus: props.plan.planStatus,
    apiKey: props.plan.apiKey,
    vendorPlan1: props.plan.vendorPlan1,
    vendorPlan2: props.plan.vendorPlan2,
    vendorPlan3: props.plan.vendorPlan3,
    vendorPlan4: props.plan.vendorPlan4,
    vendorPlan5: props.plan.vendorPlan5,
});

const networks = ['mtn', 'glo', 'airtel', '9mobile'];
const types = ['sme', 'direct', 'corporate'];
const statuses = ['on', 'off'];

const submit = () => {
    form.put(route('admin.dataplan.update', props.plan.id), {
        onSuccess: () => {
            // Form submitted successfully
        },
    });
};
</script>

<template>
    <Head title="Edit Data Plan" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Edit Data Plan
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <!-- Network -->
                                <div>
                                    <label for="network" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Network
                                    </label>
                                    <select
                                        id="network"
                                        v-model="form.network"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    >
                                        <option value="">Select Network</option>
                                        <option v-for="network in networks" :key="network" :value="network">
                                            {{ network.toUpperCase() }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.network" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.network }}
                                    </div>
                                </div>

                                <!-- Type -->
                                <div>
                                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Type
                                    </label>
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    >
                                        <option value="">Select Type</option>
                                        <option v-for="type in types" :key="type" :value="type">
                                            {{ type.toUpperCase() }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.type }}
                                    </div>
                                </div>

                                <!-- Name -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Plan Name
                                    </label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    />
                                    <div v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.name }}
                                    </div>
                                </div>

                                <!-- Validity -->
                                <div>
                                    <label for="validity" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Validity
                                    </label>
                                    <input
                                        id="validity"
                                        v-model="form.validity"
                                        type="text"
                                        placeholder="e.g., 30 days"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    />
                                    <div v-if="form.errors.validity" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.validity }}
                                    </div>
                                </div>

                                <!-- Price -->
                                <div>
                                    <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Price (₦)
                                    </label>
                                    <input
                                        id="price"
                                        v-model="form.price"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    />
                                    <div v-if="form.errors.price" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.price }}
                                    </div>
                                </div>

                                <!-- Agent Price -->
                                <div>
                                    <label for="agentPrice" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Agent Price (₦)
                                    </label>
                                    <input
                                        id="agentPrice"
                                        v-model="form.agentPrice"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    />
                                    <div v-if="form.errors.agentPrice" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.agentPrice }}
                                    </div>
                                </div>

                                <!-- API Price -->
                                <div>
                                    <label for="apiPrice" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        API Price (₦)
                                    </label>
                                    <input
                                        id="apiPrice"
                                        v-model="form.apiPrice"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    />
                                    <div v-if="form.errors.apiPrice" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.apiPrice }}
                                    </div>
                                </div>

                                <!-- Status -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Status
                                    </label>
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    >
                                        <option v-for="status in statuses" :key="status" :value="status">
                                            {{ status.toUpperCase() }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.status" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.status }}
                                    </div>
                                </div>

                                <!-- Plan Status -->
                                <div>
                                    <label for="planStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Plan Status
                                    </label>
                                    <select
                                        id="planStatus"
                                        v-model="form.planStatus"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        required
                                    >
                                        <option v-for="status in statuses" :key="status" :value="status">
                                            {{ status.toUpperCase() }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.planStatus" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.planStatus }}
                                    </div>
                                </div>

                                <!-- API Key -->
                                <div>
                                    <label for="apiKey" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        API Key (Optional)
                                    </label>
                                    <input
                                        id="apiKey"
                                        v-model="form.apiKey"
                                        type="number"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                    />
                                    <div v-if="form.errors.apiKey" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                        {{ form.errors.apiKey }}
                                    </div>
                                </div>
                            </div>

                            <!-- Vendor Plans Section -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Vendor Plan Mappings
                                </h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                    <div v-for="n in 5" :key="n">
                                        <label :for="`vendorPlan${n}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Vendor {{ n }}
                                        </label>
                                        <input
                                            :id="`vendorPlan${n}`"
                                            v-model="form[`vendorPlan${n}`]"
                                            type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex items-center justify-end space-x-4">
                                <button
                                    type="button"
                                    @click="window.history.back()"
                                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    :class="[
                                        'inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150',
                                        form.processing ? 'opacity-75 cursor-not-allowed' : ''
                                    ]"
                                >
                                    <span v-if="form.processing" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Updating...
                                    </span>
                                    <span v-else>Update Plan</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>