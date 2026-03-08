<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    history: Array,
    filter: String,
});

const getStatusColor = (status) => {
    const colors = {
        verified: 'text-green-600 bg-green-100',
        pending: 'text-yellow-600 bg-yellow-100',
        failed: 'text-red-600 bg-red-100',
    };
    return colors[status] || 'text-gray-600 bg-gray-100';
};
</script>

<template>
    <Head title="Verification History" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verification History</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filter Tabs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <Link
                                :href="route('verification.history')"
                                :class="[
                                    'py-4 px-6 border-b-2 font-medium text-sm',
                                    !filter
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                All
                            </Link>
                            <Link
                                :href="route('verification.history', { type: 'nin' })"
                                :class="[
                                    'py-4 px-6 border-b-2 font-medium text-sm',
                                    filter === 'nin'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                NIN Only
                            </Link>
                            <Link
                                :href="route('verification.history', { type: 'bvn' })"
                                :class="[
                                    'py-4 px-6 border-b-2 font-medium text-sm',
                                    filter === 'bvn'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                BVN Only
                            </Link>
                        </nav>
                    </div>
                </div>

                <!-- History Table -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div v-if="history.length === 0" class="text-center py-8 text-gray-500">
                            No verification history found.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Identity Number</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="item in history" :key="item.id">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.type }}</td>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ item.identity_number }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">₦{{ item.amount.toLocaleString() }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(item.status)]">
                                                {{ item.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ item.date }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Back Button -->
                        <div class="mt-6">
                            <Link
                                :href="route('verification.nin')"
                                class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                            >
                                ← Back to Verification
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
