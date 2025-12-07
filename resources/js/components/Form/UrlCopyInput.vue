<template>
    <div
        class="flex w-full items-center bg-gray-200 dark:bg-gray-800 dark:border dark:border-slate-700 rounded-lg overflow-hidden"
    >
        <input
            type="text"
            :value="url"
            readonly
            class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-900 text-sm font-medium text-black dark:text-gray-200 focus:outline-none"
            ref="urlInput"
        />
        <button
            @click="copyToClipboard"
            class="px-4 py-2 text-sm text-black font-bold dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-800 focus:outline-none transition-colors duration-200"
            :class="{ 'text-green-700': copied }"
        >
            {{ copied ? $t('post.copiedExclamation') : $t('post.copyLink') }}
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    url: {
        type: String,
        required: true
    }
})

const urlInput = ref(null)
const copied = ref(false)

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(props.url)
        copied.value = true

        setTimeout(() => {
            copied.value = false
        }, 2000)
    } catch (err) {
        console.error('Failed to copy:', err)
    }
}
</script>
