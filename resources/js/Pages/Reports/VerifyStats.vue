<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    filters: Object,
    range: Object,
    overall: Object,
    typeStats: Array,
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

const TYPE_COLORS = { BVN: '#7C3AED', NIN: '#0891B2' };
const colorFor = (t) => TYPE_COLORS[t] || '#6366F1';

const formatCurrency = (amount) =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(amount ?? 0));

const reload = () => {
    router.get(
        route('reports.verify-stats'),
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

const maxTypeTxns = computed(() => Math.max(1, ...props.typeStats.map((t) => t.total)));
const maxDailyTotal = computed(() => Math.max(1, ...props.daily.map((d) => d.total)));
</script>

<template>
    <Head title="NIN/BVN Verify Stats" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">NIN/BVN Verify Stats</h2>
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
                        <p class="text-sm font-medium text-gray-500">Total Verifications</p>
                        <p class="text-2xl font-bold text-gray-900">{{ overall.total.toLocaleString() }}</p>
                        <p class="text-xs text-green-600 mt-1">{{ overall.success }} successful · {{ overall.failed }} failed</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">Total Spent</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(overall.spent) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Avg {{ formatCurrency(overall.avg_value) }} / verification</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-500">ID Types Used</p>
                        <p class="text-2xl font-bold text-gray-900">{{ typeStats.length }}</p>
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
                    <!-- ID type breakdown -->
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800 mb-1">Verifications by ID Type</h3>
                        <p class="text-sm text-gray-500 mb-4">Distribution across NIN / BVN searches</p>
                        <div v-if="typeStats.length === 0" class="text-center py-8 text-gray-400">No verifications in this period</div>
                        <div v-else class="space-y-4">
                            <div v-for="t in typeStats" :key="t.idtype">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="flex items-center gap-2 font-medium text-gray-700">
                                        <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: colorFor(t.idtype) }"></span>
                                        {{ t.idtype }}
                                    </span>
                                    <span class="text-gray-500">{{ t.total }} · {{ t.success }} ok · {{ formatCurrency(t.amount) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full" :style="{ width: (t.total / maxTypeTxns * 100) + '%', backgroundColor: colorFor(t.idtype) }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status summary -->
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Status Summary</h3>
                        <dl class="space-y-4">
                            <div class="flex justify-between"><dt class="text-sm text-gray-500">Successful</dt><dd class="font-semibold text-green-600">{{ overall.success }}</dd></div>
                            <div class="flex justify-between"><dt class="text-sm text-gray-500">Failed / Other</dt><dd class="font-semibold text-red-600">{{ overall.failed }}</dd></div>
                            <div class="flex justify-between border-t pt-3"><dt class="text-sm font-medium text-gray-700">Success Rate</dt><dd class="font-bold text-lg">{{ overall.success_rate }}%</dd></div>
                        </dl>
                    </div>
                </div>

                <!-- Daily trend -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">Daily Verification Trend</h3>
                    <p class="text-sm text-gray-500 mb-4">Verifications per day</p>
                    <div v-if="daily.length === 0" class="text-center py-8 text-gray-400">No trend data for this period</div>
                    <div v-else class="flex items-end gap-1 h-48 overflow-x-auto">
                        <div v-for="d in daily" :key="d.date" class="flex-1 min-w-[10px] flex flex-col items-center justify-end group relative">
                            <div
                                class="w-full bg-cyan-600 hover:bg-cyan-700 rounded-t transition-all"
                                :style="{ height: Math.max(2, d.total / maxDailyTotal * 160) + 'px' }"
                            ></div>
                            <div class="absolute bottom-full mb-1 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">
                                {{ d.date }}: {{ d.total }} ({{ d.success }} ok)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
