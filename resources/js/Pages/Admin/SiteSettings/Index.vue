<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    site_name: props.settings?.site_name || '',
    site_url: props.settings?.site_url || '',
    site_email: props.settings?.site_email || '',
    site_email2: props.settings?.site_email2 || '',
    site_phone: props.settings?.site_phone || '',
    site_phone2: props.settings?.site_phone2 || '',
    whatsapp_url: props.settings?.whatsapp_url || '',
    whatsapp_url2: props.settings?.whatsapp_url2 || '',
    office_address: props.settings?.office_address || '',
    office_address2: props.settings?.office_address2 || '',
});

const submit = () => {
    form.put(route('admin.site-settings.update'), { preserveScroll: true });
};

const inputClass = 'w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-500';
</script>

<template>
    <Head title="Site Settings" />
    <AdminLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Site Settings</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Configure your site information and contact details. These power the Help &amp; Support page.</p>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded-lg text-sm">{{ $page.props.flash.success }}</div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Site Information -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Site Information</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Basic information about your website.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Site Name</label>
                            <input v-model="form.site_name" type="text" placeholder="Enter your site name" :class="inputClass" />
                            <p v-if="form.errors.site_name" class="mt-1 text-xs text-red-600">{{ form.errors.site_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Site URL</label>
                            <input v-model="form.site_url" type="url" placeholder="https://example.com" :class="inputClass" />
                            <p v-if="form.errors.site_url" class="mt-1 text-xs text-red-600">{{ form.errors.site_url }}</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Contact Information</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Primary and secondary contact details.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Primary Contact</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                                <input v-model="form.site_email" type="email" placeholder="contact@example.com" :class="inputClass" />
                                <p v-if="form.errors.site_email" class="mt-1 text-xs text-red-600">{{ form.errors.site_email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                                <input v-model="form.site_phone" type="tel" placeholder="+234 800 000 0000" :class="inputClass" />
                                <p v-if="form.errors.site_phone" class="mt-1 text-xs text-red-600">{{ form.errors.site_phone }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp URL</label>
                                <input v-model="form.whatsapp_url" type="url" placeholder="https://wa.me/234800000000" :class="inputClass" />
                                <p v-if="form.errors.whatsapp_url" class="mt-1 text-xs text-red-600">{{ form.errors.whatsapp_url }}</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Secondary Contact</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                                <input v-model="form.site_email2" type="email" placeholder="support@example.com" :class="inputClass" />
                                <p v-if="form.errors.site_email2" class="mt-1 text-xs text-red-600">{{ form.errors.site_email2 }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                                <input v-model="form.site_phone2" type="tel" placeholder="+234 900 000 0000" :class="inputClass" />
                                <p v-if="form.errors.site_phone2" class="mt-1 text-xs text-red-600">{{ form.errors.site_phone2 }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp URL</label>
                                <input v-model="form.whatsapp_url2" type="url" placeholder="https://wa.me/234900000000" :class="inputClass" />
                                <p v-if="form.errors.whatsapp_url2" class="mt-1 text-xs text-red-600">{{ form.errors.whatsapp_url2 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Office Addresses -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Office Addresses</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Physical office locations.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Primary Office Address</label>
                            <textarea v-model="form.office_address" rows="4" placeholder="123 Main Street, Suite 100, City, State" :class="inputClass"></textarea>
                            <p v-if="form.errors.office_address" class="mt-1 text-xs text-red-600">{{ form.errors.office_address }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Secondary Office Address</label>
                            <textarea v-model="form.office_address2" rows="4" placeholder="456 Business Ave, Floor 5, City, State" :class="inputClass"></textarea>
                            <p v-if="form.errors.office_address2" class="mt-1 text-xs text-red-600">{{ form.errors.office_address2 }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg disabled:opacity-50">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ form.processing ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
