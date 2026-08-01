<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';

const showingSidebar = ref(false);
const expandedMenus = ref(new Set());
const page = usePage();

const appName = computed(() => page.props.app?.name || 'ABC Services');
const authUser = computed(() => page.props.auth?.user ?? {});

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
            { name: 'Vendor Calls', route: 'admin.data-attempts.index' },
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

/** Location label for the mobile top bar. */
const currentSection = computed(() => {
    for (const item of menuItems) {
        if (item.children) {
            const child = item.children.find((c) => isActive(c.route));
            if (child) return `${item.name} · ${child.name}`;
        } else if (isActive(item.route)) {
            return item.name;
        }
    }
    return 'Admin';
});

watch(() => page.url, () => (showingSidebar.value = false));
</script>

<template>
    <div class="min-h-screen bg-canvas dark:bg-ink-950">
        <!-- Matches the agent rail (white in light, near-black in dark). The
             amber ADMIN badge under the wordmark is what keeps this side of the
             product from being mistaken for the other, not the rail colour. -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-[17rem] flex-col border-r border-ink-200 bg-white transition-transform duration-300 ease-out dark:border-ink-800 dark:bg-ink-950 md:translate-x-0',
                showingSidebar ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 px-5">
                <Link :href="route('admin.dashboard')" class="flex min-w-0 items-center gap-3">
                    <ApplicationLogo class="h-8 w-8 shrink-0 text-ink-900 dark:text-white" />
                    <span class="min-w-0">
                        <span class="block truncate font-display text-base font-bold leading-tight tracking-tight text-ink-900 dark:text-white">
                            {{ appName }}
                        </span>
                        <span class="block text-2xs font-semibold uppercase tracking-[0.14em] text-brass-600 dark:text-brass-400">Admin</span>
                    </span>
                </Link>

                <button
                    @click="showingSidebar = false"
                    type="button"
                    class="ml-auto -mr-1 inline-flex h-9 w-9 items-center justify-center rounded-lg text-ink-500 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-400 dark:hover:bg-ink-800 dark:hover:text-white md:hidden"
                >
                    <span class="sr-only">Close menu</span>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="scroll-slim flex-1 overflow-y-auto px-3 py-2">
                <ul class="space-y-0.5">
                    <li v-for="item in menuItems" :key="item.name">
                        <!-- Leaf -->
                        <Link
                            v-if="!item.children"
                            :href="route(item.route)"
                            :aria-current="isActive(item.route) ? 'page' : undefined"
                            :class="[
                                'relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition',
                                isActive(item.route)
                                    ? 'bg-ink-100 font-semibold text-ink-900 dark:bg-ink-800 dark:text-white'
                                    : 'font-medium text-ink-600 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-300 dark:hover:bg-ink-800/60 dark:hover:text-white',
                            ]"
                        >
                            <span
                                v-if="isActive(item.route)"
                                class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-brand-500"
                                aria-hidden="true"
                            ></span>
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                            </svg>
                            <span class="truncate">{{ item.name }}</span>
                        </Link>

                        <!-- Group -->
                        <div v-else>
                            <button
                                type="button"
                                @click="toggleMenu(item.name)"
                                :aria-expanded="isMenuExpanded(item.name)"
                                :class="[
                                    'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition',
                                    isMenuActive(item)
                                        ? 'font-semibold text-ink-900 dark:text-white'
                                        : 'font-medium text-ink-600 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-300 dark:hover:bg-ink-800/60 dark:hover:text-white',
                                ]"
                            >
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                                </svg>
                                <span class="truncate">{{ item.name }}</span>
                                <svg
                                    :class="['ml-auto h-4 w-4 shrink-0 transition-transform duration-200', isMenuExpanded(item.name) ? 'rotate-180' : '']"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div v-show="isMenuExpanded(item.name)" class="ml-[1.55rem] mt-0.5 space-y-0.5 border-l border-ink-200 pl-3 dark:border-ink-800">
                                <Link
                                    v-for="child in item.children"
                                    :key="child.route"
                                    :href="route(child.route)"
                                    :aria-current="isActive(child.route) ? 'page' : undefined"
                                    :class="[
                                        'relative block rounded-md px-3 py-2 text-sm transition',
                                        isActive(child.route)
                                            ? 'bg-ink-100 font-semibold text-ink-900 dark:bg-ink-800 dark:text-white'
                                            : 'text-ink-500 hover:bg-ink-100 hover:text-ink-900 dark:text-ink-400 dark:hover:bg-ink-800/60 dark:hover:text-white',
                                    ]"
                                >
                                    <span
                                        v-if="isActive(child.route)"
                                        class="absolute -left-[13px] top-1/2 h-4 w-0.5 -translate-y-1/2 rounded-full bg-brand-500"
                                        aria-hidden="true"
                                    ></span>
                                    {{ child.name }}
                                </Link>
                            </div>
                        </div>
                    </li>
                </ul>
            </nav>

            <div class="shrink-0 border-t border-ink-200 p-3 dark:border-ink-800">
                <Link
                    :href="route('dashboard')"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-ink-600 transition hover:bg-ink-100 hover:text-ink-900 dark:text-ink-300 dark:hover:bg-ink-800 dark:hover:text-white"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to site
                </Link>
            </div>
        </aside>

        <!-- Main -->
        <div class="md:pl-[17rem]">
            <header class="sticky top-0 z-30 border-b border-ink-200 bg-white/85 backdrop-blur dark:border-ink-800 dark:bg-ink-950/85">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button
                        @click="showingSidebar = true"
                        type="button"
                        class="-ml-1 inline-flex h-10 w-10 items-center justify-center rounded-lg text-ink-600 transition hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800 md:hidden"
                    >
                        <span class="sr-only">Open menu</span>
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- min-w-0 or `truncate` can't shrink; see AuthenticatedLayout. -->
                    <p class="min-w-0 truncate text-sm font-semibold text-ink-900 dark:text-ink-100 md:hidden">
                        {{ currentSection }}
                    </p>

                    <div class="ml-auto flex items-center gap-2">
                        <span class="pill pill-brass hidden sm:inline-flex">Admin</span>
                        <DarkModeToggle />
                        <span class="hidden text-sm font-semibold text-ink-700 dark:text-ink-200 sm:block">
                            {{ authUser.name }}
                        </span>
                    </div>
                </div>

                <div v-if="$slots.header" class="border-t border-ink-200 px-4 py-4 dark:border-ink-800 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <slot />
            </main>
        </div>

        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showingSidebar"
                @click="showingSidebar = false"
                class="fixed inset-0 z-40 bg-ink-950/60 backdrop-blur-sm md:hidden"
                aria-hidden="true"
            ></div>
        </transition>
    </div>
</template>
