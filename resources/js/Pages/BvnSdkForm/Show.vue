<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    form: Object,
});

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'long', day: 'numeric' });
};
</script>

<template>
    <Head title="BVN Onboarding Details" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="BVN onboarding"
                :title="`${form.firstName} ${form.lastName}`"
            >
                <template #actions>
                    <Link :href="route('bvn-sdk-form.submissions')" class="btn btn-ghost">Back to submissions</Link>
                </template>
            </PageHeader>

            <div class="card flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <p class="eyebrow">Submission ID</p>
                    <p class="mt-1 font-mono text-sm font-semibold text-ink-900 dark:text-ink-100">{{ form.id }}</p>
                </div>
                <StatusPill :status="form.status" />
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <section class="card card-pad">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Personal information</h2>

                    <dl class="mt-3 divide-y rule text-sm">
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Email</dt>
                            <dd class="break-all text-right font-medium text-ink-900 dark:text-ink-100">{{ form.email }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Phone number</dt>
                            <dd class="font-mono font-medium text-ink-900 dark:text-ink-100">{{ form.phoneNumber }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Address</dt>
                            <dd class="text-right font-medium text-ink-900 dark:text-ink-100">{{ form.address }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Date of birth</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">{{ formatDate(form.dateOfBirth) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Registered</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">{{ formatDate(form.created_at) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="card card-pad">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Location &amp; banking</h2>

                    <dl class="mt-3 divide-y rule text-sm">
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">State</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">{{ form.stateOfResidence }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">LGA</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">{{ form.lga }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Zone</dt>
                            <dd class="font-medium capitalize text-ink-900 dark:text-ink-100">{{ form.zone?.replaceAll('-', ' ') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Agent location</dt>
                            <dd class="text-right font-medium text-ink-900 dark:text-ink-100">{{ form.agentLocation }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">BVN</dt>
                            <dd class="font-mono font-semibold text-ink-950 dark:text-white">{{ form.agentBvn }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Bank</dt>
                            <dd class="font-medium text-ink-900 dark:text-ink-100">{{ form.bankName }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Account no.</dt>
                            <dd class="font-mono font-semibold text-ink-950 dark:text-white">{{ form.accountNumber }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-ink-500 dark:text-ink-400">Account name</dt>
                            <dd class="text-right font-medium text-ink-900 dark:text-ink-100">{{ form.accountName }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section v-if="form.comment" class="card card-pad">
                <h2 class="eyebrow">Admin comment</h2>
                <p class="mt-1.5 whitespace-pre-wrap text-sm text-ink-600 dark:text-ink-300">{{ form.comment }}</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
