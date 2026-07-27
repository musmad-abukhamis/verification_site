<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    recentEntries: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
let timer;
watch(search, (v) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.data-wallet.index'), { search: v }, { preserveState: true, replace: true });
    }, 300);
});

const money = (n) => '₦' + Number(n ?? 0).toLocaleString('en-NG');

const adjust = (user, direction) => {
    const amount = prompt(`${direction === 'credit' ? 'Credit' : 'Debit'} amount for ${user.name || user.username}:`);
    if (!amount || isNaN(Number(amount)) || Number(amount) <= 0) return;
    const routeName = direction === 'credit' ? 'admin.data-wallet.credit' : 'admin.data-wallet.debit';
    router.post(route(routeName, user.id), { amount: Number(amount) }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Wallet Adjustments" />
    <AdminLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Wallet Adjustments</h1>

            <input v-model="search" placeholder="Search users by name / username / email / phone…" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />

            <div v-if="users.length" class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40">
                        <tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3 text-right">Adjust</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                        <tr v-for="u in users" :key="u.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ u.name || u.username }}</p>
                                <p class="text-xs text-gray-400">{{ u.email }} · {{ u.phone }}</p>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ money(u.balance) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="adjust(u, 'credit')" class="mr-2 rounded bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40">Credit</button>
                                <button @click="adjust(u, 'debit')" class="rounded bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/40">Debit</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else-if="search" class="text-sm text-gray-500">No users match “{{ search }}”.</p>

            <div class="rounded-2xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Recent ledger entries</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-2 pr-4">User</th><th class="py-2 pr-4">Direction</th><th class="py-2 pr-4">Amount</th><th class="py-2 pr-4">Balance after</th><th class="py-2 pr-4">Reason</th><th class="py-2 pr-4">At</th></tr></thead>
                        <tbody>
                            <tr v-for="e in recentEntries" :key="e.id" class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 pr-4">{{ e.user }}</td>
                                <td class="py-2 pr-4" :class="e.direction === 'credit' ? 'text-green-600' : 'text-red-600'">{{ e.direction }}</td>
                                <td class="py-2 pr-4">{{ money(e.amount) }}</td>
                                <td class="py-2 pr-4">{{ money(e.balance_after) }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ e.reason }}</td>
                                <td class="py-2 pr-4 text-gray-400">{{ e.at }}</td>
                            </tr>
                            <tr v-if="!recentEntries.length"><td colspan="6" class="py-4 text-center text-gray-400">No ledger entries yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
