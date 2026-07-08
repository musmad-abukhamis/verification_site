<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();

const latest = computed(() => page.props.notifications?.latest_global ?? null);
const visible = ref(false);

// Show the newest global announcement once per browser session (per id), so it
// doesn't re-pop on every SPA navigation. "Don't show again" dismisses it
// permanently server-side; "Got it" just marks it read (may reappear next
// session until dismissed) — matching the source behaviour.
const seenKey = (id) => `global-notif-seen:${id}`;

const maybeShow = () => {
    const n = latest.value;
    if (!n) return;
    if (sessionStorage.getItem(seenKey(n.id))) return;
    visible.value = true;
};

onMounted(maybeShow);
watch(latest, maybeShow);

const markSeen = () => {
    if (latest.value) sessionStorage.setItem(seenKey(latest.value.id), '1');
};

const close = () => {
    markSeen();
    visible.value = false;
};

const gotIt = () => {
    if (!latest.value) return;
    router.post(route('notifications.read', latest.value.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications'],
    });
    close();
};

const dontShowAgain = () => {
    if (!latest.value) return;
    router.post(route('notifications.dismiss', latest.value.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications'],
    });
    visible.value = false;
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('en-NG', { month: 'short', day: 'numeric', year: 'numeric' }) : '');

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
    <Teleport to="body">
        <div v-if="visible && latest" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click="close">
            <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden" @click.stop>
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500"></div>

                <div class="p-6 pb-4 flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <svg class="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ latest.title }}</h2>
                            <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400">Global Announcement</span>
                        </div>
                    </div>
                    <button @click="close" class="h-8 w-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 pb-6 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{{ latest.message }}</p>
                    <div class="flex items-center gap-4 text-xs text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <span>{{ formatDate(latest.createdAt) }}</span>
                        <span v-if="latest.expiresAt">{{ timeRemaining(latest.expiresAt) }}</span>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button @click="gotIt" class="flex-1 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">Got it</button>
                        <button @click="dontShowAgain" class="flex-1 px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Don't show again</button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
