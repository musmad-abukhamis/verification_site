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
    price: Number,
    verificationMethods: {
        type: Object,
        default: () => ({
            nin: { active: true, label: 'By NIN Number' },
            phone: { active: true, label: 'By Phone Number' },
            demographic: { active: true, label: 'By Demographics' },
        }),
    },
});

const page = usePage();
const showResult = ref(false);
const verificationResult = ref(null);

const verificationTypes = [
    { value: 'nin', label: props.verificationMethods.nin?.label || 'By NIN Number', icon: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2', active: props.verificationMethods.nin?.active ?? true },
    { value: 'phone', label: props.verificationMethods.phone?.label || 'By Phone Number', icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', active: props.verificationMethods.phone?.active ?? true },
    { value: 'demographic', label: props.verificationMethods.demographic?.label || 'By Demographics', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', active: props.verificationMethods.demographic?.active ?? true },
];

const form = useForm({
    verification_type: 'nin',
    nin_number: '',
    phone_number: '',
    last_name: '',
    first_name: '',
    gender: '',
    date_of_birth: '',
});

const canSubmit = computed(() => {
    if (form.verification_type === 'nin') {
        return form.nin_number.length === 11;
    }
    if (form.verification_type === 'phone') {
        return form.phone_number.length >= 10;
    }
    if (form.verification_type === 'demographic') {
        return form.last_name && form.first_name && form.gender && form.date_of_birth;
    }
    return false;
});

const submit = () => {
    form.post(route('verification.nin.verify'), {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.verification_data) {
                verificationResult.value = page.props.flash.verification_data;
                showResult.value = true;
                form.reset();
                form.verification_type = 'nin';
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
    <Head title="NIN Verification" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">NIN Verification</h2>
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

                <!-- NIN Verification Form -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Verify NIN</h3>
                                <p class="text-sm text-gray-600">Enter your 11-digit National Identity Number</p>
                            </div>
                        </div>

                        <form @submit.prevent="submit">
                            <!-- Verification Type Selection -->
                            <div class="mb-6">
                                <InputLabel value="Verification Method" />
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-2">
                                    <label
                                        v-for="type in verificationTypes"
                                        :key="type.value"
                                        :class="[
                                            'relative flex flex-col items-center p-4 rounded-lg border-2 cursor-pointer transition-all',
                                            !type.active && 'opacity-60 cursor-not-allowed',
                                            form.verification_type === type.value && type.active
                                                ? 'border-purple-500 bg-purple-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]"
                                    >
                                        <!-- Active/Inactive Badge -->
                                        <span
                                            :class="[
                                                'absolute top-2 right-2 px-2 py-0.5 text-xs font-medium rounded-full',
                                                type.active
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-red-100 text-red-700'
                                            ]"
                                        >
                                            {{ type.active ? 'Active' : 'Inactive' }}
                                        </span>

                                        <input
                                            type="radio"
                                            :value="type.value"
                                            v-model="form.verification_type"
                                            class="hidden"
                                            :disabled="!type.active"
                                        />
                                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mb-2">
                                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="type.icon" />
                                            </svg>
                                        </div>
                                        <span class="font-medium text-gray-900 text-center text-sm">{{ type.label }}</span>
                                    </label>
                                </div>
                                <InputError class="mt-2" :message="form.errors.verification_type" />
                            </div>

                            <!-- NIN Number Field -->
                            <div v-if="form.verification_type === 'nin'" class="mb-4">
                                <InputLabel for="nin_number" value="NIN Number" />
                                <TextInput
                                    id="nin_number"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.nin_number"
                                    placeholder="Enter 11-digit NIN"
                                    maxlength="11"
                                />
                                <p class="mt-1 text-sm text-gray-500">Enter your 11-digit National Identity Number</p>
                                <InputError class="mt-2" :message="form.errors.nin_number" />
                            </div>

                            <!-- Phone Number Field -->
                            <div v-if="form.verification_type === 'phone'" class="mb-4">
                                <InputLabel for="phone_number" value="Phone Number" />
                                <TextInput
                                    id="phone_number"
                                    type="tel"
                                    class="mt-1 block w-full"
                                    v-model="form.phone_number"
                                    placeholder="08012345678"
                                    maxlength="11"
                                />
                                <p class="mt-1 text-sm text-gray-500">Enter the phone number linked to your NIN</p>
                                <InputError class="mt-2" :message="form.errors.phone_number" />
                            </div>

                            <!-- Demographics Fields -->
                            <div v-if="form.verification_type === 'demographic'" class="space-y-4 mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="last_name" value="Last Name" />
                                        <TextInput
                                            id="last_name"
                                            type="text"
                                            class="mt-1 block w-full"
                                            v-model="form.last_name"
                                            placeholder="Enter last name"
                                        />
                                        <InputError class="mt-2" :message="form.errors.last_name" />
                                    </div>
                                    <div>
                                        <InputLabel for="first_name" value="First Name" />
                                        <TextInput
                                            id="first_name"
                                            type="text"
                                            class="mt-1 block w-full"
                                            v-model="form.first_name"
                                            placeholder="Enter first name"
                                        />
                                        <InputError class="mt-2" :message="form.errors.first_name" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="gender" value="Gender" />
                                        <select
                                            id="gender"
                                            v-model="form.gender"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                        >
                                            <option value="">Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.gender" />
                                    </div>
                                    <div>
                                        <InputLabel for="date_of_birth" value="Date of Birth" />
                                        <TextInput
                                            id="date_of_birth"
                                            type="date"
                                            class="mt-1 block w-full"
                                            v-model="form.date_of_birth"
                                        />
                                        <InputError class="mt-2" :message="form.errors.date_of_birth" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <p v-if="price > wallet.total_balance" class="text-sm text-red-600">
                                    Insufficient balance. <a :href="route('wallet.fund')" class="underline">Fund wallet</a>
                                </p>
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing || !canSubmit }"
                                    :disabled="form.processing || !canSubmit"
                                >
                                    Verify NIN (₦{{ price }})
                                </PrimaryButton>
                            </div>
                        </form>

                        <!-- Links -->
                        <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between">
                            <a :href="route('verification.bvn')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                Verify BVN instead →
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
                            <span class="text-green-800 font-medium">NIN Verified</span>
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
