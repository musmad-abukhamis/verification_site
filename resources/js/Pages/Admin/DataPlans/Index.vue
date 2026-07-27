<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    plans: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    networks: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const network = ref(props.filters.network || 'all');
let timer;
watch([search, network], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.dataplan.index'), { search: search.value, network: network.value }, { preserveState: true, replace: true });
    }, 300);
});

const money = (n) => '₦' + Number(n ?? 0).toLocaleString('en-NG');
const toggleStatus = (p) => router.patch(route('admin.dataplan.toggle-status', p.id), {}, { preserveScroll: true });
const togglePlanStatus = (p) => router.patch(route('admin.dataplan.toggle-plan-status', p.id), {}, { preserveScroll: true });
const destroy = (p) => { if (confirm(`Delete plan "${p.name}"?`)) router.delete(route('admin.dataplan.destroy', p.id), { preserveScroll: true }); };
</script>

<template>
    <Head title="Data Plans" />
    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Data Plans</h1>
                <Link :href="route('admin.dataplan.create')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">+ New plan</Link>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <input v-model="search" placeholder="Search name / type…" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                <select v-model="network" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                    <option value="all">All networks</option>
                    <option v-for="n in networks" :key="n" :value="n">{{ n.toUpperCase() }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Prices (U/A/API)</th>
                            <th class="px-4 py-3">Vendors</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Visible</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm dark:divide-gray-700">
                        <tr v-for="p in plans.data" :key="p.id">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <!-- The plan id external integrators quote. -->
                                    <span
                                        class="rounded bg-indigo-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                        title="Public plan id — this is what developers send as plan_id"
                                    >{{ p.code }}</span>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ p.network.toUpperCase() }} · {{ p.name }}</p>
                                </div>
                                <p class="text-xs text-gray-400">{{ p.type }} · {{ p.validity }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ money(p.price) }} / {{ money(p.agent_price) }} / {{ money(p.api_price) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ p.mappings_count }} mapped</td>
                            <td class="px-4 py-3">
                                <button @click="toggleStatus(p)" class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="p.status === 'on' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-600 dark:bg-red-900/40'">
                                    {{ p.status === 'on' ? 'On' : 'Off' }}
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <button @click="togglePlanStatus(p)" class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="p.plan_status === 'on' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700'">
                                    {{ p.plan_status === 'on' ? 'Visible' : 'Hidden' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('admin.dataplan.edit', p.id)" class="mr-3 text-blue-600 hover:underline">Edit</Link>
                                <button @click="destroy(p)" class="text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!plans.data.length"><td colspan="6" class="px-4 py-8 text-center text-gray-500">No plans.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="plans.links && plans.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="(link, i) in plans.links" :key="i">
                    <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-lg px-3 py-1 text-sm"
                        :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300'" v-html="link.label" />
                    <span v-else class="rounded-lg px-3 py-1 text-sm text-gray-300" v-html="link.label" />
                </template>
            </div>
        </div>
    </AdminLayout>
</template>
