<template>
    <button
        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
        :class="
            modelValue
                ? 'bg-[#F02C56] dark:bg-[#E91E63]'
                : 'bg-gray-200 dark:bg-gray-600'
        "
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span
            class="inline-block h-4 w-4 transform rounded-full transition-all"
            :class="[
                modelValue ? 'translate-x-6' : 'translate-x-1',
                'bg-white dark:bg-gray-100',
            ]"
        />
    </button>
</template>

<script setup lang="ts">
defineProps({
    modelValue: Boolean,
});

defineEmits(["update:modelValue"]);
</script>
