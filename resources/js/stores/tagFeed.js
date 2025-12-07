import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '~/plugins/axios'

export const useTagFeedStore = defineStore('tagFeed', () => {
    const tag = ref(null)
    const feed = ref([])
    const totalResults = ref(0)
    const cursor = ref(null)
    const loading = ref(false)
    const loadingMore = ref(false)
    const isNsfw = ref(false)
    const error = ref(null)
    const hasMore = ref(true)

    const fetchTagFeed = async (id, reset = true) => {
        try {
            if (reset) {
                loading.value = true
                isNsfw.value = false
                feed.value = []
                cursor.value = null
                hasMore.value = true
            } else {
                loadingMore.value = true
            }

            error.value = null
            tag.value = id

            const axiosInstance = axios.getAxiosInstance()
            const params = cursor.value ? { cursor: cursor.value } : {}

            const response = await axiosInstance.get(`/api/v1/tags/video/${id}`, { params })

            if (response.data.meta?.is_nsfw) {
                isNsfw.value = true
                loading.value = false
                return
            }

            if (reset) {
                totalResults.value = response.data.meta?.total_results
                feed.value = response.data.data
            } else {
                feed.value = [...feed.value, ...response.data.data]
            }

            cursor.value = response.data.meta?.next_cursor || null
            hasMore.value = !!cursor.value
        } catch (err) {
            error.value = 'Failed to fetch hashtags'
            console.error('Error fetching hashtags:', err)
        } finally {
            loading.value = false
            loadingMore.value = false
        }
    }

    const loadMore = async () => {
        if (!hasMore.value || loadingMore.value || loading.value) return
        await fetchTagFeed(tag.value, false)
    }

    const reset = () => {
        tag.value = null
        totalResults.value = 0
        isNsfw.value = false
        feed.value = []
        cursor.value = null
        loading.value = false
        loadingMore.value = false
        error.value = null
        hasMore.value = true
    }

    return {
        tag,
        feed,
        cursor,
        totalResults,
        loading,
        isNsfw,
        loadingMore,
        error,
        hasMore,
        fetchTagFeed,
        loadMore,
        reset
    }
})
