<template>
    <div
        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-shadow"
    >
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    {{ title }}
                </p>
                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ formattedValue }}
                </p>
                <div v-if="change !== null" class="mt-2 flex items-center">
                    <span
                        :class="[
                            'text-sm font-medium',
                            change >= 0
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-600 dark:text-red-400'
                        ]"
                    >
                        {{ change >= 0 ? '↑' : '↓' }} {{ Math.abs(change) }}%
                    </span>
                    <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
                        vs last period
                    </span>
                </div>
            </div>

            <div
                :class="[
                    'flex items-center justify-center w-12 h-12 rounded-full',
                    getColorClasses(color)
                ]"
            >
                <component :is="icon" class="w-6 h-6 text-gray-800 dark:text-gray-100" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: String,
    value: [String, Number],
    change: Number,
    icon: {
        type: [Object, Function],
        required: true
    },
    color: {
        type: String,
        default: 'blue',
        validator: (value) => ['blue', 'purple', 'pink', 'green', 'yellow', 'red'].includes(value)
    }
})

const formattedValue = computed(() => {
    if (typeof props.value === 'number') {
        if (props.value >= 1_000_000) return (props.value / 1_000_000).toFixed(1) + 'M'
        if (props.value >= 1_000) return (props.value / 1_000).toFixed(1) + 'K'
        return props.value.toLocaleString()
    }
    return props.value
})

const getColorClasses = (color) => {
    const colors = {
        blue: 'bg-blue-100 dark:bg-blue-900/30',
        purple: 'bg-purple-100 dark:bg-purple-900/30',
        pink: 'bg-pink-100 dark:bg-pink-900/30',
        green: 'bg-green-100 dark:bg-green-900/30',
        yellow: 'bg-yellow-100 dark:bg-yellow-900/30',
        red: 'bg-red-100 dark:bg-red-900/30'
    }
    return colors[color] || colors.blue
}
</script>
