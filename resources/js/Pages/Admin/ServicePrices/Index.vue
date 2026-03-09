<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    servicePrices: Array,
    slipTypes: Array,
});

const editingPrice = ref(null);
const editingSlipType = ref(null);
const showAddSlipModal = ref(false);

const priceForm = useForm({
    price: 0,
    is_active: true,
});

const slipTypeForm = useForm({
    code: '',
    name: '',
    description: '',
    price: 0,
    component_name: '',
    sort_order: 0,
    is_active: true,
});

const editPrice = (price) => {
    editingPrice.value = price.id;
    priceForm.price = price.price;
    priceForm.is_active = price.is_active;
};

const cancelEditPrice = () => {
    editingPrice.value = null;
    priceForm.reset();
};

const updatePrice = (price) => {
    priceForm.put(route('admin.service-prices.update', price.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingPrice.value = null;
            priceForm.reset();
        },
    });
};

const editSlipType = (slip) => {
    editingSlipType.value = slip.id;
    slipTypeForm.name = slip.name;
    slipTypeForm.description = slip.description;
    slipTypeForm.price = slip.price;
    slipTypeForm.is_active = slip.is_active;
    slipTypeForm.sort_order = slip.sort_order;
};

const cancelEditSlipType = () => {
    editingSlipType.value = null;
    slipTypeForm.reset();
};

const updateSlipType = (slip) => {
    slipTypeForm.put(route('admin.slip-types.update', slip.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingSlipType.value = null;
            slipTypeForm.reset();
        },
    });
};

const openAddSlipModal = () => {
    showAddSlipModal.value = true;
    slipTypeForm.reset();
};

const storeSlipType = () => {
    slipTypeForm.post(route('admin.slip-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddSlipModal.value = false;
            slipTypeForm.reset();
        },
    });
};

const deleteSlipType = (slip) => {
    if (confirm(`Are you sure you want to delete "${slip.name}"?`)) {
        router.delete(route('admin.slip-types.destroy', slip.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Service Prices Management" />
    <AdminLayout>
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Service Prices</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Manage verification fees and slip download prices</p>
                </div>
            </div>

            <!-- Flash Messages -->
            <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg">
                {{ $page.props.flash.success }}
            </div>

            <!-- Service Prices Section -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Verification Fees</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="price in servicePrices" :key="price.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ price.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ price.description || '-' }}</td>
                                <td class="px-6 py-4">
                                    <template v-if="editingPrice === price.id">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-gray-500">₦</span>
                                            <input
                                                type="number"
                                                v-model="priceForm.price"
                                                step="0.01"
                                                min="0"
                                                class="w-24 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"
                                            />
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">₦{{ Number(price.price).toLocaleString() }}</span>
                                    </template>
                                </td>
                                <td class="px-6 py-4">
                                    <template v-if="editingPrice === price.id">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" v-model="priceForm.is_active" class="rounded" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                        </label>
                                    </template>
                                    <template v-else>
                                        <span :class="['px-2 py-1 text-xs rounded-full font-medium', price.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200']">
                                            {{ price.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </template>
                                </td>
                                <td class="px-6 py-4">
                                    <template v-if="editingPrice === price.id">
                                        <div class="flex gap-2">
                                            <button @click="updatePrice(price)" class="text-green-600 hover:text-green-800 dark:text-green-400 text-sm font-medium">Save</button>
                                            <button @click="cancelEditPrice" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 text-sm font-medium">Cancel</button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <button @click="editPrice(price)" class="text-lime-600 hover:text-lime-800 dark:text-lime-400 text-sm font-medium">Edit</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Slip Types Section -->
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Slip Types</h2>
                    <button @click="openAddSlipModal" class="px-4 py-2 bg-lime-600 hover:bg-lime-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Add Slip Type
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="slip in slipTypes" :key="slip.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <template v-if="editingSlipType === slip.id">
                                        <input type="text" v-model="slipTypeForm.name" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                    </template>
                                    <template v-else>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ slip.name }}</span>
                                    </template>
                                </td>
                                <td class="px-6 py-4">
                                    <code class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ slip.code }}</code>
                                </td>
                                <td class="px-6 py-4">
                                    <template v-if="editingSlipType === slip.id">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-gray-500">₦</span>
                                            <input type="number" v-model="slipTypeForm.price" step="0.01" min="0" class="w-24 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">₦{{ Number(slip.price).toLocaleString() }}</span>
                                    </template>
                                </td>
                                <td class="px-6 py-4">
                                    <template v-if="editingSlipType === slip.id">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" v-model="slipTypeForm.is_active" class="rounded" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                        </label>
                                    </template>
                                    <template v-else>
                                        <span :class="['px-2 py-1 text-xs rounded-full font-medium', slip.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200']">
                                            {{ slip.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </template>
                                </td>
                                <td class="px-6 py-4">
                                    <template v-if="editingSlipType === slip.id">
                                        <input type="number" v-model="slipTypeForm.sort_order" min="0" class="w-16 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
                                    </template>
                                    <template v-else>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ slip.sort_order }}</span>
                                    </template>
                                </td>
                                <td class="px-6 py-4">
                                    <template v-if="editingSlipType === slip.id">
                                        <div class="flex gap-2">
                                            <button @click="updateSlipType(slip)" class="text-green-600 hover:text-green-800 dark:text-green-400 text-sm font-medium">Save</button>
                                            <button @click="cancelEditSlipType" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 text-sm font-medium">Cancel</button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex gap-3">
                                            <button @click="editSlipType(slip)" class="text-lime-600 hover:text-lime-800 dark:text-lime-400 text-sm font-medium">Edit</button>
                                            <button @click="deleteSlipType(slip)" class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm font-medium">Delete</button>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Slip Type Modal -->
        <div v-if="showAddSlipModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Add New Slip Type</h3>
                    <form @submit.prevent="storeSlipType" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code</label>
                            <input type="text" v-model="slipTypeForm.code" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="e.g., basic" />
                            <p v-if="slipTypeForm.errors.code" class="text-xs text-red-500 mt-1">{{ slipTypeForm.errors.code }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input type="text" v-model="slipTypeForm.name" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="e.g., Basic Slip" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                            <textarea v-model="slipTypeForm.description" rows="2" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price (₦)</label>
                            <input type="number" v-model="slipTypeForm.price" step="0.01" min="0" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Component Name</label>
                            <input type="text" v-model="slipTypeForm.component_name" required class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="e.g., BasicSlip" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort Order</label>
                            <input type="number" v-model="slipTypeForm.sort_order" min="0" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" v-model="slipTypeForm.is_active" id="is_active" class="rounded" />
                            <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="submit" :disabled="slipTypeForm.processing" class="flex-1 px-4 py-2 bg-lime-600 hover:bg-lime-700 text-white font-medium rounded-lg disabled:opacity-50 transition-colors">
                                {{ slipTypeForm.processing ? 'Creating...' : 'Create' }}
                            </button>
                            <button type="button" @click="showAddSlipModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-medium rounded-lg transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
