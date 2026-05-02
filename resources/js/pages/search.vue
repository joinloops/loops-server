<template>
    <MainLayout>
        <div class="min-h-screen h-full bg-gray-50 dark:bg-gray-950">
            <SearchHeader
                :query="searchQuery"
                :activeTab="activeTab"
                :results="searchResults"
                :loading="loading"
                @updateQuery="handleQueryUpdate"
                @selectTab="handleTabChange"
            />

            <div class="max-w-5xl xl:max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
                <div v-if="loading && !loadingMore" class="flex justify-center items-center py-12">
                    <Spinner />
                </div>

                <div v-else-if="error" class="py-10">
                    <div
                        class="mx-auto max-w-xl rounded-2xl border border-gray-200/70 dark:border-amber-900/60 bg-gray-50 dark:bg-amber-950/25 backdrop-blur p-6 text-center"
                        role="alert"
                        aria-live="polite"
                    >
                        <div
                            class="mx-auto mb-4 flex h-30 w-30 p-3 items-center justify-center rounded-full bg-gray-50 dark:bg-amber-400/10 ring-1 ring-gray-600/15 dark:ring-amber-400/15"
                        >
                            <ExclamationTriangleIcon class="h-full w-full text-[#F02C56] mx-auto" />
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            Something went wrong
                        </h3>

                        <p class="mt-2 text-base leading-6 text-gray-700 dark:text-gray-300">
                            {{ error }}
                        </p>

                        <div class="mt-5 flex justify-center">
                            <AnimatedButton
                                variant="primaryOutline"
                                size="sm"
                                @click="handleSearch"
                                class="w-full sm:w-auto"
                            >
                                <span class="flex items-center gap-2">
                                    <ArrowPathIcon class="h-4 w-4" />
                                    {{ $t('common.retry') }}
                                </span>
                            </AnimatedButton>
                        </div>
                    </div>
                </div>

                <div v-else-if="!searchQuery" class="text-center py-12">
                    <div class="text-gray-500 dark:text-gray-400 mb-4 text-sm">
                        Enter a search query to get started
                    </div>
                </div>

                <div v-else>
                    <div
                        v-if="showRemoteLookupCta && !remoteLookupLoading"
                        class="mb-8 rounded-2xl border border-blue-200/70 dark:border-blue-900/60 bg-gradient-to-b from-blue-50/80 to-blue-100/50 dark:from-blue-950/30 dark:to-blue-900/20 backdrop-blur p-8 lg:p-12"
                        role="region"
                        aria-label="Remote account lookup"
                    >
                        <div class="flex flex-col items-center text-center max-w-2xl mx-auto">
                            <div
                                class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 dark:from-blue-500 dark:to-blue-600 shadow-lg shadow-blue-600/20 dark:shadow-blue-500/30"
                            >
                                <GlobeAltIcon class="h-9 w-9 text-white" />
                            </div>

                            <h3
                                class="text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white mb-3"
                            >
                                {{ $t('common.searchTheFediverse') }}
                            </h3>

                            <p
                                class="text-base lg:text-lg text-gray-700 dark:text-gray-300 mb-4 max-w-xl"
                            >
                                {{ $t('common.thisContentAppearsToBeOnAnotherServer') }}
                            </p>

                            <div
                                class="mb-6 inline-flex items-center gap-2.5 rounded-xl bg-white dark:bg-white/5 px-4 py-3 ring-1 ring-black/5 dark:ring-white/10 shadow-sm"
                            >
                                <AtSymbolIcon
                                    class="h-5 w-5 text-gray-500 dark:text-gray-400 flex-shrink-0"
                                />
                                <span
                                    class="font-mono text-base lg:text-lg font-medium text-blue-700 dark:text-blue-300"
                                >
                                    {{ searchQuery }}
                                </span>
                            </div>

                            <AnimatedButton
                                @click="handleRemoteLookup"
                                size="lg"
                                class="w-full sm:w-auto min-w-[240px]"
                                :loading="remoteLookupLoading"
                                :disabled="remoteLookupLoading"
                            >
                                <span class="flex items-center justify-center gap-2.5">
                                    <MagnifyingGlassIcon class="h-5 w-5" />
                                    <span class="font-semibold">{{
                                        $t('common.searchFediverse')
                                    }}</span>
                                </span>
                            </AnimatedButton>

                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $t('common.resultsMayTakeAMomentDependingOnTheRemoteServer') }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="remoteLookupLoading"
                        class="mb-8 flex items-center justify-center py-12"
                    >
                        <div class="text-center">
                            <Spinner />
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ $t('common.searchingTheFediverseFor') }}
                                <span class="font-mono"> {{ searchQuery }}</span
                                >...
                            </p>
                        </div>
                    </div>

                    <div v-if="activeTab === 'top'" class="space-y-8">
                        <PendingRemoteVideoCard v-if="pendingVideo" :pending="pendingVideo" />

                        <section v-if="searchResults.users?.length > 0">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Users
                                </h2>
                                <button
                                    @click="handleTabChange('users')"
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    See more →
                                </button>
                            </div>
                            <UsersList :users="searchResults.users.slice(0, 3)" />
                        </section>

                        <section v-if="searchResults.videos?.length > 0">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Videos
                                </h2>
                                <button
                                    @click="handleTabChange('videos')"
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    See more →
                                </button>
                            </div>
                            <VideosGrid :videos="searchResults.videos.slice(0, 12)" />
                        </section>

                        <section v-if="searchResults.hashtags?.length > 0">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Tags
                                </h2>
                                <button
                                    @click="handleTabChange('tags')"
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    See more →
                                </button>
                            </div>
                            <HashtagsList :hashtags="searchResults.hashtags.slice(0, 10)" />
                        </section>

                        <div
                            v-if="
                                !searchResults.users?.length &&
                                !searchResults.videos?.length &&
                                !searchResults.hashtags?.length &&
                                !showRemoteLookupCta &&
                                !remoteLookupLoading &&
                                !pendingVideo
                            "
                            class="text-center py-12"
                        >
                            <div class="text-gray-500 dark:text-gray-400 mb-4 text-sm">
                                {{ $t('nav.noResultsFound') }}
                            </div>
                        </div>
                        <div class="h-50"></div>
                    </div>

                    <div v-else-if="activeTab === 'users'">
                        <UsersList
                            v-if="searchResults.users?.length > 0"
                            :users="searchResults.users"
                        />
                        <div
                            v-else-if="!showRemoteLookupCta && !remoteLookupLoading"
                            class="text-center py-12"
                        >
                            <div class="text-gray-500 dark:text-gray-400 mb-4 text-sm">
                                {{ $t('nav.noResultsFound') }}
                            </div>
                        </div>
                        <div class="h-50"></div>
                    </div>

                    <div v-else-if="activeTab === 'videos'">
                        <PendingRemoteVideoCard
                            v-if="pendingVideo"
                            :pending="pendingVideo"
                            class="mb-6"
                        />

                        <VideosGrid
                            v-if="searchResults.videos?.length > 0"
                            :videos="searchResults.videos"
                        />
                        <div v-else-if="!pendingVideo && !loading" class="text-center py-12">
                            <div class="text-gray-500 dark:text-gray-400 mb-4 text-sm">
                                {{ $t('nav.noResultsFound') }}
                            </div>
                        </div>
                        <div class="h-50"></div>
                    </div>

                    <div v-else-if="activeTab === 'tags'">
                        <HashtagsList
                            v-if="searchResults.hashtags?.length > 0"
                            :hashtags="searchResults.hashtags"
                        />
                        <div v-else-if="!loading" class="text-center py-12">
                            <div class="text-gray-500 dark:text-gray-400 mb-4 text-sm">
                                {{ $t('nav.noResultsFound') }}
                            </div>
                        </div>
                        <div class="h-50"></div>
                    </div>

                    <div v-else-if="activeTab === 'starter_kits'">
                        <StarterKitsGrid
                            v-if="searchResults.starter_kits?.length > 0"
                            :kits="searchResults.starter_kits"
                        />
                        <div v-else-if="!loading" class="text-center py-12">
                            <div class="text-gray-500 dark:text-gray-400 mb-4 text-sm">
                                {{ $t('nav.noResultsFound') }}
                            </div>
                        </div>
                        <div class="h-50"></div>
                    </div>

                    <div v-if="hasMore && activeTab !== 'top'" class="mt-6">
                        <div v-if="loadingMore" class="flex justify-center py-8">
                            <Spinner />
                        </div>
                        <div ref="loadMoreTrigger" class="h-10"></div>
                        <div
                            v-if="!hasMore && !loadingMore && getCurrentResults().length > 0"
                            class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm"
                        >
                            {{ $t('common.noMoreResults') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <GuestAuthPromptModal :show="showGuestModal" @close="showGuestModal = false" />
    </MainLayout>
</template>

<script setup>
import { onMounted, onUnmounted, inject, ref, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import GuestAuthPromptModal from '@/components/Tag/GuestAuthPromptModal.vue'
import SearchHeader from '@/components/Search/SearchHeader.vue'
import UsersList from '@/components/Search/UsersList.vue'
import VideosGrid from '@/components/Search/VideosGrid.vue'
import HashtagsList from '@/components/Search/HashtagsList.vue'
import AnimatedButton from '@/components/AnimatedButton.vue'
import {
    GlobeAltIcon,
    MagnifyingGlassIcon,
    AtSymbolIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import StarterKitsGrid from '@/components/Search/StarterKitsGrid.vue'
import PendingRemoteVideoCard from '@/components/Search/PendingRemoteVideoCard.vue'
const VALID_TABS = ['top', 'users', 'videos', 'tags', 'starter_kits']

const route = useRoute()
const router = useRouter()
const searchStore = inject('searchStore')
const authStore = inject('authStore')
const loadMoreTrigger = ref(null)
const showGuestModal = ref(false)
let observer = null

const {
    searchQuery,
    activeTab,
    searchResults,
    loading,
    loadingMore,
    error,
    hasMore,
    remoteLookupLoading,
    showRemoteLookupCta,
    pendingVideo
} = storeToRefs(searchStore)

const {
    performSearch,
    setActiveTab,
    loadMore,
    setSearchQuery,
    performRemoteLookup,
    stopPollingPendingVideo
} = searchStore

const handleQueryUpdate = (query) => {
    setSearchQuery(query)
    router.push({ query: { q: query, tab: activeTab.value } })
    if (query?.trim()) {
        performSearch()
    }
}

const handleTabChange = async (tab) => {
    await setActiveTab(tab)
    router.push({ query: { q: searchQuery.value, tab } })
}

const handleSearch = () => {
    if (searchQuery.value) {
        performSearch()
    }
}

const handleRemoteLookup = () => {
    if (!authStore.authenticated) {
        showGuestModal.value = true
        return
    }

    performRemoteLookup()
}

const getCurrentResults = () => {
    switch (activeTab.value) {
        case 'users':
            return searchResults.value.users || []
        case 'videos':
            return searchResults.value.videos || []
        case 'tags':
            return searchResults.value.hashtags || []
        case 'starter_kits':
            return searchResults.value.starter_kits || []
        default:
            return []
    }
}

const handleLoadMore = () => {
    if (!authStore.authenticated) {
        showGuestModal.value = true
        return
    }

    if (hasMore.value && !loadingMore.value && !loading.value && activeTab.value !== 'top') {
        loadMore()
    }
}

const cleanupObserver = () => {
    if (observer) {
        observer.disconnect()
        observer = null
    }
}

const setupIntersectionObserver = () => {
    if (!loadMoreTrigger.value || activeTab.value === 'top') return

    cleanupObserver()

    observer = new IntersectionObserver(
        (entries) => {
            const [entry] = entries
            if (entry.isIntersecting) {
                handleLoadMore()
            }
        },
        {
            root: null,
            rootMargin: '200px',
            threshold: 0.1
        }
    )

    observer.observe(loadMoreTrigger.value)
}

watch(
    () => route.query.q,
    (newQuery) => {
        if (newQuery && newQuery !== searchQuery.value) {
            stopPollingPendingVideo()
            setSearchQuery(newQuery)
            handleSearch()
        }
    }
)

watch(
    () => route.query.tab,
    async (newTab) => {
        if (!VALID_TABS.includes(newTab)) {
            return
        }

        if (newTab && newTab !== activeTab.value) {
            await setActiveTab(newTab)
        }
    }
)

watch(activeTab, async () => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
    cleanupObserver()

    if (activeTab.value !== 'top') {
        await nextTick()
        setTimeout(() => {
            setupIntersectionObserver()
        }, 100)
    }
})

watch(loading, async (newLoading, oldLoading) => {
    if (oldLoading && !newLoading && activeTab.value !== 'top') {
        await nextTick()
        setTimeout(() => {
            setupIntersectionObserver()
        }, 100)
    }
})

onMounted(() => {
    const queryParam = route.query.q
    const tabParam = route.query.tab || 'top'

    if (queryParam) {
        setSearchQuery(queryParam)

        if (tabParam !== 'top' && tabParam !== activeTab.value) {
            setActiveTab(tabParam)
        } else {
            setActiveTab(tabParam)
            handleSearch()
        }
    }

    if (activeTab.value !== 'top') {
        setTimeout(() => {
            setupIntersectionObserver()
        }, 100)
    }
})

onUnmounted(() => {
    cleanupObserver()
    stopPollingPendingVideo()
})
</script>
