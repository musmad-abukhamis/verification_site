<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BalanceStrip from '@/Components/BalanceStrip.vue';
import Alert from '@/Components/Alert.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    price: Number,
    transactions: Object,
    filters: Object,
});

const checkingStatus = ref({});
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
let searchTimeout;

// One form now: the two versions only differed in which provider they hit,
// which the routing chain decides.
const activeForm = useForm({ tracking_id: '', description: '' });

const canSubmit = computed(() => activeForm.tracking_id.length === 15 && !activeForm.processing);

const money = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const submit = () => {
    activeForm.post(route('nin.ipe.store'), {
        preserveScroll: true,
        onSuccess: () => activeForm.reset(),
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

const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(fetchTransactions, 300);
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-NG', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const canCheck = (status) => !['completed', 'failed'].includes(status?.toLowerCase());

const transactionsList = computed(() => props.transactions?.data || []);
</script>

<template>
    <Head title="NIN IPE Submission" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="NIN services"
                title="IPE clearance"
                description="Submit an Identity Proof of Enrollment using a NIN tracking ID."
            />

            <BalanceStrip :wallet="wallet" :price="price" />

            <!-- Submit -->
            <section class="card card-pad">
                <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">New submission</h2>

                <div class="mt-4 space-y-3">
                    <Alert v-if="$page.props.errors?.message" tone="danger">{{ $page.props.errors.message }}</Alert>
                    <Alert v-if="$page.props.flash?.success" tone="success">{{ $page.props.flash.success }}</Alert>
                </div>

                <form @submit.prevent="submit" class="mt-4 max-w-lg space-y-4">
                    <div>
                        <label for="tracking_id" class="eyebrow">Tracking ID (15 characters)</label>
                        <input
                            id="tracking_id"
                            v-model="activeForm.tracking_id"
                            type="text"
                            maxlength="15"
                            placeholder="15-character tracking ID"
                            class="mt-1.5 block w-full font-mono text-lg"
                        />
                        <p class="mt-1 font-mono text-xs text-ink-400 dark:text-ink-500">
                            {{ activeForm.tracking_id.length }}/15
                        </p>
                        <p v-if="activeForm.errors.tracking_id" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">
                            {{ activeForm.errors.tracking_id }}
                        </p>
                    </div>

                    <div>
                        <label for="description" class="eyebrow">
                            Description <span class="normal-case tracking-normal text-ink-400">(optional)</span>
                        </label>
                        <input
                            id="description"
                            v-model="activeForm.description"
                            type="text"
                            placeholder="Enrollment ref #001"
                            class="mt-1.5 block w-full"
                        />
                    </div>

                    <button type="submit" :disabled="!canSubmit" class="btn btn-primary">
                        <svg v-if="activeForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ activeForm.processing ? 'Submitting…' : `Submit IPE — ${money(price)}` }}
                    </button>
                </form>
            </section>

            <!-- History -->
            <section class="card overflow-hidden">
                <div class="flex flex-col gap-3 border-b rule px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">IPE submissions</h2>

                    <div class="flex flex-wrap gap-2">
                        <input
                            v-model="search"
                            @input="debouncedSearch"
                            type="search"
                            placeholder="Search…"
                            aria-label="Search submissions"
                            class="text-sm"
                        />
                        <select v-model="statusFilter" @change="fetchTransactions" aria-label="Filter by status" class="text-sm">
                            <option value="">All statuses</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                <template v-if="transactionsList.length">
                    <div class="scroll-slim overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="t-head">
                                <tr>
                                    <th>ID</th>
                                    <th>Tracking ID</th>
                                    <th>Result</th>
                                    <th>Status</th>
                                    <th>Comment</th>
                                    <th>Prev. balance</th>
                                    <th>New balance</th>
                                    <th>Date</th>
                                    <th><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y rule">
                                <tr v-for="tx in transactionsList" :key="tx.id" class="t-row">
                                    <td class="font-mono text-ink-500 dark:text-ink-400">{{ tx.id }}</td>
                                    <td class="font-mono font-semibold text-ink-950 dark:text-white">{{ tx.nin }}</td>
                                    <td class="text-ink-600 dark:text-ink-300">{{ tx.result || '—' }}</td>
                                    <td><StatusPill :status="tx.status" /></td>
                                    <td class="max-w-[150px] truncate text-ink-600 dark:text-ink-300" :title="tx.comment">
                                        {{ tx.comment || '—' }}
                                    </td>
                                    <td class="font-mono text-ink-600 dark:text-ink-300">{{ money(tx.old_balance) }}</td>
                                    <td class="font-mono text-ink-600 dark:text-ink-300">{{ money(tx.new_balance) }}</td>
                                    <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ formatDate(tx.created_at) }}</td>
                                    <td class="text-right">
                                        <button
                                            @click="checkStatus(tx)"
                                            :disabled="!canCheck(tx.status) || checkingStatus[tx.id]"
                                            class="btn btn-secondary btn-sm"
                                        >
                                            <svg v-if="checkingStatus[tx.id]" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            {{ checkingStatus[tx.id] ? 'Checking…' : 'Check' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination :paginator="transactions" label="submissions" />
                </template>

                <EmptyState
                    v-else
                    title="No IPE submissions yet"
                    description="Submit a tracking ID above and each attempt will be listed here."
                    icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
