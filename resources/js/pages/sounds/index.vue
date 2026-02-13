<template>
    <MainLayout>
        <div class="min-h-screen dark:bg-gray-950">
            <SoundsHeader
                :id="id"
                :totalResults="totalResults"
                :duration="sound?.duration"
                :originalVideo="originalVideo"
            />

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div v-if="loading" class="flex justify-center items-center py-12">
                    <Spinner />
                </div>

                <div v-else-if="error" class="text-center py-12">
                    <div class="text-gray-500 dark:text-gray-400 mb-4">
                        {{ $t('sounds.errorLoadingSound') || 'Error loading sound' }}
                    </div>
                </div>

                <div v-else-if="allVideos && allVideos.length" class="space-y-6">
                    <div
                        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 sm:gap-4"
                    >
                        <router-link
                            v-for="(video, index) in allVideos"
                            :to="`/v/${video.hid}`"
                            :key="video.id"
                            class="group cursor-pointer"
                        >
                            <div
                                class="relative aspect-[9/16] overflow-hidden bg-white dark:bg-gray-900 rounded-xl hover:shadow-2xl dark:hover:shadow-gray-900/50 transition-all duration-300"
                                :style="
                                    index === 0
                                        ? 'box-shadow: 0 0 0 2px rgba(240, 44, 86, 0.85);'
                                        : ''
                                "
                            >
                                <img
                                    :src="video.media.thumbnail"
                                    :alt="video.caption"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                    :loading="index === 0 ? 'eager' : 'lazy'"
                                />

                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-80 group-hover:opacity-60 transition-opacity duration-300"
                                ></div>

                                <div v-if="index === 0" class="absolute top-3 left-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full flex gap-1.5 items-center text-white text-xs font-bold shadow-lg"
                                        style="background-color: #f02c56"
                                    >
                                        <svg
                                            class="w-3.5 h-3.5"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"
                                            />
                                        </svg>
                                        ORIGINAL
                                    </span>
                                </div>

                                <div
                                    class="absolute"
                                    :class="index === 0 ? 'right-3 top-3' : 'left-3 top-3'"
                                >
                                    <span
                                        class="bg-black/10 backdrop-blur-sm px-2 py-1 rounded-full flex gap-1.5 items-center text-white text-xs font-medium"
                                    >
                                        <HeartIcon class="h-4 w-4" />
                                        {{ formatNumber(video.likes) }}
                                    </span>
                                </div>

                                <!-- Comment count -->
                                <div v-if="index" class="absolute top-3 right-3">
                                    <span
                                        class="bg-black/10 backdrop-blur-sm px-2 py-1 rounded-full flex gap-1.5 items-center text-white text-xs font-medium"
                                    >
                                        <ChatBubbleOvalLeftIcon class="h-4 w-4" />
                                        {{ formatNumber(video.comments) }}
                                    </span>
                                </div>

                                <!-- Caption -->
                                <div class="absolute bottom-0 left-0 right-0 p-3">
                                    <div
                                        v-if="video.caption"
                                        class="mt-2 text-white text-xs line-clamp-2 font-medium"
                                    >
                                        {{ video.caption }}
                                    </div>
                                </div>

                                <!-- Play button -->
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
                                    <Avatar
                                        :src="video.account.avatar"
                                        :width="20"
                                        class="flex-shrink-0"
                                    />
                                    <span
                                        class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate"
                                    >
                                        {{ video.account.username }}
                                    </span>
                                </div>
                            </div>
                        </router-link>
                    </div>

                    <div v-if="loadingMore" class="flex justify-center py-8">
                        <Spinner />
                    </div>

                    <div ref="loadMoreTrigger" class="h-10"></div>

                    <div
                        v-if="!hasMore && !loadingMore"
                        class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm"
                    >
                        {{ $t('common.noMoreResults') || 'No more videos' }}
                    </div>
                </div>

                <div v-else class="text-center py-12">
                    <div class="text-gray-500 dark:text-gray-400 mb-4">
                        {{ $t('sounds.noVideosFound') || 'No videos found for this sound' }}
                    </div>
                </div>
            </div>
        </div>

        <GuestAuthPromptModal :show="showGuestModal" @close="showGuestModal = false" />
    </MainLayout>
</template>

<script setup>
import { onMounted, onUnmounted, inject, ref, watch, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { HeartIcon, ChatBubbleOvalLeftIcon } from '@heroicons/vue/24/outline'
import { useRoute } from 'vue-router'
import { useUtils } from '@/composables/useUtils'
import SoundsHeader from '@/components/Sounds/SoundsHeader.vue'
import GuestAuthPromptModal from '@/components/Tag/GuestAuthPromptModal.vue'

const id = ref()
const route = useRoute()
const soundsStore = inject('soundsStore')
const authStore = inject('authStore')
const loadMoreTrigger = ref(null)
const showGuestModal = ref(false)
let observer = null
const { formatNumber } = useUtils()

const {
    sound,
    originalVideo,
    allVideos,
    cursor,
    totalResults,
    loading,
    loadingMore,
    error,
    hasMore
} = storeToRefs(soundsStore)

const { fetchSoundDetails, loadMore, reset } = soundsStore

const handleFetch = async () => {
    await fetchSoundDetails(route.params.id)
    // Set up observer after initial data is loaded
    await nextTick()
    setupIntersectionObserver()
}

const setupIntersectionObserver = () => {
    // Clean up existing observer
    if (observer) {
        observer.disconnect()
        observer = null
    }

    if (!loadMoreTrigger.value) {
        return
    }

    observer = new IntersectionObserver(
        (entries) => {
            const [entry] = entries

            if (entry.isIntersecting && !loadingMore.value && !loading.value && hasMore.value) {
                if (!authStore.authenticated) {
                    showGuestModal.value = true
                    return
                }

                loadMore()
            }
        },
        {
            root: null,
            rootMargin: '200px',
            threshold: 0.1
        }
    )

    observer.observe(loadMoreTrigger.value)
}

// Remove the watch on allVideos - we'll set up observer after initial fetch

watch(
    () => route.params.id,
    (newId) => {
        if (newId && newId !== id.value) {
            id.value = newId
            if (observer) {
                observer.disconnect()
                observer = null
            }
            reset()
            handleFetch()
        }
    }
)

onMounted(() => {
    id.value = route.params.id
    handleFetch()
})

onUnmounted(() => {
    if (observer) {
        observer.disconnect()
    }
    reset()
})
</script>
