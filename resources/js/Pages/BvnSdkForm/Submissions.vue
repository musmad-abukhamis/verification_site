<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusPill from '@/Components/StatusPill.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    forms: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
let searchTimeout;

const fetchForms = () => {
    router.get(route('bvn-sdk-form.submissions'), { search: search.value }, {
        preserveState: true, preserveScroll: true, only: ['forms'],
    });
};

const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(fetchForms, 400);
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<template>
    <Head title="My BVN Onboarding Submissions" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="BVN services"
                title="My onboarding submissions"
                description="Track the BVN SDK registrations you've submitted."
            >
                <template #actions>
                    <Link :href="route('bvn-sdk-form.index')" class="btn btn-primary">New registration</Link>
                </template>
            </PageHeader>

            <section class="card overflow-hidden">
                <div class="border-b rule px-5 py-4">
                    <input
                        v-model="search"
                        @input="debouncedSearch"
                        type="search"
                        placeholder="Search name, email, phone, state, zone…"
                        aria-label="Search submissions"
                        class="w-full text-sm md:max-w-md"
                    />
                </div>

                <template v-if="forms.data.length">
                    <div class="scroll-slim overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="t-head">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>State</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y rule">
                                <tr v-for="f in forms.data" :key="f.id" class="t-row">
                                    <td class="font-semibold text-ink-900 dark:text-ink-100">{{ f.firstName }} {{ f.lastName }}</td>
                                    <td class="text-ink-600 dark:text-ink-300">{{ f.email }}</td>
                                    <td class="font-mono text-ink-600 dark:text-ink-300">{{ f.phoneNumber }}</td>
                                    <td class="text-ink-600 dark:text-ink-300">{{ f.stateOfResidence }}</td>
                                    <td><StatusPill :status="f.status" /></td>
                                    <td class="whitespace-nowrap text-ink-500 dark:text-ink-400">{{ formatDate(f.created_at) }}</td>
                                    <td class="text-right">
                                        <Link :href="route('bvn-sdk-form.show', f.id)" class="link text-sm">View</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination :paginator="forms" label="submissions" />
                </template>

                <EmptyState
                    v-else
                    title="No onboarding submissions yet"
                    description="Complete a registration and it will appear here with its review status."
                    icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                >
                    <Link :href="route('bvn-sdk-form.index')" class="btn btn-primary btn-sm">New registration</Link>
                </EmptyState>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
