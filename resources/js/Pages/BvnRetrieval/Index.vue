<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BalanceStrip from '@/Components/BalanceStrip.vue';
import Alert from '@/Components/Alert.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
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

const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(fetchRequests, 400);
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 })
        .format(Number(amount ?? 0));
</script>

<template>
    <Head title="BVN Retrieval" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="BVN services"
                title="BVN retrieval"
                description="Submit a retrieval request with your Ticket ID (BMS ID). Track it here and read the BVN once it's ready."
            />

            <BalanceStrip :wallet="wallet" :price="price" />

            <!-- Request -->
            <!-- The card fills the column; only the fields are held to a
                 readable measure, so there's no gutter beside the sidebar. -->
            <section class="card card-pad">
                <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">New retrieval request</h2>

                <div class="mt-4 space-y-3">
                    <Alert v-if="form.errors.message" tone="danger">{{ form.errors.message }}</Alert>
                    <Alert v-if="$page.props.flash?.success" tone="success">{{ $page.props.flash.success }}</Alert>
                </div>

                <!-- Terms gate: the fee is non-refundable, so the terms are read
                     before the form exists, not alongside it. -->
                <div v-if="!termsAccepted" class="mt-4 max-w-2xl space-y-4">
                    <Alert tone="warning">
                        You must read and accept the terms and conditions before submitting a BVN retrieval request.
                    </Alert>

                    <div class="scroll-slim max-h-48 space-y-2 overflow-y-auto rounded-lg border rule p-4 text-sm text-ink-600 dark:text-ink-300">
                        <p class="font-display font-semibold text-ink-900 dark:text-ink-100">Terms &amp; conditions</p>
                        <p>By submitting a BVN retrieval request you confirm that the Ticket ID (BMS ID) provided belongs to you and is accurate.</p>
                        <p>The retrieval fee is non-refundable once a request has been processed. Processing times vary and the retrieved BVN will be made available on this page once ready.</p>
                        <p>You agree not to use this service for any fraudulent or unlawful purpose.</p>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input type="checkbox" v-model="agreeChecked" />
                        I have read and accept the terms and conditions
                    </label>

                    <button @click="termsAccepted = true" :disabled="!agreeChecked" class="btn btn-primary">
                        Accept &amp; continue
                    </button>
                </div>

                <!-- Form -->
                <form v-else @submit.prevent="submit" class="mt-4 max-w-2xl space-y-4">
                    <div>
                        <label for="bmsId" class="eyebrow">Ticket ID (BMS ID) *</label>
                        <input
                            id="bmsId"
                            v-model="form.bmsId"
                            type="text"
                            maxlength="8"
                            inputmode="numeric"
                            placeholder="8-digit Ticket ID"
                            class="mt-1.5 block w-full font-mono text-lg"
                        />
                        <p v-if="form.errors.bmsId" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">
                            {{ form.errors.bmsId }}
                        </p>
                    </div>

                    <Alert tone="info">Please provide your 8-digit Ticket ID (BMS ID) for verification.</Alert>

                    <button type="submit" :disabled="form.processing" class="btn btn-primary btn-lg w-full">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ form.processing ? 'Submitting…' : `Submit request — ${formatCurrency(price)}` }}
                    </button>
                </form>
            </section>

            <!-- Requests -->
            <section class="card overflow-hidden">
                <div class="flex flex-col gap-3 border-b rule px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">My retrieval requests</h2>
                    <input
                        v-model="search"
                        @input="debouncedSearch"
                        type="search"
                        placeholder="Search ticket ID, NIN, status…"
                        aria-label="Search requests"
                        class="w-full text-sm md:max-w-xs"
                    />
                </div>

                <template v-if="requests.data.length">
                    <div class="scroll-slim overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="t-head">
                                <tr>
                                    <th>BMS ID</th>
                                    <th>BVN</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Updated</th>
                                    <th><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y rule">
                                <tr v-for="r in requests.data" :key="r.id" class="t-row">
                                    <td class="font-mono font-semibold text-ink-950 dark:text-white">{{ r.ticketId2 || '—' }}</td>
                                    <td class="font-mono" :class="r.bvn ? 'font-semibold text-ink-950 dark:text-white' : 'text-ink-400 dark:text-ink-500'">
                                        {{ r.bvn || 'Not yet provided' }}
                                    </td>
                                    <td><StatusPill :status="r.status || 'pending'" /></td>
                                    <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ formatDate(r.created_at) }}</td>
                                    <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ formatDate(r.updated_at) }}</td>
                                    <td class="text-right">
                                        <button @click="selected = r" class="btn btn-secondary btn-sm">View</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination :paginator="requests" label="requests" />
                </template>

                <EmptyState
                    v-else
                    title="No retrieval requests"
                    description="Submit a Ticket ID above and you'll be able to track the request from here."
                    icon="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                />
            </section>
        </div>

        <!-- Detail dialog. Uses the shared Modal so escape, scroll-lock and
             backdrop behaviour match the rest of the app. -->
        <Modal :show="!!selected" max-width="lg" @close="selected = null">
            <div v-if="selected" class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="font-display text-lg font-semibold text-ink-950 dark:text-white">Request details</h2>
                    <StatusPill :status="selected.status || 'pending'" />
                </div>

                <dl class="mt-5 divide-y rule text-sm">
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-ink-500 dark:text-ink-400">BMS ID</dt>
                        <dd class="font-mono font-semibold text-ink-950 dark:text-white">{{ selected.ticketId2 || '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-ink-500 dark:text-ink-400">NIN</dt>
                        <dd class="font-mono text-ink-900 dark:text-ink-100">{{ selected.nin || '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-ink-500 dark:text-ink-400">BVN</dt>
                        <dd class="font-mono" :class="selected.bvn ? 'font-semibold text-ink-950 dark:text-white' : 'text-ink-400 dark:text-ink-500'">
                            {{ selected.bvn || 'Not yet provided' }}
                        </dd>
                    </div>
                    <div class="py-2">
                        <dt class="text-ink-500 dark:text-ink-400">Comment</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-ink-900 dark:text-ink-100">{{ selected.comment || 'No comments' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 flex justify-end">
                    <button type="button" class="btn btn-secondary" @click="selected = null">Close</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
