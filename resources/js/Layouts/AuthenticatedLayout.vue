<script setup>
import { ref, computed, watch } from 'vue';
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

const appName = computed(() => page.props.app?.name || 'ABC Services');

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

// Active state uses Ziggy's route().current() (proven elsewhere in this app).
const current = (pattern) => route().current(pattern);

const menuItems = computed(() => [
    { name: 'Dashboard', route: 'dashboard', pattern: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Buy Data', route: 'buy-data', pattern: 'buy-data*', icon: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0' },
    { name: 'Data Pricing', route: 'data-pricing', pattern: 'data-pricing', icon: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z' },
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
            { name: 'BVN Verification', route: 'bvn-verify.index', pattern: 'bvn-verify.*' },
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

/** The page title, used as the mobile top-bar label so you always know where you are. */
const currentSection = computed(() => {
    for (const item of menuItems.value) {
        if (item.children) {
            const child = item.children.find((c) => current(c.pattern));
            if (child) return `${item.name} · ${child.name}`;
        } else if (current(item.pattern)) {
            return item.name;
        }
    }
    return appName.value;
});

// Never leave the off-canvas sidebar open behind a new page.
watch(() => page.url, () => (showingSidebar.value = false));
</script>

<template>
    <div class="min-h-screen bg-ink-100 dark:bg-ink-950">
        <!-- =====================================================================
             Sidebar. Deep pine in both themes: the chrome is the instrument,
             the content area is the paper you work on.
             ===================================================================== -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-[17rem] flex-col bg-brand-950 transition-transform duration-300 ease-out md:translate-x-0',
                showingSidebar ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Brand -->
            <div class="flex h-16 shrink-0 items-center gap-3 px-5">
                <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-3">
                    <ApplicationLogo class="h-8 w-8 shrink-0 text-white" />
                    <span class="truncate font-display text-base font-bold tracking-tight text-white">
                        {{ appName }}
                    </span>
                </Link>

                <button
                    @click="showingSidebar = false"
                    type="button"
                    class="ml-auto -mr-1 inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-300 hover:bg-brand-900 hover:text-white md:hidden"
                >
                    <span class="sr-only">Close menu</span>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Wallet slip. The balance is the number an agent checks most, so
                 it gets the accent and sits above the navigation, not inside it. -->
            <div class="px-4 pb-4">
                <div class="slip-guilloche overflow-hidden rounded-card border border-brass-500/30 bg-brand-900/70">
                    <div class="px-4 pt-3.5">
                        <p class="text-2xs font-semibold uppercase tracking-[0.14em] text-brass-300">Wallet balance</p>
                        <p class="mt-1 font-mono text-xl font-semibold tracking-tight text-white">
                            {{ formatCurrency(authUser.balance) }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-stretch border-t border-dashed border-brass-500/30">
                        <Link
                            :href="route('wallet.fund')"
                            class="flex-1 px-4 py-2.5 text-center text-xs font-semibold text-brass-200 transition hover:bg-brass-500/10 hover:text-white"
                        >
                            Fund wallet
                        </Link>
                        <span class="w-px bg-brass-500/20" aria-hidden="true"></span>
                        <Link
                            :href="route('wallet.transactions')"
                            class="flex-1 px-4 py-2.5 text-center text-xs font-semibold text-brand-200 transition hover:bg-brass-500/10 hover:text-white"
                        >
                            History
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="scroll-slim flex-1 overflow-y-auto px-3 pb-4">
                <ul class="space-y-0.5">
                    <li v-for="item in menuItems" :key="item.name">
                        <!-- Leaf item -->
                        <Link
                            v-if="!item.children"
                            :href="route(item.route)"
                            :aria-current="current(item.pattern) ? 'page' : undefined"
                            :class="[
                                'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition',
                                current(item.pattern)
                                    ? 'bg-brand-900 font-semibold text-white'
                                    : 'font-medium text-brand-200 hover:bg-brand-900/60 hover:text-white',
                            ]"
                        >
                            <span
                                v-if="current(item.pattern)"
                                class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-brass-500"
                                aria-hidden="true"
                            ></span>
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                            </svg>
                            <span class="truncate">{{ item.name }}</span>
                        </Link>

                        <!-- Group item -->
                        <div v-else>
                            <button
                                type="button"
                                @click="toggleMenu(item.name)"
                                :aria-expanded="isMenuExpanded(item)"
                                :class="[
                                    'relative flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition',
                                    isGroupActive(item)
                                        ? 'font-semibold text-white'
                                        : 'font-medium text-brand-200 hover:bg-brand-900/60 hover:text-white',
                                ]"
                            >
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                                </svg>
                                <span class="truncate">{{ item.name }}</span>
                                <svg
                                    :class="['ml-auto h-4 w-4 shrink-0 transition-transform duration-200', isMenuExpanded(item) ? 'rotate-180' : '']"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Children hang off a hairline, so the group reads as one unit. -->
                            <div v-show="isMenuExpanded(item)" class="ml-[1.55rem] mt-0.5 space-y-0.5 border-l border-brand-800 pl-3">
                                <Link
                                    v-for="child in item.children"
                                    :key="child.route"
                                    :href="route(child.route)"
                                    :aria-current="current(child.pattern) ? 'page' : undefined"
                                    :class="[
                                        'relative block rounded-md px-3 py-2 text-sm transition',
                                        current(child.pattern)
                                            ? 'bg-brand-900 font-semibold text-white'
                                            : 'text-brand-300 hover:bg-brand-900/60 hover:text-white',
                                    ]"
                                >
                                    <span
                                        v-if="current(child.pattern)"
                                        class="absolute -left-[13px] top-1/2 h-4 w-0.5 -translate-y-1/2 rounded-full bg-brass-500"
                                        aria-hidden="true"
                                    ></span>
                                    {{ child.name }}
                                </Link>
                            </div>
                        </div>
                    </li>
                </ul>

                <!-- Admin sits apart from the agent's own tools. -->
                <div v-if="isAdmin" class="mt-4 border-t border-brand-900 pt-4">
                    <Link
                        :href="route('admin.dashboard')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-brass-300 transition hover:bg-brass-500/10 hover:text-brass-200"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Admin Panel
                    </Link>
                </div>
            </nav>

            <!-- Account -->
            <div class="shrink-0 border-t border-brand-900 p-3">
                <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-800 font-display text-sm font-bold uppercase text-brass-300">
                        {{ (authUser.username || authUser.name || '?').charAt(0) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">{{ authUser.username || authUser.name }}</p>
                        <p class="truncate text-xs text-brand-300">{{ authUser.email }}</p>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        title="Log out"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-brand-300 transition hover:bg-brand-900 hover:text-white"
                    >
                        <span class="sr-only">Log out</span>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- =====================================================================
             Main column
             ===================================================================== -->
        <div class="md:pl-[17rem]">
            <!-- Top bar -->
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

                    <!-- On mobile the sidebar is hidden, so the top bar carries location. -->
                    <p class="truncate text-sm font-semibold text-ink-900 dark:text-ink-100 md:hidden">
                        {{ currentSection }}
                    </p>

                    <!-- Balance repeats here on mobile only, where the sidebar slip isn't visible. -->
                    <Link
                        :href="route('wallet.index')"
                        class="ml-auto hidden items-center gap-2 rounded-lg border border-brass-300 bg-brass-50 px-3 py-1.5 dark:border-brass-800 dark:bg-brass-950/40 sm:flex md:hidden"
                    >
                        <span class="font-mono text-sm font-semibold text-brass-800 dark:text-brass-200">
                            {{ formatCurrency(authUser.balance) }}
                        </span>
                    </Link>

                    <div class="ml-auto flex items-center gap-1 md:ml-auto">
                        <NotificationBell />
                        <DarkModeToggle />
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold text-ink-700 transition hover:bg-ink-100 dark:text-ink-200 dark:hover:bg-ink-800"
                                >
                                    <span class="hidden max-w-[10rem] truncate sm:block">{{ authUser.name }}</span>
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-800 font-display text-xs font-bold uppercase text-brass-300 sm:hidden">
                                        {{ (authUser.username || authUser.name || '?').charAt(0) }}
                                    </span>
                                    <svg class="h-4 w-4 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                <DropdownLink :href="route('wallet.index')">Wallet</DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>

                <!-- Page heading slot, when a page supplies one. -->
                <div v-if="$slots.header" class="border-t border-ink-200 px-4 py-4 dark:border-ink-800 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <slot />
            </main>
        </div>

        <!-- Mobile overlay -->
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

        <GlobalNotificationModal />
    </div>
</template>
