<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                @click="handleBackdropClick"
            >
                <div class="fixed inset-0 bg-black/50 dark:bg-black/70"></div>

                <div class="flex min-h-full items-center justify-center p-4">
                    <Transition
                        enter-active-class="duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="isOpen"
                            class="relative w-full max-w-md transform rounded-lg bg-white p-6 shadow-xl transition-all dark:bg-gray-800"
                            @click.stop
                        >
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ t('language.picker.title') || 'Choose Language' }}
                                </h3>
                                <button
                                    @click="closeModal"
                                    class="rounded-md p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-300 transition-colors"
                                    aria-label="Close"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                                <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">
                                    {{ t('language.picker.current') }}
                                </p>
                                <div class="flex items-center space-x-2">
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ currentLanguage.nativeName }}
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        ({{ currentLanguage.name }})
                                    </span>
                                </div>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">
                                {{ t('language.picker.description') }}
                            </p>

                            <div class="mb-6">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{ t('language.picker.select') }}
                                </label>
                                <select
                                    v-model="selectedLocale"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-blue-400 dark:focus:ring-blue-400"
                                >
                                    <option
                                        v-for="language in availableLanguages"
                                        :key="language.code"
                                        :value="language.code"
                                        class="flex items-center"
                                    >
                                        {{ language.nativeName }} ({{ language.name }})
                                    </option>
                                </select>
                            </div>

                            <div class="flex space-x-3">
                                <button
                                    @click="changeLanguage"
                                    :disabled="selectedLocale === $i18n.locale"
                                    class="flex-1 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors dark:focus:ring-offset-gray-800"
                                >
                                    {{ t('language.picker.applyChanges') }}
                                </button>
                                <button
                                    @click="closeModal"
                                    class="flex-1 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition-colors dark:focus:ring-offset-gray-800"
                                >
                                    {{ t('common.cancel') }}
                                </button>
                            </div>

                            <p class="mt-4 text-xs text-center text-gray-500 dark:text-gray-400">
                                {{ t('language.picker.note') }}
                            </p>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close'])

const { locale, availableLocales, t } = useI18n()

const selectedLocale = ref(locale.value)

const languageInfo = {
    en: { name: 'English', nativeName: 'English' },
    es: { name: 'Spanish', nativeName: 'Español' },
    fr: { name: 'French', nativeName: 'Français' },
    de: { name: 'German', nativeName: 'Deutsch' },
    it: { name: 'Italian', nativeName: 'Italiano' },
    pt: { name: 'Portuguese', nativeName: 'Português' },
    ja: { name: 'Japanese', nativeName: '日本語' },
    ko: { name: 'Korean', nativeName: '한국어' },
    zh: { name: 'Chinese', nativeName: '中文' },
    ar: { name: 'Arabic', nativeName: 'العربية' },
    ru: { name: 'Russian', nativeName: 'Русский' },
    hi: { name: 'Hindi', nativeName: 'हिन्दी' }
}

const availableLanguages = computed(() => {
    return availableLocales
        .filter((locale) => languageInfo[locale])
        .map((locale) => ({
            code: locale,
            ...languageInfo[locale]
        }))
})

const currentLanguage = computed(() => {
    return (
        languageInfo[locale.value] || {
            name: locale.value,
            flag: '🌐',
            nativeName: locale.value
        }
    )
})

const closeModal = () => {
    emit('close')
}

const changeLanguage = () => {
    if (selectedLocale.value !== locale.value) {
        locale.value = selectedLocale.value

        localStorage.setItem('preferred-language', selectedLocale.value)

        document.documentElement.lang = selectedLocale.value

        window.dispatchEvent(
            new CustomEvent('language-changed', {
                detail: { locale: selectedLocale.value }
            })
        )
    }
    closeModal()
}

const handleEscape = (e) => {
    if (e.key === 'Escape' && props.isOpen) {
        closeModal()
    }
}

const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) {
        closeModal()
    }
}

onMounted(() => {
    const savedLanguage = localStorage.getItem('preferred-language')
    if (savedLanguage && availableLocales.includes(savedLanguage)) {
        locale.value = savedLanguage
        selectedLocale.value = savedLanguage
        document.documentElement.lang = savedLanguage
    }

    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>
