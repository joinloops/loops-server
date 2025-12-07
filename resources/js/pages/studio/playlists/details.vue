<template>
    <StudioLayout>
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="mb-6">
                <button
                    @click="goBack"
                    class="flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors"
                >
                    <ArrowLeftIcon class="h-5 w-5 mr-2" />
                    {{ $t('studio.backToPlaylists') }}
                </button>
            </div>

            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6"
            >
                <div class="p-6">
                    <div v-if="loading && !playlist" class="text-center py-8">
                        <Spinner />
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ $t('studio.loadingPlaylistDotDotDot') }}
                        </p>
                    </div>

                    <div
                        v-else-if="playlist"
                        class="flex flex-col lg:flex-row lg:items-center lg:space-x-6"
                    >
                        <div class="flex-shrink-0 mb-4 lg:mb-0">
                            <div
                                class="w-32 h-64 bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden"
                            >
                                <img
                                    v-if="playlist.cover_image"
                                    :src="playlist.cover_image"
                                    :alt="`${playlist.name} cover`"
                                    class="w-full h-full object-cover"
                                    onerror="
                                        this.style.display = 'none'
                                        this.parentElement.innerHTML =
                                            '<div class=\'w-full h-full flex items-center justify-center\'><svg class=\'w-16 h-16 text-gray-400\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10\'></path></svg></div>'
                                    "
                                />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <QueueListIcon class="w-16 h-16 text-gray-400" />
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <h1
                                        class="text-2xl font-bold text-gray-900 dark:text-white mb-2"
                                    >
                                        {{ playlist.name }}
                                    </h1>
                                    <div v-if="playlist.description" class="mb-4 max-w-2xl">
                                        <AutolinkedText
                                            :caption="playlist.description"
                                            maxCharLimit="80"
                                            root-class="text-gray-500 dark:text-slate-400 whitespace-pre-wrap break-all leading-5 tracking-tight"
                                        />
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3 text-sm">
                                        <span :class="getVisibilityBadgeClass(playlist.visibility)">
                                            {{ formatVisibility(playlist.visibility) }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">
                                            {{ playlist.videos_count }}
                                            {{ playlist.videos_count > 1 ? 'videos' : 'video' }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">
                                            {{ $t('common.created') }}
                                            {{ formatDate(playlist.created_at) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex-shrink-0">
                                    <AnimatedButton @click="editPlaylist" variant="outline">
                                        <div class="flex gap-3 items-center whitespace-nowrap">
                                            <PencilSquareIcon class="h-5 w-5 flex-shrink-0" />
                                            <span>
                                                {{ $t('studio.editDetails') }}
                                            </span>
                                        </div>
                                    </AnimatedButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
            >
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $t('studio.videosInPlaylist') }} ({{ playlist?.videos_count || 0 }})
                        </h2>
                        <div
                            class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md"
                        >
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                <span class="font-medium">{{ $t('studio.tip') }}:</span>
                                {{ $t('studio.dragAndDropVideosToReorder') }}
                            </p>
                        </div>
                        <AnimatedButton @click="openAddVideoModal">
                            <div class="flex">
                                <PlusIcon class="h-5 w-5 mr-2" />
                                {{ $t('studio.addVideos') }}
                            </div>
                        </AnimatedButton>
                    </div>
                </div>

                <div v-if="loadingVideos && videos.length === 0" class="text-center py-16">
                    <Spinner />
                    <p class="text-gray-500 dark:text-gray-400">
                        {{ $t('studio.loadingVideosDotDotDot') }}
                    </p>
                </div>

                <div v-else-if="videos.length === 0" class="text-center py-16">
                    <VideoCameraIcon
                        class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4"
                    />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        {{ $t('studio.noVideosInThisPlaylist') }}
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6">
                        {{ $t('studio.addSomeVideosToGetStarted') }}
                    </p>
                    <button
                        @click="openAddVideoModal"
                        class="inline-flex items-center px-6 py-3 bg-red-500 hover:bg-red-600 text-white font-medium rounded-lg transition-colors"
                    >
                        <PlusIcon class="h-5 w-5 mr-2" />
                        {{ $t('studio.addFirstVideo') }}
                    </button>
                </div>

                <div v-else class="p-6">
                    <div class="drag-container">
                        <draggable
                            v-model="videos"
                            item-key="id"
                            handle=".drag-handle"
                            class="space-y-3"
                            @change="handleReorder"
                        >
                            <div
                                v-for="(video, index) in videos"
                                :key="video.id"
                                class="flex items-center space-x-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 transition-colors group"
                            >
                                <div
                                    class="drag-handle cursor-move text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    <Bars3Icon class="h-6 w-6" />
                                </div>

                                <div
                                    class="flex-shrink-0 text-sm font-medium text-gray-500 dark:text-gray-400 w-8"
                                >
                                    {{ index + 1 }}
                                </div>

                                <img
                                    :src="video.media.thumbnail"
                                    :alt="video.caption"
                                    class="w-12 h-20 rounded-lg object-cover flex-shrink-0"
                                    onerror="
                                        this.src = '/storage/videos/video-placeholder.jpg'
                                        this.onerror = null
                                    "
                                />

                                <div class="flex-1 min-w-0">
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"
                                    >
                                        {{ video.caption || 'Untitled Video' }}
                                    </p>
                                    <div
                                        class="flex items-center space-x-4 mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        <span class="flex items-center">
                                            <HeartIcon class="h-4 w-4 mr-1" />
                                            {{ video.likes?.toLocaleString() || 0 }}
                                        </span>
                                        <span class="flex items-center">
                                            <ChatBubbleLeftIcon class="h-4 w-4 mr-1" />
                                            {{ video.comments?.toLocaleString() || 0 }}
                                        </span>
                                        <span>{{ formatDate(video.created_at) }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <router-link
                                        :to="`/v/${video.hid}`"
                                        class="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-medium border-blue-300 text-blue-700 bg-blue-50/60 hover:bg-blue-100 hover:border-blue-400 dark:border-blue-500/70 dark:text-blue-300 dark:bg-blue-900/20 dark:hover:bg-blue-900/50 dark:hover:border-blue-300 transition-colors shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900 cursor-pointer"
                                    >
                                        <EyeIcon class="w-4 h-4" />
                                        {{ $t('studio.view') }}
                                    </router-link>

                                    <button
                                        @click="removeVideo(video.id)"
                                        class="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-medium border-red-300 text-red-700 bg-red-50/60 hover:bg-red-100 hover:border-red-400 dark:border-red-500/70 dark:text-red-300 dark:bg-red-900/20 dark:hover:bg-red-900/50 dark:hover:border-red-300 transition-colors shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900 cursor-pointer"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                        {{ $t('common.remove') }}
                                    </button>
                                </div>
                            </div>
                        </draggable>
                    </div>
                    <div v-if="hasMoreVideos" class="mt-6 text-center">
                        <button
                            @click="loadMoreVideos"
                            :disabled="loadingVideos"
                            class="px-6 py-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ loadingVideos ? 'Loading...' : 'Load More Videos' }}
                        </button>
                    </div>
                </div>
            </div>

            <PlaylistModal
                :is-open="showEditModal"
                :playlist="playlist"
                @close="showEditModal = false"
                @save="handleSavePlaylist"
                @delete="handleDeletePlaylist"
            />

            <AddVideoModal
                :is-open="showAddVideoModal"
                :playlist-id="playlistId"
                :exclude-video-ids="videoIds"
                @close="showAddVideoModal = false"
                @added="handleVideosAdded"
            />
        </div>
    </StudioLayout>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { usePlaylistStore } from '@/stores/playlist'
import { useUtils } from '@/composables/useUtils'
import PlaylistModal from '@/components/Studio/PlaylistModal.vue'
import AddVideoModal from '@/components/Studio/PlaylistAddVideoModal.vue'
import {
    EyeIcon,
    TrashIcon,
    ArrowLeftIcon,
    PlusIcon,
    QueueListIcon,
    VideoCameraIcon,
    PencilSquareIcon,
    Bars3Icon,
    HeartIcon,
    ChatBubbleLeftIcon
} from '@heroicons/vue/24/outline'
import { VueDraggableNext as draggable } from 'vue-draggable-next'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useI18n } from 'vue-i18n'
import AutolinkedText from '@/components/AutolinkedText.vue'
import AnimatedButton from '@/components/AnimatedButton.vue'

const authStore = inject('authStore')
const route = useRoute()
const router = useRouter()
const playlistStore = usePlaylistStore()
const { formatDate } = useUtils()
const { alertModal, confirmModal } = useAlertModal()
const { t } = useI18n()

const playlistId = computed(() => parseInt(route.params.id))
const loading = ref(false)
const loadingVideos = ref(false)
const playlist = computed(() => playlistStore.playlist)
const { playlistVideos: videos } = storeToRefs(playlistStore)
const hasMoreVideos = computed(() => playlistStore.videosPagination.hasMore)
const showEditModal = ref(false)
const showAddVideoModal = ref(false)

const videoIds = computed(() => videos.value.map((v) => v.id))

const loadPlaylist = async () => {
    loading.value = true

    try {
        await playlistStore.getPlaylistById(playlistId.value)
    } catch (error) {
        console.error('Error loading playlist:', error)
        router.push('/studio/playlists')
    } finally {
        loading.value = false
    }
}

const loadVideos = async () => {
    loadingVideos.value = true

    try {
        await playlistStore.fetchPlaylistVideos(playlistId.value)
    } catch (error) {
        console.error('Error loading videos:', error)
    } finally {
        loadingVideos.value = false
    }
}

const loadMoreVideos = async () => {
    if (!hasMoreVideos.value || loadingVideos.value) return

    loadingVideos.value = true

    try {
        const cursor = playlistStore.videosPagination.nextCursor
        await playlistStore.fetchPlaylistVideos(playlistId.value, cursor)
    } catch (error) {
        console.error('Error loading more videos:', error)
    } finally {
        loadingVideos.value = false
    }
}

const handleReorder = async () => {
    try {
        const videoIds = videos.value.map((v) => v.id)
        await playlistStore.reorderPlaylist(playlistId.value, videoIds)
        await loadPlaylist()
    } catch (error) {
        console.error('Error reordering videos:', error)
        await loadVideos()
    }
}

const removeVideo = async (videoId) => {
    const result = await confirmModal(
        t('studio.removeFromPlaylist'),
        t('studio.areYouSureYouWantToRemoveThisVideoFromThePlaylist'),
        t('common.delete'),
        t('common.cancel')
    )

    if (!result) {
        return
    }

    try {
        await playlistStore.removeVideoFromPlaylist(playlistId.value, videoId)

        await loadPlaylist()
    } catch (error) {
        console.error('Error removing video:', error)
    }
}

const editPlaylist = () => {
    showEditModal.value = true
}

const openAddVideoModal = () => {
    showAddVideoModal.value = true
}

const handleSavePlaylist = async (data) => {
    try {
        await playlistStore.updatePlaylist(data.id, data)
        showEditModal.value = false
    } catch (error) {
        console.error('Error saving playlist:', error)
    }
}

const handleDeletePlaylist = async (id) => {
    try {
        await playlistStore.deletePlaylist(id)
        router.push('/studio/playlists')
    } catch (error) {
        console.error('Error deleting playlist:', error)
    }
}

const handleVideosAdded = async () => {
    await loadVideos()
    await loadPlaylist()
    showAddVideoModal.value = false
}

const goBack = () => {
    playlistStore.clearPlaylist()
    router.push('/studio/playlists')
}

const getVisibilityBadgeClass = (visibility) => {
    const baseClasses = 'inline-flex px-2 py-1 text-xs font-semibold rounded-full'

    switch (visibility) {
        case 'public':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`
        case 'unlisted':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200`
        case 'followers':
            return `${baseClasses} bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200`
        case 'private':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200`
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200`
    }
}

const formatVisibility = (visibility) => {
    const labels = {
        public: 'Public',
        unlisted: 'Unlisted',
        followers: 'Followers',
        private: 'Private'
    }
    return labels[visibility] || visibility
}

onMounted(() => {
    if (authStore.getUser?.post_count < 20 || authStore.getUser?.follower_count < 100) {
        router.push('/studio')
        return
    }
    loadPlaylist()
    loadVideos()
})
</script>

<style scoped>
.drag-container {
    min-height: 200px;
    padding: 20px;
}

.ghost {
    opacity: 0.5;
    background: #e5e7eb;
    border: 2px dashed #9ca3af;
}

@media (prefers-color-scheme: dark) {
    .ghost {
        background: #374151;
        border-color: #4b5563;
    }
}
</style>
