<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    status: { type: String },
    sent: { type: Boolean, default: false },
    login: { type: String },
});

// Step 2 unlocks once a code has been sent, but the user can go back if they
// mistyped the phone/username.
const codeSent = ref(props.sent);

watch(
    () => props.sent,
    (value) => {
        if (value) codeSent.value = true;
    },
);

const requestForm = useForm({
    login: props.login ?? '',
});

const resetForm = useForm({
    login: props.login ?? '',
    code: '',
    password: '',
    password_confirmation: '',
});

const sendCode = () => {
    requestForm.post(route('password.otp.send'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm.login = requestForm.login;
        },
    });
};

const submitReset = () => {
    resetForm.post(route('password.otp.reset'), {
        onFinish: () => resetForm.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password by SMS" />

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            Enter your username, phone number or email and we will text a
            6-digit code to the phone number registered on your account.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600 dark:text-green-400"
        >
            {{ status }}
        </div>

        <form @submit.prevent="sendCode">
            <div>
                <InputLabel for="login" value="Username, phone or email" />

                <TextInput
                    id="login"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="requestForm.login"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="requestForm.errors.login" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': requestForm.processing }"
                    :disabled="requestForm.processing"
                >
                    {{ codeSent ? 'Resend code' : 'Send code' }}
                </PrimaryButton>
            </div>
        </form>

        <form v-if="codeSent" @submit.prevent="submitReset" class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-700">
            <div>
                <InputLabel for="code" value="6-digit code" />

                <TextInput
                    id="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    class="mt-1 block w-full tracking-widest"
                    v-model="resetForm.code"
                    required
                />

                <InputError class="mt-2" :message="resetForm.errors.code" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="New password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="resetForm.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="resetForm.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirm new password"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="resetForm.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="resetForm.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': resetForm.processing }"
                    :disabled="resetForm.processing"
                >
                    Reset password
                </PrimaryButton>
            </div>
        </form>

        <div class="mt-6 text-sm text-gray-600 dark:text-gray-400">
            Still have access to your email?
            <Link
                :href="route('password.request')"
                class="underline hover:text-gray-900 dark:hover:text-gray-100"
            >
                Reset by email instead
            </Link>
        </div>
    </GuestLayout>
</template>
