<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    payments: Object,
    filters: Object,
    userResults: Array,
    q: String,
    counts: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'pending');

const applyFilters = () => {
    router.get(route('admin.wallet.unattributed.index'), {
        search: search.value, status: status.value,
    }, { preserveState: true, preserveScroll: true });
};

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true }); };

const fmt = (n) => '₦' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const statusBadge = (s) => ({
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    resolved: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    ignored: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
}[s] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300');

// --- Resolve modal ------------------------------------------------------
const resolving = ref(null);
const userQuery = ref('');

const resolveForm = useForm({ username: '', amount: null, note: '' });

const openResolve = (payment) => {
    resolving.value = payment;
    userQuery.value = '';
    resolveForm.reset();
    resolveForm.clearErrors();
    // Default to the amount actually received, which is what the webhook
    // would have credited.
    resolveForm.amount = payment.amount;
};

const closeResolve = () => { resolving.value = null; userQuery.value = ''; };

// Server-side user search, same partial-reload pattern as Account Funding.
let searchTimer = null;
watch(userQuery, (value) => {
    clearTimeout(searchTimer);
    if (value.length < 2) return;
    searchTimer = setTimeout(() => {
        router.get(route('admin.wallet.unattributed.index'),
            { q: value, status: status.value, search: search.value },
            { preserveState: true, preserveScroll: true, only: ['userResults', 'q'] });
    }, 300);
});

const pickUser = (u) => { resolveForm.username = u.username; userQuery.value = ''; };

const submitResolve = () => {
    resolveForm.post(route('admin.wallet.unattributed.resolve', resolving.value.id), {
        preserveScroll: true,
        onSuccess: () => closeResolve(),
    });
};

// --- Ignore -------------------------------------------------------------
const ignoring = ref(null);
const ignoreForm = useForm({ note: '' });

const openIgnore = (payment) => {
    ignoring.value = payment;
    ignoreForm.reset();
    ignoreForm.clearErrors();
};

const submitIgnore = () => {
    ignoreForm.post(route('admin.wallet.unattributed.ignore', ignoring.value.id), {
        preserveScroll: true,
        onSuccess: () => { ignoring.value = null; },
    });
};

const reopen = (payment) => {
    router.post(route('admin.wallet.unattributed.reopen', payment.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Unattributed Payments" />
    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Unattributed Payments</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Funding that arrived but could not be matched to a user. This is real money already received — each row needs crediting or an explanation.
                    </p>
                </div>
                <Link :href="route('admin.wallet.index')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg whitespace-nowrap">
                    Account Funding
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Awaiting Reconciliation</p>
                        <p class="text-2xl font-bold" :class="counts.pending > 0 ? 'text-yellow-600' : 'text-gray-900 dark:text-white'">
                            {{ counts.pending.toLocaleString() }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-yellow-100 dark:bg-yellow-900/40 flex items-center justify-center text-yellow-600">!</div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Value Unreconciled</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ fmt(counts.pending_amount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600">₦</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                        <input v-model="search" type="text" placeholder="Reference, account number, email..." @keyup.enter="applyFilters"
                            class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select v-model="status" @change="applyFilters" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="pending">Pending</option>
                            <option value="resolved">Resolved</option>
                            <option value="ignored">Ignored</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button @click="applyFilters" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">Filter</button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Provider</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Paid To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Payer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Received</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="p in payments.data" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-500 dark:text-gray-400" :title="p.reference">
                                    {{ String(p.reference).slice(0, 12) }}…
                                </td>
                                <td class="px-6 py-4 text-sm capitalize text-gray-600 dark:text-gray-400">{{ p.provider }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-900 dark:text-white">{{ p.account_number || '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    <div>{{ p.customer_name || '—' }}</div>
                                    <div v-if="p.customer_email" class="text-xs text-gray-400">{{ p.customer_email }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">{{ fmt(p.amount) }}</td>
                                <td class="px-6 py-4">
                                    <span :class="['px-2 py-1 text-xs rounded-full font-medium capitalize', statusBadge(p.status)]">{{ p.status }}</span>
                                    <div v-if="p.resolved_user" class="text-xs text-gray-400 mt-1">→ {{ p.resolved_user.username }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ p.created_at }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <template v-if="p.status === 'pending'">
                                        <button @click="openResolve(p)" class="px-3 py-1 text-xs rounded bg-green-600 hover:bg-green-700 text-white font-medium">Credit</button>
                                        <button @click="openIgnore(p)" class="ml-2 px-3 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">Ignore</button>
                                    </template>
                                    <button v-else-if="p.status === 'ignored'" @click="reopen(p)" class="px-3 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">
                                        Reopen
                                    </button>
                                    <span v-else class="text-xs text-gray-400">{{ p.resolved_at }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="payments.data.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">
                    Nothing here — every payment received has been matched to a user.
                </div>
                <div v-if="payments.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ payments.from }} to {{ payments.to }} of {{ payments.total }} results</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(payments.prev_page_url)" :disabled="!payments.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(payments.next_page_url)" :disabled="!payments.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resolve modal -->
        <div v-if="resolving" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeResolve">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Credit this payment</h2>

                <div class="text-sm bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 space-y-1">
                    <div class="flex justify-between"><span class="text-gray-500">Reference</span><span class="font-mono text-xs">{{ resolving.reference }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Paid to</span><span class="font-mono">{{ resolving.account_number || '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Payer</span><span>{{ resolving.customer_name || resolving.customer_email || '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Amount sent</span><span class="font-semibold">{{ fmt(resolving.amount) }}</span></div>
                    <div v-if="resolving.settlement_amount !== null" class="flex justify-between">
                        <span class="text-gray-500">Settled</span><span>{{ fmt(resolving.settlement_amount) }}</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Credit to user</label>
                    <input v-if="!resolveForm.username" v-model="userQuery" type="text" placeholder="Search username, name, email or phone..."
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    <div v-else class="flex items-center justify-between rounded border border-gray-300 dark:border-gray-600 px-3 py-2">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ resolveForm.username }}</span>
                        <button @click="resolveForm.username = ''" class="text-xs text-indigo-600">change</button>
                    </div>

                    <ul v-if="!resolveForm.username && userResults.length" class="mt-1 border border-gray-200 dark:border-gray-600 rounded max-h-48 overflow-y-auto divide-y dark:divide-gray-700">
                        <li v-for="u in userResults" :key="u.id" @click="pickUser(u)"
                            class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                            <div class="font-medium text-gray-900 dark:text-white">{{ u.username }}</div>
                            <div class="text-xs text-gray-500">{{ u.email }} · balance {{ fmt(u.balance) }}</div>
                        </li>
                    </ul>
                    <p v-if="resolveForm.errors.username" class="text-sm text-red-600 mt-1">{{ resolveForm.errors.username }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount to credit</label>
                    <input v-model="resolveForm.amount" type="number" step="0.01" min="1"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    <p v-if="resolveForm.errors.amount" class="text-sm text-red-600 mt-1">{{ resolveForm.errors.amount }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note <span class="text-gray-400">(optional)</span></label>
                    <input v-model="resolveForm.note" type="text" placeholder="How was this matched?"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="closeResolve" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">Cancel</button>
                    <button @click="submitResolve" :disabled="!resolveForm.username || resolveForm.processing"
                        class="px-4 py-2 text-sm rounded-lg bg-green-600 hover:bg-green-700 text-white font-medium disabled:opacity-50">
                        {{ resolveForm.processing ? 'Crediting…' : 'Credit wallet' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Ignore modal -->
        <div v-if="ignoring" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="ignoring = null">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Ignore this payment</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No wallet is credited. Record why, so the decision is auditable later.
                </p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason</label>
                    <input v-model="ignoreForm.note" type="text" placeholder="e.g. test transfer, refunded at provider"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                    <p v-if="ignoreForm.errors.note" class="text-sm text-red-600 mt-1">{{ ignoreForm.errors.note }}</p>
                </div>
                <div class="flex justify-end gap-2">
                    <button @click="ignoring = null" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">Cancel</button>
                    <button @click="submitIgnore" :disabled="ignoreForm.processing"
                        class="px-4 py-2 text-sm rounded-lg bg-gray-700 hover:bg-gray-800 text-white font-medium disabled:opacity-50">Ignore</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
