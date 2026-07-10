<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    wallet: Object,
    recent_transactions: Array,
    reserved_accounts: { type: Array, default: () => [] },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? {});
const copied = ref(null);

const copy = async (value) => {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = value;
        setTimeout(() => (copied.value = null), 1500);
    } catch (e) {
        // clipboard unavailable; ignore
    }
};

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
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Dashboard
            </h2>
        </template>

        <div>
            <div>
                <!-- Welcome Banner -->
                <div class="overflow-hidden rounded-lg mb-6 bg-gradient-to-r from-blue-500/10 via-purple-500/10 to-pink-500/10 dark:from-blue-500/20 dark:via-purple-500/20 dark:to-pink-500/20 border border-gray-100 dark:border-gray-700">
                    <div class="p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100">
                                Welcome back, {{ authUser.username || authUser.name }}!
                                <span v-if="authUser.role" class="text-base font-medium text-gray-500 dark:text-gray-400">({{ authUser.role }})</span>
                                👋
                            </h1>
                            <p class="mt-1 text-gray-600 dark:text-gray-300">
                                Your balance:
                                <span class="font-semibold text-gray-900 dark:text-gray-100">₦{{ wallet.total_balance.toLocaleString() }}</span>
                            </p>
                        </div>
                        <Link :href="route('wallet.fund')">
                            <PrimaryButton>Fund Wallet</PrimaryButton>
                        </Link>
                    </div>
                </div>

                <!-- Virtual Accounts -->
                <div v-if="reserved_accounts.length" class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 mb-6">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Your Virtual Accounts</h3>
                            <Link :href="route('wallet.fund')" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm">Manage →</Link>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="acct in reserved_accounts"
                                :key="acct.account_number"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 flex flex-col gap-2"
                            >
                                <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase">{{ acct.bank }}</div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xl font-mono font-bold tracking-wide text-gray-900 dark:text-gray-100">{{ acct.account_number }}</span>
                                    <button
                                        type="button"
                                        class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200"
                                        @click="copy(acct.account_number)"
                                    >
                                        {{ copied === acct.account_number ? 'Copied' : 'Copy' }}
                                    </button>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ acct.account_name }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Balance Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Total Balance -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div class="p-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                            <h3 class="text-sm font-medium opacity-90">Total Balance</h3>
                            <p class="text-3xl font-bold mt-1">₦{{ wallet.total_balance.toLocaleString() }}</p>
                        </div>
                    </div>

                    <!-- Main Balance -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Main Balance</h3>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">₦{{ wallet.balance.toLocaleString() }}</p>
                        </div>
                    </div>

                    <!-- Bonus Balance -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div class="p-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Bonus Balance</h3>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">₦{{ wallet.bonus_balance.toLocaleString() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Quick Actions</h3>
                        <div class="flex flex-wrap gap-4">
                            <Link :href="route('wallet.fund')">
                                <PrimaryButton>Fund Wallet</PrimaryButton>
                            </Link>
                            <Link :href="route('buy-data')">
                                <PrimaryButton class="bg-blue-600 hover:bg-blue-700">Buy Data</PrimaryButton>
                            </Link>
                            <Link :href="route('verification.nin')">
                                <PrimaryButton class="bg-purple-600 hover:bg-purple-700">Verify ID</PrimaryButton>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Services Grid -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Our Services</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <!-- Data Service -->
                            <Link :href="route('buy-data')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Data Bundle</span>
                                </div>
                            </Link>

                            <!-- NIN Verification -->
                            <Link :href="route('nin.verify.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">NIN Verify</span>
                                </div>
                            </Link>

                            <!-- BVN Search -->
                            <Link :href="route('bvn-search.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">BVN Search</span>
                                </div>
                            </Link>

                            <!-- BVN Modification -->
                            <Link :href="route('bvn-modification.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-pink-100 dark:bg-pink-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">BVN Modification</span>
                                </div>
                            </Link>

                            <!-- BVN SDK Onboarding -->
                            <Link :href="route('bvn-sdk-form.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">BVN Onboarding</span>
                                </div>
                            </Link>

                            <!-- BVN Retrieval -->
                            <Link :href="route('bvn-retrieval.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">BVN Retrieval</span>
                                </div>
                            </Link>

                            <!-- ID Card -->
                            <Link :href="route('idcard.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-teal-100 dark:bg-teal-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">ID Card</span>
                                </div>
                            </Link>

                            <!-- Enrollment Records -->
                            <Link :href="route('bvn-records.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-cyan-100 dark:bg-cyan-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Enrollment Records</span>
                                </div>
                            </Link>

                            <!-- Fund Wallet -->
                            <Link :href="route('wallet.fund')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Fund Wallet</span>
                                </div>
                            </Link>

                            <!-- Transaction History -->
                            <Link :href="route('wallet.transactions')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">History</span>
                                </div>
                            </Link>

                            <!-- NIN Validation -->
                            <Link :href="route('nin.validation.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-cyan-100 dark:bg-cyan-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">NIN Validation</span>
                                </div>
                            </Link>

                            <!-- NIN IPE Clearance -->
                            <Link :href="route('nin.ipe.index')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-rose-100 dark:bg-rose-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">NIN IPE</span>
                                </div>
                            </Link>

                            <!-- Verification History -->
                            <Link :href="route('verification.history')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-teal-100 dark:bg-teal-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Verify History</span>
                                </div>
                            </Link>

                            <!-- Profile/Settings -->
                            <Link :href="route('profile.edit')" class="group">
                                <div class="flex flex-col items-center p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition-all">
                                    <div class="w-12 h-12 bg-pink-100 dark:bg-pink-900 rounded-full flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Profile</span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recent Transactions</h3>
                            <Link :href="route('wallet.transactions')" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">
                                View All →
                            </Link>
                        </div>

                        <div v-if="recent_transactions.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                            No transactions yet.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reference</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="transaction in recent_transactions" :key="transaction.id">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">{{ transaction.reference }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ getTypeLabel(transaction.type) }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">₦{{ transaction.amount.toLocaleString() }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(transaction.status)]">
                                                {{ transaction.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ transaction.date }}</td>
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
