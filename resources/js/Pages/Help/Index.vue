<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    settings: Object,
});

const s = computed(() => props.settings ?? {});

const contactMethods = computed(() => [
    { title: 'Email', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', color: 'text-blue-600 bg-blue-100 dark:bg-blue-900/40', primary: s.value.site_email, secondary: s.value.site_email2, href: (v) => `mailto:${v}` },
    { title: 'Phone', icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', color: 'text-green-600 bg-green-100 dark:bg-green-900/40', primary: s.value.site_phone, secondary: s.value.site_phone2, href: (v) => `tel:${v}` },
    { title: 'WhatsApp', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', color: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-900/40', primary: s.value.whatsapp_url, secondary: s.value.whatsapp_url2, isUrl: true, href: (v) => v },
    { title: 'Website', icon: 'M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18', color: 'text-indigo-600 bg-indigo-100 dark:bg-indigo-900/40', primary: s.value.site_url, secondary: null, isUrl: true, href: (v) => v },
].filter((m) => m.primary || m.secondary));

const offices = computed(() => [
    { title: 'Primary Office', address: s.value.office_address },
    { title: 'Secondary Office', address: s.value.office_address2 },
].filter((o) => o.address));

const form = useForm({
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    priority: 'medium',
});

const submit = () => {
    form.post(route('help.submit'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const inputClass = 'w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-500';
</script>

<template>
    <Head title="Help & Support" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Hero -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl shadow p-8 text-white">
                <h1 class="text-2xl font-bold">Help &amp; Support</h1>
                <p class="mt-1 text-white/80 max-w-2xl">
                    Need a hand? Reach out through any of the channels below or send us a message and
                    {{ s.site_name ? `the ${s.site_name} team` : 'our team' }} will get back to you.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Contact info + offices -->
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Contact Information</h2>
                        <div v-if="contactMethods.length" class="space-y-5">
                            <div v-for="m in contactMethods" :key="m.title" class="flex items-start gap-3">
                                <div :class="['shrink-0 w-10 h-10 rounded-lg flex items-center justify-center', m.color]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="m.icon" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-medium text-gray-900 dark:text-white">{{ m.title }}</h3>
                                    <div class="space-y-0.5 text-sm">
                                        <div v-if="m.primary">
                                            <a :href="m.href(m.primary)" :target="m.isUrl ? '_blank' : undefined" :rel="m.isUrl ? 'noopener noreferrer' : undefined"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline break-all">
                                                {{ m.isUrl ? 'Primary Link' : m.primary }}
                                            </a>
                                            <span v-if="!m.isUrl" class="text-xs text-gray-400 ml-1">(Primary)</span>
                                        </div>
                                        <div v-if="m.secondary">
                                            <a :href="m.href(m.secondary)" :target="m.isUrl ? '_blank' : undefined" :rel="m.isUrl ? 'noopener noreferrer' : undefined"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline break-all">
                                                {{ m.isUrl ? 'Secondary Link' : m.secondary }}
                                            </a>
                                            <span v-if="!m.isUrl" class="text-xs text-gray-400 ml-1">(Secondary)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">Contact details have not been configured yet.</p>
                    </div>

                    <div v-if="offices.length" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Office Locations</h2>
                        <div class="space-y-5">
                            <div v-for="(o, i) in offices" :key="i" class="flex items-start gap-3">
                                <div class="shrink-0 w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-900 dark:text-white">{{ o.title }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ o.address }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact form -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6 h-fit">
                    <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        Send us a Message
                    </h2>

                    <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">
                        {{ $page.props.flash.success }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name *</label>
                                <input v-model="form.firstName" type="text" placeholder="Enter your first name" :class="inputClass" />
                                <p v-if="form.errors.firstName" class="mt-1 text-xs text-red-600">{{ form.errors.firstName }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name *</label>
                                <input v-model="form.lastName" type="text" placeholder="Enter your last name" :class="inputClass" />
                                <p v-if="form.errors.lastName" class="mt-1 text-xs text-red-600">{{ form.errors.lastName }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                            <input v-model="form.email" type="email" placeholder="Enter your email address" :class="inputClass" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                            <input v-model="form.phone" type="tel" placeholder="Enter your phone number" :class="inputClass" />
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject *</label>
                            <input v-model="form.subject" type="text" placeholder="What is this regarding?" :class="inputClass" />
                            <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message *</label>
                            <textarea v-model="form.message" rows="5" placeholder="Tell us how we can help you..." :class="inputClass"></textarea>
                            <p v-if="form.errors.message" class="mt-1 text-xs text-red-600">{{ form.errors.message }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority Level</label>
                            <select v-model="form.priority" :class="inputClass">
                                <option value="low">Low - General inquiry</option>
                                <option value="medium">Medium - Need assistance</option>
                                <option value="high">High - Urgent support needed</option>
                            </select>
                        </div>
                        <button type="submit" :disabled="form.processing"
                            class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50 transition-colors">
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ form.processing ? 'Sending Message...' : 'Send Message' }}
                        </button>
                        <p v-if="s.site_email" class="text-xs text-gray-500 dark:text-gray-400 text-center">
                            We'll get back to you within 24 hours at
                            <a :href="`mailto:${s.site_email}`" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ s.site_email }}</a>.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
