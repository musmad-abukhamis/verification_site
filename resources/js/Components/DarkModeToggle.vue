<script setup>
import { ref, onMounted, watch } from 'vue';

const isDark = ref(false);

const toggleDarkMode = () => {
    isDark.value = !isDark.value;
    updateDarkMode();
};

const updateDarkMode = () => {
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('darkMode', 'true');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('darkMode', 'false');
    }
};

onMounted(() => {
    // Check localStorage first
    const stored = localStorage.getItem('darkMode');
    
    if (stored !== null) {
        isDark.value = stored === 'true';
    } else {
        // Check system preference
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    
    updateDarkMode();
});

// Listen for system preference changes
if (typeof window !== 'undefined') {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (localStorage.getItem('darkMode') === null) {
            isDark.value = e.matches;
            updateDarkMode();
        }
    });
}
</script>

<template>
    <button
        @click="toggleDarkMode"
        class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
    >
        <!-- Sun Icon (Light Mode) -->
        <svg
            v-if="isDark"
            class="w-5 h-5 text-yellow-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
            />
        </svg>
        <!-- Moon Icon (Dark Mode) -->
        <svg
            v-else
            class="w-5 h-5 text-gray-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
            />
        </svg>
    </button>
</template>
