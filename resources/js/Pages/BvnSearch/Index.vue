<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BvnPremiumSlip from '@/Components/BvnPremiumSlip.vue';
import BvnLongSlip from '@/Components/BvnLongSlip.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    slipTypes: Array,
    history: Object,
});

const page = usePage();
const result = ref(null);

const form = useForm({
    idValue: '',
    slipType: props.slipTypes?.[0]?.code || 'premium',
});

const selectedPrice = computed(() => props.slipTypes.find((t) => t.code === form.slipType)?.price ?? null);

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined) return 'N/A';
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(Number(amount));
};

const submit = () => {
    form.transform((d) => ({ ...d, idValue: d.idValue.replace(/\D/g, '') }))
        .post(route('bvn-verify.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (page.props.flash?.verification_data) {
                    result.value = page.props.flash.verification_data;
                    form.reset('idValue');
                }
            },
        });
};

const reset = () => { result.value = null; };

const formatBvn = (bvn) => {
    const s = String(bvn || '');
    return s.length === 11 ? `${s.slice(0, 4)} ${s.slice(4, 7)} ${s.slice(7, 11)}` : s;
};

const fmtDate = (d) => {
    if (!d) return '-';
    const date = new Date(d);
    return isNaN(date) ? d : date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const fullName = computed(() => {
    const r = result.value || {};
    return [r.surname, r.firstname, r.middlename].filter(Boolean).join(' ');
});

// Raw base64 JPEG (no data: prefix) -> data URL for the slip generators.
const photoDataUrl = computed(() => {
    const photo = result.value?.photo;
    return photo ? `data:image/jpeg;base64,${photo}` : '';
});

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true, only: ['history'] }); };

const statusClass = (s) => s === 'success'
    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';

const printSlip = () => window.print();
</script>

<template>
    <Head title="BVN Verification" />
    <AuthenticatedLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Wallet -->
            <div class="bg-gradient-to-r from-lime-600 to-green-700 rounded-xl shadow p-6 text-white print:hidden">
                <p class="text-sm opacity-80">Wallet Balance</p>
                <p class="text-3xl font-bold mt-1">â‚¦{{ wallet.total_balance.toLocaleString() }}</p>
            </div>

            <!-- Form -->
            <div v-if="!result" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6 max-w-xl mx-auto w-full">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">BVN Verification</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Enter a BVN number to verify identity and generate a slip.</p>

                <div v-if="form.errors.message" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ form.errors.message }}</div>

                <div v-if="slipTypes.length === 0" class="mb-4 p-3 bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 rounded-lg text-sm">
                    No BVN verification prices configured. An admin must set a Search Slip price under BVN Service Prices.
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">BVN Number</label>
                        <input v-model="form.idValue" type="text" maxlength="11" inputmode="numeric" placeholder="Enter 11-digit BVN"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2.5 font-mono focus:ring-2 focus:ring-lime-500" />
                        <p v-if="form.errors.idValue" class="mt-1 text-xs text-red-600">{{ form.errors.idValue }}</p>
                        <p class="mt-1 text-xs text-gray-500">Please enter the 11-digit Bank Verification Number.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Slip Type</label>
                        <select v-model="form.slipType" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2.5">
                            <option v-for="t in slipTypes" :key="t.code" :value="t.code">{{ t.name }} â€” {{ formatCurrency(t.price) }}</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="form.processing || slipTypes.length === 0"
                        class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-lime-600 hover:bg-lime-700 text-white rounded-lg font-medium disabled:opacity-50">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ form.processing ? 'Verifying...' : `Verify BVN${selectedPrice ? ` (${formatCurrency(selectedPrice)})` : ''}` }}
                    </button>
                </form>
            </div>

            <!-- Result + slip -->
            <div v-else class="space-y-4">
                <div class="flex items-center justify-between print:hidden">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Verification Result</h2>
                    <div class="flex gap-2">
                        <button @click="printSlip" class="px-4 py-2 bg-lime-600 hover:bg-lime-700 text-white text-sm font-medium rounded-lg">Print Slip</button>
                        <button @click="reset" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm rounded-lg text-gray-600 dark:text-gray-300">Verify Another</button>
                    </div>
                </div>

                <!-- Downloadable PDF slips -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl mx-auto w-full print:hidden">
                    <BvnPremiumSlip
                        :surname="result.surname || ''"
                        :othernames="[result.firstname, result.middlename].filter(Boolean).join(' ')"
                        :dob="result.dob || ''"
                        :gender="result.gender || ''"
                        :nin="String(result.bvn || '')"
                        :photo="photoDataUrl"
                        :issued-date="fmtDate(new Date())"
                        :qr-value="`BVN:${result.bvn || ''}|${fullName}`"
                        :watermark="String(result.bvn || 'VERIFIED')"
                    />
                    <BvnLongSlip
                        :bvn="String(result.bvn || '')"
                        :nin="String(result.nin || '')"
                        :first-name="result.firstname || ''"
                        :last-name="result.surname || ''"
                        :middle-name="result.middlename || ''"
                        :phone="result.phone || ''"
                        :email="result.email || ''"
                        :dob="result.dob || ''"
                        :gender="result.gender || ''"
                        :marital="result.marital_status || ''"
                        :state="result.state_of_origin || ''"
                        :lga="result.lga_of_origin || ''"
                        :address="result.residential_Address || ''"
                        :enrollment-bank="result.enrollment_bank || ''"
                        :enrollment-branch="result.enrollment_bank_branch || ''"
                        :reg-date="result.registration_date || ''"
                        :residential-addr="result.residential_Address || ''"
                        :image-url="photoDataUrl"
                    />
                </div>

                <!-- Printable BVN slip -->
                <div class="bg-white text-gray-900 rounded-xl shadow-lg overflow-hidden max-w-xl mx-auto border border-gray-200">
                    <div class="bg-lime-600 text-white px-6 py-3 text-center">
                        <h3 class="text-lg font-bold">Bank Verification Number Slip</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-4 mb-6">
                            <img :src="result.photo ? `data:image/jpeg;base64,${result.photo}` : 'https://placehold.co/96x96?text=Photo'"
                                @error="(e) => e.target.src = 'https://placehold.co/96x96?text=Photo'"
                                alt="Photo" class="w-24 h-24 rounded-lg object-cover border border-gray-200 bg-gray-50" />
                            <div>
                                <p class="text-xl font-bold uppercase">{{ fullName }}</p>
                                <p class="text-lg font-mono tracking-wider text-lime-700">{{ formatBvn(result.bvn) }}</p>
                            </div>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div><dt class="text-gray-500">Date of Birth</dt><dd class="font-medium">{{ fmtDate(result.dob) }}</dd></div>
                            <div><dt class="text-gray-500">Gender</dt><dd class="font-medium">{{ result.gender || '-' }}</dd></div>
                            <div><dt class="text-gray-500">Phone Number</dt><dd class="font-medium">{{ result.phone || '-' }}</dd></div>
                            <div><dt class="text-gray-500">Email</dt><dd class="font-medium break-all">{{ result.email || 'N/A' }}</dd></div>
                            <div><dt class="text-gray-500">Marital Status</dt><dd class="font-medium">{{ result.marital_status || '-' }}</dd></div>
                            <div><dt class="text-gray-500">State of Origin</dt><dd class="font-medium">{{ result.state_of_origin || '-' }}</dd></div>
                            <div><dt class="text-gray-500">LGA of Origin</dt><dd class="font-medium">{{ result.lga_of_origin || '-' }}</dd></div>
                            <div><dt class="text-gray-500">Registration Date</dt><dd class="font-medium">{{ fmtDate(result.registration_date) }}</dd></div>
                            <div class="col-span-2"><dt class="text-gray-500">Enrollment Bank</dt><dd class="font-medium">{{ result.enrollment_bank || '-' }} <span v-if="result.enrollment_bank_branch">({{ result.enrollment_bank_branch }})</span></dd></div>
                            <div class="col-span-2"><dt class="text-gray-500">Residential Address</dt><dd class="font-medium">{{ result.residential_Address || '-' }}</dd></div>
                        </dl>
                    </div>
                    <div class="bg-gray-50 px-6 py-2 text-center text-xs text-gray-400 border-t">Generated {{ fmtDate(new Date()) }}</div>
                </div>
            </div>

            <!-- History -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden print:hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Verification History</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BVN</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slip</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="h in history.data" :key="h.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ h.bvn }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ h.name || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ h.slip_type }}</td>
                                <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium capitalize', statusClass(h.status)]">{{ h.status }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">â‚¦{{ Number(h.price || 0).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ fmtDate(h.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="history.data.length === 0" class="py-10 text-center text-gray-400">No BVN searches yet.</div>
                <div v-if="history.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ history.from }}â€“{{ history.to }} of {{ history.total }}</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(history.prev_page_url)" :disabled="!history.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(history.next_page_url)" :disabled="!history.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
