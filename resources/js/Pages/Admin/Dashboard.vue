<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    recentTransactions: Array,
    recentUsers: Array,
    chartData: Array,
});

const statCards = [
    { name: 'Total Users', value: props.stats.total_users, icon: 'users', color: 'blue' },
    { name: 'Total Revenue', value: '₦' + props.stats.total_revenue.toLocaleString(), icon: 'money', color: 'green' },
    { name: 'Total Transactions', value: props.stats.total_transactions, icon: 'transaction', color: 'purple' },
    { name: 'Pending Transactions', value: props.stats.pending_transactions, icon: 'clock', color: 'yellow' },
    { name: 'Verifications', value: props.stats.total_verifications, icon: 'check', color: 'indigo' },
    { name: 'Wallet Balance', value: '₦' + props.stats.total_wallet_balance.toLocaleString(), icon: 'wallet', color: 'pink' },
];

const getStatusColor = (status) => {
    const colors = {
        success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="stat in statCards"
                    :key="stat.name"
                    class="bg-white dark:bg-gray-800 rounded-lg shadow p-6"
                >
                    <div class="flex items-center">
                        <div :class="`p-3 rounded-lg bg-${stat.color}-100 dark:bg-${stat.color}-900`">
                            <svg class="w-6 h-6" :class="`text-${stat.color}-600 dark:text-${stat.color}-400`" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ stat.name }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Management Section — rebuilt on the normalized schema in Phase 2 -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Data Management</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Plans, vendors, routing and transactions</p>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        The data-module admin (Plans CRUD, Vendors, routing matrix, failover
                        settings, network prefixes and the transactions/exceptions report) is
                        being rebuilt on the new normalized vendor schema and will appear here shortly.
                    </p>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Transactions -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Transactions</h3>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div
                            v-for="transaction in recentTransactions"
                            :key="transaction.id"
                            class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ transaction.user }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ transaction.type }} - {{ transaction.reference }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-900 dark:text-white">₦{{ transaction.amount.toLocaleString() }}</p>
                                <span
                                    :class="getStatusColor(transaction.status)"
                                    class="px-2 py-1 text-xs rounded-full"
                                >
                                    {{ transaction.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Users</h3>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div
                            v-for="user in recentUsers"
                            :key="user.id"
                            class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center">
                                    <span class="text-indigo-600 dark:text-indigo-400 font-medium">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div class="ml-3">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                </div>
                            </div>
                            <span
                                v-if="user.is_admin"
                                class="px-2 py-1 text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 rounded-full"
                            >
                                Admin
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
