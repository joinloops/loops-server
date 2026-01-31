<template>
    <BlankLayout>
        <RegistrationClosed v-if="registrationClosed" />
        <RegistrationAlreadyAuthenticated v-else-if="!registrationClosed && isAuthed" />
        <div v-else class="min-h-screen bg-white dark:bg-gray-950">
            <div class="flex flex-col min-h-screen">
                <div
                    class="sticky top-0 z-10 bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800"
                >
                    <div class="flex items-center justify-between px-4 py-3">
                        <button
                            v-if="!isMobileAuth"
                            @click="closeModal"
                            class="p-2 -ml-2 rounded-full text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 active:bg-gray-100 dark:active:bg-gray-800 transition-colors"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                        <div v-else class="w-8"></div>
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            {{ Math.round(((registrationStep - 1) / 3) * 100) }}%
                        </div>
                    </div>

                    <div class="w-full bg-gray-100 dark:bg-gray-900 h-1">
                        <div
                            class="bg-gradient-to-r from-rose-500 to-red-600 h-1 transition-all duration-500 ease-out"
                            :style="{ width: `${((registrationStep - 1) / 3) * 100}%` }"
                        ></div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <div class="px-6 py-8">
                        <div class="mb-8">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                {{ getTitle() }}
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ getSubtitle() }}
                            </p>
                        </div>

                        <div
                            v-if="error"
                            class="mb-6 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4"
                        >
                            <p class="text-sm text-red-700 dark:text-red-400">
                                {{ error }}
                            </p>
                        </div>

                        <div
                            v-if="success"
                            class="mb-6 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4"
                        >
                            <p class="text-sm text-green-700 dark:text-green-400">
                                {{ success }}
                            </p>
                        </div>

                        <form
                            v-if="registrationStep === 1"
                            @submit.prevent="handleSendCode"
                            class="space-y-5"
                        >
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
                                    inputmode="email"
                                    class="w-full px-4 py-3.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                    :placeholder="t('common.enterYourEmail')"
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
                            </template>

                            <AnimatedButton
                                type="submit"
                                :loading="loading"
                                class="w-full"
                                variant="primary"
                            >
                                {{ t('common.continue') }}
                            </AnimatedButton>

                            <button
                                v-if="error"
                                type="button"
                                @click="handleOtpAlreadyHaveCode"
                                class="w-full text-center text-sm font-semibold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 transition-colors py-2"
                            >
                                {{ t('common.iAlreadyHaveTheCode') }}
                            </button>
                        </form>

                        <form
                            v-if="registrationStep === 2"
                            @submit.prevent="handleVerifyCode"
                            class="space-y-5"
                        >
                            <div>
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
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    class="w-full px-4 py-3.5 text-center text-2xl tracking-[0.5em] rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                    placeholder="000000"
                                />
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    {{ t('common.enterTheSixDigitCodeSentTo') }}
                                    <span class="font-medium">{{ registrationData.email }}</span>
                                </p>
                            </div>

                            <div class="flex justify-center">
                                <button
                                    type="button"
                                    @click="handleResendCode"
                                    :disabled="resendCooldown > 0 || loading"
                                    class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 disabled:text-gray-400 dark:disabled:text-gray-600 transition-colors py-2"
                                >
                                    {{
                                        resendCooldown > 0
                                            ? `${t('common.resendIn')} ${resendCooldown}s`
                                            : t('common.resendCode')
                                    }}
                                </button>
                            </div>

                            <div class="flex gap-3">
                                <AnimatedButton
                                    type="button"
                                    @click="goBackStep"
                                    variant="outline"
                                    class="flex-1"
                                >
                                    {{ t('settings.back') }}
                                </AnimatedButton>
                                <AnimatedButton
                                    type="submit"
                                    :loading="loading"
                                    class="flex-1"
                                    variant="primary"
                                >
                                    {{ t('common.verify') }}
                                </AnimatedButton>
                            </div>
                        </form>

                        <form
                            v-if="registrationStep === 3"
                            @submit.prevent="handleBirthdateNext"
                            class="space-y-5"
                        >
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{ t('common.birthdate') || 'Birth date' }}
                                </label>

                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <select
                                            v-model="birth.month"
                                            required
                                            class="w-full px-3 py-3.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        >
                                            <option value="" disabled>
                                                {{ t('common.month') || 'Month' }}
                                            </option>
                                            <option
                                                v-for="m in months"
                                                :key="m.value"
                                                :value="m.value"
                                            >
                                                {{ m.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <select
                                            v-model="birth.day"
                                            required
                                            class="w-full px-3 py-3.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        >
                                            <option value="" disabled>
                                                {{ t('common.day') || 'Day' }}
                                            </option>
                                            <option v-for="d in days" :key="d" :value="d">
                                                {{ d }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <select
                                            v-model="birth.year"
                                            required
                                            class="w-full px-3 py-3.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        >
                                            <option value="" disabled>
                                                {{ t('common.year') || 'Year' }}
                                            </option>
                                            <option v-for="y in years" :key="y" :value="y">
                                                {{ y }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-3 text-xs text-gray-600 dark:text-gray-400">
                                    {{
                                        t('common.weUseThisToVerifyAge') ||
                                        "We use this to verify your age. It won't be public."
                                    }}
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <AnimatedButton
                                    type="button"
                                    @click="goBackStep"
                                    variant="outline"
                                    class="flex-1"
                                >
                                    {{ t('settings.back') }}
                                </AnimatedButton>
                                <AnimatedButton
                                    type="submit"
                                    :loading="loading"
                                    class="flex-1"
                                    variant="primary"
                                >
                                    {{ t('common.continue') }}
                                </AnimatedButton>
                            </div>
                        </form>

                        <form
                            v-if="registrationStep === 4"
                            @submit.prevent="handleSetProfile"
                            class="space-y-5"
                        >
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{ t('common.username') }}
                                </label>
                                <input
                                    v-model="form.username"
                                    type="text"
                                    required
                                    maxlength="24"
                                    autocomplete="off"
                                    class="w-full px-4 py-3.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                    :placeholder="t('common.chooseAUsername')"
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
                                        autocomplete="new-password"
                                        class="w-full px-4 py-3.5 pr-12 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                        :placeholder="t('common.createAStrongPassword')"
                                    />
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 active:bg-gray-100 dark:active:bg-gray-800 rounded-full transition-colors"
                                    >
                                        <svg
                                            v-if="showPassword"
                                            class="h-5 w-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L8.17 8.17m1.708 1.708a3 3 0 004.243 4.243m2.12-2.12L18.536 8.17m-1.415 1.708a3 3 0 00-4.243-4.243m-2.12 2.12L8.17 8.17"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-5 w-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                            />
                                        </svg>
                                    </button>
                                </div>

                                <div v-if="form.password" class="mt-3">
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <span class="text-gray-600 dark:text-gray-400">{{
                                            t('common.passwordStrength')
                                        }}</span>
                                        <span
                                            :class="`text-${passwordStrength.color}-600 dark:text-${passwordStrength.color}-400 font-medium`"
                                        >
                                            {{ passwordStrength.text }}
                                        </span>
                                    </div>
                                    <div
                                        class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5"
                                    >
                                        <div
                                            class="h-1.5 rounded-full transition-all duration-300"
                                            :class="`bg-${passwordStrength.color}-500`"
                                            :style="{
                                                width: `${(passwordStrength.score / 5) * 100}%`
                                            }"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{ t('common.confirmPassword') }}
                                </label>
                                <input
                                    v-model="form.confirmPassword"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    class="w-full px-4 py-3.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-base placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-colors"
                                    :placeholder="t('settings.confirmYourPassword')"
                                />
                            </div>

                            <div class="flex gap-3 pt-2">
                                <AnimatedButton
                                    type="button"
                                    @click="goBackStep"
                                    variant="outline"
                                    class="flex-1"
                                >
                                    {{ t('settings.back') }}
                                </AnimatedButton>
                                <AnimatedButton
                                    type="submit"
                                    :loading="loading"
                                    class="flex-1"
                                    variant="primary"
                                >
                                    {{ t('common.finishSignUp') }}
                                </AnimatedButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </BlankLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick, inject, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BlankLayout from '@/layouts/BlankLayout.vue'
import RegistrationClosed from '@/components/Layout/RegistrationClosed.vue'
import { useAuthStore } from '~/stores/auth'
import CloudflareTurnstile from '@/components/Captcha/CloudflareTurnstile.vue'
import HCaptcha from '@/components/Captcha/HCaptcha.vue'
import { useI18n } from 'vue-i18n'
import RegistrationAlreadyAuthenticated from '@/components/Layout/RegistrationAlreadyAuthenticated.vue'

const appConfig = inject('appConfig')
const authStore = useAuthStore()

const route = useRoute()
const router = useRouter()
const isAuthed = ref(false)
const registrationClosed = computed(() => appConfig && appConfig.registration === false)

const axios = inject('axios')
const appCaptcha = inject('appCaptcha')
const { t } = useI18n()

const loading = ref(false)
const error = ref('')
const success = ref('')
const showPassword = ref(false)
const minimumAge = ref(13)

const registrationStep = ref(1)
const resendCooldown = ref(0)
const resendTimer = ref(null)
const captchaType = ref(appCaptcha.provider)
const captchaToken = ref('')
const turnstileRef = ref(null)
const hcaptchaRef = ref(null)
const birth = ref({ day: '', month: '', year: '' })
const registrationBirthdate = ref('')

const isMobileAuth = computed(() => {
    return route.query.mobile === 'true'
})

const redirectUri = computed(() => {
    return route.query.redirect_uri || null
})

const months = [
    { value: 1, label: t('common.months.jan') || 'January' },
    { value: 2, label: t('common.months.feb') || 'February' },
    { value: 3, label: t('common.months.mar') || 'March' },
    { value: 4, label: t('common.months.apr') || 'April' },
    { value: 5, label: t('common.months.may') || 'May' },
    { value: 6, label: t('common.months.jun') || 'June' },
    { value: 7, label: t('common.months.jul') || 'July' },
    { value: 8, label: t('common.months.aug') || 'August' },
    { value: 9, label: t('common.months.sep') || 'September' },
    { value: 10, label: t('common.months.oct') || 'October' },
    { value: 11, label: t('common.months.nov') || 'November' },
    { value: 12, label: t('common.months.dec') || 'December' }
]

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

    if (appCaptcha.provider === 'turnstile' && turnstileRef.value) {
        turnstileRef.value.reset()
    } else if (appCaptcha.provider === 'hcaptcha' && hcaptchaRef.value) {
        hcaptchaRef.value.reset()
    }
}

const form = ref({
    email: '',
    password: '',
    confirmPassword: '',
    username: '',
    verificationCode: '',
    birthdate: '',
    captcha_type: '',
    captcha_token: ''
})

const registrationData = ref({
    email: '',
    username: '',
    password: '',
    token: '',
    user: null
})

const openRegistration = computed(() => {
    return appConfig.registration
})

const getTitle = () => {
    switch (registrationStep.value) {
        case 1:
            return t('common.createAccount')
        case 2:
            return t('common.verifyYourEmail')
        case 3:
            return t('common.confirmYourBirthdate')
        case 4:
            return t('common.setUpYourProfile')
        default:
            return t('common.createAccount')
    }
}

const getSubtitle = () => {
    switch (registrationStep.value) {
        case 1:
            return t('common.enterYourEmailToGetStarted')
        case 2:
            return t('common.weSentAVerificationCodeToYourEmail')
        case 3:
            return t('common.weNeedYourBirthdateToVerifyAge')
        case 4:
            return t('common.chooseAUsernameAndSecurePassword')
        default:
            return t('common.createANewAccountToGetStarted')
    }
}

const years = computed(() => {
    const now = new Date().getFullYear()
    const span = 120
    return Array.from({ length: span }, (_, i) => now - i)
})

function daysInMonth(y, m) {
    if (!y || !m) return 31
    return new Date(y, m, 0).getDate()
}

const days = computed(() => {
    const y = Number(birth.value.year) || 2000
    const m = Number(birth.value.month) || 1
    const max = daysInMonth(y, m)
    return Array.from({ length: max }, (_, i) => i + 1)
})

const birthdateFormatted = computed(() => {
    const y = birth.value.year
    const m = birth.value.month
    const d = birth.value.day
    if (!y || !m || !d) return ''
    const mm = String(m).padStart(2, '0')
    const dd = String(d).padStart(2, '0')
    return `${y}-${mm}-${dd}`
})

function isOldEnough(ymd, minYears) {
    const [y, m, d] = ymd.split('-').map(Number)
    const today = new Date()
    let age = today.getFullYear() - y
    const mdiff = today.getMonth() + 1 - m
    if (mdiff < 0 || (mdiff === 0 && today.getDate() < d)) age--
    return age >= minYears
}

const passwordStrength = computed(() => {
    const password = form.value.password
    if (!password) return { score: 0, text: '', color: '' }

    let score = 0
    const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        numbers: /\d/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    }

    score = Object.values(checks).filter(Boolean).length

    if (score <= 2) return { score, text: 'Weak', color: 'red' }
    if (score <= 3) return { score, text: 'Fair', color: 'yellow' }
    if (score <= 4) return { score, text: 'Good', color: 'blue' }
    return { score, text: 'Strong', color: 'green' }
})

async function verifyAgeApi(ymd) {
    try {
        const res = await axios.post('/api/v1/auth/register/verify-age', {
            birthdate: ymd,
            minAge: minimumAge.value
        })
        if (typeof res?.data?.data?.allowed === 'boolean') {
            minimumAge.value = res.data.data.minAge
            await nextTick()
            return res.data.data.allowed
        }
        return isOldEnough(ymd, minimumAge.value)
    } catch (e) {
        return isOldEnough(ymd, minimumAge.value)
    }
}

const redirectToApp = (token, user) => {
    if (!redirectUri.value) {
        console.error('No redirect URI provided')
        return
    }

    const url = new URL(redirectUri.value)
    url.searchParams.set('type', 'success')
    url.searchParams.set('token', token)
    url.searchParams.set('user', JSON.stringify(user))

    window.location.href = url.toString()
}

const closeModal = () => {
    if (isMobileAuth.value && redirectUri.value) {
        const url = new URL(redirectUri.value)
        url.searchParams.set('type', 'cancel')
        url.searchParams.set('cancelled', 'true')
        window.location.href = url.toString()
    } else {
        authStore.closeAuthModal()
    }
    clearForm()
    clearMessages()
    registrationStep.value = 1
}

const clearForm = () => {
    form.value = {
        email: '',
        password: '',
        confirmPassword: '',
        username: '',
        verificationCode: '',
        birthdate: '',
        captcha_type: '',
        captcha_token: ''
    }
    birth.value = { day: '', month: '', year: '' }
    registrationBirthdate.value = ''
}

const clearMessages = () => {
    error.value = ''
    success.value = ''
}

const setError = (message) => {
    error.value = message
    success.value = ''
}

const setSuccess = (message) => {
    success.value = message
    error.value = ''
}

const startResendCooldown = () => {
    resendCooldown.value = 60
    resendTimer.value = setInterval(() => {
        resendCooldown.value--
        if (resendCooldown.value <= 0) {
            clearInterval(resendTimer.value)
        }
    }, 1000)
}

const handleOtpAlreadyHaveCode = async () => {
    clearMessages()
    registrationData.value.email = form.value.email
    registrationStep.value = 2
}

const handleSendCode = async () => {
    clearMessages()
    loading.value = true

    try {
        const data = {
            email: form.value.email,
            captcha_type: form.value.captcha_type,
            captcha_token: form.value.captcha_token
        }
        await authStore.registerEmailVerification(data).then((res) => {
            registrationData.value.email = form.value.email
            registrationStep.value = 2
            setSuccess(t('common.verificationCodeSentToYourEmail'))
            startResendCooldown()
        })
    } catch (err) {
        setError(
            err.response?.data?.error?.message ||
                err.response?.data?.message ||
                t('common.failedToSendVerificationCode')
        )
        throw err
    } finally {
        loading.value = false
    }
}

const handleVerifyCode = async () => {
    clearMessages()
    loading.value = true

    // Strip spaces and non-numeric characters from the code
    const cleanCode = form.value.verificationCode?.replace(/[^0-9]/g, '') || ''
    form.value.verificationCode = cleanCode

    if (cleanCode.length != 6) {
        setError(t('common.invalidCodeLength'))
        setTimeout(() => {
            clearMessages()
            loading.value = false
        }, 1500)
        return
    }

    try {
        await authStore.verifyEmailVerification(form.value.email, cleanCode).then((res) => {
            registrationStep.value = 3
            setSuccess(t('common.emailVerifiedSuccessfully'))
        })
    } catch (err) {
        console.error('Verification error:', err)

        const errorMessage =
            err.response?.data?.message ||
            err.response?.data?.error?.message ||
            err.message ||
            t('common.invalidVerificationCode')

        setError(errorMessage)

        if (err.response?.status === 404) {
            setTimeout(() => {
                registrationStep.value = 1
                clearMessages()
            }, 2000)
        }
    } finally {
        loading.value = false
    }
}

const handleResendCode = async () => {
    if (resendCooldown.value > 0) return

    loading.value = true
    try {
        await authStore.registerResendEmailVerification({ email: form.value.email }).then((res) => {
            setSuccess(t('common.newVerificationCodeSent'))
            startResendCooldown()
        })
    } catch (err) {
        if (err?.response?.data?.message) {
            setError(err?.response?.data?.message)
        } else {
            setError(t('common.failedToResendVerificationCode'))
        }
    } finally {
        loading.value = false
    }
}

const handleBirthdateNext = async () => {
    clearMessages()
    loading.value = true

    try {
        if (!birthdateFormatted.value) {
            setError(t('common.pleaseSelectYourBirthdate'))
            return
        }

        const ok = await verifyAgeApi(birthdateFormatted.value)
        if (!ok) {
            setError(
                t('common.youMustBeAtLeastXYearsOld', {
                    years: minimumAge.value
                })
            )
            return
        }

        registrationBirthdate.value = birthdateFormatted.value
        registrationData.value.birthdate = registrationBirthdate.value
        form.value.birthdate = registrationBirthdate.value

        setSuccess(t('common.birthdateVerified') || 'Birthdate verified!')
        registrationStep.value = 4
    } catch (err) {
        setError(err?.message || t('common.failedToVerifyAgePleaseTryAgain'))
    } finally {
        loading.value = false
    }
}

const handleSetProfile = async () => {
    clearMessages()
    loading.value = true

    if (form.value.password !== form.value.confirmPassword) {
        setError(t('common.passwordsDoNotMatch'))
        loading.value = false
        return
    }

    if (passwordStrength.value.score < 3) {
        setError(t('common.pleaseChooseAStrongerPassword'))
        loading.value = false
        return
    }

    try {
        await authStore
            .registerUsername(
                form.value.username,
                form.value.password,
                form.value.confirmPassword,
                birthdateFormatted.value,
                isMobileAuth.value
            )
            .then((res) => {
                if (res.data?.data?.token) {
                    registrationData.value.token = res.data.data.token
                    registrationData.value.user = res.data.data.user
                }
                registrationData.value.username = form.value.username
                registrationData.value.password = form.value.password
            })

        await authStore.forceRegisterLogin()

        if (isMobileAuth.value && redirectUri.value) {
            redirectToApp(registrationData.value.token, registrationData.value.user)
        } else {
            setSuccess(t('common.accountCreatedSuccessfullyWelcomeAboard'))
            setTimeout(() => {
                window.location.reload()
                closeModal()
            }, 2000)
        }
    } catch (err) {
        setError(
            err.response?.data?.error?.message ||
                err.response?.data?.message ||
                t('common.anUnexpectedErrorOccuredPleaseTryAgain')
        )
        throw err
    } finally {
        loading.value = false
    }
}

const goBackStep = () => {
    if (registrationStep.value > 1) {
        registrationStep.value--
        clearMessages()
    }
}

onMounted(() => {
    if (authStore.authenticated) {
        isAuthed.value = true
    } else if (registrationClosed.value) {
        // Show registration closed message
    }

    document.addEventListener('gesturestart', (e) => e.preventDefault())
    document.addEventListener('gesturechange', (e) => e.preventDefault())
})
</script>

<style scoped>
input,
select,
textarea {
    font-size: 16px !important;
}

.overflow-y-auto {
    -webkit-overflow-scrolling: touch;
}
</style>
