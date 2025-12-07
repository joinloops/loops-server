<template>
    <div class="dark:bg-gray-950 -mt-2">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-4xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                            {{ $t('common.explore') }}
                        </h1>

                        <div v-if="activeHashtag" class="flex items-center gap-3 text-sm flex-wrap">
                            <span class="text-gray-600 dark:text-gray-400 font-medium">
                                {{ formatNumber(totalResults || 0) }}
                                {{ totalResults === 1 ? $t('common.video') : $t('common.videos') }}
                            </span>
                        </div>

                        <p v-else class="text-gray-500 dark:text-gray-400 text-sm">
                            {{
                                $t('explore.discoverTrendingContent') ||
                                'Discover trending content and popular hashtags'
                            }}
                        </p>
                    </div>
                </div>

                <div v-if="hashtags && hashtags.length" class="relative">
                    <div class="mr-4 flex gap-2 overflow-x-auto no-scrollbar pb-2 -mx-1 px-3">
                        <button
                            v-for="hashtag in hashtags"
                            :key="hashtag.id"
                            @click="$emit('selectHashtag', hashtag)"
                            :class="[
                                'flex-shrink-0 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200 whitespace-nowrap cursor-pointer',
                                'disabled:opacity-50 disabled:cursor-not-allowed',
                                activeHashtag?.id === hashtag.id
                                    ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                            ]"
                            :disabled="loading"
                        >
                            {{ hashtag.name }}
                        </button>
                    </div>

                    <div
                        class="absolute right-0 top-0 bottom-2 w-8 bg-gradient-to-l from-gray-50 dark:from-gray-950 to-transparent pointer-events-none"
                        aria-hidden="true"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useUtils } from '@/composables/useUtils'

const { formatNumber } = useUtils()

defineProps({
    hashtags: {
        type: Array,
        default: () => []
    },
    activeHashtag: {
        type: Object,
        default: null
    },
    totalResults: {
        type: Number,
        default: null
    },
    loading: {
        type: Boolean,
        default: false
    }
})

defineEmits(['selectHashtag'])
</script>
