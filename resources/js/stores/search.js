import { defineStore } from 'pinia'
import axios from '~/plugins/axios'
import { ref } from 'vue'

export const useSearchStore = defineStore('search', () => {
    const searchQuery = ref('')
    const activeTab = ref('top')
    const searchResults = ref({
        hashtags: [],
        users: [],
        videos: []
    })
    const loading = ref(false)
    const loadingMore = ref(false)
    const error = ref(null)
    const hasMore = ref(false)
    const nextCursor = ref(null)

    const setSearchQuery = (query) => {
        searchQuery.value = query
    }

    const setActiveTab = async (tab) => {
        const previousTab = activeTab.value
        activeTab.value = tab
        nextCursor.value = null
        hasMore.value = false

        if (searchQuery.value && tab !== 'top' && previousTab !== tab) {
            await performSearch(false)
        }
    }

    const performSearch = async (append = false) => {
        if (!searchQuery.value.trim()) {
            error.value = 'Please enter a search query'
            return
        }

        if (append) {
            loadingMore.value = true
        } else {
            loading.value = true
            nextCursor.value = null
        }

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

            if (activeTab.value !== 'top') {
                params.type = activeTab.value === 'tags' ? 'hashtags' : activeTab.value
            }

            const response = await axiosInstance.get('/api/v1/search', { params })

            const data = response.data

            if (append) {
                if (activeTab.value === 'users') {
                    searchResults.value.users = [
                        ...searchResults.value.users,
                        ...(data.data.users || [])
                    ]
                } else if (activeTab.value === 'videos') {
                    searchResults.value.videos = [
                        ...searchResults.value.videos,
                        ...(data.data.videos || [])
                    ]
                } else if (activeTab.value === 'tags') {
                    searchResults.value.hashtags = [
                        ...searchResults.value.hashtags,
                        ...(data.data.hashtags || [])
                    ]
                }
            } else {
                if (activeTab.value === 'users') {
                    searchResults.value.users = data.data.users || []
                } else if (activeTab.value === 'videos') {
                    searchResults.value.videos = data.data.videos || []
                } else if (activeTab.value === 'tags') {
                    searchResults.value.hashtags = data.data.hashtags || []
                } else {
                    searchResults.value = {
                        hashtags: data.data.hashtags || [],
                        users: data.data.users || [],
                        videos: data.data.videos || []
                    }
                }
            }

            nextCursor.value = data.meta?.next_cursor || null
            hasMore.value = !!data.meta?.next_cursor
        } catch (err) {
            console.error('Search error:', err)
            error.value = err.response?.data?.message || 'Failed to perform search'
        } finally {
            loading.value = false
            loadingMore.value = false
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

            return { success: true }
        } catch (err) {
            console.error('Follow error:', err)
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

            return { success: true }
        } catch (err) {
            console.error('Unfollow error:', err)
            return {
                success: false,
                error: err.response?.data?.message || 'Failed to unfollow user'
            }
        }
    }

    const clearSearch = () => {
        searchQuery.value = ''
        searchResults.value = {
            hashtags: [],
            users: [],
            videos: []
        }
        error.value = null
        nextCursor.value = null
        hasMore.value = false
    }

    return {
        searchQuery,
        activeTab,
        searchResults,
        loading,
        loadingMore,
        error,
        hasMore,

        setSearchQuery,
        setActiveTab,
        performSearch,
        loadMore,
        followUser,
        unfollowUser,
        clearSearch
    }
})
