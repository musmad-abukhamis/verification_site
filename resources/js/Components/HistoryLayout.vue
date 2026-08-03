<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import StatTile from '@/Components/StatTile.vue';
import TabNav from '@/Components/TabNav.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * The frame every history screen shares: header, tab strip, the active tab's
 * figures, one filter bar, and the table the page supplies.
 *
 * Filters live here rather than in each page because they mean the same thing
 * everywhere — a text search, a status, a date window — and each history page
 * would otherwise re-implement the same debounce and the same round trip.
 */
const props = defineProps({
    eyebrow: { type: String, default: 'History' },
    title: { type: String, required: true },
    description: { type: String, default: null },

    /** [{ key, label, count }] */
    tabs: { type: Array, required: true },
    active: { type: String, required: true },
    routeName: { type: String, required: true },

    /** [{ label, value, money?, tone? }] for the active tab. */
    stats: { type: Array, default: () => [] },

    /** Laravel paginator for the active tab. */
    records: { type: Object, required: true },
    /** Noun for the pagination count line. */
    label: { type: String, default: 'records' },

    filters: { type: Object, default: () => ({}) },
    searchPlaceholder: { type: String, default: 'Search…' },
    /** [{ value, label }]; omit to hide the status filter. */
    statuses: { type: Array, default: () => [] },

    emptyTitle: { type: String, default: 'Nothing here yet' },
    emptyDescription: { type: String, default: null },
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? 'all');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const reload = () => {
    router.get(
        route(props.routeName),
        {
            // The tab is part of the query, so a filtered view stays on the
            // tab it was filtered on.
            tab: props.active,
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(reload, 350);
});
watch([status, from, to], reload);

const clearFilters = () => {
    search.value = '';
    status.value = 'all';
    from.value = '';
    to.value = '';
};

const hasFilters = () => Boolean(search.value || status.value !== 'all' || from.value || to.value);
</script>

<template>
    <div class="space-y-6">
        <PageHeader :eyebrow="eyebrow" :title="title" :description="description">
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>
        </PageHeader>

        <TabNav :tabs="tabs" :active="active" :route-name="routeName" />

        <div v-if="stats.length" class="grid gap-4 sm:grid-cols-2" :class="stats.length > 3 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'">
            <StatTile
                v-for="stat in stats"
                :key="stat.label"
                :label="stat.label"
                :value="stat.money ? formatCurrency(stat.value) : stat.value"
                :tone="stat.tone ?? 'neutral'"
            />
        </div>

        <div class="card card-pad">
            <div class="flex flex-col gap-3 lg:flex-row">
                <input
                    v-model="search"
                    type="search"
                    :placeholder="searchPlaceholder"
                    class="flex-1"
                    aria-label="Search records"
                />

                <select v-if="statuses.length" v-model="status" aria-label="Filter by status">
                    <option value="all">All statuses</option>
                    <option v-for="option in statuses" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <div class="flex items-center gap-2">
                    <input v-model="from" type="date" aria-label="From date" class="min-w-0 flex-1" />
                    <span class="text-sm text-ink-400" aria-hidden="true">→</span>
                    <input v-model="to" type="date" aria-label="To date" class="min-w-0 flex-1" />
                </div>

                <button v-if="hasFilters()" type="button" class="btn btn-ghost btn-sm" @click="clearFilters">
                    Clear
                </button>
            </div>
        </div>

        <section class="card overflow-hidden">
            <template v-if="records.data.length">
                <slot />
                <Pagination :paginator="records" :label="label" />
            </template>

            <EmptyState
                v-else
                :title="hasFilters() ? 'No matching records' : emptyTitle"
                :description="hasFilters()
                    ? 'Nothing matches these filters. Try clearing the search or widening the dates.'
                    : emptyDescription"
                icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
        </section>
    </div>
</template>
