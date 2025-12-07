<template>
    <SettingsLayout>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <button
                    class="flex items-center text-gray-500 hover:text-gray-400 cursor-pointer"
                    @click="$router.back()"
                >
                    <i class="bx bx-arrow-back text-[20px] mr-1"></i>
                </button>
                <h1 class="text-2xl font-semibold tracking-tight dark:text-gray-100">
                    {{ $t('settings.emailSettings') }}
                </h1>
            </div>
            <hr class="border-gray-300 dark:border-gray-700" />

            <section class="my-8">
                <div class="-mt-3 mb-6">
                    <div class="flex items-start gap-3">
                        <div>
                            <p class="text-gray-700 dark:text-blue-400">
                                {{ $t('settings.yourEmailIsUsedForRecoveryMessage') }}
                            </p>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ $t('settings.currentEmailAddress') }}
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center"
                                >
                                    <i class="bx bx-envelope text-blue-600 text-[24px]"></i>
                                </div>
                                <div>
                                    <h3
                                        class="text-lg font-medium text-gray-800 dark:text-gray-300"
                                    >
                                        {{ currentEmail }}
                                    </h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span
                                            class="text-sm"
                                            :class="
                                                emailVerified ? 'text-green-600' : 'text-orange-600'
                                            "
                                        >
                                            <i
                                                :class="
                                                    emailVerified
                                                        ? 'bx bx-check-circle'
                                                        : 'bx bx-error-circle'
                                                "
                                                class="mr-1"
                                            ></i>
                                            {{
                                                emailVerified
                                                    ? $t('settings.verified')
                                                    : $t('settings.unverified')
                                            }}
                                        </span>
                                        <span class="text-gray-300">•</span>
                                        <span v-if="emailVerified" class="text-sm text-gray-500"
                                            >{{ $t('settings.added') }}
                                            {{ formatDate(emailAddedDate) }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                            <button
                                v-if="!emailVerified"
                                class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                @click="resendVerification"
                                :disabled="isResending"
                            >
                                {{
                                    isResending
                                        ? $t('settings.sendingDotDotDot')
                                        : $t('settings.resendVerification')
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="pendingEmail"
                    class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6"
                >
                    <div class="flex items-start gap-3">
                        <i class="bx bx-time text-orange-500 text-[20px] mt-0.5"></i>
                        <div class="flex-1">
                            <h3 class="font-medium text-orange-800 mb-1">
                                {{ $t('settings.emailChangePending') }}
                            </h3>
                            <p class="text-sm text-orange-700 mb-3">
                                {{ $t('settings.weveSentAnEmail') }}
                                <strong>{{ pendingEmail }}</strong
                                >. {{ $t('settings.clickTheLinkInThatEmail') }}
                            </p>
                            <div class="flex space-x-10">
                                <button
                                    class="text-sm text-orange-600 font-medium hover:text-orange-700 cursor-pointer"
                                    @click="resendPendingVerification"
                                    :disabled="isResending"
                                >
                                    {{
                                        isResending
                                            ? $t('settings.sendingDotDotDot')
                                            : $t('settings.resendVerification')
                                    }}
                                </button>
                                <button
                                    class="text-sm text-red-600 font-medium hover:text-red-700 cursor-pointer"
                                    @click="cancelEmailChange"
                                >
                                    {{ $t('settings.cancelChange') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    {{ $t('settings.changeEmailAddress') }}
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <form @submit.prevent="changeEmail">
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2"
                                >
                                    {{ $t('settings.newEmailAddress') }}
                                </label>
                                <input
                                    type="email"
                                    v-model="newEmail"
                                    :placeholder="$t('settings.enterYourNewEmailAddress')"
                                    class="w-full px-3 py-2 border border-gray-300 dark:text-gray-200 dark:border-gray-700 dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    :class="{ 'border-red-300': emailError }"
                                    :disabled="isChangingEmail || !!pendingEmail"
                                />
                                <p v-if="emailError" class="text-sm text-red-600 mt-1">
                                    {{ emailError }}
                                </p>
                            </div>

                            <div class="mb-6">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2"
                                >
                                    {{ $t('settings.confirmYourPassword') }}
                                </label>
                                <input
                                    type="password"
                                    v-model="passwordConfirm"
                                    :placeholder="$t('settings.enterYourCurrentPassword')"
                                    class="w-full px-3 py-2 border border-gray-300 dark:text-gray-200 dark:border-gray-700 dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    :class="{ 'border-red-300': passwordError }"
                                    :disabled="isChangingEmail || !!pendingEmail"
                                />
                                <p v-if="passwordError" class="text-sm text-red-600 mt-1">
                                    {{ passwordError }}
                                </p>
                            </div>

                            <div class="flex gap-3">
                                <button
                                    type="submit"
                                    :disabled="!canChangeEmail || isChangingEmail || !!pendingEmail"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 dark:bg-blue-700 rounded-lg hover:bg-blue-700 disabled:bg-gray-300 dark:disabled:text-gray-700 dark:disabled:bg-gray-900 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                >
                                    {{
                                        isChangingEmail
                                            ? $t('settings.changingDotDotDot')
                                            : $t('settings.changeEmail')
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                                    @click="resetForm"
                                    :disabled="isChangingEmail"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!--<h2 class="tracking-tight font-light mb-4 dark:text-gray-300">Email preferences</h2>
                <div class="flex flex-col gap-3 mb-6">
                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[60%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">Security alerts</h3>
                                <p class="text-xs text-gray-500 font-light">Receive emails about login attempts, password changes, and other security events.</p>
                            </div>
                            <div class="flex items-center">
                                <span class="text-sm text-gray-600 mr-3">Required</span>
                                <ToggleSwitch v-model="securityAlerts" :disabled="true" />
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[60%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">Account notifications</h3>
                                <p class="text-xs text-gray-500 font-light">Get notified about followers, comments, and other account activity.</p>
                            </div>
                            <ToggleSwitch v-model="accountNotifications" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[60%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">Product updates</h3>
                                <p class="text-xs text-gray-500 font-light">Receive emails about new features, improvements, and platform updates.</p>
                            </div>
                            <ToggleSwitch v-model="productUpdates" />
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="px-4 py-6 flex items-center justify-between">
                            <div class="flex flex-col max-w-[60%]">
                                <h3 class="font-medium mb-2 dark:text-gray-300">Marketing emails</h3>
                                <p class="text-xs text-gray-500 font-light">Promotional content, tips, and recommendations from Loops.</p>
                            </div>
                            <ToggleSwitch v-model="marketingEmails" />
                        </div>
                    </div>
                </div>-->

                <!--<h2 class="tracking-tight font-light mb-4 dark:text-gray-300">Troubleshooting</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                        <div class="p-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <i class="bx bx-help-circle text-blue-500 text-[20px]"></i>
                                <div>
                                    <h3 class="font-medium text-gray-800 dark:text-gray-300">Not receiving emails?</h3>
                                    <p class="text-xs text-gray-600  dark:text-gray-500">Check your spam folder and email filters</p>
                                </div>
                            </div>
                            <i class="bx bx-chevron-right text-gray-400"></i>
                        </div>
                    </div>
                </div>-->
            </section>
        </div>
    </SettingsLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import ToggleSwitch from '@/components/Form/ToggleSwitch.vue'
import axios from '~/plugins/axios'
const axiosInstance = axios.getAxiosInstance()
import { useUtils } from '@/composables/useUtils'
const { formatDate } = useUtils()
import { useAlertModal } from '@/composables/useAlertModal.js'
const { alertModal, confirmModal } = useAlertModal()

const currentEmail = ref()
const emailVerified = ref()
const emailAddedDate = ref()
const pendingEmail = ref('')

const newEmail = ref('')
const passwordConfirm = ref('')
const emailError = ref('')
const passwordError = ref('')

const isChangingEmail = ref(false)
const isResending = ref(false)
const isSendingTest = ref(false)

const securityAlerts = ref(true)
const accountNotifications = ref(true)
const productUpdates = ref(false)
const marketingEmails = ref(false)
const emailFrequency = ref('instant')

const canChangeEmail = computed(() => {
    return (
        newEmail.value &&
        newEmail.value !== currentEmail.value &&
        passwordConfirm.value &&
        !emailError.value &&
        !passwordError.value
    )
})

const fetchEmailData = async () => {
    try {
        await axiosInstance.get('/api/v1/account/settings/email').then((res) => {
            currentEmail.value = res.data.data.current_email
            emailVerified.value = res.data.data.email_verified
            emailAddedDate.value = res.data.data.email_added_date
            pendingEmail.value = res.data.data.pending_email
            console.log(res.data.data)
        })
    } catch (error) {}
}

const validateEmail = () => {
    emailError.value = ''

    if (!newEmail.value) {
        return
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(newEmail.value)) {
        emailError.value = 'Please enter a valid email address.'
        return
    }

    if (newEmail.value === currentEmail.value) {
        emailError.value = 'This is already your current email address.'
        return
    }

    if (newEmail.value.length > 255) {
        emailError.value = 'Email address is too long.'
        return
    }
}

const validatePassword = () => {
    passwordError.value = ''

    if (!passwordConfirm.value) {
        return
    }

    if (passwordConfirm.value.length < 1) {
        passwordError.value = 'Password is required to change your email.'
        return
    }
}

const changeEmail = async () => {
    validateEmail()
    validatePassword()

    if (emailError.value || passwordError.value) {
        return
    }

    isChangingEmail.value = true

    try {
        const response = await axiosInstance.post('/api/v1/account/settings/email/update', {
            email: newEmail.value,
            password: passwordConfirm.value
        })

        if (response.status === 200) {
            pendingEmail.value = newEmail.value
            resetForm()

            alertModal('Success', 'Email change initiated. Check your new email for verification.')
            console.log('Email change initiated. Check your new email for verification.')
        }
    } catch (error) {
        console.error('Email change failed:', error)

        if (error.response && error.response.data) {
            const errorData = error.response.data

            if (errorData.error && errorData.error.message && errorData.error.message.errors) {
                const errors = errorData.error.message.errors

                if (errors.email) {
                    emailError.value = errors.email[0]
                }
                if (errors.password) {
                    passwordError.value = errors.password[0]
                }
            } else if (errorData.error && errorData.error.message) {
                emailError.value =
                    typeof errorData.error.message === 'string'
                        ? errorData.error.message
                        : 'Failed to change email. Please try again.'
            } else {
                emailError.value = 'Failed to change email. Please try again.'
            }
        } else if (error.request) {
            emailError.value = 'Network error. Please check your connection and try again.'
        } else {
            emailError.value = 'An unexpected error occurred. Please try again.'
        }
    } finally {
        isChangingEmail.value = false
    }
}

const resendVerification = async () => {
    isResending.value = true

    try {
        const response = await fetch('/api/user/email/verify/resend', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
                Accept: 'application/json',
                'Content-Type': 'application/json'
            }
        })

        if (response.ok) {
            console.log('Verification email sent successfully.')
        } else {
            console.error('Failed to send verification email.')
        }
    } catch (error) {
        console.error('Resend verification failed:', error)
    } finally {
        isResending.value = false
    }
}

const resendPendingVerification = async () => {
    isResending.value = true

    try {
        const response = await fetch('/api/user/email/change/resend', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
                Accept: 'application/json',
                'Content-Type': 'application/json'
            }
        })

        if (response.ok) {
            console.log('Verification email resent successfully.')
        } else {
            console.error('Failed to resend verification email.')
        }
    } catch (error) {
        console.error('Resend failed:', error)
    } finally {
        isResending.value = false
    }
}

const cancelEmailChange = async () => {
    const result = await confirmModal(
        'Confirm Email Change Cancellation',
        'Are you sure you want to cancel the email change?',
        'Confirm',
        'Close'
    )

    if (!result) {
        return
    }

    try {
        const response = await axiosInstance.post('/api/v1/account/settings/email/cancel')

        if (response.status === 200) {
            pendingEmail.value = ''
            console.log('Email change cancelled.')
        } else {
            console.error('Failed to cancel email change.')
        }
    } catch (error) {
        console.error('Cancel failed:', error)
    }
}

const resetForm = () => {
    newEmail.value = ''
    passwordConfirm.value = ''
    emailError.value = ''
    passwordError.value = ''
}

onMounted(() => fetchEmailData())
watch(newEmail, validateEmail)
watch(passwordConfirm, validatePassword)
</script>
