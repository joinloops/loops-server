<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isVisible"
                class="fixed inset-0 z-70 flex items-center justify-center p-4"
                @click="handleBackdropClick"
            >
                <div class="absolute inset-0 bg-black/50 dark:bg-black/70"></div>

                <Transition
                    enter-active-class="transition duration-300 ease-out transform"
                    enter-from-class="opacity-0 scale-95 translate-y-4"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-200 ease-in transform"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-4"
                >
                    <div
                        v-if="isVisible"
                        class="relative w-full max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-xl"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="titleId"
                        :aria-describedby="bodyId"
                        @click.stop
                    >
                        <button
                            v-if="persistModal"
                            @click="close"
                            class="absolute top-4 right-4 z-10 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                            aria-label="Close modal"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path
                                    fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </button>

                        <div class="px-6 pt-6 pb-4" v-if="title">
                            <h3
                                :id="titleId"
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                                :class="{ 'pr-8': persistModal }"
                            >
                                {{ title }}
                            </h3>
                        </div>

                        <div class="px-6 pb-6" v-if="body">
                            <p
                                :id="bodyId"
                                class="text-gray-600 dark:text-gray-300"
                                v-html="body"
                            ></p>
                        </div>

                        <div
                            class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-700/50 rounded-b-lg"
                        >
                            <button
                                v-for="(action, index) in actions"
                                :key="index"
                                :class="getButtonClasses(action.type)"
                                @click="handleAction(action)"
                                class="px-4 py-2 text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 cursor-pointer"
                                v-html="action.text"
                            ></button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'

const props = defineProps({
    title: {
        type: String,
        default: ''
    },
    body: {
        type: String,
        default: ''
    },
    actions: {
        type: Array,
        default: () => []
    },
    modelValue: {
        type: Boolean,
        default: false
    },
    closeOnBackdrop: {
        type: Boolean,
        default: true
    },
    persistModal: {
        type: Boolean,
        default: false
    },
    closeOnEscape: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['update:modelValue', 'close'])

const isVisible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const titleId = computed(() => `alert-modal-title-${Math.random().toString(36).substr(2, 9)}`)
const bodyId = computed(() => `alert-modal-body-${Math.random().toString(36).substr(2, 9)}`)

const getButtonClasses = (type) => {
    const baseClasses =
        'px-4 py-2 text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800'

    switch (type) {
        case 'danger':
            return `${baseClasses} bg-[#F02C56] hover:bg-[#F02C56]/80 text-white focus:ring-red-500`
        case 'primary':
            return `${baseClasses} bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500`
        case 'link':
            return `${baseClasses} text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 focus:ring-blue-500`
        case 'cancel':
        default:
            return `${baseClasses} bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:ring-blue-500`
    }
}

const handleAction = (action) => {
    if (action.callback && typeof action.callback === 'function') {
        action.callback()
    }
    close()
}

const handleBackdropClick = (event) => {
    if (props.closeOnBackdrop && !props.persistModal && event.target === event.currentTarget) {
        close()
    }
}

const close = () => {
    isVisible.value = false
    emit('close')
}

const handleEscape = (e) => {
    if (e.key === 'Escape' && isVisible.value && props.closeOnEscape && !props.persistModal) {
        close()
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>
