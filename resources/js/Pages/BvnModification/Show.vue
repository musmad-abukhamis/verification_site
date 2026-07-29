<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';
import { formatDateOnly } from '@/utils/date';

defineProps({
    request: Object,
});

const formatDate = (date) => formatDateOnly(
    date,
    { year: 'numeric', month: 'long', day: 'numeric' },
    'en-NG',
    '—',
);

const money = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const fullName = (f, m, l) => [f, m, l].filter(Boolean).join(' ') || '—';
</script>

<template>
    <Head title="BVN Modification Request" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader eyebrow="BVN modification" title="Request details">
                <template #actions>
                    <Link :href="route('bvn-modification.requests')" class="btn btn-ghost">Back to requests</Link>
                    <a :href="route('bvn-modification.slip', request.id)" target="_blank" class="btn btn-secondary">
                        View NIN slip
                    </a>
                </template>
            </PageHeader>

            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b rule px-5 py-4">
                    <div>
                        <p class="eyebrow">Request ID</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-ink-900 dark:text-ink-100">{{ request.id }}</p>
                    </div>
                    <StatusPill :status="request.status" />
                </div>

                <div class="grid grid-cols-1 gap-8 p-5 md:grid-cols-2">
                    <!-- Request info -->
                    <div>
                        <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Request information</h2>

                        <dl class="mt-3 divide-y rule text-sm">
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-ink-500 dark:text-ink-400">BVN</dt>
                                <dd class="font-mono font-semibold text-ink-950 dark:text-white">{{ request.bvn }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-ink-500 dark:text-ink-400">NIN</dt>
                                <dd class="font-mono font-semibold text-ink-950 dark:text-white">{{ request.nin }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-ink-500 dark:text-ink-400">Service type</dt>
                                <dd class="text-right text-ink-900 dark:text-ink-100">{{ request.service_label }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-ink-500 dark:text-ink-400">Amount charged</dt>
                                <dd class="font-mono font-semibold text-ink-950 dark:text-white">{{ money(request.amount_charged) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-ink-500 dark:text-ink-400">Submitted on</dt>
                                <dd class="text-ink-900 dark:text-ink-100">{{ formatDate(request.created_at) }}</dd>
                            </div>
                            <div v-if="request.user" class="flex justify-between gap-4 py-2">
                                <dt class="text-ink-500 dark:text-ink-400">Submitted by</dt>
                                <dd class="text-right text-ink-900 dark:text-ink-100">
                                    {{ request.user.username }}
                                    <span class="block text-xs text-ink-500 dark:text-ink-400">{{ request.user.email }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Modification details. Old is muted, new carries the weight:
                         the point of the screen is what changed. -->
                    <div>
                        <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">What changes</h2>

                        <div class="mt-3 space-y-4">
                            <div v-if="request.needs_name" class="rounded-lg border rule p-3">
                                <p class="eyebrow">Name</p>
                                <div class="mt-2 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p class="text-xs text-ink-400 dark:text-ink-500">From</p>
                                        <p class="mt-0.5 text-ink-500 dark:text-ink-400">
                                            {{ fullName(request.old_first_name, request.old_middle_name, request.old_last_name) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-ink-400 dark:text-ink-500">To</p>
                                        <p class="mt-0.5 font-semibold text-ink-950 dark:text-white">
                                            {{ fullName(request.new_first_name, request.new_middle_name, request.new_last_name) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="request.needs_dob" class="rounded-lg border rule p-3">
                                <p class="eyebrow">Date of birth</p>
                                <div class="mt-2 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p class="text-xs text-ink-400 dark:text-ink-500">From</p>
                                        <p class="mt-0.5 text-ink-500 dark:text-ink-400">{{ formatDate(request.old_dob) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-ink-400 dark:text-ink-500">To</p>
                                        <p class="mt-0.5 font-semibold text-ink-950 dark:text-white">{{ formatDate(request.new_dob) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="request.needs_phone" class="rounded-lg border rule p-3">
                                <p class="eyebrow">Phone number</p>
                                <div class="mt-2 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p class="text-xs text-ink-400 dark:text-ink-500">From</p>
                                        <p class="mt-0.5 font-mono text-ink-500 dark:text-ink-400">{{ request.old_phone_number || '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-ink-400 dark:text-ink-500">To</p>
                                        <p class="mt-0.5 font-mono font-semibold text-ink-950 dark:text-white">{{ request.new_phone_number || '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="request.comment">
                                <p class="eyebrow">Comment</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm text-ink-600 dark:text-ink-300">{{ request.comment }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
