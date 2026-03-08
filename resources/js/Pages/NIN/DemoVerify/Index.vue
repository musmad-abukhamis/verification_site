<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    price: Number,
    transactions: Object,
});

const page = usePage();
const provider = ref('v1');
const showResult = ref(false);
const verificationResult = ref(null);

const form = useForm({
    firstName: '',
    lastName: '',
    gender: 'M',
    dateOfBirth: '',
    ref: '',
});

const canSubmit = computed(() =>
    form.firstName.length >= 2 && form.lastName.length >= 2 && form.gender && form.dateOfBirth && !form.processing
);

// Convert YYYY-MM-DD (HTML date input) to DD-MM-YYYY (API format)
const formattedDob = computed(() => {
    if (!form.dateOfBirth) return '';
    const [y, m, d] = form.dateOfBirth.split('-');
    return `${d}-${m}-${y}`;
});

const submit = () => {
    const routeName = provider.value === 'v1' ? 'nin.demo.v1' : 'nin.demo.v2';
    const data = { ...form.data(), dateOfBirth: formattedDob.value };
    router.post(route(routeName), data, {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.verification_data) {
                verificationResult.value = page.props.flash.verification_data;
                showResult.value = true;
                form.reset();
            }
        },
        onError: () => {},
    });
};

const closeResult = () => { showResult.value = false; verificationResult.value = null; };

const goToPage = (url) => {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: false, only: ['transactions'] });
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('en-NG', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const getStatusClass = (status) => {
    const map = {
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        processing: 'border border-gray-300 text-gray-700',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return map[status?.toLowerCase()] ?? 'bg-gray-100 text-gray-800';
};

const transactionsList = computed(() => props.transactions?.data || []);
const pagination = computed(() => ({
    from: props.transactions?.from || 0,
    to: props.transactions?.to || 0,
    total: props.transactions?.total || 0,
    prev_page_url: props.transactions?.prev_page_url,
    next_page_url: props.transactions?.next_page_url,
}));
</script>

<template>
    <Head title="NIN Demo Verification" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Wallet -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl shadow p-6 text-white">
                <p class="text-sm opacity-80">Wallet Balance</p>
                <p class="text-3xl font-bold mt-1">₦{{ wallet.total_balance.toLocaleString() }}</p>
                <div class="flex gap-4 mt-2 text-sm opacity-80">
                    <span>Main: ₦{{ wallet.balance.toLocaleString() }}</span>
                    <span>Bonus: ₦{{ wallet.bonus_balance.toLocaleString() }}</span>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">NIN Demo Verification</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Verify a NIN by matching personal details (name, gender, DOB) — always billed at premium price.</p>
                <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 mb-6">Price: ₦{{ price?.toLocaleString() }}</p>

                <div v-if="$page.props.errors?.message" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ $page.props.errors.message }}</div>
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

                <!-- Provider Tabs -->
                <div class="flex gap-2 mb-6">
                    <button v-for="v in ['v1', 'v2']" :key="v" @click="provider = v"
                        :class="['px-5 py-2 rounded-lg text-sm font-semibold transition-colors', provider === v ? 'bg-emerald-600 text-white shadow' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                        {{ v === 'v1' ? 'V1 — ArewaSmart' : 'V2 — ArewaSmart Alt' }}
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4 max-w-lg">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                            <input v-model="form.firstName" type="text" placeholder="Ahmed"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                            <p v-if="form.errors.firstName" class="mt-1 text-xs text-red-500">{{ form.errors.firstName }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                            <input v-model="form.lastName" type="text" placeholder="Ibrahim"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                            <p v-if="form.errors.lastName" class="mt-1 text-xs text-red-500">{{ form.errors.lastName }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Gender</label>
                            <select v-model="form.gender" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-emerald-500">
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date of Birth</label>
                            <input v-model="form.dateOfBirth" type="date"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-emerald-500" />
                            <p class="mt-1 text-xs text-gray-500">Will be sent as DD-MM-YYYY</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference <span class="text-gray-400">(optional)</span></label>
                        <input v-model="form.ref" type="text" placeholder="my-custom-ref"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-emerald-500" />
                    </div>

                    <button type="submit" :disabled="!canSubmit"
                        class="flex items-center gap-2 px-6 py-2.5 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ form.processing ? 'Verifying...' : `Demo Verify (₦${price?.toLocaleString()})` }}
                    </button>
                </form>
            </div>

            <!-- History Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Demo Verification History</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NIN</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Old Bal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Bal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="tx in transactionsList" :key="tx.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ tx.id }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ tx.nin || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium', getStatusClass(tx.status)]">{{ tx.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-[180px] truncate">{{ tx.comment || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">₦{{ Number(tx.old_balance || 0).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">₦{{ Number(tx.new_balance || 0).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(tx.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="transactionsList.length === 0" class="py-10 text-center text-gray-400">No demo verifications yet</div>
                </div>
                <div v-if="pagination.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(pagination.prev_page_url)" :disabled="!pagination.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(pagination.next_page_url)" :disabled="!pagination.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Result Modal -->
        <div v-if="showResult" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Demo Verification Result</h3>
                        <button @click="closeResult" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div v-if="verificationResult?.photo" class="flex justify-center mb-4">
                        <img :src="verificationResult.photo" alt="Photo" class="w-24 h-24 rounded-full object-cover border-4 border-emerald-200" />
                    </div>
                    <div class="space-y-2">
                        <div v-for="(val, key) in verificationResult" :key="key" class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm text-gray-500 capitalize">{{ key.replace(/_/g, ' ') }}</span>
                            <span v-if="key !== 'photo'" class="text-sm font-medium text-gray-900 dark:text-white text-right max-w-[60%]">{{ val || 'N/A' }}</span>
                        </div>
                    </div>
                    <button @click="closeResult" class="mt-6 w-full bg-emerald-600 text-white py-2.5 rounded-lg hover:bg-emerald-700 font-medium">Close</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
