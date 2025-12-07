import { ref, createApp, h } from 'vue'
import AlertModal from '@/components/AlertModal.vue'

const isVisible = ref(false)
const modalProps = ref({})

let modalInstance = null

export const useAlertModal = () => {
    const alertModal = (title, body, actions = [], options = {}) => {
        return new Promise((resolve) => {
            const defaultOptions = {
                closeOnBackdrop: true,
                persistModal: false,
                closeOnEscape: true
            }

            const modalOptions = { ...defaultOptions, ...options }

            if (actions.length === 0) {
                actions = [
                    {
                        text: 'OK',
                        type: 'cancel',
                        callback: () => resolve(null)
                    }
                ]
            }

            const wrappedActions = actions.map((action, index) => ({
                ...action,
                callback: () => {
                    if (action.callback) action.callback()
                    resolve(index)
                }
            }))

            modalProps.value = {
                title,
                body,
                actions: wrappedActions,
                modelValue: true,
                ...modalOptions
            }

            if (!modalInstance) {
                const modalContainer = document.createElement('div')
                document.body.appendChild(modalContainer)

                modalInstance = createApp({
                    render() {
                        return h(AlertModal, {
                            ...modalProps.value,
                            'onUpdate:modelValue': (value) => {
                                modalProps.value.modelValue = value
                                if (!value) resolve(null)
                            },
                            onClose: () => resolve(null)
                        })
                    }
                })

                modalInstance.mount(modalContainer)
            }

            isVisible.value = true
        })
    }

    const confirmModal = (
        title,
        body,
        confirmText = 'Confirm',
        cancelText = 'Cancel',
        options = {}
    ) => {
        return new Promise((resolve) => {
            alertModal(
                title,
                body,
                [
                    {
                        text: cancelText,
                        type: 'cancel',
                        callback: () => resolve(false)
                    },
                    {
                        text: confirmText,
                        type: 'danger',
                        callback: () => resolve(true)
                    }
                ],
                options
            )
        })
    }

    const persistentModal = (title, body, actions = []) => {
        return alertModal(title, body, actions, {
            closeOnBackdrop: false,
            persistModal: true,
            closeOnEscape: false
        })
    }

    return {
        alertModal,
        confirmModal,
        persistentModal
    }
}

export default {
    install(app) {
        const { alertModal, confirmModal, persistentModal } = useAlertModal()

        app.config.globalProperties.$alertModal = alertModal
        app.config.globalProperties.$confirmModal = confirmModal
        app.config.globalProperties.$persistentModal = persistentModal
        app.provide('alertModal', alertModal)
        app.provide('confirmModal', confirmModal)
        app.provide('persistentModal', persistentModal)
    }
}
