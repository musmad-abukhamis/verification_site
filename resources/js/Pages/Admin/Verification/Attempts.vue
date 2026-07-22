<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const props = defineProps({
    attempts: { type: Object, required: true },
    services: { type: Array, default: () => [] },
    providers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Array, default: () => [] },
});

const filters = reactive({
    service: props.filters.service ?? '',
    provider_id: props.filters.provider_id ?? '',
    outcome: props.filters.outcome ?? '',
    search: props.filters.search ?? '',
});

const expanded = ref(null);

const applyFilters = () => router.get(route('admin.verification-attempts.index'), filters, {
    preserveState: true,
    replace: true,
});

const outcomeClass = (outcome) => ({
    success: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    fail: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    timeout: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
}[outcome] ?? 'bg-gray-100 text-gray-600');

const rateClass = (rate) => rate >= 90 ? 'text-green-600' : rate >= 60 ? 'text-amber-600' : 'text-red-600';

const formatDate = (value) => value ? new Date(value).toLocaleString() : '—';
</script>

<template>
    <Head title="Verification Provider Calls" />

    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Provider Calls</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Every hop, including the ones that failed and were failed over — where a quietly broken primary shows up.
                </p>
            </div>

            <!-- 24h health per provider -->
            <div v-if="summary.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="row in summary" :key="row.provider" class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.provider }}</p>
                    <p class="mt-1 text-2xl font-bold" :class="rateClass(row.success_rate)">{{ row.success_rate }}%</p>
                    <p class="text-xs text-gray-400">{{ row.success }}/{{ row.total }} in 24h · {{ row.avg_ms }}ms avg</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid gap-3 rounded-lg bg-white p-4 shadow dark:bg-gray-800 sm:grid-cols-4">
                <select v-model="filters.service" @change="applyFilters" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">All services</option>
                    <option v-for="s in services" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="filters.provider_id" @change="applyFilters" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">All providers</option>
                    <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <select v-model="filters.outcome" @change="applyFilters" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">All outcomes</option>
                    <option value="success">Success</option>
                    <option value="fail">Fail</option>
                    <option value="timeout">Timeout</option>
                </select>
                <input v-model="filters.search" @keyup.enter="applyFilters" placeholder="Reference…"
                    class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3">Provider</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Outcome</th>
                            <th class="px-4 py-3">Message</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                        <template v-for="a in attempts.data" :key="a.id">
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{{ formatDate(a.created_at) }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ a.service_label }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ a.provider }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ a.user ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="outcomeClass(a.outcome)">{{ a.outcome }}</span>
                                    <span class="ml-1 text-xs text-gray-400">{{ a.http_status }} · {{ a.duration_ms }}ms</span>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 text-xs text-gray-500">{{ a.message }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="expanded = expanded === a.id ? null : a.id" class="text-xs text-blue-600 hover:underline">
                                        {{ expanded === a.id ? 'Hide' : 'Details' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="expanded === a.id">
                                <td colspan="7" class="bg-gray-50 px-4 py-4 dark:bg-gray-900/40">
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <div>
                                            <p class="mb-1 text-xs font-medium text-gray-500">Request (credentials removed)</p>
                                            <pre class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-300">{{ JSON.stringify(a.request_payload, null, 2) }}</pre>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-xs font-medium text-gray-500">Response</p>
                                            <pre class="max-h-64 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-300">{{ JSON.stringify(a.response, null, 2) }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!attempts.data.length">
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">No provider calls recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="attempts.links?.length > 3" class="flex flex-wrap justify-center gap-1">
                <component v-for="link in attempts.links" :key="link.label"
                    :is="link.url ? 'a' : 'span'" :href="link.url"
                    class="rounded px-3 py-1 text-sm"
                    :class="link.active ? 'bg-blue-600 text-white' : link.url ? 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300' : 'text-gray-400'"
                    v-html="link.label" />
            </div>
        </div>
    </AdminLayout>
</template>
