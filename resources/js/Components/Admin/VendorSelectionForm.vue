<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Vendor Selection</h3>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- SME Plan -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SME Plan</label>
        <div class="flex items-center space-x-2">
          <select 
            v-model="formData.SME"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            @change="saveField('SME')"
          >
            <option value="1">VENDOR_1</option>
            <option value="2">VENDOR_2</option>
            <option value="3">VENDOR_3</option>
            <option value="4">VENDOR_4</option>
            <option value="5">VENDOR_5</option>
          </select>
          <button
            type="button"
            @click="saveField('SME')"
            :disabled="loadingStates.SME"
            class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            <span v-if="loadingStates.SME">
              <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            <span v-else-if="successStates.SME" class="text-green-500">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </span>
            <span v-else>Save</span>
          </button>
        </div>
      </div>

      <!-- SME Plan 2 -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SME Plan 2</label>
        <div class="flex items-center space-x-2">
          <select 
            v-model="formData.SME2"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            @change="saveField('SME2')"
          >
            <option value="1">VENDOR1</option>
            <option value="2">VENDOR2</option>
            <option value="3">VENDOR3</option>
            <option value="4">VENDOR4</option>
            <option value="5">VENDOR5</option>
          </select>
          <button
            type="button"
            @click="saveField('SME2')"
            :disabled="loadingStates.SME2"
            class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            <span v-if="loadingStates.SME2">
              <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            <span v-else-if="successStates.SME2" class="text-green-500">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </span>
            <span v-else>Save</span>
          </button>
        </div>
      </div>

      <!-- Corporate Gifting -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Corporate Gifting</label>
        <div class="flex items-center space-x-2">
          <select 
            v-model="formData.CORPORATE_GIFTING"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            @change="saveField('CORPORATE_GIFTING')"
          >
            <option value="1">VENDOR1</option>
            <option value="2">VENDOR2</option>
            <option value="3">VENDOR3</option>
            <option value="4">VENDOR4</option>
            <option value="5">VENDOR5</option>
          </select>
          <button
            type="button"
            @click="saveField('CORPORATE_GIFTING')"
            :disabled="loadingStates.CORPORATE_GIFTING"
            class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            <span v-if="loadingStates.CORPORATE_GIFTING">
              <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            <span v-else-if="successStates.CORPORATE_GIFTING" class="text-green-500">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </span>
            <span v-else>Save</span>
          </button>
        </div>
      </div>

      <!-- Corporate Gifting 2 -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Corporate Gifting 2</label>
        <div class="flex items-center space-x-2">
          <select 
            v-model="formData.CORPORATE_GIFTING2"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            @change="saveField('CORPORATE_GIFTING2')"
          >
            <option value="1">VENDOR_1</option>
            <option value="2">VENDOR_2</option>
            <option value="3">VENDOR_3</option>
            <option value="4">VENDOR_4</option>
            <option value="5">VENDOR_5</option>
          </select>
          <button
            type="button"
            @click="saveField('CORPORATE_GIFTING2')"
            :disabled="loadingStates.CORPORATE_GIFTING2"
            class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            <span v-if="loadingStates.CORPORATE_GIFTING2">
              <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            <span v-else-if="successStates.CORPORATE_GIFTING2" class="text-green-500">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </span>
            <span v-else>Save</span>
          </button>
        </div>
      </div>

      <!-- Data Share -->
      <div class="space-y-2 md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data Share</label>
        <div class="flex items-center space-x-2">
          <select 
            v-model="formData.DATASHARE"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            @change="saveField('DATASHARE')"
          >
            <option value="1">VENDOR_1</option>
            <option value="2">VENDOR_2</option>
            <option value="3">VENDOR_3</option>
            <option value="4">VENDOR_4</option>
            <option value="5">VENDOR_5</option>
          </select>
          <button
            type="button"
            @click="saveField('DATASHARE')"
            :disabled="loadingStates.DATASHARE"
            class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            <span v-if="loadingStates.DATASHARE">
              <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            <span v-else-if="successStates.DATASHARE" class="text-green-500">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </span>
            <span v-else>Save</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  networkId: String,
  initialData: Object
});

const formData = reactive({
  SME: '1',
  SME2: '1',
  CORPORATE_GIFTING: '1',
  CORPORATE_GIFTING2: '1',
  DATASHARE: '1'
});

const loadingStates = reactive({
  SME: false,
  SME2: false,
  CORPORATE_GIFTING: false,
  CORPORATE_GIFTING2: false,
  DATASHARE: false
});

const successStates = reactive({
  SME: false,
  SME2: false,
  CORPORATE_GIFTING: false,
  CORPORATE_GIFTING2: false,
  DATASHARE: false
});

onMounted(() => {
  if (props.initialData) {
    Object.assign(formData, props.initialData);
  }
});

const saveField = async (field) => {
  const fieldValue = formData[field];
  
  // Set loading state
  loadingStates[field] = true;
  successStates[field] = false;

  try {
    await axios.post('/api/admin/vendor-selection', {
      id: props.networkId,
      [field]: fieldValue,
      // Send all values to maintain consistency
      SME: formData.SME,
      SME2: formData.SME2,
      CORPORATE_GIFTING: formData.CORPORATE_GIFTING,
      CORPORATE_GIFTING2: formData.CORPORATE_GIFTING2,
      DATASHARE: formData.DATASHARE
    });

    // Show success indicator
    successStates[field] = true;

    // Hide success indicator after 2 seconds
    setTimeout(() => {
      successStates[field] = false;
    }, 2000);
  } catch (error) {
    console.error(`Error saving ${field}:`, error);
    alert(`Error saving ${field}: ${error.response?.data?.message || error.message}`);
  } finally {
    loadingStates[field] = false;
  }
};

defineExpose({ saveField });
</script>