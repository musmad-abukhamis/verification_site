<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

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

const goToPage = (url) => {
    if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

// Laravel paginator links: keep First/prev, numbered pages, next/Last (skip the
// text prev/next labels, which we render separately).
const numberedLinks = computed(() => {
    if (!props.records?.links) return [];
    return props.records.links.filter((l) => !['&laquo; Previous', 'Next &raquo;'].includes(l.label));
});
</script>

<template>
    <Head title="Search BVN Records" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Search BVN Records</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Look up enrolment records by Ticket ID or Agent ID.</p>
            </div>

            <!-- Search form -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
                <div class="flex flex-col sm:flex-row gap-3">
                    <select v-model="searchType"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:w-44">
                        <option value="ticket_id">Ticket ID</option>
                        <option value="enroller_id">Agent ID</option>
                    </select>
                    <div class="flex-1">
                        <input v-model="query" type="text" minlength="6" @keyup.enter="search"
                            :placeholder="`Enter ${searchType === 'ticket_id' ? 'Ticket ID' : 'Agent ID'} (min. 6 characters)`"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        <p v-if="query.trim() && !isValid" class="mt-1 text-xs text-red-500">Please enter at least 6 characters.</p>
                    </div>
                    <button @click="search" :disabled="!isValid"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap">
                        Search
                    </button>
                </div>
            </div>

            <!-- Empty state (no search yet) -->
            <div v-if="!hasSearched" class="bg-white dark:bg-slate-800 rounded-xl shadow py-20 text-center">
                <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <p class="text-lg font-medium text-gray-600 dark:text-gray-400">Enter a Ticket ID or Agent ID to search for BVN records.</p>
            </div>

            <!-- Results -->
            <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ticket ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">BVN</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Agent Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Agent ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Comment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date Enrolled</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="r in records.data" :key="r.ticket_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ r.ticket_id }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ r.bvn }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ r.enrollee_name }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{{ r.enroller_id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ r.status }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 max-w-[200px] truncate">{{ r.comment }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ r.date_enrolled }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="records.data.length === 0" class="py-10 text-center text-gray-500 dark:text-gray-400">No records found for the search criteria.</div>

                <!-- Pagination -->
                <div v-if="records.total > records.per_page" class="p-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-center gap-1">
                    <button @click="goToPage(records.prev_page_url)" :disabled="!records.prev_page_url"
                        class="px-3 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 disabled:opacity-50">Prev</button>
                    <button v-for="link in numberedLinks" :key="link.label"
                        @click="goToPage(link.url)" :disabled="!link.url"
                        :class="['px-3 py-1.5 text-sm rounded-md border',
                            link.active ? 'bg-indigo-600 text-white border-indigo-700' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700',
                            !link.url ? 'opacity-50 cursor-default' : '']"
                        v-html="link.label"></button>
                    <button @click="goToPage(records.next_page_url)" :disabled="!records.next_page_url"
                        class="px-3 py-1.5 text-sm rounded-md border border-gray-300 dark:border-gray-600 disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
