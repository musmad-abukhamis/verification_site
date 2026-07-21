<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ServicePriceTable from '@/Components/ServicePriceTable.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    services: Array,
    roles: Array,
});

const groups = [
    { key: 'verification', title: 'Verification Fees', blurb: 'Charged when a lookup runs. Slips are billed separately.' },
    { key: 'slip', title: 'Slip Downloads', blurb: 'Charged when a slip is generated, on top of the verification fee.' },
];
</script>

<template>
    <Head title="Service Prices" />
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Service Prices</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Each service has one base price. Add a price against a role to charge that role differently —
                    leave it blank and they pay the base price.
                </p>
            </div>

            <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg">
                {{ $page.props.flash.success }}
            </div>

            <ServicePriceTable
                :groups="groups"
                :services="services"
                :roles="roles"
                update-route="admin.service-prices.update"
            />
        </div>
    </AdminLayout>
</template>
