<template>
    <div class="flex space-x-3 pl-4 group/nested-comment">
        <router-link :to="`/@${comment.account.username}`">
            <img
                :src="comment.account.avatar"
                :alt="comment.account.username"
                class="w-6 h-6 rounded-full flex-shrink-0"
                @error="$event.target.src = '/storage/avatars/default.jpg'"
            />
        </router-link>
        <div class="flex-1 min-w-0">
            <div class="mb-1">
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
                    class="text-[16px] text-gray-900 dark:text-gray-100 leading-relaxed break-all"
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

            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4 text-gray-500 text-sm">
                    <span>{{ formatContentDate(comment.created_at) }}</span>
                </div>
            </div>
        </div>

        <div class="relative flex flex-col">
            <div
                class="relative flex opacity-0 group-hover/nested-comment:opacity-100 transition-opacity duration-200"
            >
                <button
                    @click.stop="toggleDropdown"
                    class="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded"
                >
                    <i class="bx bx-dots-horizontal text-gray-400 text-lg"></i>
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

            <div class="relative flex justify-center items-center space-x-1">
                <button
                    @click="handleLike"
                    class="flex flex-col justify-center items-center space-y-1 hover:text-red-500 transition-colors"
                    :disabled="isLikeLoading"
                >
                    <i
                        :class="heartIconClass"
                        class="text-lg transition-colors"
                    ></i>
                    <span class="text-gray-400 dark:text-gray-100 text-xs">{{
                        comment.likes || 0
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, inject } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useCommentStore } from "@/stores/comments";
import { useUtils } from "@/composables/useUtils";
import { useAlertModal } from "@/composables/useAlertModal.js";
import { useReportModal } from "@/composables/useReportModal";

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
        required: true,
    },
});

const MAX_CHAR_LIMIT = 100;

const authStore = useAuthStore();
const commentStore = useCommentStore();
const appStore = inject("appStore");
const videoStore = inject("videoStore");
const { formatContentDate } = useUtils();
const { alertModal, confirmModal } = useAlertModal();
const { openReportModal } = useReportModal();

const showDropdown = ref(false);
const isExpanded = ref(false);
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

        await commentStore.deleteCommentReply(
            props.videoId,
            props.parentCommentId,
            props.comment.id,
        );
        await videoStore.decrementCommentCount();
    } catch (error) {
        console.error("Failed to delete reply:", error);
    } finally {
        isDeletingComment.value = false;
    }
};

const handleReport = () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal("login");
        return;
    }
    openReportModal("reply", props.comment.id, window.location.href);
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
            await commentStore.unlikeNestedComment(
                props.videoId,
                props.parentCommentId,
                props.comment.id,
            );
        } else {
            await commentStore.likeNestedComment(
                props.videoId,
                props.parentCommentId,
                props.comment.id,
            );
        }
    } catch (error) {
        console.error("Failed to toggle like on nested comment:", error);
    }
};
</script>
