<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <PageHeader
                eyebrow="Account"
                title="Profile"
                description="Your details, your password, and the switch that closes the account."
            />

            <div class="card card-pad">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </div>

            <div class="card card-pad">
                <UpdatePasswordForm />
            </div>

            <!-- Destructive action, kept visually apart from the rest. -->
            <div class="card card-pad border-danger-200 dark:border-danger-900">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
