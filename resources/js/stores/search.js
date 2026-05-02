import { defineStore } from 'pinia'
import axios from '~/plugins/axios'
import { ref } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'

const POLL_INTERVAL_MS = 10000
const MAX_POLL_ATTEMPTS = 60
const VALID_TABS = ['top', 'users', 'videos', 'tags', 'starter_kits']

export const useSearchStore = defineStore('search', () => {
    const searchQuery = ref('')
    const activeTab = ref('top')
    const searchResults = ref({
        hashtags: [],
        users: [],
        videos: [],
        starter_kits: []
    })
    const loading = ref(false)
    const loadingMore = ref(false)
    const error = ref(null)
    const hasMore = ref(false)
    const nextCursor = ref(null)
    const remoteLookupLoading = ref(false)
    const showRemoteLookupCta = ref(false)
    const pendingVideo = ref(null)
    let activeRequestId = 0

    let pollTimerId = null
    let pollAttempts = 0

    const setSearchQuery = (query) => {
        searchQuery.value = query
        showRemoteLookupCta.value = false
    }

    const setActiveTab = async (tab) => {
        if (!VALID_TABS.includes(tab)) {
            return
        }
        const previousTab = activeTab.value
        activeTab.value = tab
        nextCursor.value = null
        hasMore.value = false
        showRemoteLookupCta.value = false

        if (searchQuery.value && tab !== 'top' && previousTab !== tab) {
            await performSearch(false)
        }
    }

    const isRemoteQuery = (query) => {
        if (!query) return false
        const trimmed = query.trim()

        if (trimmed.startsWith('https://')) {
            return true
        }

        const webfingerPattern = /^@?[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
        return webfingerPattern.test(trimmed)
    }

    const stopPollingPendingVideo = () => {
        if (pollTimerId) {
            clearTimeout(pollTimerId)
            pollTimerId = null
        }
        pollAttempts = 0
    }

    const pollPendingVideo = async () => {
        if (!pendingVideo.value?.ap_id) {
            stopPollingPendingVideo()
            return
        }

        pollAttempts++

        if (pollAttempts > MAX_POLL_ATTEMPTS) {
            stopPollingPendingVideo()
            error.value = 'Video is taking longer than expected to process. Try again in a moment.'
            pendingVideo.value = null
            return
        }

        const axiosInstance = axios.getAxiosInstance()

        try {
            const response = await axiosInstance.get('/api/v1/search/remote/status', {
                params: { ap_id: pendingVideo.value.ap_id }
            })

            const { status, video } = response.data

            if (status === 'ready' && video) {
                searchResults.value.videos = [video]
                pendingVideo.value = null
                stopPollingPendingVideo()
                return
            }

            if (status === 'unavailable') {
                stopPollingPendingVideo()
                error.value = 'This video is not available'
                pendingVideo.value = null
                return
            }
        } catch (err) {
            if (err.response?.status === 422) {
                stopPollingPendingVideo()
                pendingVideo.value = null
                error.value = err.response?.data?.message || 'Invalid request'
                return
            }
            console.warn('Pending video poll failed, will retry:', err.message)
        }

        pollTimerId = setTimeout(pollPendingVideo, POLL_INTERVAL_MS)
    }

    const startPollingPendingVideo = () => {
        stopPollingPendingVideo()
        pollTimerId = setTimeout(pollPendingVideo, POLL_INTERVAL_MS)
    }

    const performSearch = async (append = false) => {
        if (!searchQuery.value.trim()) {
            error.value = 'Please enter a search query'
            return
        }

        if (!append) {
            stopPollingPendingVideo()
            pendingVideo.value = null
        }

        if (append) {
            loadingMore.value = true
        } else {
            loading.value = true
            nextCursor.value = null
            showRemoteLookupCta.value = false
        }

        const requestId = ++activeRequestId
        const requestQuery = searchQuery.value
        const requestTab = activeTab.value

        error.value = null
        const axiosInstance = axios.getAxiosInstance()
        try {
            const params = {
                query: searchQuery.value,
                per_page: 10
            }

            if (append && nextCursor.value) {
                params.cursor = nextCursor.value
            }

            if (requestTab !== 'top') {
                params.type = requestTab === 'tags' ? 'hashtags' : requestTab
            }

            const response = await axiosInstance.get('/api/v1/search', { params })

            if (
                requestId !== activeRequestId ||
                requestQuery !== searchQuery.value ||
                requestTab !== activeTab.value
            ) {
                return
            }

            const data = response.data

            if (append) {
                if (requestTab === 'users') {
                    searchResults.value.users = [
                        ...searchResults.value.users,
                        ...(data.data.users || [])
                    ]
                } else if (requestTab === 'videos') {
                    searchResults.value.videos = [
                        ...searchResults.value.videos,
                        ...(data.data.videos || [])
                    ]
                } else if (requestTab === 'tags') {
                    searchResults.value.hashtags = [
                        ...searchResults.value.hashtags,
                        ...(data.data.hashtags || [])
                    ]
                } else if (requestTab === 'starter_kits') {
                    searchResults.value.starter_kits = [
                        ...searchResults.value.starter_kits,
                        ...(data.data.starter_kits || [])
                    ]
                }
            } else {
                loading.value = true
                nextCursor.value = null
                showRemoteLookupCta.value = false

                if (activeTab.value === 'top') {
                    searchResults.value = {
                        hashtags: [],
                        users: [],
                        videos: [],
                        starter_kits: []
                    }
                } else if (activeTab.value === 'users') {
                    searchResults.value.users = []
                } else if (activeTab.value === 'videos') {
                    searchResults.value.videos = []
                } else if (activeTab.value === 'tags') {
                    searchResults.value.hashtags = []
                } else if (activeTab.value === 'starter_kits') {
                    searchResults.value.starter_kits = []
                }
            }

            nextCursor.value = data.meta?.next_cursor || null
            hasMore.value = !!data.meta?.next_cursor

            const hasResults =
                searchResults.value.users?.length > 0 ||
                searchResults.value.videos?.length > 0 ||
                searchResults.value.hashtags?.length > 0 ||
                searchResults.value.starter_kits?.length > 0

            if (!hasResults && isRemoteQuery(searchQuery.value)) {
                showRemoteLookupCta.value = true
            }
        } catch (err) {
            if (requestId !== activeRequestId) return
            console.error('Search error:', err)
            error.value = err.response?.data?.message || 'Failed to perform search'
        } finally {
            if (requestId === activeRequestId) {
                loading.value = false
                loadingMore.value = false
            }
        }
    }

    const performRemoteLookup = async () => {
        if (!searchQuery.value.trim()) {
            return
        }

        stopPollingPendingVideo()
        pendingVideo.value = null
        remoteLookupLoading.value = true
        showRemoteLookupCta.value = false
        error.value = null

        const requestId = ++activeRequestId
        const requestQuery = searchQuery.value
        const requestTab = activeTab.value

        const axiosInstance = axios.getAxiosInstance()
        try {
            const response = await axiosInstance.post('/api/v1/search/remote', {
                q: searchQuery.value
            })

            if (
                requestId !== activeRequestId ||
                requestQuery !== searchQuery.value ||
                requestTab !== activeTab.value
            ) {
                return
            }

            const data = response.data.data

            const users = data.users || []
            const videos = data.videos || []
            const hashtags = data.hashtags || []
            const starterKits = data.starter_kits || []
            const pending = data.pending || null

            if (pending && pending.type === 'video') {
                searchResults.value.users = []
                searchResults.value.videos = []
                searchResults.value.hashtags = []
                searchResults.value.starter_kits = []
                pendingVideo.value = pending
                activeTab.value = 'videos'
                startPollingPendingVideo()
                return
            }

            const hasResults =
                users.length || videos.length || hashtags.length || starterKits.length

            if (!hasResults) {
                error.value = 'No results found for that address'
                return
            }

            searchResults.value.users = users
            searchResults.value.videos = videos
            searchResults.value.hashtags = hashtags
            searchResults.value.starter_kits = starterKits

            if (starterKits.length) {
                activeTab.value = 'starter_kits'
            } else if (users.length) {
                activeTab.value = 'users'
            } else if (videos.length) {
                activeTab.value = 'videos'
            } else if (hashtags.length) {
                activeTab.value = 'tags'
            }
        } catch (err) {
            if (requestId !== activeRequestId) return
            console.error('Remote lookup error:', err)
            error.value =
                err.response?.data?.error?.message ||
                err.response?.data?.message ||
                'Failed to lookup remote content'
        } finally {
            if (requestId === activeRequestId) {
                remoteLookupLoading.value = false
            }
        }
    }

    const loadMore = async () => {
        if (!hasMore.value || loadingMore.value || loading.value || activeTab.value === 'top') {
            return
        }
        await performSearch(true)
    }

    const followUser = async (userId) => {
        const axiosInstance = axios.getAxiosInstance()
        try {
            await axiosInstance.post(`/api/v1/account/follow/${userId}`)
            const user = searchResults.value.users.find((u) => u.id === userId)
            if (user) {
                user.is_following = true
                user.follower_count = (user.follower_count || 0) + 1
            }
            try {
                const queryClient = useQueryClient()
                queryClient.invalidateQueries({ queryKey: ['following-feed'] })
            } catch (e) {}
            return { success: true }
        } catch (err) {
            return {
                success: false,
                error: err.response?.data?.message || 'Failed to follow user'
            }
        }
    }

    const unfollowUser = async (userId) => {
        const axiosInstance = axios.getAxiosInstance()
        try {
            await axiosInstance.post(`/api/v1/account/unfollow/${userId}`)
            const user = searchResults.value.users.find((u) => u.id === userId)
            if (user) {
                user.is_following = false
                user.follower_count = Math.max(0, (user.follower_count || 0) - 1)
            }
            try {
                const queryClient = useQueryClient()
                queryClient.invalidateQueries({ queryKey: ['following-feed'] })
            } catch (e) {}
            return { success: true }
        } catch (err) {
            return {
                success: false,
                error: err.response?.data?.message || 'Failed to unfollow user'
            }
        }
    }

    const clearSearch = () => {
        stopPollingPendingVideo()
        searchQuery.value = ''
        searchResults.value = {
            hashtags: [],
            users: [],
            videos: [],
            starter_kits: []
        }
        error.value = null
        nextCursor.value = null
        hasMore.value = false
        showRemoteLookupCta.value = false
        remoteLookupLoading.value = false
        pendingVideo.value = null
    }

    return {
        searchQuery,
        activeTab,
        searchResults,
        loading,
        loadingMore,
        error,
        hasMore,
        remoteLookupLoading,
        showRemoteLookupCta,
        pendingVideo,

        setSearchQuery,
        setActiveTab,
        performSearch,
        loadMore,
        performRemoteLookup,
        stopPollingPendingVideo,
        isRemoteQuery,
        followUser,
        unfollowUser,
        clearSearch
    }
})
