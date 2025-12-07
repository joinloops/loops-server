<template>
    <button
        @click="handleToggleDarkMode"
        class="flex items-center px-2 py-2 rounded-full text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
    >
        <component :is="isDark ? SunIcon : MoonIcon" class="size-5" />
    </button>
</template>

<script setup>
import { ref } from 'vue'
import { SunIcon, MoonIcon } from '@heroicons/vue/24/outline'

const isDark = ref(document.documentElement.classList.contains('dark'))

const handleToggleDarkMode = () => {
    isDark.value = !isDark.value
    if (isDark.value) {
        document.documentElement.classList.add('dark')
        localStorage.setItem('theme', 'dark')
    } else {
        document.documentElement.classList.remove('dark')
        localStorage.setItem('theme', 'light')
    }
}
</script>
