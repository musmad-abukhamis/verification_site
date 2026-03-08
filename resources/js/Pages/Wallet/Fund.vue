<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    wallet: Object,
    paystack_key: String,
});

const form = useForm({
    amount: '',
});

const isProcessing = ref(false);

const fundWithPaystack = () => {
    isProcessing.value = true;
    
    // Initialize Paystack payment
    const handler = PaystackPop.setup({
        key: props.paystack_key,
        email: '', // Will be filled from auth
        amount: parseFloat(form.amount) * 100, // Convert to kobo
        currency: 'NGN',
        ref: 'WALLET_' + Date.now(),
        callback: function(response) {
            // Verify payment on server
            verifyPayment(response.reference);
        },
        onClose: function() {
            isProcessing.value = false;
        },
    });
    
    handler.openIframe();
};

const verifyPayment = (reference) => {
    // This would call your backend to verify the payment
    // and credit the user's wallet
    form.post(route('wallet.fund.verify'), {
        reference: reference,
        onFinish: () => {
            isProcessing.value = false;
        },
    });
};

const presetAmounts = [500, 1000, 2000, 5000, 10000];
</script>

<template>
    <Head title="Fund Wallet" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fund Wallet</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Current Balance -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                        <h3 class="text-lg font-medium mb-2">Current Balance</h3>
                        <p class="text-3xl font-bold">₦{{ wallet.total_balance.toLocaleString() }}</p>
                    </div>
                </div>

                <!-- Fund Wallet Form -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Add Funds to Wallet</h3>

                        <form @submit.prevent="fundWithPaystack">
                            <!-- Amount -->
                            <div class="mb-6">
                                <InputLabel for="amount" value="Amount (₦)" />
                                
                                <!-- Preset Amounts -->
                                <div class="grid grid-cols-3 md:grid-cols-5 gap-3 mb-4">
                                    <button
                                        v-for="preset in presetAmounts"
                                        :key="preset"
                                        type="button"
                                        @click="form.amount = preset"
                                        :class="[
                                            'py-2 px-4 rounded-lg border-2 text-center transition-all',
                                            form.amount == preset
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]"
                                    >
                                        ₦{{ preset.toLocaleString() }}
                                    </button>
                                </div>

                                <TextInput
                                    id="amount"
                                    type="number"
                                    class="mt-1 block w-full"
                                    v-model="form.amount"
                                    placeholder="Enter amount"
                                    min="100"
                                />
                                <p class="mt-1 text-sm text-gray-500">Minimum: ₦100</p>
                                <InputError class="mt-2" :message="form.errors.amount" />
                            </div>

                            <!-- Payment Methods -->
                            <div class="mb-6">
                                <InputLabel value="Payment Method" />
                                <div class="mt-2 p-4 border-2 border-indigo-500 bg-indigo-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold">
                                            P
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-medium text-gray-900">Paystack</p>
                                            <p class="text-sm text-gray-500">Pay with card, bank transfer, or USSD</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <PrimaryButton
                                :class="{ 'opacity-25': isProcessing || !form.amount || form.amount < 100 }"
                                :disabled="isProcessing || !form.amount || form.amount < 100"
                                class="w-full justify-center"
                            >
                                <span v-if="isProcessing">Processing...</span>
                                <span v-else>Proceed to Payment</span>
                            </PrimaryButton>
                        </form>

                        <!-- Security Note -->
                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                                <p class="ml-3 text-sm text-gray-600">
                                    Your payment is secured by Paystack. We do not store your card details.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
