import { ref } from 'vue'

export function useLanguagePicker() {
    const isLanguagePickerOpen = ref(false)

    const openLanguagePicker = () => {
        isLanguagePickerOpen.value = true
    }

    const closeLanguagePicker = () => {
        isLanguagePickerOpen.value = false
    }

    return {
        isLanguagePickerOpen,
        openLanguagePicker,
        closeLanguagePicker
    }
}
