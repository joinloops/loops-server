import { ref, computed, inject } from 'vue'
import axios from '~/plugins/axios'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useI18n } from 'vue-i18n'

const CATEGORIES_WITH_TEXT_INPUT = [
    '1018', // Misinformation
    '1021', // Frauds and scams
    '1023', // Report illegal content
    '1026' // Other
]

const axiosInstance = axios.getAxiosInstance()

const isOpen = ref(false)
const currentStep = ref(1)
const isSubmitting = ref(false)

const reportType = ref('')
const reportContentId = ref('')
const reportRoutePath = ref('')
const selectedCategory = ref(null)
const additionalText = ref('')
const reportAccount = ref(null)
const isBlocking = ref(false)
const blockDone = ref(false)
const blockError = ref('')

export function useReportModal() {
    const { alertModal } = useAlertModal()
    const { t } = useI18n()
    const authStore = inject('authStore')

    const REPORT_CATEGORIES = computed(() => [
        {
            key: '1009',
            message: t('reports.types.1009', 'Untagged AI content')
        },
        {
            key: '1010',
            message: t('reports.types.1010', 'Inappropriate and irrelevant search')
        },
        {
            key: '1011',
            message: t('reports.types.1011', 'Violence, abuse, and criminal exploitation')
        },
        {
            key: '1012',
            message: t('reports.types.1012', 'Hate and harassment')
        },
        {
            key: '1013',
            message: t('reports.types.1013', 'Suicide and self-harm')
        },
        {
            key: '1014',
            message: t('reports.types.1014', 'Disordered eating and unhealthy body image')
        },
        {
            key: '1015',
            message: t('reports.types.1015', 'Dangerous activities and challenges')
        },
        {
            key: '1016',
            message: t('reports.types.1016', 'Nudity and sexual content')
        },
        {
            key: '1017',
            message: t('reports.types.1017', 'Shocking and graphic content')
        },
        { key: '1018', message: t('reports.types.1018', 'Misinformation') },
        {
            key: '1019',
            message: t('reports.types.1019', 'Deceptive behavior and spam')
        },
        {
            key: '1020',
            message: t('reports.types.1020', 'Regulated goods and activities')
        },
        { key: '1021', message: t('reports.types.1021', 'Frauds and scams') },
        {
            key: '1022',
            message: t('reports.types.1022', 'Sharing personal information')
        },
        {
            key: '1023',
            message: t('reports.types.1023', 'Report illegal content')
        },
        {
            key: '1024',
            message: t('reports.types.1024', 'Counterfeits and intellectual property')
        },
        {
            key: '1025',
            message: t('reports.types.1025', 'Undisclosed branded content')
        },
        { key: '1026', message: t('reports.types.1026', 'Other') }
    ])

    const requiresTextInput = computed(() => {
        return (
            selectedCategory.value &&
            CATEGORIES_WITH_TEXT_INPUT.includes(selectedCategory.value.key)
        )
    })

    const canProceedToNextStep = computed(() => {
        if (currentStep.value === 1) {
            return selectedCategory.value !== null
        }
        if (currentStep.value === 2) {
            return !requiresTextInput.value || additionalText.value.trim().length > 0
        }
        return false
    })

    function openReportModal(type, contentId, routePath, account = null) {
        resetReportModal()
        reportType.value = type
        reportContentId.value = contentId
        reportRoutePath.value = routePath
        reportAccount.value = account
        isOpen.value = true
    }

    function closeReportModal() {
        const shouldReload = blockDone.value
        isOpen.value = false
        resetReportModal()

        if (shouldReload) {
            window.location.reload()
        }
    }

    function resetReportModal() {
        currentStep.value = 1
        selectedCategory.value = null
        additionalText.value = ''
        isSubmitting.value = false
        isBlocking.value = false
        blockDone.value = false
        blockError.value = ''
    }

    const currentProfileId = computed(() => {
        const id = authStore?.user?.id
        return id == null ? null : String(id)
    })

    const canBlock = computed(() => {
        const id = reportAccount.value?.id
        if (!id || !currentProfileId.value) return false
        return String(id) !== currentProfileId.value
    })

    function goToNextReportStep() {
        if (canProceedToNextStep.value) {
            currentStep.value = 2
        }
    }

    function goToPreviousReportStep() {
        if (currentStep.value === 2) {
            currentStep.value = 1
        }
    }

    function selectCategory(category) {
        selectedCategory.value = category
    }

    async function blockAccount() {
        if (!canBlock.value || isBlocking.value || blockDone.value) return

        isBlocking.value = true
        blockError.value = ''

        try {
            await axiosInstance.post(`/api/v1/account/block/${reportAccount.value.id}`)
            blockDone.value = true
        } catch (error) {
            blockError.value = error.response?.data?.message
            console.error('Error blocking account:', error)
        } finally {
            isBlocking.value = false
        }
    }

    async function submitReport() {
        if (!canProceedToNextStep.value) return

        isSubmitting.value = true

        try {
            await axiosInstance.post('/api/v1/report', {
                type: reportType.value,
                id: reportContentId.value,
                key: selectedCategory.value.key,
                path: reportRoutePath.value,
                comment: additionalText.value || null
            })

            currentStep.value = 3
        } catch (error) {
            const msg = error.response?.data?.message
            closeReportModal()
            alertModal('Error', msg)
            console.error('Error submitting report:', error)
        } finally {
            isSubmitting.value = false
        }
    }

    return {
        isOpen,
        currentStep,
        isSubmitting,
        reportType,
        reportContentId,
        reportRoutePath,
        selectedCategory,
        additionalText,

        requiresTextInput,
        canProceedToNextStep,

        REPORT_CATEGORIES,
        reportAccount,
        isBlocking,
        blockDone,
        blockError,
        canBlock,
        blockAccount,

        openReportModal,
        closeReportModal,
        resetReportModal,
        goToNextReportStep,
        goToPreviousReportStep,
        selectCategory,
        submitReport
    }
}
