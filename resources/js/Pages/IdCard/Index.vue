<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    price: [Number, String, null],
    requests: Object,
    filters: Object,
});

// ---- Application form ----
const form = useForm({
    fullname: '',
    email: '',
    agentId: '',
    passportImage: null,
});

const previewImage = ref(null);
const fileError = ref('');

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || amount === '' || Number(amount) === 0) return 'Contact Support';
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(Number(amount));
};

const feeAvailable = computed(() => props.price && Number(props.price) > 0);

const onFile = (e) => {
    fileError.value = '';
    const file = e.target.files?.[0];
    if (!file) return;

    if (file.size > 500 * 1024) {
        fileError.value = 'Image must be less than 500KB';
        e.target.value = '';
        return;
    }
    const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        fileError.value = 'Only JPEG, PNG, and WebP images are allowed';
        e.target.value = '';
        return;
    }

    form.passportImage = file;
    const reader = new FileReader();
    reader.onload = (ev) => { previewImage.value = ev.target?.result; };
    reader.readAsDataURL(file);
};

const submit = () => {
    form.post(route('idcard.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            previewImage.value = null;
            fileError.value = '';
        },
    });
};

// ---- Requests list ----
const search = ref(props.filters?.search || '');

const applyFilters = () => {
    router.get(route('idcard.index'), { search: search.value }, { preserveState: true, preserveScroll: true });
};

const clearSearch = () => {
    search.value = '';
    applyFilters();
};

const goToPage = (url) => {
    if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusClass = (s) => {
    const map = {
        approved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[s?.toLowerCase()] || map.pending;
};

// ---- Details modal ----
const selected = ref(null);
const openDetails = (r) => { selected.value = r; };
const closeDetails = () => { selected.value = null; };
</script>

<template>
    <Head title="ID Card Application" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Wallet -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl shadow p-6 text-white">
                <p class="text-sm opacity-80">Wallet Balance</p>
                <p class="text-3xl font-bold mt-1">₦{{ wallet.total_balance.toLocaleString() }}</p>
                <div class="flex gap-4 mt-2 text-sm opacity-80">
                    <span>ID Card Fee: {{ formatCurrency(price) }}</span>
                </div>
            </div>

            <!-- Application Form -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <div class="mb-1">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">ID Card Application</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Submit your ID card application with the required documents and information.</p>
                </div>

                <div v-if="form.errors.message" class="my-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ form.errors.message }}</div>
                <div v-if="$page.props.flash?.success" class="my-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

                <form @submit.prevent="submit" class="space-y-6 mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Full Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                            <input v-model="form.fullname" type="text" maxlength="27" placeholder="Enter your full name"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-emerald-500" />
                            <div class="flex justify-between text-xs mt-1">
                                <span class="text-red-600">{{ form.errors.fullname }}</span>
                                <span class="text-gray-400">{{ form.fullname.length }}/27</span>
                            </div>
                        </div>
                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                            <input v-model="form.email" type="email" placeholder="Enter your email address"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-emerald-500" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <!-- Agent ID -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Agent ID *</label>
                        <input v-model="form.agentId" type="text" placeholder="Enter your agent ID"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 font-mono focus:ring-2 focus:ring-emerald-500" />
                        <p v-if="form.errors.agentId" class="mt-1 text-xs text-red-600">{{ form.errors.agentId }}</p>
                    </div>

                    <!-- Passport Image -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Passport Image *</label>
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <p class="mb-1 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> passport photo</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG or WebP (MAX. 500KB)</p>
                            </div>
                            <input type="file" class="hidden" accept="image/jpeg,image/jpg,image/png,image/webp" @change="onFile" />
                        </label>
                        <p v-if="fileError" class="mt-1 text-xs text-red-600">{{ fileError }}</p>
                        <p v-if="form.errors.passportImage" class="mt-1 text-xs text-red-600">{{ form.errors.passportImage }}</p>

                        <div v-if="previewImage" class="flex items-center space-x-4 p-4 mt-3 bg-gray-50 dark:bg-gray-700/40 rounded-lg">
                            <img :src="previewImage" alt="Preview" class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ form.passportImage?.name }}</p>
                                <p class="text-xs text-gray-500">{{ form.passportImage ? (form.passportImage.size / 1024).toFixed(1) + ' KB' : '' }}</p>
                            </div>
                            <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing || !feeAvailable"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span v-if="form.processing">Submitting Application...</span>
                        <span v-else>Submit ID Card Application<template v-if="feeAvailable"> — {{ formatCurrency(price) }}</template></span>
                    </button>
                    <p v-if="!feeAvailable" class="text-center text-xs text-yellow-600 dark:text-yellow-400">ID card service fee is not configured yet. Please contact support.</p>
                    <p v-else class="text-center text-xs text-gray-500 dark:text-gray-400">
                        By submitting this application, you agree to the service fee which will be deducted from your wallet balance.
                    </p>
                </form>
            </div>

            <!-- My Applications -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">My ID Card Applications</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">View and track your submitted ID card applications.</p>
                </div>

                <!-- Search -->
                <div class="flex gap-2 mb-4">
                    <input v-model="search" type="text" placeholder="Search by name, email, agent ID, or status..." @keyup.enter="applyFilters"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2 focus:ring-2 focus:ring-emerald-500" />
                    <button @click="applyFilters" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg">Search</button>
                    <button v-if="filters?.search" @click="clearSearch" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300">Clear</button>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Full Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ r.fullname }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ r.email }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-600 dark:text-gray-400">{{ r.agentId }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium capitalize', statusClass(r.status)]">{{ r.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(r.created_at) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">₦{{ r.amount_charged }}</td>
                                <td class="px-4 py-3">
                                    <button @click="openDetails(r)" class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 text-sm font-medium">View</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="requests.data.length === 0" class="py-10 text-center text-gray-400">No ID card applications found.</div>

                <div v-if="requests.total > 0" class="mt-4 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ requests.from }}–{{ requests.to }} of {{ requests.total }}</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(requests.prev_page_url)" :disabled="!requests.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(requests.next_page_url)" :disabled="!requests.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50" @click.self="closeDetails">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">ID Card Application Details</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Submitted on {{ formatDate(selected.created_at) }}</p>
                    </div>
                    <button @click="closeDetails" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-4 p-5">
                    <div>
                        <label class="text-xs font-medium text-gray-500">Full Name</label>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ selected.fullname }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Email</label>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ selected.email }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Agent ID</label>
                        <p class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ selected.agentId }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Status</label>
                        <div class="mt-0.5">
                            <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium capitalize', statusClass(selected.status)]">{{ selected.status }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Amount Charged</label>
                        <p class="text-sm text-gray-900 dark:text-gray-100">₦{{ selected.amount_charged }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500">Balance Change</label>
                        <p class="text-sm text-gray-900 dark:text-gray-100">₦{{ selected.old_balance }} → ₦{{ selected.new_balance }}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs font-medium text-gray-500">Passport Photo</label>
                        <div class="mt-2">
                            <img :src="selected.image_url" alt="Passport" class="w-32 h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-600" />
                        </div>
                    </div>
                    <div v-if="selected.comment" class="col-span-2">
                        <label class="text-xs font-medium text-gray-500">Comment</label>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ selected.comment }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
