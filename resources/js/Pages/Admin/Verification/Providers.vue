<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    providers: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    authStyles: { type: Array, default: () => [] },
    bodyTypes: { type: Array, default: () => [] },
});

const page = usePage();
const testResult = computed(() => page.props.flash?.testResult ?? null);

const showForm = ref(false);
const editingId = ref(null);
const testingFor = ref(null); // { providerId, service }
const testInput = ref({});

const blankProvider = () => ({
    name: '',
    slug: '',
    base_url: '',
    auth_type: 'bearer',
    auth_config: { header_name: '', prefix: '', key_header: '', secret_header: '', body_field: '', query_param: '' },
    credentials: { token: '', key: '', secret: '', username: '', password: '' },
    extra_headers: [],
    timeout_seconds: 30,
    priority: 100,
    is_active: true,
    notes: '',
    endpoints: [],
});

const form = useForm(blankProvider());

// Only the credential / config inputs the chosen auth style actually uses.
const style = computed(() => props.authStyles.find((s) => s.value === form.auth_type) ?? { credentials: [], config: [] });
const credentialStatus = ref({});

const serviceMeta = (key) => props.services.find((s) => s.value === key) ?? { inputs: [], required: [], label: key };

// Services this provider does not already have an endpoint for.
const availableServices = computed(() => {
    const taken = form.endpoints.map((e) => e.service);
    return props.services.filter((s) => !taken.includes(s.value));
});

const configLabels = {
    header_name: 'Header name (e.g. x-api-key)',
    prefix: 'Value prefix (optional)',
    key_header: 'Key header name (e.g. api-key)',
    secret_header: 'Secret header name (e.g. api-secret)',
    body_field: 'Body field name (e.g. api_key)',
    query_param: 'Query parameter name',
};

const credentialLabels = {
    token: 'API key / token',
    key: 'API key',
    secret: 'API secret',
    username: 'Username',
    password: 'Password',
};

const openCreate = () => {
    editingId.value = null;
    credentialStatus.value = {};
    form.defaults(blankProvider());
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (p) => {
    editingId.value = p.id;
    credentialStatus.value = p.credential_status ?? {};
    form.defaults({
        ...blankProvider(),
        name: p.name,
        slug: p.slug,
        base_url: p.base_url,
        auth_type: p.auth_type,
        auth_config: { ...blankProvider().auth_config, ...(p.auth_config ?? {}) },
        // Always blank: secrets are never sent to the browser. Leaving a field
        // empty on save keeps the stored value.
        credentials: blankProvider().credentials,
        extra_headers: [...(p.extra_headers ?? [])],
        timeout_seconds: p.timeout_seconds,
        priority: p.priority,
        is_active: p.is_active,
        notes: p.notes ?? '',
        endpoints: (p.endpoints ?? []).map((e) => ({ ...e, field_map: [...e.field_map], static_fields: [...e.static_fields], response_map: [...e.response_map], success_rule: { ...e.success_rule } })),
    });
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

// Slug follows the name until the provider is saved once.
watch(() => form.name, (name) => {
    if (!editingId.value) {
        form.slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
});

const addEndpoint = () => {
    const next = availableServices.value[0];
    if (!next) return;
    form.endpoints.push({
        service: next.value,
        http_method: 'POST',
        path: '/',
        body_type: 'json',
        is_active: true,
        field_map: next.inputs.map((input) => ({ input, field: input, format: '', transform: '', values: '' })),
        static_fields: [],
        success_rule: { path: '', in: '', error_path: '', data_path: '' },
        response_map: [],
    });
};

// Keep the field-map rows in step when the service changes.
const onServiceChange = (endpoint) => {
    endpoint.field_map = serviceMeta(endpoint.service).inputs.map((input) => {
        const existing = endpoint.field_map.find((r) => r.input === input);
        return existing ?? { input, field: input, format: '', transform: '', values: '' };
    });
};

const removeEndpoint = (index) => form.endpoints.splice(index, 1);
const addPair = (list) => list.push({ key: '', value: '' });
const addMapping = (list) => list.push({ field: '', path: '' });
const removeAt = (list, index) => list.splice(index, 1);

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    if (editingId.value) {
        form.put(route('admin.verification-providers.update', editingId.value), opts);
    } else {
        form.post(route('admin.verification-providers.store'), opts);
    }
};

const toggle = (p) => router.patch(route('admin.verification-providers.toggle', p.id), {}, { preserveScroll: true });

const destroy = (p) => {
    if (confirm(`Delete "${p.name}"? Its endpoints and routing positions are removed too.`)) {
        router.delete(route('admin.verification-providers.destroy', p.id), { preserveScroll: true });
    }
};

const openTest = (provider, service) => {
    testingFor.value = { providerId: provider.id, providerName: provider.name, service };
    testInput.value = Object.fromEntries(serviceMeta(service).inputs.map((i) => [i, '']));
};

const runTest = () => {
    router.post(
        route('admin.verification-providers.test', testingFor.value.providerId),
        { service: testingFor.value.service, input: testInput.value },
        { preserveScroll: true, preserveState: true },
    );
};

const outcomeClass = (outcome) => ({
    success: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    fail: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    timeout: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
}[outcome] ?? 'bg-gray-100 text-gray-600');

const inputLabel = (name) => name.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
</script>

<template>
    <Head title="Verification Providers" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Verification Providers</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Add a NIN/BVN provider by choosing its service types, header style and body shape — no deploy needed.
                    </p>
                </div>
                <button @click="openCreate" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    + New provider
                </button>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3">Provider</th>
                            <th class="px-4 py-3">Auth</th>
                            <th class="px-4 py-3">Services</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                        <tr v-for="p in providers" :key="p.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ p.name }}</p>
                                <p class="text-xs text-gray-400">{{ p.base_url }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ p.auth_type }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <button v-for="e in p.endpoints" :key="e.service" @click="openTest(p, e.service)"
                                        class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 hover:bg-blue-100 hover:text-blue-700 dark:bg-gray-700 dark:text-gray-300"
                                        :title="`Test ${e.service}`">
                                        {{ e.service }}
                                    </button>
                                    <span v-if="!p.endpoints.length" class="text-xs text-gray-400">none</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ p.priority }}</td>
                            <td class="px-4 py-3">
                                <button @click="toggle(p)" class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="p.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700'">
                                    {{ p.is_active ? 'Active' : 'Inactive' }}
                                </button>
                                <p v-if="p.is_active && !p.is_usable" class="mt-1 text-xs text-amber-600">Missing credentials</p>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button @click="openEdit(p)" class="mr-3 text-blue-600 hover:underline">Edit</button>
                                <button @click="destroy(p)" class="text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!providers.length">
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No providers yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Provider form ─────────────────────────────────────────── -->
            <div v-if="showForm" class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4" @click.self="showForm = false">
                <div class="my-8 w-full max-w-4xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ editingId ? 'Edit' : 'New' }} verification provider
                    </h2>

                    <!-- Connection -->
                    <section class="space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Connection</h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-sm text-gray-600 dark:text-gray-300">Name</label>
                                <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" placeholder="Prembly" />
                                <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="text-sm text-gray-600 dark:text-gray-300">Slug</label>
                                <input v-model="form.slug" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" placeholder="prembly" />
                                <p v-if="form.errors.slug" class="text-xs text-red-500">{{ form.errors.slug }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600 dark:text-gray-300">Base URL</label>
                            <input v-model="form.base_url" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" placeholder="https://api.prembly.com" />
                            <p v-if="form.errors.base_url" class="text-xs text-red-500">{{ form.errors.base_url }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="text-sm text-gray-600 dark:text-gray-300">Timeout (seconds)</label>
                                <input v-model.number="form.timeout_seconds" type="number" min="5" max="120" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            </div>
                            <div>
                                <label class="text-sm text-gray-600 dark:text-gray-300">Priority</label>
                                <input v-model.number="form.priority" type="number" min="0" max="1000" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                <p class="mt-1 text-xs text-gray-400">Lower runs first when a service has no explicit routing.</p>
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" /> Active
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- Authentication / header style -->
                    <section class="mt-6 space-y-3 border-t border-gray-100 pt-5 dark:border-gray-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Header / authentication style</h3>
                        <select v-model="form.auth_type" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            <option v-for="s in authStyles" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>

                        <div v-if="style.config.length" class="grid gap-3 sm:grid-cols-2">
                            <div v-for="key in style.config" :key="key">
                                <label class="text-sm text-gray-600 dark:text-gray-300">{{ configLabels[key] ?? key }}</label>
                                <input v-model="form.auth_config[key]" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            </div>
                        </div>

                        <div v-if="style.credentials.length" class="grid gap-3 sm:grid-cols-2">
                            <div v-for="key in style.credentials" :key="key">
                                <label class="text-sm text-gray-600 dark:text-gray-300">
                                    {{ credentialLabels[key] ?? key }}
                                    <span v-if="credentialStatus[key]" class="ml-1 text-xs text-green-600">• stored</span>
                                </label>
                                <input v-model="form.credentials[key]" type="password" autocomplete="new-password"
                                    :placeholder="credentialStatus[key] ? 'Leave blank to keep the stored value' : ''"
                                    class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-sm text-gray-600 dark:text-gray-300">Extra static headers</label>
                                <button type="button" @click="addPair(form.extra_headers)" class="text-xs text-blue-600 hover:underline">+ Add header</button>
                            </div>
                            <div v-for="(h, i) in form.extra_headers" :key="i" class="mt-2 flex gap-2">
                                <input v-model="h.key" placeholder="Accept" class="w-1/3 rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                <input v-model="h.value" placeholder="application/json" class="flex-1 rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                <button type="button" @click="removeAt(form.extra_headers, i)" class="px-2 text-red-500">×</button>
                            </div>
                        </div>
                    </section>

                    <!-- Endpoints, one per service type -->
                    <section class="mt-6 space-y-4 border-t border-gray-100 pt-5 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Service endpoints</h3>
                            <button type="button" @click="addEndpoint" :disabled="!availableServices.length"
                                class="text-xs text-blue-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400">
                                + Add service
                            </button>
                        </div>

                        <p v-if="!form.endpoints.length" class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-900/40">
                            Add at least one service — a provider with no endpoints is never routed.
                        </p>

                        <div v-for="(e, i) in form.endpoints" :key="i" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="grid flex-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="text-xs text-gray-500">Service type</label>
                                        <select v-model="e.service" @change="onServiceChange(e)" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                            <option v-for="s in services" :key="s.value" :value="s.value">{{ s.label }}</option>
                                        </select>
                                        <p v-if="form.errors[`endpoints.${i}.service`]" class="text-xs text-red-500">{{ form.errors[`endpoints.${i}.service`] }}</p>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500">Body type</label>
                                        <select v-model="e.body_type" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                            <option v-for="b in bodyTypes" :key="b.value" :value="b.value">{{ b.label }}</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="button" @click="removeEndpoint(i)" class="mt-5 text-sm text-red-500 hover:underline">Remove</button>
                            </div>

                            <div class="mt-3 flex gap-2">
                                <select v-model="e.http_method" class="w-28 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                    <option>POST</option><option>GET</option><option>PUT</option><option>PATCH</option>
                                </select>
                                <input v-model="e.path" placeholder="/identitypass/verification/bvn"
                                    class="flex-1 rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                            </div>
                            <p v-if="form.errors[`endpoints.${i}.path`]" class="text-xs text-red-500">{{ form.errors[`endpoints.${i}.path`] }}</p>

                            <!-- Field map: canonical input → provider's field name -->
                            <div class="mt-4">
                                <p class="text-xs font-medium text-gray-500">Request fields</p>
                                <p class="text-xs text-gray-400">What this provider calls each input. Date format uses PHP tokens (d-m-Y); value table is <code>male=M, female=F</code>.</p>
                                <div class="mt-2 space-y-2">
                                    <div v-for="(row, ri) in e.field_map" :key="ri" class="grid grid-cols-12 gap-2">
                                        <span class="col-span-3 self-center text-xs text-gray-500">{{ inputLabel(row.input) }}</span>
                                        <input v-model="row.field" placeholder="number" class="col-span-3 rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                        <input v-model="row.format" placeholder="d-m-Y" class="col-span-2 rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                        <select v-model="row.transform" class="col-span-2 rounded-lg border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                            <option value="">as-is</option><option value="upper">UPPER</option><option value="lower">lower</option><option value="title">Title</option>
                                        </select>
                                        <input v-model="row.values" placeholder="male=M" class="col-span-2 rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    </div>
                                </div>
                            </div>

                            <!-- Constants -->
                            <div class="mt-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium text-gray-500">Constant fields</p>
                                    <button type="button" @click="addPair(e.static_fields)" class="text-xs text-blue-600 hover:underline">+ Add</button>
                                </div>
                                <p class="text-xs text-gray-400">Always sent, e.g. <code>slipType = standard</code>. <code>{reference}</code> inserts our transaction reference.</p>
                                <div v-for="(sf, si) in e.static_fields" :key="si" class="mt-2 flex gap-2">
                                    <input v-model="sf.key" placeholder="slipType" class="w-1/3 rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    <input v-model="sf.value" placeholder="standard" class="flex-1 rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    <button type="button" @click="removeAt(e.static_fields, si)" class="px-2 text-red-500">×</button>
                                </div>
                            </div>

                            <!-- Success rule -->
                            <div class="mt-4">
                                <p class="text-xs font-medium text-gray-500">Success rule <span class="font-normal text-gray-400">— leave blank to auto-detect</span></p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-4">
                                    <input v-model="e.success_rule.path" placeholder="status" class="rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    <input v-model="e.success_rule.in" placeholder="success, 111111" class="rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    <input v-model="e.success_rule.error_path" placeholder="error" class="rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    <input v-model="e.success_rule.data_path" placeholder="data" class="rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                </div>
                                <p class="mt-1 text-xs text-gray-400">Field · accepted values · error field · field that must contain the record.</p>
                            </div>

                            <!-- Response overrides -->
                            <div class="mt-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium text-gray-500">Response overrides</p>
                                    <button type="button" @click="addMapping(e.response_map)" class="text-xs text-blue-600 hover:underline">+ Add</button>
                                </div>
                                <p class="text-xs text-gray-400">Only needed when the normalizer misreads a field. Canonical name → dotted path, e.g. <code>last_name</code> → <code>data.surname</code>.</p>
                                <div v-for="(rm, mi) in e.response_map" :key="mi" class="mt-2 flex gap-2">
                                    <input v-model="rm.field" placeholder="last_name" class="w-1/3 rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    <input v-model="rm.path" placeholder="data.surname" class="flex-1 rounded-lg border-gray-300 font-mono text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    <button type="button" @click="removeAt(e.response_map, mi)" class="px-2 text-red-500">×</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="mt-6">
                        <label class="text-sm text-gray-600 dark:text-gray-300">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button @click="showForm = false" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Cancel</button>
                        <button @click="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                            {{ editingId ? 'Save changes' : 'Create provider' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Test console ──────────────────────────────────────────── -->
            <div v-if="testingFor" class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4" @click.self="testingFor = null">
                <div class="my-8 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Test {{ testingFor.providerName }}
                    </h2>
                    <p class="text-sm text-gray-500">{{ serviceMeta(testingFor.service).label }}</p>
                    <p class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                        This sends a real request. Nobody is charged in the wallet, but the provider may bill your account for it.
                    </p>

                    <div class="mt-4 space-y-3">
                        <div v-for="input in serviceMeta(testingFor.service).inputs" :key="input">
                            <label class="text-sm text-gray-600 dark:text-gray-300">
                                {{ inputLabel(input) }}
                                <span v-if="serviceMeta(testingFor.service).required.includes(input)" class="text-red-500">*</span>
                            </label>
                            <input v-model="testInput[input]" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                :placeholder="input === 'date_of_birth' ? 'YYYY-MM-DD' : ''" />
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-3">
                        <button @click="testingFor = null" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Close</button>
                        <button @click="runTest" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Send test request</button>
                    </div>

                    <div v-if="testResult" class="mt-6 space-y-3 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="outcomeClass(testResult.outcome)">{{ testResult.outcome }}</span>
                            <span class="text-xs text-gray-500">HTTP {{ testResult.http_status ?? '—' }} · {{ testResult.duration_ms }}ms</span>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ testResult.message }}</p>

                        <details open>
                            <summary class="cursor-pointer text-xs font-medium text-gray-500">Normalized result</summary>
                            <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-green-300">{{ JSON.stringify(testResult.normalized, null, 2) }}</pre>
                        </details>
                        <details>
                            <summary class="cursor-pointer text-xs font-medium text-gray-500">Request sent</summary>
                            <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-300">{{ JSON.stringify(testResult.request, null, 2) }}</pre>
                        </details>
                        <details>
                            <summary class="cursor-pointer text-xs font-medium text-gray-500">Raw response</summary>
                            <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-300">{{ JSON.stringify(testResult.raw, null, 2) }}</pre>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
