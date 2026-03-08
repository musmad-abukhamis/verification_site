<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    price: Number,
    transactions: Object,
    filters: Object,
});

const page = usePage();
const provider = ref('v1');
const checkingStatus = ref({});
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
let searchTimeout;

const v1Form = useForm({ trkid: '' });
const v2Form = useForm({ tracking_id: '', description: '' });

const activeForm = computed(() => provider.value === 'v1' ? v1Form : v2Form);

const canSubmit = computed(() => {
    if (provider.value === 'v1') return v1Form.trkid.length === 15 && !v1Form.processing;
    return v2Form.tracking_id.length === 15 && !v2Form.processing;
});

const submit = () => {
    const routeName = provider.value === 'v1' ? 'nin.ipe.v1' : 'nin.ipe.v2';
    const form = provider.value === 'v1' ? v1Form : v2Form;
    form.post(route(routeName), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const checkStatus = (tx) => {
    if (checkingStatus.value[tx.id]) return;
    checkingStatus.value[tx.id] = true;
    router.post(route('nin.ipe.status', tx.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { checkingStatus.value[tx.id] = false; },
    });
};

const fetchTransactions = () => {
    router.get(route('nin.ipe.index'), { search: search.value, status: statusFilter.value }, {
        preserveState: true, preserveScroll: true, only: ['transactions'],
    });
};

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
        processing: 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return map[status?.toLowerCase()] ?? 'bg-gray-100 text-gray-800';
};

const canCheck = (status) => !['completed', 'failed'].includes(status?.toLowerCase());

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
    <Head title="NIN IPE Submission" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Wallet -->
            <div class="bg-gradient-to-r from-orange-500 to-rose-500 rounded-xl shadow p-6 text-white">
                <p class="text-sm opacity-80">Wallet Balance</p>
                <p class="text-3xl font-bold mt-1">₦{{ wallet.total_balance.toLocaleString() }}</p>
                <div class="flex gap-4 mt-2 text-sm opacity-80">
                    <span>Main: ₦{{ wallet.balance.toLocaleString() }}</span>
                    <span>Bonus: ₦{{ wallet.bonus_balance.toLocaleString() }}</span>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">IPE Submission</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Submit an Identity Proof of Enrollment (IPE) using a NIN tracking ID.</p>
                <p class="text-sm font-semibold text-orange-600 dark:text-orange-400 mb-6">Price: ₦{{ price?.toLocaleString() }}</p>

                <div v-if="$page.props.errors?.message" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">{{ $page.props.errors.message }}</div>
                <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

                <!-- Provider Tabs -->
                <div class="flex gap-2 mb-6">
                    <button v-for="v in ['v1', 'v2']" :key="v" @click="provider = v"
                        :class="['px-5 py-2 rounded-lg text-sm font-semibold transition-colors', provider === v ? 'bg-orange-500 text-white shadow' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200']">
                        {{ v === 'v1' ? 'V1 — Nguru/Litetech' : 'V2 — ArewaSmart' }}
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4 max-w-lg">
                    <!-- V1: trkid field -->
                    <div v-if="provider === 'v1'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tracking ID (15 characters)</label>
                        <input v-model="v1Form.trkid" type="text" maxlength="15" placeholder="Enter 15-character tracking ID"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 font-mono focus:ring-2 focus:ring-orange-500" />
                        <p class="mt-1 text-xs text-gray-500">{{ v1Form.trkid.length }}/15 characters</p>
                        <p v-if="v1Form.errors.trkid" class="mt-1 text-xs text-red-500">{{ v1Form.errors.trkid }}</p>
                    </div>
                    <!-- V2: tracking_id + description fields -->
                    <div v-else class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tracking ID (15 characters)</label>
                            <input v-model="v2Form.tracking_id" type="text" maxlength="15" placeholder="Enter 15-character tracking ID"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 font-mono focus:ring-2 focus:ring-orange-500" />
                            <p class="mt-1 text-xs text-gray-500">{{ v2Form.tracking_id.length }}/15 characters</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description <span class="text-gray-400">(optional)</span></label>
                            <input v-model="v2Form.description" type="text" placeholder="Enrollment ref #001"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2.5 focus:ring-2 focus:ring-orange-500" />
                        </div>
                    </div>

                    <button type="submit" :disabled="!canSubmit"
                        class="flex items-center gap-2 px-6 py-2.5 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <svg v-if="activeForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ activeForm.processing ? 'Submitting...' : `Submit IPE (₦${price?.toLocaleString()})` }}
                    </button>
                </form>
            </div>

            <!-- Transaction History -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">IPE Submissions</h3>
                    <div class="flex gap-3">
                        <input v-model="search" @input="() => { clearTimeout(searchTimeout); searchTimeout = setTimeout(fetchTransactions, 300); }"
                            type="text" placeholder="Search..." class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        <select v-model="statusFilter" @change="fetchTransactions" class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">All Statuses</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Result</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Old Bal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New Bal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="tx in transactionsList" :key="tx.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ tx.id }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ tx.nin }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ tx.result || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium', getStatusClass(tx.status)]">{{ tx.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-[150px] truncate">{{ tx.comment || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">₦{{ Number(tx.old_balance || 0).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">₦{{ Number(tx.new_balance || 0).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(tx.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <button @click="checkStatus(tx)" :disabled="!canCheck(tx.status) || checkingStatus[tx.id]"
                                        class="inline-flex items-center gap-1 px-3 py-1 text-xs bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300 rounded hover:bg-orange-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <svg v-if="checkingStatus[tx.id]" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        {{ checkingStatus[tx.id] ? 'Checking...' : 'Check' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="transactionsList.length === 0" class="py-10 text-center text-gray-400">No IPE submissions yet</div>
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
    </AuthenticatedLayout>
</template>
