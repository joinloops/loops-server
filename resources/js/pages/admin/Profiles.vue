<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Profiles</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Manage user profiles and account status
            </p>
        </div>

        <DataTable
            title="Profiles"
            :columns="columns"
            :data="profiles"
            :loading="loading"
            :has-previous="pagination.prev_cursor"
            :has-next="pagination.next_cursor"
            :has-actions="false"
            :sort-options="sortOptions"
            :show-local-filter="true"
            @local-change="handleLocalChange"
            @sort="handleSort"
            @search="handleSearch"
            @refresh="fetchProfiles"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-user="{ item }">
                <router-link
                    :to="`/admin/profiles/${item.id}`"
                    class="flex items-center gap-3 min-w-[320px] hover:bg-gray-50 dark:hover:bg-gray-800/50 -m-2 p-2 rounded-lg transition-colors group"
                >
                    <div class="relative flex-shrink-0">
                        <img
                            :src="item.avatar"
                            :alt="item.username"
                            class="w-12 h-12 rounded-full ring-2 ring-gray-200 dark:ring-gray-700 group-hover:ring-blue-400 dark:group-hover:ring-blue-500 transition-all"
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                        <div
                            v-if="!item.local"
                            class="absolute -bottom-1 -right-1 w-4 h-4 bg-blue-500 dark:bg-blue-400 rounded-full border-2 border-white dark:border-gray-900 flex items-center justify-center"
                            title="Federated user"
                        >
                            <GlobeAltIcon class="w-2.5 h-2.5 text-white" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <div
                                class="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 truncate transition-colors"
                            >
                                @{{ item.username }}
                            </div>
                            <div
                                v-if="item.is_owner"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-xs font-medium rounded"
                            >
                                <ShieldCheckIcon class="w-3 h-3" />
                                Owner
                            </div>
                        </div>
                        <div
                            v-if="item.name"
                            class="text-sm text-gray-600 dark:text-gray-400 truncate"
                        >
                            {{ item.name }}
                        </div>
                        <div
                            v-if="item.bio"
                            class="text-xs text-gray-500 dark:text-gray-500 line-clamp-1 max-w-[280px]"
                        >
                            {{ item.bio }}
                        </div>
                    </div>
                </router-link>
            </template>

            <template #cell-stats="{ item }">
                <div class="flex flex-col gap-2 min-w-[140px]">
                    <div class="flex items-center gap-4 text-sm">
                        <div
                            class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            title="Videos"
                        >
                            <VideoCameraIcon class="w-4 h-4 text-gray-400" />
                            <span class="font-medium">{{ formatNumber(item.post_count) }}</span>
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            title="Followers"
                        >
                            <UsersIcon class="w-4 h-4 text-gray-400" />
                            <span class="font-medium">{{ formatNumber(item.follower_count) }}</span>
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            title="Following"
                        >
                            <UserPlusIcon class="w-4 h-4 text-gray-400" />
                            <span class="font-medium">{{
                                formatNumber(item.following_count)
                            }}</span>
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-permissions="{ item }">
                <div class="flex flex-wrap gap-1 min-w-[160px]">
                    <span
                        v-if="item.can_upload"
                        class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400 text-xs rounded"
                        title="Can upload videos"
                    >
                        <CloudArrowUpIcon class="w-3 h-3" />
                        Upload
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-500 text-xs rounded line-through"
                        title="Cannot upload videos"
                    >
                        <CloudArrowUpIcon class="w-3 h-3" />
                        Upload
                    </span>
                    <span
                        v-if="!item.can_share && !item.can_like && !item.can_comment"
                        class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-xs rounded"
                        title="Limited interactions"
                    >
                        <NoSymbolIcon class="w-3 h-3" />
                        Restricted
                    </span>
                </div>
            </template>

            <template #cell-status="{ item }">
                <div class="flex items-center gap-2 min-w-[120px]">
                    <span
                        v-if="item.status === 1"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400 text-xs font-medium rounded-full"
                    >
                        <CheckCircleIcon class="w-3.5 h-3.5" />
                        Active
                    </span>
                    <span
                        v-else-if="item.status === 6"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-xs font-medium rounded-full"
                    >
                        <XCircleIcon class="w-3.5 h-3.5" />
                        Suspended
                    </span>
                    <span
                        v-else-if="item.status === 7"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-yellow-100 dark:bg-yellow-800 text-yellow-600 dark:text-yellow-400 text-xs font-medium rounded-full"
                    >
                        <EyeSlashIcon class="w-3.5 h-3.5" />
                        Disabled
                    </span>
                    <span
                        v-else-if="item.status === 8"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-xs font-medium rounded-full"
                    >
                        <XCircleIcon class="w-3.5 h-3.5" />
                        Deleted
                    </span>
                    <span
                        v-else-if="item.is_hidden"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-medium rounded-full"
                    >
                        <EyeSlashIcon class="w-3.5 h-3.5" />
                        Hidden
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-yellow-100 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 text-xs font-medium rounded-full"
                    >
                        <ExclamationCircleIcon class="w-3.5 h-3.5" />
                        {{ item.status_desc }}
                    </span>
                    <span
                        v-if="item.is_blocking"
                        class="inline-flex items-center gap-1 px-2 py-1 bg-orange-100 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400 text-xs rounded"
                        title="Instance is blocked"
                    >
                        <ShieldExclamationIcon class="w-3 h-3" />
                    </span>
                </div>
            </template>

            <template #cell-created_at="{ item }">
                <div class="flex flex-col gap-1 text-sm min-w-[140px]">
                    <div class="text-gray-900 dark:text-white font-medium">
                        {{ formatDate(item.created_at) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-500" :title="item.created_at">
                        {{ formatRelativeTime(item.created_at) }}
                    </div>
                    <div
                        v-if="item.url"
                        class="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-600 truncate max-w-[140px]"
                    >
                        <GlobeAltIcon class="w-3 h-3 flex-shrink-0" />
                        <a
                            :href="item.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:text-blue-500 dark:hover:text-blue-400 truncate"
                            :title="item.url"
                        >
                            {{ item.username }}
                        </a>
                    </div>
                </div>
            </template>

            <template #actions="{ item }">
                <div class="flex items-center gap-2">
                    <button
                        @click="viewProfile(item)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
                        title="View profile"
                    >
                        <EyeIcon class="w-4 h-4" />
                        View
                    </button>
                    <button
                        @click="manageProfile(item)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors"
                        title="Manage profile"
                    >
                        <Cog6ToothIcon class="w-4 h-4" />
                        Manage
                    </button>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import { profilesApi } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'
import {
    GlobeAltIcon,
    ShieldCheckIcon,
    VideoCameraIcon,
    UsersIcon,
    UserPlusIcon,
    CloudArrowUpIcon,
    NoSymbolIcon,
    CheckCircleIcon,
    XCircleIcon,
    EyeSlashIcon,
    ExclamationCircleIcon,
    ShieldExclamationIcon,
    EyeIcon,
    Cog6ToothIcon
} from '@heroicons/vue/24/outline'

const { truncateMiddle, formatDate } = useUtils()

const router = useRouter()
const profiles = ref([])
const loading = ref(false)
const pagination = ref({
    cursor: null,
    hasPrevious: false,
    hasNext: false
})

const searchQuery = ref('')
const sortBy = ref('')
const localOnly = ref(false)
const DEBOUNCE_DELAY = 300
let searchTimeout = null

const columns = [
    { key: 'user', label: 'User' },
    { key: 'stats', label: 'Stats' },
    { key: 'permissions', label: 'Permissions' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Joined / Instance' }
]

const sortOptions = [
    { name: 'Username A-Z', value: 'username_asc' },
    { name: 'Username Z-A', value: 'username_desc' },
    { name: 'Most Followers', value: 'followers_desc' },
    { name: 'Most Videos', value: 'video_count_desc' },
    { name: 'Newest', value: 'created_at_desc' },
    { name: 'Oldest', value: 'created_at_asc' },
    { name: 'Suspended', value: 'suspended' },
    { name: 'Disabled', value: 'disabled' },
    { name: 'Deleted', value: 'deleted' },
    { name: 'Last Updated', value: 'updated_at_desc' }
]

const formatNumber = (num) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M'
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K'
    }
    return num.toString()
}

const formatRelativeTime = (dateString) => {
    const date = new Date(dateString)
    const now = new Date()
    const seconds = Math.floor((now - date) / 1000)

    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    }

    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit)
        if (interval >= 1) {
            return `${interval} ${unit}${interval === 1 ? '' : 's'} ago`
        }
    }

    return 'just now'
}

const extractDomain = (url) => {
    try {
        const urlObj = new URL(url)
        return urlObj.hostname.replace('www.', '')
    } catch {
        return url
    }
}

const fetchProfiles = async (cursor = null, direction = 'next') => {
    loading.value = true
    try {
        const params = { cursor, direction }

        if (searchQuery.value) {
            params.search = searchQuery.value
        }

        if (sortBy.value) {
            params.sort = sortBy.value
        }

        if (localOnly.value) {
            params.local = true
        }

        const response = await profilesApi.getProfiles(params)
        profiles.value = response.data
        pagination.value = response.meta
    } catch (error) {
        console.error('Error fetching profiles:', error)
    } finally {
        loading.value = false
    }
}

const viewProfile = (profile) => {
    router.push(`/@${profile.username}`)
}

const manageProfile = (profile) => {
    router.push(`/admin/profiles/${profile.id}`)
}

watch(localOnly, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchProfiles()
    }, DEBOUNCE_DELAY)
})

watch(searchQuery, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchProfiles()
    }, DEBOUNCE_DELAY)
})

watch(sortBy, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchProfiles()
    }, DEBOUNCE_DELAY)
})

const handleSort = (sortValue) => {
    sortBy.value = sortValue
}

const handleSearch = (query) => {
    searchQuery.value = query
}

const handleLocalChange = (value) => {
    localOnly.value = value
}

const nextPage = () => {
    if (pagination.value.next_cursor) {
        fetchProfiles(pagination.value.next_cursor, 'next')
    }
}

const previousPage = () => {
    if (pagination.value.prev_cursor) {
        fetchProfiles(pagination.value.prev_cursor, 'previous')
    }
}

onUnmounted(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
})

onMounted(() => {
    fetchProfiles()
})
</script>
