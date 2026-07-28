<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <!-- Errors state what happened, and are announced rather than only shown. -->
    <p
        v-show="message"
        role="alert"
        class="mt-1.5 flex items-start gap-1.5 text-sm font-medium text-danger-600 dark:text-danger-400"
    >
        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
                fill-rule="evenodd"
                d="M18 10A8 8 0 1 1 2 10a8 8 0 0 1 16 0Zm-8-4a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"
                clip-rule="evenodd"
            />
        </svg>
        <span>{{ message }}</span>
    </p>
</template>
