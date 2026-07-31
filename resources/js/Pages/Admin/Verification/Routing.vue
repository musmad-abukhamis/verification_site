<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    services: { type: Array, default: () => [] },
    providers: { type: Array, default: () => [] },
    eligible: { type: Object, default: () => ({}) },
    routes: { type: Object, default: () => ({}) },
    effective: { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
    failedCharges: { type: Object, default: () => ({}) },
});

const page = usePage();

// service -> ordered provider ids. Position 1 is the primary; the rest are
// failover candidates, tried in order.
const chains = ref(Object.fromEntries(props.services.map((s) => [s.value, [...(props.routes[s.value] ?? [])]])));

const providerName = (id) => props.providers.find((p) => p.id === id)?.name ?? id;
const providerFor = (id) => props.providers.find((p) => p.id === id);

const unusedFor = (service) => (props.eligible[service] ?? []).filter((id) => !chains.value[service].includes(id));

const addToChain = (service, event) => {
    const id = event.target.value;
    if (id) chains.value[service].push(id);
    event.target.value = '';
};

const move = (service, index, delta) => {
    const chain = chains.value[service];
    const target = index + delta;
    if (target < 0 || target >= chain.length) return;
    [chain[index], chain[target]] = [chain[target], chain[index]];
};

const removeFrom = (service, index) => chains.value[service].splice(index, 1);

const routeForm = useForm({ routes: [] });

const saveRoutes = () => {
    routeForm.routes = props.services.map((s) => ({ service: s.value, provider_ids: chains.value[s.value] }));
    routeForm.put(route('admin.verification-routing.update'), { preserveScroll: true });
};

const settingsForm = useForm({
    failover_enabled: props.settings.failover_enabled,
    failover_max_attempts: props.settings.failover_max_attempts,
    attempt_retention_days: props.settings.attempt_retention_days,
});

const saveSettings = () => settingsForm.put(route('admin.verification-routing.settings.update'), { preserveScroll: true });

// --- Failed Verification Charges -------------------------------------------
// A fee for a verification the provider confirmed as failed (record not found,
// mismatch). Timeouts and provider outages are never charged, so this panel
// cannot be used to bill for the site's own downtime.
const chargeForm = useForm({
    bvn_enabled: props.failedCharges.bvn_enabled ?? false,
    bvn_amount: props.failedCharges.bvn_amount ?? 0,
    nin_enabled: props.failedCharges.nin_enabled ?? false,
    nin_amount: props.failedCharges.nin_amount ?? 0,
    dedupe_seconds: props.failedCharges.dedupe_seconds ?? 60,
});

const naira = (value) =>
    '₦' + Number(value || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// A toggle that is on but priced at zero charges nothing — say so rather than
// letting an admin believe the fee is live.
const bvnInactive = computed(() => chargeForm.bvn_enabled && Number(chargeForm.bvn_amount) <= 0);
const ninInactive = computed(() => chargeForm.nin_enabled && Number(chargeForm.nin_amount) <= 0);

const chargeSaved = computed(() => page.props.flash?.success);

const saveFailedCharges = () =>
    chargeForm.put(route('admin.verification-routing.failed-charges.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Verification Routing" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Verification Routing &amp; Failover</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Order the providers for each service. Position 1 is the primary; the rest are tried in turn when it declines.
                </p>
            </div>

            <!-- Failover settings -->
            <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-400">Failover</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="settingsForm.failover_enabled" type="checkbox" class="rounded border-gray-300" />
                        Enable failover
                    </label>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Max providers per request</label>
                        <input v-model.number="settingsForm.failover_max_attempts" type="number" min="0" max="20"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                        <p class="mt-1 text-xs text-gray-400">0 = try the whole chain.</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600 dark:text-gray-300">Keep call logs for (days)</label>
                        <input v-model.number="settingsForm.attempt_retention_days" type="number" min="1" max="365"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                </div>
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-900/40 dark:text-gray-400">
                    A provider that explicitly declines (record not found, bad parameter) hands off to the next one.
                    A provider that <em>times out</em> is ambiguous: lookups move on, but BVN Retrieval and IPE Clearance stop
                    and wait for reconciliation, because the request may already have been submitted upstream.
                </div>
                <div class="mt-4 flex justify-end">
                    <button @click="saveSettings" :disabled="settingsForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        Save settings
                    </button>
                </div>
            </div>

            <!-- Failed verification charges -->
            <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Failed Verification Charges</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Charge the user when a provider confirms a verification failed — record not found, invalid
                            number, mismatch. The normal verification fee is still refunded in full; this is taken in its place.
                        </p>
                    </div>
                    <span v-if="chargeSaved" class="shrink-0 rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        {{ chargeSaved }}
                    </span>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <!-- BVN -->
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">BVN</h3>
                        <label class="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="chargeForm.bvn_enabled" type="checkbox" class="rounded border-gray-300" />
                            Charge failed BVN attempts
                        </label>
                        <div class="mt-3">
                            <label class="text-sm text-gray-600 dark:text-gray-300">Failed BVN charge amount (₦)</label>
                            <input v-model.number="chargeForm.bvn_amount" type="number" min="0" max="100000" step="0.01"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            <p v-if="chargeForm.errors.bvn_amount" class="mt-1 text-xs font-medium text-red-600">
                                {{ chargeForm.errors.bvn_amount }}
                            </p>
                            <p v-else-if="bvnInactive" class="mt-1 text-xs text-amber-600">
                                Enabled, but ₦0.00 — nothing will be charged.
                            </p>
                            <p v-else-if="chargeForm.bvn_enabled" class="mt-1 text-xs text-gray-400">
                                A confirmed BVN failure costs the user {{ naira(chargeForm.bvn_amount) }}.
                            </p>
                            <p v-else class="mt-1 text-xs text-gray-400">Off — failed BVN attempts are free.</p>
                        </div>
                    </div>

                    <!-- NIN -->
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">NIN</h3>
                        <label class="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="chargeForm.nin_enabled" type="checkbox" class="rounded border-gray-300" />
                            Charge failed NIN attempts
                        </label>
                        <div class="mt-3">
                            <label class="text-sm text-gray-600 dark:text-gray-300">Failed NIN charge amount (₦)</label>
                            <input v-model.number="chargeForm.nin_amount" type="number" min="0" max="100000" step="0.01"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            <p v-if="chargeForm.errors.nin_amount" class="mt-1 text-xs font-medium text-red-600">
                                {{ chargeForm.errors.nin_amount }}
                            </p>
                            <p v-else-if="ninInactive" class="mt-1 text-xs text-amber-600">
                                Enabled, but ₦0.00 — nothing will be charged.
                            </p>
                            <p v-else-if="chargeForm.nin_enabled" class="mt-1 text-xs text-gray-400">
                                A confirmed NIN failure costs the user {{ naira(chargeForm.nin_amount) }}.
                            </p>
                            <p v-else class="mt-1 text-xs text-gray-400">Off — failed NIN attempts are free.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 sm:max-w-xs">
                    <label class="text-sm text-gray-600 dark:text-gray-300">Repeat-attempt window (seconds)</label>
                    <input v-model.number="chargeForm.dedupe_seconds" type="number" min="0" max="3600"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    <p v-if="chargeForm.errors.dedupe_seconds" class="mt-1 text-xs font-medium text-red-600">
                        {{ chargeForm.errors.dedupe_seconds }}
                    </p>
                    <p v-else class="mt-1 text-xs text-gray-400">
                        Re-submitting the same number inside this window is billed once. 0 = off.
                    </p>
                </div>

                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-900/40 dark:text-gray-400">
                    Only a <em>provider-confirmed</em> failure is charged. A timeout, an unreadable reply, an HTTP 5xx, an
                    unconfigured chain or a decline caused by our own upstream account is refunded in full and never billed —
                    and a user without the balance for the fee is never taken negative.
                </div>

                <div class="mt-4 flex justify-end">
                    <button @click="saveFailedCharges" :disabled="chargeForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        Save charges
                    </button>
                </div>
            </div>

            <!-- Per-service chains -->
            <div class="space-y-4">
                <div v-for="s in services" :key="s.value" class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ s.label }}</h3>
                            <p class="text-xs text-gray-400">
                                {{ s.group }} · <code>{{ s.value }}</code>
                                <span v-if="!s.idempotent" class="ml-1 text-amber-600">· submission — no failover on timeout</span>
                            </p>
                        </div>
                        <select v-if="unusedFor(s.value).length" @change="addToChain(s.value, $event)"
                            class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            <option value="">+ Add provider</option>
                            <option v-for="id in unusedFor(s.value)" :key="id" :value="id">{{ providerName(id) }}</option>
                        </select>
                    </div>

                    <ol v-if="chains[s.value].length" class="mt-4 space-y-2">
                        <li v-for="(id, i) in chains[s.value]" :key="id"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                            <span class="w-6 text-center text-xs font-semibold text-gray-400">{{ i + 1 }}</span>
                            <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">
                                {{ providerName(id) }}
                                <span v-if="i === 0" class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">primary</span>
                                <span v-if="!providerFor(id)?.is_active" class="ml-1 text-xs text-gray-400">(inactive — skipped)</span>
                                <span v-else-if="!providerFor(id)?.is_usable" class="ml-1 text-xs text-amber-600">(no credentials — skipped)</span>
                            </span>
                            <button @click="move(s.value, i, -1)" :disabled="i === 0" class="px-1 text-gray-400 hover:text-gray-700 disabled:opacity-30">↑</button>
                            <button @click="move(s.value, i, 1)" :disabled="i === chains[s.value].length - 1" class="px-1 text-gray-400 hover:text-gray-700 disabled:opacity-30">↓</button>
                            <button @click="removeFrom(s.value, i)" class="px-1 text-red-500 hover:text-red-700">×</button>
                        </li>
                    </ol>

                    <p v-else class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-500 dark:bg-gray-900/40">
                        No explicit order.
                        <template v-if="effective[s.value]?.length">
                            Every provider offering this service is tried by priority:
                            <span class="font-medium">{{ effective[s.value].map((p) => p.name).join(' → ') }}</span>.
                        </template>
                        <template v-else>
                            No provider currently offers this service, so requests will be refused.
                        </template>
                    </p>
                </div>
            </div>

            <div class="flex justify-end">
                <button @click="saveRoutes" :disabled="routeForm.processing"
                    class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    Save routing
                </button>
            </div>
        </div>
    </AdminLayout>
</template>
