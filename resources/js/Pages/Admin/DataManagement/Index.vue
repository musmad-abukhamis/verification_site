<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
});

const statCards = [
    { 
        title: 'Total Data Plans', 
        value: props.stats.total_plans, 
        icon: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
        color: 'bg-blue-500',
        textColor: 'text-blue-600'
    },
    { 
        title: 'Active Vendors', 
        value: props.stats.active_vendors, 
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        color: 'bg-green-500',
        textColor: 'text-green-600'
    },
    { 
        title: 'Total Transactions', 
        value: props.stats.total_transactions, 
        icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        color: 'bg-purple-500',
        textColor: 'text-purple-600'
    },
    { 
        title: 'Success Rate', 
        value: props.stats.total_transactions > 0 ? Math.round((props.stats.successful_transactions / props.stats.total_transactions) * 100) + '%' : '0%',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        color: 'bg-indigo-500',
        textColor: 'text-indigo-600'
    }
];
</script>

<template>
    <Head title="Data Management Dashboard" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Data Management Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 gap-6 mb-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div 
                        v-for="stat in statCards" 
                        :key="stat.title"
                        class="overflow-hidden bg-white rounded-lg shadow dark:bg-gray-800"
                    >
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div :class="[stat.color, 'p-3 rounded-lg']">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <dt class="text-sm font-medium text-gray-500 truncate dark:text-gray-400">
                                        {{ stat.title }}
                                    </dt>
                                    <dd :class="['text-2xl font-semibold', stat.textColor]">
                                        {{ stat.value }}
                                    </dd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Management Cards -->
                    <div class="bg-white rounded-lg shadow dark:bg-gray-800">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Data Plans</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                Manage data plans and vendor mappings
                            </p>
                            <a 
                                :href="route('admin.data-management.plans')"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Manage Plans
                            </a>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow dark:bg-gray-800">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Network IDs</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                Configure vendor network IDs for MTN, AIRTEL, GLO, 9MOBILE
                            </p>
                            <a 
                                :href="route('admin.networkid.index')"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                            >
                                Manage Network IDs
                            </a>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow dark:bg-gray-800">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Transactions</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                View and monitor data transactions
                            </p>
                            <a 
                                :href="route('admin.data-management.transactions')"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                            >
                                View Transactions
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Vendor Management -->
                <div class="mt-8 bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Vendor Management</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <a 
                                :href="route('admin.vendors.index')"
                                class="flex flex-col items-center p-4 text-center bg-gray-50 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-8 h-8 text-gray-600 dark:text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Vendors</span>
                            </a>
                            
                            <a 
                                :href="route('admin.vendors.api')"
                                class="flex flex-col items-center p-4 text-center bg-gray-50 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-8 h-8 text-gray-600 dark:text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.544-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.544-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.544.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.544.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">API Config</span>
                            </a>
                            
                            <a 
                                :href="route('admin.vendors.active')"
                                class="flex flex-col items-center p-4 text-center bg-gray-50 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-8 h-8 text-gray-600 dark:text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Active Vendors</span>
                            </a>
                            
                            <a 
                                :href="route('admin.verification-logs.index')"
                                class="flex flex-col items-center p-4 text-center bg-gray-50 rounded-lg hover:bg-gray-100 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors"
                            >
                                <svg class="w-8 h-8 text-gray-600 dark:text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Logs</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>