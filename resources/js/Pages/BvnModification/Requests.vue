<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    requests: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const serviceType = ref(props.filters?.serviceType || 'all');
const dateFrom = ref(props.filters?.dateFrom || '');
const dateTo = ref(props.filters?.dateTo || '');
const showAdvanced = ref(false);

const applyFilters = () => {
    router.get(route('bvn-modification.requests'), {
        search: search.value,
        status: status.value,
        serviceType: serviceType.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value,
    }, { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    search.value = '';
    status.value = 'all';
    serviceType.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' });
};

const money = (amount) =>
    amount === null || amount === undefined
        ? '—'
        : new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount));
</script>

<template>
    <Head title="My BVN Modification Requests" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="BVN services"
                title="My modification requests"
                description="Track the requests you've submitted and what came back."
            >
                <template #actions>
                    <Link :href="route('bvn-modification.index')" class="btn btn-primary">New request</Link>
                </template>
            </PageHeader>

            <!-- Filters -->
            <div class="card card-pad">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label for="search" class="eyebrow">Search</label>
                        <input
                            id="search"
                            v-model="search"
                            type="search"
                            placeholder="BVN or NIN…"
                            @keyup.enter="applyFilters"
                            class="mt-1.5 block w-full font-mono"
                        />
                    </div>

                    <div>
                        <label for="status" class="eyebrow">Status</label>
                        <select id="status" v-model="status" @change="applyFilters" class="mt-1.5 block w-full">
                            <option value="all">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="modified">Modified</option>
                            <option value="rejected">Rejected</option>
                            <option value="picked">Picked</option>
                        </select>
                    </div>

                    <div>
                        <label for="serviceType" class="eyebrow">Service type</label>
                        <select id="serviceType" v-model="serviceType" @change="applyFilters" class="mt-1.5 block w-full">
                            <option value="all">All types</option>
                            <option value="modify-name">Name modification</option>
                            <option value="modify-dob">DOB modification</option>
                            <option value="modify-name-dob">Name &amp; DOB modification</option>
                            <option value="modify-phone">Phone modification</option>
                            <option value="modify-name-dob-phone">Name, DOB &amp; phone modification</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button @click="applyFilters" class="btn btn-primary flex-1">Search</button>
                        <button
                            @click="showAdvanced = !showAdvanced"
                            :aria-expanded="showAdvanced"
                            class="btn btn-secondary"
                        >
                            Dates
                        </button>
                    </div>
                </div>

                <div v-if="showAdvanced" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border rule bg-ink-50 p-4 dark:bg-ink-950/40 md:grid-cols-3">
                    <div>
                        <label for="dateFrom" class="eyebrow">Date from</label>
                        <input id="dateFrom" v-model="dateFrom" type="date" @change="applyFilters" class="mt-1.5 block w-full" />
                    </div>
                    <div>
                        <label for="dateTo" class="eyebrow">Date to</label>
                        <input id="dateTo" v-model="dateTo" type="date" @change="applyFilters" class="mt-1.5 block w-full" />
                    </div>
                    <div class="flex items-end">
                        <button @click="clearFilters" class="btn btn-secondary w-full">Clear all filters</button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <section class="card overflow-hidden">
                <template v-if="requests.data.length">
                    <div class="scroll-slim overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="t-head">
                                <tr>
                                    <th>BVN</th>
                                    <th>Service type</th>
                                    <th>Comment</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Prev. balance</th>
                                    <th>New balance</th>
                                    <th><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y rule">
                                <tr v-for="r in requests.data" :key="r.id" class="t-row">
                                    <td class="font-mono font-semibold text-ink-950 dark:text-white">{{ r.bvn }}</td>
                                    <td class="text-ink-700 dark:text-ink-200">{{ r.service_label }}</td>
                                    <td class="max-w-[160px] truncate text-ink-600 dark:text-ink-300" :title="r.comment">
                                        {{ r.comment || '—' }}
                                    </td>
                                    <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ formatDate(r.created_at) }}</td>
                                    <td><StatusPill :status="r.status" /></td>
                                    <td class="font-mono text-ink-600 dark:text-ink-300">{{ money(r.old_balance) }}</td>
                                    <td class="font-mono text-ink-600 dark:text-ink-300">{{ money(r.new_balance) }}</td>
                                    <td class="text-right">
                                        <Link :href="route('bvn-modification.show', r.id)" class="link text-sm">View</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination :paginator="requests" label="requests" />
                </template>

                <EmptyState
                    v-else
                    title="No modification requests"
                    description="Nothing matches these filters yet. Submit a request and you can track it from here."
                    icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                >
                    <Link :href="route('bvn-modification.index')" class="btn btn-primary btn-sm">New request</Link>
                </EmptyState>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
