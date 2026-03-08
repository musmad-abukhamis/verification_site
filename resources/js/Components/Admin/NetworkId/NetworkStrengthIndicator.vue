<template>
  <div class="flex items-center gap-2">
    <svg
      xmlns="http://www.w3.org/2000/svg"
      class="h-5 w-5"
      :class="signalColor"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
    >
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
    </svg>
    <span class="text-sm font-medium">{{ strength }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  strength: {
    type: String,
    required: true
  }
});

const signalColor = computed(() => {
  const colors = {
    'MTN': 'text-yellow-500',
    'AIRTEL': 'text-red-500',
    'GLO': 'text-green-500',
    '9MOBILE': 'text-blue-500'
  };
  return colors[props.strength] || 'text-gray-500';
});
</script>