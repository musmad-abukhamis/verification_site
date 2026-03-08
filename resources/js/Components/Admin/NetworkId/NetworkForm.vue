<template>
  <div class="w-full min-h-screen mb-5">
    <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow">
      <!-- Card Header -->
      <div class="p-6 border-b dark:border-gray-700">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
          {{ networkName }} Network Configuration
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 mb-5">
          Enter network ID for each vendor (e.g., 1, 2, or 3)
        </p>
      </div>

      <!-- Card Content -->
      <div class="p-6">
        <form @submit.prevent="onSubmit" class="space-y-6">
          <!-- Vendor Network Fields -->
          <div v-for="vendor in 5" :key="vendor" class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-2">
                Vendor {{ vendor }} {{ networkName }} Network ID
              </label>
              <NetworkStrengthIndicator :strength="networkName" />
            </div>
            <input
              type="text"
              v-model="form[`vendor${vendor}Network`]"
              class="w-full max-w-[200px] rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-lime-500 focus:ring-2 focus:ring-lime-500 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              :class="{ 'border-red-500': errors[`vendor${vendor}Network`] }"
            />
            <p v-if="errors[`vendor${vendor}Network`]" class="text-red-500 text-xs mt-1">
              {{ errors[`vendor${vendor}Network`] }}
            </p>
          </div>

          <!-- Hidden ID Field -->
          <input type="hidden" v-model="form.id" />

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isSubmitting"
            class="w-full px-4 py-2 bg-lime-500 hover:bg-gray-700 text-gray-800 hover:text-white rounded-md font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <svg
              v-if="isSubmitting"
              class="animate-spin h-4 w-4"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg
              v-else
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ isSubmitting ? 'Saving...' : 'Save Network Values' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import NetworkStrengthIndicator from './NetworkStrengthIndicator.vue';

const props = defineProps({
  networkName: {
    type: String,
    required: true
  },
  existingData: {
    type: Object,
    default: null
  }
});

// Get initial value from props or default to '1'
const getInitialValue = (vendorNum) => {
  const key = `vendor${vendorNum}network`;
  if (props.existingData && props.existingData[key] !== undefined && props.existingData[key] !== null) {
    return String(props.existingData[key]);
  }
  return '1';
};

// Initialize form with props data
const form = ref({
  id: props.networkName,
  vendor1Network: getInitialValue(1),
  vendor2Network: getInitialValue(2),
  vendor3Network: getInitialValue(3),
  vendor4Network: getInitialValue(4),
  vendor5Network: getInitialValue(5),
});

const isSubmitting = ref(false);
const errors = ref({});

const onSubmit = () => {
  isSubmitting.value = true;
  errors.value = {};

  axios.post(route('admin.networks.store'), { ...form.value })
    .then(response => {
      isSubmitting.value = false;
      
      if (window.Swal) {
        window.Swal.fire({
          title: 'Success!',
          text: 'Network values have been saved to the database.',
          icon: 'success',
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
            title: 'Error',
            text: 'Failed to save network values. Please check the form.',
            icon: 'error',
            confirmButtonColor: '#65a300',
          });
        }
      } else {
        if (window.Swal) {
          window.Swal.fire({
            title: 'Error',
            text: err.message || 'An error occurred while saving.',
            icon: 'error',
            confirmButtonColor: '#65a300',
          });
        }
      }
    });
};
</script>