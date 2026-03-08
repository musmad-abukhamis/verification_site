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
    price: Number,
});

const page = usePage();
const showResult = ref(false);
const verificationResult = ref(null);

const form = useForm({
    bvn_number: '',
});

const submit = () => {
    form.post(route('verification.bvn.verify'), {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.verification_data) {
                verificationResult.value = page.props.flash.verification_data;
                showResult.value = true;
                form.reset();
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
    <Head title="BVN Verification" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">BVN Verification</h2>
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

                <!-- BVN Verification Form -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Verify BVN</h3>
                                <p class="text-sm text-gray-600">Enter your 11-digit Bank Verification Number</p>
                            </div>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="mb-4">
                                <InputLabel for="bvn_number" value="BVN Number" />
                                <TextInput
                                    id="bvn_number"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.bvn_number"
                                    placeholder="12345678901"
                                    maxlength="11"
                                />
                                <InputError class="mt-2" :message="form.errors.bvn_number" />
                            </div>

                            <div class="flex items-center justify-between">
                                <p v-if="price > wallet.total_balance" class="text-sm text-red-600">
                                    Insufficient balance. <a :href="route('wallet.fund')" class="underline">Fund wallet</a>
                                </p>
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing || form.bvn_number.length !== 11 }"
                                    :disabled="form.processing || form.bvn_number.length !== 11"
                                >
                                    Verify BVN (₦{{ price }})
                                </PrimaryButton>
                            </div>
                        </form>

                        <!-- Links -->
                        <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between">
                            <a :href="route('verification.nin')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                ← Verify NIN instead
                            </a>
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
                            <span class="text-green-800 font-medium">BVN Verified</span>
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
