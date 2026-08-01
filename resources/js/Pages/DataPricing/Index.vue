<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    plans: Array,
    networks: Array,
    role: String,
    authenticated: Boolean,
});

/**
 * The page is reachable two ways: from the sidebar when signed in, and as a
 * public price list. Signed in it renders inside the app chrome — losing the
 * navigation just because you checked a price is disorienting. Signed out it
 * keeps its own masthead.
 */
const Wrapper = computed(() => (props.authenticated ? AuthenticatedLayout : 'div'));

const active = ref('all');
const search = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.plans.filter((p) => {
        const matchesNetwork = active.value === 'all' || p.network === active.value;
        const matchesTerm = !term
            || p.name.toLowerCase().includes(term)
            || p.type.toLowerCase().includes(term)
            || String(p.plan_id) === term;

        return matchesNetwork && matchesTerm;
    });
});

const shownNetworks = computed(() =>
    props.networks.filter((n) => filtered.value.some((p) => p.network === n)),
);

const inNetwork = (network) => filtered.value.filter((p) => p.network === network);

const money = (value) => `₦${Number(value).toLocaleString()}`;

// Reuse the operators' own colours so the sections are scannable at a glance.
// Operator marks are the one sanctioned exception to the status-colour rule.
const brand = (network) => ({
    mtn: 'bg-warning-400 text-warning-950',
    airtel: 'bg-danger-600 text-white',
    glo: 'bg-success-600 text-white',
    '9mobile': 'bg-success-800 text-white',
}[network] || 'bg-ink-500 text-white');

const rateLabel = computed(() => {
    if (!props.authenticated) return 'Retail prices';
    if (props.role === 'AGENT' || props.role === 'SMART') return 'Your agent rates';
    if (props.role === 'API') return 'Your API rates';
    return 'Your rates';
});
</script>

<template>
    <Head title="Data Pricing" />

    <component :is="Wrapper" :class="authenticated ? null : 'min-h-screen bg-canvas dark:bg-ink-950'">
        <!-- Public masthead only: signed in, the app's own chrome is already there. -->
        <header v-if="!authenticated" class="border-b rule bg-white dark:bg-ink-900">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <p class="eyebrow">{{ rateLabel }}</p>
                    <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-ink-950 dark:text-white">
                        Data pricing
                    </h1>
                    <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                        {{ plans.length }} plans across {{ networks.length }} networks
                    </p>
                </div>

                <Link :href="route('login')" class="btn btn-primary">Sign in to buy</Link>
            </div>
        </header>

        <div :class="authenticated ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8'">
            <PageHeader
                v-if="authenticated"
                eyebrow="Data"
                title="Data pricing"
                :description="`${rateLabel} — ${plans.length} plans across ${networks.length} networks.`"
            >
                <template #actions>
                    <Link :href="route('buy-data')" class="btn btn-primary">Buy data</Link>
                </template>
            </PageHeader>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="active = 'all'"
                        :aria-pressed="active === 'all'"
                        :class="['rounded-full px-3 py-1.5 text-sm font-semibold transition',
                            active === 'all'
                                ? 'bg-brand-600 text-white dark:bg-brand-600'
                                : 'bg-white text-ink-600 shadow-card hover:text-ink-900 dark:bg-ink-900 dark:text-ink-300 dark:hover:text-white']"
                    >
                        All
                    </button>
                    <button
                        v-for="n in networks" :key="n"
                        @click="active = n"
                        :aria-pressed="active === n"
                        :class="['rounded-full px-3 py-1.5 text-sm font-semibold uppercase transition',
                            active === n
                                ? brand(n)
                                : 'bg-white text-ink-600 shadow-card hover:text-ink-900 dark:bg-ink-900 dark:text-ink-300 dark:hover:text-white']"
                    >
                        {{ n }}
                    </button>
                </div>

                <input
                    v-model="search"
                    type="search"
                    placeholder="Search plan, type or id…"
                    aria-label="Search plans"
                    class="ml-auto w-full text-sm sm:w-64"
                />
            </div>

            <!-- One table per network -->
            <section v-for="network in shownNetworks" :key="network" class="card overflow-hidden">
                <div class="flex items-center gap-3 border-b rule px-5 py-4">
                    <span :class="['rounded px-2 py-1 text-xs font-bold uppercase tracking-wide', brand(network)]">
                        {{ network }}
                    </span>
                    <span class="text-sm text-ink-500 dark:text-ink-400">{{ inNetwork(network).length }} plans</span>
                </div>

                <div class="scroll-slim overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="t-head">
                            <tr>
                                <th>Plan ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Validity</th>
                                <th class="text-right">Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y rule">
                            <tr v-for="plan in inNetwork(network)" :key="plan.plan_id" class="t-row">
                                <td>
                                    <code class="rounded bg-brand-50 px-1.5 py-0.5 font-mono text-xs font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                                        {{ plan.plan_id }}
                                    </code>
                                </td>
                                <td class="font-semibold text-ink-900 dark:text-ink-100">{{ plan.name }}</td>
                                <td class="text-ink-600 dark:text-ink-300">{{ plan.type }}</td>
                                <td class="text-ink-600 dark:text-ink-300">{{ plan.validity || '—' }}</td>
                                <td class="text-right font-mono font-semibold text-ink-950 dark:text-white">{{ money(plan.price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p v-if="!shownNetworks.length" class="card px-6 py-10 text-center text-sm text-ink-500 dark:text-ink-400">
                No plans match that search.
            </p>

            <footer class="border-t rule pt-6 text-sm text-ink-500 dark:text-ink-400">
                <p v-if="!authenticated">
                    Prices shown are retail. Agents and API resellers get their own rates —
                    <Link :href="route('login')" class="link">sign in</Link>
                    to see yours.
                </p>
                <p class="mt-2">
                    Reselling from your own site? The <strong class="font-semibold text-ink-700 dark:text-ink-200">Plan ID</strong>
                    column is what you send as
                    <code class="rounded bg-ink-100 px-1 font-mono text-xs dark:bg-ink-800">plan_id</code> —
                    see the <a :href="route('developers')" class="link">API documentation</a>.
                </p>
            </footer>
        </div>
    </component>
</template>
