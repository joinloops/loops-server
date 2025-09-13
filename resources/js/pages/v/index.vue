<template>
    <div
        class="fixed lg:flex justify-between z-50 top-0 left-0 w-full h-full dark:bg-black lg:overflow-hidden overflow-auto"
    >
        <div
            v-if="isVideoLoading"
            class="flex items-center justify-center w-full h-full bg-black"
        >
            <div class="text-center">
                <Spinner class="h-12 w-12 mx-auto mb-4" />
                <p class="text-white text-lg">
                    {{ $t("post.loadingVideoDotDotDot") }}
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

                <h2
                    class="text-2xl font-bold text-gray-900 dark:text-white mb-4"
                >
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
                        {{ $t("common.tryAgain") }}
                    </button>

                    <button
                        @click="goHome"
                        class="w-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center cursor-pointer"
                    >
                        <HomeIcon class="h-5 w-5 mr-2" />
                        {{ $t("post.goHome") }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-else-if="currentVideo"
            class="lg:w-[calc(100%-540px)] h-full relative"
        >
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

            <video
                v-if="currentVideo.video"
                class="absolute object-cover w-full my-auto z-[-1] h-screen"
                :src="currentVideo.video"
            />

            <div
                v-if="!isVideoLoaded"
                class="flex items-center justify-center bg-black h-screen lg:min-w-[480px]"
            >
                <div class="text-center">
                    <Spinner class="h-12 w-12 text-white mx-auto mb-4" />
                    <p class="text-white">
                        {{ $t("post.loadingVideoDotDotDot") }}
                    </p>
                </div>
            </div>

            <div class="bg-black lg:min-w-[480px]">
                <video
                    v-if="currentVideo.media"
                    ref="videoRef"
                    loop
                    controls
                    class="h-screen mx-auto"
                    :src="currentVideo.media.src_url"
                    @loadeddata="onVideoLoaded"
                    @error="onVideoError"
                />
            </div>
        </div>

        <div
            v-if="currentVideo && !isVideoLoading && !error"
            class="lg:max-w-[550px] relative w-full h-full bg-white dark:bg-slate-950"
        >
            <div
                class="p-3 sm:p-5 m-2 sm:m-8 mb-0 rounded-lg bg-gray-100 dark:bg-slate-900"
            >
                <div
                    class="flex items-start sm:items-center justify-between gap-3"
                >
                    <div class="flex items-center flex-1 min-w-0">
                        <router-link :to="`/@${currentVideo.account.username}`">
                            <img
                                class="rounded-full flex-shrink-0"
                                :class="{
                                    'w-10 h-10': true,
                                    'sm:w-12 sm:h-12': true,
                                }"
                                :src="currentVideo.account.avatar"
                                :alt="currentVideo.account.username"
                                @error="
                                    $event.target.src =
                                        '/storage/avatars/default.jpg'
                                "
                            />
                        </router-link>
                        <div class="ml-3 pt-0.5 min-w-0 flex-1 items-center">
                            <div
                                class="text-base sm:text-[17px] font-semibold dark:text-slate-50 mt-2 -mb-1 truncate"
                            >
                                {{ currentVideo.account.username }}
                            </div>
                            <div
                                class="text-xs sm:text-[13px] font-light flex gap-1 items-center"
                            >
                                <div class="dark:text-slate-400 truncate">
                                    {{ currentVideo.account.name }}
                                </div>
                                <div
                                    class="text-slate-400 dark:text-slate-500 leading-none"
                                >
                                    ·
                                </div>
                                <div
                                    class="dark:text-slate-400 whitespace-nowrap"
                                >
                                    {{
                                        formatTimestamp(currentVideo.created_at)
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <template v-if="authStore.isAuthenticated">
                        <button
                            v-if="userId === currentVideo.account.id"
                            @click="showEditModal = true"
                            class="flex items-center bg-[#F02C56] text-white border dark:border-red-400 hover:bg-[#F02C56]/70 rounded-md px-4 sm:px-8 py-2 sm:py-[6px] text-sm sm:text-base font-medium whitespace-nowrap flex-shrink-0 cursor-pointer"
                        >
                            {{ $t("post.edit") }}
                        </button>
                        <template v-else>
                            <button
                                v-if="!profileStore.isFollowing"
                                @click="profileStore.follow()"
                                class="flex items-center bg-[#F02C56] text-white border dark:border-red-400 hover:bg-red-600 rounded-md px-4 sm:px-8 py-2 sm:py-[6px] text-sm sm:text-base font-medium whitespace-nowrap flex-shrink-0 cursor-pointer"
                            >
                                {{ $t("common.follow") }}
                            </button>
                            <button
                                v-else
                                @click="profileStore.unfollow()"
                                class="flex items-center border-[#F02C56] text-[#F02C56] border rounded-md px-4 sm:px-8 py-2 sm:py-[6px] text-sm sm:text-base font-medium whitespace-nowrap flex-shrink-0 cursor-pointer hover:opacity-60"
                            >
                                {{ $t("common.unfollow") }}
                            </button>
                        </template>
                    </template>
                    <template v-else>
                        <button
                            @click="handleGuestFollow"
                            class="flex items-center bg-[#F02C56] text-white border dark:border-red-400 hover:bg-red-600 rounded-md px-4 sm:px-8 py-2 sm:py-[6px] text-sm sm:text-base font-medium whitespace-nowrap flex-shrink-0"
                        >
                            {{ $t("common.follow") }}
                        </button>
                    </template>
                </div>

                <div class="mt-3 sm:mt-3">
                    <p class="text-sm dark:text-slate-300 leading-relaxed">
                        {{ currentVideo.caption }}
                    </p>
                </div>

                <div
                    class="mt-3 text-xs sm:text-sm font-medium dark:text-slate-500 flex items-center"
                >
                    <MusicalNoteIcon
                        class="inline h-4 w-4 mr-1 flex-shrink-0"
                    />
                    <span class="truncate"
                        >original sound - {{ currentVideo.account.name }}</span
                    >
                </div>
            </div>

            <div class="flex items-center px-8 mt-4 justify-between">
                <div class="pb-4 text-center flex items-center">
                    <button
                        @click="isLiked ? unlikePost() : likePost()"
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
                    <span
                        class="text-sm pl-2 pr-4 text-gray-800 dark:text-slate-500 font-semibold"
                    >
                        {{ formatCount(currentVideo.likes) }}
                    </span>
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
                    <span
                        class="text-sm pl-2 pr-4 text-gray-800 dark:text-slate-500 font-semibold"
                    >
                        {{ formatCount(0) }}
                    </span>
                </div>

                <div class="pb-4 text-center flex items-center">
                    <div
                        class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:hover:bg-slate-700 dark:bg-slate-800 dark:text-slate-50 cursor-pointer"
                    >
                        <BookmarkIcon class="h-4 w-4 text-gray-400" />
                    </div>
                    <span
                        class="text-sm pl-2 pr-4 text-gray-800 dark:text-slate-500 font-semibold"
                    >
                        {{ formatCount(0) }}
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
                                    $t("common.report")
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-center px-8 mb-3">
                <UrlCopyInput :url="`${currentVideo.url}`" />
            </div>

            <div
                class="bg-[#F8F8F8] dark:bg-slate-900 z-0 w-full h-[calc(100%-10px)] border-t-2 border-gray-100 dark:border-slate-800 overflow-auto"
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
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, computed, inject } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAppStore } from "~/stores/app";
import { useAuthStore } from "~/stores/auth";
import { useVideoStore } from "~/stores/video";
import { useProfileStore } from "~/stores/profile";
import { useHashids } from "@/composables/useHashids";
import { useAlertModal } from "@/composables/useAlertModal.js";
import { useUtils } from "@/composables/useUtils";
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
} from "@heroicons/vue/24/outline";
import UrlCopyInput from "@/components/Form/UrlCopyInput.vue";
import Comments from "@/components/Status/Comments.vue";
import ReportModal from "@/components/ReportModal.vue";
import { useReportModal } from "@/composables/useReportModal";

const router = useRouter();
const route = useRoute();
const appStore = useAppStore();
const authStore = useAuthStore();
const profileStore = useProfileStore();
const videoStore = useVideoStore();
const { openReportModal } = useReportModal();
const { formatNumber, formatDate, goBack } = useUtils();
const { alertModal, confirmModal, persistentModal } = useAlertModal();
const appConfig = inject("appConfig");

const videoRef = ref(null);
const isVideoLoaded = ref(false);
const isVideoLoading = ref(true);
const showEditModal = ref(false);
const showMenu = ref(false);
const error = ref(null);

const currentVideo = computed(() => videoStore.video);
const userId = computed(() => authStore.id);

const isLiked = false;

const setError = (type, title, message) => {
    error.value = { type, title, message };
    isVideoLoading.value = false;
};

const retryLoad = async () => {
    error.value = null;
    isVideoLoading.value = true;
    await loadPost();
};

const goHome = () => {
    router.push("/");
};

const handleGuestFollow = async () => {
    const currentDomain = window.location.hostname;
    const accountHandle = `@${currentVideo.value.account.username}@${currentDomain}`;

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
    `;

    if (!window.copyToClipboard) {
        window.copyToClipboard = async (text, buttonElement) => {
            try {
                await navigator.clipboard.writeText(text);

                if (buttonElement) {
                    const originalContent = buttonElement.innerHTML;
                    buttonElement.innerHTML = `
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    `;
                    buttonElement.classList.add(
                        "bg-green-500",
                        "hover:bg-green-600",
                    );
                    buttonElement.classList.remove(
                        "bg-[#F02C56]",
                        "hover:bg-[#F02C56]/80",
                    );

                    setTimeout(() => {
                        buttonElement.innerHTML = originalContent;
                        buttonElement.classList.remove(
                            "bg-green-500",
                            "hover:bg-green-600",
                        );
                        buttonElement.classList.add(
                            "bg-[#F02C56]",
                            "hover:bg-[#F02C56]/80",
                        );
                    }, 2000);
                }
            } catch (err) {
                console.error("Failed to copy: ", err);
                const textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand("copy");
                document.body.removeChild(textArea);
            }
        };
    }

    const ctaButtons = appConfig.registration
        ? [
              {
                  text: `<div class="tracking-tight">Join Loops</div>`,
                  type: "danger",
                  callback: () => {
                      authStore.openAuthModal("register");
                  },
              },

              {
                  text: "Close",
                  type: "button",
                  callback: () => {},
              },
          ]
        : [
              {
                  text: "Close",
                  type: "button",
                  callback: () => {},
              },
          ];

    await alertModal(
        "Follow @" + currentVideo.value.account.username,
        modalBody,
        ctaButtons,
        {
            persistModal: true,
            closeOnBackdrop: true,
            closeOnEscape: true,
        },
    );
};

const handleShare = () => {
    const shareData = {
        title: "Loops",
        text: `View ${currentVideo.value.account.username}'s video on Loops`,
        url: currentVideo.value.url,
    };

    if (!navigator.canShare) {
        // todo: handle error + fallback
        return;
    }

    if (!navigator.canShare(shareData)) {
        // todo: handle error + fallback
        return;
    }

    navigator.share(shareData);
};

const loadPost = async () => {
    const { decodeHashid } = useHashids();
    const postId = decodeHashid(route.params.id);

    if (!postId) {
        setError(
            "not-found",
            "Video Not Found",
            "This video link is broken or the video has been removed.",
        );
        return;
    }

    videoStore.currentVideo = null;

    try {
        await videoStore.getVideoById(postId);
        isVideoLoading.value = false;

        if (!videoStore.currentVideo) {
            setError(
                "not-found",
                "Video Not Found",
                "This video might have been deleted or moved.",
            );
        }
    } catch (error) {
        console.error("Error loading post:", error);

        if (error?.response?.status === 400) {
            setError(
                "not-found",
                "Video Not Found",
                "This video link is broken or the video has been removed.",
            );
        } else if (error?.response?.status === 403) {
            setError(
                "private",
                "Private Video",
                "This video is private and only visible to the creator.",
            );
        } else if (error?.response?.status === 404) {
            setError(
                "not-found",
                "Video Not Found",
                "This video might have been deleted or the link is incorrect.",
            );
        } else if (!navigator.onLine) {
            setError(
                "network",
                "No Internet Connection",
                "Please check your internet connection and try again.",
            );
        } else {
            setError(
                "network",
                "Something Went Wrong",
                "Unable to load the video. Please try again later.",
            );
        }
    } finally {
        if (authStore.isAuthenticated && videoStore.currentVideo) {
            await profileStore.getProfileState(
                videoStore.currentVideo.account.id,
            );
        }
    }
};

const handleReport = async () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal("login");
        return;
    }
    showMenu.value = false;
    openReportModal("video", currentVideo.value?.id, window.location.href);
};

const onVideoLoaded = (e) => {
    if (e.target) {
        setTimeout(() => {
            isVideoLoaded.value = true;
        }, 500);
    }
};

const onVideoError = () => {
    setError(
        "network",
        "Video Unavailable",
        "This video cannot be played. It might be corrupted or temporarily unavailable.",
    );
};

onMounted(loadPost);

onBeforeUnmount(() => {
    if (videoRef.value) {
        videoRef.value.pause();
        videoRef.value.currentTime = 0;
        videoRef.value.src = "";
    }
});

watch(isVideoLoaded, (newVal) => {
    if (newVal && videoRef.value) {
        setTimeout(() => videoRef.value.play(), 500);
    }
});

const likePost = async () => {
    try {
        await videoStore.likeVideo();
    } catch (error) {
        console.log(error);
    }
};

const unlikePost = async () => {
    try {
        await videoStore.likeVideo();
    } catch (error) {
        console.log(error);
    }
};

const deletePost = async () => {
    let res = confirm("Are you sure you want to delete this post?");
    if (res) {
        try {
            await videoStore.deleteVideoById(currentVideo.value.id);
            await profileStore.getProfile(userId.value);
            router.push(`/profile/${userId.value}`);
        } catch (error) {
            console.log(error);
        }
    }
};

const handleSaveVideo = async (data) => {
    await videoStore.updateVideoStore(data);
};

const handleDeleteVideo = async () => {};

const formatCount = (count) => {
    if (!count) return "0";

    const formatter = Intl.NumberFormat("en", {
        notation: "compact",
        maximumFractionDigits: 1,
    });

    return formatter.format(count);
};

const formatTimestamp = (isoTimestamp) => {
    const date = new Date(isoTimestamp);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (isNaN(date.getTime())) {
        return "Invalid date";
    }

    if (diffInSeconds < 60) {
        return "just now";
    }

    if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes}m ago`;
    }

    if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours}h ago`;
    }

    const options = { month: "short", day: "numeric", year: "numeric" };
    return date.toLocaleDateString("en-US", options);
};
</script>
