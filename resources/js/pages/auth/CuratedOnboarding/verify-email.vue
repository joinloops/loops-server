<template>
    <BlankLayout>
        <div class="verify-email relative min-h-screen overflow-hidden bg-gray-50 dark:bg-gray-950">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div
                    class="absolute -top-1/2 -left-1/4 h-[900px] w-[900px] rounded-full bg-gradient-to-br from-[#F02C56]/40 via-[#F02C56]/10 to-transparent blur-3xl animate-drift-slow"
                />
                <div
                    class="absolute -bottom-1/3 -right-1/4 h-[700px] w-[700px] rounded-full bg-gradient-to-tl from-[#F02C56]/30 via-rose-400/10 to-transparent blur-3xl animate-drift-slow-reverse"
                />
                <div
                    class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-[500px] rounded-full bg-gradient-to-br from-rose-400/10 via-[#F02C56]/10 to-transparent blur-2xl animate-drift-slow"
                />
            </div>

            <div
                class="relative z-10 flex min-h-screen items-center justify-center px-4 py-12 sm:py-16"
            >
                <Transition
                    enter-active-class="transition duration-500 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-4"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                >
                    <div v-if="status === 'success'" class="w-full max-w-lg text-center">
                        <div
                            class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-green-500 shadow-lg shadow-green-500/25"
                        >
                            <svg
                                class="h-10 w-10 text-white"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2.5"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>
                        <h2
                            class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl"
                        >
                            Email verified!
                        </h2>
                        <p class="mt-3 text-lg text-gray-500 dark:text-gray-400">
                            Your email address has been confirmed. Our team will now review your
                            application and get back to you soon.
                        </p>
                        <div
                            class="mt-8 inline-flex items-center gap-2 rounded-full bg-gray-100 dark:bg-gray-800/80 px-5 py-2.5 text-sm text-gray-600 dark:text-gray-300"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                />
                            </svg>
                            We'll be in touch soon
                        </div>
                        <div class="mt-6">
                            <router-link
                                to="/"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-[#F02C56] hover:underline"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 19l-7-7 7-7"
                                    />
                                </svg>
                                Back to home
                            </router-link>
                        </div>
                    </div>
                </Transition>

                <Transition
                    enter-active-class="transition duration-500 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-4"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                >
                    <div v-if="status === 'failed'" class="w-full max-w-lg">
                        <div class="text-center mb-8">
                            <div
                                class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-red-400 to-rose-600 shadow-lg shadow-red-500/25"
                            >
                                <svg
                                    class="h-10 w-10 text-white"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2.5"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl"
                            >
                                {{ failedTitle }}
                            </h2>
                            <p class="mt-3 text-2xl text-gray-500 dark:text-gray-400">
                                {{ failedMessage }}
                            </p>

                            <p class="mt-3 text-base text-gray-500 dark:text-gray-400">
                                Please
                                <router-link
                                    to="/contact"
                                    class="text-red-500 font-medium underline"
                                    >contact support</router-link
                                >
                                if you think this is an error.
                            </p>
                        </div>

                        <div class="mt-3 px-3 flex justify-between items-center gap-3">
                            <AnimatedButton
                                variant="primaryGradient"
                                size="lg"
                                class="w-full"
                                pill
                                @click="router.push('/')"
                            >
                                Go home
                            </AnimatedButton>
                        </div>
                    </div>
                </Transition>

                <div v-if="status === 'idle'" class="w-full max-w-lg">
                    <div class="mb-8 text-center sm:mb-10">
                        <div
                            class="mb-4 inline-flex items-center gap-2 rounded-full border border-gray-200 dark:border-gray-700/60 bg-white/70 dark:bg-gray-800/50 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400 backdrop-blur-sm"
                        >
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#F02C56] opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex h-2 w-2 rounded-full bg-[#F02C56]"
                                ></span>
                            </span>
                            One more step
                        </div>
                        <h1
                            class="text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-5xl"
                        >
                            Verify your <span class="text-[#F02C56]">email</span>
                        </h1>
                        <p class="mt-2 text-base text-gray-500 dark:text-gray-400 sm:text-lg">
                            We sent a verification link to
                            <strong v-if="displayEmail" class="text-gray-700 dark:text-gray-200">{{
                                displayEmail
                            }}</strong><span v-else>your inbox</span>.
                            <br class="hidden sm:block" />
                            Click it or paste the token below.
                        </p>
                    </div>

                    <div
                        class="rounded-3xl border border-gray-200/80 dark:border-gray-700/40 bg-white/90 dark:bg-gray-900/80 shadow-xl shadow-gray-900/5 dark:shadow-black/30 backdrop-blur-md px-6 py-8 sm:px-8"
                    >
                        <div v-if="hasError" class="text-center mb-8">
                            <h2
                                class="text-3xl font-extrabold tracking-tight text-red-500 sm:text-4xl"
                            >
                                {{ failedTitle }}
                            </h2>
                            <p class="mt-3 text-base text-red-500 dark:text-red-400">
                                {{ failedMessage }}
                            </p>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label
                                    class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                >
                                    Verification Token
                                </label>
                                <input
                                    v-model="token"
                                    type="text"
                                    placeholder="Paste your token here"
                                    spellcheck="false"
                                    autocomplete="off"
                                    class="form-input font-mono tracking-wider"
                                    :class="
                                        tokenInvalid
                                            ? '!border-red-400 dark:!border-red-500 !ring-red-400/20'
                                            : ''
                                    "
                                    @input="tokenInvalid = false"
                                />
                                <Transition
                                    enter-active-class="transition duration-200 ease-out"
                                    enter-from-class="opacity-0 -translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition duration-150 ease-in"
                                    leave-from-class="opacity-100"
                                    leave-to-class="opacity-0"
                                >
                                    <p
                                        v-if="tokenInvalid"
                                        class="mt-2 flex items-center gap-1.5 text-sm font-medium text-red-500"
                                    >
                                        <svg
                                            class="h-4 w-4 flex-shrink-0"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                                            />
                                        </svg>
                                        Please paste a valid verification token.
                                    </p>
                                </Transition>
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
                                        theme="auto"
                                        @success="onCaptchaSuccess"
                                        @error="onCaptchaError"
                                        @expired="onCaptchaExpired"
                                    />
                                </template>
                            </template>

                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 -translate-y-1"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-150"
                                leave-to-class="opacity-0"
                            >
                                <div
                                    v-if="generalError"
                                    class="flex items-start gap-2.5 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-500/20 p-3.5"
                                >
                                    <svg
                                        class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <p class="text-sm font-medium text-red-600 dark:text-red-400">
                                        {{ generalError }}
                                    </p>
                                </div>
                            </Transition>

                            <AnimatedButton
                                variant="primaryGradient"
                                size="lg"
                                :pill="true"
                                :disabled="!canVerify"
                                :loading="loading"
                                class="w-full"
                                @click="handleVerify"
                            >
                                Verify Email Address
                            </AnimatedButton>
                        </div>
                    </div>

                    <div
                        class="mt-3 px-3 flex justify-between items-center gap-3 text-gray-400 dark:text-gray-500"
                    >
                        <router-link to="/" class="flex items-center gap-1">
                            <HomeIcon class="size-6" />
                        </router-link>

                        <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">
                            Having trouble?
                            <router-link
                                to="/contact"
                                class="text-[#F02C56] font-semibold hover:underline"
                            >
                                Contact support
                            </router-link>
                        </p>

                        <button @click="handleToggleDarkMode" class="cursor-pointer">
                            <SunIcon v-if="isDark" class="size-6" />
                            <MoonIcon v-else class="size-6" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </BlankLayout>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AnimatedButton from '@/components/AnimatedButton.vue'
import CloudflareTurnstile from '@/components/Captcha/CloudflareTurnstile.vue'
import HCaptcha from '@/components/Captcha/HCaptcha.vue'
import { HomeIcon, MoonIcon, SunIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const api = useApiClient()
const route = useRoute()
const appCaptcha = inject('appCaptcha')
const authStore = inject('authStore')

const status = ref('idle')
const loading = ref(false)
const token = ref('')
const tokenInvalid = ref(false)
const generalError = ref(null)
const hasError = ref(false)

const captchaToken = ref('')
const captchaType = ref(appCaptcha.provider)
const turnstileRef = ref(null)
const hcaptchaRef = ref(null)

const failedTitle = ref('Verification failed')
const failedMessage = ref('This link has expired or is invalid. Request a new one below.')

const isDark = ref(document.documentElement.classList.contains('dark'))

const displayEmail = computed(() => {
    const raw = route.query.email || ''
    if (!raw) return ''
    const [local, domain] = raw.split('@')
    if (!domain) return raw
    return `${local[0]}${'•'.repeat(Math.max(local.length - 2, 2))}${local.at(-1)}@${domain}`
})

const canVerify = computed(() => {
    if (!token.value.trim()) return false
    if (appCaptcha?.enabled && !captchaToken.value) return false
    return true
})

const handleToggleDarkMode = () => {
    isDark.value = !isDark.value
    document.documentElement.classList.toggle('dark', isDark.value)
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
}

const onCaptchaSuccess = (t) => {
    captchaToken.value = t
}

const onCaptchaError = () => {
    captchaToken.value = ''
    resetCaptcha()
}

const onCaptchaExpired = () => {
    captchaToken.value = ''
    resetCaptcha()
}

const resetCaptcha = () => {
    if (appCaptcha?.provider === 'turnstile') {
        turnstileRef.value?.reset()
    } else if (appCaptcha?.provider === 'hcaptcha') {
        hcaptchaRef.value?.reset()
    }
}

async function handleVerify() {
    const trimmed = token.value.trim()
    if (!trimmed) {
        tokenInvalid.value = true
        return
    }

    loading.value = true
    generalError.value = null
    hasError.value = false

    try {
        await api
            .post('/api/v1/onboarding/verify-email', {
                token: trimmed,
                captcha_type: captchaType.value,
                captcha_token: captchaToken.value
            })
            .then((res) => {
                if (
                    res.data?.message.startsWith(
                        'Email verified successfully. Your application is now being reviewed.'
                    )
                ) {
                    status.value = 'success'
                }
            })
            .catch((e) => {
                const code = e?.response?.status
                const msg = e?.response?.data?.message

                if (msg.startsWith('The captcha type field is required.')) {
                    failedTitle.value = 'Captcha error'
                    failedMessage.value = e?.response?.data?.message
                    loading.value = false
                    hasError.value = true
                } else if (code === 422 || code === 404) {
                    failedTitle.value = 'Invalid token'
                    failedMessage.value =
                        e?.response?.data?.message ||
                        'This token is invalid or has already been used.'
                    status.value = 'failed'
                } else if (code === 410) {
                    failedTitle.value = 'Link expired'
                    failedMessage.value =
                        'This verification link has expired. Links are only valid for 24 hours.'
                    status.value = 'failed'
                } else {
                    generalError.value =
                        e?.response?.data?.message || 'Something went wrong. Please try again.'
                    resetCaptcha()
                }
            })
    } catch (e) {
        const code = e?.response?.status
        const msg = e?.response?.data?.message

        if (msg.startsWith('The captcha type field is required.')) {
            failedTitle.value = 'Captcha error'
            failedMessage.value = e?.response?.data?.message
            loading.value = false
            hasError.value = true
        } else if (code === 422 || code === 404) {
            failedTitle.value = 'Invalid token'
            failedMessage.value =
                e?.response?.data?.message || 'This token is invalid or has already been used.'
            status.value = 'failed'
        } else if (code === 410) {
            failedTitle.value = 'Link expired'
            failedMessage.value =
                'This verification link has expired. Links are only valid for 24 hours.'
            status.value = 'failed'
        } else {
            generalError.value =
                e?.response?.data?.message || 'Something went wrong. Please try again.'
            resetCaptcha()
        }
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    if (authStore.isAuthenticated) {
        router.push('/')
        return
    }
    if (route.query.token) token.value = route.query.token
})
</script>
