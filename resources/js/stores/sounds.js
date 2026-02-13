import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useSoundsStore = defineStore('sounds', () => {
    const sound = ref(null)
    const originalVideo = ref(null)
    const feed = ref([])
    const cursor = ref(null)
    const totalResults = ref(0)
    const loading = ref(false)
    const loadingMore = ref(false)
    const error = ref(false)
    const hasMore = ref(true)
    const soundKey = ref(null)

    const allVideos = computed(() => {
        if (!originalVideo.value) return feed.value

        const filteredFeed = feed.value.filter((video) => video.id !== originalVideo.value.id)

        return [originalVideo.value, ...filteredFeed]
    })

    const fetchSoundDetails = async (id) => {
        loading.value = true
        error.value = false

        try {
            const response = await axios.get(`/api/v1/sounds/details/${id}`)
            sound.value = response.data.data
            originalVideo.value = response.data.data.original_video
            soundKey.value = response.data.data.key
            totalResults.value = response.data.data.usage_count || 0

            if (soundKey.value) {
                await fetchSoundFeed(id)
            }
        } catch (err) {
            console.error('Error fetching sound details:', err)
            error.value = true
        } finally {
            loading.value = false
        }
    }

    const fetchSoundFeed = async (id) => {
        if (!soundKey.value) return

        try {
            const params = {
                key: soundKey.value
            }

            if (cursor.value) {
                params.cursor = cursor.value
            }

            const response = await axios.get(`/api/v1/sounds/feed/${id}`, { params })

            if (response.data.data) {
                feed.value = response.data.data
                cursor.value = response.data.meta?.next_cursor || null
                hasMore.value = !!response.data.meta?.next_cursor
            }
        } catch (err) {
            console.error('Error fetching sound feed:', err)
            error.value = true
        }
    }

    const loadMore = async () => {
        if (!hasMore.value || loadingMore.value || !sound.value?.id) return

        loadingMore.value = true

        try {
            const params = {
                key: soundKey.value,
                cursor: cursor.value
            }

            const response = await axios.get(`/api/v1/sounds/feed/${sound.value.id}`, { params })

            if (response.data.data) {
                feed.value = [...feed.value, ...response.data.data]
                cursor.value = response.data.meta?.next_cursor || null
                hasMore.value = !!response.data.meta?.next_cursor
            }
        } catch (err) {
            console.error('Error loading more:', err)
        } finally {
            loadingMore.value = false
        }
    }

    const reset = () => {
        sound.value = null
        originalVideo.value = null
        feed.value = []
        cursor.value = null
        totalResults.value = 0
        loading.value = false
        loadingMore.value = false
        error.value = false
        hasMore.value = true
        soundKey.value = null
    }

    return {
        sound,
        originalVideo,
        feed,
        allVideos,
        cursor,
        totalResults,
        loading,
        loadingMore,
        error,
        hasMore,
        soundKey,
        fetchSoundDetails,
        loadMore,
        reset
    }
})
