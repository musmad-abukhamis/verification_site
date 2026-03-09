<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import StandardSlip from '@/Components/StandardSlip.vue';
import StandardSlipV2 from '@/Components/StandardSlipV2.vue';
import PremiumSlip from '@/Components/PremiumSlip.vue';
import axios from 'axios';

const props = defineProps({
    wallet: Object,
    verificationPrice: { type: Number, default: 50 },
    slipTypes: { type: Array, default: () => [] },
    transactions: Object,
    slipDownloads: { type: Array, default: () => [] },
});

const page = usePage();

// Form state
const provider = ref('v1');
const activeTab = ref('verify');
const verificationResult = ref(null);
const validationId = ref(null);
const showDetailsModal = ref(false);
const detailsRecord = ref(null);
const slipVersion = ref(null);
const downloadingSlip = ref(null);
const slipDownloadError = ref(null);
const slipTypesData = ref(props.slipTypes || []);

// Table state
const sortField = ref('created_at');
const sortDirection = ref('desc');

const form = useForm({
    idType: 'nin',
    idValue: '',
});

const canSubmit = computed(() => {
    return form.idValue.length >= 10 && !form.processing;
});

// Fetch slip types from API on mount and restore validation state
onMounted(async () => {
    // Restore validation state from flash data if available
    if (page.props.flash?.verification_data) {
        verificationResult.value = page.props.flash.verification_data;
        // Use validation_id from flash or from within verification_data
        validationId.value = page.props.flash?.validation_id || page.props.flash?.verification_data?.validation_id;
        activeTab.value = 'results';
    }
    
    if (slipTypesData.value.length === 0) {
        try {
            const response = await axios.get(route('nin.slip.types'));
            if (response.data.success) {
                slipTypesData.value = response.data.data;
            }
        } catch (e) {
            console.error('Failed to fetch slip types:', e);
        }
    }
});

const submit = () => {
    const routeName = provider.value === 'v1' ? 'nin.verify.v1' : 'nin.verify.v2';
    form.post(route(routeName), {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.verification_data) {
                verificationResult.value = page.props.flash.verification_data;
                // Use validation_id from flash or from within verification_data
                validationId.value = page.props.flash?.validation_id || page.props.flash?.verification_data?.validation_id;
                activeTab.value = 'results';
                form.reset('idValue');
            }
        },
    });
};

const backToVerify = () => {
    activeTab.value = 'verify';
    verificationResult.value = null;
    validationId.value = null;
    slipDownloadError.value = null;
};

const downloadSlip = async (slipCode) => {
    // Try to get validation_id from ref or from verification result
    const currentValidationId = validationId.value || verificationResult.value?.validation_id;
    
    if (!currentValidationId) {
        slipDownloadError.value = 'No verification record found. Please verify again.';
        return;
    }

    downloadingSlip.value = slipCode;
    slipDownloadError.value = null;

    try {
        console.log('Downloading slip:', { validation_id: currentValidationId, slip_type: slipCode });
        const response = await axios.post(route('nin.slip.download'), {
            validation_id: currentValidationId,
            slip_type: slipCode,
        });

        if (response.data.success) {
            // Set the slip version to show the preview
            slipVersion.value = slipCode;
            
            // Refresh wallet data
            router.reload({ only: ['wallet'] });
        } else {
            slipDownloadError.value = response.data.message || 'Failed to process slip download.';
        }
    } catch (error) {
        console.error('Slip download error:', error.response?.data || error);
        const msg = error.response?.data?.message || error.message || 'An error occurred.';
        slipDownloadError.value = msg;
    } finally {
        downloadingSlip.value = null;
    }
};

const handleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    router.get(route('nin.verify.index'), { sort: sortField.value, direction: sortDirection.value }, { preserveState: true, preserveScroll: true, only: ['transactions'] });
};

const goToPage = (url) => {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: false, only: ['transactions'] });
};

const openDetails = (tx) => {
    detailsRecord.value = tx;
    showDetailsModal.value = true;
};

const parsedResult = computed(() => {
    if (!detailsRecord.value?.result) return null;
    try { return JSON.parse(detailsRecord.value.result); } catch { return detailsRecord.value.result; }
});

const now = new Date();

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatNIN = (nin) => {
    if (!nin) return '-';
    const cleaned = String(nin).replace(/\D/g, '');
    return cleaned.length === 11 ? cleaned.replace(/(\d{4})(\d{4})(\d{3})/, '$1 $2 $3') : nin;
};

const qrValue = computed(() => {
    return "NIN:" + (verificationResult.value?.nin || verificationResult.value?.idValue) +" " + "Name:" + verificationResult.value?.surname + " " + verificationResult.value?.othernames +" " + "DOB:" + formatDob(verificationResult.value?.dob || verificationResult.value?.birthdate);
});

const formatDob = (dob) => {
    if (!dob) return '-';
    try {
        // Handle DD-MM-YYYY format (e.g., 13-10-1994)
        const parts = String(dob).split('-');
        if (parts.length === 3) {
            const [day, month, year] = parts;
            // Reconstruct as YYYY-MM-DD for proper parsing
            const isoDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
            const d = new Date(isoDate);
            if (!isNaN(d.getTime())) {
                return d.toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' });
            }
        }
        // Try direct parsing for ISO or other formats
        const d = new Date(dob);
        if (!isNaN(d.getTime())) {
            return d.toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' });
        }
        return dob;
    } catch { return dob; }
};

const getGender = (gender) => {
    if (!gender) return '-';
    const g = String(gender).toLowerCase();
    if (g === 'm' || g === 'Male' || g === 'male') return 'Male';
    if (g === 'f' || g === 'Female'  || g === 'female') return 'Female';
    return gender;
};

const getFullName = (result) => {
    if (!result) return '';
    const surname = result.surname || '';
    const firstname = result.firstname || '';
    const middlename = result.middlename || '';
    if (middlename) return `${surname} ${firstname} ${middlename}`;
    return `${surname} ${firstname}`.trim();
};

const getStatusClass = (status) => {
    const map = {
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        validated: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        processing: 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return map[status?.toLowerCase()] ?? 'bg-gray-100 text-gray-800';
};

const transactionsList = computed(() => props.transactions?.data || []);
const pagination = computed(() => ({
    from: props.transactions?.from || 0,
    to: props.transactions?.to || 0,
    total: props.transactions?.total || 0,
    links: props.transactions?.links || [],
    prev_page_url: props.transactions?.prev_page_url,
    next_page_url: props.transactions?.next_page_url,
}));
</script>

<template>
    <Head title="NIN Verification Portal" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Wallet Card -->
            <div class="bg-gradient-to-r from-lime-500 to-green-600 rounded-xl shadow-lg p-6 text-white overflow-hidden">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <p class="text-sm opacity-90">Wallet Balance</p>
                </div>
                <p class="text-3xl font-bold">₦{{ wallet.total_balance.toLocaleString() }}</p>
                <div class="flex gap-4 mt-2 text-sm opacity-80">
                    <span>Main: ₦{{ wallet.balance.toLocaleString() }}</span>
                    <span>Bonus: ₦{{ wallet.bonus_balance.toLocaleString() }}</span>
                </div>
            </div>

            <!-- Main Verification Card -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg overflow-hidden border border-lime-100 dark:border-gray-700">
                <!-- Header -->
                <div class="bg-lime-500 dark:bg-lime-700 px-6 py-4">
                    <div class="flex items-center gap-2 text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <h2 class="text-xl font-bold">NIN Verification Portal</h2>
                    </div>
                    <p class="text-white/80 text-sm mt-1">Secure and instant verification of Nigerian National ID</p>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-2">
                        <button
                            @click="activeTab = 'verify'; verificationResult = null"
                            :class="['py-3 text-center text-sm font-semibold transition-colors', activeTab === 'verify' ? 'bg-lime-50 dark:bg-lime-900/20 text-lime-700 dark:text-lime-400 border-b-2 border-lime-500' : 'bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600']"
                        >
                            Verify ID @ ₦{{ verificationPrice.toLocaleString() }}
                        </button>
                        <button
                            @click="verificationResult && (activeTab = 'results')"
                            :disabled="!verificationResult"
                            :class="['py-3 text-center text-sm font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed', activeTab === 'results' ? 'bg-lime-50 dark:bg-lime-900/20 text-lime-700 dark:text-lime-400 border-b-2 border-lime-500' : 'bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600']"
                        >
                            Results
                        </button>
                    </div>
                </div>

                <!-- Verify Tab Content -->
                <div v-show="activeTab === 'verify'" class="p-6">
                    <!-- Flash errors -->
                    <div v-if="$page.props.errors?.message" class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ $page.props.errors.message }}</p>
                        </div>
                    </div>
                    <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">
                        {{ $page.props.flash.success }}
                    </div>

                    <!-- Provider Tabs -->
                    <div class="flex gap-2 mb-6">
                        <button
                            v-for="v in ['v1', 'v2']" :key="v"
                            @click="provider = v"
                            :class="[
                                'px-5 py-2 rounded-lg text-sm font-semibold transition-colors',
                                provider === v
                                    ? 'bg-lime-600 text-white shadow'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ v === 'v1' ? 'V1 — Prembly' : 'V2 — ArewaSmart' }}
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5 max-w-lg mx-auto">
                        <!-- ID Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">ID Type</label>
                            <div class="flex gap-3">
                                <label v-for="t in [{value:'nin', label:'NIN Number'}, {value:'phone', label:'Phone Number'}]" :key="t.value"
                                    :class="['flex items-center gap-2 px-4 py-2 rounded-lg border-2 cursor-pointer transition-colors', form.idType === t.value ? 'border-lime-500 bg-lime-50 dark:bg-lime-900/20' : 'border-gray-200 dark:border-gray-600']">
                                    <input type="radio" :value="t.value" v-model="form.idType" class="hidden" />
                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ t.label }}</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ form.idType === 'nin' ? '11-digit National Identification Number' : 'Phone number linked to NIN' }}</p>
                        </div>

                        <!-- ID Value -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ form.idType === 'nin' ? 'NIN Number' : 'Phone Number' }}
                            </label>
                            <input
                                v-model="form.idValue"
                                type="text"
                                :maxlength="form.idType === 'nin' ? 11 : 11"
                                :placeholder="form.idType === 'nin' ? 'Enter 11-digit NIN' : 'e.g. 08012345678'"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-lime-500 focus:border-lime-500"
                            />
                            <p v-if="form.errors.idValue" class="mt-1 text-xs text-red-500">{{ form.errors.idValue }}</p>
                        </div>

                        <!-- Verification Price Info -->
                        <div class="bg-lime-50 dark:bg-lime-900/20 rounded-lg p-4 border border-lime-200 dark:border-lime-800">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-300">Verification Fee</span>
                                <span class="text-lg font-bold text-lime-600 dark:text-lime-400">₦{{ verificationPrice.toLocaleString() }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Slip download is charged separately after verification.</p>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="!canSubmit"
                            class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-lime-600 hover:bg-lime-700 text-white rounded-full font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <svg v-if="form.processing" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ form.processing ? 'Verifying...' : 'Verify ID' }}
                        </button>
                        <p class="text-center text-xs text-gray-500 dark:text-gray-400">Using <strong>{{ provider === 'v1' ? 'Prembly' : 'ArewaSmart' }}</strong> provider</p>
                    </form>
                </div>

                <!-- Results Tab Content -->
                <div v-if="activeTab === 'results' && verificationResult" class="p-6">
                    <!-- Photo & Name Section -->
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative mb-4">
                            <div class="absolute inset-0 bg-lime-500 rounded-full opacity-10 animate-pulse"></div>
                            <img
                                :src="verificationResult?.photo || '/default-avatar.png'"
                                alt="Person Photo"
                                class="w-28 h-28 rounded-full border-4 border-lime-100 dark:border-gray-600 shadow-md object-cover"
                            />
                            <div class="absolute bottom-0 right-0 bg-lime-500 text-white p-1.5 rounded-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">{{ getFullName(verificationResult) }}</h2>
                        <p class="text-lime-600 dark:text-lime-400 font-medium">Verified Successfully</p>
                    </div>

                    <!-- Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <!-- NIN -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm mb-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                <span>NIN</span>
                            </div>
                            <p class="font-medium text-gray-800 dark:text-white">{{ formatNIN(verificationResult?.nin || verificationResult?.idValue) }}</p>
                        </div>
                        <!-- Gender -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm mb-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>Gender</span>
                            </div>
                            <p class="font-medium text-gray-800 dark:text-white">{{ getGender(verificationResult?.gender) }}</p>
                        </div>
                        <!-- Date of Birth -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm mb-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Date of Birth</span>
                            </div>
                            <p class="font-medium text-gray-800 dark:text-white">{{ formatDob(verificationResult?.dob || verificationResult?.birthdate) }}</p>
                        </div>
                        <!-- Phone -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm mb-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <span>Phone</span>
                            </div>
                            <p class="font-medium text-gray-800 dark:text-white">{{ verificationResult?.phone || verificationResult?.telephoneno || '-' }}</p>
                        </div>
                        <!-- State -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm mb-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>State</span>
                            </div>
                            <p class="font-medium text-gray-800 dark:text-white">{{ verificationResult?.state || verificationResult?.residence_state || '-' }}</p>
                        </div>
                        <!-- LGA -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm mb-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <span>LGA</span>
                            </div>
                            <p class="font-medium text-gray-800 dark:text-white">{{ verificationResult?.lga || verificationResult?.residence_lga || '-' }}</p>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg mb-6">
                        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm mb-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <span>Address</span>
                        </div>
                        <p class="font-medium text-gray-800 dark:text-white">{{ verificationResult?.address || verificationResult?.residence_AdressLine1 || '-' }}</p>
                    </div>

                    <!-- Download NIN Slip -->
                    <div class="mb-6">
                        <!-- Slip Download Error -->
                        <div v-if="slipDownloadError" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">
                            {{ slipDownloadError }}
                        </div>

                        <!-- Slip Type Selection with Pricing -->
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Download NIN Slip</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <button
                                v-for="slip in slipTypesData"
                                :key="slip.code"
                                @click="downloadSlip(slip.code)"
                                :disabled="downloadingSlip === slip.code"
                                :class="[
                                    'relative flex flex-col items-center p-4 rounded-lg border-2 transition-colors',
                                    slipVersion === slip.code
                                        ? 'border-lime-500 bg-lime-50 dark:bg-lime-900/20'
                                        : 'border-gray-200 dark:border-gray-600 hover:border-lime-300 dark:hover:border-lime-700',
                                    downloadingSlip === slip.code ? 'opacity-75 cursor-wait' : 'cursor-pointer'
                                ]"
                            >
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ slip.name }}</span>
                                <span class="text-xs text-lime-600 dark:text-lime-400 font-medium mt-1">₦{{ slip.price.toLocaleString() }}</span>
                                <span v-if="downloadingSlip === slip.code" class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50 rounded-lg">
                                    <svg class="w-5 h-5 animate-spin text-lime-600" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </span>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Click a slip type to purchase and download. Each slip type is charged separately.</p>

                        <!-- Slip Preview (after purchase) -->
                        <div v-if="slipVersion">
                            <!-- v1 Slip (standard background) -->
                            <StandardSlip v-if="slipVersion === 'standard' || slipVersion === 'v1'"
                                :surname="verificationResult?.surname || ''"
                                :othernames="(verificationResult?.firstname || '') + ' ' + (verificationResult?.middlename || '')"
                                :dob="verificationResult?.dob || verificationResult?.birthdate || ''"
                                :gender="verificationResult?.gender || ''"
                                :nin="verificationResult?.nin || verificationResult?.idValue || ''"
                                :photo="verificationResult?.photo || '/default-avatar.png'"
                                :qr-value="verificationResult?.nin || verificationResult?.idValue || ''"
                                :tracking-id="verificationResult?.nin || ''"
                            />

                            <!-- v2 Slip (clean design, no background) -->
                            <StandardSlipV2 v-else-if="slipVersion === 'v2'"
                                :surname="verificationResult?.surname || ''"
                                :othernames="(verificationResult?.firstname || '') + ' ' + (verificationResult?.middlename || '')"
                                :dob="verificationResult?.dob || verificationResult?.birthdate || ''"
                                :gender="verificationResult?.gender || ''"
                                :nin="verificationResult?.nin || verificationResult?.idValue || ''"
                                :photo="verificationResult?.photo || '/default-avatar.png'"
                                :qr-value="verificationResult?.nin || verificationResult?.idValue || ''"
                            />

                            <!-- Premium Slip (premium background + QR logo) -->
                            <PremiumSlip v-else-if="slipVersion === 'premium'"
                                :surname="verificationResult?.surname || ''"
                                :othernames="(verificationResult?.firstname || '') + ' ' + (verificationResult?.middlename || '')"
                                :dob="formatDob(verificationResult?.dob || verificationResult?.birthdate) || ''"
                                :gender="getGender(verificationResult?.gender) || ''"
                                :nin="verificationResult?.nin || verificationResult?.idValue || ''"
                                :photo="verificationResult?.photo || '/default-avatar.png'"
                                :qr-value="qrValue || ''"
                                :tracking-id="verificationResult?.nin || ''"
                                :dateIssue="formatDate(now)"
                            />
                        </div>
                    </div>

                    <!-- Back Button -->
                    <button
                        @click="backToVerify"
                        class="w-full py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-full font-medium transition-colors flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        New Verification
                    </button>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transaction History</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th v-for="col in [{field:'id',label:'ID'},{field:'nin',label:'NIN/Phone'},{field:'status',label:'Status'},{field:null,label:'Comment'},{field:null,label:'Old Bal'},{field:null,label:'New Bal'},{field:'created_at',label:'Date'},{field:null,label:''}]"
                                    :key="col.label"
                                    @click="col.field && handleSort(col.field)"
                                    :class="['px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase', col.field ? 'cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600' : '']">
                                    {{ col.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="tx in transactionsList" :key="tx.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ tx.id }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ tx.nin }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium', getStatusClass(tx.status)]">{{ tx.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-[180px] truncate" :title="tx.comment">{{ tx.comment || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">₦{{ Number(tx.old_balance || 0).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">₦{{ Number(tx.new_balance || 0).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(tx.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <button @click="openDetails(tx)" class="text-xs text-lime-600 dark:text-lime-400 hover:underline">Details</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="transactionsList.length === 0" class="py-10 text-center text-gray-400">No transactions yet</div>
                </div>
                <!-- Pagination -->
                <div v-if="pagination.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(pagination.prev_page_url)" :disabled="!pagination.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(pagination.next_page_url)" :disabled="!pagination.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>

            <!-- Slip Download History -->
            <div v-if="props.slipDownloads?.length > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow mt-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Slip Download History</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Slip Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">NIN</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="download in props.slipDownloads" :key="download.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ download.reference }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ download.slip_name }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-300">{{ download.nin || '-' }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">₦{{ Number(download.amount).toLocaleString() }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium', getStatusClass(download.status)]">{{ download.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ download.date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <div v-if="showDetailsModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Transaction Details</h3>
                        <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded p-3">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Reference</p>
                                <p class="font-mono font-medium text-gray-900 dark:text-white break-all">{{ detailsRecord?.reference || '-' }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded p-3">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Status</p>
                                <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium', getStatusClass(detailsRecord?.status)]">{{ detailsRecord?.status }}</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded p-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Comment / Error Message</p>
                            <p class="text-gray-900 dark:text-white">{{ detailsRecord?.comment || 'No message' }}</p>
                        </div>
                        <div v-if="parsedResult" class="bg-gray-50 dark:bg-gray-700 rounded p-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Full API Response</p>
                            <div v-if="typeof parsedResult === 'object'" class="space-y-1">
                                <div v-for="(val, key) in parsedResult" :key="key" class="flex justify-between py-1 border-b border-gray-200 dark:border-gray-600 last:border-0">
                                    <span class="text-gray-500 dark:text-gray-400 capitalize text-xs">{{ String(key).replace(/_/g, ' ') }}</span>
                                    <span class="text-gray-900 dark:text-white text-xs font-medium text-right max-w-[60%] break-words">{{ val ?? 'null' }}</span>
                                </div>
                            </div>
                            <pre v-else class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-all">{{ parsedResult }}</pre>
                        </div>
                        <div v-else class="bg-gray-50 dark:bg-gray-700 rounded p-3 text-gray-400 text-xs text-center">No API response stored</div>
                    </div>
                    <button @click="showDetailsModal = false" class="mt-5 w-full bg-lime-600 text-white py-2 rounded-lg hover:bg-lime-700 font-medium">Close</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
