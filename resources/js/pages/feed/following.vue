<template>
    <FeedLayout>
        <SnapScrollFeed
            v-if="!isEmpty"
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

        <div v-else class="flex flex-col items-center justify-center min-h-screen px-6 py-12">
            <div class="text-center mb-8">
                <div
                    class="w-24 h-24 mx-auto mb-6 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center"
                >
                    <svg
                        class="w-12 h-12 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                        />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    No Posts Yet
                </h3>
                <p class="text-gray-600 dark:text-gray-400 max-w-sm mx-auto">
                    You're not following anyone yet. Follow some accounts to see their posts in your
                    feed!
                </p>

                <div v-if="showRefresh" class="mt-4 flex justify-center">
                    <button
                        type="button"
                        @click="refetch()"
                        class="flex items-center bg-[#F02C56] border border-[#F02C56] text-white rounded-lg px-5 py-2 hover:bg-[#F02C56]/80 hover:border-[#F02C5699] cursor-pointer"
                    >
                        <i class="bx bx-refresh text-[20px]"></i>
                        <div>Refresh</div>
                    </button>
                </div>
            </div>

            <SuggestedAccountsCarousel
                @account-followed="onAccountFollowed"
                @account-unfollowed="onAccountUnfollowed"
                class="w-full max-w-4xl"
            />
        </div>

        <div v-if="isLoading" class="flex items-center justify-center min-h-screen">
            <Spinner />
        </div>
    </FeedLayout>
</template>

<script setup>
import { inject, computed, ref } from 'vue'
import { useFollowingFeed } from '~/composables/useFollowingFeed'
import FeedLayout from '~/layouts/FeedLayout.vue'
import { useFeedInteraction } from '~/composables/useFeedInteraction'
import SnapScrollFeed from '~/components/Feed/SnapScrollFeed.vue'
import VideoPlayer from '~/components/Feed/VideoPlayer.vue'
import SuggestedAccountsCarousel from '~/components/SuggestedAccountsCarousel.vue'

const authStore = inject('authStore')

const showRefresh = ref(false)
const accountsFollowed = ref(0)

const { hasInteracted, handleFirstInteraction, globalMuted } = useFeedInteraction()

const { data: feedData, allPosts, isEmpty, isLoading, loadMore, refetch } = useFollowingFeed()

const authenticatedFeed = useFollowingFeed()

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

const onAccountFollowed = (accountId) => {
    accountsFollowed.value = accountsFollowed.value + 1
    showRefresh.value = true
}
const onAccountUnfollowed = (accountId) => {
    accountsFollowed.value = accountsFollowed.value - 1
    if (accountsFollowed.value == 0) {
        showRefresh.value = false
    }
}
</script>
