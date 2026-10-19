<template>
    <MainLayout>
        <div
            v-if="!isLoading && !error && profileStore.id"
            class="pt-[30px] px-5 align-center xl:max-w-7xl xl:mx-auto"
        >
            <ProfileHeader />

            <ProfileTabBar
                :show-private-tabs="
                    authStore.authenticated && profileStore.id === authStore.getUser?.id
                "
                @tab-change="handleTabChange"
                @filter-change="handleFilterChange"
                ref="tabBarRef"
            />

            <ProfilePlaylists v-if="playlists && playlists.length" :playlists="playlists" />

            <div
                v-if="show"
                class="mt-4 grid xl:grid-cols-6 lg:grid-cols-4 md:grid-cols-3 grid-cols-2 gap-3"
            >
                <div v-for="post in displayPosts" :key="post.id">
                    <ProfileVideoCard :post="post" />
                </div>

                <div
                    v-for="n in skeletonsVisible ? SKELETON_COUNT : 0"
                    :key="`skeleton-${n}`"
                    :class="[
                        'transition-opacity duration-500 ease-out',
                        skeletonsFading ? 'opacity-0' : 'opacity-100'
                    ]"
                >
                    <ProfileVideoCardSkeleton />
                </div>
            </div>

            <div v-if="show" ref="sentinelRef" class="w-full h-20"></div>

            <div
                v-if="profileStore.isLoadingMorePosts && !skeletonsVisible"
                class="flex justify-center py-8"
            >
                <Spinner />
            </div>

            <div v-else-if="profileStore.relationship.blocking" class="flex justify-center py-8">
                <p class="text-gray-500 dark:text-gray-400 text-sm">You blocked this account.</p>
            </div>

            <div
                v-else-if="
                    displayPosts &&
                    displayPosts.length > 16 &&
                    !profileStore.hasMorePosts &&
                    !skeletonsVisible
                "
                class="flex justify-center py-8"
            >
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    {{ $t('profile.noMorePostsToLoad') }}
                </p>
            </div>

            <div
                v-else-if="
                    currentTab === 'bookmarks' &&
                    displayPosts &&
                    displayPosts.length === 0 &&
                    !profileStore.isLoadingMorePosts
                "
                class="flex flex-col items-center justify-center py-16"
            >
                <div class="text-6xl mb-4">
                    <BookmarkIcon class="w-20 h-20" />
                </div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">
                    {{ $t('profile.favouritePosts') }}
                </h3>
                <p class="text-gray-500 dark:text-gray-400 text-center">
                    {{ $t('profile.yourFavouritePostsWillAppearHere') }}
                </p>
            </div>

            <div
                v-else-if="
                    displayPosts && displayPosts.length === 0 && !profileStore.isLoadingMorePosts
                "
                class="flex flex-col items-center justify-center py-16"
            >
                <div class="bg-gray-100 p-6 rounded-full mb-4 dark:bg-gray-800 dark:text-white">
                    <Squares2X2Icon class="w-13 h-13" />
                </div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">
                    {{ $t('profile.noVideosYet') }}
                </h3>
                <p class="text-gray-500 dark:text-gray-400 text-center">
                    {{
                        profileStore.isSelf
                            ? $t('profile.youHaventPostedAnyVideosYet')
                            : `@${$t('profile.userHasntPostedAnyVideosYet', { username: profileStore.username })}`
                    }}
                </p>
            </div>
        </div>

        <div v-else-if="isLoading" class="pt-[90px] px-5 overflow-hidden">
            <div class="flex flex-col items-center justify-center min-h-[400px]">
                <Spinner />
                <p class="text-gray-500 dark:text-gray-400 mt-4 text-sm">
                    {{ $t('profile.loadingProfileDotDotDot') }}
                </p>
            </div>
        </div>

        <div v-else-if="error" class="pt-[90px] px-5 overflow-hidden">
            <div
                class="flex flex-col items-center justify-center min-h-[400px] max-w-md mx-auto text-center"
            >
                <div class="text-6xl mb-6">
                    <ExclamationTriangleIcon class="size-20 text-red-500" />
                </div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-3">
                    {{
                        error.type === 'not-found'
                            ? $t('profile.profileNotFound')
                            : error.type === 'rate-limit'
                              ? 'Rate limited'
                              : $t('common.somethingWentWrong')
                    }}
                </h2>
                <I18nT
                    v-if="error.type === 'not-found'"
                    keypath="profile.profile404ErrorMessage"
                    tag="p"
                    scope="global"
                    class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed"
                >
                    <template #userId>
                        <b>{{ error.userId }}</b>
                    </template>
                </I18nT>
                <p v-else class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                    {{ error.message }}
                </p>

                <div class="flex flex-col gap-3 w-full">
                    <AnimatedButton
                        variant="primary"
                        class="w-full"
                        size="lg"
                        pill
                        @click="retryLoad"
                        :disabled="retryLoading"
                    >
                        <div class="flex items-center gap-3">
                            <Spinner v-if="retryLoading" class="w-4 h-4 mr-2" />
                            <span>{{
                                retryLoading
                                    ? $t('common.retryingDotDotDot')
                                    : $t('common.tryAgain')
                            }}</span>
                        </div>
                    </AnimatedButton>

                    <AnimatedButton
                        variant="light"
                        class="w-full"
                        size="lg"
                        pill
                        @click="$router.push('/')"
                    >
                        {{ $t('common.goToHome') }}
                    </AnimatedButton>
                </div>
            </div>
        </div>
        <ReportModal />
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import MainLayout from '~/layouts/MainLayout.vue'
import ProfileVideoCard from '~/components/Profile/ProfileVideoCard.vue'
import ProfileVideoCardSkeleton from '~/components/Profile/ProfileVideoCardSkeleton.vue'
import ProfilePlaylists from '~/components/Profile/ProfilePlaylists.vue'
import { useProfileStore } from '~/stores/profile'
import { useAuthStore } from '~/stores/auth'
import { useUtils } from '@/composables/useUtils'
import { useI18n, Translation as I18nT } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { BookmarkIcon, ExclamationTriangleIcon, Squares2X2Icon } from '@heroicons/vue/24/outline'

const { formatCount } = useUtils()
const authStore = useAuthStore()
const profileStore = useProfileStore()

const route = useRoute()
const router = useRouter()

const { t } = useI18n()
const show = ref(false)
const showFollowersModal = ref(false)
const showEditModal = ref(false)
const isLoading = ref(false)
const error = ref(null)
const retryLoading = ref(false)
const currentTab = ref('videos')
const currentFilter = ref('Latest')
const tabBarRef = ref(null)

const SKELETON_COUNT = 20
const FILL_THRESHOLD = 300
const MAX_FILL_PASSES = 5
const SKELETON_FADE_MS = 500

const sentinelRef = ref(null)
const skeletonsVisible = ref(false)
const skeletonsFading = ref(false)

let sentinelObserver = null
let skeletonTimer = null
let resizeTimer = null
let isFilling = false

const { posts, allLikes, bookmarkedPosts, playlists } = storeToRefs(profileStore)

const displayPosts = computed(() => {
    if (currentTab.value === 'bookmarks') {
        return bookmarkedPosts.value || []
    }
    return posts.value || []
})

const metaTitle = computed(() => {
    if (!profileStore.name) return 'Loops'
    return `${profileStore.name} (@${profileStore.username}) | Loops`
})

const metaDescription = computed(() => {
    if (!profileStore.username) return 'Watch videos on Loops'

    const parts = []

    if (profileStore.bio) {
        parts.push(profileStore.bio)
    }

    const stats = [
        `${formatCount(profileStore.postCount)} videos`,
        `${formatCount(profileStore.followerCount)} followers`,
        `${formatCount(profileStore.allLikes)} likes`
    ]

    parts.push(stats.join(' · '))

    return parts.join(' | ')
})

const profileUrl = computed(() => {
    if (!profileStore.username) return ''
    return `${window.location.origin}/@${profileStore.username}`
})

const profileAvatar = computed(() => {
    return profileStore.avatar || '/storage/avatars/default.jpg'
})

useHead({
    title: metaTitle,
    meta: [
        {
            name: 'description',
            content: metaDescription
        },
        {
            property: 'og:title',
            content: metaTitle
        },
        {
            property: 'og:description',
            content: metaDescription
        },
        {
            property: 'og:image',
            content: profileAvatar
        },
        {
            property: 'og:url',
            content: profileUrl
        },
        {
            property: 'og:type',
            content: 'profile'
        },
        {
            property: 'profile:username',
            content: () => profileStore.username || ''
        },
        {
            name: 'twitter:card',
            content: 'summary'
        },
        {
            name: 'twitter:title',
            content: metaTitle
        },
        {
            name: 'twitter:description',
            content: metaDescription
        },
        {
            name: 'twitter:image',
            content: profileAvatar
        }
    ]
})

const revealSkeletons = () => {
    if (skeletonTimer) {
        clearTimeout(skeletonTimer)
        skeletonTimer = null
    }
    skeletonsFading.value = false
    skeletonsVisible.value = true
}

const dismissSkeletons = () => {
    if (!skeletonsVisible.value) return
    skeletonsFading.value = true
    skeletonTimer = setTimeout(() => {
        skeletonsVisible.value = false
        skeletonsFading.value = false
        skeletonTimer = null
    }, SKELETON_FADE_MS)
}

const isPageScrollable = () => {
    return document.documentElement.scrollHeight > window.innerHeight + FILL_THRESHOLD
}

const loadMorePosts = async () => {
    if (!profileStore.id) return

    try {
        if (currentTab.value === 'bookmarks') {
            await profileStore.loadMoreBookmarkedPosts()
        } else {
            await profileStore.loadMorePosts(profileStore.id)
        }
    } catch (err) {
        console.error('Error loading more posts:', err)
    }
}

const ensureViewportFilled = async () => {
    if (isFilling) return
    isFilling = true

    try {
        let passes = 0

        while (passes < MAX_FILL_PASSES && profileStore.hasMorePosts && !isPageScrollable()) {
            passes++
            revealSkeletons()

            const before = displayPosts.value.length
            await loadMorePosts()
            await nextTick()
            await new Promise((resolve) => requestAnimationFrame(resolve))

            if (displayPosts.value.length === before) break
        }
    } finally {
        dismissSkeletons()
        isFilling = false
    }
}

const handleSentinel = async (entries) => {
    if (!entries[0]?.isIntersecting) return
    if (isFilling || profileStore.isLoadingMorePosts || !profileStore.hasMorePosts) return

    revealSkeletons()
    await loadMorePosts()
    await nextTick()
    await ensureViewportFilled()
    dismissSkeletons()
}

const teardownObserver = () => {
    if (sentinelObserver) {
        sentinelObserver.disconnect()
        sentinelObserver = null
    }
}

const setupObserver = () => {
    teardownObserver()
    if (!sentinelRef.value) return

    sentinelObserver = new IntersectionObserver(handleSentinel, {
        rootMargin: '600px 0px',
        threshold: 0
    })

    sentinelObserver.observe(sentinelRef.value)
}

const handleResize = () => {
    if (resizeTimer) clearTimeout(resizeTimer)
    resizeTimer = setTimeout(() => ensureViewportFilled(), 200)
}

const handleTabChange = async (tab) => {
    currentTab.value = tab

    if (tab === 'bookmarks' && profileStore.isSelf) {
        try {
            profileStore.isLoadingMorePosts = true
            await profileStore.getBookmarkedPosts()
        } catch (err) {
            console.error('Error loading bookmarked posts:', err)
        } finally {
            profileStore.isLoadingMorePosts = false
        }
    }

    await nextTick()
    await ensureViewportFilled()
}

const handleFilterChange = async (filter) => {
    profileStore.isLoadingMorePosts = true
    currentFilter.value = filter

    if (currentTab.value === 'bookmarks') {
        await profileStore.getBookmarkedPosts(filter).finally(() => {
            profileStore.isLoadingMorePosts = false
        })
    } else {
        await profileStore.updateSort(filter).finally(() => {
            profileStore.isLoadingMorePosts = false
        })
    }

    await nextTick()
    await ensureViewportFilled()
}

const openEditProfile = () => {
    showEditModal.value = true
}

const gotoProfile = (id) => {
    showFollowersModal.value = false
    router.push(`/@${id}`)
}

const loadProfileData = async (userId) => {
    try {
        isLoading.value = true
        error.value = null
        show.value = false

        await profileStore.getProfileAndPosts(userId)
    } catch (err) {
        console.error('Error loading profile:', err)

        if (err.response?.status === 404 && err.response?.data?.moved_to) {
            router.replace(`/@${err.response.data.moved_to}`)
            return
        }

        if (err.response?.status === 404) {
            error.value = {
                type: 'not-found',
                userId
            }
        } else if ([500, 502, 503].includes(err.response?.status)) {
            error.value = {
                type: 'server-error',
                message: t('profile.profile500ErrorMessage')
            }
        } else if (!navigator.onLine) {
            error.value = {
                type: 'network-error',
                message: t('profile.profileOfflineErrorMessage')
            }
        } else if (err.response?.status === 429) {
            error.value = {
                type: 'rate-limit',
                message: 'You have been rate limited. Please try again later.'
            }
        } else {
            error.value = {
                type: 'unknown-error',
                message: t('profile.profileUnknownErrorMessage')
            }
        }
    } finally {
        isLoading.value = false
    }
}

const retryLoad = async () => {
    retryLoading.value = true
    try {
        await loadProfileData(route.params.id)
    } finally {
        retryLoading.value = false
    }
}

onMounted(async () => {
    await loadProfileData(route.params.id)
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    teardownObserver()
    window.removeEventListener('resize', handleResize)
    if (skeletonTimer) clearTimeout(skeletonTimer)
    if (resizeTimer) clearTimeout(resizeTimer)
})

watch(sentinelRef, async (el) => {
    if (!el) {
        teardownObserver()
        return
    }
    setupObserver()
    await ensureViewportFilled()
})

watch(
    () => route.params.id,
    (newId) => {
        if (newId) {
            loadProfileData(newId)
            currentTab.value = 'videos'
        }
    }
)

watch(
    () => displayPosts.value,
    () => {
        setTimeout(() => (show.value = true), 300)
    }
)
</script>
