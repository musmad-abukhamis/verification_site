<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    records: Object,
    filters: Object,
    hasSearched: Boolean,
});

const searchType = ref(props.filters?.searchType || 'ticket_id');
const query = ref(props.filters?.query || '');

const isValid = computed(() => query.value.trim().length >= 6);

const search = () => {
    if (!isValid.value) return;
    router.get(route('bvn-records.index'), {
        searchType: searchType.value,
        query: query.value.trim(),
    }, { preserveState: true, preserveScroll: true });
};

/* -- expandable comments ------------------------------------------------- */

// The comment holds the full validation message ("...Bad photo quality;
// BatchID: 4245709..."), which is the part users actually need when an
// enrolment failed — but it is far too long to sit untruncated in a table.
const expanded = ref(new Set());

const isExpanded = (id) => expanded.value.has(id);

const toggle = (id) => {
    if (expanded.value.has(id)) {
        expanded.value.delete(id);
    } else {
        expanded.value.add(id);
    }
};

const allExpanded = computed(() =>
    (props.records?.data?.length ?? 0) > 0 &&
    props.records.data.every((r) => !r.comment || expanded.value.has(r.ticket_id))
);

const toggleAll = () => {
    if (allExpanded.value) {
        expanded.value.clear();
    } else {
        props.records.data.forEach((r) => r.comment && expanded.value.add(r.ticket_id));
    }
};

// A new search or page of results is a fresh set of rows — carrying expansion
// state across them would leave arbitrary rows open.
watch(() => props.records, () => expanded.value.clear());
</script>

<template>
    <Head title="Search BVN Records" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="BVN services"
                title="Search BVN records"
                description="Look up enrolment records by Ticket ID or Agent ID."
            />

            <!-- Search -->
            <div class="card card-pad">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <select v-model="searchType" aria-label="Search by" class="sm:w-44">
                        <option value="ticket_id">Ticket ID</option>
                        <option value="enroller_id">Agent ID</option>
                    </select>

                    <div class="flex-1">
                        <input
                            v-model="query"
                            type="search"
                            minlength="6"
                            @keyup.enter="search"
                            :placeholder="`Enter ${searchType === 'ticket_id' ? 'Ticket ID' : 'Agent ID'} (min. 6 characters)`"
                            aria-label="Search term"
                            class="block w-full font-mono"
                        />
                        <p v-if="query.trim() && !isValid" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">
                            Please enter at least 6 characters.
                        </p>
                    </div>

                    <button @click="search" :disabled="!isValid" class="btn btn-primary">Search</button>
                </div>
            </div>

            <!-- Nothing searched yet -->
            <div v-if="!hasSearched" class="card">
                <EmptyState
                    title="Search for an enrolment record"
                    description="Enter a Ticket ID or an Agent ID above and matching records will be listed here."
                    icon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </div>

            <!-- Results -->
            <section v-else class="card overflow-hidden">
                <template v-if="records.data.length">
                    <div class="scroll-slim overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="t-head">
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>BVN</th>
                                    <th>Agent name</th>
                                    <th>Agent ID</th>
                                    <th>Status</th>
                                    <th>
                                        <span class="inline-flex items-center gap-2">
                                            Comment
                                            <button
                                                type="button"
                                                @click="toggleAll"
                                                class="text-[11px] font-normal normal-case tracking-normal text-brand-700 hover:underline dark:text-brand-300"
                                            >
                                                {{ allExpanded ? 'Collapse all' : 'Expand all' }}
                                            </button>
                                        </span>
                                    </th>
                                    <th>Date enrolled</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y rule">
                                <tr v-for="r in records.data" :key="r.ticket_id" class="t-row">
                                    <td class="align-top font-mono font-semibold text-ink-950 dark:text-white">{{ r.ticket_id }}</td>
                                    <td class="align-top font-mono text-ink-700 dark:text-ink-200">{{ r.bvn }}</td>
                                    <td class="align-top text-ink-700 dark:text-ink-200">{{ r.enrollee_name }}</td>
                                    <td class="align-top font-mono text-ink-700 dark:text-ink-200">{{ r.enroller_id }}</td>
                                    <td class="align-top text-ink-700 dark:text-ink-200">{{ r.status }}</td>
                                    <td class="max-w-[280px] align-top text-ink-600 dark:text-ink-300">
                                        <button
                                            v-if="r.comment"
                                            type="button"
                                            @click="toggle(r.ticket_id)"
                                            :aria-expanded="isExpanded(r.ticket_id)"
                                            :title="isExpanded(r.ticket_id) ? 'Click to collapse' : 'Click to see the full message'"
                                            class="flex w-full items-start gap-1.5 rounded text-left hover:text-ink-900 dark:hover:text-ink-100"
                                        >
                                            <span
                                                class="min-w-0 flex-1"
                                                :class="isExpanded(r.ticket_id) ? 'whitespace-pre-wrap break-words' : 'truncate'"
                                            >{{ r.comment }}</span>
                                            <svg
                                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-400 transition-transform"
                                                :class="{ 'rotate-180': isExpanded(r.ticket_id) }"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                        <span v-else class="text-ink-400 dark:text-ink-500">—</span>
                                    </td>
                                    <td class="whitespace-nowrap align-top text-ink-500 dark:text-ink-400">{{ r.date_enrolled }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination :paginator="records" label="records" />
                </template>

                <EmptyState
                    v-else
                    title="No records found"
                    description="Nothing matches that Ticket ID or Agent ID. Check the value and try again."
                    icon="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
