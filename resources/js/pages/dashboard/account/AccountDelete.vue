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

            <section v-if="isAdmin" class="my-8">
                <div
                    class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg p-4"
                >
                    <div class="flex items-start gap-3">
                        <i class="bx bx-shield text-amber-500 text-[20px] mt-0.5"></i>
                        <div>
                            <h3 class="font-medium text-amber-800 dark:text-amber-300 mb-1">
                                Admin accounts can't be deleted here
                            </h3>
                            <p class="text-sm text-amber-700 dark:text-amber-400">
                                To protect your instance, admin accounts can only be deleted with
                                the command line tools on your server.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="my-8">
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
                                        Your data is marked for deletion but not yet removed, and
                                        your username stays reserved for you. Simply log back in if
                                        you change your mind.
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
                                        deleted, your media is removed from storage and other
                                        servers in the fediverse are told to remove their copies.
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

                        <div
                            class="mb-6 rounded-lg border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <label class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    v-model="immediate"
                                    class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                />
                                <span>
                                    <span
                                        class="block text-sm font-medium text-gray-800 dark:text-gray-300"
                                        >Skip the 30-day grace period</span
                                    >
                                    <span class="block text-sm text-gray-600"
                                        >Delete everything now and release your username right away,
                                        for example so another account of yours can use it.</span
                                    >
                                </span>
                            </label>

                            <div v-if="immediate" class="mt-4">
                                <div
                                    class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mb-4"
                                >
                                    <p class="text-sm text-amber-800 dark:text-amber-300">
                                        Your account can't be restored by logging back in. Your
                                        username becomes available to anyone on this server as soon
                                        as you confirm.
                                    </p>
                                </div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    Type your username
                                    <span class="font-semibold">@{{ username }}</span>
                                    to confirm
                                </label>
                                <input
                                    type="text"
                                    v-model="confirmUsername"
                                    autocomplete="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    :placeholder="username"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:text-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>
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
                                :disabled="!canDelete || submitting"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                                @click="deleteAccount"
                            >
                                {{
                                    immediate
                                        ? 'Delete my account now'
                                        : 'Delete my account permanently'
                                }}
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
const immediate = ref(false)
const confirmUsername = ref('')
const submitting = ref(false)
const { alertModal } = useAlertModal()
const router = useRouter()
const authStore = inject('authStore')

const axiosInstance = axios.getAxiosInstance()

const isAdmin = computed(() => !!authStore?.isAdmin)
const username = computed(() => authStore?.getUser?.username ?? '')

const usernameMatches = computed(() => {
    const typed = confirmUsername.value.trim().replace(/^@/, '').toLowerCase()
    return typed.length > 0 && typed === username.value.toLowerCase()
})

const canDelete = computed(() => {
    return (
        !isAdmin.value &&
        confirmPermanent.value &&
        confirmDataLoss.value &&
        confirmBackup.value &&
        deleteConfirmText.value === 'DELETE' &&
        passwordConfirm.value.length > 0 &&
        (!immediate.value || usernameMatches.value)
    )
})

const finish = (stillLoggedIn) => {
    if (stillLoggedIn) {
        window.location.href = '/'
        return
    }

    authStore.resetUser()
    router.push('/')
}

const deleteAccount = async () => {
    if (!canDelete.value || submitting.value) {
        return
    }

    submitting.value = true

    const payload = { password: passwordConfirm.value }

    if (immediate.value) {
        payload.immediate = true
        payload.confirm_username = confirmUsername.value.trim()
    }

    await axiosInstance
        .post('/api/v1/account/settings/account/delete', payload)
        .then((res) => {
            const data = res.data?.data ?? {}
            const stillLoggedIn = !!data.logged_in

            alertModal(
                data.immediate ? 'Account deleted' : 'Successfully deleted account.',
                data.immediate
                    ? 'Your account is being deleted now and your username has been released.'
                    : `Your account is now pending deletion in ${data.grace_days ?? 30} days, or until you log in again!`,
                [
                    {
                        text: 'Ok',
                        type: 'cancel',
                        callback: () => finish(stillLoggedIn)
                    }
                ]
            )
        })
        .catch((error) => {
            if (error.response && error.response.data && error.response.data.message) {
                alertModal('Error', error.response.data.message)
            } else {
                alertModal('Oops!', 'An unexpected error occured. Please try again.')
            }
        })
        .finally(() => {
            submitting.value = false
        })
}
</script>
