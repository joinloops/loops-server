<template>
    <FeedLayout>
        <SnapScrollFeed
            key="following-feed"
            :feed-data="feedData"
            :item-component="VideoPlayer"
            :get-item-props="getVideoProps"
            :get-item-key="getVideoKey"
            :auto-play="hasInteracted"
            :scroll-threshold="1.5"
            :snap-sensitivity="50"
            @item-visible="onVideoVisible"
            @item-hidden="onVideoHidden"
            @interaction="onUserInteraction"
        />
    </FeedLayout>
</template>

<script setup>
import { inject, computed, shallowRef, watch } from 'vue'
import { useFeed } from '~/composables/useFeed'
import { usePublicFeed } from '~/composables/usePublicFeed'
import { useFeedInteraction } from '~/composables/useFeedInteraction'
import FeedLayout from '~/layouts/FeedLayout.vue'
import SnapScrollFeed from '~/components/Feed/SnapScrollFeed.vue'
import VideoPlayer from '~/components/Feed/VideoPlayer.vue'

const authStore = inject('authStore')

const activeFeed = shallowRef(null)

// Get the global interaction state (shared with SnapScrollFeed)
const { hasInteracted, handleFirstInteraction, globalMuted } = useFeedInteraction()

watch(
    () => authStore.authenticated,
    (isAuthenticated, _, onCleanup) => {
        activeFeed.value = isAuthenticated ? useFeed() : usePublicFeed()
    },
    { immediate: true }
)

const feedData = computed(() => {
    return activeFeed.value
})

const getVideoProps = (post, index) => ({
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
    bookmarks: 0,
    shares: 0,
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
