<template>
    <button
        type="button"
        :class="[
            'relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none focus:ring-1 focus:ring-offset-2',
            toggleClasses,
            { 'opacity-50 cursor-not-allowed': disabled }
        ]"
        :disabled="disabled"
        @click="toggle"
        role="switch"
        :aria-checked="modelValue"
    >
        <span
            :class="[
                'inline-block w-4 h-4 transform bg-white rounded-full transition-transform shadow-lg ring-0',
                modelValue ? 'translate-x-6' : 'translate-x-1'
            ]"
        />
    </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    },
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'danger', 'warning'].includes(value)
    }
})

const emit = defineEmits(['update:modelValue'])

const toggleClasses = computed(() => {
    const baseClasses = {
        default: {
            enabled: 'bg-blue-600 focus:ring-blue-500',
            disabled: 'bg-gray-200 dark:bg-gray-700'
        },
        danger: {
            enabled: 'bg-[#F02C56] dark:bg-[#E91E63] focus:ring-red-500',
            disabled: 'bg-gray-200 dark:bg-gray-600'
        },
        warning: {
            enabled: 'bg-orange-500 focus:ring-orange-500',
            disabled: 'bg-gray-200 dark:bg-gray-700'
        }
    }

    const variantClasses = baseClasses[props.variant]
    return props.modelValue ? variantClasses.enabled : variantClasses.disabled
})

const toggle = () => {
    if (!props.disabled) {
        emit('update:modelValue', !props.modelValue)
    }
}
</script>
