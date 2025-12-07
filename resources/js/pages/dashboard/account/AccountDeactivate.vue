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
                <h1 class="text-2xl font-semibold tracking-tight dark:text-gray-300">
                    Deactivate account
                </h1>
            </div>
            <hr class="border-gray-300 dark:border-gray-700" />

            <section class="my-8">
                <div
                    class="bg-orange-50 dark:bg-orange-200 border border-orange-200 rounded-lg p-4 mb-6"
                >
                    <div class="flex items-start gap-3">
                        <i class="bx bx-info-circle text-orange-500 text-[20px] mt-0.5"></i>
                        <div>
                            <h3 class="font-medium text-orange-800 mb-1">Before you deactivate</h3>
                            <p class="text-sm text-orange-700">
                                Account deactivation is temporary and reversible. You can reactivate
                                anytime by logging back in.
                            </p>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    What happens when you deactivate
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <i class="bx bx-hide text-gray-400 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Your profile will be hidden
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        Others won't be able to find or view your profile, videos,
                                        or comments.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bx bx-pause text-gray-400 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Your videos will be private
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        All your videos will become temporarily unavailable to
                                        others.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bx bx-user-minus text-gray-400 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Followers won't see new content
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        Your followers will stop receiving notifications and won't
                                        see your content in their feed.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bx bx-data text-gray-400 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Your data is preserved
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        All your content, followers, and account data will be saved
                                        and restored when you reactivate.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">Reactivation</h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <div class="flex items-start gap-3">
                            <i class="bx bx-refresh text-green-500 text-[20px] mt-0.5"></i>
                            <div>
                                <h4 class="font-medium text-gray-800 mb-2 dark:text-gray-300">
                                    Easy to reactivate
                                </h4>
                                <p class="text-sm text-gray-600 mb-3">
                                    Simply log in with your username and password to reactivate your
                                    account. Everything will be restored exactly as you left it.
                                </p>
                                <p class="text-xs text-gray-500">
                                    Note: If you don't reactivate within 30 days, we may send you a
                                    reminder email.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    Confirm deactivation
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                    <div class="p-6">
                        <div class="mb-4">
                            <label class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    v-model="confirmUnderstand"
                                    class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300"
                                    >I understand that my account will be temporarily deactivated
                                    and I can reactivate it by logging in.</span
                                >
                            </label>
                        </div>

                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                            >
                                Type your password to confirm
                            </label>
                            <input
                                type="password"
                                v-model="passwordConfirm"
                                placeholder="Enter your password"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:text-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            />
                        </div>

                        <div class="flex gap-3 justify-end">
                            <button
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                @click="$router.back()"
                            >
                                Cancel
                            </button>
                            <button
                                :disabled="!confirmUnderstand || !passwordConfirm"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                                @click="deactivateAccount"
                            >
                                Deactivate account
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>

<script setup>
import axios from '~/plugins/axios'
import { ref, inject } from 'vue'
import { useRouter } from 'vue-router'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import { useAlertModal } from '@/composables/useAlertModal.js'

const confirmUnderstand = ref(false)
const passwordConfirm = ref('')
const { alertModal, confirmModal } = useAlertModal()
const router = useRouter()
const authStore = inject('authStore')

const axiosInstance = axios.getAxiosInstance()

const deactivateAccount = async () => {
    await axiosInstance
        .post('/api/v1/account/settings/account/disable', {
            password: passwordConfirm.value
        })
        .then((res) => {
            alertModal(
                'Successfully disabled account.',
                'Your account is now disabled until you log in again!',
                [
                    {
                        text: 'Ok',
                        type: 'cancel',
                        callback: () => {
                            authStore.resetUser()
                            router.push('/')
                        }
                    }
                ]
            )
            console.log(res)
        })
        .catch((error) => {
            if (error.response && error.response.data && error.response.data.message) {
                alertModal('Error', error.response.data.message)
            } else {
                alertModal('Oops!', 'An unexpected error occured. Please try again.')
            }
        })
    console.log('Deactivating account...')
}
</script>
