<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    price: Number,
    transactions: Object,
    filters: Object,
    wallet: Object,
});

// Form state
const nin = ref('');
const isSubmitting = ref(false);
const formError = ref('');

// Table state
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const sortField = ref(props.filters?.sort || 'created_at');
const sortDirection = ref(props.filters?.direction || 'desc');
const checkingStatus = ref({});
const tableError = ref('');

let searchTimeout;

// Debounced search
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchTransactions();
    }, 300);
});

watch(statusFilter, () => {
    fetchTransactions();
});

const fetchTransactions = () => {
    router.get(route('nin-ipe-clearance.index'), {
        search: search.value,
        status: statusFilter.value,
        sort: sortField.value,
        direction: sortDirection.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['transactions'],
        onError: (errors) => {
            tableError.value = errors.message || 'Failed to load transactions';
        }
    });
};

const handleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    fetchTransactions();
};

const submitNin = async () => {
    if (nin.value.length !== 11) {
        formError.value = 'NIN must be exactly 11 characters';
        return;
    }
    
    formError.value = '';
    isSubmitting.value = true;
    
    router.post(route('nin-ipe-clearance.store'), {
        nin: nin.value,
    }, {
        onSuccess: () => {
            nin.value = '';
            isSubmitting.value = false;
        },
        onError: (errors) => {
            formError.value = errors.nin || errors.message || 'Failed to submit NIN';
            isSubmitting.value = false;
        },
    });
};

const checkStatus = async (transaction) => {
    if (checkingStatus.value[transaction.id]) return;
    
    checkingStatus.value[transaction.id] = true;
    tableError.value = '';
    
    router.post(route('nin-ipe-clearance.check', transaction.id), {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            checkingStatus.value[transaction.id] = false;
        },
        onError: (errors) => {
            checkingStatus.value[transaction.id] = false;
            tableError.value = errors.message || 'Failed to check status';
        },
    });
};

const getStatusBadge = (status) => {
    const badges = {
        completed: {
            class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            icon: 'check',
        },
        cleared: {
            class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            icon: 'check',
        },
        processing: {
            class: 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300',
            icon: 'clock',
        },
        failed: {
            class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            icon: 'x',
        },
        rejected: {
            class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            icon: 'x',
        },
    };
    
    const key = status?.toLowerCase();
    return badges[key] || { class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300', icon: null };
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const canCheckStatus = (transaction) => {
    const status = transaction.status?.toLowerCase();
    return status !== 'completed' && status !== 'cleared' && status !== 'rejected';
};

const goToPage = (url) => {
    if (!url) return;
    router.visit(url, {
        preserveState: true,
        preserveScroll: false,
        only: ['transactions'],
    });
};

const transactionsList = computed(() => props.transactions?.data || []);
const pagination = computed(() => ({
    from: props.transactions?.from || 0,
    to: props.transactions?.to || 0,
    total: props.transactions?.total || 0,
    links: props.transactions?.links || [],
    prev_page_url: props.transactions?.prev_page_url,
    next_page_url: props.transactions?.next_page_url,
    current_page: props.transactions?.current_page || 1,
    last_page: props.transactions?.last_page || 1,
}));
</script>

<template>
    <Head title="NIN IPE Clearance" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Section 1: NIN IPE Clearance Form -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-6">
                <h1 class="text-gray-600 dark:text-[#fdfdfd] font-bold text-center mb-4 text-xl">
                    NIN IPE CLEARANCE PAGE
                </h1>
                <p class="text-center text-gray-500 dark:text-gray-400 mb-4 text-sm">
                    Clear your NIN IPE (Identity Protection and Enhancement) status securely and efficiently.
                </p>
                <p class="text-center text-indigo-600 dark:text-indigo-400 font-semibold mb-10">
                    Price: ₦{{ price?.toLocaleString() || 0 }}
                </p>

                <!-- Form Error -->
                <div v-if="formError" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-center text-sm">
                    {{ formError }}
                </div>

                <form @submit.prevent="submitNin" class="max-w-md mx-auto space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Enter NIN
                        </label>
                        <input
                            v-model="nin"
                            type="text"
                            maxlength="11"
                            placeholder="Enter 11-character NIN"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-4 py-2"
                            :disabled="isSubmitting"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ nin.length }}/11 characters
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="isSubmitting || nin.length !== 11"
                        class="w-1/2 mx-auto flex justify-center items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span v-if="isSubmitting">Submitting...</span>
                        <span v-else>Submit @ ₦{{ price?.toLocaleString() || 0 }}</span>
                    </button>
                </form>
            </div>

            <!-- Section 2: Transaction History -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow">
                <!-- Header with Controls -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Clearance History
                    </h2>
                    
                    <!-- Table Error -->
                    <div v-if="tableError" class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-lg text-sm">
                        {{ tableError }}
                    </div>

                    <div class="flex flex-col md:flex-row gap-4">
                        <!-- Search -->
                        <div class="relative flex-1 md:w-64">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search transactions..."
                                class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>

                        <!-- Status Filter -->
                        <div class="relative md:w-[180px]">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            <select
                                v-model="statusFilter"
                                class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white appearance-none"
                            >
                                <option value="">All Statuses</option>
                                <option value="completed">Completed</option>
                                <option value="processing">Processing</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th 
                                    @click="handleSort('id')"
                                    class="w-[80px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center gap-1">
                                        ID
                                        <svg v-if="sortField === 'id'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path v-if="sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </th>
                                <th 
                                    @click="handleSort('nin')"
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center gap-1">
                                        NIN
                                        <svg v-if="sortField === 'nin'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path v-if="sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Result
                                </th>
                                <th 
                                    @click="handleSort('status')"
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center gap-1">
                                        Status
                                        <svg v-if="sortField === 'status'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path v-if="sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase max-w-[200px]">
                                    Comment
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Old Bal
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    New Bal
                                </th>
                                <th 
                                    @click="handleSort('created_at')"
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center gap-1">
                                        Created
                                        <svg v-if="sortField === 'created_at'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path v-if="sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </th>
                                <th 
                                    @click="handleSort('updated_at')"
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center gap-1">
                                        Updated
                                        <svg v-if="sortField === 'updated_at'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path v-if="sortDirection === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="transaction in transactionsList" :key="transaction.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ transaction.id }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-mono">
                                    {{ transaction.nin }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ transaction.result || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full', getStatusBadge(transaction.status).class]">
                                        <svg v-if="getStatusBadge(transaction.status).icon === 'check'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <svg v-else-if="getStatusBadge(transaction.status).icon === 'clock'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <svg v-else-if="getStatusBadge(transaction.status).icon === 'x'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ transaction.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-[200px] truncate" :title="transaction.comment">
                                    {{ transaction.comment || '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    ₦{{ transaction.old_balance?.toLocaleString() || 0 }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    ₦{{ transaction.new_balance?.toLocaleString() || 0 }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(transaction.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(transaction.updated_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        @click="checkStatus(transaction)"
                                        :disabled="!canCheckStatus(transaction) || checkingStatus[transaction.id]"
                                        class="inline-flex items-center gap-1 px-3 py-1 text-xs bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300 rounded hover:bg-indigo-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <svg v-if="checkingStatus[transaction.id]" class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ checkingStatus[transaction.id] ? 'Checking...' : 'Check' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="transactionsList.length === 0" class="py-12 text-center text-gray-500 dark:text-gray-400">
                        No transactions found
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="transactionsList.length > 0" class="border-t border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }} transactions
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                @click="goToPage(pagination.prev_page_url)"
                                :disabled="!pagination.prev_page_url"
                                class="px-3 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Previous
                            </button>
                            
                            <template v-for="(link, index) in pagination.links" :key="index">
                                <button
                                    v-if="link.url && !link.label.includes('Previous') && !link.label.includes('Next')"
                                    @click="goToPage(link.url)"
                                    :class="[
                                        'px-3 py-1 text-sm rounded-lg',
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700'
                                    ]"
                                    v-html="link.label"
                                />
                            </template>
                            
                            <button
                                @click="goToPage(pagination.next_page_url)"
                                :disabled="!pagination.next_page_url"
                                class="px-3 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
