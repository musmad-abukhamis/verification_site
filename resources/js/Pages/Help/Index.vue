<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Alert from '@/Components/Alert.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    settings: Object,
});

const s = computed(() => props.settings ?? {});

// Channels are equal options, so they share one icon treatment rather than
// each carrying its own colour.
const contactMethods = computed(() => [
    { title: 'Email', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', primary: s.value.site_email, secondary: s.value.site_email2, href: (v) => `mailto:${v}` },
    { title: 'Phone', icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', primary: s.value.site_phone, secondary: s.value.site_phone2, href: (v) => `tel:${v}` },
    { title: 'WhatsApp', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', primary: s.value.whatsapp_url, secondary: s.value.whatsapp_url2, isUrl: true, href: (v) => v },
    { title: 'Website', icon: 'M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18', primary: s.value.site_url, secondary: null, isUrl: true, href: (v) => v },
].filter((m) => m.primary || m.secondary));

const offices = computed(() => [
    { title: 'Primary office', address: s.value.office_address },
    { title: 'Secondary office', address: s.value.office_address2 },
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
</script>

<template>
    <Head title="Help & Support" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <PageHeader
                eyebrow="Support"
                title="Help &amp; support"
                :description="`Reach out through any channel below, or send a message and ${s.site_name ? `the ${s.site_name} team` : 'our team'} will get back to you.`"
            />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Contact info + offices -->
                <div class="space-y-6">
                    <section class="card card-pad">
                        <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Contact information</h2>

                        <div v-if="contactMethods.length" class="mt-4 space-y-5">
                            <div v-for="m in contactMethods" :key="m.title" class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="m.icon" />
                                    </svg>
                                </span>

                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ m.title }}</h3>
                                    <div class="mt-0.5 space-y-0.5 text-sm">
                                        <p v-if="m.primary">
                                            <a
                                                :href="m.href(m.primary)"
                                                :target="m.isUrl ? '_blank' : undefined"
                                                :rel="m.isUrl ? 'noopener noreferrer' : undefined"
                                                class="link break-all"
                                            >
                                                {{ m.isUrl ? 'Primary link' : m.primary }}
                                            </a>
                                            <span v-if="!m.isUrl" class="ml-1 text-xs text-ink-400 dark:text-ink-500">(primary)</span>
                                        </p>
                                        <p v-if="m.secondary">
                                            <a
                                                :href="m.href(m.secondary)"
                                                :target="m.isUrl ? '_blank' : undefined"
                                                :rel="m.isUrl ? 'noopener noreferrer' : undefined"
                                                class="link break-all"
                                            >
                                                {{ m.isUrl ? 'Secondary link' : m.secondary }}
                                            </a>
                                            <span v-if="!m.isUrl" class="ml-1 text-xs text-ink-400 dark:text-ink-500">(secondary)</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-else class="mt-4 text-sm text-ink-500 dark:text-ink-400">
                            Contact details have not been configured yet.
                        </p>
                    </section>

                    <section v-if="offices.length" class="card card-pad">
                        <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Office locations</h2>

                        <div class="mt-4 space-y-5">
                            <div v-for="(o, i) in offices" :key="i" class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ o.title }}</h3>
                                    <p class="mt-0.5 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ o.address }}</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Contact form -->
                <section class="card card-pad h-fit">
                    <h2 class="font-display text-base font-semibold text-ink-950 dark:text-white">Send us a message</h2>

                    <Alert v-if="$page.props.flash?.success" tone="success" class="mt-4">
                        {{ $page.props.flash.success }}
                    </Alert>

                    <form @submit.prevent="submit" class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="firstName" class="eyebrow">First name *</label>
                                <input id="firstName" v-model="form.firstName" type="text" class="mt-1.5 block w-full" />
                                <p v-if="form.errors.firstName" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.firstName }}</p>
                            </div>
                            <div>
                                <label for="lastName" class="eyebrow">Last name *</label>
                                <input id="lastName" v-model="form.lastName" type="text" class="mt-1.5 block w-full" />
                                <p v-if="form.errors.lastName" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.lastName }}</p>
                            </div>
                        </div>

                        <div>
                            <label for="email" class="eyebrow">Email address *</label>
                            <input id="email" v-model="form.email" type="email" class="mt-1.5 block w-full" />
                            <p v-if="form.errors.email" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label for="phone" class="eyebrow">Phone number</label>
                            <input id="phone" v-model="form.phone" type="tel" class="mt-1.5 block w-full font-mono" />
                            <p v-if="form.errors.phone" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.phone }}</p>
                        </div>

                        <div>
                            <label for="subject" class="eyebrow">Subject *</label>
                            <input id="subject" v-model="form.subject" type="text" placeholder="What is this regarding?" class="mt-1.5 block w-full" />
                            <p v-if="form.errors.subject" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.subject }}</p>
                        </div>

                        <div>
                            <label for="message" class="eyebrow">Message *</label>
                            <textarea id="message" v-model="form.message" rows="5" placeholder="Tell us how we can help…" class="mt-1.5 block w-full"></textarea>
                            <p v-if="form.errors.message" class="mt-1 text-xs font-medium text-danger-600 dark:text-danger-400">{{ form.errors.message }}</p>
                        </div>

                        <div>
                            <label for="priority" class="eyebrow">Priority level</label>
                            <select id="priority" v-model="form.priority" class="mt-1.5 block w-full">
                                <option value="low">Low — general inquiry</option>
                                <option value="medium">Medium — need assistance</option>
                                <option value="high">High — urgent support needed</option>
                            </select>
                        </div>

                        <button type="submit" :disabled="form.processing" class="btn btn-primary btn-lg w-full">
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ form.processing ? 'Sending…' : 'Send message' }}
                        </button>

                        <p v-if="s.site_email" class="text-center text-xs text-ink-500 dark:text-ink-400">
                            We'll get back to you within 24 hours at
                            <a :href="`mailto:${s.site_email}`" class="link">{{ s.site_email }}</a>.
                        </p>
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
