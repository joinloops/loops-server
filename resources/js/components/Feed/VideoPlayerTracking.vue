<template>
    <div
        class="relative flex justify-center h-[100dvh] lg:h-[calc(100dvh-60px)] w-full overflow-hidden video-wrapper"
    >
        <div class="flex items-center h-full w-full lg:max-w-7xl lg:mx-auto px-0 lg:px-4 lg:py-4">
            <div
                :class="[
                    'relative h-full transition-transform duration-300 w-full lg:flex-1',
                    showComments ? 'lg:-translate-x-[150px]' : 'translate-x-0'
                ]"
            >
                <div class="flex items-center lg:items-end h-full justify-center w-full">
                    <div
                        :class="[
                            'relative flex items-center h-full w-full bg-black border-0 lg:border-[0.5px] lg:border-slate-300 lg:dark:border-slate-800 overflow-hidden rounded-none lg:rounded-xl video-container',
                            videoAspectClass
                        ]"
                        :style="videoAspectStyle"
                        @touchstart="handleTouchStart"
                        @touchmove="handleTouchMove"
                        @touchend="handleTouchEnd"
                        @click="handleVideoClick"
                    >
                        <video
                            ref="videoRef"
                            class="video-js vjs-default-skin h-full w-full object-contain"
                            playsinline
                            :muted="isMuted"
                        >
                            <source :src="videoUrl" type="video/mp4" />
                        </video>

                        <div
                            v-if="isSensitive && !isSensitiveRevealed"
                            class="absolute inset-0 bg-black/90 rounded-none lg:rounded-xl flex flex-col items-center justify-center z-30"
                        >
                            <div class="text-center">
                                <i
                                    class="bx bx-low-vision text-white text-[48px] sm:text-[64px] mb-4"
                                ></i>
                                <h3 class="text-white text-lg sm:text-xl font-semibold mb-2">
                                    {{ $t('post.sensitiveContent') }}
                                </h3>
                                <p
                                    class="text-white/80 text-sm sm:text-base px-6 mb-4 leading-relaxed"
                                >
                                    This content may not be suitable for all audiences
                                </p>
                                <button
                                    @click.stop="revealSensitiveContent"
                                    class="mt-5 w-full bg-[#F02C56] border border-[#F02C56] text-white rounded-lg px-5 py-2 hover:bg-[#F02C56]/80 hover:border-[#F02C5699] cursor-pointer backdrop-blur-sm text-sm font-medium"
                                >
                                    Show Content
                                </button>
                            </div>
                        </div>

                        <div v-show="showPlayButton && canInteract" class="play-overlay">
                            <button class="play-button" @click.stop="handlePlayClick">
                                <i class="bx bx-play text-[40px] sm:text-[60px] text-white"></i>
                            </button>
                        </div>

                        <div
                            v-if="!isPaused && showMobilePauseButton && isMobile && canInteract"
                            class="mobile-pause-overlay"
                        >
                            <button class="play-button" @click.stop="pause">
                                <i class="bx bx-pause text-[40px] text-white"></i>
                            </button>
                        </div>

                        <button
                            v-if="!isPaused && hasGlobalInteraction && isMuted && canInteract"
                            @click.stop="toggleMute"
                            class="absolute bottom-[106px] left-4 lg:bottom-4 lg:right-auto lg:left-4 z-10 bg-black/50 rounded-full p-2 text-white flex items-center justify-center hover:bg-black/70"
                        >
                            <i
                                :class="isMuted ? 'bx bx-volume-mute' : 'bx bx-volume-full'"
                                class="text-xl"
                            ></i>
                        </button>

                        <div
                            class="absolute bottom-10 lg:bottom-0 left-0 right-0 h-30 bg-gradient-to-t from-black/80 via-black/50 to-transparent pointer-events-none z-0"
                        ></div>

                        <div
                            v-if="canInteract"
                            class="absolute bottom-10 left-2 right-20 lg:bottom-2 lg:right-4 p-2 lg:p-4 text-white pointer-events-none z-10"
                        >
                            <div class="mb-0">
                                <span class="text-base sm:text-lg font-semibold drop-shadow-lg"
                                    >@{{ username }}</span
                                >
                            </div>
                            <div class="mb-2">
                                <AutolinkedText
                                    :caption="caption"
                                    :mentions="mentions"
                                    :tags="hashtags"
                                    text-size="text-[14px]"
                                    root-class="text-gray-300 dark:text-slate-300 whitespace-pre-wrap leading-relaxed pointer-events-auto drop-shadow-md"
                                    :max-char-limit="80"
                                />
                            </div>
                        </div>

                        <div
                            v-if="canInteract"
                            class="absolute right-2 bottom-15 lg:bottom-4 flex flex-col items-center space-y-6 lg:hidden pointer-events-auto z-10"
                        >
                            <div class="flex flex-col items-center">
                                <router-link :to="`/@${username}`">
                                    <div
                                        class="h-10 w-10 sm:h-12 sm:w-12 overflow-hidden shadow rounded-full bg-gray-200 dark:border-slate-800"
                                    >
                                        <img
                                            :src="profileImage"
                                            alt="Profile"
                                            class="h-full w-full object-cover rounded-full"
                                            @error="
                                                $event.target.src = '/storage/avatars/default.jpg'
                                            "
                                        />
                                    </div>
                                </router-link>
                            </div>

                            <div class="flex flex-col items-center">
                                <button
                                    @click.stop="toggleLike"
                                    :class="[
                                        videoLiked
                                            ? 'text-red-500 hover:text-red-400'
                                            : 'text-white hover:text-red-500 dark:text-white'
                                    ]"
                                    class="mobile-interaction-btn"
                                >
                                    <i
                                        class="text-[28px]"
                                        :class="[videoLiked ? 'bx bxs-heart' : 'bx bx-heart']"
                                    ></i>
                                </button>
                                <span class="mt-1 text-xs font-medium text-white dark:text-white">{{
                                    formatCount(likeCount)
                                }}</span>
                            </div>

                            <div class="flex flex-col items-center text-white hover:text-red-500">
                                <button @click.stop="toggleComments" class="mobile-interaction-btn">
                                    <i
                                        class="bx bx-message-square-dots text-[24px] sm:text-[28px]"
                                    ></i>
                                </button>
                                <span class="mt-1 text-xs font-medium">{{
                                    formatCount(commentCount)
                                }}</span>
                            </div>

                            <div class="flex flex-col items-center text-white hover:text-red-500">
                                <button @click.stop="toggleBookmark" class="mobile-interaction-btn">
                                    <i
                                        class="bx text-[24px] sm:text-[28px]"
                                        :class="[
                                            videoBookmarked
                                                ? 'bxs-bookmark text-red-500'
                                                : 'bx-bookmark'
                                        ]"
                                    ></i>
                                </button>
                                <span class="mt-1 text-xs font-medium">{{
                                    formatCount(bookmarksCount)
                                }}</span>
                            </div>

                            <div class="flex flex-col items-center text-white hover:text-red-500">
                                <ShareModal :url="shareUrl">
                                    <button class="mobile-interaction-btn" @click.stop>
                                        <i class="bx bx-share text-[24px] sm:text-[28px]"></i>
                                    </button>
                                </ShareModal>
                                <span class="mt-1 text-xs font-medium">{{
                                    formatCount(shares)
                                }}</span>
                            </div>

                            <div class="flex flex-col items-center">
                                <div class="relative">
                                    <button
                                        class="mt-1 text-white hover:text-gray-300 mobile-interaction-btn"
                                        @click.stop="showMenu = !showMenu"
                                    >
                                        <i class="bx bx-cog text-[24px] sm:text-[28px]"></i>
                                    </button>

                                    <div
                                        v-if="showMenu"
                                        class="absolute z-20 bg-white dark:bg-slate-900 rounded-lg w-[200px] shadow-xl overflow-hidden border border-gray-200 dark:border-slate-700 divide-y divide-gray-200 dark:divide-gray-700 bottom-[43px] -right-2"
                                    >
                                        <LoopLink
                                            class="flex w-full items-center justify-start gap-2 py-3 px-4 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                                            :id="videoId"
                                        >
                                            <i class="bx bx-link text-[20px]"></i>
                                            <span class="pl-2 font-semibold text-sm">{{
                                                $t('post.permalink')
                                            }}</span>
                                        </LoopLink>
                                        <button
                                            v-if="
                                                authStore.authenticated &&
                                                profileId != authStore.user.id
                                            "
                                            class="flex w-full items-center justify-start gap-2 py-3 px-4 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                                            @click="handleReport"
                                        >
                                            <i class="bx bx-flag text-[20px]"></i>
                                            <span class="pl-2 font-semibold text-sm">{{
                                                $t('common.report')
                                            }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="canInteract"
                        class="hidden lg:flex flex-col items-center space-y-6 ml-4"
                    >
                        <div class="flex flex-col items-center">
                            <router-link :to="`/@${username}`">
                                <div
                                    class="h-12 w-12 overflow-hidden shadow rounded-full bg-gray-200 dark:border-slate-800"
                                >
                                    <img
                                        :src="profileImage"
                                        alt="Profile"
                                        class="h-full w-full object-cover rounded-full"
                                        @error="$event.target.src = '/storage/avatars/default.jpg'"
                                    />
                                </div>
                            </router-link>
                        </div>
                        <div class="flex flex-col items-center">
                            <button
                                @click="toggleLike"
                                :class="[
                                    videoLiked
                                        ? 'text-red-500 hover:text-red-400'
                                        : 'text-dark hover:text-red-500 dark:text-white'
                                ]"
                            >
                                <i
                                    class="text-[30px]"
                                    :class="[videoLiked ? 'bx bxs-heart' : 'bx bx-heart']"
                                ></i>
                            </button>
                            <span class="mt-1 text-sm font-medium dark:text-white">{{
                                formatCount(likeCount)
                            }}</span>
                        </div>

                        <div
                            v-if="canComment"
                            class="flex flex-col items-center text-dark hover:text-red-500 dark:text-white"
                        >
                            <button @click="toggleComments">
                                <i class="bx bx-message-square-dots text-[30px]"></i>
                            </button>
                            <span class="mt-1 text-sm font-medium">{{
                                formatCount(commentCount)
                            }}</span>
                        </div>

                        <div
                            class="flex flex-col items-center text-dark hover:text-red-500 dark:text-white"
                        >
                            <button @click="toggleBookmark">
                                <i
                                    class="bx text-[24px] sm:text-[28px]"
                                    :class="[
                                        videoBookmarked
                                            ? 'bxs-bookmark text-red-500'
                                            : 'bx-bookmark'
                                    ]"
                                ></i>
                            </button>
                            <span class="mt-1 text-sm font-medium">{{
                                formatCount(bookmarksCount)
                            }}</span>
                        </div>

                        <div
                            class="flex flex-col items-center text-dark hover:text-red-500 dark:text-white cursor-pointer"
                        >
                            <ShareModal type="video" :username="username" :url="shareUrl">
                                <button>
                                    <i class="bx bx-share text-[30px]"></i>
                                </button>
                            </ShareModal>
                            <span class="mt-1 text-sm font-medium">{{ formatCount(shares) }}</span>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="relative">
                                <button
                                    class="mt-1 hover:text-gray-600 dark:text-slate-500"
                                    @click="showMenu = !showMenu"
                                >
                                    <i class="bx bx-cog text-[30px]"></i>
                                </button>
                                <div
                                    v-if="showMenu"
                                    id="videoMenu"
                                    class="absolute z-20 bg-white dark:bg-slate-900 rounded-lg w-[200px] shadow-xl overflow-hidden border border-gray-200 dark:border-slate-700 bottom-[43px] -right-2 divide-y divide-gray-200 dark:divide-gray-700"
                                >
                                    <LoopLink
                                        class="flex w-full items-center justify-start gap-2 py-3 px-4 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                                        :id="videoId"
                                    >
                                        <i class="bx bx-link text-[20px]"></i>
                                        <span class="pl-2 font-semibold text-sm">{{
                                            $t('post.permalink')
                                        }}</span>
                                    </LoopLink>
                                    <button
                                        v-if="
                                            authStore.authenticated &&
                                            profileId != authStore.user.id
                                        "
                                        class="flex w-full items-center justify-start gap-2 py-3 px-4 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                                        @click="handleReport"
                                    >
                                        <i class="bx bx-flag text-[20px]"></i>
                                        <span class="pl-2 font-semibold text-sm">{{
                                            $t('common.report')
                                        }}</span>
                                    </button>
                                    <button
                                        v-if="
                                            authStore.authenticated &&
                                            profileId == authStore.user.id
                                        "
                                        class="flex w-full items-center justify-start gap-2 py-3 px-4 hover:bg-gray-100 text-red-500 dark:hover:bg-slate-800 cursor-pointer"
                                        @click="handleVideoDelete"
                                    >
                                        <i class="bx bx-trash text-[20px]"></i>
                                        <span class="pl-2 font-semibold text-sm">{{
                                            $t('common.delete')
                                        }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showComments"
            class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
            @touchstart.stop.prevent
            @touchmove.stop.prevent
            @touchend.stop.prevent
            @click.stop="toggleComments"
        ></div>

        <div
            v-if="showComments"
            :class="[
                'fixed top-[70px] lg:top-0 bottom-0 right-0 bg-gray-50 dark:bg-slate-900 border-l border-t lg:border-t border-gray-100 dark:border-slate-800 transform transition-transform duration-300 z-50 flex flex-col w-full sm:w-[400px] lg:w-[400px] shadow-xl comments-panel',
                showComments ? 'translate-x-0' : 'translate-x-full'
            ]"
            @touchstart.stop
            @touchmove.stop
            @touchend.stop
            @click.stop
            data-interactive="true"
        >
            <div class="sticky top-0 z-10 bg-gray-50 dark:bg-slate-900">
                <div
                    class="flex items-center justify-between p-4 border-b border-gray-300 dark:border-slate-700"
                >
                    <h2 class="text-lg font-semibold text-black dark:text-gray-400">
                        {{ $t('post.comments') }} ({{ formatCount(commentCount) }})
                    </h2>
                    <button @click.stop="toggleComments" class="text-gray-400 hover:text-gray-300">
                        <i class="bx bx-x text-[20px]" />
                    </button>
                </div>
            </div>

            <div
                class="flex-1 overflow-y-auto overscroll-contain pb-16"
                @wheel.stop
                @touchstart.stop
                @touchmove.stop
                @touchend.stop
                @scroll.stop="handleScroll"
            >
                <div
                    v-if="comments.length === 0 && !isLoading"
                    class="h-full flex items-center justify-center flex-grow text-dark dark:text-gray-600"
                >
                    <p class="text-sm text-gray-400">
                        {{ $t('post.noCommentsYet') }}
                    </p>
                </div>

                <CommentItem
                    v-for="comment in comments"
                    :key="comment.id"
                    :comment="comment"
                    :videoId="videoId"
                />

                <div v-if="isLoading" class="p-4 text-center">
                    <Spinner />
                </div>
            </div>

            <div
                v-if="authStore.authenticated"
                class="absolute bottom-0 left-0 right-0 bg-gray-50 dark:bg-slate-900 border-t border-gray-300 dark:border-slate-700 p-3"
                @touchstart.stop
                @touchmove.stop
                @touchend.stop
                @click.stop
            >
                <form @submit.prevent.stop="submitComment" class="flex items-center space-x-2">
                    <div class="flex-1 relative">
                        <textarea
                            v-model="newComment"
                            :placeholder="$t('post.addCommentDotDotDot')"
                            class="w-full px-4 py-2 text-sm bg-gray-100 dark:bg-slate-800 dark:text-white border border-gray-200 dark:border-slate-700 rounded-lg resize-none focus:outline-none focus:ring-1 focus:ring-red-500"
                            rows="1"
                            style="min-height: 40px; max-height: 120px"
                            @input.stop="autoResize"
                            @touchstart.stop
                            @touchmove.stop
                            @touchend.stop
                            @click.stop
                        ></textarea>
                        <div
                            class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center space-x-2"
                        >
                            <EmojiPicker v-model="selectedEmoji" @select="onEmojiSelect">
                                <template #trigger="{ toggle }">
                                    <button
                                        type="button"
                                        class="text-gray-400 hover:text-gray-600"
                                        @click.stop="toggle"
                                        @touchstart.stop
                                        @touchend.stop
                                    >
                                        <i class="bx bx-smile text-xl"></i>
                                    </button>
                                </template>
                            </EmojiPicker>
                        </div>
                    </div>
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!newComment.trim()"
                        @click.stop
                        @touchstart.stop
                        @touchend.stop
                    >
                        {{ $t('post.post') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import {
    ref,
    onMounted,
    onUnmounted,
    onBeforeUnmount,
    computed,
    watch,
    inject,
    nextTick
} from 'vue'
import { useVideoTracking } from '~/composables/useVideoTracking'
import { useCommentStore } from '~/stores/comments'
import { useFeedInteraction } from '~/composables/useFeedInteraction'
import EmojiPicker from '@/components/Form/EmojiPicker.vue'
import ShareModal from '@/components/Feed/ShareModal.vue'
import Spinner from '@/components/Spinner.vue'
import videojs from 'video.js'
import 'video.js/dist/video-js.css'
import LoopLink from '../LoopLink.vue'
import { useReportModal } from '@/composables/useReportModal'
import { useQueryClient } from '@tanstack/vue-query'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    duration: { type: Number, default: 0 },
    videoId: { type: String, required: true },
    videoUrl: { type: String, required: true },
    shareUrl: { type: String, required: true },
    username: { type: String, default: 'username' },
    caption: { type: String, default: '' },
    hashtags: { type: Array, default: () => [] },
    mentions: { type: Array, default: () => [] },
    profileImage: { type: String, default: '' },
    profileId: { type: String, default: '' },
    likes: { type: Number, default: 0 },
    bookmarks: { type: Number, default: 0 },
    hasLiked: { type: Boolean, default: false },
    hasBookmarked: { type: Boolean, default: false },
    canComment: { type: Boolean, default: true },
    shares: { type: Number, default: 0 },
    comments: { type: Array, default: () => [] },
    commentCount: { type: Number, default: 0 },
    index: { type: Number, default: 0 },
    isSensitive: { type: Boolean, default: false },
    altText: { type: String, default: '' }
})

const emit = defineEmits(['interaction'])

const authStore = inject('authStore')
const videoStore = inject('videoStore')
const showMenu = ref(false)
const showComments = ref(false)
const commentStore = useCommentStore()
const comments = computed(() => commentStore.getComments(props.videoId))
const isLoading = computed(() => commentStore.isLoading(props.videoId))
const hasMore = computed(() => commentStore.hasMore(props.videoId))
const videoRef = ref(null)
const isPaused = ref(true)
const newComment = ref('')
const likeCount = ref(0)
const bookmarksCount = ref(0)
const videoLiked = ref(false)
const videoBookmarked = ref(false)
const selectedEmoji = ref('')
const isVisible = ref(false)
const playerReady = ref(false)
const isSensitiveRevealed = ref(false)
const pendingPlay = ref(false)
const { openReportModal } = useReportModal()
const queryClient = useQueryClient()
const { alertModal, confirmModal } = useAlertModal()
const { t } = useI18n()
const { recordImpression } = useVideoTracking()
const watchStartTime = ref(0)
const accumulatedWatchTime = ref(0)
const hasReportedCompletion = ref(false)
const videoDuration = ref(props.duration || 0)
const hasFlushedFinal = ref(false)
const videoWidth = ref(null)
const videoHeight = ref(null)
const videoOrientation = ref('portrait')

const {
    hasInteracted: hasGlobalInteraction,
    handleFirstInteraction: globalHandleFirstInteraction,
    globalMuted,
    setGlobalMuted
} = useFeedInteraction()

const isMuted = computed({
    get: () => globalMuted.value,
    set: (value) => setGlobalMuted(value)
})

const videoAspectClass = computed(() => {
    if (!videoWidth.value || !videoHeight.value) {
        return 'lg:max-w-sm xl:max-w-md 2xl:max-w-lg lg:aspect-[9/16]'
    }

    const aspectRatio = videoWidth.value / videoHeight.value

    if (videoOrientation.value === 'portrait') {
        return 'lg:max-w-sm xl:max-w-md 2xl:max-w-lg lg:aspect-[9/16]'
    } else if (videoOrientation.value === 'landscape') {
        return 'lg:max-w-4xl xl:max-w-5xl 2xl:max-w-6xl lg:aspect-[16/9]'
    } else {
        return 'lg:max-w-2xl xl:max-w-3xl 2xl:max-w-4xl lg:aspect-square'
    }
})

const videoAspectStyle = computed(() => {
    if (!videoWidth.value || !videoHeight.value) return {}

    const aspectRatio = videoWidth.value / videoHeight.value

    return {
        '--video-aspect-ratio': aspectRatio
    }
})

const canInteract = computed(() => !props.isSensitive || isSensitiveRevealed.value)

const showPlayButton = computed(() => !hasGlobalInteraction.value || isPaused.value)

const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024)
const isMobile = computed(() => windowWidth.value < 1024)
const showMobilePauseButton = ref(false)
const touchTimeout = ref(null)

const touchStartTime = ref(0)
const touchStartX = ref(0)
const touchStartY = ref(0)
const isLongPress = ref(false)
const longPressTimeout = ref(null)

let player = null

const revealSensitiveContent = async () => {
    isSensitiveRevealed.value = true
    if (pendingPlay.value) {
        pendingPlay.value = false
        await nextTick()
        await handlePlayClick()
    }
}

const handleTouchStart = (e) => {
    if (!canInteract.value) return
    touchStartTime.value = Date.now()
    touchStartX.value = e.touches[0].clientX
    touchStartY.value = e.touches[0].clientY
    isLongPress.value = false
    if (longPressTimeout.value) clearTimeout(longPressTimeout.value)
    if (isMobile.value && hasGlobalInteraction.value && !isPaused.value) {
        longPressTimeout.value = setTimeout(() => {
            isLongPress.value = true
            showMobilePauseButton.value = true
            if (touchTimeout.value) clearTimeout(touchTimeout.value)
            touchTimeout.value = setTimeout(() => (showMobilePauseButton.value = false), 2000)
        }, 500)
    }
}

const handleTouchMove = (e) => {
    if (longPressTimeout.value) {
        const deltaX = Math.abs(e.touches[0].clientX - touchStartX.value)
        const deltaY = Math.abs(e.touches[0].clientY - touchStartY.value)
        if (deltaX > 10 || deltaY > 10) {
            clearTimeout(longPressTimeout.value)
            longPressTimeout.value = null
        }
    }
}

const handleTouchEnd = (e) => {
    if (!canInteract.value) return
    const touchEndTime = Date.now()
    const touchDuration = touchEndTime - touchStartTime.value
    if (longPressTimeout.value) {
        clearTimeout(longPressTimeout.value)
        longPressTimeout.value = null
    }
    if (isLongPress.value) {
        isLongPress.value = false
        return
    }
    if (touchDuration < 500) {
        const deltaX = Math.abs(e.changedTouches[0].clientX - touchStartX.value)
        const deltaY = Math.abs(e.changedTouches[0].clientY - touchStartY.value)
        if (deltaX < 10 && deltaY < 10) handleVideoClick(e)
    }
}

const handleVideoClick = async (e) => {
    if (e.target.closest('button, a, .mobile-interaction-btn')) return
    if (!canInteract.value) return
    if (!hasGlobalInteraction.value || isPaused.value) {
        await handlePlayClick()
    } else if (isMobile.value) {
        pause()
    } else {
        if (player && !player.paused()) {
            pause()
        } else {
            await handlePlayClick()
        }
    }
}

const handleResize = () => {
    windowWidth.value = window.innerWidth
}

const onEmojiSelect = (emoji) => {
    newComment.value += emoji.native
}

const autoResize = (e) => {
    const textarea = e.target
    textarea.style.height = 'auto'
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px'
}

const submitComment = async () => {
    if (!newComment.value.trim()) return
    try {
        await commentStore.addComment(props.videoId, newComment.value).then(() => {
            newComment.value = ''
        })
    } catch (error) {
        console.error('Error submitting comment:', error)
    }
}

const toggleLike = async () => {
    if (!authStore.authenticated) {
        authStore.openAuthModal()
        return
    }
    const state = videoLiked.value

    if (state) {
        await videoStore.unlikeVideo(props.videoId).then((res) => {
            videoStore.setVideo(res.data)
            videoLiked.value = false
            likeCount.value = res.data.likes
        })
    } else {
        await videoStore.likeVideo(props.videoId).then((res) => {
            videoStore.setVideo(res.data)
            videoLiked.value = true
            likeCount.value = res.data.likes
        })
    }
}

const toggleBookmark = async () => {
    if (!authStore.authenticated) {
        authStore.openAuthModal()
        return
    }
    const state = videoBookmarked.value

    if (state) {
        await videoStore.unbookmarkVideo(props.videoId).then((res) => {
            videoStore.setVideo(res.data)
            videoBookmarked.value = false
            bookmarksCount.value = res.data.bookmarks
        })
    } else {
        await videoStore.bookmarkVideo(props.videoId).then((res) => {
            videoStore.setVideo(res.data)
            videoBookmarked.value = true
            bookmarksCount.value = res.data.bookmarks
        })
    }
}

const toggleMute = () => {
    if (player) {
        const newMutedState = !isMuted.value
        setGlobalMuted(newMutedState)
        player.muted(newMutedState)
    }
}

onMounted(async () => {
    await nextTick()
    window.addEventListener('resize', handleResize)
    videoStore.setVideo({
        id: props.videoId,
        account: { id: props.profileId },
        comments: props.commentCount,
        likes: props.likesCount,
        bookmarks: props.bookmarks,
        has_liked: props.hasLiked,
        has_bookmarked: props.hasBookmarked
    })
    likeCount.value = props.likes
    videoLiked.value = props.hasLiked
    videoBookmarked.value = props.hasBookmarked
    bookmarksCount.value = props.bookmarks
    if (videoRef.value) {
        player = videojs(videoRef.value, {
            controls: false,
            autoplay: false,
            preload: 'none',
            loop: true,
            fluid: true,
            muted: true,
            playbackRates: [0.5, 1, 1.5, 2],
            controlBar: {
                children: [
                    'playToggle',
                    'volumePanel',
                    'progressControl',
                    'remainingTimeDisplay',
                    'playbackRateMenuButton',
                    'fullscreenToggle'
                ]
            }
        })
        player.on('loadedmetadata', () => {
            videoDuration.value = player.duration()

            const videoElement = player.tech({ IWillNotUseThisInPlugins: true }).el()

            if (videoElement) {
                videoWidth.value = videoElement.videoWidth
                videoHeight.value = videoElement.videoHeight

                const aspectRatio = videoWidth.value / videoHeight.value

                if (aspectRatio < 0.95) {
                    videoOrientation.value = 'portrait'
                } else if (aspectRatio > 1.05) {
                    videoOrientation.value = 'landscape'
                } else {
                    videoOrientation.value = 'square'
                }
            }
        })
        player.on('ready', () => {
            playerReady.value = true

            if (props.altText) {
                const el = player.el().querySelector('video')

                if (el) {
                    el.setAttribute('aria-label', props.altText)
                }
            }
        })
        player.on('play', function () {
            isPaused.value = false
            watchStartTime.value = Date.now()
            if (hasGlobalInteraction.value) {
                player.muted(isMuted.value)
            }
        })
        player.on('pause', function () {
            isPaused.value = true
            showMobilePauseButton.value = false
            if (watchStartTime.value > 0) {
                const sessionDuration = (Date.now() - watchStartTime.value) / 1000
                accumulatedWatchTime.value += sessionDuration
                watchStartTime.value = 0
            }
        })
        player.on('error', function (e) {
            console.error('Video error:', e, props.videoId)
            playerReady.value = false
        })
    }
})

const flushTracking = (reason = 'unknown') => {
    let pendingSession = 0
    if (watchStartTime.value > 0) {
        pendingSession = (Date.now() - watchStartTime.value) / 1000
    }

    const totalTime = accumulatedWatchTime.value + pendingSession

    if (totalTime > 0.5) {
        const isCompleted = videoDuration.value > 0 && totalTime >= videoDuration.value * 0.8
        recordImpression(props.videoId, totalTime, isCompleted)
    }

    accumulatedWatchTime.value = 0
    watchStartTime.value = 0
}

watch(
    () => [props.videoId, props.isSensitive],
    () => {
        isSensitiveRevealed.value = false
        pendingPlay.value = false
        pause()
    }
)

watch(showComments, async (newVal) => {
    if (newVal) {
        await commentStore.fetchComments(props.videoId, true)
    }
})

const handleScroll = async (e) => {
    const container = e.target
    const bottomReached =
        container.scrollHeight - container.scrollTop <= container.clientHeight + 100
    if (bottomReached && !isLoading.value && hasMore.value) {
        await commentStore.fetchComments(props.videoId)
    }
}

onBeforeUnmount(() => {
    flushTracking('Unmount')

    window.removeEventListener('resize', handleResize)
    if (touchTimeout.value) {
        clearTimeout(touchTimeout.value)
    }

    if (longPressTimeout.value) {
        clearTimeout(longPressTimeout.value)
    }

    if (player) {
        player.dispose()
        player = null
    }

    commentStore.clearComments(props.videoId)
})

const play = async () => {
    if (!player) return Promise.resolve()
    if (!canInteract.value) return Promise.resolve()
    try {
        isVisible.value = true
        if (!playerReady.value) {
            await new Promise((resolve) => {
                player.one('ready', resolve)
                setTimeout(resolve, 3000)
            })
        }
        if (player.readyState() < 2) {
            player.load()
            await new Promise((resolve) => {
                const checkReady = () => {
                    if (player.readyState() >= 2) resolve()
                    else setTimeout(checkReady, 50)
                }
                checkReady()
            })
        }
        player.muted(hasGlobalInteraction.value ? isMuted.value : true)
        await player.play()
        isPaused.value = false
    } catch (error) {
        try {
            player.muted(true)
            await player.play()
            isPaused.value = false
        } catch (retryError) {
            console.error('Failed to play video even when muted:', retryError)
            throw retryError
        }
    }
}

const pause = () => {
    if (player) {
        player.pause()
        showMobilePauseButton.value = false
        isPaused.value = true
    }
}

const hideUI = () => {
    if (showComments.value) showComments.value = false
    if (showMenu.value) showMenu.value = false
    showMobilePauseButton.value = false
}

const toggleComments = (e) => {
    if (e) {
        e.preventDefault()
        e.stopPropagation()
    }
    showComments.value = !showComments.value
    if (showComments.value && showMenu.value) showMenu.value = false
}

const handlePlayClick = async () => {
    if (!canInteract.value) {
        pendingPlay.value = true
        return
    }
    if (!hasGlobalInteraction.value) {
        globalHandleFirstInteraction()
        emit('interaction')
        setGlobalMuted(false)
    }
    isPaused.value = false
    await play()
}

const preload = async () => {
    if (player && player.readyState() < 1) {
        try {
            player.load()
        } catch (error) {
            console.warn('Failed to preload video:', error)
        }
    }
}

const cleanup = () => {
    if (player) player.pause()
    isVisible.value = false
    showComments.value = false
    showMenu.value = false
    showMobilePauseButton.value = false
    videoWidth.value = null
    videoHeight.value = null
    videoOrientation.value = 'portrait'
}

const onVisible = () => {
    isVisible.value = true
    accumulatedWatchTime.value = 0
    watchStartTime.value = 0
}

// const onHidden = () => {
//     isVisible.value = false
//     pause()
//     flushTracking(false)
// }

const onHidden = () => {
    // Pause internally to stop the visual playing, but don't let it mess up our math
    if (player && !player.paused()) {
        player.pause()
    }
    // Force flush immediately
    flushTracking('onHidden')
}

const handleReport = () => {
    openReportModal('video', props.videoId, window.location.href)
    showMenu.value = false
}

const formatCount = (count) => {
    if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M'
    if (count >= 1000) return (count / 1000).toFixed(1) + 'K'
    return count.toString()
}

const handleVideoDelete = async () => {
    showMenu.value = false

    const result = await confirmModal(
        t('post.deleteVideo'),
        t('post.deleteVideoConfirmMessage'),
        t('common.delete'),
        t('common.cancel')
    )
    if (result) {
        try {
            await videoStore.deleteVideoById(props.videoId)
            await nextTick()
            queryClient.invalidateQueries({ queryKey: ['feed'] })
            queryClient.invalidateQueries({ queryKey: ['following-feed'] })
            window.location.reload()
        } catch (error) {
            console.log(error)
        }
    }
}

defineExpose({
    play,
    pause,
    hideUI,
    preload,
    cleanup,
    onVisible,
    onHidden,
    playVideo: play,
    pauseVideo: pause,
    hideComments: hideUI,
    revealSensitiveContent
})
</script>

<style scoped>
@supports (-webkit-touch-callout: none) {
    .video-wrapper {
        height: calc(100vh - 70px);
        height: calc(100dvh - 70px);
    }

    @media (max-width: 767px) {
        .video-wrapper {
            height: calc(100dvh - 80px);
        }
    }

    @media (min-width: 1024px) {
        .video-wrapper {
            height: calc(100vh - 130px);
            height: calc(100dvh - 130px);
        }
    }
}

.video-wrapper {
    height: 100dvh;
}

@media (max-width: 767px) {
    .video-wrapper {
        height: calc(100dvh - 80px);
    }
}

@media (min-width: 1024px) {
    .video-wrapper {
        height: calc(100dvh - 60px);
    }
}

.video-js {
    font-size: 14px;
}

.video-js .vjs-control-bar {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.video-js .vjs-progress-control {
    position: absolute;
    width: 100%;
    bottom: 3.5em;
    height: 0.5em;
}

.video-js .vjs-progress-control:hover {
    height: 1em;
    transform: translateY(0.25em);
}

.video-js .vjs-progress-holder {
    height: 100%;
}

.video-js .vjs-play-progress {
    background-color: #fff;
}

.video-js .vjs-big-play-button {
    background-color: transparent !important;
    border: none;
    border-radius: 50%;
    width: 3em;
    height: 3em;
    line-height: 3em;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
}

.video-js:hover .vjs-big-play-button {
    background-color: transparent;
}

.video-js .vjs-volume-panel {
    margin-right: 0.5em;
}

.video-js .vjs-time-control {
    padding-left: 0.5em;
    padding-right: 0.5em;
}

.video-js .vjs-playback-rate .vjs-menu {
    width: 8em;
}

.play-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1;
}

.mobile-pause-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2;
    width: 80px;
    height: 80px;
    pointer-events: auto;
}

.video-container {
    overscroll-behavior: contain;
    touch-action: manipulation;
}

.mobile-pause-overlay {
    overscroll-behavior: contain;
    touch-action: manipulation;
}

.play-button {
    width: 60px;
    height: 60px;
    background-color: rgba(0, 0, 0, 0.4);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    padding-left: 8px;
    transition: background-color 0.2s ease;
    border: none;
    outline: none;
}

@media (min-width: 640px) {
    .play-button {
        width: 80px;
        height: 80px;
    }
}

.play-button:hover {
    background-color: rgba(0, 0, 0, 0.1);
    transition: all 0.5s ease-in;
}

.play-icon {
    width: 40px;
    height: 40px;
    color: white;
}

.mobile-interaction-btn {
    position: relative;
    z-index: 10;
    border: none;
    background: none;
    padding: 8px;
    cursor: pointer;
    outline: none;
    -webkit-tap-highlight-color: transparent;
}

.mobile-interaction-btn:active {
    transform: scale(0.95);
}

.mobile-interaction-btn i {
    display: block;
    pointer-events: none;
}

.hover\:overflow-y-auto:hover {
    scrollbar-width: thin;
    scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.hover\:overflow-y-auto:hover::-webkit-scrollbar {
    width: 6px;
}

.hover\:overflow-y-auto:hover::-webkit-scrollbar-track {
    background: transparent;
}

.hover\:overflow-y-auto:hover::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

@media (max-width: 1023px) {
    .video-container {
        border-radius: 0 !important;
        max-width: 100% !important;
        width: 100vw !important;
        height: 100dvh !important;
        aspect-ratio: unset !important;
    }

    body {
        overflow-x: hidden;
    }

    .video-js {
        width: 100% !important;
        height: 100% !important;
    }

    .mobile-interaction-btn {
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

* {
    -webkit-tap-highlight-color: transparent;
}
</style>
