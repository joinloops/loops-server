<template>
    <div>
        <input
            :id="`input-${placeholder}`"
            :placeholder="placeholder"
            class="block w-full bg-[#F1F1F2] text-gray-800 dark:text-slate-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-md py-2.5 px-3 focus:outline-none"
            :type="inputType"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            autocomplete="off"
            :maxlength="max"
        />
        <span v-if="error" class="text-red-500 text-[14px] font-semibold">
            {{ error }}
        </span>
    </div>
</template>

<script setup>
import { onMounted, computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    placeholder: {
        type: String,
        default: ''
    },
    inputType: {
        type: String,
        default: 'text'
    },
    max: {
        type: [String, Number],
        default: undefined
    },
    autoFocus: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: ''
    }
})

defineEmits(['update:modelValue'])

onMounted(() => {
    if (props.autoFocus) {
        document.getElementById(`input-${props.placeholder}`).focus()
    }
})
</script>
