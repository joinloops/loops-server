<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
        @click.self="closeModal"
    >
        <div
            class="bg-white dark:bg-slate-900 rounded-lg shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto"
        >
            <div
                class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-slate-700"
            >
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ $t('post.editVideo') }}
                </h2>
                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <XMarkIcon class="h-6 w-6" />
                </button>
            </div>

            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ $t('post.caption') }}
                    </label>
                    <textarea
                        v-model="formData.caption"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#F02C56] focus:border-[#F02C56] dark:bg-slate-800 dark:text-white resize-none"
                        rows="4"
                        :placeholder="$t('post.writeYourCaptionDotDotDot')"
                        maxlength="500"
                    />
                    <div class="text-right text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ formData.caption.length }}/240
                    </div>
                </div>

                <div>
                    <AnimatedButton
                        @click="showAltText = !showAltText"
                        type="button"
                        variant="light"
                        class="flex w-full"
                    >
                        <div class="flex items-center gap-2 text-sm font-medium">
                            <div>
                                <svg
                                    :class="{ 'rotate-90': showAltText }"
                                    class="h-4 w-4 transition-transform duration-200"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>
                            </div>
                            <div>
                                {{ $t('studio.editAltText') }}
                            </div>
                        </div>
                    </AnimatedButton>

                    <div v-if="showAltText" class="mt-3 space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t('studio.altText') }}
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $t('studio.altTextHelp') }}
                        </p>
                        <textarea
                            v-model="formData.altText"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#F02C56] focus:border-[#F02C56] dark:bg-slate-800 dark:text-white resize-none"
                            rows="3"
                            :placeholder="$t('studio.describeYourVideoDotDotDotAltText')"
                            maxlength="2000"
                        />
                        <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                            {{ formData.altText.length }}/2000
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('post.pinToProfile') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('post.showThisVideoAtTheTopOfYourProfile') }}
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.pinToProfile" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('post.commentsEnabled') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('post.allowPeopleToCommentOnThisVideo') }}
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.commentsEnabled" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('post.downloadsEnabled') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('post.allowPeopleToDownloadThisVideo') }}
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.downloadsEnabled" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('studio.duet') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('studio.duetMessage') }}
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.allowDuets" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('studio.stitch') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('studio.stitchMessage') }}
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.allowStitches" />
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ $t('studio.language') }}
                    </label>
                    <div class="relative">
                        <select
                            v-model="formData.lang"
                            class="block w-full px-4 py-2 pr-8 text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                            <option value="" disabled selected>
                                {{ $t('studio.selectLanguage') }}
                            </option>

                            <option v-for="lang in languages" :key="lang.code" :value="lang.code">
                                {{ lang.name }}
                            </option>
                        </select>
                        <div
                            class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none"
                        >
                            <svg
                                class="w-5 h-5 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        {{ $t('studio.selectLanguageHelp') }}
                    </p>
                </div>

                <div class="space-y-4 pt-4 border-t border-gray-200 dark:border-slate-700">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('studio.containsNSFW') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('studio.containsNSFWMessage') }}
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.isNsfw" :disabled="formData.isNsfw" />
                    </div>

                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('studio.disclosePostContent') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('studio.disclosePostContentHelp') }}
                            </p>
                        </div>
                        <ToggleSwitch
                            v-model="formData.containsAds"
                            :disabled="formData.containsAds"
                        />
                    </div>

                    <div class="flex items-start justify-between gap-5">
                        <div class="flex-1">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('studio.containsAlteredContent') }}
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('studio.containsAlteredContentHelp') }}
                            </p>
                        </div>
                        <ToggleSwitch
                            v-model="formData.containsAiContent"
                            :disabled="formData.containsAiContent"
                        />
                    </div>
                </div>

                <div class="border-t border-gray-200 dark:border-slate-700 pt-6">
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                        <div class="flex items-start">
                            <ExclamationTriangleIcon class="h-5 w-5 text-red-400 mt-0.5 mr-3" />
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-400">
                                    {{ $t('post.deleteVideo') }}
                                </h3>
                                <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                    {{ $t('post.thisActionCannotBeUndone') }}
                                </p>
                                <button
                                    @click="confirmDelete"
                                    class="mt-3 bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 px-4 rounded-md transition-colors duration-200"
                                >
                                    {{ $t('post.deleteVideo') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 rounded-b-lg"
            >
                <button
                    @click="closeModal"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200 cursor-pointer"
                >
                    {{ $t('common.cancel') }}
                </button>
                <button
                    @click="saveChanges"
                    :disabled="isSaving"
                    class="px-4 py-2 text-sm font-medium text-white bg-[#F02C56] hover:bg-red-600 disabled:bg-red-300 disabled:cursor-not-allowed rounded-md transition-colors duration-200 flex items-center cursor-pointer"
                >
                    <Spinner v-if="isSaving" size="sm" class="mr-2" />
                    {{ isSaving ? $t('common.savingDotDotDot') : $t('post.saveChanges') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, inject } from 'vue'
import { storeToRefs } from 'pinia'
import { XMarkIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { useAlertModal } from '@/composables/useAlertModal.js'
const appStore = inject('appStore')
const { languages } = storeToRefs(appStore)

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    video: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close', 'save', 'delete'])
const { alertModal, confirmModal } = useAlertModal()

const isSaving = ref(false)
const showAltText = ref(false)

const formData = ref({
    caption: '',
    altText: '',
    lang: '',
    pinToProfile: false,
    commentsEnabled: true,
    downloadsEnabled: true,
    allowDuets: true,
    allowStitches: true,
    isNsfw: false,
    containsAds: false,
    containsAiContent: false
})

onMounted(async () => {
    await appStore.ensureLanguages()
})

watch(
    () => [props.isOpen, props.video],
    ([isOpen, video]) => {
        if (isOpen && video) {
            formData.value = {
                caption: video.caption || '',
                altText: video.media.alt_text || '',
                pinToProfile: video.pinned || false,
                lang: video.lang || '',
                downloadsEnabled: video.permissions.can_download || false,
                commentsEnabled: video.permissions.can_comment !== false,
                allowDuets: video.permissions.can_duet !== false,
                allowStitches: video.permissions.can_stitch !== false,
                isNsfw: video.is_sensitive || false,
                containsAds: video.meta.contains_ad || false,
                containsAiContent: video.meta.contains_ai || false
            }
            showAltText.value = !!video.alt_text
        }
    },
    { immediate: true }
)

const closeModal = () => {
    emit('close')
}

const saveChanges = async () => {
    if (!props.video) return

    isSaving.value = true

    try {
        await emit('save', {
            id: props.video.id,
            caption: formData.value.caption,
            alt_text: formData.value.altText,
            is_pinned: formData.value.pinToProfile,
            lang: formData.value.lang,
            can_comment: formData.value.commentsEnabled,
            can_download: formData.value.downloadsEnabled,
            can_duet: formData.value.allowDuets,
            can_stitch: formData.value.allowStitches,
            is_sensitive: formData.value.isNsfw,
            contains_ad: formData.value.containsAds,
            contains_ai: formData.value.containsAiContent
        })
    } catch (error) {
        console.error('Error saving video:', error)
    } finally {
        isSaving.value = false
        closeModal()
    }
}

const confirmDelete = async () => {
    const confirmed = await confirmModal(
        'Confirm Delete',
        `Are you sure you want to delete this video? All likes, comments and other interactions will be lost forever.<br /><br /><strong class="text-red-500">This action cannot be undone.</strong>`,
        'Delete',
        'Cancel'
    )
    if (confirmed) {
        emit('delete', props.video.id)
        closeModal()
    } else {
        closeModal()
    }
}
</script>
