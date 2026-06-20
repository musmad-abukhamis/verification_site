<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    forms: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
let searchTimeout;

const fetchForms = () => {
    router.get(route('bvn-sdk-form.submissions'), { search: search.value }, {
        preserveState: true, preserveScroll: true, only: ['forms'],
    });
};

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true }); };

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' });
};

const statusClass = (s) => {
    const map = {
        onboarded: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        submitted: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
        picked: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        pending: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[s?.toLowerCase()] || map.pending;
};
</script>

<template>
    <Head title="My BVN Onboarding Submissions" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My BVN Onboarding Submissions</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Track your submitted BVN SDK onboarding registrations.</p>
                </div>
                <Link :href="route('bvn-sdk-form.index')" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg whitespace-nowrap">New Registration</Link>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <input v-model="search" @input="() => { clearTimeout(searchTimeout); searchTimeout = setTimeout(fetchForms, 400); }"
                        type="text" placeholder="Search by name, email, phone, state, zone..."
                        class="w-full md:max-w-md rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm" />
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">State</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="f in forms.data" :key="f.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ f.firstName }} {{ f.lastName }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ f.email }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ f.phoneNumber }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ f.stateOfResidence }}</td>
                                <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium capitalize', statusClass(f.status)]">{{ f.status }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(f.created_at) }}</td>
                                <td class="px-4 py-3"><Link :href="route('bvn-sdk-form.show', f.id)" class="text-violet-600 hover:text-violet-800 dark:text-violet-400 text-sm font-medium">View</Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="forms.data.length === 0" class="py-10 text-center text-gray-400">No onboarding submissions yet.</div>
                <div v-if="forms.total > 0" class="p-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-500">Showing {{ forms.from }}–{{ forms.to }} of {{ forms.total }}</p>
                    <div class="flex gap-2">
                        <button @click="goToPage(forms.prev_page_url)" :disabled="!forms.prev_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Prev</button>
                        <button @click="goToPage(forms.next_page_url)" :disabled="!forms.next_page_url" class="px-3 py-1 text-sm rounded border border-gray-300 disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
