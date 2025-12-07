<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <router-link
            v-for="hashtag in hashtags"
            :key="hashtag.id"
            :to="`/tag/${hashtag.slug}`"
            class="flex items-center justify-between p-4 bg-white dark:bg-gray-900 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors group"
        >
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div
                    class="w-12 h-12 rounded-full bg-gradient-to-br from-red-200 to-rose-300 dark:from-red-700 dark:to-rose-900 flex items-center justify-center flex-shrink-0"
                >
                    <HashtagIcon class="h-6 w-6 text-black dark:text-white" />
                </div>

                <div class="min-w-0 flex-1">
                    <h3
                        class="font-semibold text-gray-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors"
                    >
                        #{{ hashtag.name }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ formatNumber(hashtag.videos_count || hashtag.count || 0) }}
                        {{ hashtag.videos_count === 1 ? 'video' : 'videos' }}
                    </p>
                </div>
            </div>

            <ChevronRightIcon
                class="h-5 w-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 flex-shrink-0"
            />
        </router-link>
    </div>
</template>

<script setup>
import { HashtagIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import { useUtils } from '@/composables/useUtils'

const props = defineProps({
    hashtags: {
        type: Array,
        required: true
    }
})

const { formatNumber } = useUtils()
</script>
