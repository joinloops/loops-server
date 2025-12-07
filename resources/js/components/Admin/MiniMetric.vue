<template>
    <div
        :class="[
            'bg-white dark:bg-gray-800 rounded-lg shadow-sm border p-4',
            alert
                ? 'border-orange-300 dark:border-orange-700'
                : 'border-gray-200 dark:border-gray-700'
        ]"
    >
        <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">{{ label }}</p>
        <p
            :class="[
                'text-xl font-bold',
                alert ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-white'
            ]"
        >
            {{ value }}
        </p>
    </div>
</template>

<script setup>
defineProps({
    label: {
        type: String,
        required: true
    },
    value: {
        type: [String, Number],
        required: true
    },
    alert: {
        type: Boolean,
        default: false
    }
})
</script>
