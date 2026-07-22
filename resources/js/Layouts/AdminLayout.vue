<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';

const showingSidebar = ref(false);
const expandedMenus = ref(new Set());
const page = usePage();

const toggleMenu = (menuName) => {
    if (expandedMenus.value.has(menuName)) {
        expandedMenus.value.delete(menuName);
    } else {
        expandedMenus.value.add(menuName);
    }
};

const menuItems = [
    { name: 'Dashboard', route: 'admin.dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Users', route: 'admin.users.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'Transactions', route: 'admin.transactions.index', icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
    {
        name: 'Wallet',
        icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        children: [
            { name: 'Account Funding', route: 'admin.wallet.index' },
            { name: 'Transactions', route: 'admin.wallet.transactions' },
            { name: 'Unattributed Payments', route: 'admin.wallet.unattributed.index' },
        ]
    },
    {
        name: 'NIN Services',
        icon: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2',
        children: [
            { name: 'Validations', route: 'admin.nin-validations.index' },
            { name: 'Service Prices', route: 'admin.service-prices.index' },
        ]
    },
    {
        name: 'BVN Services',
        icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        children: [
            { name: 'Modifications', route: 'admin.bvn-modifications.index' },
            { name: 'SDK Onboarding', route: 'admin.bvn-sdk-forms.index' },
            { name: 'Retrievals', route: 'admin.bvn-retrievals.index' },
            { name: 'Searches', route: 'admin.bvn-searches.index' },
            { name: 'Enrollment Records', route: 'admin.enrollment-records.index' },
            { name: 'Service Prices', route: 'admin.bvn-prices.index' },
        ]
    },
    {
        name: 'Verification Engine',
        icon: 'M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-3.999',
        children: [
            { name: 'Providers', route: 'admin.verification-providers.index' },
            { name: 'Routing & Failover', route: 'admin.verification-routing.index' },
            { name: 'Provider Calls', route: 'admin.verification-attempts.index' },
        ]
    },
    {
        name: 'Data (VTU)',
        icon: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
        children: [
            { name: 'Plans', route: 'admin.dataplan.index' },
            { name: 'Vendors', route: 'admin.vendors.index' },
            { name: 'Routing & Settings', route: 'admin.data.routing.index' },
            { name: 'Transactions', route: 'admin.data-transactions.index' },
            { name: 'Wallet Adjustments', route: 'admin.data-wallet.index' },
        ]
    },
    { name: 'Verification Logs', route: 'admin.verification-logs.index', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { name: 'Notifications', route: 'admin.notifications.index', icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' },
    { name: 'Agent ID Card', route: 'admin.agent-id.index', icon: 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z' },
    { name: 'ID Card Requests', route: 'admin.idcard.index', icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z' },
    {
        name: 'Reports',
        icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        children: [
            { name: 'NIN/BVN Transactions', route: 'admin.reports.verify-transactions' },
            { name: 'Data Sub Stats', route: 'admin.reports.data-stats' },
            { name: 'Verification Stats', route: 'admin.reports.verify-stats' },
        ]
    },
    { name: 'Settings', route: 'admin.settings.index', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    { name: 'Site Settings', route: 'admin.site-settings.index', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
];

const isActive = (routeName) => {
    return page.url.startsWith(route(routeName));
};

const isMenuActive = (item) => {
    if (item.children) {
        return item.children.some(child => isActive(child.route));
    }
    return isActive(item.route);
};

const isMenuExpanded = (menuName) => {
    return expandedMenus.value.has(menuName) || isMenuActive({ children: menuItems.find(i => i.name === menuName)?.children });
};
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed top-0 left-0 z-40 w-64 h-screen transition-transform bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700',
                showingSidebar ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
            ]"
        >
            <div class="h-full px-3 py-4 overflow-y-auto">
                <!-- Logo -->
                <div class="flex items-center mb-8 px-2">
                    <Link :href="route('admin.dashboard')" class="flex items-center">
                        <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center mr-3">
                            <span class="text-white font-bold text-xl">A</span>
                        </div>
                        <span class="text-xl font-semibold text-gray-900 dark:text-white">Admin</span>
                    </Link>
                </div>

                <!-- Navigation -->
                <ul class="space-y-2">
                    <li v-for="item in menuItems" :key="item.name">
                        <!-- Regular menu item -->
                        <Link
                            v-if="!item.children"
                            :href="route(item.route)"
                            :class="[
                                'flex items-center p-3 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group transition-colors',
                                isActive(item.route) && 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400'
                            ]"
                        >
                            <svg class="w-5 h-5 transition duration-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                            </svg>
                            <span class="ml-3">{{ item.name }}</span>
                        </Link>

                        <!-- Dropdown menu item -->
                        <div v-else>
                            <button
                                @click="toggleMenu(item.name)"
                                :class="[
                                    'w-full flex items-center justify-between p-3 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group transition-colors',
                                    isMenuActive(item) && 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400'
                                ]"
                            >
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 transition duration-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                    </svg>
                                    <span class="ml-3">{{ item.name }}</span>
                                </div>
                                <svg
                                    :class="[
                                        'w-4 h-4 transition-transform duration-200',
                                        isMenuExpanded(item.name) ? 'rotate-180' : ''
                                    ]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <!-- Submenu -->
                            <div
                                v-show="isMenuExpanded(item.name)"
                                class="ml-6 mt-1 space-y-1"
                            >
                                <Link
                                    v-for="child in item.children"
                                    :key="child.route"
                                    :href="route(child.route)"
                                    :class="[
                                        'block px-3 py-2 text-sm rounded-lg transition-colors',
                                        isActive(child.route)
                                            ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400'
                                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                                    ]"
                                >
                                    {{ child.name }}
                                </Link>
                            </div>
                        </div>
                    </li>
                </ul>

                <!-- Back to Site -->
                <div class="mt-8 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <Link
                        :href="route('dashboard')"
                        class="flex items-center p-3 text-gray-600 rounded-lg dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span class="ml-3">Back to Site</span>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="md:ml-64">
            <!-- Top Navigation -->
            <nav class="bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <!-- Mobile menu button -->
                        <div class="flex items-center md:hidden">
                            <button
                                @click="showingSidebar = !showingSidebar"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                        </div>

                        <!-- Right side -->
                        <div class="flex items-center ml-auto space-x-4">
                            <DarkModeToggle />
                            
                            <!-- User Dropdown -->
                            <div class="relative">
                                <button class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                                    <span class="mr-2">{{ $page.props.auth.user.name }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <main class="py-6">
                <div class="px-4 sm:px-6 lg:px-8">
                    <slot />
                </div>
            </main>
        </div>

        <!-- Mobile Sidebar Overlay -->
        <div
            v-if="showingSidebar"
            @click="showingSidebar = false"
            class="fixed inset-0 z-30 bg-gray-900/50 md:hidden"
        ></div>
    </div>
</template>
