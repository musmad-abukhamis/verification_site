<template>
  <div class="container mx-auto px-4 py-10">
    <!-- Page Header -->
    <div class="mb-8 text-center">
      <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Vendor Network Configuration</h2>
      <p class="mt-2 text-gray-600 dark:text-gray-400">Select a network to configure vendor network IDs</p>
    </div>

    <!-- Network Selection Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <Link
        v-for="network in networkNames"
        :key="network"
        :href="route('admin.networkid.edit', network)"
        class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-lg transition-shadow border-2 border-transparent hover:border-lime-500"
      >
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ network }}</h3>
          <NetworkStrengthIndicator :strength="network" />
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Configure vendor network IDs for {{ network }}
        </p>
        <div class="mt-4 flex items-center text-lime-600 font-medium">
          <span>Edit Configuration</span>
          <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import NetworkStrengthIndicator from '@/Components/Admin/NetworkId/NetworkStrengthIndicator.vue';

const networkNames = ['MTN', 'AIRTEL', 'GLO', '9MOBILE'];
</script>