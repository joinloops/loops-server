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
                <div class="w-full h-20"></div>
            </div>

            <div v-if="profileStore.isLoadingMorePosts" class="flex justify-center py-8">
                <Spinner />
            </div>

            <div v-else-if="profileStore.relationship.blocking" class="flex justify-center py-8">
                <p class="text-gray-500 dark:text-gray-400 text-sm">You blocked this account.</p>
            </div>

            <div
                v-else-if="displayPosts && displayPosts.length > 16 && !profileStore.hasMorePosts"
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
                <div class="text-6xl mb-4">📹</div>
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
                <p
                    class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed"
                    v-html="error.message"
                ></p>

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
import ProfilePlaylists from '~/components/Profile/ProfilePlaylists.vue'
import { useProfileStore } from '~/stores/profile'
import { useAuthStore } from '~/stores/auth'
import { useUtils } from '@/composables/useUtils'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { BookmarkIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

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

let scrollTimeout = null

const handleTabChange = async (tab) => {
    currentTab.value = tab

    if (tab === 'bookmarks' && profileStore.isSelf) {
        try {
            profileStore.isLoadingMorePosts = true
            await profileStore.getBookmarkedPosts()
        } catch (error) {
            console.error('Error loading bookmarked posts:', error)
        } finally {
            profileStore.isLoadingMorePosts = false
        }
    }
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
}

const openEditProfile = () => {
    showEditModal.value = true
}

const gotoProfile = (id) => {
    showFollowersModal.value = false
    router.push(`/@${id}`)
}

const sanitize = (s) =>
    s.replace(
        /[<>&"']/g,
        (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&#39;' })[c]
    )

const loadProfileData = async (userId) => {
    try {
        isLoading.value = true
        error.value = null
        show.value = false

        await profileStore.getProfileAndPosts(userId)
    } catch (err) {
        console.error('Error loading profile:', err)

        if (err.response?.status === 404) {
            error.value = {
                type: 'not-found',
                message: t('profile.profile404ErrorMessage', {
                    userid: sanitize(userId)
                })
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

const handleScroll = () => {
    if (scrollTimeout) {
        clearTimeout(scrollTimeout)
    }

    scrollTimeout = setTimeout(() => {
        if (profileStore.isLoadingMorePosts || !profileStore.hasMorePosts) {
            return
        }

        const scrollTop = window.pageYOffset || document.documentElement.scrollTop
        const windowHeight = window.innerHeight
        const documentHeight = document.documentElement.scrollHeight

        const threshold = 300
        const distanceFromBottom = documentHeight - (scrollTop + windowHeight)

        if (distanceFromBottom < threshold) {
            loadMorePosts()
        }
    }, 100)
}

const loadMorePosts = async () => {
    if (!profileStore.id) return

    try {
        if (currentTab.value === 'bookmarks') {
            await profileStore.loadMoreBookmarkedPosts()
        } else {
            await profileStore.loadMorePosts(profileStore.id)
        }
    } catch (error) {
        console.error('Error loading more posts:', error)
    }
}

onMounted(async () => {
    await loadProfileData(route.params.id)
    window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
    if (scrollTimeout) {
        clearTimeout(scrollTimeout)
    }
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
