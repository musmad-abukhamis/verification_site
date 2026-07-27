<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ServicePriceTable from '@/Components/ServicePriceTable.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    services: Array,
    roles: Array,
});

const groups = [
    { key: 'bvn_modification', title: 'Modification Services', blurb: 'Charged when a modification request is submitted.' },
    { key: 'bvn_search', title: 'Search & Retrieval', blurb: 'BVN lookups and retrieval requests.' },
    { key: 'bvn_other', title: 'Onboarding & Others', blurb: 'SDK onboarding and the ID card fee.' },
];
</script>

<template>
    <Head title="BVN Service Prices" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">BVN Service Prices</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Each service has one base price. Add a price against a role to charge that role differently —
                    leave it blank and they pay the base price.
                </p>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">
                {{ $page.props.flash.success }}
            </div>

            <ServicePriceTable
                :groups="groups"
                :services="services"
                :roles="roles"
                update-route="admin.bvn-prices.update"
            />
        </div>
    </AdminLayout>
</template>
