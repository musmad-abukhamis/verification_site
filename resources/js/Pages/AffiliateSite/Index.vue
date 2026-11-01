<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    fee: { type: Number, required: true },
    whatsappGroupUrl: { type: String, default: null },
});

const formattedFee = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0,
}).format(props.fee);
</script>

<template>
    <Head title="Own a site like ours" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="Affiliate opportunity"
                title="Do you want to own a site like ours?"
                description="Get started with a website offering the services available on our platform."
            />

            <section class="card card-pad max-w-3xl">
                <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">
                    What the site can offer
                </h2>
                <p class="mt-2 text-sm leading-6 text-ink-600 dark:text-ink-300">
                    The site package is based on the services available here, including NIN and BVN
                    verification, validation, IPE clearance, BVN modification and onboarding,
                    retrieval, ID cards, enrollment records, data, and wallet services.
                </p>

                <div class="mt-6 rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-900 dark:bg-brand-950/30">
                    <p class="eyebrow">One-time fee</p>
                    <p class="mt-1 font-display text-2xl font-bold text-ink-950 dark:text-white">
                        {{ formattedFee }}
                    </p>
                    <p class="mt-2 text-sm leading-6 text-ink-600 dark:text-ink-300">
                        Payment is arranged manually. Join the affiliate WhatsApp group to discuss
                        payment, setup, and any questions with the team. No payment is taken or
                        verified on this website.
                    </p>
                </div>

                <a
                    v-if="whatsappGroupUrl"
                    :href="whatsappGroupUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-5 inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-ink-950"
                >
                    Continue to affiliate WhatsApp group
                    <span class="sr-only"> (opens in a new tab)</span>
                </a>
                <p v-else class="mt-5 text-sm text-ink-500 dark:text-ink-400" role="status">
                    The WhatsApp group link is not available yet. Please check back later.
                </p>

                <p class="mt-4 text-xs leading-5 text-ink-500 dark:text-ink-400">
                    The fee covers the site opportunity. Confirm the full package and any ongoing
                    costs with the team on WhatsApp before making a manual payment.
                </p>
            </section>

            <Link :href="route('dashboard')" class="link text-sm font-medium">
                Back to dashboard
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
