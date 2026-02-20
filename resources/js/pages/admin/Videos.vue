<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Videos Management</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Monitor and moderate video content</p>
        </div>

        <DataTable
            title="Videos"
            :columns="columns"
            :data="videos"
            :loading="loading"
            :has-previous="pagination.prev_cursor"
            :has-next="pagination.next_cursor"
            :has-actions="false"
            :initial-search-query="searchQuery"
            :sort-options="sortOptions"
            @search="handleSearch"
            @sort="handleSort"
            @refresh="fetchVideos"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-id="{ value }">
                <router-link
                    :to="`/admin/videos/${value}`"
                    :title="value"
                    class="text-xs text-blue-500 hover:text-blue-300"
                >
                    {{ truncateMiddle(value, 12) }}
                </router-link>
            </template>

            <template #cell-video="{ item }">
                <div class="flex items-center">
                    <img
                        :src="item.media.thumbnail"
                        :alt="item.caption"
                        class="w-16 h-12 rounded mr-3 object-cover"
                    />
                    <div class="flex gap-2 flex-col">
                        <div
                            v-if="item.caption"
                            class="text-xs font-medium text-gray-900 dark:text-white max-w-xs truncate"
                        >
                            {{ item.caption }}
                        </div>
                        <div
                            v-else
                            class="text-xs font-medium italic text-gray-500 dark:text-gray-400 max-w-xs truncate"
                        >
                            Untitled Video
                        </div>
                        <div class="flex gap-2">
                            <div
                                v-if="item?.media?.duration"
                                class="flex items-center gap-1 text-gray-500 dark:text-gray-400"
                            >
                                <span class="bx bx-timer text-base"></span>
                                <span class="text-[12px]"> {{ item.media.duration }}s </span>
                            </div>
                            <span
                                v-if="item.lang"
                                class="inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-400/10 dark:text-gray-300"
                                >{{ item.lang }}</span
                            >
                            <span
                                v-if="item.permissions.can_comment"
                                class="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-400/10 dark:text-green-400 gap-1"
                                ><span class="bx bx-check"></span>Comments
                            </span>
                            <span
                                v-if="item.permissions.can_duet"
                                class="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-400/10 dark:text-green-400 gap-1"
                                ><span class="bx bx-check"></span>Duets
                            </span>
                            <span
                                v-if="item.meta.contains_ai"
                                class="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400"
                            >
                                AI
                            </span>
                            <span
                                v-if="item.meta.contains_ad"
                                class="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400"
                            >
                                Ad
                            </span>
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-account="{ item }">
                <router-link :to="`/admin/profiles/${item.account.id}`">
                    <div class="flex items-center">
                        <img
                            :src="item.account.avatar"
                            :alt="item.account.username"
                            class="w-8 h-8 rounded-full mr-2"
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                        <span>{{ item.account.username }}</span>
                    </div>
                </router-link>
            </template>

            <template #cell-stats="{ item }">
                <div class="space-y-1">
                    <div class="text-sm">Likes: {{ formatNumber(item.likes) }}</div>
                    <div class="text-sm">Comments: {{ formatNumber(item.comments) }}</div>
                </div>
            </template>

            <template #cell-created_at="{ value }">
                {{ formatRecentDate(value) }}
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import { videosApi } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'
const { truncateMiddle, formatNumber, formatDate, formatRecentDate } = useUtils()

const router = useRouter()
const route = useRoute()
const videos = ref([])
const loading = ref(false)
const pagination = ref({
    cursor: null,
    hasPrevious: false,
    hasNext: false
})

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'video', label: 'Video' },
    { key: 'account', label: 'Account' },
    { key: 'stats', label: 'Engagement' },
    { key: 'created_at', label: 'Uploaded' }
]

const searchQuery = ref(route.query.q || '')
const sortBy = ref('')
const DEBOUNCE_DELAY = 300
let searchTimeout = null

const sortOptions = [
    { name: 'Most Likes', value: 'likes_desc' },
    { name: 'Most Comments', value: 'comments_desc' },
    { name: 'Newest', value: 'created_at_desc' },
    { name: 'Oldest', value: 'created_at_asc' },
    { name: 'Unprocessed', value: 'unprocessed' },
    { name: 'Last Updated', value: 'updated_at_desc' }
]

const fetchVideos = async (cursor = null, direction = 'next') => {
    loading.value = true
    try {
        const params = { cursor, direction }

        if (searchQuery.value) {
            params.search = searchQuery.value
        }

        if (sortBy.value) {
            params.sort = sortBy.value
        }

        const response = await videosApi.getVideos(params)
        videos.value = response.data
        pagination.value = response.meta
    } catch (error) {
        console.error('Error fetching videos:', error)
    } finally {
        loading.value = false
    }
}

const watchVideo = (video) => {
    router.push(`/admin/videos/${video.id}`)
}

// Watch for URL query parameter changes
watch(
    () => route.query.q,
    (newQuery) => {
        searchQuery.value = newQuery || ''
    }
)

watch(searchQuery, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        const query = { ...route.query }
        if (newQuery) {
            query.q = newQuery
        } else {
            delete query.q
        }

        if (route.query.q !== newQuery) {
            router.replace({ query })
        }

        fetchVideos()
    }, DEBOUNCE_DELAY)
})

watch(sortBy, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchVideos()
    }, DEBOUNCE_DELAY)
})

const handleSort = (sortValue) => {
    sortBy.value = sortValue
}

const handleSearch = (query) => {
    searchQuery.value = query
}

const nextPage = () => {
    if (pagination.value.next_cursor) {
        fetchVideos(pagination.value.next_cursor, 'next')
    }
}

const previousPage = () => {
    if (pagination.value.prev_cursor) {
        fetchVideos(pagination.value.prev_cursor, 'previous')
    }
}

onMounted(() => {
    fetchVideos()
})
</script>
