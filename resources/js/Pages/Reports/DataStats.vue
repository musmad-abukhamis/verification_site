<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    filters: Object,
    range: Object,
    overall: Object,
    networkStats: Array,
    daily: Array,
});

const preset = ref(props.filters?.preset ?? 'last30days');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

const presets = [
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: 'Last 7 days', value: 'last7days' },
    { label: 'Last 30 days', value: 'last30days' },
    { label: 'Custom Range', value: 'custom' },
];

const NETWORK_COLORS = { MTN: '#FFCC00', Airtel: '#FF0000', Glo: '#00B050', '9mobile': '#0066CC' };
const colorFor = (n) => NETWORK_COLORS[n] || '#6366F1';

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const reload = () => {
    router.get(
        route('reports.data-stats'),
        {
            preset: preset.value,
            from: preset.value === 'custom' ? from.value || undefined : undefined,
            to: preset.value === 'custom' ? to.value || undefined : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(preset, (v) => {
    if (v !== 'custom') reload();
});

const maxNetworkTxns = computed(() => Math.max(1, ...props.networkStats.map((n) => n.total)));
const maxDailyRevenue = computed(() => Math.max(1, ...props.daily.map((d) => d.revenue)));
const bestNetwork = computed(() =>
    props.networkStats.length
        ? props.networkStats.reduce((p, c) => (p.total >= c.total ? p : c)).network
        : 'N/A',
);
</script>

<template>
    <Head title="Data Sub Stats" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Sub Stats</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Date range controls -->
                <div class="bg-white shadow-sm rounded-lg p-4 flex flex-col md:flex-row md:items-center gap-3 md:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <select v-model="preset" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="p in presets" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                        <template v-if="preset === 'custom'">
                            <input v-model="from" type="date" class="rounded-md border-gray-300 shadow-sm" />
                            <span class="text-gray-400">to</span>
                            <input v-model="to" type="date" class="rounded-md border-gray-300 shadow-sm" />
                            <button @click="reload" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Apply</button>
                        </template>
                    </div>
                    <span class="text-sm text-gray-500">{{ range.from }} – {{ range.to }}</span>
                </div>

                <!-- Overview cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900">{{ overall.total.toLocaleString() }}</p>
                        <p class="text-xs text-green-600 mt-1">{{ overall.success }} successful · {{ overall.failed }} failed</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Total Spent</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(overall.revenue) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Avg {{ formatCurrency(overall.avg_value) }} / txn</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Data Used (success)</p>
                        <p class="text-2xl font-bold text-gray-900">{{ overall.data_gb }} GB</p>
                        <p class="text-xs text-gray-500 mt-1">{{ (overall.data_gb * 1000).toFixed(0) }} MB total</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Success Rate</p>
                        <p class="text-2xl font-bold text-gray-900">{{ overall.success_rate }}%</p>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <div class="bg-green-600 h-2 rounded-full" :style="{ width: overall.success_rate + '%' }"></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Network breakdown -->
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800 mb-1">Sales by Network</h3>
                        <p class="text-sm text-gray-500 mb-4">Distribution of your transactions across networks</p>
                        <div v-if="networkStats.length === 0" class="text-center py-8 text-gray-400">No transactions in this period</div>
                        <div v-else class="space-y-4">
                            <div v-for="n in networkStats" :key="n.network">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="flex items-center gap-2 font-medium text-gray-700">
                                        <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: colorFor(n.network) }"></span>
                                        {{ n.network }}
                                    </span>
                                    <span class="text-gray-500">{{ n.total }} txns · {{ formatCurrency(n.amount) }} · {{ n.dataGB }} GB</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full" :style="{ width: (n.total / maxNetworkTxns * 100) + '%', backgroundColor: colorFor(n.network) }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick stats -->
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Quick Stats</h3>
                        <dl class="space-y-4">
                            <div class="flex justify-between"><dt class="text-sm text-gray-500">Best Network</dt><dd class="font-semibold">{{ bestNetwork }}</dd></div>
                            <div class="flex justify-between"><dt class="text-sm text-gray-500">Networks Used</dt><dd class="font-semibold">{{ networkStats.length }}</dd></div>
                            <div class="flex justify-between"><dt class="text-sm text-gray-500">Avg Data / Txn</dt><dd class="font-semibold">{{ overall.success > 0 ? (overall.data_gb / overall.success).toFixed(2) : 0 }} GB</dd></div>
                            <div class="flex justify-between border-t pt-3"><dt class="text-sm font-medium text-gray-700">Success Rate</dt><dd class="font-bold text-lg">{{ overall.success_rate }}%</dd></div>
                        </dl>
                    </div>
                </div>

                <!-- Daily trend -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">Daily Revenue Trend</h3>
                    <p class="text-sm text-gray-500 mb-4">Successful revenue per day</p>
                    <div v-if="daily.length === 0" class="text-center py-8 text-gray-400">No trend data for this period</div>
                    <div v-else class="flex items-end gap-1 h-48 overflow-x-auto">
                        <div v-for="d in daily" :key="d.date" class="flex-1 min-w-[10px] flex flex-col items-center justify-end group relative">
                            <div
                                class="w-full bg-indigo-500 hover:bg-indigo-600 rounded-t transition-all"
                                :style="{ height: Math.max(2, d.revenue / maxDailyRevenue * 160) + 'px' }"
                            ></div>
                            <div class="absolute bottom-full mb-1 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                                {{ d.date }}: {{ formatCurrency(d.revenue) }} ({{ d.success }} txns)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
