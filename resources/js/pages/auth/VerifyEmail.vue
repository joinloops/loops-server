<template>
    <FullLayout>
        <div
            class="grid min-h-full w-full place-items-center bg-white dark:bg-slate-950 px-6 py-24 sm:py-32 lg:px-8"
        >
            <div class="w-full max-w-md">
                <div class="text-center mb-8">
                    <div
                        class="mx-auto h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center mb-4"
                    >
                        <i class="bx bx-envelope text-blue-600 text-[24px]"></i>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ isEmailChange ? 'Verify Email Change' : 'Verify Your Email' }}
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{
                            isEmailChange
                                ? 'Confirm your new email address to complete the change'
                                : 'Confirm your email address to activate your account'
                        }}
                    </p>
                </div>

                <div
                    v-if="verificationStatus === 'success'"
                    class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-gray-800 shadow-lg rounded-lg p-6 text-center"
                >
                    <div
                        class="mx-auto h-16 w-16 rounded-full bg-green-100 flex items-center justify-center mb-4"
                    >
                        <i class="bx bx-check text-green-600 text-[32px]"></i>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">
                        {{ successMessage }}
                    </h2>

                    <router-link
                        to="/"
                        class="flex justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium cursor-pointer"
                    >
                        Continue to Loops
                    </router-link>
                </div>

                <div
                    v-else-if="verificationStatus === 'error'"
                    class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-gray-800 shadow-lg rounded-lg p-6 text-center"
                >
                    <div
                        class="mx-auto h-16 w-16 rounded-full bg-red-100 flex items-center justify-center mb-4"
                    >
                        <i class="bx bx-x text-red-600 text-[32px]"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                        Verification Failed
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        {{ errorMessage }}
                    </p>
                    <div class="space-y-3">
                        <button
                            @click="resendVerification"
                            :disabled="isResending"
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 transition-colors font-medium cursor-pointer"
                        >
                            {{ isResending ? 'Sending...' : 'Request New Verification Email' }}
                        </button>
                        <button
                            @click="goToLogin"
                            class="w-full px-4 py-2 border border-gray-300 text-gray-700 dark:text-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors font-medium cursor-pointer"
                        >
                            Back to Login
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-gray-800 shadow-lg rounded-lg p-6"
                >
                    <form @submit.prevent="submitVerification">
                        <input type="hidden" v-model="userId" />
                        <input type="hidden" v-model="token" />

                        <div
                            class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4 mb-6"
                        >
                            <div class="flex items-start gap-3">
                                <i class="bx bx-error text-orange-500 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4
                                        class="font-medium text-orange-800 dark:text-orange-200 mb-1"
                                    >
                                        Security Notice
                                    </h4>
                                    <p class="text-sm text-orange-700 dark:text-orange-300">
                                        Only click "Verify Email" if you initiated this
                                        {{ isEmailChange ? 'email change' : 'account creation' }}
                                        yourself. If you didn't request this, please close this
                                        page.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="!canSubmit || isVerifying"
                            class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 cursor-pointer disabled:cursor-not-allowed transition-colors font-medium flex items-center justify-center gap-2"
                        >
                            <i v-if="isVerifying" class="bx bx-loader-alt animate-spin"></i>
                            <span>{{ isVerifying ? 'Verifying...' : 'Verify Email Address' }}</span>
                        </button>

                        <div class="mt-4 text-center">
                            <router-link
                                v-if="!authStore.authenticated"
                                to="/login"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 underline cursor-pointer"
                            >
                                Cancel and return to login
                            </router-link>
                            <router-link
                                v-else
                                to="/"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 underline cursor-pointer"
                            >
                                Cancel and return home
                            </router-link>
                        </div>
                    </form>
                </div>

                <div v-if="verificationStatus === 'pending'" class="mt-6 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        This verification link will expire in 24 hours from when it was sent.
                    </p>
                </div>
            </div>
        </div>
    </FullLayout>
</template>

<script setup>
import { inject, onMounted, ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FullLayout from '@/layouts/FullLayout.vue'
import { useAlertModal } from '@/composables/useAlertModal.js'
const { alertModal, confirmModal } = useAlertModal()
import axios from '~/plugins/axios'
const axiosInstance = axios.getAxiosInstance()

const authStore = inject('authStore')
const route = useRoute()
const router = useRouter()

// Form data
const userId = ref('')
const token = ref('')
const isEmailChange = ref(false)

// State management
const verificationStatus = ref('pending')
const isVerifying = ref(false)
const isResending = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const showCaptcha = ref(false)
const captchaCompleted = ref(false)

const canSubmit = computed(() => {
    const hasRequiredData = userId.value && token.value
    const captchaReady = !showCaptcha.value || captchaCompleted.value
    return hasRequiredData && captchaReady && !isVerifying.value
})

onMounted(() => {
    extractRouteParameters()
    determineVerificationType()
})

const extractRouteParameters = () => {
    userId.value = route.params.userId || ''
    token.value = route.params.token || ''

    if (!userId.value) userId.value = route.query.user_id || ''
    if (!token.value) token.value = route.query.token || ''

    if (!userId.value || !token.value) {
        verificationStatus.value = 'error'
        errorMessage.value = 'Invalid verification link. Missing required parameters.'
    }
}

const determineVerificationType = () => {
    const path = route.path
    isEmailChange.value = path.includes('change') || route.query.type === 'change'
}

const submitVerification = async () => {
    if (!canSubmit.value) return

    isVerifying.value = true
    verificationStatus.value = 'verifying'

    try {
        const response = await axiosInstance.post('/api/v1/account/settings/email/verify', {
            user_id: userId.value,
            token: token.value
        })

        console.log(response)

        if (response.status === 200) {
            verificationStatus.value = 'success'
            successMessage.value =
                response.data.data ||
                (isEmailChange.value
                    ? 'Your email address has been successfully updated!'
                    : 'Your email has been verified and your account is now active!')
        } else {
            throw new Error(data.message || 'Verification failed')
        }
    } catch (error) {
        verificationStatus.value = 'error'
        if (error.response && error.response.data) {
            const errorData = error.response.data

            if (errorData.error && errorData.error.message && errorData.error.message) {
                errorMessage.value = errorData.error.message
            }
        }
    } finally {
        isVerifying.value = false
    }
}

const resendVerification = async () => {
    if (!userId.value) {
        errorMessage.value = 'Cannot resend verification email. User ID is missing.'
        return
    }

    isResending.value = true

    try {
        const response = await axiosInstance.post('/api/v1/account/settings/email/resend', {
            user_id: userId.value
        })

        if (response.status === 200) {
            alertModal(
                'Success!',
                'A new verification email has been sent. Please check your inbox.'
            )
        } else {
            const data = response.data
            throw new Error(data.message || 'Failed to resend verification email')
        }
    } catch (error) {
        console.error('Resend failed:', error)
        if (error.response && error.response.data) {
            const errorData = error.response.data

            if (errorData.error && errorData.error.message && errorData.error.message) {
                errorMessage.value = errorData.error.message
                alertModal(
                    'Oops!',
                    errorData.error.message ||
                        'Failed to resend verification email. Please try again later.'
                )
            }
        }
    } finally {
        isResending.value = false
    }
}

const redirectToApp = () => {
    window.location.href = '/dashboard'
}

const goToLogin = () => {
    router.push('/login')
}

watch(
    () => route.params,
    () => {
        if (verificationStatus.value === 'pending') {
            extractRouteParameters()
            determineVerificationType()
        }
    },
    { deep: true }
)
</script>
