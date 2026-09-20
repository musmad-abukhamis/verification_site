<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Who a history row belongs to, on the admin history screens.
 *
 * It links to the user rather than just naming them: an admin reading history
 * is usually on their way to that person's account — to check a balance, or to
 * put money back.
 */
defineProps({
    /** { id, name, username } — null for a row whose user has been deleted. */
    user: { type: Object, default: null },
});
</script>

<template>
    <Link
        v-if="user"
        :href="route('admin.users.show', user.id)"
        class="block min-w-0 text-ink-900 hover:text-brand-700 dark:text-ink-100 dark:hover:text-brand-300"
    >
        <span class="block truncate font-medium">{{ user.name }}</span>
        <span v-if="user.username" class="block truncate font-mono text-2xs text-ink-500 dark:text-ink-400">
            @{{ user.username }}
        </span>
    </Link>

    <span v-else class="text-ink-400 dark:text-ink-500">Unknown</span>
</template>
