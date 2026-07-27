<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    price: Number,
    zones: Array,
});

const step = ref(1);
const totalSteps = 3;
const stepLabels = ['Account', 'Personal', 'Location'];
const clientErrors = ref({});

const form = useForm({
    agentLocation: '',
    agentBvn: '',
    bankName: '',
    accountNumber: '',
    accountName: '',
    firstName: '',
    lastName: '',
    email: '',
    phoneNumber: '',
    address: '',
    stateOfResidence: '',
    dateOfBirth: '',
    lga: '',
    zone: '',
});

const zoneLabel = (z) => z.split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');

// Lightweight per-step validation mirroring the source zod schema.
const validateStep = (s) => {
    const e = {};
    if (s === 1) {
        if (form.agentLocation.trim().length < 3) e.agentLocation = 'Agent location is required';
        if (!/^\d{11}$/.test(form.agentBvn)) e.agentBvn = 'BVN must be 11 digits';
        if (form.bankName.trim().length < 2) e.bankName = 'Bank name is required';
        if (!/^\d{10}$/.test(form.accountNumber)) e.accountNumber = 'Account number must be 10 digits';
        if (form.accountName.trim().length < 3) e.accountName = 'Account name is required';
    } else if (s === 2) {
        if (form.firstName.trim().length < 2) e.firstName = 'First name is required';
        if (form.lastName.trim().length < 2) e.lastName = 'Last name is required';
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email)) e.email = 'Invalid email address';
        if (!/^\d{11,}$/.test(form.phoneNumber)) e.phoneNumber = 'Phone number must be at least 11 digits';
        if (form.address.trim().length < 5) e.address = 'Address is required';
    } else if (s === 3) {
        if (form.stateOfResidence.trim().length < 2) e.stateOfResidence = 'State is required';
        if (form.lga.trim().length < 2) e.lga = 'LGA is required';
        if (!form.dateOfBirth) e.dateOfBirth = 'Date of birth is required';
        if (!form.zone) e.zone = 'Zone is required';
    }
    clientErrors.value = e;
    return Object.keys(e).length === 0;
};

const next = () => { if (validateStep(step.value)) step.value = Math.min(step.value + 1, totalSteps); };
const prev = () => { step.value = Math.max(step.value - 1, 1); };

const submit = () => {
    if (!validateStep(3)) return;
    form.post(route('bvn-sdk-form.store'), { preserveScroll: true });
};

const err = (field) => clientErrors.value[field] || form.errors[field];

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined) return 'N/A';
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(Number(amount));
};

const progress = computed(() => ((step.value - 1) / (totalSteps - 1)) * 100);
</script>

<template>
    <Head title="BVN SDK Onboarding" />
    <AuthenticatedLayout>
        <div class="max-w-3xl mx-auto space-y-6">
            <!-- Wallet + price -->
            <div class="bg-gradient-to-r from-violet-600 to-indigo-600 rounded-xl shadow p-6 text-white flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-80">Wallet Balance</p>
                    <p class="text-3xl font-bold mt-1">₦{{ wallet.total_balance.toLocaleString() }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm opacity-80">Onboarding Fee</p>
                    <p class="text-2xl font-bold mt-1">{{ formatCurrency(price) }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">BVN SDK Onboarding</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Complete your registration by providing your BVN and account details.</p>
                    </div>
                    <Link :href="route('bvn-sdk-form.submissions')" class="text-sm font-medium text-violet-600 dark:text-violet-400 hover:underline whitespace-nowrap">
                        My Submissions →
                    </Link>
                </div>

                <div v-if="form.errors.message" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ form.errors.message }}</div>

                <!-- Stepper -->
                <div class="mb-8">
                    <div class="flex justify-between mb-2">
                        <div v-for="(label, i) in stepLabels" :key="label" class="flex flex-col items-center flex-1">
                            <div :class="['w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium',
                                step > i + 1 ? 'bg-green-600 text-white' : step === i + 1 ? 'bg-violet-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300']">
                                <svg v-if="step > i + 1" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span v-else>{{ i + 1 }}</span>
                            </div>
                            <span class="text-xs mt-1 text-gray-600 dark:text-gray-400">{{ label }}</span>
                        </div>
                    </div>
                    <div class="relative w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full">
                        <div class="absolute top-0 left-0 h-full bg-violet-600 rounded-full transition-all duration-300" :style="{ width: progress + '%' }"></div>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Step 1: Account -->
                    <div v-show="step === 1" class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Account Information</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Agent Location</label>
                            <input v-model="form.agentLocation" type="text" placeholder="Enter agent location" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            <p v-if="err('agentLocation')" class="mt-1 text-xs text-red-600">{{ err('agentLocation') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Agent BVN</label>
                            <input v-model="form.agentBvn" type="text" maxlength="11" placeholder="Enter 11-digit BVN" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 font-mono" />
                            <p v-if="err('agentBvn')" class="mt-1 text-xs text-red-600">{{ err('agentBvn') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank Name</label>
                            <input v-model="form.bankName" type="text" placeholder="Enter bank name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            <p v-if="err('bankName')" class="mt-1 text-xs text-red-600">{{ err('bankName') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Number</label>
                            <input v-model="form.accountNumber" type="text" maxlength="10" placeholder="Enter 10-digit account number" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 font-mono" />
                            <p v-if="err('accountNumber')" class="mt-1 text-xs text-red-600">{{ err('accountNumber') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account Name</label>
                            <input v-model="form.accountName" type="text" placeholder="Enter account name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            <p v-if="err('accountName')" class="mt-1 text-xs text-red-600">{{ err('accountName') }}</p>
                        </div>
                    </div>

                    <!-- Step 2: Personal -->
                    <div v-show="step === 2" class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                                <input v-model="form.firstName" type="text" placeholder="Enter first name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                                <p v-if="err('firstName')" class="mt-1 text-xs text-red-600">{{ err('firstName') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                                <input v-model="form.lastName" type="text" placeholder="Enter last name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                                <p v-if="err('lastName')" class="mt-1 text-xs text-red-600">{{ err('lastName') }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input v-model="form.email" type="email" placeholder="Enter email address" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            <p v-if="err('email')" class="mt-1 text-xs text-red-600">{{ err('email') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                            <input v-model="form.phoneNumber" type="text" placeholder="Enter phone number" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 font-mono" />
                            <p v-if="err('phoneNumber')" class="mt-1 text-xs text-red-600">{{ err('phoneNumber') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                            <input v-model="form.address" type="text" placeholder="Enter your address" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            <p v-if="err('address')" class="mt-1 text-xs text-red-600">{{ err('address') }}</p>
                        </div>
                    </div>

                    <!-- Step 3: Location -->
                    <div v-show="step === 3" class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Location Information</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State of Residence</label>
                            <input v-model="form.stateOfResidence" type="text" placeholder="Enter your state" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            <p v-if="err('stateOfResidence')" class="mt-1 text-xs text-red-600">{{ err('stateOfResidence') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">LGA</label>
                            <input v-model="form.lga" type="text" placeholder="Enter your LGA" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            <p v-if="err('lga')" class="mt-1 text-xs text-red-600">{{ err('lga') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date of Birth</label>
                            <input v-model="form.dateOfBirth" type="date" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            <p v-if="err('dateOfBirth')" class="mt-1 text-xs text-red-600">{{ err('dateOfBirth') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Zone</label>
                            <select v-model="form.zone" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2">
                                <option value="">Select zone</option>
                                <option v-for="z in zones" :key="z" :value="z">{{ zoneLabel(z) }}</option>
                            </select>
                            <p v-if="err('zone')" class="mt-1 text-xs text-red-600">{{ err('zone') }}</p>
                        </div>
                    </div>

                    <!-- Nav buttons -->
                    <div class="flex justify-between pt-4">
                        <button v-if="step > 1" type="button" @click="prev" class="px-5 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300">Previous</button>
                        <span v-else></span>

                        <button v-if="step < totalSteps" type="button" @click="next" class="px-5 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm font-medium">Next</button>
                        <button v-else type="submit" :disabled="form.processing" class="flex items-center gap-2 px-5 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm font-medium disabled:opacity-50">
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ form.processing ? 'Submitting...' : `Submit (${formatCurrency(price)})` }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
