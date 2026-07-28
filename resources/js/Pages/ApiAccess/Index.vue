<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Alert from '@/Components/Alert.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    enabled: Boolean,
    token: String,
    endpoint: String,
    services: Array,
});

const revealed = ref(false);
const copied = ref(false);

const form = useForm({});

const regenerate = () => {
    const message = props.token
        ? 'Issue a new token?\n\nYour current token stops working immediately and any live integration using it will start failing until you update it.'
        : 'Generate your API token?';

    if (confirm(message)) {
        form.post(route('api-access.regenerate'), { preserveScroll: true });
    }
};

const copy = async () => {
    await navigator.clipboard.writeText(props.token);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
};

const masked = (token) => `${token.slice(0, 12)}${'•'.repeat(24)}`;
const money = (value) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(value ?? 0));
</script>

<template>
    <Head title="API Access" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <PageHeader
                eyebrow="Developers"
                title="API access"
                description="Sell our verification and data services from your own site."
            >
                <template #actions>
                    <a :href="route('developers')" target="_blank" class="btn btn-secondary">Read the docs</a>
                </template>
            </PageHeader>

            <Alert v-if="$page.props.flash?.success" tone="success">{{ $page.props.flash.success }}</Alert>

            <!-- Not an API account -->
            <section v-if="!enabled" class="card">
                <EmptyState
                    title="API access is not enabled"
                    description="The API lets you resell our services from your own website or app. Contact support to have it enabled on your account, then come back here for your token."
                    icon="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                >
                    <Link :href="route('help.index')" class="btn btn-primary btn-sm">Contact support</Link>
                </EmptyState>
            </section>

            <template v-else>
                <!-- Token -->
                <section class="card card-pad">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Your API token</h2>
                    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                        Send it as
                        <code class="rounded bg-ink-100 px-1 font-mono text-xs dark:bg-ink-800">Authorization: Bearer &lt;token&gt;</code>
                        on every request.
                    </p>

                    <div v-if="token" class="mt-4 flex flex-wrap items-center gap-2">
                        <code class="flex-1 break-all rounded-lg border rule bg-ink-50 px-3 py-2 font-mono text-sm text-ink-800 dark:bg-ink-950/50 dark:text-ink-100">
                            {{ revealed ? token : masked(token) }}
                        </code>
                        <button @click="revealed = !revealed" class="btn btn-secondary btn-sm">
                            {{ revealed ? 'Hide' : 'Reveal' }}
                        </button>
                        <button @click="copy" class="btn btn-secondary btn-sm">
                            {{ copied ? 'Copied' : 'Copy' }}
                        </button>
                    </div>

                    <p v-else class="mt-4 text-sm font-medium text-warning-700 dark:text-warning-400">
                        No token yet — generate one to start integrating.
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button @click="regenerate" :disabled="form.processing" class="btn btn-primary">
                            {{ token ? 'Regenerate token' : 'Generate token' }}
                        </button>
                        <span class="text-xs text-ink-500 dark:text-ink-400">
                            Treat this like a password. Anyone holding it can spend your wallet balance.
                        </span>
                    </div>

                    <p v-if="form.errors.token" class="mt-2 text-sm font-medium text-danger-600 dark:text-danger-400">
                        {{ form.errors.token }}
                    </p>
                </section>

                <!-- Endpoint -->
                <section class="card card-pad">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Base URL</h2>
                    <code class="mt-2 block break-all rounded-lg border rule bg-ink-50 px-3 py-2 font-mono text-sm text-ink-800 dark:bg-ink-950/50 dark:text-ink-100">
                        {{ endpoint }}
                    </code>
                </section>

                <!-- Rates -->
                <section class="card overflow-hidden">
                    <div class="border-b rule px-5 py-4">
                        <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Your rates</h2>
                        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
                            What your account is charged per call. Also available live from
                            <code class="rounded bg-ink-100 px-1 font-mono text-xs dark:bg-ink-800">GET /services</code>.
                        </p>
                    </div>

                    <div v-if="services.length" class="scroll-slim overflow-x-auto">
                        <table class="min-w-full">
                            <tbody class="divide-y rule">
                                <tr v-for="s in services" :key="s.service" class="t-row">
                                    <td class="font-semibold text-ink-900 dark:text-ink-100">{{ s.label }}</td>
                                    <td><code class="font-mono text-xs text-ink-400 dark:text-ink-500">{{ s.service }}</code></td>
                                    <td class="text-right font-mono font-semibold text-ink-950 dark:text-white">{{ money(s.price) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="px-5 py-6 text-sm text-ink-500 dark:text-ink-400">
                        No services are currently available on your account.
                    </p>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
