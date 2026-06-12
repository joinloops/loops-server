<template>
    <TikTokLayout>
        <SnapScrollFeed
            v-if="!loading"
            key="explore-feed"
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

        <div v-if="loading" class="flex items-center justify-center min-h-screen bg-black">
            <Spinner />
        </div>

        <div v-else-if="currentVideos.length === 0" class="flex items-center justify-center min-h-screen bg-black">
            <div class="text-center text-white/50">
                <p class="text-lg">{{ $t('explore.noVideosFoundForThisHashtag') }}</p>
            </div>
        </div>
    </TikTokLayout>
</template>

<script setup>
import { onMounted, onUnmounted, inject, ref, watch, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useUtils } from '@/composables/useUtils'
import TikTokLayout from '~/layouts/TikTokLayout.vue'
import SnapScrollFeed from '~/components/Feed/SnapScrollFeed.vue'
import VideoPlayer from '~/components/Feed/VideoPlayer.vue'
import { useFeedInteraction } from '~/composables/useFeedInteraction'

const exploreStore = inject('exploreStore')
const authStore = inject('authStore')
const showGuestModal = ref(false)
const { formatNumber } = useUtils()
const { hasInteracted, handleFirstInteraction, globalMuted } = useFeedInteraction()

const {
    hashtags,
    activeHashtag,
    currentVideos,
    loading,
    loadingMore,
    error,
    hasMore,
    totalResults
} = storeToRefs(exploreStore)

const { fetchHashtags, setActiveHashtag, loadMore } = exploreStore

// Map explore videos to feed format
const feedData = computed(() => {
    if (!currentVideos.value || currentVideos.value.length === 0) return null
    return {
        pages: [{
            data: currentVideos.value.map(v => ({
                id: v.hid || v.id,
                media: {
                    src_url: v.media?.src_url || v.media?.url,
                    thumbnail: v.media?.thumbnail,
                    alt_text: v.media?.alt_text || ''
                },
                url: `/v/${v.hid || v.id}?by=${v.account?.username || ''}`,
                account: {
                    id: v.account?.id,
                    username: v.account?.username,
                    avatar: v.account?.avatar
                },
                caption: v.caption || v.title || '',
                tags: v.tags || [],
                mentions: v.mentions || [],
                likes: v.likes_count || v.likes || 0,
                has_liked: v.has_liked || false,
                has_bookmarked: v.has_bookmarked || false,
                bookmarks: v.bookmarks_count || v.bookmarks || 0,
                shares: v.shares_count || 0,
                comments: v.comments_count || v.comments || 0,
                permissions: {
                    can_comment: v.permissions?.can_comment ?? true
                },
                is_sensitive: v.is_sensitive || false
            })),
            meta: { next_cursor: null }
        }],
        pageParams: [null]
    }
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
    hasBookmarked: post.has_bookmarked,
    bookmarks: post.bookmarks,
    shares: post.shares,
    comments: [],
    canComment: post.permissions?.can_comment,
    'comment-count': post.comments,
    index: index,
    isSensitive: post.is_sensitive,
    altText: post.media.alt_text,
    autoPlay: hasInteracted.value,
    muted: globalMuted.value
})

const getVideoKey = (post) => post.id

const onVideoVisible = (index) => {}
const onVideoHidden = (index) => {}
const onUserInteraction = () => {
    handleFirstInteraction()
}

watch(activeHashtag, () => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
})

onMounted(() => {
    fetchHashtags()
})
</script>
