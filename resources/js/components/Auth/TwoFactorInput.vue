<template>
    <div class="flex flex-col items-center space-y-4">
        <div class="flex space-x-3">
            <input
                v-for="(digit, index) in digits"
                :key="index"
                :ref="(el) => setInputRef(el, index)"
                v-model="digits[index]"
                type="text"
                inputmode="numeric"
                pattern="[0-9]"
                class="w-12 h-12 text-center text-xl font-bold rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 transition-all duration-200 ease-in-out"
                :class="{
                    'border-red-500 dark:border-red-400': hasError && digits[index] === '',
                    'border-green-500 dark:border-green-400': digits[index] !== ''
                }"
                @input="handleInput(index, $event)"
                @keydown="handleKeyDown(index, $event)"
                @focus="handleFocus(index)"
                @blur="handleBlur"
                autocomplete="one-time-code"
            />
        </div>

        <div v-if="hasError" class="text-red-500 dark:text-red-400 text-sm">
            {{ errorMessage || 'Please enter a valid 6-digit code' }}
        </div>

        <div class="text-gray-500 dark:text-gray-400 text-sm text-center">
            Enter the 6-digit code from your authenticator app
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    hasError: {
        type: Boolean,
        default: false
    },
    errorMessage: {
        type: String,
        default: ''
    },
    autoSubmit: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'complete'])

const digits = ref(['', '', '', '', '', ''])
const inputRefs = ref([])
const isComponentFocused = ref(false)

const code = computed(() => digits.value.join(''))
const isComplete = computed(() => code.value.length === 6)

const setInputRef = (el, index) => {
    if (el) {
        inputRefs.value[index] = el
    }
}

watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue !== code.value) {
            const newDigits = newValue.padEnd(6, '').slice(0, 6).split('')
            digits.value = newDigits
        }
    },
    { immediate: true }
)

watch(code, (newCode) => {
    emit('update:modelValue', newCode)

    if (isComplete.value) {
        emit('complete', newCode)
        if (props.autoSubmit) {
        }
    }
})

const handleInput = (index, event) => {
    const value = event.target.value.replace(/[^0-9]/g, '')

    if (value.length > 0) {
        digits.value[index] = value.slice(-1)

        if (index < 5) {
            nextTick(() => {
                inputRefs.value[index + 1]?.focus()
            })
        }
    } else {
        digits.value[index] = ''
    }
}

const handleKeyDown = (index, event) => {
    if (event.key === 'Backspace') {
        if (digits.value[index] === '' && index > 0) {
            nextTick(() => {
                inputRefs.value[index - 1]?.focus()
            })
        } else {
            digits.value[index] = ''
        }
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault()
        inputRefs.value[index - 1]?.focus()
    }

    if (event.key === 'ArrowRight' && index < 5) {
        event.preventDefault()
        inputRefs.value[index + 1]?.focus()
    }

    if (
        !/[0-9]/.test(event.key) &&
        !['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight'].includes(event.key)
    ) {
        event.preventDefault()
    }
}

const handlePaste = async (event) => {
    event.preventDefault()
    event.stopPropagation()

    let pastedText = ''

    try {
        if (navigator.clipboard && navigator.clipboard.readText) {
            pastedText = await navigator.clipboard.readText()
        } else if (event.clipboardData) {
            pastedText =
                event.clipboardData.getData('text/plain') ||
                event.clipboardData.getData('text') ||
                event.clipboardData.getData('Text')
        } else if (window.clipboardData) {
            pastedText = window.clipboardData.getData('Text')
        }
    } catch (err) {
        if (event.clipboardData) {
            pastedText = event.clipboardData.getData('text') || ''
        }
        console.log('Clipboard access limited:', err)
    }

    const numericText = pastedText.replace(/[^0-9]/g, '').slice(0, 6)

    if (numericText.length > 0) {
        digits.value = ['', '', '', '', '', '']

        // Fill in the pasted digits
        for (let i = 0; i < numericText.length && i < 6; i++) {
            digits.value[i] = numericText[i]
        }

        const nextIndex = numericText.length >= 6 ? 5 : numericText.length
        nextTick(() => {
            inputRefs.value[nextIndex]?.focus()
        })
    }
}

const handleBlur = () => {
    setTimeout(() => {
        const activeElement = document.activeElement
        const isFocusedOnOurInput = inputRefs.value.some((input) => input === activeElement)
        if (!isFocusedOnOurInput) {
            isComponentFocused.value = false
        }
    }, 10)
}

const handleFocus = (index) => {
    isComponentFocused.value = true
    nextTick(() => {
        inputRefs.value[index]?.select()
    })
}

const handleDocumentPaste = async (event) => {
    if (!isComponentFocused.value) return

    event.preventDefault()
    event.stopPropagation()

    let pastedText = ''

    try {
        // Try modern clipboard API first
        if (navigator.clipboard && navigator.clipboard.readText) {
            pastedText = await navigator.clipboard.readText()
        }
        // Fallback to event clipboardData
        else if (event.clipboardData) {
            pastedText =
                event.clipboardData.getData('text/plain') ||
                event.clipboardData.getData('text') ||
                event.clipboardData.getData('Text')
        } else if (window.clipboardData) {
            pastedText = window.clipboardData.getData('Text')
        }
    } catch (err) {
        if (event.clipboardData) {
            pastedText = event.clipboardData.getData('text') || ''
        }
        console.log('Clipboard access limited:', err)
    }

    const numericText = pastedText.replace(/[^0-9]/g, '').slice(0, 6)

    if (numericText.length > 0) {
        digits.value = ['', '', '', '', '', '']

        for (let i = 0; i < numericText.length && i < 6; i++) {
            digits.value[i] = numericText[i]
        }

        const nextIndex = numericText.length >= 6 ? 5 : numericText.length
        nextTick(() => {
            inputRefs.value[nextIndex]?.focus()
        })
    }
}

const clear = () => {
    digits.value = ['', '', '', '', '', '']
    nextTick(() => {
        inputRefs.value[0]?.focus()
    })
}

const focus = () => {
    const firstEmpty = digits.value.findIndex((digit) => digit === '')
    const indexToFocus = firstEmpty !== -1 ? firstEmpty : 0
    nextTick(() => {
        inputRefs.value[indexToFocus]?.focus()
    })
}

onMounted(() => {
    nextTick(() => {
        inputRefs.value[0]?.focus()
    })

    document.addEventListener('paste', handlePaste)
})

onUnmounted(() => {
    document.removeEventListener('paste', handlePaste)
})

defineExpose({
    clear,
    focus
})
</script>
