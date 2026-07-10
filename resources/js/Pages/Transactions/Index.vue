<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    transactions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');

let timer = null;
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('data-transactions.index'), { search: search.value, status: status.value }, {
            preserveState: true,
            replace: true,
            only: ['transactions', 'filters'],
        });
    }, 300);
});

const money = (n) => '₦' + Number(n ?? 0).toLocaleString('en-NG');

const badge = (s) => ({
    success: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    fail: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    refunded: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    refunded_unconfirmed: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
}[s] || 'bg-gray-100 text-gray-700');
</script>

<template>
    <Head title="My Data Purchases" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">My Data Purchases</h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search reference, plan, phone…"
                    class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                />
                <select
                    v-model="status"
                    class="rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                >
                    <option value="all">All statuses</option>
                    <option value="success">Success</option>
                    <option value="processing">Processing</option>
                    <option value="pending">Pending</option>
                    <option value="fail">Failed</option>
                    <option value="refunded">Refunded</option>
                    <option value="refunded_unconfirmed">Refunded (unconfirmed)</option>
                </select>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                        <tr v-for="t in transactions.data" :key="t.reference">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ t.network?.toUpperCase() }} · {{ t.plan_name }}</p>
                                <p class="font-mono text-xs text-gray-400">{{ t.reference }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ t.phone }}</td>
                            <td class="px-4 py-3 font-medium">{{ money(t.price) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold capitalize" :class="badge(t.status)">
                                    {{ t.status.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ t.date }}</td>
                        </tr>
                        <tr v-if="!transactions.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">No purchases yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="transactions.links && transactions.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <template v-for="(link, i) in transactions.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="rounded-lg px-3 py-1 text-sm"
                        :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-lg px-3 py-1 text-sm text-gray-300"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
