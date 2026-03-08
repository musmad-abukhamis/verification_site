<template>

  <div class="max-w-7xl mx-auto px-4 py-8 mb-10">
    <!-- Page Header -->
    <div class="mb-8 text-center">
      <h2 class="text-3xl font-bold text-gray-900">Vendor API Configuration</h2>
      <p class="mt-2 text-gray-600">Manage your vendor API URLs and keys</p>
    </div>

    <!-- Error State -->
    <div v-if="fetchError" class="min-h-screen flex items-center justify-center bg-red-50">
      <div class="bg-white p-8 rounded-lg shadow-lg flex items-center space-x-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-red-700">{{ fetchError }}</p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-else-if="!existingVendor && !error" class="min-h-screen flex items-center justify-center">
      <div class="flex items-center space-x-4">
        <svg class="h-6 w-6 animate-spin text-lime-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-gray-600">Loading vendor data...</p>
      </div>
    </div>

    <!-- Main Form -->
    <form v-else @submit.prevent="onSubmit" class="space-y-8">
      <!-- Vendor Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="index in 5" :key="index" class="bg-white p-6 rounded-xl shadow-md space-y-4">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Vendor {{ index }}</h3>
          
          <!-- URL Field -->
          <div class="space-y-2">
            <label class="flex items-center space-x-2 text-sm font-medium text-gray-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-lime-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
              </svg>
              <span>URL</span>
            </label>
            <input
              type="url"
              v-model="form[`vendor${index}url`]"
              placeholder="https://api.example.com"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-lime-500 focus:ring-2 focus:ring-lime-500 focus:outline-none transition-colors"
              :class="{ 'border-red-500': errors[`vendor${index}url`] }"
            />
            <p v-if="errors[`vendor${index}url`]" class="text-red-500 text-xs">{{ errors[`vendor${index}url`] }}</p>
          </div>

          <!-- API Key Field -->
          <div class="space-y-2">
            <label class="flex items-center space-x-2 text-sm font-medium text-gray-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-lime-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
              </svg>
              <span>API Key</span>
            </label>
            <div class="relative">
              <input
                :type="showKeys[`vendor${index}key`] ? 'text' : 'password'"
                v-model="form[`vendor${index}key`]"
                placeholder="Enter API key"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-16 text-sm focus:border-lime-500 focus:ring-2 focus:ring-lime-500 focus:outline-none transition-colors"
                :class="{ 'border-red-500': errors[`vendor${index}key`] }"
              />
              <button
                type="button"
                @click="toggleKeyVisibility(`vendor${index}key`)"
                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-xs text-gray-500 hover:text-gray-700 font-medium"
              >
                {{ showKeys[`vendor${index}key`] ? "Hide" : "Show" }}
              </button>
            </div>
            <p v-if="errors[`vendor${index}key`]" class="text-red-500 text-xs">{{ errors[`vendor${index}key`] }}</p>
          </div>
        </div>
      </div>

      <!-- Hidden ID Field -->
      <input type="hidden" v-model="form.id" />

      <!-- Submit Button -->
      <div class="mt-8 flex justify-center">
        <button
          type="submit"
          :disabled="isSubmitting"
          class="px-6 py-3 bg-lime-600 text-white rounded-lg hover:bg-lime-700 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200 flex items-center space-x-2 font-medium"
        >
          <!-- Loading Spinner -->
          <svg v-if="isSubmitting" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <!-- Save Icon -->
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          <span>{{ isSubmitting ? 'Saving...' : 'Save Changes' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  vendorApi: Object
});

// Form state
const form = reactive({
  id: null,
  vendor1url: '',
  vendor1key: '',
  vendor2url: '',
  vendor2key: '',
  vendor3url: '',
  vendor3key: '',
  vendor4url: '',
  vendor4key: '',
  vendor5url: '',
  vendor5key: '',
});

// UI state
const existingVendor = ref(null);
const fetchError = ref(null);
const error = ref(null);
const isSubmitting = ref(false);
const showKeys = ref({});
const errors = ref({});

// Initialize data on mount
onMounted(() => {
  if (props.vendorApi) {
    existingVendor.value = props.vendorApi;
    // Populate form with existing data
    Object.assign(form, {
      id: props.vendorApi.id || null,
      vendor1url: props.vendorApi.vendor1url || '',
      vendor1key: props.vendorApi.vendor1key || '',
      vendor2url: props.vendorApi.vendor2url || '',
      vendor2key: props.vendorApi.vendor2key || '',
      vendor3url: props.vendorApi.vendor3url || '',
      vendor3key: props.vendorApi.vendor3key || '',
      vendor4url: props.vendorApi.vendor4url || '',
      vendor4key: props.vendorApi.vendor4key || '',
      vendor5url: props.vendorApi.vendor5url || '',
      vendor5key: props.vendorApi.vendor5key || '',
    });
  } else {
    // Set defaults if no existing data
    for (let i = 1; i <= 5; i++) {
      form[`vendor${i}url`] = '';
      form[`vendor${i}key`] = '';
    }
  }
});

// Toggle API key visibility
const toggleKeyVisibility = (field) => {
  if (!showKeys.value[field]) {
    showKeys.value[field] = false;
  }
  showKeys.value[field] = !showKeys.value[field];
};

// Handle form submission
const onSubmit = () => {
  error.value = null;
  isSubmitting.value = true;
  errors.value = {};

  // Use axios to make the PUT request since we're returning JSON from the controller
  axios.put(route('admin.vendors.api.update'), { ...form })
    .then(response => {
      isSubmitting.value = false;
      
      if (window.Swal) {
        window.Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: 'Vendor API configuration updated successfully!',
          confirmButtonColor: '#65a300',
        });
      }
    })
    .catch(err => {
      isSubmitting.value = false;
      if (err.response && err.response.status === 422) {
        errors.value = err.response.data.errors || {};
        
        if (window.Swal) {
          window.Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Please fix the validation errors.',
            confirmButtonColor: '#65a300',
          });
        }
      } else {
        if (window.Swal) {
          window.Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: err.message || 'An error occurred while saving the configuration.',
            confirmButtonColor: '#65a300',
          });
        }
      }
    });
};
</script>