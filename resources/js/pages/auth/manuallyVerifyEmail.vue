<template>
    <FullLayout>
        <div
            class="grid min-h-full w-full place-items-center bg-white dark:bg-slate-950 px-6 py-24 sm:py-32 lg:px-8"
        >
            <div class="w-full max-w-md">
                <!-- Loading state while checking for pending verification -->
                <div v-if="isCheckingStatus" class="flex flex-col items-center justify-center py-12">
                    <i class="bx bx-loader-alt animate-spin text-4xl text-blue-600 dark:text-blue-400 mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400">{{ t('common.loading') }}...</p>
                </div>

                <template v-else>
                <div class="text-center mb-8">
                    <div
                        class="mx-auto h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mb-4"
                    >
                        <i class="bx bx-envelope text-blue-600 dark:text-blue-400 text-[24px]"></i>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ getTitle() }}
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ getSubtitle() }}
                    </p>
                </div>

                <div
                    v-if="verificationStatus === 'success'"
                    class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-gray-800 shadow-lg rounded-lg p-6 text-center"
                >
                    <div
                        class="mx-auto h-16 w-16 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mb-4"
                    >
                        <i class="bx bx-check text-green-600 dark:text-green-400 text-[32px]"></i>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                        {{ t('common.emailVerifiedSuccessfully') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        {{ t('common.yourEmailHasBeenVerifiedYouCanNowSignIn') }}
                    </p>

                    <router-link
                        to="/login"
                        class="flex justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium cursor-pointer"
                    >
                        {{ t('common.continueToLogin') }}
                    </router-link>
                </div>

                <div
                    v-else-if="verificationStatus === 'error'"
                    class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-gray-800 shadow-lg rounded-lg p-6 text-center"
                >
                    <div
                        class="mx-auto h-16 w-16 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center mb-4"
                    >
                        <i class="bx bx-x text-red-600 dark:text-red-400 text-[32px]"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                        {{ t('common.verificationFailed') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        {{ errorMessage }}
                    </p>
                    <div class="space-y-3">
                        <button
                            v-if="currentStep === 2"
                            @click="resendVerification"
                            :disabled="isResending || resendCooldown > 0"
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 transition-colors font-medium cursor-pointer"
                        >
                            {{
                                isResending
                                    ? t('settings.sendingDotDotDot')
                                    : resendCooldown > 0
                                      ? `${t('common.resendIn')} ${resendCooldown}s`
                                      : t('common.resendVerificationEmail')
                            }}
                        </button>
                        <button
                            @click="goToLogin"
                            class="w-full px-4 py-2 border border-gray-300 text-gray-700 dark:text-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors font-medium cursor-pointer"
                        >
                            {{ t('common.backToLogin') }}
                        </button>
                    </div>
                </div>

                <div
                    v-else-if="currentStep === 1"
                    class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-gray-800 shadow-lg rounded-lg p-6"
                >
                    <Transition
                        enter-active-class="duration-300 ease-out"
                        enter-from-class="opacity-0 transform -translate-y-2"
                        enter-to-class="opacity-100 transform translate-y-0"
                        leave-active-class="duration-200 ease-in"
                        leave-from-class="opacity-100 transform translate-y-0"
                        leave-to-class="opacity-0 transform -translate-y-2"
                    >
                        <div
                            v-if="error"
                            class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4"
                        >
                            <p class="text-sm text-red-700 dark:text-red-400">
                                {{ error }}
                            </p>
                        </div>
                    </Transition>

                    <form @submit.prevent="handleInitiateVerification">
                        <div class="space-y-4 mb-6">
                            <div
                                class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4 mb-6"
                            >
                                <div class="flex items-start gap-3">
                                    <i class="bx bx-error text-orange-500 text-[20px] mt-0.5"></i>
                                    <div>
                                        <h4
                                            class="font-medium text-orange-800 dark:text-orange-200 mb-1"
                                        >
                                            {{ t('common.securityNotice') }}
                                        </h4>
                                        <p class="text-sm text-orange-700 dark:text-orange-300">
                                            {{ t('common.onlyVerifyIfYouRequestedThis') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{ t('common.email') }}
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    required
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                    :placeholder="t('common.enterYourEmail')"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{ t('common.password') }}
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        class="w-full px-4 py-3 pr-12 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        :placeholder="t('common.enterYourPassword')"
                                    />
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                    >
                                        <i
                                            :class="showPassword ? 'bx bx-show' : 'bx bx-hide'"
                                            class="text-xl"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-start mb-6">
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
                                    theme="auto"
                                    @success="onCaptchaSuccess"
                                    @error="onCaptchaError"
                                    @expired="onCaptchaExpired"
                                />
                            </template>
                        </div>

                        <button
                            type="submit"
                            :disabled="!canSubmitStep1 || isVerifying"
                            class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 cursor-pointer disabled:cursor-not-allowed transition-colors font-medium flex items-center justify-center gap-2"
                        >
                            <i v-if="isVerifying" class="bx bx-loader-alt animate-spin"></i>
                            <span>{{
                                isVerifying
                                    ? t('settings.sendingDotDotDot')
                                    : t('common.sendVerificationCode')
                            }}</span>
                        </button>

                        <div class="mt-4 text-center">
                            <router-link
                                to="/login"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 underline cursor-pointer"
                            >
                                {{ t('common.cancelAndReturnToLogin') }}
                            </router-link>
                        </div>
                    </form>
                </div>

                <div
                    v-else-if="currentStep === 2"
                    class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-gray-800 shadow-lg rounded-lg p-6"
                >
                    <Transition
                        enter-active-class="duration-300 ease-out"
                        enter-from-class="opacity-0 transform -translate-y-2"
                        enter-to-class="opacity-100 transform translate-y-0"
                        leave-active-class="duration-200 ease-in"
                        leave-from-class="opacity-100 transform translate-y-0"
                        leave-to-class="opacity-0 transform -translate-y-2"
                    >
                        <div
                            v-if="error"
                            class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4"
                        >
                            <p class="text-sm text-red-700 dark:text-red-400">
                                {{ error }}
                            </p>
                        </div>
                    </Transition>

                    <Transition
                        enter-active-class="duration-300 ease-out"
                        enter-from-class="opacity-0 transform -translate-y-2"
                        enter-to-class="opacity-100 transform translate-y-0"
                        leave-active-class="duration-200 ease-in"
                        leave-from-class="opacity-100 transform translate-y-0"
                        leave-to-class="opacity-0 transform -translate-y-2"
                    >
                        <div
                            v-if="successMessage"
                            class="mb-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4"
                        >
                            <p class="text-sm text-green-700 dark:text-green-400">
                                {{ successMessage }}
                            </p>
                        </div>
                    </Transition>

                    <form @submit.prevent="handleSubmitVerification">
                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                            >
                                {{ t('common.verificationCode') }}
                            </label>
                            <input
                                v-model="form.verificationCode"
                                type="text"
                                required
                                maxlength="6"
                                pattern="[0-9]{6}"
                                class="w-full px-4 py-3 text-center text-2xl tracking-widest rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                placeholder="000000"
                            />
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ t('common.enterTheSixDigitCodeSentTo') }}
                                <span class="font-medium">{{ form.email }}</span>
                            </p>
                        </div>

                        <div class="flex justify-between items-center mb-6">
                            <button
                                type="button"
                                @click="resendVerification"
                                :disabled="resendCooldown > 0 || isResending"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 disabled:text-gray-400 dark:disabled:text-gray-600 transition-colors cursor-pointer"
                            >
                                {{
                                    resendCooldown > 0
                                        ? `${t('common.resendIn')} ${resendCooldown}s`
                                        : t('common.resendCode')
                                }}
                            </button>
                        </div>

                        <button
                            type="submit"
                            :disabled="!canSubmitStep2 || isVerifying"
                            class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 cursor-pointer disabled:cursor-not-allowed transition-colors font-medium flex items-center justify-center gap-2"
                        >
                            <i v-if="isVerifying" class="bx bx-loader-alt animate-spin"></i>
                            <span>{{
                                isVerifying ? t('common.verifying') : t('common.verifyEmail')
                            }}</span>
                        </button>

                        <div class="mt-4 text-center">
                            <button
                                type="button"
                                @click="goBackToStep1"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 underline cursor-pointer"
                            >
                                {{ t('common.useADifferentEmail') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="currentStep === 2" class="mt-6 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ t('common.thisVerificationLinkWillExpireIn24Hours') }}
                    </p>
                </div>
                </template>
            </div>
        </div>
    </FullLayout>
</template>

<script setup>
import { inject, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import FullLayout from '@/layouts/FullLayout.vue'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useI18n } from 'vue-i18n'
import axios from '~/plugins/axios'

const appCaptcha = inject('appCaptcha')
const { alertModal } = useAlertModal()
const { t } = useI18n()
const axiosInstance = axios.getAxiosInstance()
const router = useRouter()
const captchaType = ref(appCaptcha.provider)
const captchaToken = ref('')
const turnstileRef = ref(null)
const hcaptchaRef = ref(null)

// Form data
const form = ref({
    email: '',
    password: '',
    verificationCode: ''
})

// State management
const currentStep = ref(1) // 1 = email/password, 2 = verification code
const verificationStatus = ref('pending') // pending, success, error
const isVerifying = ref(false)
const isResending = ref(false)
const error = ref('')
const errorMessage = ref('')
const successMessage = ref('')
const showPassword = ref(false)
const resendCooldown = ref(0)
const resendTimer = ref(null)
const isCheckingStatus = ref(true)

// Check for pending verification session on mount
onMounted(async () => {
    try {
        const response = await axiosInstance.get('/api/v1/auth/verify/email/status')
        if (response.data.has_pending_verification) {
            form.value.email = response.data.email
            currentStep.value = 2
            successMessage.value = t('common.verificationCodeSentToYourEmail')
            startResendCooldown()
        }
    } catch (err) {
        // If status check fails, just show step 1
        console.error('Failed to check verification status:', err)
    } finally {
        isCheckingStatus.value = false
    }
})

const canSubmitStep1 = computed(() => {
    return form.value.email && form.value.password && !isVerifying.value
})

const canSubmitStep2 = computed(() => {
    return (
        form.value.verificationCode &&
        form.value.verificationCode.length === 6 &&
        !isVerifying.value
    )
})

const getTitle = () => {
    if (verificationStatus.value === 'success') {
        return t('common.verificationComplete')
    }
    if (verificationStatus.value === 'error') {
        return t('common.verificationFailed')
    }
    if (currentStep.value === 1) {
        return t('common.verifyYourEmail')
    }
    return t('common.enterVerificationCode')
}

const getSubtitle = () => {
    if (verificationStatus.value === 'success' || verificationStatus.value === 'error') {
        return ''
    }
    if (currentStep.value === 1) {
        return t('common.enterYourEmailAndPasswordToReceiveVerificationCode')
    }
    return t('common.weSentAVerificationCodeToYourEmail')
}

function resetCaptcha() {
    captchaToken.value = ''
    form.value.captcha_token = ''
    if (appCaptcha.enabled) {
        if (appCaptcha.provider === 'turnstile' && turnstileRef.value) {
            turnstileRef.value.reset()
        } else if (appCaptcha.provider === 'hcaptcha' && hcaptchaRef.value) {
            hcaptchaRef.value.reset()
        }
    }
}

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
    resetCaptcha()
}

const handleInitiateVerification = async () => {
    error.value = ''
    isVerifying.value = true

    try {
        const response = await axiosInstance.post('/api/v1/auth/verify/email', {
            email: form.value.email,
            password: form.value.password,
            captcha_type: form.value.captcha_type,
            captcha_token: form.value.captcha_token
        })

        if (response.status === 200) {
            currentStep.value = 2
            successMessage.value =
                response.data?.message || t('common.verificationCodeSentToYourEmail')
            startResendCooldown()
        }
    } catch (err) {
        error.value =
            err.response?.data?.error?.message ||
            err.response?.data?.message ||
            t('common.failedToSendVerificationCode')
    } finally {
        isVerifying.value = false
    }
}

const handleSubmitVerification = async () => {
    error.value = ''
    successMessage.value = ''
    isVerifying.value = true

    try {
        const response = await axiosInstance.post('/api/v1/auth/verify/email/confirm', {
            email: form.value.email,
            code: form.value.verificationCode
        })

        if (response.status === 200) {
            verificationStatus.value = 'success'
        }
    } catch (err) {
        error.value =
            err.response?.data?.error?.message ||
            err.response?.data?.message ||
            t('common.invalidVerificationCode')
        verificationStatus.value = 'error'
        errorMessage.value = error.value
    } finally {
        isVerifying.value = false
    }
}

const resendVerification = async () => {
    if (resendCooldown.value > 0 || isResending.value) return

    error.value = ''
    isResending.value = true

    try {
        const response = await axiosInstance.post('/api/v1/auth/verify/email/resend', {
            email: form.value.email
        })

        if (response.status === 200) {
            successMessage.value = t('common.newVerificationCodeSent')
            startResendCooldown()
        }
    } catch (err) {
        error.value =
            err.response?.data?.error?.message ||
            err.response?.data?.message ||
            t('common.failedToResendVerificationEmail')
    } finally {
        isResending.value = false
    }
}

const startResendCooldown = () => {
    resendCooldown.value = 60
    if (resendTimer.value) {
        clearInterval(resendTimer.value)
    }
    resendTimer.value = setInterval(() => {
        resendCooldown.value--
        if (resendCooldown.value <= 0) {
            clearInterval(resendTimer.value)
        }
    }, 1000)
}

const goBackToStep1 = () => {
    currentStep.value = 1
    form.value.verificationCode = ''
    error.value = ''
    successMessage.value = ''
    verificationStatus.value = 'pending'
}

const goToLogin = () => {
    router.push('/login')
}
</script>

<style scoped>
input[type='text']::-webkit-inner-spin-button,
input[type='text']::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type='text'] {
    -moz-appearance: textfield;
}
</style>
