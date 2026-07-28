<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2.5 text-start text-sm font-medium leading-5 text-ink-700 transition hover:bg-brand-50 hover:text-brand-900 dark:text-ink-300 dark:hover:bg-ink-800 dark:hover:text-white"
    >
        <slot />
    </Link>
</template>
