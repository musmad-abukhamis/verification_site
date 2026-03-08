<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    wallet: Object,
    recent_transactions: Array,
});

const getStatusColor = (status) => {
    const colors = {
        success: 'text-green-600 bg-green-100',
        pending: 'text-yellow-600 bg-yellow-100',
        failed: 'text-red-600 bg-red-100',
        refunded: 'text-gray-600 bg-gray-100',
    };
    return colors[status] || 'text-gray-600 bg-gray-100';
};

const getTypeLabel = (type) => {
    const labels = {
        airtime: 'Airtime Purchase',
        data: 'Data Purchase',
        nin_verification: 'NIN Verification',
        bvn_verification: 'BVN Verification',
        wallet_funding: 'Wallet Funding',
        refund: 'Refund',
    };
    return labels[type] || type;
};
</script>

<template>
    <Head title="My Wallet" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Wallet</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Balance Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Total Balance -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                            <h3 class="text-sm font-medium opacity-90">Total Balance</h3>
                            <p class="text-3xl font-bold mt-1">₦{{ wallet.total_balance.toLocaleString() }}</p>
                        </div>
                    </div>

                    <!-- Main Balance -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500">Main Balance</h3>
                            <p class="text-2xl font-bold text-gray-900 mt-1">₦{{ wallet.balance.toLocaleString() }}</p>
                        </div>
                    </div>

                    <!-- Bonus Balance -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500">Bonus Balance</h3>
                            <p class="text-2xl font-bold text-gray-900 mt-1">₦{{ wallet.bonus_balance.toLocaleString() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                        <div class="flex flex-wrap gap-4">
                            <Link :href="route('wallet.fund')">
                                <PrimaryButton>Fund Wallet</PrimaryButton>
                            </Link>
                            <Link :href="route('vtu.airtime')">
                                <PrimaryButton class="bg-green-600 hover:bg-green-700">Buy Airtime</PrimaryButton>
                            </Link>
                            <Link :href="route('vtu.data')">
                                <PrimaryButton class="bg-blue-600 hover:bg-blue-700">Buy Data</PrimaryButton>
                            </Link>
                            <Link :href="route('verification.nin')">
                                <PrimaryButton class="bg-purple-600 hover:bg-purple-700">Verify ID</PrimaryButton>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Recent Transactions</h3>
                            <Link :href="route('wallet.transactions')" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                View All →
                            </Link>
                        </div>

                        <div v-if="recent_transactions.length === 0" class="text-center py-8 text-gray-500">
                            No transactions yet.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="transaction in recent_transactions" :key="transaction.id">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ transaction.reference }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ getTypeLabel(transaction.type) }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">₦{{ transaction.amount.toLocaleString() }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(transaction.status)]">
                                                {{ transaction.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ transaction.date }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
