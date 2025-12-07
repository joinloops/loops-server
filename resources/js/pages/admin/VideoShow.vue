<template>
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <div v-if="loading" class="space-y-6">
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <div class="animate-pulse space-y-4">
                    <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded w-2/3"></div>
                    <div class="h-64 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                </div>
            </div>
        </div>

        <div v-else-if="video" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                        <div class="relative bg-black aspect-video">
                            <video class="w-full h-full object-fit" controls>
                                <source :src="video.media.src_url" type="video/mp4" />
                            </video>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                    >
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Caption
                        </h3>
                        <div class="prose dark:prose-invert max-w-none">
                            <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                {{ video.caption || 'No caption provided.' }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Comments ({{ formatNumber(video.comments) }})
                                </h3>
                                <div class="flex items-center gap-3">
                                    <select
                                        v-model="commentsSortBy"
                                        @change="fetchComments"
                                        class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-1"
                                    >
                                        <option value="newest">Newest</option>
                                        <option value="oldest">Oldest</option>
                                        <option value="most_liked">Most Liked</option>
                                    </select>
                                    <button
                                        @click="fetchComments"
                                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 cursor-pointer"
                                    >
                                        <ArrowPathIcon class="size-6" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            <div v-if="commentsLoading" class="p-6">
                                <div v-for="i in 3" :key="i" class="flex gap-3 mb-4 animate-pulse">
                                    <div
                                        class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded-full flex-shrink-0"
                                    ></div>
                                    <div class="flex-1 space-y-2">
                                        <div
                                            class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"
                                        ></div>
                                        <div
                                            class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3/4"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div
                                    v-for="comment in comments"
                                    :key="comment.id"
                                    class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <div class="flex gap-3">
                                        <img
                                            :src="comment.account.avatar"
                                            :alt="comment.account.username"
                                            class="w-8 h-8 rounded-full flex-shrink-0"
                                            @error="
                                                $event.target.src = '/storage/avatars/default.jpg'
                                            "
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span
                                                    class="font-medium text-gray-900 dark:text-white text-sm"
                                                    >{{ comment.account.username }}</span
                                                >
                                                <span
                                                    v-if="comment.account.verified"
                                                    class="text-blue-500"
                                                >
                                                    <svg
                                                        class="w-3 h-3"
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                            clip-rule="evenodd"
                                                        ></path>
                                                    </svg>
                                                </span>
                                                <span
                                                    class="text-xs text-gray-500 dark:text-gray-400"
                                                    >{{
                                                        formatTimeAgo(comment.created_at, 'long')
                                                    }}</span
                                                >
                                            </div>
                                            <p
                                                class="text-gray-700 dark:text-gray-300 text-sm mb-2"
                                            >
                                                {{ comment.caption }}
                                            </p>

                                            <div
                                                class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                <div class="flex gap-2">
                                                    <button
                                                        @click="deleteComment(comment)"
                                                        class="text-gray-600 hover:text-red-500 dark:text-gray-400 dark:hover:text-gray-300 cursor-pointer"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="commentsPagination.hasNext"
                                    class="p-4 text-center border-t border-gray-200 dark:border-gray-700"
                                >
                                    <button
                                        @click="loadMoreComments"
                                        :disabled="loadingMoreComments"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium disabled:opacity-50 cursor-pointer"
                                    >
                                        {{
                                            loadingMoreComments
                                                ? 'Loading...'
                                                : 'Load More Comments'
                                        }}
                                    </button>
                                </div>

                                <div
                                    v-if="!commentsLoading && comments.length === 0"
                                    class="p-6 text-center text-gray-500 dark:text-gray-400"
                                >
                                    No comments yet.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                    >
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button
                                @click="moderateVideo('publish')"
                                v-if="video.status !== 'published'"
                                class="flex-1 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors cursor-pointer"
                            >
                                Approve Video
                            </button>
                            <button
                                @click="moderateVideo('unpublished')"
                                v-if="video.status === 'published'"
                                class="flex-1 px-4 py-2 bg-yellow-400 text-yellow-900 text-sm font-medium rounded-lg hover:bg-yellow-500 transition-colors cursor-pointer"
                            >
                                Unlist Video
                            </button>
                            <button
                                @click="handleDeleteVideo()"
                                v-if="video.status !== 'archived'"
                                class="flex-1 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors cursor-pointer"
                            >
                                Delete Video
                            </button>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-6 py-3"
                    >
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Video Status
                            </h3>

                            <div class="flex items-center gap-3">
                                <router-link
                                    v-if="video.status === 'published'"
                                    :to="`/v/${video.hid}`"
                                >
                                    <span
                                        class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 hover:bg-green-200 hover:dark:bg-green-800 capitalize cursor-pointer"
                                    >
                                        {{ video.status }}
                                    </span>
                                </router-link>
                                <span
                                    v-else
                                    :class="[
                                        'px-3 py-1 text-sm font-medium rounded-full',
                                        video.status === 'pending'
                                            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                            : video.status === 'unpublished'
                                              ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                              : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                    ]"
                                >
                                    {{ video.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-6 py-3"
                    >
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                            Video Published By
                        </h3>

                        <router-link :to="`/admin/profiles/${video.account.id}`">
                            <div class="flex items-center gap-3">
                                <img
                                    :src="video.account.avatar"
                                    :alt="video.account.username"
                                    class="w-12 h-12 rounded-full border-2 border-gray-200 dark:border-gray-600"
                                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                                />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900 dark:text-white">{{
                                            video.account.display_name || video.account.username
                                        }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        @{{ video.account.username }}
                                    </p>
                                </div>
                            </div>
                        </router-link>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                    >
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">
                            Video Stats
                        </h3>

                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ formatNumber(video.likes) }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Likes
                                </div>
                            </div>

                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ formatNumber(video.comments) }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Comments
                                </div>
                            </div>

                            <component
                                :is="(video.reported_count || 0) > 0 ? 'router-link' : 'div'"
                                :to="
                                    (video.reported_count || 0) > 0
                                        ? `/admin/reports?q=video_id:${video.id}`
                                        : undefined
                                "
                                class="text-center"
                                :class="
                                    (video.reported_count || 0) > 0
                                        ? 'hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg p-2 -m-2 transition-colors cursor-pointer'
                                        : ''
                                "
                            >
                                <div
                                    class="text-2xl font-bold"
                                    :class="
                                        (video.reported_count || 0) > 0
                                            ? 'text-red-600 dark:text-red-400'
                                            : 'text-gray-900 dark:text-white'
                                    "
                                >
                                    {{ formatNumber(video.reported_count || 0) }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Reports
                                </div>
                            </component>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                    >
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Video Information
                        </h3>
                        <div class="space-y-3">
                            <div v-if="video.is_sensitive">
                                <div v-if="video.is_sensitive" class="text-red-600">
                                    Video is NSFW/Sensitive
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400"
                                    >Upload Date</label
                                >
                                <div class="text-gray-900 dark:text-white">
                                    {{ formatDateTime(video.created_at) }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400"
                                    >File Size</label
                                >
                                <div class="text-gray-900 dark:text-white">
                                    {{ formatBytes(video.media.size || 0) }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400"
                                    >Privacy</label
                                >
                                <div class="text-gray-900 dark:text-white">
                                    {{ video.privacy || 'Public' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400"
                                    >Pinned to Profile</label
                                >
                                <div class="text-gray-900 dark:text-white">
                                    {{ video.pinned ? 'Yes' : 'No' }}
                                </div>
                            </div>
                            <div v-if="video.permissions">
                                <label class="block text-sm font-medium text-gray-400"
                                    >Comments</label
                                >
                                <div
                                    v-if="video.permissions?.can_comment"
                                    class="text-gray-900 dark:text-white"
                                >
                                    Open
                                </div>
                                <div v-else class="text-red-600">Closed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center"
        >
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Video Not Found</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                The video you're looking for doesn't exist or has been removed.
            </p>
            <router-link
                to="/admin/videos"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
            >
                Back to Videos
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { videosApi } from '@/services/adminApi'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import { useUtils } from '@/composables/useUtils'
const {
    truncateMiddle,
    formatNumber,
    formatDate,
    formatTimeAgo,
    formatDateTime,
    formatDuration,
    formatBytes
} = useUtils()
const { alertModal, confirmModal } = useAlertModal()

const route = useRoute()
const router = useRouter()

// Reactive data
const videoId = computed(() => route.params.id)
const loading = ref(true)
const video = ref(null)
const comments = ref([])
const commentsLoading = ref(false)
const loadingMoreComments = ref(false)
const commentsSortBy = ref('newest')
const commentsPagination = ref({
    cursor: null,
    hasNext: false
})

// Methods
const fetchVideo = async () => {
    loading.value = true

    try {
        const response = await videosApi.getVideo(videoId.value)
        video.value = response.data

        // Fetch comments after video loads
        await fetchComments()
    } catch (error) {
        console.error('Error fetching video:', error)
        video.value = null
    } finally {
        loading.value = false
    }
}

const fetchComments = async (cursor = null) => {
    if (!cursor) {
        commentsLoading.value = true
        comments.value = []
    } else {
        loadingMoreComments.value = true
    }

    try {
        const response = await videosApi.getVideoComments(videoId.value, {
            cursor,
            sort: commentsSortBy.value
        })

        const newComments = response.data
        const pagination = response.meta

        if (cursor) {
            comments.value.push(...newComments)
        } else {
            comments.value = newComments
        }

        commentsPagination.value = {
            cursor: pagination.next_cursor,
            hasNext: pagination.next_cursor != null
        }
    } catch (error) {
        console.error('Error fetching comments:', error)
    } finally {
        commentsLoading.value = false
        loadingMoreComments.value = false
    }
}

const loadMoreComments = () => {
    if (commentsPagination.value.hasNext && !loadingMoreComments.value) {
        fetchComments(commentsPagination.value.cursor)
    }
}

const moderateVideo = async (status) => {
    loading.value = true
    try {
        const response = await videosApi.moderateVideo(video.value.id, {
            action: status
        })
        video.value = response.data
    } catch (error) {
        console.error('Error moderating video:', error)
    } finally {
        loading.value = false
    }
}

const handleDeleteVideo = async () => {
    const result = await confirmModal(
        'Delete Video?',
        'Are you sure you want to delete this video?',
        'Delete',
        'Cancel'
    )

    if (result) {
        await moderateVideo('delete')
        router.push('/admin/videos')
    }
}

const deleteComment = async (comment) => {
    const result = await confirmModal(
        'Delete Video Comment',
        `Are you sure you want to mark this comment by ${comment.account.username}? This action cannot be undone.`,
        'Delete',
        'Cancel'
    )

    if (result) {
        try {
            await videosApi.deleteVideoComment(comment.id).then(() => {
                comments.value = comments.value.filter((c) => c.id !== comment.id)
                video.value.comments--
            })
        } catch (error) {
            console.error('Error deleting comment:', error)
        }
    }
}

onMounted(() => {
    fetchVideo()
})
</script>
