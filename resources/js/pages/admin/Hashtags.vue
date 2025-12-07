<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Hashtags</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Monitor and manage hashtags and topics
            </p>
        </div>

        <DataTable
            title="Hashtags"
            :columns="columns"
            :data="hashtags"
            :loading="loading"
            :has-previous="pagination.prev_cursor !== null"
            :has-next="pagination.next_cursor !== null"
            :has-actions="false"
            :sort-options="sortOptions"
            @search="handleSearch"
            @refresh="fetchHashtags"
            @previous="previousPage"
            @sort="handleSort"
            @next="nextPage"
        >
            <template #cell-id="{ value, item }">
                <button
                    @click="openModal(item)"
                    class="text-blue-600 hover:text-blue-800 font-medium underline cursor-pointer"
                >
                    {{ value }}
                </button>
            </template>

            <template #cell-user="{ item }">
                <div class="flex items-center">
                    <img
                        :src="item.user.avatar"
                        :alt="item.user.username"
                        class="w-8 h-8 rounded-full mr-2"
                        @error="$event.target.src = '/storage/avatars/default.jpg'"
                    />
                    <span>{{ item.user.username }}</span>
                </div>
            </template>

            <template #cell-video="{ item }">
                <div class="flex items-center">
                    <img
                        :src="item.video.thumbnail"
                        :alt="item.video.title"
                        class="w-12 h-8 rounded mr-2 object-cover"
                    />
                    <span class="max-w-xs truncate">{{ item.video.title }}</span>
                </div>
            </template>

            <template #cell-can_search="{ value }">
                <span :class="[value ? 'text-green-600' : 'text-red-600']">{{
                    value ? 'Yes' : 'No'
                }}</span>
            </template>

            <template #cell-can_trend="{ value }">
                <span :class="{ 'text-green-600': value }">{{ value ? 'Yes' : '' }}</span>
            </template>

            <template #cell-is_nsfw="{ value }">
                <span :class="{ 'text-red-600': value }">{{ value ? 'Yes' : '' }}</span>
            </template>

            <template #cell-count="{ value }">
                {{ formatNumber(value) }}
            </template>

            <template #cell-created_at="{ value }">
                {{ formatDate(value) }}
            </template>
        </DataTable>

        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50"
            @click="closeModal"
        >
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4"
                @click.stop
            >
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Hashtag Settings
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        #{{ selectedHashtag?.name }}
                    </p>
                </div>

                <div class="px-6 py-4 space-y-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 mr-4">
                            <label class="text-sm font-medium text-gray-900 dark:text-white">
                                Auto-link
                            </label>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Automatically convert text mentions to clickable hashtag links
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.can_autolink" :disabled="saving" />
                    </div>

                    <div class="flex items-start justify-between">
                        <div class="flex-1 mr-4">
                            <label class="text-sm font-medium text-gray-900 dark:text-white">
                                Searchable
                            </label>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Allow users to discover content through hashtag search
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.can_search" :disabled="saving" />
                    </div>

                    <div class="flex items-start justify-between">
                        <div class="flex-1 mr-4">
                            <label class="text-sm font-medium text-gray-900 dark:text-white">
                                Trendable
                            </label>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Include in trending hashtags and discovery feeds
                            </p>
                        </div>
                        <ToggleSwitch v-model="formData.can_trend" :disabled="saving" />
                    </div>

                    <div class="flex items-start justify-between">
                        <div class="flex-1 mr-4">
                            <label class="text-sm font-medium text-red-600 dark:text-red-400">
                                Banned
                            </label>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Block this hashtag from being used in new content
                            </p>
                        </div>
                        <ToggleSwitch
                            v-model="formData.is_banned"
                            variant="danger"
                            :disabled="saving"
                        />
                    </div>

                    <!-- NSFW Setting -->
                    <div class="flex items-start justify-between">
                        <div class="flex-1 mr-4">
                            <label class="text-sm font-medium text-orange-600 dark:text-orange-400">
                                NSFW Content
                            </label>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                Mark content with this hashtag as not safe for work
                            </p>
                        </div>
                        <ToggleSwitch
                            v-model="formData.is_nsfw"
                            variant="warning"
                            :disabled="saving"
                        />
                    </div>
                </div>

                <div
                    class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3"
                >
                    <button
                        @click="closeModal"
                        :disabled="saving"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveSettings"
                        :disabled="saving"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 flex items-center"
                    >
                        <svg
                            v-if="saving"
                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{ saving ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import ToggleSwitch from '@/components/Form/ToggleSwitch.vue'
import { hashtagsApi } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'
const { formatNumber, formatDate } = useUtils()

const route = useRoute()
const router = useRouter()

const hashtags = ref([])
const loading = ref(false)
const pagination = ref({
    cursor: null,
    prev: false,
    next: false
})

const showModal = ref(false)
const selectedHashtag = ref(null)
const saving = ref(false)
const formData = ref({
    can_autolink: false,
    can_search: false,
    can_trend: false,
    is_banned: false,
    is_nsfw: false
})

const searchQuery = ref('')
const sortBy = ref('')
const hydrating = ref(true)

const currentCursor = ref(null)
const currentDir = ref('next')

const DEBOUNCE_DELAY = 300
let searchTimeout = null

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Hashtag' },
    { key: 'count', label: '# of uses' },
    { key: 'can_search', label: 'Searchable' },
    { key: 'can_trend', label: 'Trendable' },
    { key: 'is_nsfw', label: 'NSFW' },
    { key: 'created_at', label: 'Created At' }
]

const sortOptions = [
    { name: 'Most Used', value: 'count_desc' },
    { name: 'Newest', value: 'created_at_desc' },
    { name: 'Oldest', value: 'created_at_asc' },
    { name: 'Trendable', value: 'can_trend' },
    { name: 'Banned', value: 'is_banned' },
    { name: 'NSFW', value: 'is_nsfw' }
]

const setQuery = (updates, { replace = false } = {}) => {
    const nextQuery = { ...route.query, ...updates }
    Object.keys(nextQuery).forEach((k) => {
        if (nextQuery[k] === '' || nextQuery[k] === null || nextQuery[k] === undefined) {
            nextQuery[k] = undefined // removes param
        }
    })

    const same =
        Object.keys(nextQuery).length === Object.keys(route.query).length &&
        Object.keys(nextQuery).every((k) => nextQuery[k] === route.query[k])

    if (same) return Promise.resolve()

    const nav = { query: nextQuery }
    return replace ? router.replace(nav) : router.push(nav)
}

const fetchHashtags = async (cursor = null, direction = 'next') => {
    loading.value = true
    try {
        const params = { cursor, direction }
        if (searchQuery.value) params.search = searchQuery.value
        if (sortBy.value) params.sort = sortBy.value

        const response = await hashtagsApi.getHashtags(params)
        hashtags.value = response.data
        pagination.value = response.meta

        currentCursor.value = cursor
        currentDir.value = direction
    } catch (error) {
        console.error('Error fetching hashtags:', error)
    } finally {
        loading.value = false
    }
}

const openModal = (hashtag) => {
    selectedHashtag.value = hashtag
    formData.value = {
        can_autolink: hashtag.can_autolink || false,
        can_search: hashtag.can_search || false,
        can_trend: hashtag.can_trend || false,
        is_banned: hashtag.is_banned || false,
        is_nsfw: hashtag.is_nsfw || false
    }
    showModal.value = true
    setQuery({ id: hashtag.id })
}

const closeModal = () => {
    showModal.value = false
    selectedHashtag.value = null
    saving.value = false
    setQuery({ id: undefined }, { replace: true })
}

const saveSettings = async () => {
    if (!selectedHashtag.value) return

    saving.value = true
    try {
        await hashtagsApi.updateHashtag(selectedHashtag.value.id, formData.value)
        const index = hashtags.value.findIndex((h) => h.id === selectedHashtag.value.id)
        if (index !== -1) {
            hashtags.value[index] = {
                ...hashtags.value[index],
                ...formData.value
            }
        }
        closeModal()
    } catch (error) {
        console.error('Error updating hashtag:', error)
    } finally {
        saving.value = false
    }
}

watch(searchQuery, () => {
    if (hydrating.value) return
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        setQuery({
            q: searchQuery.value || undefined,
            id: undefined,
            cursor: undefined,
            dir: undefined
        })
        fetchHashtags(null, 'next')
    }, DEBOUNCE_DELAY)
})

const handleSearch = (query) => {
    searchQuery.value = query
}

watch(sortBy, () => {
    if (hydrating.value) return
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        setQuery({
            sort: sortBy.value || undefined,
            cursor: undefined,
            dir: undefined
        })
        fetchHashtags(null, 'next')
    }, DEBOUNCE_DELAY)
})

const handleSort = (sortValue) => {
    sortBy.value = sortValue
}

const nextPage = () => {
    if (pagination.value.next_cursor) {
        const next = pagination.value.next_cursor
        setQuery({ cursor: next, dir: 'next' })
        fetchHashtags(next, 'next')
    }
}

const previousPage = () => {
    if (pagination.value.prev_cursor) {
        const prev = pagination.value.prev_cursor
        setQuery({ cursor: prev, dir: 'previous' })
        fetchHashtags(prev, 'previous')
    }
}

const openModalById = async (id) => {
    if (!id) return
    let tag = hashtags.value.find((h) => String(h.id) === String(id))

    if (!tag) {
        try {
            const numericId = Number(id)
            const res = await hashtagsApi.getHashtag(numericId)
            tag = res?.data || res
        } catch (e) {
            console.error('Unable to load hashtag by id:', id, e)
        }
    }

    if (tag) {
        openModal(tag)
    } else {
        setQuery({ id: undefined }, { replace: true })
    }
}

onMounted(async () => {
    const { q, id, sort, cursor, dir } = route.query

    if (typeof sort === 'string') sortBy.value = sort
    if (typeof q === 'string') searchQuery.value = q

    currentCursor.value = typeof cursor === 'string' ? cursor : null
    currentDir.value = dir === 'previous' ? 'previous' : 'next'

    await fetchHashtags(currentCursor.value, currentDir.value)

    if (id) await openModalById(id)

    hydrating.value = false
})

watch(
    () => route.query.id,
    async (newId, oldId) => {
        if (hydrating.value) return
        if (newId === oldId) return

        if (newId) {
            await openModalById(newId)
        } else if (showModal.value) {
            showModal.value = false
            selectedHashtag.value = null
        }
    }
)
</script>
