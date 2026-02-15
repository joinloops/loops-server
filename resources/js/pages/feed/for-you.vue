<template>
    <FeedLayout>
        <div
            v-if="showEmptyState"
            class="flex h-screen flex-col items-center justify-center px-10 dark:bg-black z-50"
        >
            <div class="text-6xl mb-4">🌟</div>
            <h2 class="dark:text-white text-2xl font-bold mb-2 text-center">
                You're all caught up!
            </h2>
            <p class="dark:text-white/70 text-base text-center leading-relaxed">
                We're curating more Loops for you. Check back soon.
            </p>
        </div>

        <SnapScrollFeed
            v-else
            key="for-you-feed"
            :feed-data="feedData"
            :item-component="VideoPlayerTracking"
            :get-item-props="getVideoProps"
            :get-item-key="getVideoKey"
            :auto-play="hasInteracted"
            :scroll-threshold="1.5"
            :snap-sensitivity="50"
            @item-visible="onVideoVisible"
            @item-hidden="onVideoHidden"
            @interaction="onUserInteraction"
        />
        <HideCommentConfirmModal />
    </FeedLayout>
</template>

<script setup>
import { inject, computed, shallowRef, watch } from 'vue'
import { useForYouFeed } from '~/composables/useForYouFeed'
import { usePublicFeed } from '~/composables/usePublicFeed'
import { useFeedInteraction } from '~/composables/useFeedInteraction'
import FeedLayout from '~/layouts/FeedLayout.vue'
import SnapScrollFeed from '~/components/Feed/SnapScrollFeed.vue'
import VideoPlayerTracking from '~/components/Feed/VideoPlayerTracking.vue'

const authStore = inject('authStore')

const activeFeed = shallowRef(null)

const { hasInteracted, handleFirstInteraction, globalMuted } = useFeedInteraction()

watch(
    () => authStore.authenticated,
    (isAuthenticated, _, onCleanup) => {
        activeFeed.value = isAuthenticated ? useForYouFeed() : usePublicFeed()
    },
    { immediate: true }
)

const feedData = computed(() => {
    return activeFeed.value
})

const showEmptyState = computed(() => {
    if (!feedData.value || !authStore.authenticated) {
        return false
    }

    const isSuccess = feedData.value.isSuccess?.value ?? feedData.value.isSuccess
    const isFetching = feedData.value.isFetching?.value ?? feedData.value.isFetching
    const data = feedData.value.data?.value ?? feedData.value.data
    const hasNextPage = feedData.value.hasNextPage?.value ?? feedData.value.hasNextPage

    const pages = data?.pages || []
    const firstPage = pages[0]
    const hasNoPosts = firstPage?.data?.length === 0

    return isSuccess && !isFetching && hasNoPosts && !hasNextPage
})

const getVideoProps = (post, index) => ({
    duration: post.media?.duration,
    'video-id': post.id,
    'video-url': post.media.src_url,
    'share-url': post.url,
    'profile-id': post.account.id,
    username: post.account.username,
    'profile-image': post.account.avatar,
    caption: post.caption,
    hashtags: post.tags,
    mentions: post.mentions,
    likes: post.likes,
    hasLiked: post.has_liked,
    hasBookmarked: post.has_bookmarked,
    bookmarks: post.bookmarks,
    shares: post.shares,
    comments: [],
    canComment: post.permissions?.can_comment,
    'comment-count': post.comments,
    index: index,
    isSensitive: post?.is_sensitive,
    altText: post?.media.alt_text,
    autoPlay: hasInteracted.value,
    muted: globalMuted.value
})

const getVideoKey = (post) => post.id

const onVideoVisible = (index) => {}

const onVideoHidden = (index) => {}

const onUserInteraction = () => {
    handleFirstInteraction()
}
</script>
