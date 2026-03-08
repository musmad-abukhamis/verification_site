<template>
  <div class="container mx-auto px-4 py-10">
    <!-- Back Link -->
    <div class="mb-6">
      <Link
        :href="route('admin.networkid.index')"
        class="inline-flex items-center text-gray-600 hover:text-lime-600 transition-colors"
      >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Networks
      </Link>
    </div>

    <!-- Page Header -->
    <div class="mb-8 text-center">
      <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
        {{ networkName }} Network Configuration
      </h2>
      <p class="mt-2 text-gray-600 dark:text-gray-400">
        Configure vendor network IDs for {{ networkName }}
      </p>
    </div>

    <!-- Network Form -->
    <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow">
      <!-- Card Header -->
      <div class="p-6 border-b dark:border-gray-700">
        <div class="flex items-center justify-between">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
            Vendor Network IDs
          </h3>
          <NetworkStrengthIndicator :strength="networkName" />
        </div>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Enter network ID for each vendor (e.g., 1, 2, or 3)
        </p>
      </div>

      <!-- Card Content -->
      <div class="p-6">
        <form @submit.prevent="onSubmit" class="space-y-6">
          <!-- Vendor Network Fields -->
          <div v-for="vendor in 5" :key="vendor" class="space-y-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Vendor {{ vendor }} {{ networkName }} Network ID
            </label>
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
          <div class="flex gap-4 pt-4">
            <Link
              :href="route('admin.networkid.index')"
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md font-medium hover:bg-gray-300 transition-colors"
            >
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="flex-1 px-4 py-2 bg-lime-600 text-white rounded-md font-medium hover:bg-lime-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
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
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import NetworkStrengthIndicator from '@/Components/Admin/NetworkId/NetworkStrengthIndicator.vue';

const props = defineProps({
  networkName: {
    type: String,
    required: true
  },
  networkData: {
    type: Object,
    required: true
  }
});

// Initialize form with existing data
const form = useForm({
  id: props.networkName,
  vendor1Network: props.networkData.vendor1network || '1',
  vendor2Network: props.networkData.vendor2network || '1',
  vendor3Network: props.networkData.vendor3network || '1',
  vendor4Network: props.networkData.vendor4network || '1',
  vendor5Network: props.networkData.vendor5network || '1',
});

const isSubmitting = ref(false);
const errors = ref({});

const onSubmit = () => {
  isSubmitting.value = true;
  
  form.post(route('admin.networks.store'), {
    preserveScroll: true,
    onSuccess: () => {
      isSubmitting.value = false;
      if (window.Swal) {
        window.Swal.fire({
          title: 'Success!',
          text: 'Network values have been saved to the database.',
          icon: 'success',
          confirmButtonColor: '#65a300',
        });
      }
    },
    onError: (errs) => {
      isSubmitting.value = false;
      errors.value = errs;
      if (window.Swal) {
        window.Swal.fire({
          title: 'Error',
          text: 'Failed to save network values. Please check the form.',
          icon: 'error',
          confirmButtonColor: '#65a300',
        });
      }
    }
  });
};
</script>