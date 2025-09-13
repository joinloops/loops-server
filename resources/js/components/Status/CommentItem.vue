<template>
    <div class="group/comment-root pb-3">
        <div class="flex space-x-3 px-4 pt-3">
            <div v-if="comment.tombstone">
                <img
                    :src="comment.account.avatar"
                    :alt="comment.account.username"
                    class="w-10 h-10 rounded-full flex-shrink-0"
                />
            </div>
            <router-link v-else :to="`/@${comment.account.username}`">
                <img
                    :src="comment.account.avatar"
                    :alt="comment.account.username"
                    class="w-10 h-10 rounded-full flex-shrink-0"
                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                />
            </router-link>
            <div class="flex-1 min-w-0">
                <div v-if="comment.tombstone" class="mb-1">
                    <div class="flex items-center space-x-1">
                        <span
                            class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >{{ comment.account.name }}</span
                        >
                    </div>
                </div>
                <div v-else class="mb-1">
                    <router-link
                        :to="`/@${comment.account.username}`"
                        class="flex items-center space-x-1"
                    >
                        <span
                            class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >{{ comment.account.name }}</span
                        >
                        <span
                            v-if="comment.account.id == activePost.account.id"
                            class="text-sm font-medium text-[#fe2c55]"
                            >·</span
                        >
                        <span
                            v-if="comment.account.id == activePost.account.id"
                            class="text-sm font-medium text-[#fe2c55]"
                            >{{ $t("post.creator") }}</span
                        >
                    </router-link>
                </div>

                <div class="mb-2">
                    <p
                        class="text-[16px] leading-relaxed"
                        :class="[
                            comment.tombstone
                                ? 'italic text-gray-500 dark:text-gray-500'
                                : 'text-[#161823] dark:text-gray-100',
                        ]"
                    >
                        {{ isExpanded ? comment.caption : truncatedText }}
                        <button
                            v-if="comment.caption.length > MAX_CHAR_LIMIT"
                            @click="isExpanded = !isExpanded"
                            class="text-gray-500 hover:text-gray-600 text-sm ml-1 cursor-pointer"
                        >
                            {{
                                isExpanded
                                    ? $t("post.showLess")
                                    : $t("post.dotDotDotMore")
                            }}
                        </button>
                    </p>
                </div>

                <div
                    v-if="!comment.tombstone"
                    class="flex items-center justify-between"
                >
                    <div
                        class="flex items-center space-x-4 text-gray-400 text-sm"
                    >
                        <span>{{ formatContentDate(comment.created_at) }}</span>
                        <button
                            v-if="authStore.isAuthenticated"
                            @click="showReplyInput = !showReplyInput"
                            class="hover:text-gray-700 font-medium cursor-pointer"
                        >
                            {{ $t("post.reply") }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="showReplyInput && authStore.isAuthenticated"
                    class="mt-3"
                >
                    <div class="flex space-x-3">
                        <img
                            :src="authStore.user?.avatar"
                            :alt="authStore.user?.username"
                            class="w-8 h-8 rounded-full flex-shrink-0"
                            @error="
                                $event.target.src =
                                    '/storage/avatars/default.jpg'
                            "
                        />
                        <div class="flex-1">
                            <textarea
                                v-model="replyText"
                                class="w-full p-2 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-slate-300 rounded-lg resize-none text-sm focus:border-[#F02C56] focus:outline-[#F02C56] dark:focus:outline-[#F02C56]"
                                :placeholder="$t('post.writeAReplyDotDotDot')"
                                rows="2"
                            ></textarea>
                            <div class="flex justify-end mt-2">
                                <button
                                    @click="handleReply"
                                    class="px-4 py-1.5 bg-[#F02C56] hover:bg-[#F02C56]/70 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors cursor-pointer"
                                    :disabled="
                                        isSubmittingReply || !replyText.trim()
                                    "
                                >
                                    {{
                                        isSubmittingReply
                                            ? $t("post.postingDotDotDot")
                                            : $t("post.reply")
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="totalRepliesCount > 0"
                    :class="[comment.tombstone ? 'pt-0' : 'pt-4']"
                >
                    <button
                        v-if="!repliesLoaded && !isLoadingReplies"
                        @click="loadReplies"
                        class="text-sm text-gray-400 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium transition-colors flex items-center space-x-2 cursor-pointer"
                    >
                        <div
                            class="flex w-8 bg-gray-300 dark:bg-gray-700 h-[1px]"
                        ></div>
                        {{ $t("post.view") }} {{ totalRepliesCount }}
                        {{
                            totalRepliesCount === 1
                                ? $t("post.reply")
                                : $t("post.replies")
                        }}
                        <i class="bx bx-chevron-down text-lg" />
                    </button>

                    <div
                        v-if="isLoadingReplies"
                        class="text-sm text-gray-500 dark:text-gray-400 mb-3"
                    >
                        <Spinner />
                    </div>
                </div>
            </div>

            <div class="relative flex flex-col">
                <div
                    v-if="!comment.tombstone"
                    class="relative flex opacity-0 group-hover/comment-root:opacity-100 transition-opacity duration-200"
                >
                    <button
                        @click.stop="toggleDropdown"
                        class="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded"
                    >
                        <i
                            class="bx bx-dots-horizontal text-gray-400 text-lg"
                        ></i>
                    </button>

                    <div
                        v-show="showDropdown"
                        class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-900 rounded-lg shadow-lg z-10 border border-gray-200 dark:border-gray-700"
                        @click.stop
                    >
                        <router-link
                            :to="comment.url"
                            class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            {{ $t("post.permalink") }}
                        </router-link>

                        <button
                            v-if="comment.is_owner"
                            @click="handleDelete"
                            class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-800"
                            :disabled="isDeletingComment"
                        >
                            {{
                                isDeletingComment
                                    ? $t("post.deletingDotDotDot")
                                    : $t("post.delete")
                            }}
                        </button>
                        <button
                            v-else
                            @click="handleReport"
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            {{ $t("common.report") }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="!comment.tombstone"
                    class="relative flex justify-center items-center space-x-1"
                >
                    <button
                        @click="handleLike"
                        class="flex flex-col justify-center items-center space-y-1 hover:text-red-500 transition-colors"
                        :disabled="isLikeLoading"
                    >
                        <i
                            :class="heartIconClass"
                            class="text-xl transition-colors"
                        ></i>
                        <span class="text-gray-400 dark:text-gray-100">{{
                            comment.likes || 0
                        }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="repliesLoaded" class="relative px-4 pb-2 ml-[35px]">
            <div class="space-y-5">
                <NestedCommentItem
                    v-for="reply in loadedReplies"
                    :key="reply.id"
                    :comment="reply"
                    :video-id="videoId"
                    :parent-comment-id="comment.id"
                />
            </div>

            <div
                class="flex justify-content-between items-center w-full pl-4 mt-5"
            >
                <button
                    v-if="hasMoreReplies && !isLoadingReplies"
                    @click="loadMoreReplies"
                    class="text-sm text-gray-400 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium transition-colors cursor-pointer flex items-center space-x-2"
                >
                    <div
                        class="flex w-8 bg-gray-300 dark:bg-gray-700 h-[1px]"
                    ></div>
                    <span class="flex">
                        {{ $t("post.loadMoreReplies") }}
                        <i class="bx bx-chevron-down text-lg" />
                    </span>
                </button>

                <div class="flex flex-1 justify-end">
                    <button
                        @click="hideReplies"
                        class="flex items-center space-x-1 text-sm text-gray-400 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium transition-colors cursor-pointer"
                    >
                        <span>{{ $t("post.hide") }}</span>
                        <i class="bx bx-chevron-up text-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, inject } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useCommentStore } from "@/stores/comments";
import { useReportModal } from "@/composables/useReportModal";
import NestedCommentItem from "./NestedCommentItem.vue";
import { useAlertModal } from "@/composables/useAlertModal.js";
import { useUtils } from "@/composables/useUtils";

const props = defineProps({
    comment: {
        type: Object,
        required: true,
    },
    videoId: {
        type: String,
        required: true,
    },
    parentCommentId: {
        type: String,
        default: null,
    },
});

const MAX_CHAR_LIMIT = 150;

const appStore = inject("appStore");
const authStore = useAuthStore();
const videoStore = inject("videoStore");
const commentStore = useCommentStore();
const { formatContentDate } = useUtils();
const { alertModal, confirmModal } = useAlertModal();
const { openReportModal } = useReportModal();

const showReplyInput = ref(false);
const replyText = ref("");
const showDropdown = ref(false);
const isExpanded = ref(false);
const isSubmittingReply = ref(false);
const isDeletingComment = ref(false);

const truncatedText = computed(() => {
    if (props.comment.caption.length <= MAX_CHAR_LIMIT) {
        return props.comment.caption;
    }
    return props.comment.caption.slice(0, MAX_CHAR_LIMIT);
});

const activePost = computed(() => {
    return videoStore.currentVideo;
});

const loadedReplies = computed(() => {
    return commentStore.getReplies(props.videoId, props.comment.id) || [];
});

const isLoadingReplies = computed(() => {
    return commentStore.isLoadingReplies(props.videoId, props.comment.id);
});

const hasMoreReplies = computed(() => {
    return commentStore.hasMoreReplies(props.videoId, props.comment.id);
});

const repliesLoaded = computed(() => {
    return commentStore.areRepliesLoaded(props.videoId, props.comment.id);
});

const totalRepliesCount = computed(() => {
    return props.comment.replies || props.comment.children?.length || 0;
});

const isLikeLoading = computed(() => {
    return commentStore.isLikeLoading(props.videoId, props.comment.id);
});

const heartIconClass = computed(() => {
    if (isLikeLoading.value) {
        return "bx bx-loader-alt bx-spin text-gray-400";
    }

    return props.comment.liked
        ? "bx bxs-heart text-red-500"
        : "bx bx-heart text-gray-400 hover:text-red-500";
});

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value;
};

const handleClickOutside = (event) => {
    if (showDropdown.value) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});

const handleReply = async () => {
    if (!replyText.value.trim() || isSubmittingReply.value) return;

    try {
        isSubmittingReply.value = true;

        await commentStore.addCommentReply(
            props.videoId,
            props.comment.id,
            replyText.value,
        );

        await videoStore.incrementCommentCount();

        replyText.value = "";
        showReplyInput.value = false;
    } catch (error) {
        console.error("Failed to add reply:", error);
    } finally {
        isSubmittingReply.value = false;
    }
};

const loadReplies = async () => {
    if (isLoadingReplies.value) return;

    try {
        await commentStore.fetchReplies(props.videoId, props.comment.id, true);
    } catch (error) {
        console.error("Failed to load replies:", error);
    }
};

const loadMoreReplies = async () => {
    if (isLoadingReplies.value || !hasMoreReplies.value) return;

    try {
        await commentStore.fetchReplies(props.videoId, props.comment.id, false);
    } catch (error) {
        console.error("Failed to load more replies:", error);
    }
};

const hideReplies = () => {
    commentStore.clearReplies(props.videoId, props.comment.id);
};

const handleDelete = async () => {
    if (isDeletingComment.value) return;

    const result = await confirmModal(
        "Confirm",
        `Are you sure you want to delete this comment?`,
        "Delete",
        "Cancel",
    );

    if (!result) {
        showDropdown.value = false;
        return;
    }

    try {
        isDeletingComment.value = true;
        showDropdown.value = false;

        if (props.parentCommentId) {
            await commentStore.deleteCommentReply(
                props.videoId,
                props.parentCommentId,
                props.comment.id,
            );
        } else {
            await commentStore.deleteComment(props.videoId, props.comment.id);
        }

        await videoStore.decrementCommentCount();
    } catch (error) {
        console.error("Failed to delete comment:", error);
    } finally {
        isDeletingComment.value = false;
    }
};

const handleReport = () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal("login");
        return;
    }
    openReportModal("comment", props.comment.id, window.location.href);
    showDropdown.value = false;
};

const handleLike = async () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal("login");
        return;
    }

    if (isLikeLoading.value) return;

    try {
        if (props.comment.liked) {
            await commentStore.unlikeComment(props.videoId, props.comment.id);
        } else {
            await commentStore.likeComment(props.videoId, props.comment.id);
        }
    } catch (error) {
        console.error("Failed to toggle like:", error);
    }
};
</script>
