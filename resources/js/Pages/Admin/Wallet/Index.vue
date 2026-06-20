<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    userResults: Array,
    q: String,
});

const search = ref(props.q || '');
const dropdownOpen = ref(false);
const selected = ref(null);
let searchTimeout;

const form = useForm({
    option: 'Credit',
    username: '',
    amount: '',
});

watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.wallet.index'), { q: val }, {
            only: ['userResults', 'q'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 350);
});

const pickUser = (u) => {
    selected.value = u;
    form.username = u.username;
    search.value = `${u.username} — ${u.name || u.email}`;
    dropdownOpen.value = false;
};

const submit = () => {
    form.post(route('admin.wallet.fund'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('amount');
            // Keep the selected user but refresh their displayed balance next search.
        },
    });
};

const fmt = (n) => '₦' + Number(n || 0).toLocaleString();
</script>

<template>
    <Head title="Account Funding" />
    <AdminLayout>
        <div class="max-w-2xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Account Funding</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Credit or debit any user's wallet.</p>
                </div>
                <Link :href="route('admin.wallet.transactions')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg whitespace-nowrap">
                    Wallet Transactions
                </Link>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Option -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Funding Option</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="form.option = 'Credit'"
                                :class="['py-2.5 rounded-lg text-sm font-semibold border', form.option === 'Credit' ? 'bg-green-600 text-white border-green-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300']">
                                Credit
                            </button>
                            <button type="button" @click="form.option = 'Debit'"
                                :class="['py-2.5 rounded-lg text-sm font-semibold border', form.option === 'Debit' ? 'bg-red-600 text-white border-red-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300']">
                                Debit
                            </button>
                        </div>
                    </div>

                    <!-- User search/select -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                        <input
                            v-model="search"
                            @focus="dropdownOpen = true"
                            type="text"
                            placeholder="Search by username, name, email, or phone..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2.5"
                            autocomplete="off"
                        />
                        <p v-if="form.errors.username" class="mt-1 text-xs text-red-600">{{ form.errors.username }}</p>

                        <div v-if="dropdownOpen && userResults.length" class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <button
                                v-for="u in userResults"
                                :key="u.id"
                                type="button"
                                @click="pickUser(u)"
                                class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 border-b last:border-b-0 border-gray-100 dark:border-gray-700"
                            >
                                <div class="font-medium text-sm text-gray-900 dark:text-white">{{ u.username }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ u.name || u.email }} — Balance: {{ fmt(u.balance) }}</div>
                            </button>
                        </div>
                        <div v-else-if="dropdownOpen && search.length >= 2" class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg px-3 py-2 text-sm text-gray-500">
                            No users found.
                        </div>
                    </div>

                    <!-- Selected summary -->
                    <div v-if="selected" class="rounded-lg bg-gray-50 dark:bg-gray-700/40 p-3 text-sm flex items-center justify-between">
                        <span class="text-gray-600 dark:text-gray-300">Selected: <strong class="text-gray-900 dark:text-white">{{ selected.username }}</strong></span>
                        <span class="text-gray-600 dark:text-gray-300">Current balance: <strong class="text-gray-900 dark:text-white">{{ fmt(selected.balance) }}</strong></span>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (₦)</label>
                        <input v-model="form.amount" type="number" min="1" step="0.01" placeholder="Enter amount"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2.5" />
                        <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing || !form.username"
                        :class="['w-full py-2.5 rounded-lg text-white font-medium disabled:opacity-50', form.option === 'Credit' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700']">
                        {{ form.processing ? 'Processing...' : `${form.option} Wallet` }}
                    </button>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
