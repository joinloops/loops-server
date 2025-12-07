<template>
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6"
        >
            <div class="p-6">
                <div
                    class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0"
                >
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        <div class="text-xl font-bold dark:text-white">
                            {{ $t('studio.myPosts') }}
                        </div>
                    </div>

                    <div class="relative">
                        <div
                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                        >
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <input
                            v-model="searchQuery"
                            @input="handleSearch"
                            type="text"
                            :placeholder="$t('studio.searchByPostCaption')"
                            class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent w-full sm:w-80"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                            >
                                {{ $t('studio.contentCreatedOn') }}
                                <button
                                    @click="sortBy('created_at')"
                                    class="ml-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    <ArrowsUpDownIcon class="w-4 h-4" />
                                </button>
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                            >
                                {{ $t('common.status') }}
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                            >
                                {{ $t('studio.likes') }}
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                            >
                                {{ $t('studio.comments') }}
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                            >
                                {{ $t('studio.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <tr
                            v-for="post in posts"
                            :key="post.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <img
                                        :src="post.media.thumbnail"
                                        :alt="`${post.caption} thumbnail`"
                                        class="w-12 h-12 rounded-lg object-cover"
                                        onerror="
                                            this.src = '/storage/videos/video-placeholder.jpg'
                                            this.onerror = null
                                        "
                                    />
                                    <div>
                                        <div
                                            class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                        >
                                            {{ post.caption }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(post.created_at) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="getStatusBadgeClass(post.status)">
                                    {{ post.status.charAt(0).toUpperCase() + post.status.slice(1) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                {{ post.likes.toLocaleString() }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                {{ post.comments.toLocaleString() }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-5">
                                    <router-link
                                        v-if="post.status === 'published'"
                                        :to="`/v/${post.hid}`"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium transition-colors cursor-pointer"
                                    >
                                        {{ $t('studio.view') }}
                                    </router-link>
                                    <button
                                        v-if="post.status === 'published'"
                                        @click="editPost(post)"
                                        class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300 text-sm font-medium transition-colors cursor-pointer"
                                    >
                                        {{ $t('common.edit') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!loading && posts.length === 0" class="text-center py-16">
                <div class="mx-auto w-32 h-32 mb-6">
                    <i class="bx bx-video text-[150px]"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    {{ $t('common.noPostsYet') }}
                </h3>
                <p class="text-gray-500 dark:text-gray-400 mb-6">
                    {{ $t('studio.yourPostedAndProcessingVideos') }}
                </p>
                <button
                    @click="uploadVideo"
                    class="bg-red-500 hover:bg-red-600 text-white px-8 py-3 rounded-lg font-medium transition-colors"
                >
                    {{ $t('studio.uploadFirstVideo') }}
                </button>
            </div>

            <div v-if="loading" class="text-center py-16">
                <div
                    class="animate-spin mx-auto w-8 h-8 border-4 border-gray-300 border-t-red-500 rounded-full mb-4"
                ></div>
                <p class="text-gray-500 dark:text-gray-400">Loading posts...</p>
            </div>
        </div>

        <div v-if="!loading && posts.length > 0" class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ showingFrom }} to {{ showingTo }} of {{ totalCount }} results
            </div>
            <div class="flex space-x-2">
                <button
                    @click="previousPage"
                    :disabled="!canGoPrevious"
                    class="px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                >
                    Previous
                </button>
                <button
                    @click="nextPage"
                    :disabled="!canGoNext"
                    class="px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                >
                    Next
                </button>
            </div>
        </div>

        <EditModal
            :is-open="showEditModal"
            :video="currentVideo"
            @close="showEditModal = false"
            @save="handleSaveVideo"
            @delete="handleDeleteVideo"
        />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, inject } from 'vue'
import { useUtils } from '@/composables/useUtils'
import {
    EyeIcon,
    HeartIcon,
    ChatBubbleLeftIcon,
    LockClosedIcon,
    MagnifyingGlassIcon,
    ArrowsUpDownIcon,
    MoonIcon,
    SunIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    apiBase: {
        type: String,
        default: '/api/v1/studio/posts'
    }
})

const activeTab = ref('views')
const isDarkMode = ref(false)
const loading = ref(false)
const posts = ref([])
const searchQuery = ref('')
const sortField = ref('created_at')
const sortDirection = ref('desc')
const axios = inject('axios')
const authStore = inject('authStore')
const videoStore = inject('videoStore')
const { formatContentDate, formatDate } = useUtils()
const showEditModal = ref(false)
const currentVideo = computed(() => videoStore.video)

const filters = reactive({
    privacy: '',
    date: '',
    search: ''
})

const pagination = reactive({
    currentCursor: null,
    prevCursors: [],
    hasMore: false,
    hasPrevious: false,
    total: 0,
    perPage: 10,
    currentPage: 1
})

const showingFrom = computed(() => {
    if (posts.value.length === 0) return 0
    return (pagination.currentPage - 1) * pagination.perPage + 1
})

const showingTo = computed(() => {
    return Math.min(pagination.currentPage * pagination.perPage, pagination.total)
})

const totalCount = computed(() => pagination.total)

const canGoPrevious = computed(() => pagination.hasPrevious)
const canGoNext = computed(() => pagination.hasMore)

const debounce = (func, wait) => {
    let timeout
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout)
            func(...args)
        }
        clearTimeout(timeout)
        timeout = setTimeout(later, wait)
    }
}

const fetchPosts = async (cursor = null, limit = 10, searchFilters = {}) => {
    const response = await axios.get(props.apiBase, {
        params: {
            cursor,
            limit,
            search: searchFilters.search,
            privacy: searchFilters.privacy,
            date_filter: searchFilters.date,
            sort_field: sortField.value,
            sort_direction: sortDirection.value
        }
    })
    return response.data
}

const loadPosts = async (cursor = null, addToPrevStack = true) => {
    loading.value = true

    try {
        const searchFilters = {
            search: filters.search,
            privacy: filters.privacy,
            date: filters.date
        }

        const response = await fetchPosts(cursor, pagination.perPage, searchFilters)

        if (addToPrevStack && pagination.currentCursor !== null) {
            pagination.prevCursors.push(pagination.currentCursor)
        }

        pagination.currentCursor = cursor
        posts.value = response.data
        pagination.hasMore = response.meta.next_cursor
        pagination.hasPrevious = response.meta.prev_cursor
        pagination.perPage = response.meta.per_page
        pagination.total = response.meta.total_videos
    } catch (error) {
        console.error('Error loading posts:', error)
    } finally {
        loading.value = false
    }
}

const handleSearch = debounce(() => {
    filters.search = searchQuery.value
    resetPagination()
    loadPosts()
}, 300)

const handleFilterChange = () => {
    resetPagination()
    loadPosts()
}

const clearFilters = () => {
    filters.privacy = ''
    filters.date = ''
    filters.search = ''
    searchQuery.value = ''
    resetPagination()
    loadPosts()
}

const resetPagination = () => {
    pagination.currentCursor = null
    pagination.prevCursors = []
    pagination.currentPage = 1
}

const nextPage = async () => {
    if (!pagination.hasMore) return

    const response = await fetchPosts(pagination.currentCursor, pagination.perPage, {
        search: filters.search,
        privacy: filters.privacy,
        date: filters.date
    })

    if (response.meta.next_cursor) {
        pagination.currentPage++
        loadPosts(response.meta.next_cursor)
    }
}

const previousPage = () => {
    if (!pagination.hasPrevious) return

    const prevCursor = pagination.prevCursors.pop()
    pagination.currentPage--
    loadPosts(prevCursor, false)
}

const sortBy = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortField.value = field
        sortDirection.value = 'desc'
    }
    resetPagination()
    loadPosts()
}

const getStatusBadgeClass = (status) => {
    const baseClasses = 'inline-flex px-2 py-1 text-xs font-semibold rounded-full'

    switch (status) {
        case 'published':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`
        case 'processing':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200`
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200`
    }
}

const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value
    if (isDarkMode.value) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
}

const editPost = async (post) => {
    await videoStore.getVideoById(post.id)
    showEditModal.value = true
}

const handleSaveVideo = async (data) => {
    await videoStore.updateVideoStore(data)
    resetPagination()
    loadPosts()
}

const handleDeleteVideo = async (data) => {
    await videoStore.deleteVideoById(data)
    resetPagination()
    loadPosts()
}

onMounted(() => {
    if (
        localStorage.getItem('theme') === 'dark' ||
        (!localStorage.getItem('theme') &&
            window.matchMedia('(prefers-color-scheme: dark)').matches)
    ) {
        isDarkMode.value = true
        document.documentElement.classList.add('dark')
    }

    loadPosts()
})

watch(isDarkMode, (newValue) => {
    localStorage.setItem('theme', newValue ? 'dark' : 'light')
})
</script>
