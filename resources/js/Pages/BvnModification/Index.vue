<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    prices: Object,
});

const activeTab = ref('old');

const form = useForm({
    serviceType: '',
    bvn: '',
    nin: '',
    ninSlip: null,
    oldFirstName: '',
    oldMiddleName: '',
    oldLastName: '',
    oldDob: '',
    oldPhoneNumber: '',
    newFirstName: '',
    newMiddleName: '',
    newLastName: '',
    newDob: '',
    newPhoneNumber: '',
});

// serviceType → bvnserviceprices column
const priceColumnMap = {
    'modify-name': 'name_mod',
    'modify-dob': 'dob_mod',
    'modify-name-dob': 'namedob_mod',
    'modify-phone': 'phone_mod',
    'modify-name-dob-phone': 'namephonedob_mod',
};

const serviceOptions = [
    { value: 'modify-name', label: 'Name Modification', column: 'name_mod', description: 'Update your name information' },
    { value: 'modify-dob', label: 'DOB Modification', column: 'dob_mod', description: 'Correct your date of birth' },
    { value: 'modify-phone', label: 'Phone Number Modification', column: 'phone_mod', description: 'Update your phone number' },
    { value: 'modify-name-dob', label: 'Name & DOB Modification', column: 'namedob_mod', description: 'Update both name and date of birth' },
    { value: 'modify-name-dob-phone', label: 'Complete Profile Modification', column: 'namephonedob_mod', description: 'Update name, date of birth, and phone number' },
];

const needsName = computed(() => ['modify-name', 'modify-name-dob', 'modify-name-dob-phone'].includes(form.serviceType));
const needsDob = computed(() => ['modify-dob', 'modify-name-dob', 'modify-name-dob-phone'].includes(form.serviceType));
const needsPhone = computed(() => ['modify-phone', 'modify-name-dob-phone'].includes(form.serviceType));

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || amount === '' || amount === '0' || Number(amount) === 0) return 'Contact Support';
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(Number(amount));
};

const selectedPrice = computed(() => {
    const col = priceColumnMap[form.serviceType];
    return col ? props.prices?.[col] : null;
});

const isAvailable = (amount) => amount && amount !== '0' && amount !== '' && Number(amount) > 0;

const onFile = (e) => {
    form.ninSlip = e.target.files?.[0] || null;
};

const submit = () => {
    form.post(route('bvn-modification.store'), {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="BVN Modification" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Wallet -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl shadow p-6 text-white">
                <p class="text-sm opacity-80">Wallet Balance</p>
                <p class="text-3xl font-bold mt-1">₦{{ wallet.total_balance.toLocaleString() }}</p>
                <div class="flex gap-4 mt-2 text-sm opacity-80">
                    <span>Main: ₦{{ wallet.balance.toLocaleString() }}</span>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">BVN Modification Request</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Select a service type and fill in the required information.</p>
                    </div>
                    <Link :href="route('bvn-modification.requests')" class="text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:underline whitespace-nowrap">
                        My Requests →
                    </Link>
                </div>

                <div v-if="form.errors.message" class="my-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ form.errors.message }}</div>
                <div v-if="$page.props.flash?.success" class="my-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

                <form @submit.prevent="submit" class="space-y-6 mt-4">
                    <!-- Service Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Service Type</label>
                        <select v-model="form.serviceType"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-emerald-500">
                            <option value="">Select service type</option>
                            <option v-for="opt in serviceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <p v-if="form.errors.serviceType" class="mt-1 text-xs text-red-600">{{ form.errors.serviceType }}</p>
                    </div>

                    <!-- Pricing -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Service Pricing</label>
                        </div>
                        <div class="space-y-2">
                            <div v-for="opt in serviceOptions" :key="opt.value"
                                @click="form.serviceType = opt.value"
                                :class="['p-3 border rounded-lg cursor-pointer transition-all flex items-center justify-between',
                                    form.serviceType === opt.value ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30 shadow-sm' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300']">
                                <div>
                                    <p class="font-medium text-sm text-gray-900 dark:text-gray-100">{{ opt.label }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ opt.description }}</p>
                                </div>
                                <span :class="['text-xs font-medium px-2.5 py-1 rounded-full',
                                    isAvailable(prices?.[opt.column]) ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' : 'bg-yellow-100 text-yellow-800']">
                                    {{ formatCurrency(prices?.[opt.column]) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BVN / NIN -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">BVN</label>
                            <input v-model="form.bvn" type="text" placeholder="Enter your BVN"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 font-mono focus:ring-2 focus:ring-emerald-500" />
                            <p v-if="form.errors.bvn" class="mt-1 text-xs text-red-600">{{ form.errors.bvn }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">NIN</label>
                            <input v-model="form.nin" type="text" placeholder="Enter your NIN"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 font-mono focus:ring-2 focus:ring-emerald-500" />
                            <p v-if="form.errors.nin" class="mt-1 text-xs text-red-600">{{ form.errors.nin }}</p>
                        </div>
                    </div>

                    <!-- NIN Slip -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Attach NIN Slip</label>
                        <input type="file" accept="image/jpeg,image/png,application/pdf" @change="onFile"
                            class="w-full text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" />
                        <p class="mt-1 text-xs text-gray-500">Upload your NIN slip (JPEG, PNG, or PDF — max 5MB)</p>
                        <p v-if="form.errors.ninSlip" class="mt-1 text-xs text-red-600">{{ form.errors.ninSlip }}</p>
                    </div>

                    <!-- Old / New record tabs -->
                    <div v-if="form.serviceType">
                        <div class="grid grid-cols-2 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-4">
                            <button type="button" @click="activeTab = 'old'"
                                :class="['py-2 text-sm font-semibold', activeTab === 'old' ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300']">Old Record</button>
                            <button type="button" @click="activeTab = 'new'"
                                :class="['py-2 text-sm font-semibold', activeTab === 'new' ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300']">New Record</button>
                        </div>

                        <!-- OLD -->
                        <div v-show="activeTab === 'old'" class="space-y-4">
                            <div v-if="needsName" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                                    <input v-model="form.oldFirstName" type="text" placeholder="Old first name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Middle Name</label>
                                    <input v-model="form.oldMiddleName" type="text" placeholder="Old middle name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                                    <input v-model="form.oldLastName" type="text" placeholder="Old last name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                                </div>
                            </div>
                            <div v-if="needsDob">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date of Birth</label>
                                <input v-model="form.oldDob" type="date" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            </div>
                            <div v-if="needsPhone">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                                <input v-model="form.oldPhoneNumber" type="text" placeholder="Old phone number" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            </div>
                        </div>

                        <!-- NEW -->
                        <div v-show="activeTab === 'new'" class="space-y-4">
                            <div v-if="needsName" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                                    <input v-model="form.newFirstName" type="text" placeholder="New first name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Middle Name</label>
                                    <input v-model="form.newMiddleName" type="text" placeholder="New middle name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                                    <input v-model="form.newLastName" type="text" placeholder="New last name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                                </div>
                            </div>
                            <div v-if="needsDob">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date of Birth</label>
                                <input v-model="form.newDob" type="date" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            </div>
                            <div v-if="needsPhone">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                                <input v-model="form.newPhoneNumber" type="text" placeholder="New phone number" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2" />
                            </div>
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing || !form.serviceType"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span v-if="form.processing">Submitting...</span>
                        <span v-else>Submit Request<template v-if="form.serviceType"> — {{ formatCurrency(selectedPrice) }}</template></span>
                    </button>

                    <p v-if="form.serviceType" class="text-center text-xs text-gray-500 dark:text-gray-400">
                        By submitting this request, you agree to the service fee which will be deducted from your wallet balance.
                    </p>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
