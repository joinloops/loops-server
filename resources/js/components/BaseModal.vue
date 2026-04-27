<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
            >
                <div
                    class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
                    @click="handleBackdropClick"
                ></div>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2"
                    appear
                >
                    <div
                        v-if="modelValue"
                        ref="panelRef"
                        :class="[
                            'relative w-full overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10',
                            maxWidthClass
                        ]"
                        tabindex="-1"
                        @keydown.esc.stop="handleEscape"
                    >
                        <div
                            v-if="title || $slots.header || !hideClose"
                            class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                        >
                            <div class="min-w-0 flex-1">
                                <slot name="header">
                                    <h2
                                        v-if="title"
                                        :id="titleId"
                                        class="text-base font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{ title }}
                                    </h2>
                                    <p
                                        v-if="description"
                                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ description }}
                                    </p>
                                </slot>
                            </div>
                            <button
                                v-if="!hideClose"
                                type="button"
                                @click="close"
                                class="-mr-1 -mt-1 shrink-0 rounded-md p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                aria-label="Close"
                            >
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <div :class="['px-5 py-4', bodyClass]">
                            <slot />
                        </div>

                        <div
                            v-if="$slots.footer"
                            class="border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50"
                        >
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    maxWidth: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl'].includes(v)
    },
    closeOnBackdrop: { type: Boolean, default: true },
    closeOnEscape: { type: Boolean, default: true },
    hideClose: { type: Boolean, default: false },
    bodyClass: { type: String, default: '' }
})

const emit = defineEmits(['update:modelValue', 'close', 'open'])

const panelRef = ref(null)
const previousActiveElement = ref(null)

const titleId = `modal-title-${Math.random().toString(36).slice(2, 9)}`

const maxWidthClass = computed(
    () =>
        ({
            sm: 'max-w-sm',
            md: 'max-w-md',
            lg: 'max-w-lg',
            xl: 'max-w-xl',
            '2xl': 'max-w-2xl',
            '3xl': 'max-w-3xl',
            '4xl': 'max-w-4xl'
        })[props.maxWidth]
)

const close = () => {
    emit('update:modelValue', false)
    emit('close')
}

const handleBackdropClick = () => {
    if (props.closeOnBackdrop) close()
}

const handleEscape = () => {
    if (props.closeOnEscape) close()
}

const handleGlobalKeydown = (e) => {
    if (e.key === 'Escape' && props.closeOnEscape && props.modelValue) {
        e.stopPropagation()
        close()
    }
}

const lockScroll = () => {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth
    document.body.style.overflow = 'hidden'
    if (scrollbarWidth > 0) {
        document.body.style.paddingRight = `${scrollbarWidth}px`
    }
}

const unlockScroll = () => {
    document.body.style.overflow = ''
    document.body.style.paddingRight = ''
}

watch(
    () => props.modelValue,
    async (open) => {
        if (open) {
            previousActiveElement.value = document.activeElement
            lockScroll()
            document.addEventListener('keydown', handleGlobalKeydown)
            await nextTick()
            const focusable = panelRef.value?.querySelector(
                'input:not([type="hidden"]), textarea, select, button:not([aria-label="Close"]), [tabindex]:not([tabindex="-1"])'
            )
            ;(focusable || panelRef.value)?.focus()
            emit('open')
        } else {
            unlockScroll()
            document.removeEventListener('keydown', handleGlobalKeydown)
            previousActiveElement.value?.focus?.()
            previousActiveElement.value = null
        }
    }
)

onUnmounted(() => {
    unlockScroll()
    document.removeEventListener('keydown', handleGlobalKeydown)
})
</script>
