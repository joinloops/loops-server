<template>
    <div
        class="fixed flex justify-center pt-14 md:pt-[105px] z-50 top-0 left-0 w-full h-full bg-black/90"
    >
        <div
            class="relative bg-white dark:bg-gray-950 w-full max-w-[700px] mx-3 overflow-hidden rounded-lg mb-10 flex flex-col"
        >
            <div
                class="sticky top-0 z-10 flex items-center justify-between w-full p-5 border-b border-b-gray-300 dark:border-b-slate-800 bg-white dark:bg-slate-900"
            >
                <div class="text-[22px] font-medium dark:text-slate-400">
                    {{ t('profile.editProfile') }}
                </div>
                <button
                    @click="closeEditProfile"
                    :disabled="isSaving"
                    class="cursor-pointer text-gray-600 hover:text-gray-400 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <XMarkIcon class="size-6" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                <div class="p-4">
                    <div
                        v-if="error"
                        class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md"
                    >
                        <p class="text-sm text-red-600 dark:text-red-400">
                            {{ error }}
                        </p>
                    </div>

                    <div v-if="!uploadedImage">
                        <div
                            class="flex flex-col border-b border-gray-100 dark:border-b-slate-800 px-1.5 py-2 w-full"
                        >
                            <div
                                class="font-semibold text-[15px] sm:mb-0 mb-1 text-gray-700 sm:w-[160px] sm:text-left dark:text-gray-400"
                            >
                                {{ t('common.avatar') }}
                            </div>

                            <div class="flex items-center justify-center sm:-mt-6">
                                <div class="relative">
                                    <label for="image" class="relative cursor-pointer">
                                        <img
                                            class="rounded-full"
                                            width="95"
                                            height="95"
                                            :src="userImage"
                                            :alt="`${userName || 'User'}'s avatar`"
                                            onerror="
                                                this.src = '/storage/avatars/default.jpg'
                                                this.onerror = null
                                            "
                                        />
                                        <div
                                            class="absolute bottom-0 right-0 rounded-full bg-white shadow-xl border p-2 border-gray-300 inline-flex items-center justify-center"
                                        >
                                            <PaintBrushIcon class="size-4" />
                                        </div>
                                    </label>
                                    <input
                                        class="hidden"
                                        type="file"
                                        id="image"
                                        @input="getUploadedImage"
                                        accept="image/png, image/jpeg, image/jpg"
                                        :disabled="isSaving"
                                    />

                                    <button
                                        v-if="canDeleteAvatar"
                                        @click="deleteAvatar"
                                        :disabled="isSaving"
                                        class="absolute -top-2 -left-2 rounded-full bg-red-500 hover:bg-red-600 text-white p-1.5 shadow-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        title="Delete avatar"
                                    >
                                        <TrashIcon class="size-3" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex flex-col border-b border-gray-100 dark:border-b-slate-800 px-1.5 py-2 mt-1.5 w-full"
                        >
                            <div
                                class="font-semibold text-[15px] sm:mb-0 mb-1 text-gray-700 sm:w-[160px] sm:text-left text-center dark:text-gray-400"
                            >
                                {{ t('profile.displayName') }}
                            </div>

                            <div class="flex items-center justify-center sm:-mt-6">
                                <div class="sm:w-[60%] w-full max-w-md">
                                    <TextInput
                                        :placeholder="t('profile.displayName')"
                                        v-model="userName"
                                        input-type="text"
                                        :max="30"
                                        :disabled="isSaving"
                                    />
                                    <div class="flex justify-between mt-2">
                                        <div class="text-[11px] text-gray-500">
                                            {{ t('profile.displayNameHelp') }}
                                        </div>
                                        <div
                                            v-if="userName"
                                            class="text-[11px]"
                                            :class="
                                                userName.length >= 25
                                                    ? 'text-orange-500'
                                                    : 'text-gray-500'
                                            "
                                        >
                                            {{ userName.length }}/30
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col px-1.5 py-2 mt-2 w-full">
                            <div
                                class="font-semibold text-sm sm:mb-0 mb-1 text-gray-700 sm:text-left dark:text-gray-400"
                            >
                                {{ t('profile.bio') }}
                            </div>

                            <div class="flex items-center justify-center sm:-mt-6">
                                <div class="sm:w-[60%] w-full max-w-md">
                                    <textarea
                                        cols="30"
                                        rows="4"
                                        v-model="userBio"
                                        maxlength="250"
                                        :placeholder="t('profile.bioPlaceholder')"
                                        :disabled="isSaving"
                                        class="resize-none w-full bg-[#F1F1F2] dark:bg-slate-900 dark:text-slate-50 text-gray-800 border dark:border-slate-800 border-gray-300 rounded-md py-2.5 px-3 focus:outline-none focus:ring-2 focus:ring-[#F02C56] focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                                    ></textarea>
                                    <div class="flex justify-between mt-1">
                                        <div class="text-[11px] text-gray-500">
                                            {{ t('profile.bioHelp') }}
                                        </div>
                                        <div
                                            v-if="userBio"
                                            class="text-[11px]"
                                            :class="
                                                userBio.length >= 70
                                                    ? 'text-orange-500'
                                                    : 'text-gray-500'
                                            "
                                        >
                                            {{ userBio.length }}/250
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="w-full h-[430px]">
                        <Cropper
                            class="h-[430px]"
                            ref="cropper"
                            :stencil-component="CircleStencil"
                            :src="uploadedImage"
                        />
                    </div>
                </div>
            </div>

            <div
                class="sticky bottom-0 p-5 border-t border-t-gray-300 dark:border-t-slate-800 w-full bg-white dark:bg-slate-900"
            >
                <div
                    id="UpdateInfoButtons"
                    v-if="!uploadedImage"
                    class="flex items-center justify-end"
                >
                    <button
                        @click="closeEditProfile"
                        :disabled="isSaving"
                        class="flex items-center border rounded-md px-3 py-[6px] hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="px-2 font-medium text-[15px] dark:text-slate-400">{{
                            t('common.cancel')
                        }}</span>
                    </button>

                    <button
                        :disabled="!isUpdated || isSaving"
                        @click="updateUserInfo"
                        class="flex items-center text-white border rounded-md ml-3 px-3 py-[6px] transition-all duration-200 disabled:cursor-not-allowed min-w-[80px]"
                        :class="
                            !isUpdated || isSaving
                                ? 'bg-gray-300 dark:bg-slate-700 border-gray-300 dark:border-slate-700 text-gray-500 dark:text-slate-500'
                                : 'bg-[#F02C56] border-red-400 hover:bg-red-600'
                        "
                    >
                        <span v-if="isSaving" class="flex items-center">
                            <svg
                                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
                            {{ t('common.savingDotDotDot') }}
                        </span>
                        <span v-else class="mx-4 font-medium text-[15px]">{{
                            t('common.save')
                        }}</span>
                    </button>
                </div>

                <div id="CropperButtons" v-else class="flex items-center justify-end">
                    <button
                        @click="cancelImageUpload"
                        :disabled="isSaving"
                        class="flex items-center border rounded-md px-3 py-[6px] hover:bg-gray-100 dark:border-slate-700 dark:hover:bg-slate-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="px-2 font-medium text-[15px] dark:text-slate-400">{{
                            t('common.cancel')
                        }}</span>
                    </button>

                    <button
                        @click="cropAndUpdateImage"
                        :disabled="isSaving"
                        class="flex items-center bg-[#F02C56] text-white border border-red-400 rounded-md ml-3 px-3 py-[6px] hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed min-w-[80px]"
                    >
                        <span v-if="isSaving" class="flex items-center">
                            <svg
                                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
                            {{ t('profile.applyingDotDotDot') }}
                        </span>
                        <span v-else class="mx-4 font-medium text-[15px]">{{
                            t('common.apply')
                        }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { Cropper, CircleStencil } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'
import TextInput from '@/components/Form/TextInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useProfileStore } from '@/stores/profile'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { XMarkIcon, PaintBrushIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useI18n } from 'vue-i18n'

const emit = defineEmits(['updated', 'close'])
const { alertModal, confirmModal } = useAlertModal()

const authStore = useAuthStore()
const profileStore = useProfileStore()

const route = useRoute()

const { t } = useI18n()
const file = ref(null)
const cropper = ref(null)
const uploadedImage = ref(null)
const userImage = ref(null)
const userName = ref(null)
const userBio = ref(null)
const isSaving = ref(false)
const error = ref(null)

const originalName = ref(null)
const originalBio = ref(null)
const originalImage = ref(null)

const isUpdated = computed(() => {
    if (isSaving.value) return false

    const nameChanged = userName.value !== originalName.value
    const bioChanged = userBio.value !== originalBio.value

    return nameChanged || bioChanged
})

const canDeleteAvatar = computed(() => {
    return userImage.value && !userImage.value.endsWith('default.jpg')
})

const getUploadedImage = (e) => {
    const selectedFile = e.target.files[0]
    if (!selectedFile) return

    const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg']
    if (!allowedTypes.includes(selectedFile.type)) {
        error.value = t('profile.avatarFileTypeError')
        return
    }

    const maxSize = 2 * 1024 * 1024
    if (selectedFile.size > maxSize) {
        error.value = t('profile.avatarFileSizeError')
        return
    }

    error.value = null
    file.value = selectedFile
    uploadedImage.value = URL.createObjectURL(selectedFile)
}

const cropAndUpdateImage = async () => {
    if (!cropper.value || isSaving.value) return

    isSaving.value = true
    error.value = null

    try {
        const { coordinates } = cropper.value.getResult()
        const data = new FormData()
        data.append('avatar', file.value || '')

        await authStore.updateAvatar(data)
        await authStore.checkAuth()
        await profileStore.getProfileAndPosts(authStore.getUser.username)

        userImage.value = authStore.getUser.avatar
        originalImage.value = authStore.getUser.avatar
        uploadedImage.value = null
        file.value = null

        const fileInput = document.getElementById('image')
        if (fileInput) fileInput.value = ''
    } catch (err) {
        console.error('Error updating avatar:', err)
        error.value = t('profile.avatarFailedToUploadError')
    } finally {
        isSaving.value = false
    }
}

const deleteAvatar = async () => {
    if (!canDeleteAvatar.value || isSaving.value) return

    const result = await confirmModal(
        t('profile.deleteAvatar'),
        t('profile.deleteAvatarConfirmMessage'),
        t('profile.delete'),
        t('common.cancel')
    )

    if (!result) return

    isSaving.value = true
    error.value = null

    try {
        await authStore.deleteAvatar()
        await authStore.checkAuth()
        await profileStore.getProfileAndPosts(authStore.getUser.username)

        userImage.value = '/storage/avatars/default.jpg'
        originalImage.value = '/storage/avatars/default.jpg'
    } catch (err) {
        console.error('Error deleting avatar:', err)
        error.value = t('profile.deleteAvatarFailedMessage')
    } finally {
        isSaving.value = false
    }
}

const updateUserInfo = async () => {
    if (!isUpdated.value || isSaving.value) return

    isSaving.value = true
    error.value = null

    try {
        await authStore.updateBio(userName.value?.trim(), userBio.value?.trim())
        await authStore.checkAuth()
        await profileStore.getProfileAndPosts(authStore.getUser.username)

        originalName.value = authStore.getUser.name
        originalBio.value = authStore.getUser.bio
        userName.value = authStore.getUser.name
        userBio.value = authStore.getUser.bio

        setTimeout(() => {
            closeEditProfile()
        }, 300)
    } catch (err) {
        console.error('Error updating profile:', err)
        error.value = t('profile.failedToUpdateProfileErrorMessage')
    } finally {
        isSaving.value = false
    }
}

const closeEditProfile = () => {
    if (isSaving.value) return
    emit('updated', true)
}

const cancelImageUpload = () => {
    if (isSaving.value) return

    uploadedImage.value = null
    file.value = null
    error.value = null

    const fileInput = document.getElementById('image')
    if (fileInput) fileInput.value = ''
}

watch([userName, userBio], () => {
    if (error.value) {
        error.value = null
    }
})

onMounted(() => {
    const user = authStore.getUser

    userName.value = user.name
    userBio.value = user.bio
    userImage.value = user.avatar

    originalName.value = user.name
    originalBio.value = user.bio
    originalImage.value = user.avatar
})
</script>
