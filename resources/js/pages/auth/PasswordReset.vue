<template>
    <FullLayout>
        <div
            class="w-full flex items-center justify-center px-4"
            style="min-height: calc(100vh - 70px)"
        >
            <div class="w-full max-w-md">
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-lg overflow-hidden">
                    <div
                        class="px-6 py-4 bg-gray-50 dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700"
                    >
                        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Reset Password
                        </h1>
                    </div>

                    <div class="p-6">
                        <form @submit.prevent="handleSubmit">
                            <div class="mb-6">
                                <label
                                    for="email"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    Email Address
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    :class="[
                                        'w-full px-3 py-2 border rounded-md shadow-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors',
                                        errors.email
                                            ? 'border-red-500 dark:border-red-400'
                                            : 'border-gray-300 dark:border-slate-600'
                                    ]"
                                    required
                                    autocomplete="email"
                                />
                                <p
                                    v-if="errors.email"
                                    class="text-red-500 dark:text-red-400 text-sm mt-1"
                                    role="alert"
                                >
                                    <strong>{{ errors.email.join(',') }}</strong>
                                </p>
                            </div>

                            <div class="mb-6">
                                <label
                                    for="password"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    Password
                                </label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    :class="[
                                        'w-full px-3 py-2 border rounded-md shadow-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors',
                                        errors.password
                                            ? 'border-red-500 dark:border-red-400'
                                            : 'border-gray-300 dark:border-slate-600'
                                    ]"
                                    required
                                    autofocus
                                    autocomplete="new-password"
                                />
                                <p
                                    v-if="errors.password"
                                    class="text-red-500 dark:text-red-400 text-sm mt-1"
                                    role="alert"
                                >
                                    <strong>{{ errors.password }}</strong>
                                </p>
                            </div>

                            <div class="mb-6">
                                <label
                                    for="password-confirm"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    Confirm Password
                                </label>
                                <input
                                    id="password-confirm"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm bg-white dark:bg-slate-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    required
                                    autocomplete="new-password"
                                />
                            </div>

                            <template v-if="appCaptcha.enabled">
                                <template v-if="appCaptcha.provider === 'turnstile'">
                                    <CloudflareTurnstile
                                        ref="turnstileRef"
                                        :sitekey="appCaptcha.siteKey"
                                        theme="auto"
                                        @success="onCaptchaSuccess"
                                        @error="onCaptchaError"
                                        @expired="onCaptchaExpired"
                                    />
                                </template>
                                <template v-else-if="appCaptcha.provider === 'hcaptcha'">
                                    <HCaptcha
                                        ref="hcaptchaRef"
                                        :sitekey="appCaptcha.siteKey"
                                        theme="light"
                                        @success="onCaptchaSuccess"
                                        @error="onCaptchaError"
                                        @expired="onCaptchaExpired"
                                    />
                                </template>
                                <p
                                    v-if="errors.captcha_token"
                                    class="text-red-500 dark:text-red-400 text-sm mt-1"
                                    role="alert"
                                >
                                    <strong>{{ errors.captcha_token.join(',') }}</strong>
                                </p>
                            </template>

                            <div class="flex mt-3 justify-end">
                                <button
                                    type="submit"
                                    :disabled="loading"
                                    :class="[
                                        'font-medium py-2 px-4 rounded-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900',
                                        loading
                                            ? 'bg-gray-400 dark:bg-gray-600 cursor-not-allowed'
                                            : 'bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white'
                                    ]"
                                >
                                    {{ loading ? 'Resetting...' : 'Reset Password' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mt-3 text-sm text-center">
                    <router-link
                        to="/"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 underline transition-colors"
                    >
                        Back to Loops
                    </router-link>
                </div>
            </div>
        </div>
    </FullLayout>
</template>

<script setup>
import { onMounted, ref, inject } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import FullLayout from '@/layouts/FullLayout.vue'
import CloudflareTurnstile from '@/components/Captcha/CloudflareTurnstile.vue'
import HCaptcha from '@/components/Captcha/HCaptcha.vue'

const route = useRoute()
const router = useRouter()
const authStore = inject('authStore')
const appStore = inject('appStore')
const appCaptcha = inject('appCaptcha')
const captchaType = ref(appCaptcha.provider)
const captchaToken = ref('')
const turnstileRef = ref(null)
const hcaptchaRef = ref(null)

const form = ref({
    email: '',
    password: '',
    password_confirmation: '',
    token: ''
})

const onCaptchaSuccess = (token) => {
    captchaToken.value = token
    form.value.captcha_token = token
    form.value.captcha_type = captchaType.value
}

const onCaptchaError = (error) => {
    console.error('Captcha error:', error)
    captchaToken.value = ''
    form.value.captcha_token = ''
}

const onCaptchaExpired = () => {
    captchaToken.value = ''
    form.value.captcha_token = ''
}

// Form state
const loading = ref(false)
const errors = ref({})

onMounted(() => {
    form.value.token = route.params.token || route.query.token || ''

    if (route.query.email) {
        form.value.email = route.query.email
    }
})

const handleSubmit = async () => {
    try {
        loading.value = true
        errors.value = {}

        if (form.value.password !== form.value.password_confirmation) {
            errors.value.password = 'Passwords do not match'
            return
        }

        const response = await authStore.storeMyResetPassword(form.value)
        if (response) {
            router.push('/login')
        }
    } catch (error) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
        } else {
            console.log(error)
        }
    } finally {
        loading.value = false
    }
}
</script>
