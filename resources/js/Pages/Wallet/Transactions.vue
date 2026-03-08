<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    transactions: Object,
    filters: Object,
});

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

const transactionTypes = [
    { value: '', label: 'All Types' },
    { value: 'airtime', label: 'Airtime' },
    { value: 'data', label: 'Data' },
    { value: 'nin_verification', label: 'NIN Verification' },
    { value: 'bvn_verification', label: 'BVN Verification' },
    { value: 'wallet_funding', label: 'Wallet Funding' },
];

const statuses = [
    { value: '', label: 'All Statuses' },
    { value: 'success', label: 'Success' },
    { value: 'pending', label: 'Pending' },
    { value: 'failed', label: 'Failed' },
];
</script>

<template>
    <Head title="Transaction History" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transaction History</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-4">
                        <form class="flex flex-wrap gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <select
                                    name="type"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    @change="$event.target.form.submit()"
                                >
                                    <option
                                        v-for="type in transactionTypes"
                                        :key="type.value"
                                        :value="type.value"
                                        :selected="filters.type === type.value"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select
                                    name="status"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    @change="$event.target.form.submit()"
                                >
                                    <option
                                        v-for="status in statuses"
                                        :key="status.value"
                                        :value="status.value"
                                        :selected="filters.status === status.value"
                                    >
                                        {{ status.label }}
                                    </option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="transactions.data.length === 0" class="text-center py-8 text-gray-500">
                            No transactions found.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fee</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="transaction in transactions.data" :key="transaction.id">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ transaction.reference }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ getTypeLabel(transaction.type) }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">₦{{ transaction.amount.toLocaleString() }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">₦{{ transaction.fee.toLocaleString() }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">₦{{ transaction.total_amount.toLocaleString() }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(transaction.status)]">
                                                {{ transaction.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ transaction.date }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <div class="mt-6 flex items-center justify-between">
                                <div class="text-sm text-gray-500">
                                    Showing {{ transactions.from }} to {{ transactions.to }} of {{ transactions.total }} results
                                </div>
                                <div class="flex gap-2">
                                    <Link
                                        v-for="link in transactions.links"
                                        :key="link.label"
                                        :href="link.url"
                                        :class="[
                                            'px-3 py-1 rounded text-sm',
                                            link.active
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                            !link.url && 'opacity-50 cursor-not-allowed'
                                        ]"
                                        v-html="link.label"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
