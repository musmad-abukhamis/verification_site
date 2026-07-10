<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    transactions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    exceptionsCount: { type: Number, default: 0 },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
let timer;
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.data-transactions.index'), { search: search.value, status: status.value }, { preserveState: true, replace: true });
    }, 300);
});

const money = (n) => '₦' + Number(n ?? 0).toLocaleString('en-NG');
const badge = (s) => ({
    success: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40',
    pending: 'bg-gray-100 text-gray-600 dark:bg-gray-700',
    fail: 'bg-red-100 text-red-600 dark:bg-red-900/40',
    refunded: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40',
    refunded_unconfirmed: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40',
}[s] || 'bg-gray-100 text-gray-600');
</script>

<template>
    <Head title="Data Transactions" />
    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Data Transactions</h1>
                <button v-if="exceptionsCount" @click="status = 'refunded_unconfirmed'"
                    class="rounded-lg bg-orange-100 px-3 py-1 text-sm font-semibold text-orange-700 dark:bg-orange-900/40">
                    {{ exceptionsCount }} exception(s)
                </button>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <input v-model="search" placeholder="Search reference / phone / plan…" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                <select v-model="status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    <option value="all">All statuses</option>
                    <option value="success">Success</option>
                    <option value="processing">Processing</option>
                    <option value="pending">Pending</option>
                    <option value="fail">Failed</option>
                    <option value="refunded">Refunded</option>
                    <option value="refunded_unconfirmed">Refunded (unconfirmed)</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3">User / Ref</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Att.</th>
                            <th class="px-4 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                        <tr v-for="t in transactions.data" :key="t.reference" class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/30" @click="router.get(route('admin.data-transactions.show', t.reference))">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ t.user }}</p>
                                <p class="font-mono text-xs text-gray-400">{{ t.reference }}</p>
                            </td>
                            <td class="px-4 py-3">{{ t.network?.toUpperCase() }} · {{ t.plan_name }}</td>
                            <td class="px-4 py-3">{{ t.phone }}</td>
                            <td class="px-4 py-3 font-medium">{{ money(t.price) }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold capitalize" :class="badge(t.status)">{{ t.status.replace('_',' ') }}</span></td>
                            <td class="px-4 py-3 text-gray-500">{{ t.attempts }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ t.date }}</td>
                        </tr>
                        <tr v-if="!transactions.data.length"><td colspan="7" class="px-4 py-8 text-center text-gray-500">No transactions.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="transactions.links && transactions.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="(link, i) in transactions.links" :key="i">
                    <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-lg px-3 py-1 text-sm"
                        :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300'" v-html="link.label" />
                    <span v-else class="rounded-lg px-3 py-1 text-sm text-gray-300" v-html="link.label" />
                </template>
            </div>
        </div>
    </AdminLayout>
</template>
