<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const props = defineProps({
    networks: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    vendors: { type: Array, default: () => [] },
    routes: { type: Object, default: () => ({}) },
    networkCodes: { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
    prefixes: { type: Object, default: () => ({}) },
});

const vendorName = (id) => props.vendors.find((v) => v.id === id)?.name || id;

/* ---- settings ---- */
const settingsForm = useForm({
    failover_enabled: !!props.settings.failover_enabled,
    failover_max_attempts: props.settings.failover_max_attempts ?? 0,
    reconcile_cutoff_minutes: props.settings.reconcile_cutoff_minutes ?? 120,
    requery_interval_minutes: props.settings.requery_interval_minutes ?? 5,
});
const saveSettings = () => settingsForm.put(route('admin.data.settings.update'), { preserveScroll: true });

/* ---- routing matrix ---- */
const matrix = reactive({});
for (const net of props.networks) {
    for (const type of props.types) {
        matrix[`${net}|${type}`] = [...(props.routes[`${net}|${type}`] || [])];
    }
}
const addVendor = (key, id) => { if (id && !matrix[key].includes(id)) matrix[key].push(id); };
const removeVendor = (key, i) => matrix[key].splice(i, 1);
const move = (key, i, dir) => {
    const j = i + dir;
    if (j < 0 || j >= matrix[key].length) return;
    [matrix[key][i], matrix[key][j]] = [matrix[key][j], matrix[key][i]];
};
const savingRoutes = ref(false);
const saveRoutes = () => {
    savingRoutes.value = true;
    const routes = Object.entries(matrix).map(([key, vendor_ids]) => {
        const [network, type] = key.split('|');
        return { network, type, vendor_ids };
    });
    router.put(route('admin.data.routing.update'), { routes }, {
        preserveScroll: true,
        onFinish: () => { savingRoutes.value = false; },
    });
};

/* ---- network codes ---- */
const codes = reactive({});
for (const net of props.networks) {
    for (const v of props.vendors) {
        codes[`${net}|${v.id}`] = props.networkCodes[net]?.[v.id] ?? '';
    }
}
const saveCodes = () => {
    const payload = Object.entries(codes).map(([key, external_network_code]) => {
        const [network, vendor_id] = key.split('|');
        return { network, vendor_id, external_network_code };
    });
    router.put(route('admin.data.network-codes.update'), { codes: payload }, { preserveScroll: true });
};

/* ---- prefixes ---- */
const newPrefix = reactive(Object.fromEntries(props.networks.map((n) => [n, ''])));
const addPrefix = (network) => {
    const prefix = (newPrefix[network] || '').trim();
    if (!prefix) return;
    router.post(route('admin.data.prefixes.add'), { network, prefix }, {
        preserveScroll: true,
        only: ['prefixes', 'flash'],
        onSuccess: () => { newPrefix[network] = ''; },
    });
};
const removePrefix = (network, prefix) => router.delete(route('admin.data.prefixes.remove'), {
    data: { network, prefix }, preserveScroll: true, only: ['prefixes', 'flash'],
});
</script>

<template>
    <Head title="Data Routing & Settings" />
    <AdminLayout>
        <div class="space-y-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Routing &amp; Settings</h1>

            <!-- Settings -->
            <section class="space-y-4 rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Failover &amp; reconciliation</h2>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <input v-model="settingsForm.failover_enabled" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                    Enable failover (try the next vendor on an explicit failure)
                </label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="text-xs text-gray-500">Max attempts (0 = all)</label>
                        <input v-model.number="settingsForm.failover_max_attempts" type="number" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Reconcile cutoff (min)</label>
                        <input v-model.number="settingsForm.reconcile_cutoff_minutes" type="number" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Requery interval (min)</label>
                        <input v-model.number="settingsForm.requery_interval_minutes" type="number" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                </div>
                <button @click="saveSettings" :disabled="settingsForm.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">Save settings</button>
            </section>

            <!-- Routing matrix -->
            <section class="space-y-4 rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Routing matrix</h2>
                    <button @click="saveRoutes" :disabled="savingRoutes" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">Save routing</button>
                </div>
                <p class="text-xs text-gray-400">Position 1 is the primary vendor; the rest are failover candidates in order.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div v-for="net in networks" :key="net" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="mb-2 font-semibold uppercase text-gray-700 dark:text-gray-200">{{ net }}</h3>
                        <div v-for="type in types" :key="type" class="mb-4">
                            <p class="mb-1 text-xs font-medium text-gray-500">{{ type }}</p>
                            <ol class="space-y-1">
                                <li v-for="(id, i) in matrix[`${net}|${type}`]" :key="id" class="flex items-center gap-2 rounded bg-gray-50 px-2 py-1 text-sm dark:bg-gray-900/40">
                                    <span class="w-5 text-xs text-gray-400">{{ i + 1 }}.</span>
                                    <span class="flex-1">{{ vendorName(id) }}</span>
                                    <button @click="move(`${net}|${type}`, i, -1)" class="text-gray-400 hover:text-gray-700">↑</button>
                                    <button @click="move(`${net}|${type}`, i, 1)" class="text-gray-400 hover:text-gray-700">↓</button>
                                    <button @click="removeVendor(`${net}|${type}`, i)" class="text-red-500 hover:text-red-700">✕</button>
                                </li>
                            </ol>
                            <select class="mt-1 w-full rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" @change="addVendor(`${net}|${type}`, $event.target.value); $event.target.value=''">
                                <option value="">+ add vendor…</option>
                                <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}{{ v.is_active ? '' : ' (inactive)' }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Network codes -->
            <section class="space-y-4 rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Vendor network codes</h2>
                    <button @click="saveCodes" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save codes</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-2 pr-4">Vendor</th>
                                <th v-for="net in networks" :key="net" class="py-2 pr-4 uppercase">{{ net }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in vendors" :key="v.id" class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-200">{{ v.name }}</td>
                                <td v-for="net in networks" :key="net" class="py-2 pr-4">
                                    <input v-model="codes[`${net}|${v.id}`]" class="w-20 rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Prefixes -->
            <section class="space-y-4 rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Network prefixes</h2>
                <p class="text-xs text-gray-400">Add new NCC prefixes here — no redeploy needed. Hints only; never blocks purchases.</p>
                <div class="grid gap-4 md:grid-cols-2">
                    <div v-for="net in networks" :key="net" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="mb-2 font-semibold uppercase text-gray-700 dark:text-gray-200">{{ net }}</h3>
                        <div class="flex flex-wrap gap-1">
                            <span v-for="p in (prefixes[net] || [])" :key="p" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700">
                                {{ p }}
                                <button @click="removePrefix(net, p)" class="text-red-500 hover:text-red-700">✕</button>
                            </span>
                            <span v-if="!(prefixes[net] || []).length" class="text-xs text-gray-400">none</span>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <input v-model="newPrefix[net]" placeholder="e.g. 0707" class="w-28 rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" @keyup.enter="addPrefix(net)" />
                            <button @click="addPrefix(net)" class="rounded bg-gray-200 px-2 py-1 text-xs font-semibold dark:bg-gray-700">Add</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
