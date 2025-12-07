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
                <h1 class="text-2xl font-semibold tracking-tight">Delete account</h1>
            </div>
            <hr class="border-gray-300 dark:border-gray-700" />

            <section class="my-8">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <i class="bx bx-error text-red-500 text-[20px] mt-0.5"></i>
                        <div>
                            <h3 class="font-medium text-red-800 mb-1">This action is permanent</h3>
                            <p class="text-sm text-red-700">
                                Account deletion cannot be undone. Consider deactivating instead if
                                you might want to return.
                            </p>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">What gets deleted</h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <i class="bx bx-trash text-red-400 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        All your videos
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        Every video you've posted will be permanently removed from
                                        Loops.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i
                                    class="bx bx-message-square-x text-red-400 text-[20px] mt-0.5"
                                ></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Comments and interactions
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        All your comments, likes, and other interactions will be
                                        removed.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bx bx-user-x text-red-400 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Profile and followers
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        Your profile, follower connections, and following list will
                                        be deleted.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bx bx-history text-red-400 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Account history
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        Watch history, preferences, and all account data will be
                                        permanently erased.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    Before you proceed
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <i class="bx bx-download text-blue-500 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Download your data
                                    </h4>
                                    <p class="text-sm text-gray-600 mb-2">
                                        Make sure to download a copy of your data before deletion.
                                    </p>
                                    <router-link
                                        to="/dashboard/account/data"
                                        class="text-sm text-blue-600 font-medium hover:text-blue-700"
                                    >
                                        Download account data →
                                    </router-link>
                                </div>
                            </div>
                            <div class="hidden flex items-start gap-3">
                                <i class="bx bx-pause text-orange-500 text-[20px] mt-0.5"></i>
                                <div>
                                    <h4 class="font-medium text-gray-800">
                                        Consider deactivation instead
                                    </h4>
                                    <p class="text-sm text-gray-600 mb-2">
                                        Deactivation hides your account temporarily but preserves
                                        all your data.
                                    </p>
                                    <router-link
                                        to="/dashboard/account/deactivate"
                                        class="text-sm text-orange-600 font-medium hover:text-orange-700"
                                    >
                                        Learn about deactivation →
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">Deletion timeline</h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mb-6">
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center mt-0.5"
                                >
                                    <span class="text-xs font-medium text-blue-600">1</span>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Immediate deactivation
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        Your account becomes inaccessible immediately after
                                        confirmation.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center mt-0.5"
                                >
                                    <span class="text-xs font-medium text-blue-600">2</span>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        30-day grace period
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        Your data is marked for deletion but not yet removed. Simply
                                        log back in if you change your mind.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center mt-0.5"
                                >
                                    <span class="text-xs font-medium text-red-600">3</span>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-gray-300">
                                        Permanent deletion
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        After 30 days, all your data is permanently and irreversibly
                                        deleted.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="tracking-tight font-light mb-4 dark:text-gray-300">
                    Final confirmation
                </h2>
                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                    <div class="p-6">
                        <div class="space-y-4 mb-6">
                            <label class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    v-model="confirmPermanent"
                                    class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                />
                                <span class="text-sm text-gray-700"
                                    >I understand that this action is permanent and cannot be
                                    undone.</span
                                >
                            </label>

                            <label class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    v-model="confirmDataLoss"
                                    class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                />
                                <span class="text-sm text-gray-700"
                                    >I understand that all my videos, comments, and account data
                                    will be permanently deleted.</span
                                >
                            </label>

                            <label class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    v-model="confirmBackup"
                                    class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                />
                                <span class="text-sm text-gray-700"
                                    >I have downloaded any data I want to keep (or I don't need to
                                    keep any data).</span
                                >
                            </label>
                        </div>

                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                            >
                                Type "DELETE" to confirm
                            </label>
                            <input
                                type="text"
                                v-model="deleteConfirmText"
                                placeholder="Type DELETE in capital letters"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:text-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            />
                        </div>

                        <div class="mb-6">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                            >
                                Enter your password
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
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                @click="$router.back()"
                            >
                                Cancel
                            </button>
                            <button
                                :disabled="!canDelete"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                                @click="deleteAccount"
                            >
                                Delete my account permanently
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
import { ref, computed, inject } from 'vue'
import { useRouter } from 'vue-router'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import { useAlertModal } from '@/composables/useAlertModal.js'

const confirmPermanent = ref(false)
const confirmDataLoss = ref(false)
const confirmBackup = ref(false)
const deleteConfirmText = ref('')
const passwordConfirm = ref('')
const { alertModal, confirmModal } = useAlertModal()
const router = useRouter()
const authStore = inject('authStore')

const axiosInstance = axios.getAxiosInstance()

const canDelete = computed(() => {
    return (
        confirmPermanent.value &&
        confirmDataLoss.value &&
        confirmBackup.value &&
        deleteConfirmText.value === 'DELETE' &&
        passwordConfirm.value.length > 0
    )
})

const deleteAccount = async () => {
    await axiosInstance
        .post('/api/v1/account/settings/account/delete', {
            password: passwordConfirm.value
        })
        .then((res) => {
            alertModal(
                'Successfully deleted account.',
                'Your account is now pending deletion in 30 days, or until you log in again!',
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
}
</script>
