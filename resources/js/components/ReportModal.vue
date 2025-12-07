<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                @click="closeReportModal"
            >
                <Transition
                    enter-active-class="transition-all duration-300"
                    enter-from-class="opacity-0 scale-95 translate-y-4"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition-all duration-300"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-4"
                >
                    <div
                        v-if="isOpen"
                        class="w-full max-w-md mx-4 bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden"
                        @click.stop
                    >
                        <div
                            class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700"
                        >
                            <div class="flex items-center space-x-3">
                                <button
                                    v-if="currentStep > 1"
                                    @click="goToPreviousReportStep"
                                    class="p-1 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"
                                >
                                    <svg
                                        class="w-5 h-5"
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
                                </button>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                                    {{ t('common.report') }}
                                </h2>
                            </div>
                            <button
                                @click="closeReportModal"
                                class="p-1 text-gray-600 dark:text-gray-400 hover:text-gray-400 dark:hover:text-gray-200 transition-colors cursor-pointer"
                            >
                                <i class="bx bx-x text-[29px]" />
                            </button>
                        </div>

                        <div class="p-6">
                            <div v-if="currentStep === 1">
                                <p class="text-xs text-gray-600 dark:text-gray-500 mb-2">
                                    {{ t('post.pleaseSelectAScenario') }}
                                </p>

                                <div
                                    class="max-h-80 overflow-y-scroll border border-gray-200 dark:border-gray-700 dark:bg-gray-700 divide-y-1 divide-gray-200 dark:divide-gray-600 rounded-lg"
                                >
                                    <button
                                        v-for="category in REPORT_CATEGORIES"
                                        :key="category.key"
                                        @click="selectCategory(category)"
                                        class="w-full flex items-center justify-between p-3 text-sm text-left text-gray-900 dark:text-gray-300 dark:hover:text-white transition-colors group cursor-pointer"
                                        :class="{
                                            'bg-red-100 text-red-500 dark:bg-gray-900':
                                                selectedCategory?.key === category.key
                                        }"
                                    >
                                        <span class="font-medium">{{ category.message }}</span>
                                        <svg
                                            class="w-5 h-5 text-gray-400 dark:text-gray-500 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-colors"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </button>
                                </div>

                                <div class="mt-6 flex justify-end">
                                    <button
                                        @click="goToNextReportStep"
                                        :disabled="!canProceedToNextStep"
                                        class="px-6 py-3 bg-[#F02C56] text-white font-medium rounded-lg hover:bg-[#F02C56]/80 disabled:bg-gray-300 dark:disabled:bg-gray-600 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                    >
                                        {{ t('common.next') }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="currentStep === 2">
                                <div class="mb-6">
                                    <h3
                                        class="text-lg font-medium text-gray-900 dark:text-white mb-2"
                                    >
                                        {{ selectedCategory?.message }}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{
                                            t('post.yourReportingThis', {
                                                type: reportType,
                                                message: selectedCategory?.message
                                            })
                                        }}
                                    </p>
                                </div>

                                <div v-if="requiresTextInput" class="mb-6">
                                    <label
                                        for="additional-details"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                    >
                                        {{ t('post.additionalDetails') }}
                                        <span
                                            v-if="selectedCategory?.key === '1026'"
                                            class="text-red-500"
                                            >*</span
                                        >
                                    </label>
                                    <textarea
                                        id="additional-details"
                                        v-model="additionalText"
                                        :placeholder="getTextareaPlaceholder()"
                                        rows="4"
                                        maxlength="500"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 resize-none"
                                    ></textarea>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ additionalText.length }}/500
                                        {{ t('post.characters') }}
                                    </p>
                                </div>

                                <div class="flex justify-end space-x-3">
                                    <button
                                        v-if="!isSubmitting"
                                        @click="closeReportModal"
                                        class="px-6 py-3 text-gray-700 dark:text-gray-500 font-medium rounded-lg hover:text-gray-600 dark:hover:text-gray-300 transition-colors cursor-pointer"
                                    >
                                        {{ t('common.cancel') }}
                                    </button>
                                    <button
                                        v-if="!isSubmitting"
                                        @click="goToPreviousReportStep"
                                        class="px-6 py-3 text-gray-700 border dark:border-gray-700/50 dark:text-gray-500 font-medium rounded-lg hover:bg-gray-100 dark:hover:text-gray-100 dark:hover:bg-gray-900/20 transition-colors cursor-pointer"
                                    >
                                        {{ t('post.goBack') }}
                                    </button>
                                    <button
                                        @click="submitReport"
                                        :disabled="!canProceedToNextStep || isSubmitting"
                                        class="px-6 py-3 bg-[#F02C56] text-white font-medium rounded-lg hover:bg-[#F02C56]/80 disabled:bg-gray-300 dark:disabled:bg-gray-600 disabled:cursor-not-allowed transition-colors flex items-center cursor-pointer space-x-2"
                                    >
                                        <span v-if="isSubmitting">
                                            <div
                                                class="w-6 h-6 rounded-full border-4 border-gray-200 border-t-[#F02C56] animate-spin"
                                            ></div>
                                        </span>
                                        <span>{{
                                            isSubmitting
                                                ? t('post.submittingDotDotDot')
                                                : t('post.submit')
                                        }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { useReportModal } from '@/composables/useReportModal'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const {
    isOpen,
    currentStep,
    isSubmitting,
    reportType,
    selectedCategory,
    additionalText,
    requiresTextInput,
    canProceedToNextStep,
    REPORT_CATEGORIES,
    closeReportModal,
    goToNextReportStep,
    goToPreviousReportStep,
    selectCategory,
    submitReport
} = useReportModal()

function getTextareaPlaceholder() {
    if (!selectedCategory.value) return ''

    switch (selectedCategory.value.key) {
        case '1018':
            return t('post.reportTextPlaceholder1018')
        case '1021':
            return t('post.reportTextPlaceholder1021')
        case '1023':
            return t('post.reportTextPlaceholder1023')
        case '1026':
            return t('post.reportTextPlaceholder1026')
        default:
            return t('post.reportTextPlaceholderDefault')
    }
}
</script>
