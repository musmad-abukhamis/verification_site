<script setup>
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const brand = computed(() => {
    const name = page.props.app?.name;
    return name && name !== 'Laravel' ? name : 'SmartVerify';
});

const year = new Date().getFullYear();

const highlights = [
    { title: 'Airtime & Data', desc: 'Instant top-ups on every network.', icon: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0' },
    { title: 'NIN & BVN Services', desc: 'Verify, validate, search and more.', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { title: 'One secure wallet', desc: 'Fund instantly and pay for anything.', icon: 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9v3' },
];
</script>

<template>
    <div class="min-h-screen bg-white dark:bg-gray-950 lg:grid lg:grid-cols-2">
        <!-- ===== Brand panel (left) ===== -->
        <div class="relative hidden overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-700 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="pointer-events-none absolute inset-0 opacity-30">
                <div class="absolute -top-16 -left-16 h-72 w-72 rounded-full bg-white/20 blur-3xl"></div>
                <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-purple-400/30 blur-3xl"></div>
            </div>

            <Link href="/" class="relative flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 text-white ring-1 ring-white/30 backdrop-blur">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </span>
                <span class="text-xl font-bold text-white">{{ brand }}</span>
            </Link>

            <div class="relative">
                <h2 class="text-3xl font-extrabold leading-tight text-white xl:text-4xl">
                    Airtime, Data &amp; Identity services — all in one place.
                </h2>
                <p class="mt-4 max-w-md text-indigo-100">
                    Join thousands using {{ brand }} for fast, secure transactions from a single wallet.
                </p>

                <ul class="mt-10 space-y-5">
                    <li v-for="item in highlights" :key="item.title" class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 text-white ring-1 ring-white/20 backdrop-blur">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-semibold text-white">{{ item.title }}</p>
                            <p class="text-sm text-indigo-100">{{ item.desc }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="relative text-sm text-indigo-200">© {{ year }} {{ brand }}. All rights reserved.</p>
        </div>

        <!-- ===== Form panel (right) ===== -->
        <div class="relative flex min-h-screen flex-col px-4 py-8 sm:px-6 lg:min-h-0 lg:px-12">
            <!-- Top bar: home link + dark toggle -->
            <div class="flex items-center justify-between">
                <Link href="/" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to home
                </Link>
                <DarkModeToggle />
            </div>

            <!-- Centered form -->
            <div class="flex flex-1 items-center justify-center py-8">
                <div class="w-full max-w-md">
                    <!-- Compact brand mark for mobile -->
                    <Link href="/" class="mb-8 flex items-center justify-center gap-2.5 lg:hidden">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        </span>
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ brand }}</span>
                    </Link>

                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
