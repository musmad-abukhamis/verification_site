<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const open = ref(false);

const data = computed(() => page.props.notifications ?? { items: [], unread: 0 });
const items = computed(() => data.value.items ?? []);
const unread = computed(() => data.value.unread ?? 0);

const markRead = (id) => {
    router.post(route('notifications.read', id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications'],
    });
};

const dismiss = (id) => {
    router.post(route('notifications.dismiss', id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications'],
    });
};

const formatDate = (iso) => {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' });
};

const timeRemaining = (iso) => {
    if (!iso) return '';
    const diff = new Date(iso).getTime() - Date.now();
    if (diff <= 0) return 'Expired';
    const days = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    if (days > 0) return `${days}d ${hours}h remaining`;
    if (hours > 0) return `${hours}h remaining`;
    return `${Math.floor((diff % 3600000) / 60000)}m remaining`;
};
</script>

<template>
    <div>
        <!-- Bell trigger -->
        <button
            type="button"
            @click="open = true"
            class="relative inline-flex items-center justify-center h-9 w-9 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 focus:outline-none"
            aria-label="Notifications"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span v-if="unread > 0" class="absolute -top-0.5 -right-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                {{ unread > 99 ? '99+' : unread }}
            </span>
        </button>

        <!-- Drawer -->
        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-50" @click="open = false">
                <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
                <div
                    class="absolute right-0 top-0 h-full w-full max-w-md bg-white dark:bg-gray-800 shadow-xl flex flex-col"
                    @click.stop
                >
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h2>
                        <button @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Close">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div
                            v-for="n in items"
                            :key="n.id"
                            @click="!n.isRead && markRead(n.id)"
                            :class="['rounded-lg border p-4 transition-shadow hover:shadow-md',
                                !n.isRead ? 'border-l-4 border-l-indigo-500 border-gray-200 dark:border-gray-700 cursor-pointer' : 'border-gray-200 dark:border-gray-700']"
                        >
                            <div class="flex items-start gap-3">
                                <svg class="h-5 w-5 shrink-0 mt-0.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="font-medium text-gray-900 dark:text-white">{{ n.title }}</h3>
                                        <div class="flex gap-1 shrink-0">
                                            <span v-if="!n.isRead" class="px-1.5 py-0.5 text-[10px] rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200">New</span>
                                            <span v-if="n.is_global" class="px-1.5 py-0.5 text-[10px] rounded border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400">Global</span>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ n.message }}</p>
                                    <div class="flex items-center justify-between mt-3">
                                        <div class="text-xs text-gray-400">
                                            {{ formatDate(n.createdAt) }}
                                            <span v-if="n.expiresAt" class="block mt-0.5">{{ timeRemaining(n.expiresAt) }}</span>
                                        </div>
                                        <button @click.stop="dismiss(n.id)" class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Dismiss</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="items.length === 0" class="flex flex-col items-center justify-center h-40 text-gray-400">
                            <svg class="h-10 w-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <p class="text-sm">No notifications at this time</p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
