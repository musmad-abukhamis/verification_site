<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';

const props = defineProps({
    canLogin: { type: Boolean, default: true },
    canRegister: { type: Boolean, default: true },
    appName: { type: String, default: '' },
    laravelVersion: { type: String, default: '' },
    phpVersion: { type: String, default: '' },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

// Brand name: use the configured APP_NAME unless it's still the framework default.
const brand = computed(() =>
    props.appName && props.appName !== 'Laravel' ? props.appName : 'SmartVerify'
);

const year = new Date().getFullYear();
const mobileMenuOpen = ref(false);
const openFaq = ref(0);

const toggleFaq = (i) => (openFaq.value = openFaq.value === i ? -1 : i);

// Service catalogue grouped by category (no pricing shown).
const serviceGroups = [
    {
        title: 'Airtime & Data',
        subtitle: 'Top up instantly on every network.',
        accent: 'from-emerald-500 to-green-600',
        services: [
            { name: 'Buy Airtime', desc: 'Instant airtime for MTN, Glo, Airtel & 9mobile.', icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
            { name: 'Buy Data', desc: 'Affordable data bundles delivered in seconds.', icon: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0' },
        ],
    },
    {
        title: 'NIN Services',
        subtitle: 'Everything around the National Identity Number.',
        accent: 'from-indigo-500 to-purple-600',
        services: [
            { name: 'NIN Verification', desc: 'Confirm identity details from a NIN.', icon: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2' },
            { name: 'NIN Validation', desc: 'Validate NIN records against the database.', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
            { name: 'IPE Clearance', desc: 'Resolve pending enrolment (IPE) issues.', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
            { name: 'NIN by Phone', desc: 'Look up a NIN using a phone number.', icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
            { name: 'Demographic Search', desc: 'Find records using personal details.', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
        ],
    },
    {
        title: 'BVN Services',
        subtitle: 'Full Bank Verification Number toolkit.',
        accent: 'from-orange-500 to-amber-600',
        services: [
            { name: 'BVN Verification', desc: 'Confirm BVN details with confidence.', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
            { name: 'BVN Search', desc: 'Search & generate a printable BVN slip.', icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' },
            { name: 'BVN Modification', desc: 'Request updates to BVN information.', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
            { name: 'BVN Onboarding', desc: 'Guided new BVN enrolment wizard.', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            { name: 'BVN Retrieval', desc: 'Retrieve a forgotten BVN securely.', icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' },
        ],
    },
    {
        title: 'Wallet',
        subtitle: 'Fund once, pay for everything.',
        accent: 'from-sky-500 to-blue-600',
        services: [
            { name: 'Instant Wallet Funding', desc: 'Top up via your dedicated virtual account.', icon: 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9v3' },
            { name: 'Transaction Reports', desc: 'Track every transaction with clear reports.', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        ],
    },
];

const features = [
    { name: 'Lightning fast', desc: 'Airtime, data and verifications are delivered in seconds — no waiting around.', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    { name: 'Bank-grade security', desc: 'Your data and funds are protected with encrypted, secure infrastructure.', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { name: 'Available 24/7', desc: 'Run transactions any time of day, every day of the week.', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'One simple wallet', desc: 'Fund instantly through a dedicated virtual account and pay for any service.', icon: 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9v3' },
    { name: 'Reliable uptime', desc: 'Multiple vendors and resilient APIs keep services running smoothly.', icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01' },
    { name: 'Clear records', desc: 'Detailed history and reports for every transaction you make.', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
];

const steps = [
    { name: 'Create your account', desc: 'Sign up free in under a minute — no paperwork required.', icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' },
    { name: 'Fund your wallet', desc: 'Get a dedicated virtual account and top up instantly.', icon: 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9v3' },
    { name: 'Use any service', desc: 'Buy airtime & data or run NIN/BVN services right away.', icon: 'M5 13l4 4L19 7' },
];

const faqs = [
    { q: 'What services can I access?', a: 'Airtime and data top-ups across all networks, plus a complete suite of NIN and BVN services — verification, validation, search, modification, onboarding, retrieval and more.' },
    { q: 'How do I pay for services?', a: 'You fund your in-app wallet through a dedicated virtual account, then pay seamlessly from your balance whenever you use a service.' },
    { q: 'Is my information safe?', a: 'Yes. We use secure, encrypted infrastructure and only process the information required to complete your requests.' },
    { q: 'How fast are transactions?', a: 'Airtime, data and most verifications are processed instantly. Requests that need manual fulfilment show a live status you can track.' },
    { q: 'Do I need to sign up?', a: 'Creating a free account takes less than a minute and gives you a wallet plus access to every service on the platform.' },
];

const ctaPrimary = computed(() =>
    authUser.value ? { label: 'Go to Dashboard', href: route('dashboard') }
        : props.canRegister ? { label: 'Create free account', href: route('register') }
        : { label: 'Log in', href: route('login') }
);

// Smooth scrolling for in-page anchors.
onMounted(() => document.documentElement.classList.add('scroll-smooth'));
onBeforeUnmount(() => document.documentElement.classList.remove('scroll-smooth'));
</script>

<template>
    <Head :title="`${brand} — Airtime, Data, NIN & BVN Services`" />

    <div class="min-h-screen bg-white text-gray-700 dark:bg-gray-950 dark:text-gray-300">
        <!-- ===== Navbar ===== -->
        <header class="sticky top-0 z-40 border-b border-gray-100 bg-white/80 backdrop-blur dark:border-gray-800 dark:bg-gray-950/80">
            <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="#top" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">{{ brand }}</span>
                </a>

                <!-- Desktop links -->
                <div class="hidden items-center gap-8 md:flex">
                    <a href="#services" class="text-sm font-medium hover:text-indigo-600 dark:hover:text-indigo-400">Services</a>
                    <a href="#why" class="text-sm font-medium hover:text-indigo-600 dark:hover:text-indigo-400">Why us</a>
                    <a href="#how" class="text-sm font-medium hover:text-indigo-600 dark:hover:text-indigo-400">How it works</a>
                    <a href="#faq" class="text-sm font-medium hover:text-indigo-600 dark:hover:text-indigo-400">FAQ</a>
                </div>

                <div class="flex items-center gap-2">
                    <DarkModeToggle />
                    <template v-if="authUser">
                        <Link :href="route('dashboard')" class="hidden rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:inline-block">
                            Dashboard
                        </Link>
                    </template>
                    <template v-else-if="canLogin">
                        <Link :href="route('login')" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 transition hover:text-indigo-600 dark:text-gray-200 dark:hover:text-indigo-400 sm:inline-block">
                            Log in
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="hidden rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:inline-block">
                            Get started
                        </Link>
                    </template>

                    <!-- Mobile menu button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 md:hidden">
                        <span class="sr-only">Toggle menu</span>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="mobileMenuOpen ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'" />
                        </svg>
                    </button>
                </div>
            </nav>

            <!-- Mobile menu -->
            <div v-show="mobileMenuOpen" class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 md:hidden">
                <div class="flex flex-col gap-1">
                    <a @click="mobileMenuOpen = false" href="#services" class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800">Services</a>
                    <a @click="mobileMenuOpen = false" href="#why" class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800">Why us</a>
                    <a @click="mobileMenuOpen = false" href="#how" class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800">How it works</a>
                    <a @click="mobileMenuOpen = false" href="#faq" class="rounded-lg px-3 py-2 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800">FAQ</a>
                    <div class="mt-2 flex gap-2">
                        <Link v-if="authUser" :href="route('dashboard')" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white">Dashboard</Link>
                        <template v-else-if="canLogin">
                            <Link :href="route('login')" class="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-center text-sm font-semibold dark:border-gray-700">Log in</Link>
                            <Link v-if="canRegister" :href="route('register')" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white">Get started</Link>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <main id="top">
            <!-- ===== Hero ===== -->
            <section class="relative overflow-hidden">
                <!-- decorative gradient blobs -->
                <div class="pointer-events-none absolute inset-0 -z-10">
                    <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-indigo-400/20 blur-3xl dark:bg-indigo-600/20"></div>
                    <div class="absolute top-10 right-0 h-72 w-72 rounded-full bg-purple-400/20 blur-3xl dark:bg-purple-600/20"></div>
                    <div class="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-sky-400/10 blur-3xl dark:bg-sky-600/10"></div>
                </div>

                <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:gap-8 lg:px-8 lg:py-28">
                    <div class="text-center lg:text-left">
                        <span class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                            Airtime · Data · NIN · BVN — all in one place
                        </span>
                        <h1 class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-gray-900 sm:text-5xl lg:text-6xl dark:text-white">
                            Your all-in-one
                            <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">VTU & identity</span>
                            platform
                        </h1>
                        <p class="mx-auto mt-6 max-w-xl text-lg text-gray-600 lg:mx-0 dark:text-gray-400">
                            Buy airtime and data, and run NIN & BVN verifications in seconds — all from one secure wallet. Fast, reliable and built for everyone.
                        </p>
                        <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row lg:justify-start">
                            <Link :href="ctaPrimary.href" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-700 sm:w-auto">
                                {{ ctaPrimary.label }}
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </Link>
                            <a href="#services" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-6 py-3.5 text-base font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                                Explore services
                            </a>
                        </div>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-gray-500 lg:justify-start dark:text-gray-400">
                            <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Instant delivery</span>
                            <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Secure wallet</span>
                            <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> 24/7 access</span>
                        </div>
                    </div>

                    <!-- Hero visual: floating service cards -->
                    <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                        <div class="rounded-3xl border border-gray-100 bg-white/70 p-6 shadow-2xl shadow-indigo-500/10 backdrop-blur dark:border-gray-800 dark:bg-gray-900/70">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Wallet balance</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">₦ •••••</p>
                                </div>
                                <span class="rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 p-2.5 text-white">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9v3" /></svg>
                                </span>
                            </div>
                            <div class="mt-6 grid grid-cols-2 gap-3">
                                <div v-for="s in ['Airtime','Data','NIN','BVN']" :key="s" class="rounded-xl border border-gray-100 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-800/60">
                                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                    </div>
                                    <p class="mt-2 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ s }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-5 -right-3 hidden rounded-2xl border border-gray-100 bg-white p-4 shadow-xl sm:flex dark:border-gray-800 dark:bg-gray-900">
                            <span class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                </span>
                                Transaction successful
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== Services ===== -->
            <section id="services" class="border-t border-gray-100 bg-gray-50/60 py-20 dark:border-gray-800 dark:bg-gray-900/40 sm:py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">Everything you need, in one app</h2>
                        <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">From everyday top-ups to complete NIN & BVN services — explore what {{ brand }} offers.</p>
                    </div>

                    <div class="mt-14 space-y-14">
                        <div v-for="group in serviceGroups" :key="group.title">
                            <div class="mb-6 flex items-center gap-4">
                                <span :class="['h-10 w-1.5 rounded-full bg-gradient-to-b', group.accent]"></span>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ group.title }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ group.subtitle }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                <div
                                    v-for="service in group.services"
                                    :key="service.name"
                                    class="group relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-6 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-indigo-500/10 dark:border-gray-800 dark:bg-gray-900"
                                >
                                    <div :class="['inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-lg', group.accent]">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="service.icon" /></svg>
                                    </div>
                                    <h4 class="mt-5 text-base font-semibold text-gray-900 dark:text-white">{{ service.name }}</h4>
                                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ service.desc }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== Why us ===== -->
            <section id="why" class="py-20 sm:py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">Why choose {{ brand }}?</h2>
                        <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">Built to be fast, secure and effortless for everyone.</p>
                    </div>
                    <div class="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="f in features" :key="f.name" class="rounded-2xl border border-gray-100 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="f.icon" /></svg>
                            </div>
                            <h3 class="mt-5 text-lg font-semibold text-gray-900 dark:text-white">{{ f.name }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ f.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== How it works ===== -->
            <section id="how" class="border-y border-gray-100 bg-gray-50/60 py-20 dark:border-gray-800 dark:bg-gray-900/40 sm:py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">Get started in 3 simple steps</h2>
                        <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">No paperwork. No hassle. Just sign up and go.</p>
                    </div>
                    <div class="mt-14 grid grid-cols-1 gap-8 md:grid-cols-3">
                        <div v-for="(step, i) in steps" :key="step.name" class="relative text-center">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="step.icon" /></svg>
                            </div>
                            <div class="mt-5 inline-flex items-center gap-2">
                                <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">Step {{ i + 1 }}</span>
                            </div>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ step.name }}</h3>
                            <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ step.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== FAQ ===== -->
            <section id="faq" class="py-20 sm:py-24">
                <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center">
                        <h2 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">Frequently asked questions</h2>
                        <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">Everything you need to know to get going.</p>
                    </div>
                    <div class="mt-10 divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-100 dark:divide-gray-800 dark:border-gray-800">
                        <div v-for="(item, i) in faqs" :key="i" class="bg-white dark:bg-gray-900">
                            <button @click="toggleFaq(i)" class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                                <span class="text-base font-semibold text-gray-900 dark:text-white">{{ item.q }}</span>
                                <svg :class="['h-5 w-5 shrink-0 text-indigo-500 transition-transform', openFaq === i ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div v-show="openFaq === i" class="px-6 pb-5 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ item.a }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== CTA ===== -->
            <section class="px-4 pb-20 sm:px-6 lg:px-8">
                <div class="relative mx-auto max-w-7xl overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-16 text-center shadow-2xl shadow-indigo-500/30 sm:px-12">
                    <div class="pointer-events-none absolute inset-0 opacity-20">
                        <div class="absolute -top-10 -left-10 h-48 w-48 rounded-full bg-white blur-3xl"></div>
                        <div class="absolute -bottom-16 right-0 h-56 w-56 rounded-full bg-white blur-3xl"></div>
                    </div>
                    <div class="relative">
                        <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Ready to get started?</h2>
                        <p class="mx-auto mt-4 max-w-xl text-lg text-indigo-100">Join {{ brand }} today and enjoy fast airtime, data and identity services from one secure wallet.</p>
                        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <Link :href="ctaPrimary.href" class="inline-flex w-full items-center justify-center rounded-xl bg-white px-6 py-3.5 text-base font-semibold text-indigo-700 shadow-lg transition hover:bg-indigo-50 sm:w-auto">
                                {{ ctaPrimary.label }}
                            </Link>
                            <Link v-if="!authUser && canLogin" :href="route('login')" class="inline-flex w-full items-center justify-center rounded-xl border border-white/40 px-6 py-3.5 text-base font-semibold text-white transition hover:bg-white/10 sm:w-auto">
                                Log in
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- ===== Footer ===== -->
        <footer class="border-t border-gray-100 bg-white dark:border-gray-800 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <a href="#top" class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        </span>
                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ brand }}</span>
                    </a>
                    <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm">
                        <a href="#services" class="hover:text-indigo-600 dark:hover:text-indigo-400">Services</a>
                        <a href="#why" class="hover:text-indigo-600 dark:hover:text-indigo-400">Why us</a>
                        <a href="#how" class="hover:text-indigo-600 dark:hover:text-indigo-400">How it works</a>
                        <a href="#faq" class="hover:text-indigo-600 dark:hover:text-indigo-400">FAQ</a>
                        <Link v-if="canLogin && !authUser" :href="route('login')" class="hover:text-indigo-600 dark:hover:text-indigo-400">Log in</Link>
                    </nav>
                </div>
                <div class="mt-8 border-t border-gray-100 pt-6 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    © {{ year }} {{ brand }}. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>
