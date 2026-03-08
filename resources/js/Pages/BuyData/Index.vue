<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Swal from 'sweetalert2';

// Props
const props = defineProps({
    wallet: Object,
    networks: Array,
    user: Object,
});

// Form
const form = useForm({
    network: '',
    type: '',
    planId: null,
    planName: '',
    planPrice: 0,
    phoneNumber: '',
});

// State
const loading = ref(false);
const phoneValidation = ref({ valid: null, network: null, message: '' });
const dataTypes = ref([]);
const dataPlans = ref([]);
const selectedPlan = ref(null);

// Network prefix mapping for phone validation
const networkPrefixes = {
    mtn: ['0703', '0706', '0803', '0806', '0810', '0813', '0814', '0816', '0903', '0906', '0913', '0916', '07025', '07026', '0704', '0707'],
    airtel: ['0701', '0708', '0802', '0808', '0812', '0901', '0902', '0904', '0907', '0912', '0911'],
    glo: ['0705', '0805', '0807', '0811', '0815', '0905', '0915'],
    '9mobile': ['0809', '0817', '0818', '0908', '0909']
};

// Computed
const isFormValid = computed(() => {
    return form.network && form.type && form.planId && form.phoneNumber && phoneValidation.value.valid;
});

const getNetworkColor = (network) => {
    const colors = {
        mtn: 'text-yellow-500',
        airtel: 'text-red-500',
        glo: 'text-green-500',
        '9mobile': 'text-teal-500'
    };
    return colors[network.toLowerCase()] || 'text-gray-500';
};

const getNetworkIcon = (network) => {
    const icons = {
        mtn: 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
        airtel: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
        glo: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
        '9mobile': 'M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'
    };
    return icons[network.toLowerCase()] || 'M12 18h.01';
};

// Watchers
watch(() => form.network, async (newNetwork) => {
    if (newNetwork) {
        form.type = '';
        form.planId = null;
        form.planName = '';
        form.planPrice = 0;
        dataTypes.value = [];
        dataPlans.value = [];
        selectedPlan.value = null;
        await fetchDataTypes(newNetwork);
    }
});

watch(() => form.type, async (newType) => {
    if (newType && form.network) {
        form.planId = null;
        form.planName = '';
        form.planPrice = 0;
        dataPlans.value = [];
        selectedPlan.value = null;
        await fetchDataPlans(form.network, newType);
    }
});

watch(() => form.planId, (newPlanId) => {
    if (newPlanId) {
        const plan = dataPlans.value.find(p => p.id === newPlanId);
        if (plan) {
            selectedPlan.value = plan;
            form.planName = plan.name;
            form.planPrice = getPlanPrice(plan);
        }
    }
});

// Methods
const fetchDataTypes = async (network) => {
    try {
        const response = await fetch(`/api/plans/${network}`);
        const data = await response.json();
        dataTypes.value = data.types || [];
    } catch (error) {
        console.error('Error fetching data types:', error);
        Swal.fire('Error', 'Failed to fetch data types', 'error');
    }
};

const fetchDataPlans = async (network, type) => {
    try {
        const response = await fetch(`/api/plans/${network}/${type}`);
        const data = await response.json();
        dataPlans.value = data.plans || [];
    } catch (error) {
        console.error('Error fetching data plans:', error);
        Swal.fire('Error', 'Failed to fetch data plans', 'error');
    }
};

const validatePhoneNumber = () => {
    if (!form.phoneNumber) {
        phoneValidation.value = { valid: null, network: null, message: '' };
        return;
    }

    const phone = form.phoneNumber.replace(/\D/g, '');
    if (phone.length < 10) {
        phoneValidation.value = { valid: false, network: null, message: 'Phone number too short' };
        return;
    }

    const prefix = phone.substring(0, 4);
    const prefix5 = phone.substring(0, 5);
    
    for (const [network, prefixes] of Object.entries(networkPrefixes)) {
        if (prefixes.includes(prefix) || prefixes.includes(prefix5)) {
            phoneValidation.value = {
                valid: true,
                network: network,
                message: `Valid ${network.toUpperCase()} number`
            };
            return;
        }
    }
    
    phoneValidation.value = {
        valid: false,
        network: null,
        message: 'This number does not match any Nigerian network'
    };
};

const resetForm = () => {
    form.reset();
    phoneValidation.value = { valid: null, network: null, message: '' };
    dataTypes.value = [];
    dataPlans.value = [];
    selectedPlan.value = null;
};

const getPlanPrice = (plan) => {
    const user = props.user;
    if (user.role === 'AGENT') {
        return plan.agent_price || plan.price;
    } else if (user.role === 'API') {
        return plan.api_price || plan.price;
    }
    return plan.price;
};

const submitForm = async () => {
    if (!isFormValid.value) return;

    const result = await Swal.fire({
        title: 'Confirm Purchase',
        html: `
            <div class="text-left">
                <p><strong>Network:</strong> ${form.network.toUpperCase()}</p>
                <p><strong>Plan:</strong> ${form.planName}</p>
                <p><strong>Phone:</strong> ${form.phoneNumber}</p>
                <p class="text-xl font-bold mt-2">Total: ₦${form.planPrice.toLocaleString()}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirm Purchase',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        loading.value = true;
        form.post(route('buy-data.purchase'), {
            onSuccess: () => {
                loading.value = false;
                Swal.fire('Success', 'Data purchase successful!', 'success');
                resetForm();
            },
            onError: (errors) => {
                loading.value = false;
                Swal.fire('Error', errors.error || 'Purchase failed', 'error');
            }
        });
    }
};
</script>

<template>
    <Head title="Buy Data" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-950">
            <div class="py-12">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <!-- Header -->
                    <div class="text-center mb-12">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            Mobile Data Purchase
                        </h1>
                        <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                            Get instant data for any network at the best prices. Select your preferred network, choose a plan, and stay connected without interruptions.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Left Side - Form -->
                        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg overflow-hidden">
                            <!-- Card Header -->
                            <div class="bg-gradient-to-r from-lime-600 to-lime-700 dark:from-slate-700 dark:to-slate-600 p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-xl font-bold text-white flex items-center">
                                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                            </svg>
                                            Buy Data Bundle
                                        </h2>
                                        <p class="text-lime-100 dark:text-slate-200 mt-1">
                                            Fill the form below to purchase data
                                        </p>
                                    </div>
                                    <button 
                                        @click="resetForm"
                                        type="button"
                                        class="px-4 py-2 border border-white/30 bg-transparent text-white rounded-lg hover:bg-white/10 transition-colors"
                                    >
                                        Reset Form
                                    </button>
                                </div>
                            </div>

                            <!-- Form -->
                            <div class="p-6">
                                <div class="space-y-6">
                                    <!-- Network Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Select Network
                                        </label>
                                        <select
                                            v-model="form.network"
                                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-lime-500 focus:border-lime-500"
                                        >
                                            <option value="">Choose a network</option>
                                            <option 
                                                v-for="network in networks" 
                                                :key="network.value" 
                                                :value="network.value"
                                                class="flex items-center"
                                            >
                                                {{ network.label }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.network" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.network }}
                                        </div>
                                    </div>

                                    <!-- Data Type Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Data Type
                                        </label>
                                        <select
                                            v-model="form.type"
                                            :disabled="!form.network"
                                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-lime-500 focus:border-lime-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <option value="">Select data type</option>
                                            <option 
                                                v-for="type in dataTypes" 
                                                :key="type"
                                                :value="type"
                                            >
                                                {{ type }} {{ type === 'off' ? '(Unavailable)' : '' }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.type" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.type }}
                                        </div>
                                    </div>

                                    <!-- Data Plan Selection -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Data Plan
                                        </label>
                                        <select
                                            v-model="form.planId"
                                            :disabled="!form.type"
                                            class="w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-lime-500 focus:border-lime-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <option value="">Select a plan</option>
                                            <option 
                                                v-for="plan in dataPlans" 
                                                :key="plan.id"
                                                :value="plan.id"
                                            >
                                                {{ plan.name }} - ₦{{ getPlanPrice(plan).toLocaleString() }} ({{ plan.validity_days }} days)
                                            </option>
                                        </select>
                                        <div v-if="form.errors.planId" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.planId }}
                                        </div>
                                    </div>

                                    <!-- Phone Number -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Phone Number
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <input
                                                v-model="form.phoneNumber"
                                                @input="validatePhoneNumber"
                                                type="tel"
                                                placeholder="08063545466"
                                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-lime-500 focus:border-lime-500"
                                            />
                                        </div>
                                        
                                        <!-- Validation Feedback -->
                                        <div 
                                            v-if="phoneValidation.valid !== null" 
                                            :class="[
                                                'mt-2 p-3 rounded-lg flex items-start',
                                                phoneValidation.valid 
                                                    ? 'bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800' 
                                                    : 'bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800'
                                            ]"
                                        >
                                            <svg 
                                                :class="[
                                                    'h-5 w-5 mr-2 mt-0.5 flex-shrink-0',
                                                    phoneValidation.valid ? 'text-green-500' : 'text-amber-500'
                                                ]"
                                                fill="none" 
                                                stroke="currentColor" 
                                                viewBox="0 0 24 24"
                                            >
                                                <path 
                                                    v-if="phoneValidation.valid" 
                                                    stroke-linecap="round" 
                                                    stroke-linejoin="round" 
                                                    stroke-width="2" 
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" 
                                                />
                                                <path 
                                                    v-else 
                                                    stroke-linecap="round" 
                                                    stroke-linejoin="round" 
                                                    stroke-width="2" 
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" 
                                                />
                                            </svg>
                                            <div>
                                                <p :class="[
                                                    'font-medium',
                                                    phoneValidation.valid ? 'text-green-800 dark:text-green-200' : 'text-amber-800 dark:text-amber-200'
                                                ]">
                                                    {{ phoneValidation.message }}
                                                </p>
                                                <p v-if="phoneValidation.valid" class="text-sm text-green-600 dark:text-green-400 mt-1">
                                                    This number is compatible with {{ phoneValidation.network?.toUpperCase() }}
                                                </p>
                                                <p v-else class="text-sm text-amber-600 dark:text-amber-400 mt-1">
                                                    You can still proceed, but please verify the number is correct
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div v-if="form.errors.phoneNumber" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {{ form.errors.phoneNumber }}
                                        </div>
                                    </div>

                                    <!-- Submit Button -->
                                    <button
                                        @click="submitForm"
                                        :disabled="!isFormValid || loading"
                                        :class="[
                                            'w-full py-3 px-4 rounded-lg font-medium transition-all',
                                            isFormValid && !loading
                                                ? 'bg-gradient-to-r from-lime-600 to-lime-700 text-white hover:from-lime-700 hover:to-lime-800 shadow-lg hover:shadow-xl'
                                                : 'bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-gray-400 cursor-not-allowed'
                                        ]"
                                    >
                                        <span v-if="loading" class="flex items-center justify-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Processing...
                                        </span>
                                        <span v-else>Buy Data</span>
                                    </button>

                                    <!-- Info Text -->
                                    <div class="flex items-start p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                        <svg class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <p class="text-sm text-blue-800 dark:text-blue-200">
                                            Data will be delivered instantly after successful payment. For any issues, please contact customer support.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side - Image & Benefits -->
                        <div class="space-y-8">
                            <!-- Hero Image -->
                            <div class="relative rounded-2xl overflow-hidden shadow-xl">
                                <img 
                                    src="https://placehold.co/600x256?text=Data+Plans&font=montserrat" 
                                    alt="Stay Connected" 
                                    class="w-full h-64 object-cover"
                                >
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
                                    <h3 class="text-2xl font-bold mb-2">Stay Connected Anywhere</h3>
                                    <p class="text-lg opacity-90">Instant data delivery for all networks</p>
                                </div>
                            </div>

                            <!-- Benefits Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <!-- Instant Delivery -->
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-lg border border-gray-100 dark:border-slate-700">
                                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Instant Delivery</h4>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        Data is delivered to your line immediately after purchase
                                    </p>
                                </div>

                                <!-- All Networks -->
                                <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-lg border border-gray-100 dark:border-slate-700">
                                    <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">All Networks</h4>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        Support for MTN, Airtel, Glo and 9mobile networks
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>