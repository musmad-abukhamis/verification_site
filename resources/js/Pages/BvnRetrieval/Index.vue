<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    wallet: Object,
    price: Number,
    requests: Object,
    filters: Object,
});

const termsAccepted = ref(false);
const agreeChecked = ref(false);
const search = ref(props.filters?.search || '');
const selected = ref(null);
let searchTimeout;

const form = useForm({ bmsId: '' });

const submit = () => {
    form.post(route('bvn-retrieval.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const fetchRequests = () => {
    router.get(route('bvn-retrieval.index'), { search: search.value }, {
        preserveState: true, preserveScroll: true, only: ['requests'],
    });
};

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true, only: ['requests'] }); };

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatCurrency = (amount) => {
    if (amount === null || amount === undefined) return 'N/A';
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(Number(amount));
};

const statusClass = (s) => {
    const map = {
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[s?.toLowerCase()] || map.pending;
};
</script>

<template>
    <Head title="BVN Retrieval" />
    <AuthenticatedLayout>
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="text-center space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">BVN Retrieval Service</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-2xl mx-auto">Submit your BVN retrieval request with your Ticket ID (BMS ID). Track the status and view your BVN once it's ready.</p>
            </div>

            <!-- Wallet + price -->
            <div class="bg-gradient-to-r from-sky-600 to-blue-700 rounded-xl shadow p-6 text-white flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-80">Wallet Balance</p>
                    <p class="text-3xl font-bold mt-1">₦{{ wallet.total_balance.toLocaleString() }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm opacity-80">Retrieval Fee</p>
                    <p class="text-2xl font-bold mt-1">{{ formatCurrency(price) }}</p>
                </div>
            </div>

            <!-- Form card -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6 max-w-2xl mx-auto w-full">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-1">BVN Retrieval Request</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Submit a request to retrieve your BVN using your Ticket ID (BMS ID).</p>

                <div v-if="form.errors.message" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ form.errors.message }}</div>
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

                <!-- Terms gate -->
                <div v-if="!termsAccepted" class="space-y-4">
                    <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 dark:bg-amber-950/20 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-sm">
                        You must read and accept the terms and conditions before submitting a BVN retrieval request.
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <p class="font-semibold">Terms &amp; Conditions</p>
                        <p>By submitting a BVN retrieval request you confirm that the Ticket ID (BMS ID) provided belongs to you and is accurate.</p>
                        <p>The retrieval fee is non-refundable once a request has been processed. Processing times vary and the retrieved BVN will be made available on this page once ready.</p>
                        <p>You agree not to use this service for any fraudulent or unlawful purpose.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <input type="checkbox" v-model="agreeChecked" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
                        I have read and accept the terms and conditions
                    </label>
                    <button @click="termsAccepted = true" :disabled="!agreeChecked"
                        class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        Accept &amp; Continue
                    </button>
                </div>

                <!-- Form -->
                <form v-else @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ticket ID (BMS ID) *</label>
                        <input v-model="form.bmsId" type="text" maxlength="8" inputmode="numeric" placeholder="Enter your 8-digit Ticket ID"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 font-mono" />
                        <p v-if="form.errors.bmsId" class="mt-1 text-xs text-red-600">{{ form.errors.bmsId }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-sky-50 border border-sky-200 dark:bg-sky-950/20 dark:border-sky-800 text-sky-800 dark:text-sky-200 text-sm">
                        Please provide your 8-digit Ticket ID (BMS ID) for verification.
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-sm font-medium disabled:opacity-50">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ form.processing ? 'Submitting Request...' : `Submit Request (${formatCurrency(price)})` }}
                    </button>
                </form>
            </div>

            <!-- Requests table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">My BVN Retrieval Requests</h3>
                    <input v-model="search" @input="() => { clearTimeout(searchTimeout); searchTimeout = setTimeout(fetchRequests, 400); }"
                        type="text" placeholder="Search by ticket ID, NIN, status..."
                        class="w-full md:max-w-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm" />
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BMS ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BVN</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Updated</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ r.ticketId2 || '-' }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ r.bvn || 'Not yet provided' }}</td>
                                <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium capitalize', statusClass(r.status)]">{{ r.status || 'pending' }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(r.created_at) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(r.updated_at) }}</td>
                                <td class="px-4 py-3"><button @click="selected = r" class="text-sky-600 hover:text-sky-800 dark:text-sky-400 text-sm font-medium">View</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="requests.data.length === 0" class="py-10 text-center text-gray-400">No BVN retrieval requests found.</div>
                <div v-if="requests.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ requests.from }}–{{ requests.to }} of {{ requests.total }}</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(requests.prev_page_url)" :disabled="!requests.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(requests.next_page_url)" :disabled="!requests.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- View modal -->
        <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="selected = null">
            <div class="fixed inset-0 bg-black/50"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-lg w-full p-6">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Request Details</h3>
                    <button @click="selected = null" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400">BMS ID</dt><dd class="font-mono text-gray-900 dark:text-white">{{ selected.ticketId2 || '-' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Status</dt><dd><span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium capitalize', statusClass(selected.status)]">{{ selected.status || 'pending' }}</span></dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">NIN</dt><dd class="font-mono text-gray-900 dark:text-white">{{ selected.nin || '-' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">BVN</dt><dd class="font-mono text-gray-900 dark:text-white">{{ selected.bvn || 'Not yet provided' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 dark:text-gray-400">Comment</dt><dd class="text-gray-900 dark:text-white">{{ selected.comment || 'No comments' }}</dd></div>
                </dl>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
