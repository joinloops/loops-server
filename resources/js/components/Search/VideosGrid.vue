<template>
    <div
        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 sm:gap-4"
    >
        <router-link
            v-for="video in videos"
            :to="`/v/${video.hid}?by=${video.account.username}`"
            :key="video.id"
            class="group cursor-pointer"
        >
            <div
                class="relative aspect-[9/16] overflow-hidden bg-white dark:bg-gray-900 rounded-xl hover:shadow-2xl dark:hover:shadow-gray-900/50 transition-all duration-300"
            >
                <img
                    :src="video.media.thumbnail"
                    :alt="video.title || video.caption"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                    loading="lazy"
                />

                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-80 group-hover:opacity-60 transition-opacity duration-300"
                ></div>

                <div class="absolute top-3 left-3">
                    <span
                        class="bg-black/10 backdrop-blur-sm px-2 py-1 rounded-full flex gap-1.5 items-center text-white text-xs font-medium"
                    >
                        <HeartIcon class="h-4 w-4" />
                        {{ formatNumber(video.likes) }}
                    </span>
                </div>

                <div class="absolute top-3 right-3">
                    <span
                        class="bg-black/10 backdrop-blur-sm px-2 py-1 rounded-full flex gap-1.5 items-center text-white text-xs font-medium"
                    >
                        <ChatBubbleOvalLeftIcon class="h-4 w-4" />
                        {{ formatNumber(video.comments || 0) }}
                    </span>
                </div>

                <div class="absolute bottom-0 left-0 right-0 p-3">
                    <div
                        v-if="video.caption || video.title"
                        class="text-white text-xs line-clamp-2 font-medium"
                    >
                        {{ video.caption || video.title }}
                    </div>
                </div>

                <div
                    class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                >
                    <div
                        class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center"
                    >
                        <svg
                            class="w-6 h-6 text-white ml-1"
                            fill="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path d="M8 5v14l11-7z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="mt-2 px-1">
                <div class="flex items-center gap-2 min-w-0 mb-1.5">
                    <Avatar :src="video.account.avatar" :width="20" class="flex-shrink-0" />
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">
                        {{ video.account.username }}
                    </span>
                </div>
            </div>
        </router-link>
    </div>
</template>

<script setup>
import { HeartIcon, ChatBubbleOvalLeftIcon } from '@heroicons/vue/24/outline'
import { useUtils } from '@/composables/useUtils'

const props = defineProps({
    videos: {
        type: Array,
        required: true
    }
})

const { formatNumber } = useUtils()
</script>
