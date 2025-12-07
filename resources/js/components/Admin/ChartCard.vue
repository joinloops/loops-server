<template>
    <div
        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
    >
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            {{ title }}
        </h3>
        <slot></slot>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true
    }
})
</script>
