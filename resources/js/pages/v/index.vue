<template>
    <div
        class="fixed lg:flex justify-between z-50 top-0 left-0 w-full h-full dark:bg-black lg:overflow-hidden overflow-auto"
    >
        <div v-if="isVideoLoading" class="flex items-center justify-center w-full h-full bg-black">
            <div class="text-center">
                <Spinner class="h-12 w-12 mx-auto mb-4" />
                <p class="text-white text-lg">
                    {{ $t('post.loadingVideoDotDotDot') }}
                </p>
            </div>
        </div>

        <div
            v-else-if="error"
            class="flex items-center justify-center w-full h-full bg-white dark:bg-black"
        >
            <div class="text-center max-w-md px-6">
                <div class="mx-auto mb-6">
                    <VideoCameraSlashIcon
                        v-if="error.type === 'not-found'"
                        class="h-16 w-16 text-gray-400 dark:text-gray-600 mx-auto"
                    />
                    <LockClosedIcon
                        v-else-if="error.type === 'private'"
                        class="h-16 w-16 text-gray-400 dark:text-gray-600 mx-auto"
                    />
                    <ExclamationTriangleIcon
                        v-else
                        class="h-16 w-16 text-gray-400 dark:text-gray-600 mx-auto"
                    />
                </div>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    {{ error.title }}
                </h2>
                <p class="text-gray-600 dark:text-gray-400 mb-8">
                    {{ error.message }}
                </p>

                <div class="space-y-3">
                    <button
                        @click="retryLoad"
                        v-if="error.type === 'network'"
                        class="w-full bg-[#F02C56] hover:bg-red-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center cursor-pointer"
                    >
                        <ArrowPathIcon class="h-5 w-5 mr-2" />
                        {{ $t('common.tryAgain') }}
                    </button>

                    <button
                        @click="goHome"
                        class="w-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center cursor-pointer"
                    >
                        <HomeIcon class="h-5 w-5 mr-2" />
                        {{ $t('post.goHome') }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-else-if="currentVideo && currentVideo.is_sensitive && !showSensitiveContent"
            class="flex items-center justify-center w-full h-full bg-gray-100 dark:bg-gray-900"
        >
            <div class="text-center max-w-md px-6">
                <div class="mx-auto mb-6">
                    <ExclamationTriangleIcon class="h-16 w-16 text-orange-500 mx-auto" />
                </div>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    Sensitive Content
                </h2>
                <p class="text-gray-600 dark:text-gray-400 mb-8">
                    This video may contain sensitive content that some viewers may find disturbing
                    or inappropriate. Viewer discretion is advised.
                </p>

                <div class="space-y-3">
                    <button
                        @click="handleViewSensitiveContent"
                        class="w-full bg-[#F02C56] hover:bg-red-600 text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center cursor-pointer"
                    >
                        <EyeIcon class="h-5 w-5 mr-2" />
                        View Content
                    </button>

                    <button
                        @click="goBack"
                        class="w-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center cursor-pointer"
                    >
                        <ArrowLeftIcon class="h-5 w-5 mr-2" />
                        Go Back
                    </button>
                </div>
            </div>
        </div>

        <div v-else-if="currentVideo" class="lg:w-[calc(100%-540px)] h-full relative">
            <button
                class="absolute z-20 m-5 flex items-center rounded-full bg-gray-900 dark:bg-slate-950 p-1.5 hover:bg-gray-800"
                @click="goBack"
            >
                <ChevronLeftIcon class="h-8 w-8 text-white" />
            </button>

            <img
                class="absolute bottom-[18px] right-[18px] rounded-full lg:mx-0 mx-auto z-30"
                width="50"
                src="/nav-logo.png"
            />

            <div
                v-if="currentVideo.media"
                class="absolute object-cover w-full my-auto z-[-1] h-screen bg-black bg-center bg-cover blur-2xl opacity-50"
                :style="`background-image: url(${currentVideo.media.thumbnail})`"
            ></div>

            <div
                v-if="!isVideoLoaded"
                class="flex items-center justify-center bg-black h-screen lg:min-w-[480px]"
            >
                <div class="text-center">
                    <Spinner class="h-12 w-12 text-white mx-auto mb-4" />
                    <p class="text-white">
                        {{ $t('post.loadingVideoDotDotDot') }}
                    </p>
                </div>
            </div>

            <div class="bg-black lg:min-w-[480px] relative">
                <video
                    v-if="currentVideo.media"
                    ref="videoRef"
                    loop
                    :controls="showControls"
                    playsinline
                    preload="auto"
                    class="h-screen mx-auto"
                    :class="{ 'opacity-0': !isVideoLoaded }"
                    :aria-label="currentVideo.media.alt_text"
                    :src="currentVideo.media.src_url"
                    @loadedmetadata="onVideoMetadataLoaded"
                    @canplay="onVideoCanPlay"
                    @error="onVideoError"
                    @loadstart="onVideoLoadStart"
                    @play="onVideoPlay"
                    @pause="onVideoPause"
                />

                <div
                    v-if="showPlayButton && isVideoLoaded && currentVideo.media"
                    @click="handlePlayButtonClick"
                    class="absolute inset-0 flex items-center justify-center z-10 bg-black/70 backdrop-blur-sm cursor-pointer transition-opacity duration-300 hover:bg-black/60"
                >
                    <div class="flex flex-col items-center gap-3 animate-pulse">
                        <div
                            class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm w-30 h-30 flex justify-center items-center rounded-full p-6 shadow-2xl hover:scale-110 transition-transform duration-200"
                        >
                            <PlayIcon class="h-16 w-16 text-[#F02C56] dark:text-white ml-2" />
                        </div>
                        <p class="text-white text-lg font-semibold drop-shadow-lg">
                            {{ $t('common.tapToPlay') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="
                currentVideo &&
                !isVideoLoading &&
                !error &&
                (!currentVideo.is_sensitive || showSensitiveContent)
            "
            class="lg:max-w-[550px] relative w-full h-full bg-white dark:bg-slate-950 flex flex-col"
        >
            <div class="flex-shrink-0">
                <div
                    class="p-3 sm:p-5 mx-2 mt-2 sm:mt-8 sm:mx-8 mb-0 rounded-lg bg-gray-100 dark:bg-slate-900"
                >
                    <div class="flex items-start sm:items-center justify-between gap-3">
                        <div class="flex items-center flex-1 min-w-0">
                            <router-link :to="`/@${currentVideo.account.username}`">
                                <img
                                    class="rounded-full flex-shrink-0"
                                    :class="{
                                        'w-10 h-10': true,
                                        'sm:w-12 sm:h-12': true
                                    }"
                                    :src="currentVideo.account.avatar"
                                    :alt="currentVideo.account.username"
                                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                                />
                            </router-link>
                            <div class="ml-3 min-w-0 flex flex-col justify-center">
                                <router-link :to="`/@${currentVideo.account.username}`">
                                    <div
                                        class="text-base sm:text-[17px] font-semibold dark:text-slate-50 truncate"
                                    >
                                        {{ currentVideo.account.username }}
                                    </div>
                                </router-link>
                                <div
                                    class="text-xs sm:text-[13px] font-light flex gap-1 items-center"
                                >
                                    <div class="dark:text-slate-400 truncate">
                                        {{ currentVideo.account.name }}
                                    </div>
                                    <div class="text-slate-400 dark:text-slate-500 leading-none">
                                        ·
                                    </div>
                                    <div class="dark:text-slate-400 whitespace-nowrap">
                                        {{ formatRecentDate(currentVideo.created_at) }}
                                    </div>
                                    <div
                                        v-if="currentVideo.is_edited"
                                        class="text-slate-400 dark:text-slate-500 leading-none"
                                    >
                                        ·
                                    </div>
                                    <button
                                        v-if="currentVideo?.is_edited"
                                        @click="openVideoHistory(currentVideo?.id)"
                                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 cursor-pointer"
                                    >
                                        Edited
                                    </button>
                                </div>
                            </div>
                        </div>

                        <template v-if="authStore.isAuthenticated">
                            <button
                                v-if="userId === currentVideo.account.id"
                                @click="showEditModal = true"
                                class="flex items-center bg-[#F02C56] text-white border dark:border-red-400 hover:bg-[#F02C56]/70 rounded-md px-4 sm:px-8 py-2 sm:py-[6px] text-sm sm:text-base font-medium whitespace-nowrap flex-shrink-0 cursor-pointer"
                            >
                                {{ $t('post.edit') }}
                            </button>
                            <template v-else>
                                <button
                                    v-if="!profileStore.isFollowing"
                                    @click="profileStore.follow()"
                                    class="flex items-center bg-[#F02C56] text-white border dark:border-red-400 hover:bg-red-600 rounded-md px-4 sm:px-8 py-2 sm:py-[6px] text-sm sm:text-base font-medium whitespace-nowrap flex-shrink-0 cursor-pointer"
                                >
                                    {{ $t('common.follow') }}
                                </button>
                                <button
                                    v-else
                                    @click="profileStore.unfollow()"
                                    class="flex items-center border-[#F02C56] text-[#F02C56] border rounded-md px-4 sm:px-8 py-2 sm:py-[6px] text-sm sm:text-base font-medium whitespace-nowrap flex-shrink-0 cursor-pointer hover:opacity-60"
                                >
                                    {{ $t('common.unfollow') }}
                                </button>
                            </template>
                        </template>
                        <template v-else>
                            <button
                                @click="handleGuestFollow"
                                class="flex items-center bg-[#F02C56] text-white border dark:border-red-400 hover:bg-red-600 rounded-md px-4 sm:px-8 py-2 sm:py-[6px] text-sm sm:text-base font-medium whitespace-nowrap flex-shrink-0"
                            >
                                {{ $t('common.follow') }}
                            </button>
                        </template>
                    </div>

                    <div class="mt-3 sm:mt-3">
                        <AutolinkedText
                            :caption="currentVideo?.caption"
                            :mentions="currentVideo?.mentions"
                            :tags="currentVideo?.tags"
                            :max-char-limit="140"
                        />
                    </div>

                    <div
                        v-if="currentVideo.is_sensitive"
                        class="mt-3 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200"
                    >
                        <ExclamationTriangleIcon class="h-3 w-3 mr-1" />
                        Sensitive Content
                    </div>

                    <div
                        class="mt-3 text-xs sm:text-sm font-medium dark:text-slate-500 flex items-center"
                    >
                        <MusicalNoteIcon class="inline h-4 w-4 mr-1 flex-shrink-0" />
                        <span class="truncate"
                            >original sound - {{ currentVideo.account.name }}</span
                        >
                    </div>
                    <div
                        v-if="currentVideo?.meta?.contains_ai || currentVideo?.meta?.contains_ad"
                        class="flex mt-3 gap-3"
                    >
                        <div
                            v-if="currentVideo?.meta.contains_ai"
                            class="text-xs bg-gray-200 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded inline-flex px-3 py-1"
                        >
                            Creator labelled as AI-generated
                        </div>

                        <div
                            v-if="currentVideo?.meta.contains_ad"
                            class="text-xs bg-gray-200 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded inline-flex px-3 py-1"
                        >
                            Sponsored
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-shrink-0">
                <div class="flex items-center px-8 mt-4 justify-between">
                    <div class="pb-4 text-center flex items-center">
                        <button
                            @click="currentVideo.has_liked == true ? unlikePost() : likePost()"
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:hover:bg-slate-700 dark:bg-slate-800 dark:text-slate-50 cursor-pointer"
                        >
                            <span
                                class="text-[20px]"
                                :class="
                                    currentVideo.has_liked
                                        ? 'bx bxs-heart text-red-500'
                                        : 'bx bx-heart text-gray-400 hover:text-red-500'
                                "
                            ></span>
                        </button>
                        <button
                            @click="openInteractionModal('likes')"
                            class="text-sm pl-2 pr-4 text-gray-800 dark:text-slate-500 font-semibold hover:text-[#F02C56] dark:hover:text-[#F02C56] transition-colors cursor-pointer"
                        >
                            {{ formatCount(currentVideo.likes) }}
                        </button>
                    </div>

                    <div class="pb-4 text-center flex items-center">
                        <div
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:hover:bg-slate-700 dark:bg-slate-800 dark:text-slate-50 cursor-pointer"
                        >
                            <ChatBubbleOvalLeftIcon class="h-4 w-4 text-gray-400" />
                        </div>
                        <span
                            class="text-sm pl-2 pr-4 text-gray-800 dark:text-slate-500 font-semibold"
                        >
                            {{ formatCount(currentVideo.comments) }}
                        </span>
                    </div>

                    <div class="pb-4 text-center flex items-center">
                        <button
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:hover:bg-slate-700 dark:bg-slate-800 dark:text-slate-50 cursor-pointer"
                            @click="handleShare"
                        >
                            <ShareIcon class="h-4 w-4 text-gray-400" />
                        </button>
                        <button
                            @click="openInteractionModal('shares')"
                            class="text-sm pl-2 pr-4 text-gray-800 dark:text-slate-500 font-semibold hover:text-[#F02C56] dark:hover:text-[#F02C56] transition-colors cursor-pointer"
                        >
                            {{ formatCount(currentVideo.shares) }}
                        </button>
                    </div>

                    <div class="pb-4 text-center flex items-center">
                        <button
                            class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:hover:bg-slate-700 dark:bg-slate-800 dark:text-slate-50 cursor-pointer"
                            @click="handleBookmark"
                        >
                            <SolidBookmark
                                v-if="currentVideo?.has_bookmarked"
                                class="h-4 w-4 text-red-500"
                            />
                            <BookmarkIcon v-else class="h-4 w-4 text-gray-400" />
                        </button>
                        <span
                            class="text-sm pl-2 pr-4 text-gray-800 dark:text-slate-500 font-semibold"
                        >
                            {{ formatCount(currentVideo.bookmarks) }}
                        </span>
                    </div>

                    <div
                        v-if="userId != currentVideo.account.id"
                        class="pb-4 text-center flex items-center"
                    >
                        <div class="relative">
                            <button @click="showMenu = !showMenu">
                                <div
                                    class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:hover:bg-slate-700 dark:bg-slate-800 dark:text-slate-50 cursor-pointer"
                                >
                                    <EllipsisVerticalIcon class="h-5 w-5" />
                                </div>
                            </button>

                            <div
                                v-if="showMenu"
                                class="absolute bg-white dark:bg-slate-900 rounded-lg w-[200px] shadow-xl overflow-hidden border border-gray-200 dark:border-slate-700 top-[43px] -right-2"
                            >
                                <div
                                    @click="handleReport"
                                    class="flex items-center justify-start py-3 px-4 hover:bg-gray-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                                >
                                    <FlagIcon class="h-4 w-4" />
                                    <span class="pl-2 font-semibold text-sm">{{
                                        $t('common.report')
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center px-8 mb-3">
                    <UrlCopyInput :url="`${currentVideo.url}`" />
                </div>
            </div>

            <div
                class="flex-1 min-h-0 border-t-2 border-gray-100 dark:border-slate-800 bg-[#F8F8F8] dark:bg-slate-900"
            >
                <Comments />
            </div>
        </div>

        <ReportModal />

        <EditModal
            :is-open="showEditModal"
            :video="currentVideo"
            @close="showEditModal = false"
            @save="handleSaveVideo"
            @delete="handleDeleteVideo"
        />

        <EditHistoryModal />

        <HideCommentConfirmModal />

        <InteractionModal
            :is-open="showInteractionModal"
            :video-id="currentVideo?.id"
            :initial-tab="InteractionModalTab"
            @close="closeInteractionModal"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed, inject } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '~/stores/app'
import { useAuthStore } from '~/stores/auth'
import { useVideoStore } from '~/stores/video'
import { useProfileStore } from '~/stores/profile'
import { useHashids } from '@/composables/useHashids'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useUtils } from '@/composables/useUtils'
import { useEditHistory } from '@/composables/useEditHistory'
import {
    ArrowPathIcon,
    VideoCameraSlashIcon,
    LockClosedIcon,
    ExclamationTriangleIcon,
    HomeIcon,
    ChevronLeftIcon,
    MusicalNoteIcon,
    HeartIcon,
    ChatBubbleOvalLeftIcon,
    ShareIcon,
    BookmarkIcon,
    EllipsisVerticalIcon,
    FlagIcon,
    EyeIcon,
    ArrowLeftIcon,
    PlayIcon
} from '@heroicons/vue/24/outline'
import { BookmarkIcon as SolidBookmark } from '@heroicons/vue/24/solid'
import UrlCopyInput from '@/components/Form/UrlCopyInput.vue'
import Comments from '@/components/Status/Comments.vue'
import ReportModal from '@/components/ReportModal.vue'
import EditHistoryModal from '@/components/Status/EditHistoryModal.vue'
import InteractionModal from '@/components/Status/InteractionModal.vue'
import { useReportModal } from '@/composables/useReportModal'
const { openVideoHistory } = useEditHistory()

const router = useRouter()
const route = useRoute()
const appStore = useAppStore()
const authStore = useAuthStore()
const profileStore = useProfileStore()
const videoStore = useVideoStore()
const { openReportModal } = useReportModal()
const { formatNumber, formatRecentDate, goBack, formatCount } = useUtils()
const { alertModal, confirmModal, persistentModal } = useAlertModal()
const appConfig = inject('appConfig')

const videoRef = ref(null)
const isVideoLoaded = ref(false)
const isVideoLoading = ref(true)
const showEditModal = ref(false)
const showMenu = ref(false)
const showSensitiveContent = ref(false)
const showInteractionModal = ref(false)
const InteractionModalTab = ref('likes')
const error = ref(null)
const videoLoadTimeout = ref(null)
const showPlayButton = ref(true)
const isPlaying = ref(false)
const showControls = ref(false)
const controlsTimeout = ref(null)

const currentVideo = computed(() => videoStore.video)
const userId = computed(() => authStore.id)

const openInteractionModal = (tab) => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    InteractionModalTab.value = tab
    showInteractionModal.value = true
}

const closeInteractionModal = () => {
    showInteractionModal.value = false
}

const setError = (type, title, message) => {
    error.value = { type, title, message }
    isVideoLoading.value = false
}

const retryLoad = async () => {
    error.value = null
    isVideoLoading.value = true
    isVideoLoaded.value = false
    await loadPost()
}

const goHome = () => {
    router.push('/')
}

const handleViewSensitiveContent = () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    showSensitiveContent.value = true
}

const handleGuestFollow = async () => {
    const currentDomain = window.location.hostname
    const accountHandle = `@${currentVideo.value.account.username}@${currentDomain}`

    const modalBody = `
        <div class="space-y-6">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-r from-[#F02C56] to-pink-500 mb-4">
                    <div class="bx bx-user-plus text-[30px] text-white"></div>
                </div>
                <p class="text-gray-600 dark:text-gray-300">
                    Connect with <span class="font-semibold text-[#F02C56]">@${currentVideo.value.account.username}</span> from your own Pixelfed, Mastodon, or other ActivityPub server
                </p>
            </div>

            <div class="rounded-lg p-4 border-2 border-dashed border-[#F02C56]">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center h-8 w-8 rounded-full bg-[#F02C56] text-white text-sm font-semibold">
                            1
                        </div>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">
                            Copy this handle
                        </h4>
                        <div class="mt-2 flex items-center space-x-2">
                            <div class="flex-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm">
                                <code class="text-[#F02C56] font-mono select-all" id="webfinger-handle">
                                    ${accountHandle}
                                </code>
                            </div>
                            <button
                                onclick="copyToClipboard('${accountHandle}', this)"
                                class="flex-shrink-0 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 text-gray-600 dark:text-gray-100 px-3 py-2 rounded-md text-xs font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-[#F02C56] focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-300 dark:border-gray-600">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center h-8 w-8 rounded-full bg-[#F02C56]/80 text-white text-sm font-semibold">
                            2
                        </div>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">
                            Search on your server
                        </h4>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            Paste the handle in your server's search box, then click follow
                        </p>
                    </div>
                </div>
            </div>

            <div class="hidden border-t border-gray-200 dark:border-gray-600 pt-4">
                <p class="text-center text-sm text-gray-600 dark:text-gray-300">
                    Don't have an account yet?
                    <span class="font-medium text-[#F02C56]">Join Loops today!</span>
                </p>
            </div>
        </div>
    `

    if (!window.copyToClipboard) {
        window.copyToClipboard = async (text, buttonElement) => {
            try {
                await navigator.clipboard.writeText(text)

                if (buttonElement) {
                    const originalContent = buttonElement.innerHTML
                    buttonElement.innerHTML = `
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    `
                    buttonElement.classList.add('bg-green-500', 'hover:bg-green-600')
                    buttonElement.classList.remove('bg-[#F02C56]', 'hover:bg-[#F02C56]/80')

                    setTimeout(() => {
                        buttonElement.innerHTML = originalContent
                        buttonElement.classList.remove('bg-green-500', 'hover:bg-green-600')
                        buttonElement.classList.add('bg-[#F02C56]', 'hover:bg-[#F02C56]/80')
                    }, 2000)
                }
            } catch (err) {
                console.error('Failed to copy: ', err)
                const textArea = document.createElement('textarea')
                textArea.value = text
                document.body.appendChild(textArea)
                textArea.select()
                document.execCommand('copy')
                document.body.removeChild(textArea)
            }
        }
    }

    const ctaButtons = appConfig.registration
        ? [
              {
                  text: `<div class="tracking-tight">Join Loops</div>`,
                  type: 'danger',
                  callback: () => {
                      authStore.openAuthModal('register')
                  }
              },

              {
                  text: 'Close',
                  type: 'button',
                  callback: () => {}
              }
          ]
        : [
              {
                  text: 'Close',
                  type: 'button',
                  callback: () => {}
              }
          ]

    await alertModal('Follow @' + currentVideo.value.account.username, modalBody, ctaButtons, {
        persistModal: true,
        closeOnBackdrop: true,
        closeOnEscape: true
    })
}

const handleShare = () => {
    const shareData = {
        title: 'Loops',
        text: `View ${currentVideo.value.account.username}'s video on Loops`,
        url: currentVideo.value.url
    }

    if (!navigator.canShare) {
        // todo: handle error + fallback
        return
    }

    if (!navigator.canShare(shareData)) {
        // todo: handle error + fallback
        return
    }

    navigator.share(shareData)
}

const loadPost = async () => {
    const { decodeHashid } = useHashids()
    const postId = decodeHashid(route.params.id)

    if (!postId) {
        setError(
            'not-found',
            'Video Not Found',
            'This video link is broken or the video has been removed.'
        )
        return
    }

    videoStore.currentVideo = null

    try {
        await videoStore.getVideoById(postId)
        isVideoLoading.value = false

        if (!videoStore.currentVideo) {
            setError('not-found', 'Video Not Found', 'This video might have been deleted or moved.')
        } else {
            videoLoadTimeout.value = setTimeout(() => {
                if (!isVideoLoaded.value) {
                    isVideoLoaded.value = true

                    if (!('ontouchstart' in window)) {
                        showControls.value = true
                    }
                }
            }, 3000)
        }
    } catch (error) {
        console.error('Error loading post:', error)

        if (error?.response?.status === 400) {
            setError(
                'not-found',
                'Video Not Found',
                'This video link is broken or the video has been removed.'
            )
        } else if (error?.response?.status === 403) {
            setError(
                'private',
                'Private Video',
                'This video is private and only visible to the creator.'
            )
        } else if (error?.response?.status === 404) {
            setError(
                'not-found',
                'Video Not Found',
                'This video might have been deleted or the link is incorrect.'
            )
        } else if (!navigator.onLine) {
            setError(
                'network',
                'No Internet Connection',
                'Please check your internet connection and try again.'
            )
        } else {
            setError(
                'network',
                'Something Went Wrong',
                'Unable to load the video. Please try again later.'
            )
        }
    } finally {
        if (authStore.isAuthenticated && videoStore.currentVideo) {
            await profileStore.getProfileState(videoStore.currentVideo.account.id)
        }
    }
}

const handleReport = async () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    showMenu.value = false
    openReportModal('video', currentVideo.value?.id, window.location.href)
}

const onVideoLoadStart = () => {
    console.log('Video load started')
}

const onVideoMetadataLoaded = (e) => {
    console.log('Video metadata loaded')
    if (videoLoadTimeout.value) {
        clearTimeout(videoLoadTimeout.value)
    }
    isVideoLoaded.value = true

    if (!('ontouchstart' in window)) {
        showControls.value = true
    }
}

const onVideoCanPlay = (e) => {
    console.log('Video can play')
    if (videoLoadTimeout.value) {
        clearTimeout(videoLoadTimeout.value)
    }
    if (!isVideoLoaded.value) {
        isVideoLoaded.value = true

        if (!('ontouchstart' in window)) {
            showControls.value = true
        }
    }
}

const onVideoError = (e) => {
    console.error('Video error:', e)
    if (videoLoadTimeout.value) {
        clearTimeout(videoLoadTimeout.value)
    }
    setError(
        'network',
        'Video Unavailable',
        'This video cannot be played. It might be corrupted or temporarily unavailable.'
    )
}

const onVideoPlay = () => {
    isPlaying.value = true
    showPlayButton.value = false

    if ('ontouchstart' in window) {
        if (controlsTimeout.value) {
            clearTimeout(controlsTimeout.value)
        }
        controlsTimeout.value = setTimeout(() => {
            showControls.value = true
        }, 800)
    }
}

const onVideoPause = () => {
    isPlaying.value = false
    if ('ontouchstart' in window) {
        showPlayButton.value = true
    }
}

const handlePlayButtonClick = () => {
    if (videoRef.value) {
        videoRef.value.play().catch((err) => {
            console.error('Play failed:', err)
        })
    }
}

onMounted(loadPost)

onBeforeUnmount(() => {
    if (videoLoadTimeout.value) {
        clearTimeout(videoLoadTimeout.value)
    }
    if (controlsTimeout.value) {
        clearTimeout(controlsTimeout.value)
    }
    if (videoRef.value) {
        videoRef.value.pause()
        videoRef.value.currentTime = 0
        videoRef.value.src = ''
    }
})

watch(isVideoLoaded, (newVal) => {
    if (
        newVal &&
        videoRef.value &&
        (!currentVideo.value?.is_sensitive || showSensitiveContent.value)
    ) {
    }
})

const likePost = async () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    try {
        await videoStore.likeVideo(currentVideo.value?.id)
    } catch (error) {
        console.log(error)
    }
}

const unlikePost = async () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    try {
        await videoStore.unlikeVideo(currentVideo.value?.id)
    } catch (error) {
        console.log(error)
    }
}

const handleSaveVideo = async (data) => {
    try {
        await videoStore.updateVideoStore(data)
    } catch (error) {
        await alertModal('Error', error?.response?.data?.message)
    }
}

const handleDeleteVideo = async (id) => {
    await videoStore.deleteVideoById(id)
    router.push(`/studio/posts`)
}

const handleBookmark = async () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    const state = currentVideo.value?.has_bookmarked
    try {
        if (state) {
            await videoStore.unbookmarkVideo(currentVideo.value?.id)
        } else {
            await videoStore.bookmarkVideo(currentVideo.value?.id)
        }
    } catch (error) {
        await alertModal('Error', error?.response?.data?.message)
    }
}
</script>
