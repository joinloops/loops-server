<template>
    <div class="h-full flex flex-col bg-white dark:bg-slate-900">
        <div class="flex-1 overflow-y-auto p-3">
            <!-- Loading State -->
            <div v-if="isLoadingHighlightedComment" class="p-4 text-center">
                <Spinner />
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-2">
                    {{ $t('post.loadingComment') }}
                </p>
            </div>

            <!-- Error State -->
            <div v-else-if="highlightError" class="p-4 text-center">
                <div
                    class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4"
                >
                    <p class="text-red-600 dark:text-red-400">
                        {{ highlightError }}
                    </p>
                    <button
                        @click="handleClearHighlight"
                        class="mt-2 text-sm text-red-600 dark:text-red-400 hover:underline cursor-pointer"
                    >
                        {{ $t('post.viewAllComments') }}
                    </button>
                </div>
            </div>

            <div v-else-if="showHighlightedView">
                <div class="space-y-3">
                    <CommentItem
                        v-if="highlightedCommentData"
                        :comment="highlightedCommentData"
                        :videoId="videoId"
                        :is-highlighted="true"
                        :highlighted-reply-id="
                            highlightedComment?.type === 'reply'
                                ? highlightedComment.commentId
                                : null
                        "
                    />
                </div>

                <!-- View All Comments Button -->
                <div class="mt-6 text-center">
                    <button
                        @click="handleViewAllComments"
                        class="inline-flex items-center px-6 py-3 bg-[#F02C56] hover:bg-[#F02C56]/80 text-white rounded-lg font-medium transition-colors duration-200 cursor-pointer"
                    >
                        <i class="bx bx-comment-dots text-xl mr-2"></i>
                        {{ $t('post.viewAllComments') }}
                    </button>
                </div>

                <div v-if="hasOtherComments && showBlurredPreview" class="mt-6 relative">
                    <div
                        class="absolute inset-0 bg-gradient-to-b from-transparent via-white/50 to-white dark:via-slate-900/50 dark:to-slate-900 z-10 backdrop-blur-sm"
                    ></div>
                    <div class="opacity-40 pointer-events-none space-y-3">
                        <CommentItem
                            v-for="comment in previewComments"
                            :key="comment.id"
                            :comment="comment"
                            :videoId="videoId"
                        />
                    </div>
                </div>
            </div>

            <!-- Normal Comments View -->
            <div v-else>
                <div v-if="isLoading && !comments.length" class="p-4 text-center">
                    <Spinner />
                </div>
                <div v-else-if="error" class="p-4 text-center text-red-500">
                    {{ error?.message || $t('post.errorLoadingComments') }}
                </div>

                <div v-else>
                    <div
                        v-if="!comments.length"
                        class="p-4 text-center text-gray-500 dark:text-slate-400"
                    >
                        <div v-if="!canComment" class="flex flex-col items-center space-y-2">
                            <i class="bx bx-comment-x text-[36px]"></i>
                            <span>{{ $t('post.commentsAreDisabledForThisVideo') }}</span>
                        </div>
                        <div v-else class="flex flex-col items-center space-y-2">
                            <i class="bx bx-comment-dots text-2xl"></i>
                            <span>{{ $t('post.noCommentsYet') }}</span>
                        </div>
                    </div>
                    <div v-else class="">
                        <CommentItem
                            v-for="comment in comments"
                            :key="comment.id"
                            :comment="comment"
                            :videoId="videoId"
                        />

                        <div v-if="hasMore" class="p-4 text-center">
                            <button
                                @click="loadMore"
                                class="text-sm font-medium text-[#F02C56] hover:text-[#F02C56]/70 cursor-pointer"
                                :disabled="isLoading"
                            >
                                {{ $t('common.loadMore') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="authStore.authenticated && canComment"
            class="p-4 border-t border-gray-200 dark:border-slate-800 flex-shrink-0"
        >
            <form class="relative flex items-start space-x-2" @submit.prevent="handleAddComment">
                <div class="flex-1">
                    <MentionHashtagInput
                        ref="commentInputRef"
                        v-model="newComment"
                        :placeholder="$t('post.addCommentDotDotDot')"
                        :disabled="isSubmitting || !canComment"
                        :fetch-mentions="fetchMentions"
                        :fetch-hashtags="fetchHashtags"
                        :validate-mentions="true"
                        :validate-hashtags="true"
                        :initial-validated-mentions="initialValidatedMentions"
                        :initial-validated-hashtags="initialValidatedHashtags"
                        border-class="border-gray-300 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 dark:text-slate-50 focus:border-[#F02C56]"
                        min-height="42px"
                        max-height="120px"
                        @focus="handleInputFocus"
                    />
                </div>

                <div class="flex items-center space-x-2 pt-2">
                    <EmojiPicker v-model="selectedEmoji" @select="onEmojiSelect">
                        <template #trigger="{ toggle }">
                            <button
                                type="button"
                                @click="toggle"
                                class="text-xl hover:scale-110 transition-transform"
                                :disabled="isSubmitting"
                            >
                                😊
                            </button>
                        </template>
                    </EmojiPicker>

                    <button
                        type="submit"
                        class="px-3 py-1.5 bg-[#F02C56] hover:bg-[#F02C56]/80 text-white text-sm font-medium rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                        :disabled="isSubmitting || !normalizedComment"
                    >
                        {{ isSubmitting ? $t('post.postingDotDotDot') : $t('post.post') }}
                    </button>
                </div>
            </form>
        </div>

        <div
            v-else-if="!canComment"
            class="p-4 border-t border-gray-200 dark:border-slate-800 flex-shrink-0"
        >
            <div class="text-center text-gray-500 dark:text-slate-400 text-sm">
                {{ $t('post.commentsHaveBeenDisabled') }}
            </div>
        </div>

        <div
            v-else-if="!authStore.authenticated && canComment"
            class="p-4 border-t border-gray-200 dark:border-slate-800 flex-shrink-0"
        >
            <div class="text-center text-gray-500 dark:text-slate-400 text-sm">
                {{ $t('post.signInToLeaveAComment') }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, inject, nextTick, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCommentStore } from '@/stores/comments'
import { useHashids } from '@/composables/useHashids'
import EmojiPicker from '@/components/Form/EmojiPicker.vue'
import CommentItem from './CommentItem.vue'
import Spinner from '../Spinner.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const appStore = inject('appStore')
const videoStore = inject('videoStore')
const commentStore = useCommentStore()
const { decodeHashid } = useHashids()

const newComment = ref('')
const isSubmitting = ref(false)
const selectedEmoji = ref('')
const error = ref(null)
const isLoadingHighlightedComment = ref(false)
const highlightError = ref(null)
const showBlurredPreview = ref(true)
const commentInputRef = ref(null)
const initialValidatedMentions = ref([])
const initialValidatedHashtags = ref([])
const hasAutoMentioned = ref(new Set())

const handleInputFocus = async () => {
    const vid = currentVideo.value?.id

    if (
        vid &&
        !hasAutoMentioned.value.has(vid) &&
        !newComment.value.trim() &&
        currentVideo.value?.account
    ) {
        hasAutoMentioned.value.add(vid)

        const username = currentVideo.value.account.username

        newComment.value = `@${username} `

        await nextTick()

        if (commentInputRef.value) {
            commentInputRef.value.addValidatedMention(username)
        }

        await nextTick()

        if (commentInputRef.value) {
            commentInputRef.value.focus()
            commentInputRef.value.moveCursorToEnd()
        }
    }
}

const currentVideo = computed(() => videoStore.currentVideo)
const videoId = computed(() => currentVideo.value?.id)
const comments = computed(() => (videoId.value ? commentStore.getComments(videoId.value) : []))
const isLoading = computed(() => (videoId.value ? commentStore.isLoading(videoId.value) : false))
const hasMore = computed(() => (videoId.value ? commentStore.hasMore(videoId.value) : false))

// Highlighted comment state
const highlightedComment = computed(() =>
    videoId.value ? commentStore.getHighlightedComment(videoId.value) : null
)
const highlightedCommentData = computed(() =>
    videoId.value ? commentStore.getHighlightedCommentData(videoId.value) : null
)
const showHighlightedView = computed(
    () => !!highlightedComment.value && !!highlightedCommentData.value
)
const hasOtherComments = computed(() => comments.value.length > 0)
const previewComments = computed(() => comments.value.slice(0, 2))

const canComment = computed(() => {
    return currentVideo.value?.permissions?.can_comment !== false
})

const normalizedComment = computed(() => newComment.value.trim())

const onEmojiSelect = (emoji) => {
    // Insert emoji at cursor position
    if (commentInputRef.value) {
        commentInputRef.value.insertAtCursor(emoji.native)
        commentInputRef.value.focus()
    } else {
        newComment.value += emoji.native
    }
}

// Mention the video account
const mentionCreator = async () => {
    if (!currentVideo.value?.account) return

    const username = currentVideo.value.account.username
    newComment.value = `@${username} `

    await nextTick()

    if (commentInputRef.value) {
        commentInputRef.value.addValidatedMention(username)
    }

    await nextTick()

    if (commentInputRef.value) {
        commentInputRef.value.focus()
        commentInputRef.value.moveCursorToEnd()
    }
}

const fetchMentions = async (query) => {
    try {
        const response = await videoStore.autocompleteAccount(encodeURIComponent(query))
        return response.data
    } catch (error) {
        console.error('Error fetching mentions:', error)
        return []
    }
}

// Fetch hashtags for autocomplete
const fetchHashtags = async (query) => {
    try {
        const response = await videoStore.autocompleteHashtag(encodeURIComponent(query))
        return response.data
    } catch (error) {
        console.error('Error fetching hashtags:', error)
        return []
    }
}

const pendingKey = ref(null)
const makeKey = (text, vid) => `${vid}:${text}`
const lastSubmitted = ref({ key: null, at: 0 })
const COOLDOWN_MS = 1500

const handleAddComment = async () => {
    const text = normalizedComment.value
    const vid = videoId.value

    if (!text || !vid || isSubmitting.value || !canComment.value) return

    const key = makeKey(text, vid)
    const now = Date.now()

    if (pendingKey.value === key) return

    if (lastSubmitted.value.key === key && now - lastSubmitted.value.at < COOLDOWN_MS) return

    try {
        isSubmitting.value = true
        pendingKey.value = key

        await nextTick()
        await commentStore.addComment(vid, text)
        await videoStore.incrementCommentCount()

        newComment.value = ''
        initialValidatedMentions.value = []
        initialValidatedHashtags.value = []

        // Clear the input component
        if (commentInputRef.value) {
            commentInputRef.value.clear()
        }

        hasAutoMentioned.value.delete(vid)

        lastSubmitted.value = { key, at: now }
    } catch (err) {
        error.value = err
    } finally {
        isSubmitting.value = false
        pendingKey.value = null
    }
}

const handleViewAllComments = async () => {
    // Clear the query parameters and highlighted state
    await router.replace({
        path: route.path,
        query: {}
    })

    commentStore.clearHighlightedComment(videoId.value)

    // Load normal comments
    if (!comments.value.length) {
        await commentStore.fetchComments(videoId.value, true)
    }
}

const handleClearHighlight = () => {
    highlightError.value = null
    handleViewAllComments()
}

// Load highlighted comment if query params exist
const loadHighlightedComment = async () => {
    const { cid, rid } = route.query

    if (!cid && !rid) {
        // No highlight params, load normal comments
        if (canComment.value) {
            try {
                error.value = null
                await commentStore.fetchComments(videoId.value, true)
            } catch (err) {
                console.error('Error fetching comments:', err)
                error.value = err
            }
        }
        return
    }

    if (!videoId.value) return

    isLoadingHighlightedComment.value = true
    highlightError.value = null

    try {
        if (cid) {
            // Load specific comment
            const commentId = decodeHashid(cid)
            if (!commentId) {
                throw new Error('Invalid comment ID')
            }
            await commentStore.fetchCommentById(videoId.value, commentId)
        } else if (rid) {
            // Load specific reply
            const replyId = decodeHashid(rid)
            if (!replyId) {
                throw new Error('Invalid reply ID')
            }
            await commentStore.fetchReplyById(videoId.value, replyId)
        }

        if (showBlurredPreview.value) {
            commentStore.fetchComments(videoId.value, true)
        }
    } catch (err) {
        console.error('Error loading highlighted comment:', err)
        highlightError.value =
            err.message === 'Invalid comment ID' || err.message === 'Invalid reply ID'
                ? 'The comment link is invalid or broken.'
                : 'Unable to load the comment. It may have been deleted.'
    } finally {
        isLoadingHighlightedComment.value = false
    }
}

watch(
    currentVideo,
    async (newVideo, oldVideo) => {
        if (!newVideo || newVideo.id === oldVideo?.id) return
        await loadHighlightedComment()
    },
    { immediate: true }
)

watch(
    () => route.query,
    async (newQuery, oldQuery) => {
        if (newQuery.cid !== oldQuery?.cid || newQuery.rid !== oldQuery?.rid) {
            await loadHighlightedComment()
        }
    }
)

const loadMore = async () => {
    if (!videoId.value || !hasMore.value || isLoading.value || !canComment.value) return

    try {
        await commentStore.fetchComments(videoId.value, false)
    } catch (err) {
        console.error('Error loading more comments:', err)
        error.value = err
    }
}
</script>

<style scoped>
/* Highlight animation will be in CommentItem component */
</style>
