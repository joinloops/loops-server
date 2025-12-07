<template>
    <TransitionRoot appear :show="isOpen" as="template">
        <Dialog as="div" @close="closeModal" class="relative z-50">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black/25 dark:bg-black/50" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel
                            class="w-full max-w-md transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all"
                        >
                            <DialogTitle
                                as="h3"
                                class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4"
                            >
                                {{
                                    isEditMode
                                        ? $t('studio.editPlaylist')
                                        : $t('studio.createNewPlaylist')
                                }}
                            </DialogTitle>

                            <form @submit.prevent="handleSubmit" class="space-y-4">
                                <div>
                                    <label
                                        for="name"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                    >
                                        {{ $t('studio.playlistName') }} *
                                    </label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        maxlength="30"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                        :placeholder="$t('studio.myAwesomePlaylist')"
                                    />
                                    <div class="flex justify-end mt-1">
                                        <div
                                            class="text-xs"
                                            :class="[
                                                form.name.length > 30
                                                    ? 'text-red-500'
                                                    : 'text-gray-400 dark:text-gray-500'
                                            ]"
                                        >
                                            {{ form.name.length }} / 30
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        for="description"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                    >
                                        {{ $t('studio.description') }}
                                    </label>
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        rows="3"
                                        maxlength="1000"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                        :placeholder="$t('studio.describeYourPlaylistDotDotDot')"
                                    ></textarea>
                                    <div class="flex justify-end">
                                        <div
                                            class="text-xs"
                                            :class="[
                                                form.description.length > 1000
                                                    ? 'text-red-500'
                                                    : 'text-gray-400 dark:text-gray-500'
                                            ]"
                                        >
                                            {{ form.description.length }} / 1000
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        for="visibility"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                    >
                                        {{ $t('studio.visibility') }} *
                                    </label>
                                    <select
                                        id="visibility"
                                        v-model="form.visibility"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                    >
                                        <option value="public">
                                            {{ $t('studio.statusPublic') }}
                                        </option>
                                        <option value="unlisted">
                                            {{ $t('studio.statusUnlisted') }}
                                        </option>
                                        <option value="followers">
                                            {{ $t('studio.statusFollowers') }}
                                        </option>
                                        <option value="private">
                                            {{ $t('studio.statusPrivate') }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="error"
                                    class="p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-md text-sm"
                                >
                                    {{ error }}
                                </div>

                                <div class="flex justify-between items-center pt-4">
                                    <button
                                        v-if="isEditMode && playlist"
                                        type="button"
                                        @click="handleDelete"
                                        :disabled="loading"
                                        class="px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:text-white hover:bg-red-600 dark:hover:bg-red-500 rounded-md disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                                    >
                                        {{ $t('studio.deletePlaylist') }}
                                    </button>
                                    <div v-else></div>

                                    <div class="flex space-x-3">
                                        <button
                                            type="button"
                                            @click="closeModal"
                                            :disabled="loading"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-md disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                                        >
                                            {{ $t('common.cancel') }}
                                        </button>
                                        <AnimatedButton
                                            type="submit"
                                            :disabled="loading || !isFormValid"
                                        >
                                            {{
                                                loading
                                                    ? $t('common.saving')
                                                    : isEditMode
                                                      ? $t('post.saveChanges')
                                                      : $t('studio.createPlaylist')
                                            }}
                                        </AnimatedButton>
                                    </div>
                                </div>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import { useI18n } from 'vue-i18n'
import { useAlertModal } from '@/composables/useAlertModal.js'
import AnimatedButton from '../AnimatedButton.vue'

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    playlist: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close', 'save', 'delete'])

const { t } = useI18n()
const { alertModal, confirmModal } = useAlertModal()
const form = ref({
    name: '',
    description: '',
    visibility: 'public'
})

const loading = ref(false)
const error = ref(null)

const isEditMode = computed(() => !!props.playlist)

const isFormValid = computed(() => {
    const nameLength = form.value.name.trim().length
    return nameLength >= 1 && nameLength <= 30
})

watch(
    () => props.playlist,
    (newPlaylist) => {
        if (newPlaylist) {
            form.value = {
                name: newPlaylist.name,
                description: newPlaylist.description || '',
                visibility: newPlaylist.visibility
            }
        }
    },
    { immediate: true }
)

watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen && props.playlist) {
            form.value = {
                name: props.playlist.name,
                description: props.playlist.description || '',
                visibility: props.playlist.visibility
            }
        }

        if (!isOpen) {
            error.value = null
        }
    }
)

const resetForm = () => {
    form.value = {
        name: '',
        description: '',
        visibility: 'public'
    }
    error.value = null
}

const closeModal = () => {
    if (!loading.value) {
        emit('close')
        resetForm()
    }
}

const handleSubmit = async () => {
    if (!isFormValid.value) return

    loading.value = true
    error.value = null

    try {
        emit('save', { ...form.value, id: props.playlist?.id })
    } catch (err) {
        error.value = err.message || 'An error occurred'
    } finally {
        loading.value = false
    }
}

const handleDelete = async () => {
    const result = await confirmModal(
        t('common.delete'),
        t('studio.areYouSureYouWantToDeleteThisPlaylist'),
        t('common.delete'),
        t('common.cancel')
    )

    if (result) {
        emit('delete', props.playlist.id)
        closeModal()
    }
}
</script>
