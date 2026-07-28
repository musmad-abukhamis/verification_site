<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BalanceStrip from '@/Components/BalanceStrip.vue';
import Alert from '@/Components/Alert.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    wallet: Object,
    price: Number,
    transactions: Object,
    filters: Object,
});

const nin = ref('');
const isSubmitting = ref(false);
const formError = ref('');
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const checkingStatus = ref({});
let searchTimeout;

const canSubmit = computed(() => nin.value.length === 11 && !isSubmitting.value);

const money = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const submit = () => {
    if (nin.value.length !== 11) { formError.value = 'NIN must be exactly 11 characters'; return; }
    formError.value = '';
    isSubmitting.value = true;
    router.post(route('nin.validation.store'), { nin: nin.value }, {
        preserveScroll: true,
        onSuccess: () => { nin.value = ''; isSubmitting.value = false; },
        onError: (errors) => { formError.value = errors.nin || errors.message || 'Failed to submit NIN'; isSubmitting.value = false; },
    });
};

const checkStatus = (tx) => {
    if (checkingStatus.value[tx.id]) return;
    checkingStatus.value[tx.id] = true;
    router.post(route('nin.validation.check', tx.id), {}, {
        preserveState: true, preserveScroll: true,
        onFinish: () => { checkingStatus.value[tx.id] = false; },
    });
};

const fetchTransactions = () => {
    router.get(route('nin.validation.index'), { search: search.value, status: statusFilter.value }, {
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

const canCheck = (status) => !['completed', 'validated', 'failed'].includes(status?.toLowerCase());

const transactionsList = computed(() => props.transactions?.data || []);
</script>

<template>
    <Head title="NIN Validation" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="NIN services"
                title="NIN validation"
                description="Validate a NIN against the official records. Results arrive asynchronously — check back on a row to refresh it."
            />

            <BalanceStrip :wallet="wallet" :price="price" />

            <!-- Submit -->
            <section class="card card-pad">
                <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Submit a NIN</h2>

                <div class="mt-4 space-y-3">
                    <Alert v-if="formError" tone="danger">{{ formError }}</Alert>
                    <Alert v-if="$page.props.flash?.success" tone="success">{{ $page.props.flash.success }}</Alert>
                </div>

                <form @submit.prevent="submit" class="mt-4 max-w-lg space-y-4">
                    <div>
                        <label for="nin" class="eyebrow">NIN number</label>
                        <input
                            id="nin"
                            v-model="nin"
                            type="text"
                            inputmode="numeric"
                            maxlength="11"
                            placeholder="11-digit NIN"
                            :disabled="isSubmitting"
                            class="mt-1.5 block w-full font-mono text-lg"
                        />
                        <p class="mt-1 font-mono text-xs text-ink-400 dark:text-ink-500">{{ nin.length }}/11</p>
                    </div>

                    <button type="submit" :disabled="!canSubmit" class="btn btn-primary">
                        <svg v-if="isSubmitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ isSubmitting ? 'Submitting…' : `Validate — ${money(price)}` }}
                    </button>
                </form>
            </section>

            <!-- History -->
            <section class="card overflow-hidden">
                <div class="flex flex-col gap-3 border-b rule px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Validation history</h2>

                    <div class="flex flex-wrap gap-2">
                        <input
                            v-model="search"
                            @input="debouncedSearch"
                            type="search"
                            placeholder="Search…"
                            aria-label="Search validations"
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
                                    <th>NIN</th>
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
                                    <td><StatusPill :status="tx.status" /></td>
                                    <td class="max-w-[180px] truncate text-ink-600 dark:text-ink-300" :title="tx.comment">
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

                    <Pagination :paginator="transactions" label="validations" />
                </template>

                <EmptyState
                    v-else
                    title="No validations yet"
                    description="Submit a NIN above and each attempt will be listed here with its result."
                    icon="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
