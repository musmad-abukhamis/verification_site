<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
const money = (value) => `₦${Number(value).toLocaleString()}`;
</script>

<template>
    <Head title="API Access" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">API Access</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="rounded-lg bg-green-100 p-4 text-sm text-green-700 dark:bg-green-900 dark:text-green-200">
                    {{ $page.props.flash.success }}
                </div>

                <!-- Not an API account -->
                <div v-if="!enabled" class="rounded-xl bg-white p-6 shadow dark:bg-slate-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">API access is not enabled</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        The API lets you sell our verification and data services from your own website or app.
                        Contact support to have API access enabled on your account, then come back here for your token.
                    </p>
                    <Link :href="route('help.index')" class="mt-4 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Contact Support
                    </Link>
                </div>

                <template v-else>
                    <!-- Token -->
                    <div class="rounded-xl bg-white p-6 shadow dark:bg-slate-800">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Your API token</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Send it as <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">Authorization: Bearer &lt;token&gt;</code> on every request.
                                </p>
                            </div>
                            <a :href="route('developers')" target="_blank" class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                Read the docs →
                            </a>
                        </div>

                        <div v-if="token" class="mt-4 flex flex-wrap items-center gap-2">
                            <code class="flex-1 break-all rounded-lg bg-gray-100 px-3 py-2 font-mono text-sm text-gray-800 dark:bg-gray-700 dark:text-gray-100">
                                {{ revealed ? token : masked(token) }}
                            </code>
                            <button @click="revealed = !revealed" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:text-gray-200">
                                {{ revealed ? 'Hide' : 'Reveal' }}
                            </button>
                            <button @click="copy" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:text-gray-200">
                                {{ copied ? 'Copied' : 'Copy' }}
                            </button>
                        </div>

                        <p v-else class="mt-4 text-sm text-amber-600 dark:text-amber-400">
                            No token yet — generate one to start integrating.
                        </p>

                        <div class="mt-4 flex items-center gap-3">
                            <button @click="regenerate" :disabled="form.processing"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ token ? 'Regenerate token' : 'Generate token' }}
                            </button>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Treat this like a password. Anyone holding it can spend your wallet balance.
                            </span>
                        </div>

                        <p v-if="form.errors.token" class="mt-2 text-sm text-red-600">{{ form.errors.token }}</p>
                    </div>

                    <!-- Endpoint -->
                    <div class="rounded-xl bg-white p-6 shadow dark:bg-slate-800">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Base URL</h3>
                        <code class="mt-2 block break-all rounded-lg bg-gray-100 px-3 py-2 font-mono text-sm dark:bg-gray-700 dark:text-gray-100">{{ endpoint }}</code>
                    </div>

                    <!-- Your rates -->
                    <div class="rounded-xl bg-white shadow dark:bg-slate-800">
                        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Your rates</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                What your account is charged per call. Also available live from
                                <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">GET /services</code>.
                            </p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="s in services" :key="s.service">
                                        <td class="px-6 py-3 text-sm text-gray-900 dark:text-white">{{ s.label }}</td>
                                        <td class="px-6 py-3"><code class="text-xs text-gray-400">{{ s.service }}</code></td>
                                        <td class="px-6 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ money(s.price) }}</td>
                                    </tr>
                                    <tr v-if="!services.length">
                                        <td colspan="3" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">No services are currently available on your account.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
