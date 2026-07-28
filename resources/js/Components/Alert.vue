<script setup>
/**
 * Flash messages and inline form-level errors. Every service page was
 * hand-rolling a tinted div; this is the one shape they all share.
 *
 * Tone maps to the status scale, never to brand colour — a green alert means
 * "this succeeded", not "this is our brand".
 */
defineProps({
    tone: {
        type: String,
        default: 'info',
        validator: (v) => ['success', 'danger', 'warning', 'info'].includes(v),
    },
});

const TONES = {
    success: 'border-success-200 bg-success-50 text-success-800 dark:border-success-900 dark:bg-success-950 dark:text-success-300',
    danger: 'border-danger-200 bg-danger-50 text-danger-800 dark:border-danger-900 dark:bg-danger-950 dark:text-danger-300',
    warning: 'border-warning-200 bg-warning-50 text-warning-800 dark:border-warning-900 dark:bg-warning-950 dark:text-warning-300',
    info: 'border-info-200 bg-info-50 text-info-800 dark:border-info-900 dark:bg-info-950 dark:text-info-300',
};
</script>

<template>
    <p class="rounded-lg border px-4 py-3 text-sm font-medium" :class="TONES[tone]" role="status">
        <slot />
    </p>
</template>
