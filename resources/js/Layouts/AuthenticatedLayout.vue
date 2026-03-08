<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDrawer = ref(false);
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- Traditional Top Navigation (Desktop) -->
        <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hidden md:block">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="flex-shrink-0 flex items-center">
                            <Link :href="route('dashboard')">
                                <ApplicationLogo class="block h-9 w-auto text-gray-800 dark:text-gray-200" />
                            </Link>
                        </div>
                        
                        <!-- Desktop Navigation Links -->
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                Dashboard
                            </NavLink>
                            <NavLink :href="route('wallet.index')" :active="route().current('wallet.*')">
                                Wallet
                            </NavLink>
                            <NavLink :href="route('vtu.airtime')" :active="route().current('vtu.airtime*')">
                                Airtime
                            </NavLink>
                            <NavLink :href="route('buy-data')" :active="route().current('buy-data*')">
                                Buy Data
                            </NavLink>
                            <NavLink :href="route('verification.nin')" :active="route().current('verification.nin*')">
                                NIN
                            </NavLink>
                            <NavLink :href="route('verification.bvn')" :active="route().current('verification.bvn*')">
                                BVN
                            </NavLink>
                            <NavLink 
                                v-if="$page.props.auth.user.is_admin"
                                :href="route('admin.dashboard')" 
                                :active="route().current('admin.*')"
                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                            >
                                Admin
                            </NavLink>
                        </div>
                    </div>
                    
                    <div class="hidden sm:ml-6 sm:flex sm:items-center">
                        <!-- Dark Mode Toggle -->
                        <DarkModeToggle class="mr-4" />
                        
                        <!-- User Dropdown -->
                        <div class="ml-3 relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none">
                                        {{ $page.props.auth.user.name }}
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('profile.edit')">
                                        Profile
                                    </DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">
                                        Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Mobile Header with Hamburger - STICKY -->
        <div class="md:hidden sticky top-0 z-10 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center">
                    <button
                        @click="showingNavigationDrawer = true"
                        class="mr-3 inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                    >
                        <span class="sr-only">Open menu</span>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <Link :href="route('dashboard')">
                        <ApplicationLogo class="block h-8 w-auto text-gray-800 dark:text-gray-200" />
                    </Link>
                </div>
                <div class="flex items-center">
                    <DarkModeToggle class="mr-3" />
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $page.props.auth.user.name }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Side Drawer -->
        <div v-if="showingNavigationDrawer" class="fixed inset-0 z-50 md:hidden">
            <div @click="showingNavigationDrawer = false" class="fixed inset-0 bg-gray-600 bg-opacity-75" aria-hidden="true"></div>
            <div class="relative flex-1 flex flex-col max-w-xs w-full bg-white dark:bg-gray-800 h-full">
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button
                        @click="showingNavigationDrawer = false"
                        class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                    >
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                    <div class="flex-shrink-0 flex items-center px-4">
                        <Link :href="route('dashboard')" @click="showingNavigationDrawer = false">
                            <ApplicationLogo class="h-8 w-auto" />
                        </Link>
                    </div>
                    <nav class="mt-5 px-2 space-y-1">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                            @click="showingNavigationDrawer = false"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('wallet.index')"
                            :active="route().current('wallet.*')"
                            @click="showingNavigationDrawer = false"
                        >
                            Wallet
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('vtu.airtime')"
                            :active="route().current('vtu.airtime*')"
                            @click="showingNavigationDrawer = false"
                        >
                            Airtime
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('buy-data')"
                            :active="route().current('buy-data*')"
                            @click="showingNavigationDrawer = false"
                        >
                            Buy Data
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('verification.nin')"
                            :active="route().current('verification.nin*')"
                            @click="showingNavigationDrawer = false"
                        >
                            NIN
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('verification.bvn')"
                            :active="route().current('verification.bvn*')"
                            @click="showingNavigationDrawer = false"
                        >
                            BVN
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user.is_admin"
                            :href="route('admin.dashboard')"
                            :active="route().current('admin.*')"
                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                            @click="showingNavigationDrawer = false"
                        >
                            Admin
                        </ResponsiveNavLink>
                    </nav>
                </div>
                <div class="flex-shrink-0 flex border-t border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex-1">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $page.props.auth.user.name }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $page.props.auth.user.email }}
                        </div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')" @click="showingNavigationDrawer = false">
                            Profile
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                            @click="showingNavigationDrawer = false"
                        >
                            Log Out
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Heading -->
        <header class="bg-white shadow dark:bg-gray-800" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <slot />
            </div>
        </main>
    </div>
</template>