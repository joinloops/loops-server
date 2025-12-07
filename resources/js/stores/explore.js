import { defineStore } from 'pinia'
import { ref, computed, nextTick } from 'vue'
import axios from '~/plugins/axios'

export const useExploreStore = defineStore('explore', () => {
    const hashtags = ref([])
    const videos = ref([])
    const activeHashtag = ref(null)
    const totalResults = ref(null)
    const loading = ref(false)
    const loadingMore = ref(false)
    const error = ref(null)
    const hasMore = ref(true)
    const cursor = ref(null)

    const currentVideos = computed(() => {
        if (!activeHashtag.value) return []
        return videos.value
    })

    const fetchHashtags = async () => {
        try {
            loading.value = true
            error.value = null

            const axiosInstance = axios.getAxiosInstance()
            const res = await axiosInstance.get('/api/v1/explore/tags')

            hashtags.value = res.data.data

            if (res.data.data.length > 0) {
                activeHashtag.value = res.data.data[0]
                totalResults.value = res.data.data[0].count
                await fetchVideosByHashtag(activeHashtag.value.name)
            }
        } catch (err) {
            error.value = 'Failed to fetch hashtags'
            console.error('Error fetching hashtags:', err)
        } finally {
            loading.value = false
        }
    }

    const fetchVideosByHashtag = async (hashtagName) => {
        try {
            loading.value = true
            error.value = null

            const axiosInstance = axios.getAxiosInstance()
            const res = await axiosInstance.get(`/api/v1/explore/tag-feed/${hashtagName}`)

            videos.value = res.data.data

            cursor.value = res.data.meta?.next_cursor
            hasMore.value = res.data.meta?.next_cursor != undefined
        } catch (err) {
            error.value = 'Failed to fetch videos'
            console.error('Error fetching videos:', err)
        } finally {
            loading.value = false
        }
    }

    const setActiveHashtag = async (hashtag) => {
        if (activeHashtag.value?.id !== hashtag.id) {
            activeHashtag.value = hashtag
            totalResults.value = hashtag.count
            loadingMore.value = false
            cursor.value = null
            hasMore.value = true
            await fetchVideosByHashtag(hashtag.name)
            await nextTick()
        }
    }

    const loadMore = async () => {
        if (!activeHashtag.value || !hasMore.value || loadingMore.value) {
            return
        }

        try {
            loadingMore.value = true

            const axiosInstance = axios.getAxiosInstance()
            const res = await axiosInstance.get(
                `/api/v1/explore/tag-feed/${activeHashtag.value.name}`,
                {
                    params: {
                        cursor: cursor.value
                    }
                }
            )

            videos.value = [...videos.value, ...res.data.data]

            cursor.value = res.data.meta?.next_cursor
            hasMore.value = res.data.meta?.next_cursor != undefined
        } catch (err) {
            console.error('Error loading more videos:', err)
        } finally {
            loadingMore.value = false
        }
    }

    return {
        hashtags,
        videos,
        activeHashtag,
        totalResults,
        loading,
        loadingMore,
        error,
        hasMore,
        cursor,
        currentVideos,
        fetchHashtags,
        fetchVideosByHashtag,
        setActiveHashtag,
        loadMore
    }
})
