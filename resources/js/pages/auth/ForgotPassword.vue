<template>
    <FullLayout>
        <div
            class="w-full flex items-center justify-center px-4"
            style="min-height: calc(100vh - 70px)"
        >
            <div class="w-full max-w-md">
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-lg p-8">
                    <div v-if="emailSent" class="text-center">
                        <div
                            class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-6"
                        >
                            <svg
                                class="w-8 h-8 text-green-600 dark:text-green-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                ></path>
                            </svg>
                        </div>
                        <div class="text-[24px] mb-4 font-bold dark:text-slate-300">
                            Check your email
                        </div>
                        <p
                            class="text-[15px] text-gray-600 dark:text-slate-400 mb-6 leading-relaxed"
                        >
                            We've sent a password reset link to
                            <span class="font-medium text-gray-900 dark:text-slate-300">{{
                                email
                            }}</span>
                        </p>
                        <p class="text-[13px] text-gray-500 dark:text-slate-500 mb-8">
                            Didn't receive the email? Check your spam folder or try again.
                        </p>
                        <div class="space-y-3">
                            <button
                                @click="resendEmail"
                                :disabled="resendCooldown > 0"
                                class="w-full text-[15px] font-medium text-[#F02C56] border border-[#F02C56] py-2.5 rounded-lg hover:bg-[#F02C56] hover:text-white transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{
                                    resendCooldown > 0
                                        ? `Resend in ${resendCooldown}s`
                                        : 'Resend email'
                                }}
                            </button>
                            <button
                                @click="goBackToLogin"
                                class="w-full text-[15px] font-medium text-gray-600 dark:text-slate-400 py-2.5 rounded-lg hover:text-gray-800 dark:hover:text-slate-200 transition-colors duration-200"
                            >
                                Back to login
                            </button>
                        </div>
                    </div>

                    <div v-else>
                        <div class="text-center mb-8">
                            <div class="text-[28px] mb-3 font-bold dark:text-slate-300">
                                Reset your password
                            </div>
                            <p
                                class="text-[15px] text-gray-600 dark:text-slate-400 leading-relaxed"
                            >
                                Enter your email address and we'll send you a link to reset your
                                password.
                            </p>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <div class="pb-1.5 text-[15px] dark:text-slate-500">
                                    Email address
                                </div>
                                <TextInput
                                    placeholder="Enter your email address"
                                    v-model="email"
                                    input-type="email"
                                    :auto-focus="true"
                                    :error="errors?.email?.[0]"
                                />
                            </div>

                            <div class="space-y-3">
                                <button
                                    :disabled="!email || loading"
                                    :class="
                                        !email || loading
                                            ? 'bg-gray-200 dark:bg-slate-800 cursor-not-allowed'
                                            : 'bg-[#F02C56] hover:bg-[#E0254A]'
                                    "
                                    @click="sendResetEmail"
                                    class="w-full text-[17px] font-semibold text-white py-3 rounded-lg transition-colors duration-200 flex items-center justify-center"
                                >
                                    <svg
                                        v-if="loading"
                                        class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        ></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>
                                    {{ loading ? 'Sending...' : 'Send reset link' }}
                                </button>

                                <button
                                    @click="goBackToLogin"
                                    class="w-full text-[15px] font-medium text-gray-600 dark:text-slate-400 py-2.5 rounded-lg hover:text-gray-800 dark:hover:text-slate-200 transition-colors duration-200"
                                >
                                    Back to login
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </FullLayout>
</template>

<script setup>
import { onMounted, ref, inject } from 'vue'
import { storeToRefs } from 'pinia'
import FullLayout from '@/layouts/FullLayout.vue'

const authStore = inject('authStore')
const appStore = inject('appStore')

const email = ref('')
const errors = ref(null)
const loading = ref(false)
const emailSent = ref(false)
const resendCooldown = ref(0)

const sendResetEmail = async () => {
    errors.value = null
    loading.value = true

    try {
        await authStore.sendPasswordReset({ email: email.value })
        emailSent.value = true
        startResendCooldown()
    } catch (error) {
        errors.value = error.response?.data?.errors || {
            email: ['Something went wrong. Please try again.']
        }
    } finally {
        loading.value = false
    }
}

const resendEmail = async () => {
    if (resendCooldown.value > 0) return

    loading.value = true
    try {
        await authStore.sendPasswordReset({ email: email.value })
        startResendCooldown()
    } catch (error) {
        console.error('Failed to resend email:', error)
    } finally {
        loading.value = false
    }
}

const startResendCooldown = () => {
    resendCooldown.value = 60
    const interval = setInterval(() => {
        resendCooldown.value--
        if (resendCooldown.value <= 0) {
            clearInterval(interval)
        }
    }, 1000)
}

const goBackToLogin = () => {
    appStore.isLoginOpen = true
    appStore.isForgotPasswordOpen = false
}

onMounted(() => {
    // Reset any existing state
    emailSent.value = false
    errors.value = null
    resendCooldown.value = 0
})
</script>
