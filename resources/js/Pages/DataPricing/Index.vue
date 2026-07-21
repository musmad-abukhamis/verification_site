<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    plans: Array,
    networks: Array,
    role: String,
    authenticated: Boolean,
});

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
const brand = (network) => ({
    mtn: 'bg-yellow-400 text-yellow-950',
    airtel: 'bg-red-600 text-white',
    glo: 'bg-green-600 text-white',
    '9mobile': 'bg-emerald-700 text-white',
}[network] || 'bg-gray-500 text-white');

const rateLabel = computed(() => {
    if (!props.authenticated) return 'Retail prices';
    if (props.role === 'AGENT' || props.role === 'SMART') return 'Your agent rates';
    if (props.role === 'API') return 'Your API rates';
    return 'Your rates';
});
</script>

<template>
    <Head title="Data Pricing" />

    <div class="min-h-screen bg-gray-50 dark:bg-slate-900">
        <header class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-slate-800">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <div>
                    <h1 class="text-lg font-bold text-gray-900 dark:text-white">Data Pricing</h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ rateLabel }} · {{ plans.length }} plans across {{ networks.length }} networks
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link v-if="authenticated" :href="route('buy-data')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Buy Data
                    </Link>
                    <Link v-else :href="route('login')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Sign in to buy
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="active = 'all'"
                        :class="['rounded-full px-3 py-1.5 text-sm font-medium transition',
                            active === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-600 shadow-sm dark:bg-slate-800 dark:text-gray-300']"
                    >
                        All
                    </button>
                    <button
                        v-for="n in networks" :key="n"
                        @click="active = n"
                        :class="['rounded-full px-3 py-1.5 text-sm font-medium uppercase transition',
                            active === n ? brand(n) : 'bg-white text-gray-600 shadow-sm dark:bg-slate-800 dark:text-gray-300']"
                    >
                        {{ n }}
                    </button>
                </div>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search plan, type or id…"
                    class="ml-auto w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:w-64"
                />
            </div>

            <!-- One table per network -->
            <section
                v-for="network in shownNetworks" :key="network"
                class="overflow-hidden rounded-xl bg-white shadow dark:bg-slate-800"
            >
                <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <span :class="['rounded px-2 py-1 text-xs font-bold uppercase', brand(network)]">{{ network }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ inNetwork(network).length }} plans</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Plan ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Validity</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="plan in inNetwork(network)" :key="plan.plan_id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <code class="rounded bg-indigo-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                        {{ plan.plan_id }}
                                    </code>
                                </td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ plan.name }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">{{ plan.type }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">{{ plan.validity || '—' }}</td>
                                <td class="px-6 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ money(plan.price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p v-if="!shownNetworks.length" class="rounded-xl bg-white p-8 text-center text-sm text-gray-500 shadow dark:bg-slate-800 dark:text-gray-400">
                No plans match that search.
            </p>

            <footer class="border-t border-gray-200 pt-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                <p v-if="!authenticated">
                    Prices shown are retail. Agents and API resellers get their own rates —
                    <Link :href="route('login')" class="text-indigo-600 hover:underline dark:text-indigo-400">sign in</Link>
                    to see yours.
                </p>
                <p class="mt-2">
                    Reselling from your own site? The <strong>Plan ID</strong> column is what you send as
                    <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">plan_id</code> —
                    see the <a :href="route('developers')" class="text-indigo-600 hover:underline dark:text-indigo-400">API documentation</a>.
                </p>
            </footer>
        </main>
    </div>
</template>
