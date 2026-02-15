<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
                <div class="flex min-h-full items-center justify-center p-4">
                    <Transition name="backdrop">
                        <div
                            v-if="isOpen"
                            class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"
                            @click="closeModal"
                        />
                    </Transition>

                    <Transition name="modal-content">
                        <div
                            v-if="isOpen"
                            class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 shadow-2xl transition-all"
                        >
                            <div class="flex items-center justify-center pt-8 pb-4">
                                <div
                                    class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30"
                                >
                                    <template v-if="!isHiding">
                                        <EyeSlashIcon
                                            v-if="modeHide"
                                            class="h-8 w-8 text-amber-600 dark:text-amber-400"
                                        />
                                        <EyeIcon
                                            v-else
                                            class="h-8 w-8 text-amber-600 dark:text-amber-400"
                                        />
                                    </template>
                                </div>
                            </div>

                            <div class="px-6 pb-6 pt-2 text-center">
                                <h3
                                    class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3"
                                >
                                    {{ modeHide ? $t('post.hideComment') : 'Unhide Comment' }}
                                </h3>
                                <p
                                    class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-2"
                                >
                                    {{
                                        modeHide
                                            ? $t('post.hideCommentDescription')
                                            : $t('post.unhideCommentDescription')
                                    }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-500">
                                    {{
                                        modeHide
                                            ? $t('post.hideCommentNote')
                                            : $t('post.unhideCommentNote')
                                    }}
                                </p>
                            </div>

                            <div class="px-6 pb-6 flex flex-col-reverse sm:flex-row gap-3">
                                <button
                                    @click="closeModal"
                                    class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl transition-colors"
                                    :disabled="isHiding"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                                <AnimatedButton
                                    @click="handleConfirm"
                                    :disabled="isHiding"
                                    :loading="isHiding"
                                    class="flex-1 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <div class="flex items-center justify-center gap-2">
                                        <template v-if="!isHiding">
                                            <EyeSlashIcon v-if="modeHide" class="w-4 h-4" />
                                            <EyeIcon v-else class="w-4 h-4" />
                                        </template>
                                        <span>{{
                                            isHiding
                                                ? $t('post.hidingDotDotDot')
                                                : modeHide
                                                  ? $t('post.hideComment')
                                                  : 'Unhide comment'
                                        }}</span>
                                    </div>
                                </AnimatedButton>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue'
import { useHideCommentModal } from '@/composables/useHideCommentModal'
import AnimatedButton from '@/components/AnimatedButton.vue'
import { EyeSlashIcon, EyeIcon } from '@heroicons/vue/24/outline'

const { isOpen, confirmHide, confirmUnhide, closeModal, modeHide } = useHideCommentModal()

const isHiding = ref(false)

const handleConfirm = async () => {
    isHiding.value = true
    if (modeHide.value) {
        await confirmHide()
    } else {
        await confirmUnhide()
    }
    isHiding.value = false
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.2s ease;
}

.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}

.modal-content-enter-active,
.modal-content-leave-active {
    transition: all 0.2s ease;
}

.modal-content-enter-from,
.modal-content-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(-10px);
}
</style>
