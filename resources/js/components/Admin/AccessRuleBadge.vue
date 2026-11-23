<template>
    <span
        :class="[
            'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide',
            badge.classes
        ]"
    >
        {{ badge.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    matchType: { type: String, required: true }
})

const badges = {
    exact: {
        label: 'Exact',
        classes: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
    },
    cidr: {
        label: 'CIDR',
        classes: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
    },
    range: {
        label: 'Range',
        classes: 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300'
    },
    domain: {
        label: 'Domain',
        classes: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
    },
    pattern: {
        label: 'Pattern',
        classes: 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300'
    }
}

const badge = computed(
    () =>
        badges[props.matchType] || {
            label: props.matchType,
            classes: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
        }
)
</script>
