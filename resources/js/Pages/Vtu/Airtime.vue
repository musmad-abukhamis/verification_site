<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    networks: Array,
});

const page = usePage();

const form = useForm({
    network: '',
    phone_number: '',
    amount: '',
});

const detectedNetwork = ref(null);
const isVerifying = ref(false);

const networkColors = {
    mtn: 'bg-yellow-500',
    glo: 'bg-green-500',
    airtel: 'bg-red-500',
    '9mobile': 'bg-teal-500',
};

const canSubmit = computed(() => {
    return form.network && 
           form.phone_number.length >= 10 && 
           form.amount >= 50 &&
           parseFloat(form.amount) <= props.wallet.total_balance;
});

const verifyPhoneNumber = async () => {
    if (form.phone_number.length < 10) return;
    
    isVerifying.value = true;
    try {
        const response = await fetch('/vtu/verify-phone', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.props.csrf_token,
            },
            body: JSON.stringify({ phone_number: form.phone_number }),
        });
        const data = await response.json();
        detectedNetwork.value = data.valid ? data.network : null;
    } catch (error) {
        console.error('Error verifying phone:', error);
    } finally {
        isVerifying.value = false;
    }
};

const submit = () => {
    form.post(route('vtu.airtime.purchase'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Buy Airtime" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Buy Airtime</h2>
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

                <!-- Airtime Form -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Purchase Airtime</h3>

                        <div v-if="page.props.flash?.success" class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
                            {{ page.props.flash.success }}
                        </div>

                        <form @submit.prevent="submit">
                            <!-- Network Selection -->
                            <div class="mb-4">
                                <InputLabel for="network" value="Select Network" />
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-2">
                                    <button
                                        v-for="network in networks"
                                        :key="network.value"
                                        type="button"
                                        @click="form.network = network.value"
                                        :class="[
                                            'p-4 rounded-lg border-2 text-center transition-all',
                                            form.network === network.value
                                                ? `border-${network.color}-500 ${networkColors[network.value]} text-white`
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]"
                                    >
                                        <span class="font-semibold">{{ network.label }}</span>
                                    </button>
                                </div>
                                <InputError class="mt-2" :message="form.errors.network" />
                            </div>

                            <!-- Phone Number -->
                            <div class="mb-4">
                                <InputLabel for="phone_number" value="Phone Number" />
                                <TextInput
                                    id="phone_number"
                                    type="tel"
                                    class="mt-1 block w-full"
                                    v-model="form.phone_number"
                                    @blur="verifyPhoneNumber"
                                    placeholder="08012345678"
                                    maxlength="11"
                                />
                                <div v-if="detectedNetwork && !form.network" class="mt-2 text-sm text-blue-600">
                                    Detected: {{ detectedNetwork.toUpperCase() }}
                                </div>
                                <InputError class="mt-2" :message="form.errors.phone_number" />
                            </div>

                            <!-- Amount -->
                            <div class="mb-6">
                                <InputLabel for="amount" value="Amount (₦)" />
                                <TextInput
                                    id="amount"
                                    type="number"
                                    class="mt-1 block w-full"
                                    v-model="form.amount"
                                    placeholder="100"
                                    min="50"
                                    max="50000"
                                />
                                <p class="mt-1 text-sm text-gray-500">Min: ₦50 | Max: ₦50,000</p>
                                <InputError class="mt-2" :message="form.errors.amount" />
                                <InputError class="mt-2" :message="form.errors.error" />
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600">
                                    <p v-if="parseFloat(form.amount) > wallet.total_balance" class="text-red-600">
                                        Insufficient balance. <a :href="route('wallet.fund')" class="underline">Fund wallet</a>
                                    </p>
                                </div>
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing || !canSubmit }"
                                    :disabled="form.processing || !canSubmit"
                                >
                                    Purchase Airtime
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
