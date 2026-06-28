<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    filters: Object,
    range: Object,
    users: Array,
    overall: Object,
    idtypeCounts: Array,
    channelCounts: Array,
    statusCounts: Array,
    idtypeStatus: Array,
});

const preset = ref(props.filters?.preset ?? 'last30days');
const userId = ref(props.filters?.userId ?? 'all');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

const presets = [
    { label: 'All Time', value: 'all-time' },
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: 'Last 7 days', value: 'last7days' },
    { label: 'Last 30 days', value: 'last30days' },
    { label: 'Last 90 days', value: 'last90days' },
    { label: 'Custom Range', value: 'custom' },
];

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const reload = () => {
    router.get(
        route('admin.reports.verify-stats'),
        {
            preset: preset.value,
            userId: userId.value,
            from: preset.value === 'custom' ? from.value || undefined : undefined,
            to: preset.value === 'custom' ? to.value || undefined : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch([userId], reload);
watch(preset, (v) => {
    if (v !== 'custom') reload();
});

const statusColor = (s) => {
    const k = String(s ?? '').toLowerCase();
    if (['success', 'completed', 'found'].includes(k)) return 'bg-green-500';
    if (['pending', 'processing'].includes(k)) return 'bg-yellow-500';
    if (['fail', 'failed', 'not found'].includes(k)) return 'bg-red-500';
    return 'bg-gray-400';
};

const pct = (count, list) => {
    const total = list.reduce((s, x) => s + x.count, 0);
    return total > 0 ? (count / total * 100) : 0;
};
const maxIdtype = computed(() => Math.max(1, ...props.idtypeCounts.map((x) => x.count)));
</script>

<template>
    <Head title="Verification Stats" />

    <AdminLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Verification Stats</h1>

            <!-- Controls -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col md:flex-row md:items-center gap-3 md:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <select v-model="userId" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option v-for="u in users" :key="u.value" :value="u.value">{{ u.label }}</option>
                    </select>
                    <select v-model="preset" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option v-for="p in presets" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <template v-if="preset === 'custom'">
                        <input v-model="from" type="date" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        <span class="text-gray-400">to</span>
                        <input v-model="to" type="date" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        <button @click="reload" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Apply</button>
                    </template>
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ range.from }} – {{ range.to }}</span>
            </div>

            <!-- Overview cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Verifications</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ overall.total.toLocaleString() }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Revenue (success)</p>
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ formatCurrency(overall.revenue) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- By ID type -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-4">By ID Type</h3>
                    <div v-if="idtypeCounts.length === 0" class="text-center py-6 text-gray-400">No data</div>
                    <div v-else class="space-y-4">
                        <div v-for="item in idtypeCounts" :key="item.label">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700 dark:text-gray-200">{{ item.label }}</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ item.count }}</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5">
                                <div class="h-2.5 rounded-full bg-indigo-500" :style="{ width: (item.count / maxIdtype * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- By status -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-4">By Status</h3>
                    <div v-if="statusCounts.length === 0" class="text-center py-6 text-gray-400">No data</div>
                    <div v-else class="space-y-4">
                        <div v-for="item in statusCounts" :key="item.label">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium capitalize text-gray-700 dark:text-gray-200">{{ item.label }}</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ item.count }} ({{ pct(item.count, statusCounts).toFixed(1) }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5">
                                <div :class="['h-2.5 rounded-full', statusColor(item.label)]" :style="{ width: pct(item.count, statusCounts) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- By channel -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-4">By Channel</h3>
                    <div v-if="channelCounts.length === 0" class="text-center py-6 text-gray-400">No channel data</div>
                    <table v-else class="min-w-full text-sm">
                        <tbody>
                            <tr v-for="item in channelCounts" :key="item.label" class="border-b border-gray-100 dark:border-gray-700">
                                <td class="py-2 text-gray-700 dark:text-gray-200">{{ item.label }}</td>
                                <td class="py-2 text-right font-medium text-gray-900 dark:text-white">{{ item.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ID type x status matrix -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-4">ID Type × Status</h3>
                    <div v-if="idtypeStatus.length === 0" class="text-center py-6 text-gray-400">No data</div>
                    <table v-else class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-2">ID Type</th>
                                <th class="py-2">Status</th>
                                <th class="py-2 text-right">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, i) in idtypeStatus" :key="i" class="border-b border-gray-100 dark:border-gray-700">
                                <td class="py-2 text-gray-700 dark:text-gray-200">{{ item.idtype }}</td>
                                <td class="py-2 capitalize text-gray-700 dark:text-gray-200">{{ item.status }}</td>
                                <td class="py-2 text-right font-medium text-gray-900 dark:text-white">{{ item.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
