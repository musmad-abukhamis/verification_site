<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    wallet: Object,
    networks: Array,
    data_plans: Object,
});

const page = usePage();

const form = useForm({
    network: '',
    phone_number: '',
    plan_id: '',
});

const detectedNetwork = ref(null);
const selectedPlan = ref(null);

const availablePlans = computed(() => {
    if (!form.network) return [];
    return props.data_plans[form.network] || [];
});

const canSubmit = computed(() => {
    return form.network && 
           form.phone_number.length >= 10 && 
           form.plan_id &&
           selectedPlan.value &&
           parseFloat(selectedPlan.value.price) <= props.wallet.total_balance;
});

watch(() => form.plan_id, (planId) => {
    selectedPlan.value = availablePlans.value.find(p => p.id === parseInt(planId)) || null;
});

const submit = () => {
    form.post(route('vtu.data.purchase'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Buy Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Buy Data Bundle</h2>
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

                <!-- Data Form -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-6">Purchase Data Bundle</h3>

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
                                        @click="form.network = network.value; form.plan_id = ''"
                                        :class="[
                                            'p-4 rounded-lg border-2 text-center transition-all',
                                            form.network === network.value
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
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
                                    placeholder="08012345678"
                                    maxlength="11"
                                />
                                <InputError class="mt-2" :message="form.errors.phone_number" />
                            </div>

                            <!-- Data Plan Selection -->
                            <div class="mb-6" v-if="form.network">
                                <InputLabel for="plan_id" value="Select Data Plan" />
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                                    <label
                                        v-for="plan in availablePlans"
                                        :key="plan.id"
                                        :class="[
                                            'p-4 rounded-lg border-2 cursor-pointer transition-all',
                                            form.plan_id == plan.id
                                                ? 'border-indigo-500 bg-indigo-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            :value="plan.id"
                                            v-model="form.plan_id"
                                            class="hidden"
                                        />
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-semibold text-gray-900">{{ plan.name }}</p>
                                                <p class="text-sm text-gray-500">{{ plan.data_volume }}</p>
                                                <p class="text-xs text-gray-400">{{ plan.validity_days }} days validity</p>
                                            </div>
                                            <p class="font-bold text-indigo-600">₦{{ parseFloat(plan.price).toLocaleString() }}</p>
                                        </div>
                                    </label>
                                </div>
                                <p v-if="availablePlans.length === 0" class="mt-2 text-gray-500">
                                    No data plans available for this network.
                                </p>
                                <InputError class="mt-2" :message="form.errors.plan_id" />
                            </div>

                            <!-- Selected Plan Summary -->
                            <div v-if="selectedPlan" class="mb-6 p-4 bg-gray-50 rounded-lg">
                                <h4 class="font-medium text-gray-900 mb-2">Order Summary</h4>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Plan:</span>
                                    <span class="font-medium">{{ selectedPlan.name }}</span>
                                </div>
                                <div class="flex justify-between text-sm mt-1">
                                    <span class="text-gray-600">Amount:</span>
                                    <span class="font-medium">₦{{ parseFloat(selectedPlan.price).toLocaleString() }}</span>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600">
                                    <p v-if="selectedPlan && parseFloat(selectedPlan.price) > wallet.total_balance" class="text-red-600">
                                        Insufficient balance. <a :href="route('wallet.fund')" class="underline">Fund wallet</a>
                                    </p>
                                </div>
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing || !canSubmit }"
                                    :disabled="form.processing || !canSubmit"
                                >
                                    Purchase Data
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
