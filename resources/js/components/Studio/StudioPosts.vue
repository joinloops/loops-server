<template>
    <div class="mx-auto w-full min-w-0 max-w-7xl px-3 py-5 sm:px-4 sm:py-8 lg:px-6">
        <div class="mb-5 sm:mb-6">
            <div class="py-2 sm:py-4 lg:py-6">
                <div
                    class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">
                            {{ $t('studio.myPosts') }}
                        </h1>
                    </div>

                    <div class="relative w-full lg:w-80 lg:shrink-0">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                        >
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                        </div>

                        <input
                            v-model="searchQuery"
                            type="search"
                            :placeholder="$t('studio.searchByPostCaption')"
                            class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-900 outline-none transition focus:border-transparent focus:ring-2 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder:text-gray-400"
                            @input="handleSearch"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="onlyEmbeds"
            class="mb-5 flex items-start justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3.5 dark:border-amber-800 dark:bg-amber-900/20 sm:mb-6 sm:gap-4 sm:p-4"
        >
            <div class="flex min-w-0 items-start gap-3">
                <ExclamationTriangleIcon
                    class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
                />

                <div class="min-w-0">
                    <h4 class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                        Showing only published videos with embeds enabled
                    </h4>

                    <p class="mt-1 text-xs leading-5 text-amber-800 dark:text-amber-300">
                        A filter is active. Other posts are hidden from this list.
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="shrink-0 rounded-lg p-1.5 text-amber-700 transition hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:text-amber-300 dark:hover:bg-amber-900/40"
                aria-label="Clear filter"
                @click="clearOnlyEmbeds"
            >
                <XMarkIcon class="h-5 w-5" />
            </button>
        </div>

        <div
            class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <div
                v-if="posts.length > 0"
                class="divide-y divide-gray-200 dark:divide-gray-700 lg:hidden"
            >
                <article v-for="post in posts" :key="post.id" class="min-w-0 p-4 sm:p-5">
                    <div class="flex min-w-0 items-start gap-3">
                        <img
                            :src="post.media.thumbnail"
                            :alt="`${post.caption || 'Post'} thumbnail`"
                            class="h-14 w-14 shrink-0 rounded-xl bg-gray-100 object-cover dark:bg-gray-700 sm:h-16 sm:w-16"
                            onerror="
                                this.src = '/storage/videos/video-placeholder.jpg'
                                this.onerror = null
                            "
                        />

                        <div class="min-w-0 flex-1">
                            <div class="flex min-w-0 items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p
                                        v-if="post.caption"
                                        class="line-clamp-2 break-words text-sm font-semibold leading-5 text-gray-900 dark:text-gray-100"
                                    >
                                        {{ post.caption }}
                                    </p>

                                    <p
                                        v-else
                                        class="text-sm font-medium italic text-gray-500 dark:text-gray-400"
                                    >
                                        No caption provided
                                    </p>
                                </div>

                                <span
                                    :class="getStatusBadgeClass(post.status)"
                                    class="shrink-0 whitespace-nowrap"
                                >
                                    {{ statusLabel(post.status) }}
                                </span>
                            </div>

                            <div
                                v-if="post.scheduled_at && post.status === 'scheduled'"
                                class="mt-2 min-w-0"
                            >
                                <p class="text-xs leading-5 text-gray-700 dark:text-gray-300">
                                    <span class="text-gray-500 dark:text-gray-400">
                                        Scheduled
                                    </span>

                                    <span class="font-semibold">
                                        {{ absoluteTime(post.scheduled_at) }}
                                    </span>
                                </p>

                                <p
                                    class="mt-0.5 text-xs leading-5 text-gray-500 dark:text-gray-400"
                                >
                                    {{ relativeTime(post.scheduled_at) }}
                                    in the {{ timezoneLabel }} timezone.
                                </p>
                            </div>

                            <div
                                v-else
                                class="mt-2 flex min-w-0 items-center gap-1.5 text-gray-500 dark:text-gray-400"
                            >
                                <span
                                    class="bx bx-time shrink-0 text-gray-300 dark:text-gray-600"
                                ></span>

                                <span class="min-w-0 truncate text-xs">
                                    {{ formatDate(post.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="post.status === 'published'" class="mt-4 grid grid-cols-2 gap-2">
                        <div class="min-w-0 rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-gray-900/50">
                            <p
                                class="truncate text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('studio.likes') }}
                            </p>

                            <p
                                class="mt-0.5 truncate text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100"
                            >
                                {{ post.likes.toLocaleString() }}
                            </p>
                        </div>

                        <div class="min-w-0 rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-gray-900/50">
                            <p
                                class="truncate text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('studio.comments') }}
                            </p>

                            <p
                                class="mt-0.5 truncate text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100"
                            >
                                {{ post.comments.toLocaleString() }}
                            </p>
                        </div>
                    </div>

                    <div
                        class=""
                        :class="[
                            ['published', 'scheduled'].includes(post.status)
                                ? 'mt-4 flex min-w-0 gap-2 border-t border-gray-100 pt-3 dark:border-gray-700'
                                : ''
                        ]"
                    >
                        <router-link
                            v-if="post.status === 'published'"
                            :to="`/v/${post.hid}`"
                            class="inline-flex min-w-0 flex-1 items-center justify-center rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus-visible:ring-gray-500"
                        >
                            {{ $t('studio.view') }}
                        </router-link>

                        <router-link
                            v-if="post.status === 'published'"
                            :to="`/studio/posts/${post.id}/edit`"
                            class="inline-flex min-w-0 flex-1 items-center justify-center rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus-visible:ring-gray-500"
                        >
                            {{ $t('common.edit') }}
                        </router-link>

                        <router-link
                            v-if="post.status === 'scheduled'"
                            to="/studio/scheduled"
                            class="inline-flex min-w-0 flex-1 items-center justify-center rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus-visible:ring-gray-500"
                        >
                            Manage
                        </router-link>
                    </div>
                </article>
            </div>

            <div v-if="posts.length > 0" class="hidden min-w-0 lg:block">
                <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-gray-700">
                    <colgroup>
                        <col class="w-[46%]" />
                        <col class="w-[14%]" />
                        <col class="w-[10%]" />
                        <col class="w-[12%]" />
                        <col class="w-[18%]" />
                    </colgroup>

                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th
                                scope="col"
                                class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 xl:px-6"
                            >
                                <div class="flex min-w-0 items-center">
                                    <span class="truncate">
                                        {{ $t('studio.contentCreatedOn') }}
                                    </span>

                                    <button
                                        type="button"
                                        class="ml-1 shrink-0 rounded p-0.5 text-gray-400 transition-colors hover:text-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 dark:hover:text-gray-300"
                                        aria-label="Sort by creation date"
                                        @click="sortBy('created_at')"
                                    >
                                        <ArrowsUpDownIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </th>

                            <th
                                scope="col"
                                class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('common.status') }}
                            </th>

                            <th
                                scope="col"
                                class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('studio.likes') }}
                            </th>

                            <th
                                scope="col"
                                class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('studio.comments') }}
                            </th>

                            <th
                                scope="col"
                                class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 xl:px-6"
                            >
                                {{ $t('studio.actions') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"
                    >
                        <tr
                            v-for="post in posts"
                            :key="post.id"
                            class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        >
                            <td class="min-w-0 px-4 py-4 xl:px-6">
                                <div class="flex min-w-0 items-center gap-3">
                                    <img
                                        :src="post.media.thumbnail"
                                        :alt="`${post.caption || 'Post'} thumbnail`"
                                        class="h-12 w-12 shrink-0 rounded-lg bg-gray-100 object-cover dark:bg-gray-700"
                                        onerror="
                                            this.src = '/storage/videos/video-placeholder.jpg'
                                            this.onerror = null
                                        "
                                    />

                                    <div class="min-w-0 flex-1">
                                        <p
                                            v-if="post.caption"
                                            class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"
                                            :title="post.caption"
                                        >
                                            {{ post.caption }}
                                        </p>

                                        <p
                                            v-else
                                            class="truncate text-sm font-medium italic text-gray-500 dark:text-gray-400"
                                        >
                                            No caption provided
                                        </p>

                                        <div
                                            v-if="post.scheduled_at && post.status === 'scheduled'"
                                            class="mt-1 min-w-0"
                                        >
                                            <p
                                                class="truncate text-xs text-gray-700 dark:text-gray-300"
                                            >
                                                <span class="text-gray-500 dark:text-gray-400">
                                                    Scheduled
                                                </span>

                                                <span class="font-semibold">
                                                    {{ absoluteTime(post.scheduled_at) }}
                                                </span>
                                            </p>

                                            <p
                                                class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                {{ relativeTime(post.scheduled_at) }}
                                                in the {{ timezoneLabel }}
                                                timezone.
                                            </p>
                                        </div>

                                        <div
                                            v-else
                                            class="mt-1 flex min-w-0 items-center gap-1.5 text-gray-500 dark:text-gray-400"
                                        >
                                            <span
                                                class="bx bx-time shrink-0 text-gray-300 dark:text-gray-600"
                                            ></span>

                                            <span class="truncate text-xs">
                                                {{ formatDate(post.created_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-3 py-4">
                                <span
                                    :class="getStatusBadgeClass(post.status)"
                                    class="max-w-full whitespace-nowrap"
                                >
                                    {{ statusLabel(post.status) }}
                                </span>
                            </td>

                            <td
                                class="px-3 py-4 text-right text-sm tabular-nums text-gray-900 dark:text-gray-100"
                            >
                                {{
                                    post.status === 'published' ? post.likes.toLocaleString() : '-'
                                }}
                            </td>

                            <td
                                class="px-3 py-4 text-right text-sm tabular-nums text-gray-900 dark:text-gray-100"
                            >
                                {{
                                    post.status === 'published'
                                        ? post.comments.toLocaleString()
                                        : '-'
                                }}
                            </td>

                            <td class="px-4 py-4 text-right xl:px-6">
                                <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                                    <router-link
                                        v-if="post.status === 'published'"
                                        :to="`/v/${post.hid}`"
                                        class="text-sm font-medium text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        {{ $t('studio.view') }}
                                    </router-link>

                                    <router-link
                                        v-if="post.status === 'published'"
                                        :to="`/studio/posts/${post.id}/edit`"
                                        class="text-sm font-medium text-yellow-600 transition-colors hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                                    >
                                        {{ $t('common.edit') }}
                                    </router-link>

                                    <router-link
                                        v-if="post.status === 'scheduled'"
                                        to="/studio/scheduled"
                                        class="text-sm font-medium text-yellow-600 transition-colors hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300"
                                    >
                                        Manage
                                    </router-link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!loading && posts.length === 0" class="px-4 py-12 text-center sm:py-16">
                <div class="mx-auto mb-5 flex h-24 w-24 items-center justify-center sm:mb-6">
                    <i class="bx bx-video text-8xl text-gray-300 dark:text-gray-600"></i>
                </div>

                <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100 sm:text-xl">
                    {{ $t('common.noPostsYet') }}
                </h3>

                <p
                    class="mx-auto mb-6 max-w-md text-sm leading-6 text-gray-500 dark:text-gray-400 sm:text-base"
                >
                    {{ $t('studio.yourPostedAndProcessingVideos') }}
                </p>

                <button
                    type="button"
                    class="w-full rounded-lg bg-red-500 px-6 py-3 font-medium text-white transition-colors hover:bg-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800 sm:w-auto sm:px-8"
                    @click="uploadVideo"
                >
                    {{ $t('studio.uploadFirstVideo') }}
                </button>
            </div>

            <div v-if="loading" class="px-4 py-12 text-center sm:py-16">
                <div
                    class="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-4 border-gray-300 border-t-red-500"
                ></div>

                <p class="text-sm text-gray-500 dark:text-gray-400">Loading posts...</p>
            </div>
        </div>

        <div
            v-if="!loading && posts.length > 0"
            class="mt-5 flex min-w-0 flex-col gap-3 sm:mt-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="text-center text-sm text-gray-600 dark:text-gray-300 sm:text-left">
                Showing {{ showingFrom }} to {{ showingTo }} of {{ totalCount }} results
            </div>

            <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                <button
                    type="button"
                    :disabled="!canGoPrevious"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="previousPage"
                >
                    Previous
                </button>

                <button
                    type="button"
                    :disabled="!canGoNext"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="nextPage"
                >
                    Next
                </button>
            </div>
        </div>

        <StatusEditModal
            :is-open="showEditModal"
            :video="currentVideo"
            @close="showEditModal = false"
            @save="handleSaveVideo"
            @delete="handleDeleteVideo"
        />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
    ArrowsUpDownIcon,
    ExclamationTriangleIcon,
    MagnifyingGlassIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline'

import { useUtils } from '@/composables/useUtils'

const props = defineProps({
    apiBase: {
        type: String,
        default: '/api/v1/studio/posts'
    }
})

const route = useRoute()
const router = useRouter()

const axios = inject('axios')
const videoStore = inject('videoStore')

const { formatDate } = useUtils()

const loading = ref(false)
const posts = ref([])
const searchQuery = ref('')

const sortField = ref('created_at')
const sortDirection = ref('desc')

const onlyEmbeds = ref(false)

const showEditModal = ref(false)
const currentVideo = computed(() => videoStore.video)

const now = ref(Date.now())

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
    if (posts.value.length === 0) {
        return 0
    }

    return (pagination.currentPage - 1) * pagination.perPage + 1
})

const showingTo = computed(() => {
    return Math.min(pagination.currentPage * pagination.perPage, pagination.total)
})

const totalCount = computed(() => pagination.total)

const canGoPrevious = computed(() => pagination.hasPrevious)

const canGoNext = computed(() => pagination.hasMore)

const timezoneLabel = computed(() => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone
    } catch {
        return 'your local time'
    }
})

const statusLabel = (status) => {
    if (!status) {
        return ''
    }

    return status.charAt(0).toUpperCase() + status.slice(1)
}

const absoluteTime = (value) => {
    if (!value) {
        return ''
    }

    return new Date(value).toLocaleString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    })
}

const relativeTime = (value) => {
    if (!value) {
        return ''
    }

    const target = value instanceof Date ? value : new Date(value)

    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto'
    })

    const minutes = Math.round((target.getTime() - now.value) / 60000)

    if (Math.abs(minutes) < 60) {
        return formatter.format(minutes, 'minute')
    }

    const hours = Math.round(minutes / 60)

    if (Math.abs(hours) < 48) {
        return formatter.format(hours, 'hour')
    }

    return formatter.format(Math.round(hours / 24), 'day')
}

const debounce = (func, wait) => {
    let timeout

    return (...args) => {
        clearTimeout(timeout)

        timeout = setTimeout(() => {
            func(...args)
        }, wait)
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
            sort_direction: sortDirection.value,
            only_embeds: onlyEmbeds.value ? 1 : undefined
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

const clearOnlyEmbeds = () => {
    onlyEmbeds.value = false

    const { only_embeds, ...rest } = route.query

    router.replace({
        query: rest
    })

    resetPagination()
    loadPosts()
}

const resetPagination = () => {
    pagination.currentCursor = null
    pagination.prevCursors = []
    pagination.currentPage = 1
}

const nextPage = async () => {
    if (!pagination.hasMore) {
        return
    }

    const response = await fetchPosts(pagination.currentCursor, pagination.perPage, {
        search: filters.search,
        privacy: filters.privacy,
        date: filters.date
    })

    if (!response.meta.next_cursor) {
        return
    }

    pagination.currentPage++

    loadPosts(response.meta.next_cursor)
}

const previousPage = () => {
    if (!pagination.hasPrevious) {
        return
    }

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
    const baseClasses = 'inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold'

    switch (status) {
        case 'published':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`

        case 'processing':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200`

        case 'scheduled':
            return `${baseClasses} bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200`

        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200`
    }
}

const uploadVideo = () => {
    router.push('/upload')
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

watch(
    () => route.query.only_embeds,
    (value) => {
        const newValue = value === '1'

        if (newValue === onlyEmbeds.value) {
            return
        }

        onlyEmbeds.value = newValue

        resetPagination()
        loadPosts()
    }
)

onMounted(() => {
    onlyEmbeds.value = route.query.only_embeds === '1'

    loadPosts()
})
</script>
