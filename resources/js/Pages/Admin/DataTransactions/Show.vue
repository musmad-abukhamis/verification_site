<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ transaction: { type: Object, required: true } });

const money = (n) => '₦' + Number(n ?? 0).toLocaleString('en-NG');
const outcomeColor = (o) => ({
    success: 'text-green-600', fail: 'text-red-600', timeout: 'text-amber-600',
}[o] || 'text-gray-500');
</script>

<template>
    <Head title="Data Transaction" />
    <AdminLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <Link :href="route('admin.data-transactions.index')" class="text-sm text-gray-500 hover:underline">← Back to transactions</Link>

            <div class="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ transaction.plan_name }}</h1>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold capitalize dark:bg-gray-700">{{ transaction.status.replace('_',' ') }}</span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500">User</dt><dd class="font-medium">{{ transaction.user }}</dd></div>
                    <div><dt class="text-gray-500">Reference</dt><dd class="font-mono text-xs">{{ transaction.reference }}</dd></div>
                    <div><dt class="text-gray-500">Network / Type</dt><dd>{{ transaction.network?.toUpperCase() }} · {{ transaction.type }}</dd></div>
                    <div><dt class="text-gray-500">Phone</dt><dd>{{ transaction.phone }}<span v-if="transaction.ported" class="ml-1 text-xs text-amber-600">(ported)</span></dd></div>
                    <div><dt class="text-gray-500">Amount</dt><dd class="font-medium">{{ money(transaction.price) }}</dd></div>
                    <div><dt class="text-gray-500">Balance</dt><dd>{{ money(transaction.oldbal) }} → {{ money(transaction.newbal) }}</dd></div>
                    <div><dt class="text-gray-500">Vendor ref</dt><dd class="font-mono text-xs">{{ transaction.vendor_reference || '—' }}</dd></div>
                    <div><dt class="text-gray-500">Date</dt><dd>{{ transaction.date }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Vendor attempts ({{ transaction.attempts.length }})</h2>
                <div v-for="(a, i) in transaction.attempts" :key="i" class="mb-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ a.vendor }}</span>
                        <span class="text-xs font-semibold uppercase" :class="outcomeColor(a.outcome)">{{ a.outcome }}</span>
                    </div>
                    <p class="text-xs text-gray-400">{{ a.at }}</p>
                    <details class="mt-2 text-xs">
                        <summary class="cursor-pointer text-gray-500">payload / response</summary>
                        <pre class="mt-1 overflow-x-auto rounded bg-gray-50 p-2 dark:bg-gray-900/60">{{ JSON.stringify({ request: a.request, response: a.response }, null, 2) }}</pre>
                    </details>
                </div>
                <p v-if="!transaction.attempts.length" class="text-sm text-gray-400">No vendor attempts recorded.</p>
            </div>
        </div>
    </AdminLayout>
</template>
