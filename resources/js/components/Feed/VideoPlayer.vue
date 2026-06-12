<template>
    <div class="relative flex justify-center h-[100dvh] w-full overflow-hidden video-wrapper">
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
                        <div
                            ref="playerContainer"
                            class="clappr-wrapper h-full w-full"
                        ></div>

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

                        <!-- Commerce: Product Tags Overlay -->
                        <ProductTag
                            :products="videoProducts"
                            :current-time="currentTime"
                            :video-duration="videoDuration"
                            @interaction="handleProductInteraction"
                            class="pointer-events-auto"
                        />

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
                                    formatCount(displayCommentCount)
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
                                formatCount(displayCommentCount)
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
            @click.stop="closeComments"
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
            @wheel.stop
            @click.stop
            data-interactive="true"
        >
            <div class="flex-shrink-0 bg-gray-50 dark:bg-slate-900">
                <div
                    class="flex items-center justify-between p-4 border-b border-gray-300 dark:border-slate-700"
                >
                    <h2 class="text-lg font-semibold text-black dark:text-gray-400">
                        {{ $t('post.comments') }} ({{ formatCount(displayCommentCount) }})
                    </h2>
                    <button @click.stop="closeComments" class="text-gray-400 hover:text-gray-300">
                        <i class="bx bx-x text-[20px]" />
                    </button>
                </div>
            </div>

            <div class="flex-1 min-h-0 overflow-hidden">
                <Comments />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch, inject, nextTick } from 'vue'
import { useCommentStore } from '~/stores/comments'
import { useFeedInteraction } from '~/composables/useFeedInteraction'
import ShareModal from '@/components/Feed/ShareModal.vue'
import Comments from '@/components/Status/Comments.vue'
import Clappr from '@clappr/player'
import LoopLink from '../LoopLink.vue'
import { useReportModal } from '@/composables/useReportModal'
import { useQueryClient } from '@tanstack/vue-query'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useI18n } from 'vue-i18n'
import P2PEngineMedia from '@swarmcloud/media'

// Commerce
import ProductTag from '@/components/Commerce/ProductTag.vue'
import axios from 'axios'

const props = defineProps({
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
const playerContainer = ref(null)
const isPaused = ref(true)
const likeCount = ref(0)
const bookmarksCount = ref(0)
const videoLiked = ref(false)
const videoBookmarked = ref(false)
const isVisible = ref(false)
const playerReady = ref(false)
const isSensitiveRevealed = ref(false)
const pendingPlay = ref(false)
const { openReportModal } = useReportModal()
const queryClient = useQueryClient()
const { alertModal, confirmModal } = useAlertModal()
const { t } = useI18n()
const videoWidth = ref(null)
const videoHeight = ref(null)
const videoOrientation = ref('portrait')
const shouldShowComments = computed(() => commentStore.shouldKeepCommentsOpen)

// Commerce
const currentTime = ref(0)
const videoDuration = ref(0)
const videoProducts = ref([])
const productsLoaded = ref(false)

async function fetchVideoProducts() {
    if (productsLoaded.value) return
    try {
        const { data } = await axios.get(`/api/v1/commerce/products/by-video/${props.videoId}`)
        videoProducts.value = data.products || []
        productsLoaded.value = true
    } catch (e) {
        console.warn('Failed to fetch video products:', e)
    }
}

function handleProductInteraction(event) {
    emit('interaction', event)
}

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

const displayCommentCount = computed(() => {
    const cv = videoStore.currentVideo
    if (cv && cv.id === props.videoId && typeof cv.comments === 'number') {
        return cv.comments
    }
    return props.commentCount
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

const setCurrentVideoForComments = () => {
    videoStore.setVideo({
        id: props.videoId,
        account: {
            id: props.profileId,
            username: props.username,
            avatar: props.profileImage
        },
        permissions: {
            can_comment: props.canComment
        },
        comments: props.commentCount,
        likes: props.likes,
        bookmarks: props.bookmarks,
        has_liked: props.hasLiked,
        has_bookmarked: props.hasBookmarked
    })
}

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
        if (player && !player.isPlaying()) {
            await play()
        } else {
            pause()
        }
    }
}

const handleResize = () => {
    windowWidth.value = window.innerWidth
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
        if (newMutedState) {
            player.mute()
        } else {
            player.unmute()
        }
    }
}

const setupClappr = (p2pUrl) => {
    if (!playerContainer.value) return

    player = new Clappr.Player({
        source: p2pUrl || props.videoUrl,
        parentId: playerContainer.value,
        width: '100%',
        height: '100%',
        autoPlay: false,
        mute: true,
        loop: true,
        mediacontrol: {
            seekbar: '#FFF',
            buttons: '#FFF'
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

    player.on('play', () => {
        isPaused.value = false
        if (hasGlobalInteraction.value) {
            if (isMuted.value) {
                player.mute()
            } else {
                player.unmute()
            }
        }
    })

    player.on('pause', () => {
        isPaused.value = true
        showMobilePauseButton.value = false
    })

    player.on('error', (e) => {
        console.error('Clappr error:', e, props.videoId)
        playerReady.value = false
    })

    // Get video dimensions via container
    player.on('container:loadedmetadata', () => {
        const videoEl = player.el().querySelector('video')
        if (videoEl) {
            videoWidth.value = videoEl.videoWidth
            videoHeight.value = videoEl.videoHeight

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

    // Commerce: Track playback time + fetch products
    player.on('timeupdate', () => {
        currentTime.value = player.getCurrentTime()
        videoDuration.value = player.getDuration()
    })

    fetchVideoProducts()
}

onMounted(async () => {
    await nextTick()
    window.addEventListener('resize', handleResize)
    setCurrentVideoForComments()
    likeCount.value = props.likes
    videoLiked.value = props.hasLiked
    videoBookmarked.value = props.hasBookmarked
    bookmarksCount.value = props.bookmarks

    if (!isMobile.value) {
        await checkCommentDrawer()
    }

    // ── SwarmCloud P2P: proxy video URL ──
    let p2pUrl = props.videoUrl
    try {
        const engine = new P2PEngineMedia({
            // token: '', // Register at https://oms.cdnbye.com for production
        })
        const proxied = await engine.getProxiedUrl(props.videoUrl)
        if (proxied) p2pUrl = proxied
    } catch (e) {
        console.warn('SwarmCloud P2P init failed, using direct URL:', e)
    }

    // ── Clappr Player ──
    setupClappr(p2pUrl)
})

watch(
    () => [props.videoId, props.isSensitive],
    async () => {
        isSensitiveRevealed.value = false
        pendingPlay.value = false
        videoWidth.value = null
        videoHeight.value = null
        videoOrientation.value = 'portrait'
        pause()

        if (!isMobile.value) {
            await checkCommentDrawer()
        }
    }
)

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (touchTimeout.value) clearTimeout(touchTimeout.value)
    if (longPressTimeout.value) clearTimeout(longPressTimeout.value)
    commentStore.clearComments(props.videoId)
    if (player) {
        player.destroy()
        player = null
    }
})

const play = async () => {
    if (!isMobile.value) {
        await checkCommentDrawer()
    }
    if (!player) return Promise.resolve()
    if (!canInteract.value) return Promise.resolve()
    try {
        isVisible.value = true
        player.mute()
        player.play()
        isPaused.value = false
    } catch (error) {
        console.error('Failed to play video:', error)
    }
}

const checkCommentDrawer = async () => {
    if (shouldShowComments.value) {
        setCurrentVideoForComments()
        showComments.value = true
    } else {
        showComments.value = false
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
    if (showMenu.value) showMenu.value = false
    showMobilePauseButton.value = false

    if (!commentStore.shouldKeepCommentsOpen) {
        showComments.value = false
    }
}

const toggleComments = async (e) => {
    if (e) {
        e.preventDefault()
        e.stopPropagation()
    }

    const opening = !showComments.value
    if (opening) {
        setCurrentVideoForComments()
    }

    showComments.value = opening

    if (opening && showMenu.value) showMenu.value = false
}

const closeComments = async () => {
    showComments.value = false
    commentStore.setUserManuallyClosed(true)
    commentStore.setKeepCommentsOpen(false)
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
    // Clappr handles preloading internally
}

const cleanup = () => {
    if (player) player.pause()
    isVisible.value = false
    showMenu.value = false
    showMobilePauseButton.value = false

    if (!commentStore.shouldKeepCommentsOpen) {
        showComments.value = false
    }
}

const onVisible = () => {
    isVisible.value = true
}
const onHidden = () => {
    isVisible.value = false
    pause()
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

.clappr-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
}

.clappr-wrapper :deep(.clappr-player) {
    width: 100% !important;
    height: 100% !important;
}

.clappr-wrapper :deep(.media-control) {
    background: linear-gradient(to top, rgba(0,0,0,0.5) 0%, transparent 50%);
}

.clappr-wrapper :deep(.media-control[data-media-control="true"]) {
    opacity: 1;
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

    .clappr-wrapper {
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
