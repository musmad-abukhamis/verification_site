<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import HistoryLayout from '@/Components/HistoryLayout.vue';
import HistoryUserCell from '@/Components/HistoryUserCell.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * NIN history, all users.
 *
 * Verification and Validation are separate tabs because they are separate
 * services: a verification answers in seconds, a validation is filed with NIMC
 * and takes days. They share one database table, which is exactly why the old
 * Verification Logs screen listed them together — and why it could not answer
 * "what did this person pay for".
 */
const props = defineProps({
    tab: String,
    tabs: Array,
    records: Object,
    stats: Array,
    filters: Object,
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const COPY = {
    verify: {
        description:
            'Instant NIN lookups across every user — by NIN, phone or demographics. Each row shows what it charged, including the fee on a lookup the provider confirmed as “no record”.',
        placeholder: 'Search by user, NIN or message…',
        statuses: [
            { value: 'completed', label: 'Completed' },
            { value: 'failed', label: 'Failed' },
        ],
        label: 'verifications',
        emptyTitle: 'No verifications yet',
        emptyDescription: 'NIN lookups run on the platform will be listed here with the fee and the result.',
    },
    validation: {
        description:
            'NIN validations filed with NIMC, across every user. To recheck, override or refund one, open it on the Validations management screen.',
        placeholder: 'Search by user, NIN or provider reference…',
        statuses: [
            { value: 'processing', label: 'Processing' },
            { value: 'completed', label: 'Completed' },
            { value: 'failed', label: 'Failed' },
        ],
        label: 'validations',
        emptyTitle: 'No validations filed',
        emptyDescription: 'Validations users submit will appear here until NIMC returns a result.',
        manage: 'admin.nin-validations.index',
    },
    ipe: {
        description:
            'IPE clearances submitted across every user, keyed on the 15-character tracking ID. Clearance usually takes 30 minutes to 3 hours.',
        placeholder: 'Search by user, tracking ID or message…',
        statuses: [
            { value: 'processing', label: 'Processing' },
            { value: 'completed', label: 'Completed' },
            { value: 'failed', label: 'Failed' },
        ],
        label: 'clearances',
        emptyTitle: 'No IPE clearances yet',
        emptyDescription: 'Tracking IDs submitted for clearance will be listed here.',
        manage: 'admin.nin-ipe.index',
    },
    slip: {
        description:
            'The NIN side of the NINDetails ledger — verification slips and slip downloads, with the slip type bought and the channel it came through.',
        placeholder: 'Search by user, NIN, name or slip type…',
        statuses: [
            { value: 'success', label: 'Success' },
            { value: 'fail', label: 'Failed' },
        ],
        label: 'slips',
        emptyTitle: 'No slips yet',
        emptyDescription: 'Slips generated or downloaded by users will be listed here with the price.',
    },
};

const copy = computed(() => COPY[props.tab] ?? COPY.verify);
const isAsyncJob = computed(() => props.tab === 'validation' || props.tab === 'ipe');
</script>

<template>
    <Head title="NIN History" />

    <AdminLayout>
        <HistoryLayout
            eyebrow="History"
            title="NIN history"
            :description="copy.description"
            :tabs="tabs"
            :active="tab"
            route-name="admin.history.nin"
            :stats="stats"
            :records="records"
            :label="copy.label"
            :filters="filters"
            :search-placeholder="copy.placeholder"
            :statuses="copy.statuses"
            :empty-title="copy.emptyTitle"
            :empty-description="copy.emptyDescription"
        >
            <template v-if="copy.manage" #actions>
                <Link :href="route(copy.manage)" class="btn btn-secondary btn-sm">Manage</Link>
            </template>

            <!-- ============================ Verifications =================== -->
            <template v-if="tab === 'verify'">
                <div class="scroll-slim hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>User</th>
                                <th>Reference</th>
                                <th>NIN / ID</th>
                                <th>Method</th>
                                <th>Charged</th>
                                <th>New balance</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td><HistoryUserCell :user="r.user" /></td>
                                <td class="font-mono text-ink-500 dark:text-ink-400">{{ r.reference }}</td>
                                <td class="font-mono text-ink-950 dark:text-white">{{ r.identity || '—' }}</td>
                                <td class="text-ink-600 dark:text-ink-300">{{ r.method }}</td>
                                <td class="font-mono font-semibold text-ink-950 dark:text-white">
                                    {{ formatCurrency(r.amount) }}
                                    <!-- A failed lookup that cost nothing is worth saying out
                                         loud: it is the most common support question. -->
                                    <span
                                        v-if="r.status === 'failed' && !r.charged"
                                        class="mt-0.5 block text-2xs font-normal text-ink-500 dark:text-ink-400"
                                    >
                                        not billed
                                    </span>
                                </td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ formatCurrency(r.new_balance) }}</td>
                                <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ r.date }}</td>
                                <td><StatusPill :status="r.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y rule lg:hidden">
                    <article v-for="r in records.data" :key="r.id" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-base font-semibold text-ink-950 dark:text-white">
                                    {{ r.identity || '—' }}
                                </p>
                                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ r.method }} · {{ r.date }}</p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>

                        <HistoryUserCell :user="r.user" />

                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="eyebrow">Charged</p>
                                <p class="mt-0.5 font-mono text-sm font-semibold text-ink-900 dark:text-ink-100">
                                    {{ formatCurrency(r.amount) }}
                                    <span v-if="r.status === 'failed' && !r.charged" class="font-normal text-ink-500">· not billed</span>
                                </p>
                            </div>
                            <p class="font-mono text-xs text-ink-500 dark:text-ink-400">
                                {{ formatCurrency(r.old_balance) }} → {{ formatCurrency(r.new_balance) }}
                            </p>
                        </div>

                        <p v-if="r.comment" class="text-xs text-ink-500 dark:text-ink-400">{{ r.comment }}</p>
                    </article>
                </div>
            </template>

            <!-- ===================== Validations / IPE ====================== -->
            <template v-else-if="isAsyncJob">
                <div class="scroll-slim hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>User</th>
                                <th>Reference</th>
                                <th>{{ tab === 'ipe' ? 'Tracking ID' : 'NIN' }}</th>
                                <th>Price</th>
                                <th>Refunded</th>
                                <th>Provider ref.</th>
                                <th>Submitted</th>
                                <th>Last update</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td><HistoryUserCell :user="r.user" /></td>
                                <td class="font-mono text-ink-500 dark:text-ink-400">{{ r.reference }}</td>
                                <td class="font-mono text-ink-950 dark:text-white">{{ r.identity || '—' }}</td>
                                <td class="font-mono font-semibold text-ink-950 dark:text-white">{{ formatCurrency(r.amount) }}</td>
                                <td>
                                    <StatusPill v-if="r.refunded" status="refund" :label="formatCurrency(r.refund_amount)" />
                                    <span v-else class="text-ink-400 dark:text-ink-500">—</span>
                                </td>
                                <td class="font-mono text-xs text-ink-500 dark:text-ink-400">{{ r.provider_reference || '—' }}</td>
                                <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ r.date }}</td>
                                <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ r.updated }}</td>
                                <td><StatusPill :status="r.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y rule lg:hidden">
                    <article v-for="r in records.data" :key="r.id" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="eyebrow">{{ r.identity_label }}</p>
                                <p class="mt-0.5 font-mono text-base font-semibold text-ink-950 dark:text-white">
                                    {{ r.identity || '—' }}
                                </p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>

                        <HistoryUserCell :user="r.user" />

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="eyebrow">Price</p>
                                <p class="mt-0.5 font-mono text-sm text-ink-900 dark:text-ink-100">{{ formatCurrency(r.amount) }}</p>
                            </div>
                            <div>
                                <p class="eyebrow">Refunded</p>
                                <p class="mt-0.5 font-mono text-sm text-ink-900 dark:text-ink-100">
                                    {{ r.refunded ? formatCurrency(r.refund_amount) : '—' }}
                                </p>
                            </div>
                        </div>

                        <p v-if="r.comment" class="text-xs text-ink-500 dark:text-ink-400">{{ r.comment }}</p>
                        <p class="text-xs text-ink-400 dark:text-ink-500">Submitted {{ r.date }}</p>
                    </article>
                </div>
            </template>

            <!-- ================================ Slips ======================= -->
            <template v-else>
                <div class="scroll-slim hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>User</th>
                                <th>NIN / ID</th>
                                <th>Name</th>
                                <th>Slip</th>
                                <th>Channel</th>
                                <th>Price</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td><HistoryUserCell :user="r.user" /></td>
                                <td class="font-mono text-ink-950 dark:text-white">{{ r.identity || '—' }}</td>
                                <td class="text-ink-700 dark:text-ink-200">{{ r.name }}</td>
                                <td class="capitalize text-ink-600 dark:text-ink-300">{{ r.slip_type || '—' }}</td>
                                <td class="capitalize text-ink-500 dark:text-ink-400">{{ r.channel || '—' }}</td>
                                <td class="font-mono font-semibold text-ink-950 dark:text-white">{{ formatCurrency(r.amount) }}</td>
                                <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ r.date }}</td>
                                <td><StatusPill :status="r.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y rule lg:hidden">
                    <article v-for="r in records.data" :key="r.id" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-base font-semibold text-ink-950 dark:text-white">{{ r.identity || '—' }}</p>
                                <p class="mt-0.5 truncate text-xs text-ink-500 dark:text-ink-400">{{ r.name }}</p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>

                        <HistoryUserCell :user="r.user" />

                        <div class="flex items-end justify-between gap-3">
                            <p class="font-mono text-sm font-semibold text-ink-900 dark:text-ink-100">{{ formatCurrency(r.amount) }}</p>
                            <p class="text-xs capitalize text-ink-500 dark:text-ink-400">{{ r.slip_type }} · {{ r.date }}</p>
                        </div>
                    </article>
                </div>
            </template>
        </HistoryLayout>
    </AdminLayout>
</template>
