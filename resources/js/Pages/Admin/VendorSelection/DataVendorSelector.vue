<template>
  <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Network Vendor Settings</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Configure vendor selections for different network providers and data types
        </p>
      </div>

      <!-- Tabs for network selection -->
      <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
          <button
            v-for="(network, index) in networks"
            :key="network.id"
            @click="activeTab = network.id"
            :class="[
              activeTab === network.id
                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center'
            ]"
          >
            <component
              :is="network.icon"
              class="mr-2 h-5 w-5"
              :class="[
                activeTab === network.id
                  ? network.activeColor
                  : network.inactiveColor
              ]"
            />
            {{ network.name }}
          </button>
        </nav>
      </div>

      <!-- Tab content -->
      <div class="mt-6">
        <div v-if="loading && activeTab" class="flex justify-center py-12">
          <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <div v-else-if="error" class="rounded-md bg-red-50 p-4 mb-6 dark:bg-red-900 dark:bg-opacity-30">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800 dark:text-red-200">{{ error }}</h3>
            </div>
          </div>
        </div>

        <div v-else>
          <VendorSelectionForm
            v-if="activeTab && vendorSelections[activeTab]"
            :network-id="activeTab"
            :initial-data="vendorSelections[activeTab]"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { SignalIcon, WifiIcon, RadioIcon, PhoneIcon } from '@heroicons/vue/24/outline';
import VendorSelectionForm from '@/Components/Admin/VendorSelectionForm.vue';

const props = defineProps({
  vendorSelections: Object
});

const activeTab = ref('MTN');
const loading = ref(false);
const error = ref('');

const networks = reactive([
  {
    id: 'MTN',
    name: 'MTN',
    icon: SignalIcon,
    activeColor: 'text-yellow-500',
    inactiveColor: 'text-gray-400 dark:text-gray-500'
  },
  {
    id: 'AIRTEL',
    name: 'AIRTEL',
    icon: WifiIcon,
    activeColor: 'text-red-500',
    inactiveColor: 'text-gray-400 dark:text-gray-500'
  },
  {
    id: 'GLO',
    name: 'GLO',
    icon: RadioIcon,
    activeColor: 'text-green-500',
    inactiveColor: 'text-gray-400 dark:text-gray-500'
  },
  {
    id: '9MOBILE',
    name: '9MOBILE',
    icon: PhoneIcon,
    activeColor: 'text-emerald-500',
    inactiveColor: 'text-gray-400 dark:text-gray-500'
  }
]);

// Function to load vendor selection for a specific network
const loadVendorSelection = async (networkId) => {
  loading.value = true;
  error.value = '';

  try {
    // We already have the data from props, so we just need to make sure it exists
    if (!props.vendorSelections[networkId]) {
      // If not in props, we could fetch it from the API
      console.warn(`Vendor selection for ${networkId} not found in props`);
    }
  } catch (err) {
    error.value = err.message || 'Failed to load vendor selection';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  // Initialize with MTN data
  if (props.vendorSelections && props.vendorSelections.MTN) {
    activeTab.value = 'MTN';
  }
});
</script>
