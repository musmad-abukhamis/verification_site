<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    user: Object,
});

const toggleAdmin = () => {
    if (confirm(`Are you sure you want to ${props.user.is_admin ? 'remove' : 'grant'} admin privileges for ${props.user.name}?`)) {
        router.patch(route('admin.users.toggle-admin', props.user.id));
    }
};

const toggleStatus = () => {
    if (confirm(`Are you sure you want to ${props.user.email_verified_at ? 'deactivate' : 'activate'} ${props.user.name}'s account?`)) {
        router.patch(route('admin.users.toggle-status', props.user.id));
    }
};

// Wallet credit/debit
const showCreditModal = ref(false);
const showDebitModal = ref(false);

const creditForm = useForm({
    amount: '',
    description: '',
});

const debitForm = useForm({
    amount: '',
    description: '',
});

const submitCredit = () => {
    creditForm.post(route('admin.users.wallet.credit', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCreditModal.value = false;
            creditForm.reset();
        },
    });
};

const submitDebit = () => {
    debitForm.post(route('admin.users.wallet.debit', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDebitModal.value = false;
            debitForm.reset();
        },
    });
};

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};
</script>

<template>
    <Head :title="`${user.name} - User Details`" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('admin.users.index')"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        ← Back to Users
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">User Details</h1>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="toggleAdmin"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium',
                            user.is_admin
                                ? 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-300'
                                : 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200 dark:bg-indigo-900 dark:text-indigo-300'
                        ]"
                    >
                        {{ user.is_admin ? 'Remove Admin' : 'Make Admin' }}
                    </button>
                    <button
                        @click="toggleStatus"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium',
                            user.email_verified_at
                                ? 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-300'
                                : 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900 dark:text-green-300'
                        ]"
                    >
                        {{ user.email_verified_at ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-start gap-6">
                    <div class="w-20 h-20 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center">
                        <span class="text-3xl text-indigo-600 dark:text-indigo-400 font-medium">
                            {{ user.name.charAt(0).toUpperCase() }}
                        </span>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ user.name }}</h2>
                        <p class="text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                        <div class="mt-2 flex gap-2">
                            <span
                                :class="user.email_verified_at
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'"
                                class="px-2 py-1 text-xs rounded-full"
                            >
                                {{ user.email_verified_at ? 'Active' : 'Inactive' }}
                            </span>
                            <span
                                v-if="user.is_admin"
                                class="px-2 py-1 text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 rounded-full"
                            >
                                Admin
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Phone:</span>
                                <span class="ml-2 text-gray-900 dark:text-white">{{ user.phone || 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Joined:</span>
                                <span class="ml-2 text-gray-900 dark:text-white">{{ user.created_at }}</span>
                            </div>
                            <div v-if="user.email_verified_at">
                                <span class="text-gray-500 dark:text-gray-400">Verified:</span>
                                <span class="ml-2 text-gray-900 dark:text-white">{{ user.email_verified_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wallet Info -->
            <div v-if="user.wallet" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Wallet</h3>
                    <div class="flex gap-2">
                        <button
                            @click="showCreditModal = true"
                            class="px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900 dark:text-green-300 rounded-lg text-sm font-medium"
                        >
                            + Credit Wallet
                        </button>
                        <button
                            @click="showDebitModal = true"
                            class="px-4 py-2 bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-300 rounded-lg text-sm font-medium"
                        >
                            - Debit Wallet
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Balance</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ user.wallet.balance.toLocaleString() }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Bonus</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ user.wallet.bonus_balance.toLocaleString() }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">₦{{ user.wallet.total_balance.toLocaleString() }}</p>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Transactions</h3>
                </div>
                <table v-if="user.transactions.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="transaction in user.transactions" :key="transaction.id">
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ transaction.reference }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">{{ transaction.type }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">₦{{ transaction.amount.toLocaleString() }}</td>
                            <td class="px-6 py-4">
                                <span :class="['px-2 py-1 text-xs rounded-full', statusColors[transaction.status]]">
                                    {{ transaction.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">{{ transaction.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    No transactions found
                </div>
            </div>
        </div>

        <!-- Credit Wallet Modal -->
        <div v-if="showCreditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Credit Wallet</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Credit {{ user.name }}'s wallet. Current balance: <strong>₦{{ user.wallet?.total_balance?.toLocaleString() ?? 0 }}</strong>
                    </p>
                    <form @submit.prevent="submitCredit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (₦)</label>
                            <input
                                v-model="creditForm.amount"
                                type="number"
                                min="1"
                                required
                                placeholder="Enter amount"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2"
                            />
                            <p v-if="creditForm.errors.amount" class="mt-1 text-xs text-red-500">{{ creditForm.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (optional)</label>
                            <input
                                v-model="creditForm.description"
                                type="text"
                                placeholder="e.g. Bonus credit, Refund"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2"
                            />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button
                                type="button"
                                @click="showCreditModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="creditForm.processing"
                                class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                            >
                                {{ creditForm.processing ? 'Processing...' : 'Credit Wallet' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Debit Wallet Modal -->
        <div v-if="showDebitModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Debit Wallet</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Debit {{ user.name }}'s wallet. Current balance: <strong>₦{{ user.wallet?.total_balance?.toLocaleString() ?? 0 }}</strong>
                    </p>
                    <form @submit.prevent="submitDebit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (₦)</label>
                            <input
                                v-model="debitForm.amount"
                                type="number"
                                min="1"
                                required
                                placeholder="Enter amount"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2"
                            />
                            <p v-if="debitForm.errors.amount" class="mt-1 text-xs text-red-500">{{ debitForm.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description (optional)</label>
                            <input
                                v-model="debitForm.description"
                                type="text"
                                placeholder="e.g. Service charge, Correction"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2"
                            />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button
                                type="button"
                                @click="showDebitModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="debitForm.processing"
                                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
                            >
                                {{ debitForm.processing ? 'Processing...' : 'Debit Wallet' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
