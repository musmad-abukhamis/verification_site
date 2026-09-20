<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import HistoryLayout from '@/Components/HistoryLayout.vue';
import HistoryUserCell from '@/Components/HistoryUserCell.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * BVN history, all users — one tab per BVN service, because a lookup, a
 * modification request, an onboarding registration and a retrieval are four
 * different things with four different turnarounds.
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

const PENDING_STATUSES = [
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'completed', label: 'Completed' },
    { value: 'rejected', label: 'Rejected' },
];

const COPY = {
    verify: {
        description:
            'Every BVN lookup and search on the platform, with the slip type bought and what each one charged.',
        placeholder: 'Search by user, BVN or name…',
        statuses: [
            { value: 'success', label: 'Success' },
            { value: 'fail', label: 'Failed' },
        ],
        label: 'verifications',
        emptyTitle: 'No BVN verifications yet',
        emptyDescription: 'BVN lookups run on the platform will be listed here with the fee and the result.',
    },
    modification: {
        description:
            'BVN modification requests and where each one has got to. To move one along, open it on the Modifications management screen.',
        placeholder: 'Search by user, BVN, NIN or message…',
        statuses: PENDING_STATUSES,
        label: 'requests',
        emptyTitle: 'No modification requests',
        emptyDescription: 'Name, DOB and phone modification requests users submit will be tracked here.',
        manage: 'admin.bvn-modifications.index',
    },
    onboarding: {
        description: 'BVN SDK onboarding registrations submitted for agents, across every user.',
        placeholder: 'Search by user, name, email or phone…',
        statuses: PENDING_STATUSES,
        label: 'registrations',
        emptyTitle: 'No onboarding registrations',
        emptyDescription: 'Agents registered through the onboarding wizard will be listed here.',
        manage: 'admin.bvn-sdk-forms.index',
    },
    retrieval: {
        description: 'BVN retrieval requests filed with a Ticket / BMS ID, and the BVN returned against each one.',
        placeholder: 'Search by user, ticket, batch, NIN or BVN…',
        statuses: PENDING_STATUSES,
        label: 'requests',
        emptyTitle: 'No retrieval requests',
        emptyDescription: 'Retrieval requests users file will be listed here until an agent returns the BVN.',
        manage: 'admin.bvn-retrievals.index',
    },
};

const copy = computed(() => COPY[props.tab] ?? COPY.verify);
</script>

<template>
    <Head title="BVN History" />

    <AdminLayout>
        <HistoryLayout
            eyebrow="History"
            title="BVN history"
            :description="copy.description"
            :tabs="tabs"
            :active="tab"
            route-name="admin.history.bvn"
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
                                <th>BVN</th>
                                <th>Name</th>
                                <th>Slip</th>
                                <th>Charged</th>
                                <th>New balance</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td><HistoryUserCell :user="r.user" /></td>
                                <td class="font-mono text-ink-950 dark:text-white">{{ r.identity }}</td>
                                <td class="text-ink-700 dark:text-ink-200">{{ r.name }}</td>
                                <td class="capitalize text-ink-600 dark:text-ink-300">{{ r.slip_type }}</td>
                                <td class="font-mono font-semibold text-ink-950 dark:text-white">
                                    {{ formatCurrency(r.amount) }}
                                    <span
                                        v-if="r.status === 'fail' && !r.charged"
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
                                <p class="font-mono text-base font-semibold text-ink-950 dark:text-white">{{ r.identity }}</p>
                                <p class="mt-0.5 truncate text-xs text-ink-500 dark:text-ink-400">{{ r.name }}</p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>

                        <HistoryUserCell :user="r.user" />

                        <div class="flex items-end justify-between gap-3">
                            <p class="font-mono text-sm font-semibold text-ink-900 dark:text-ink-100">
                                {{ formatCurrency(r.amount) }}
                                <span v-if="r.status === 'fail' && !r.charged" class="font-normal text-ink-500">· not billed</span>
                            </p>
                            <p class="text-xs capitalize text-ink-500 dark:text-ink-400">{{ r.slip_type }} · {{ r.date }}</p>
                        </div>
                    </article>
                </div>
            </template>

            <!-- ============================ Modifications =================== -->
            <template v-else-if="tab === 'modification'">
                <div class="scroll-slim hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>User</th>
                                <th>BVN</th>
                                <th>NIN</th>
                                <th>Service</th>
                                <th>Charged</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td><HistoryUserCell :user="r.user" /></td>
                                <td class="font-mono text-ink-950 dark:text-white">{{ r.identity }}</td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ r.nin }}</td>
                                <td class="text-ink-700 dark:text-ink-200">{{ r.service_label }}</td>
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
                                <p class="font-mono text-base font-semibold text-ink-950 dark:text-white">{{ r.identity }}</p>
                                <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ r.service_label }}</p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>

                        <HistoryUserCell :user="r.user" />

                        <div class="flex items-end justify-between gap-3">
                            <p class="font-mono text-sm font-semibold text-ink-900 dark:text-ink-100">{{ formatCurrency(r.amount) }}</p>
                            <p class="text-xs text-ink-500 dark:text-ink-400">{{ r.date }}</p>
                        </div>
                        <p v-if="r.comment" class="text-xs text-ink-500 dark:text-ink-400">{{ r.comment }}</p>
                    </article>
                </div>
            </template>

            <!-- ============================== Onboarding ==================== -->
            <template v-else-if="tab === 'onboarding'">
                <div class="scroll-slim hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>User</th>
                                <th>Agent</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Location</th>
                                <th>Charged</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td><HistoryUserCell :user="r.user" /></td>
                                <td class="text-ink-950 dark:text-white">{{ r.name }}</td>
                                <td class="text-ink-600 dark:text-ink-300">{{ r.email }}</td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ r.phone }}</td>
                                <td class="text-ink-600 dark:text-ink-300">{{ r.location }}</td>
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
                                <p class="truncate text-base font-semibold text-ink-950 dark:text-white">{{ r.name }}</p>
                                <p class="mt-0.5 truncate text-xs text-ink-500 dark:text-ink-400">{{ r.email }}</p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>

                        <HistoryUserCell :user="r.user" />

                        <div class="flex items-end justify-between gap-3">
                            <p class="font-mono text-sm text-ink-700 dark:text-ink-200">{{ r.phone }}</p>
                            <p class="text-xs text-ink-500 dark:text-ink-400">{{ r.date }}</p>
                        </div>
                    </article>
                </div>
            </template>

            <!-- =============================== Retrievals =================== -->
            <template v-else>
                <div class="scroll-slim hidden overflow-x-auto lg:block">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>User</th>
                                <th>Ticket / Batch</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>BVN returned</th>
                                <th>Charged</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="r in records.data" :key="r.id" class="t-row">
                                <td><HistoryUserCell :user="r.user" /></td>
                                <td class="font-mono text-ink-950 dark:text-white">{{ r.ticket }}</td>
                                <td class="text-ink-700 dark:text-ink-200">{{ r.name }}</td>
                                <td class="capitalize text-ink-600 dark:text-ink-300">{{ r.retrieval_type }}</td>
                                <td class="font-mono text-ink-600 dark:text-ink-300">{{ r.bvn || '—' }}</td>
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
                                <p class="font-mono text-base font-semibold text-ink-950 dark:text-white">{{ r.ticket }}</p>
                                <p class="mt-0.5 truncate text-xs text-ink-500 dark:text-ink-400">{{ r.name }}</p>
                            </div>
                            <StatusPill :status="r.status" />
                        </div>

                        <HistoryUserCell :user="r.user" />

                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="eyebrow">BVN returned</p>
                                <p class="mt-0.5 font-mono text-sm text-ink-900 dark:text-ink-100">{{ r.bvn || '—' }}</p>
                            </div>
                            <p class="text-xs text-ink-500 dark:text-ink-400">{{ r.date }}</p>
                        </div>
                        <p v-if="r.comment" class="text-xs text-ink-500 dark:text-ink-400">{{ r.comment }}</p>
                    </article>
                </div>
            </template>
        </HistoryLayout>
    </AdminLayout>
</template>
