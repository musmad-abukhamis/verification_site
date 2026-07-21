<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import GlobalNotificationModal from '@/Components/GlobalNotificationModal.vue';

const page = usePage();
const showingSidebar = ref(false);
const expandedMenus = ref(new Set());

const authUser = computed(() => page.props.auth?.user ?? {});
const isAdmin = computed(() => authUser.value?.is_admin);

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

// Active state uses Ziggy's route().current() (proven elsewhere in this app).
const current = (pattern) => route().current(pattern);

const menuItems = computed(() => [
    { name: 'Dashboard', route: 'dashboard', pattern: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Buy Data', route: 'buy-data', pattern: 'buy-data*', icon: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0' },
    { name: 'My Data Purchases', route: 'data-transactions.index', pattern: 'data-transactions.*', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    {
        name: 'Wallet',
        icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        children: [
            { name: 'Overview', route: 'wallet.index', pattern: 'wallet.index' },
            { name: 'Fund Wallet', route: 'wallet.fund', pattern: 'wallet.fund' },
            { name: 'Transactions', route: 'wallet.transactions', pattern: 'wallet.transactions' },
        ],
    },
    {
        name: 'NIN Services',
        icon: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2',
        children: [
            { name: 'Verify NIN', route: 'nin.verify.index', pattern: 'nin.verify.*' },
            { name: 'NIN Validation', route: 'nin.validation.index', pattern: 'nin.validation.*' },
            { name: 'IPE Clearance', route: 'nin.ipe.index', pattern: 'nin.ipe.*' },
        ],
    },
    {
        name: 'BVN Services',
        icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        children: [
            { name: 'BVN Search', route: 'bvn-search.index', pattern: 'bvn-search.*' },
            { name: 'Modification', route: 'bvn-modification.index', pattern: 'bvn-modification.index' },
            { name: 'My Modifications', route: 'bvn-modification.requests', pattern: 'bvn-modification.requests' },
            { name: 'Onboarding', route: 'bvn-sdk-form.index', pattern: 'bvn-sdk-form.*' },
            { name: 'Retrieval', route: 'bvn-retrieval.index', pattern: 'bvn-retrieval.*' },
            { name: 'ID Card', route: 'idcard.index', pattern: 'idcard.*' },
            { name: 'BVN Records', route: 'bvn-records.index', pattern: 'bvn-records.*' },
        ],
    },
    { name: 'Verification History', route: 'verification.history', pattern: 'verification.history', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    {
        name: 'Reports',
        icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        children: [
            { name: 'Data Transactions', route: 'reports.data-transactions', pattern: 'reports.data-transactions' },
            { name: 'NIN/BVN Transactions', route: 'reports.verify-transactions', pattern: 'reports.verify-transactions' },
            { name: 'Data Sub Stats', route: 'reports.data-stats', pattern: 'reports.data-stats' },
            { name: 'NIN/BVN Verify Stats', route: 'reports.verify-stats', pattern: 'reports.verify-stats' },
        ],
    },
    { name: 'API Access', route: 'api-access.index', pattern: 'api-access.*', icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' },
    { name: 'Profile', route: 'profile.edit', pattern: 'profile.edit', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { name: 'Help & Support', route: 'help.index', pattern: 'help.*', icon: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
]);

const toggleMenu = (name) => {
    const set = expandedMenus.value;
    set.has(name) ? set.delete(name) : set.add(name);
};

const isGroupActive = (item) => item.children?.some((c) => current(c.pattern)) ?? false;
const isMenuExpanded = (item) => expandedMenus.value.has(item.name) || isGroupActive(item);
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed top-0 left-0 z-40 w-64 h-screen transition-transform bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700',
                showingSidebar ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
            ]"
        >
            <div class="h-full flex flex-col">
                <!-- Logo -->
                <div class="flex items-center h-16 px-4 border-b border-gray-200 dark:border-gray-700">
                    <Link :href="route('dashboard')" class="flex items-center">
                        <ApplicationLogo class="block h-8 w-auto text-gray-800 dark:text-gray-200" />
                    </Link>
                </div>

                <!-- User profile -->
                <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold uppercase">
                            {{ (authUser.username || authUser.name || '?').charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ authUser.username || authUser.name }}
                            </p>
                            <span class="inline-block mt-0.5 px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">
                                {{ formatCurrency(authUser.balance) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto px-3 py-4">
                    <ul class="space-y-1">
                        <li v-for="item in menuItems" :key="item.name">
                            <!-- Leaf item -->
                            <Link
                                v-if="!item.children"
                                :href="route(item.route)"
                                @click="showingSidebar = false"
                                :class="[
                                    'flex items-center p-3 rounded-lg text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors',
                                    current(item.pattern) && 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400',
                                ]"
                            >
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                </svg>
                                <span class="ml-3 text-sm">{{ item.name }}</span>
                            </Link>

                            <!-- Group item -->
                            <div v-else>
                                <button
                                    type="button"
                                    @click="toggleMenu(item.name)"
                                    :class="[
                                        'w-full flex items-center justify-between p-3 rounded-lg text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors',
                                        isGroupActive(item) && 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400',
                                    ]"
                                >
                                    <span class="flex items-center">
                                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                                        </svg>
                                        <span class="ml-3 text-sm">{{ item.name }}</span>
                                    </span>
                                    <svg
                                        :class="['w-4 h-4 transition-transform', isMenuExpanded(item) ? 'rotate-180' : '']"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div v-show="isMenuExpanded(item)" class="ml-6 mt-1 space-y-1">
                                    <Link
                                        v-for="child in item.children"
                                        :key="child.route"
                                        :href="route(child.route)"
                                        @click="showingSidebar = false"
                                        :class="[
                                            'block px-3 py-2 text-sm rounded-lg transition-colors',
                                            current(child.pattern)
                                                ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400'
                                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white',
                                        ]"
                                    >
                                        {{ child.name }}
                                    </Link>
                                </div>
                            </div>
                        </li>

                        <!-- Admin link -->
                        <li v-if="isAdmin">
                            <Link
                                :href="route('admin.dashboard')"
                                @click="showingSidebar = false"
                                :class="[
                                    'flex items-center p-3 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors',
                                    current('admin.*') && 'bg-red-50 dark:bg-red-900/20',
                                ]"
                            >
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="ml-3 text-sm font-medium">Admin Panel</span>
                            </Link>
                        </li>
                    </ul>
                </nav>

                <!-- Footer: logout -->
                <div class="px-3 py-4 border-t border-gray-200 dark:border-gray-700">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="w-full flex items-center p-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="ml-3 text-sm">Log Out</span>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main column -->
        <div class="md:ml-64">
            <!-- Top bar -->
            <nav class="sticky top-0 z-30 bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <!-- Mobile hamburger -->
                        <button
                            @click="showingSidebar = !showingSidebar"
                            class="md:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none"
                        >
                            <span class="sr-only">Toggle sidebar</span>
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <div class="flex items-center ml-auto space-x-4">
                            <NotificationBell />
                            <DarkModeToggle />
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none">
                                        {{ authUser.name }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Optional page heading -->
            <header class="sticky top-16 z-20 bg-white shadow dark:bg-gray-800" v-if="$slots.header">
                <div class="py-5 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page content -->
            <main>
                <div class="py-6 px-4 sm:px-6 lg:px-8">
                    <slot />
                </div>
            </main>
        </div>

        <!-- Mobile overlay -->
        <div
            v-if="showingSidebar"
            @click="showingSidebar = false"
            class="fixed inset-0 z-30 bg-gray-900/50 md:hidden"
        ></div>

        <!-- Global announcement modal -->
        <GlobalNotificationModal />
    </div>
</template>
