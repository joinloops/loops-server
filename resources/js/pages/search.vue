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

            <div class="max-w-5xl px-4 sm:px-6 lg:px-8 py-6">
                <div v-if="loading && !loadingMore" class="flex justify-center items-center py-12">
                    <Spinner />
                </div>

                <div v-else-if="error" class="text-center py-12">
                    <div class="text-red-600 dark:text-red-400 mb-4">
                        {{ error }}
                    </div>
                    <button
                        @click="handleSearch"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        {{ $t('common.retry') }}
                    </button>
                </div>

                <div v-else-if="!searchQuery" class="text-center py-12">
                    <div class="text-gray-500 dark:text-gray-400 mb-4 text-sm">
                        Enter a search query to get started
                    </div>
                </div>

                <div v-else>
                    <div v-if="activeTab === 'top'" class="space-y-8">
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
                                !searchResults.hashtags?.length
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
                        <div v-else class="text-center py-12">
                            <div class="text-gray-500 dark:text-gray-400 mb-4 text-sm">
                                {{ $t('nav.noResultsFound') }}
                            </div>
                        </div>
                        <div class="h-50"></div>
                    </div>

                    <div v-else-if="activeTab === 'videos'">
                        <VideosGrid
                            v-if="searchResults.videos?.length > 0"
                            :videos="searchResults.videos"
                        />
                        <div v-else class="text-center py-12">
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
                        <div v-else class="text-center py-12">
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

const route = useRoute()
const router = useRouter()
const searchStore = inject('searchStore')
const authStore = inject('authStore')
const loadMoreTrigger = ref(null)
const showGuestModal = ref(false)
let observer = null

const { searchQuery, activeTab, searchResults, loading, loadingMore, error, hasMore } =
    storeToRefs(searchStore)

const { performSearch, setActiveTab, loadMore, setSearchQuery } = searchStore

const handleQueryUpdate = (query) => {
    setSearchQuery(query)
    router.push({ query: { q: query, tab: activeTab.value } })
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

const getCurrentResults = () => {
    switch (activeTab.value) {
        case 'users':
            return searchResults.value.users || []
        case 'videos':
            return searchResults.value.videos || []
        case 'tags':
            return searchResults.value.hashtags || []
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
            setSearchQuery(newQuery)
            handleSearch()
        }
    }
)

watch(
    () => route.query.tab,
    async (newTab) => {
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
        setActiveTab(tabParam)
        handleSearch()
    }

    if (activeTab.value !== 'top') {
        setTimeout(() => {
            setupIntersectionObserver()
        }, 100)
    }
})

onUnmounted(() => {
    cleanupObserver()
})
</script>
