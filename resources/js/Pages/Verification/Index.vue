<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    wallet: Object,
    prices: Object,
});

const page = usePage();
const activeTab = ref('nin');
const showResult = ref(false);
const verificationResult = ref(null);

const ninForm = useForm({
    nin_number: '',
});

const bvnForm = useForm({
    bvn_number: '',
});

const submitNin = () => {
    ninForm.post(route('verification.nin'), {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.verification_data) {
                verificationResult.value = page.props.flash.verification_data;
                showResult.value = true;
                ninForm.reset();
            }
        },
    });
};

const submitBvn = () => {
    bvnForm.post(route('verification.bvn'), {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.verification_data) {
                verificationResult.value = page.props.flash.verification_data;
                showResult.value = true;
                bvnForm.reset();
            }
        },
    });
};

const closeResult = () => {
    showResult.value = false;
    verificationResult.value = null;
};
</script>

<template>
    <Head title="Identity Verification" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Identity Verification</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Wallet Balance Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                        <h3 class="text-lg font-medium mb-2">Wallet Balance</h3>
                        <p class="text-3xl font-bold">₦{{ wallet.total_balance.toLocaleString() }}</p>
                        <div class="mt-2 text-sm opacity-90">
                            <span>Main: ₦{{ wallet.balance.toLocaleString() }}</span>
                            <span class="ml-4">Bonus: ₦{{ wallet.bonus_balance.toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Verification Tabs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button
                                @click="activeTab = 'nin'"
                                :class="[
                                    'py-4 px-6 border-b-2 font-medium text-sm',
                                    activeTab === 'nin'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                NIN Verification
                                <span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">₦{{ prices.nin }}</span>
                            </button>
                            <button
                                @click="activeTab = 'bvn'"
                                :class="[
                                    'py-4 px-6 border-b-2 font-medium text-sm',
                                    activeTab === 'bvn'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                BVN Verification
                                <span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">₦{{ prices.bvn }}</span>
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <!-- NIN Form -->
                        <div v-if="activeTab === 'nin'">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Verify NIN</h3>
                            <p class="text-sm text-gray-600 mb-6">Enter the 11-digit National Identity Number to verify.</p>

                            <form @submit.prevent="submitNin">
                                <div class="mb-4">
                                    <InputLabel for="nin_number" value="NIN Number" />
                                    <TextInput
                                        id="nin_number"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="ninForm.nin_number"
                                        placeholder="12345678901"
                                        maxlength="11"
                                    />
                                    <InputError class="mt-2" :message="ninForm.errors.nin_number" />
                                </div>

                                <div class="flex items-center justify-between">
                                    <p v-if="prices.nin > wallet.total_balance" class="text-sm text-red-600">
                                        Insufficient balance. <a :href="route('wallet.fund')" class="underline">Fund wallet</a>
                                    </p>
                                    <PrimaryButton
                                        :class="{ 'opacity-25': ninForm.processing || ninForm.nin_number.length !== 11 }"
                                        :disabled="ninForm.processing || ninForm.nin_number.length !== 11"
                                    >
                                        Verify NIN (₦{{ prices.nin }})
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>

                        <!-- BVN Form -->
                        <div v-if="activeTab === 'bvn'">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Verify BVN</h3>
                            <p class="text-sm text-gray-600 mb-6">Enter the 11-digit Bank Verification Number to verify.</p>

                            <form @submit.prevent="submitBvn">
                                <div class="mb-4">
                                    <InputLabel for="bvn_number" value="BVN Number" />
                                    <TextInput
                                        id="bvn_number"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="bvnForm.bvn_number"
                                        placeholder="12345678901"
                                        maxlength="11"
                                    />
                                    <InputError class="mt-2" :message="bvnForm.errors.bvn_number" />
                                </div>

                                <div class="flex items-center justify-between">
                                    <p v-if="prices.bvn > wallet.total_balance" class="text-sm text-red-600">
                                        Insufficient balance. <a :href="route('wallet.fund')" class="underline">Fund wallet</a>
                                    </p>
                                    <PrimaryButton
                                        :class="{ 'opacity-25': bvnForm.processing || bvnForm.bvn_number.length !== 11 }"
                                        :disabled="bvnForm.processing || bvnForm.bvn_number.length !== 11"
                                    >
                                        Verify BVN (₦{{ prices.bvn }})
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>

                        <!-- View History Link -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <a :href="route('verification.history')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                View Verification History →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Result Modal -->
        <div v-if="showResult" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Verification Successful</h3>
                        <button @click="closeResult" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-green-800 font-medium">Identity Verified</span>
                        </div>
                    </div>

                    <div v-if="verificationResult" class="space-y-3">
                        <div v-for="(value, key) in verificationResult" :key="key" class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600 capitalize">{{ key.replace('_', ' ') }}:</span>
                            <span class="font-medium text-gray-900">{{ value || 'N/A' }}</span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button
                            @click="closeResult"
                            class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
