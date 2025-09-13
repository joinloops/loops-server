<template>
    <div
        class="bg-white dark:bg-slate-900 rounded-lg shadow flex flex-col h-full md:max-h-[calc(100%-320px)]"
    >
        <div class="flex-1 overflow-y-auto p-3">
            <div v-if="isLoading && !comments.length" class="p-4 text-center">
                <Spinner />
            </div>
            <div v-else-if="error" class="p-4 text-center text-red-500">
                {{ error?.message || $t("post.errorLoadingComments") }}
            </div>

            <div v-else>
                <div
                    v-if="!comments.length"
                    class="p-4 text-center text-gray-500 dark:text-slate-400"
                >
                    <div
                        v-if="!canComment"
                        class="flex flex-col items-center space-y-2"
                    >
                        <i class="bx bx-comment-x text-[36px]"></i>
                        <span>{{
                            $t("post.commentsAreDisabledForThisVideo")
                        }}</span>
                    </div>
                    <div v-else class="flex flex-col items-center space-y-2">
                        <i class="bx bx-comment-dots text-2xl"></i>
                        <span>{{ $t("post.noCommentsYet") }}</span>
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
                            {{ $t("common.loadMore") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="authStore.authenticated && canComment"
            class="p-4 border-t border-gray-200 dark:border-slate-800 flex-shrink-0"
        >
            <form
                class="relative flex items-center border border-gray-300 rounded-lg bg-gray-50 dark:bg-slate-800 dark:border-slate-700 overflow-hidden"
                @submit.prevent="handleAddComment"
            >
                <input
                    v-model="newComment"
                    class="w-full p-3 bg-transparent outline-none dark:text-slate-50 dark:bg-slate-800 border-0"
                    :placeholder="$t('post.addCommentDotDotDot')"
                    :disabled="isSubmitting || !canComment"
                    aria-busy="isSubmitting"
                />

                <div class="flex items-center px-3">
                    <EmojiPicker
                        v-model="selectedEmoji"
                        @select="onEmojiSelect"
                    >
                        <template #trigger="{ toggle }">
                            <button type="button" @click="toggle">😊</button>
                        </template>
                    </EmojiPicker>

                    <button
                        type="submit"
                        class="ml-2 text-sm text-gray-400 hover:text-red-500 cursor-pointer font-bold disabled:opacity-60 disabled:cursor-not-allowed"
                        :disabled="isSubmitting || !normalizedComment"
                    >
                        {{ $t("post.post") }}
                    </button>
                </div>
            </form>
        </div>

        <div
            v-else-if="!canComment"
            class="p-4 border-t border-gray-200 dark:border-slate-800 flex-shrink-0"
        >
            <div class="text-center text-gray-500 dark:text-slate-400 text-sm">
                {{ $t("post.commentsHaveBeenDisabled") }}
            </div>
        </div>

        <div
            v-else-if="!authStore.authenticated && canComment"
            class="p-4 border-t border-gray-200 dark:border-slate-800 flex-shrink-0"
        >
            <div class="text-center text-gray-500 dark:text-slate-400 text-sm">
                {{ $t("post.signInToLeaveAComment") }}
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, inject, nextTick } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useCommentStore } from "@/stores/comments";
import EmojiPicker from "@/components/Form/EmojiPicker.vue";
import CommentItem from "./CommentItem.vue";
import Spinner from "../Spinner.vue";

const authStore = useAuthStore();
const appStore = inject("appStore");
const videoStore = inject("videoStore");
const commentStore = useCommentStore();
const newComment = ref("");
const isSubmitting = ref(false);
const selectedEmoji = ref("");
const error = ref(null);

const currentVideo = computed(() => videoStore.currentVideo);
const videoId = computed(() => currentVideo.value?.id);
const comments = computed(() =>
    videoId.value ? commentStore.getComments(videoId.value) : [],
);
const isLoading = computed(() =>
    videoId.value ? commentStore.isLoading(videoId.value) : false,
);
const hasMore = computed(() =>
    videoId.value ? commentStore.hasMore(videoId.value) : false,
);

const canComment = computed(() => {
    return currentVideo.value?.permissions?.can_comment !== false;
});

const normalizedComment = computed(() => newComment.value.trim());

const onEmojiSelect = (emoji) => {
    newComment.value += emoji.native;
};

const pendingKey = ref(null);
const makeKey = (text, vid) => `${vid}:${text}`;
const lastSubmitted = ref({ key: null, at: 0 });
const COOLDOWN_MS = 1500;

const handleAddComment = async () => {
    const text = normalizedComment.value;
    const vid = videoId.value;

    if (!text || !vid || isSubmitting.value || !canComment.value) return;

    const key = makeKey(text, vid);
    const now = Date.now();

    if (pendingKey.value === key) return;

    if (
        lastSubmitted.value.key === key &&
        now - lastSubmitted.value.at < COOLDOWN_MS
    )
        return;

    try {
        isSubmitting.value = true;
        pendingKey.value = key;

        await nextTick();
        await commentStore.addComment(vid, text);
        await videoStore.incrementCommentCount();

        newComment.value = "";
        lastSubmitted.value = { key, at: now };
    } catch (err) {
        error.value = err;
    } finally {
        isSubmitting.value = false;
        pendingKey.value = null;
    }
};

watch(
    currentVideo,
    async (newVideo, oldVideo) => {
        if (!newVideo || newVideo.id === oldVideo?.id) return;

        if (newVideo.permissions?.can_comment !== false) {
            try {
                error.value = null;
                await commentStore.fetchComments(newVideo.id, true);
            } catch (err) {
                console.error("Error fetching comments:", err);
                error.value = err;
            }
        }
    },
    { immediate: true },
);

const loadMore = async () => {
    if (
        !videoId.value ||
        !hasMore.value ||
        isLoading.value ||
        !canComment.value
    )
        return;

    try {
        await commentStore.fetchComments(videoId.value, false);
    } catch (err) {
        console.error("Error loading more comments:", err);
        error.value = err;
    }
};
</script>
