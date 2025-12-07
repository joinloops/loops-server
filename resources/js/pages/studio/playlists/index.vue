<template>
    <StudioLayout>
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6"
            >
                <div class="p-6">
                    <div
                        class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0"
                    >
                        <div class="text-xl font-bold dark:text-white">
                            {{ $t('studio.myPlaylists') }}
                        </div>

                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
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
                                    :placeholder="$t('studio.searchPlaylistsDotDotDot')"
                                    class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent w-full sm:w-64"
                                />
                            </div>
                            <AnimatedButton @click="openCreateModal">
                                <div class="flex">
                                    <PlusIcon class="h-5 w-5 mr-2" />
                                    {{ $t('studio.newPlaylist') }}
                                </div>
                            </AnimatedButton>
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
                                    {{ $t('studio.playlist') }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t('studio.visibility') }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t('common.videos') }}
                                </th>
                                <th
                                    class="flex items-center px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t('common.created') }}
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
                                    {{ $t('common.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <tr
                                v-for="playlist in playlists"
                                :key="playlist.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div
                                            class="flex-shrink-0 w-10 h-16 bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden"
                                        >
                                            <img
                                                v-if="playlist.cover_image"
                                                :src="playlist.cover_image"
                                                :alt="`${playlist.name} cover`"
                                                class="w-full h-full object-cover"
                                                onerror="
                                                    this.style.display = 'none'
                                                    this.parentElement.innerHTML =
                                                        '<div class=\'w-full h-full flex items-center justify-center\'><svg class=\'w-8 h-8 text-gray-400\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10\'></path></svg></div>'
                                                "
                                            />
                                            <div
                                                v-else
                                                class="w-full h-full flex items-center justify-center"
                                            >
                                                <QueueListIcon class="w-8 h-8 text-gray-400" />
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0 max-w-md">
                                            <div
                                                class="text-lg font-bold text-gray-900 dark:text-gray-100 truncate"
                                            >
                                                {{ playlist.name }}
                                            </div>
                                            <div
                                                v-if="playlist.description"
                                                class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 break-words"
                                            >
                                                {{ playlist.description }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="getVisibilityBadgeClass(playlist.visibility)">
                                        {{ formatVisibility(playlist.visibility) }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100"
                                >
                                    {{ playlist.videos_count || 0 }}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatDate(playlist.created_at) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <router-link
                                            :to="`/studio/playlists/${playlist.id}`"
                                            class="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-medium border-blue-300 text-blue-700 bg-blue-50/60 hover:bg-blue-100 hover:border-blue-400 dark:border-blue-500/70 dark:text-blue-300 dark:bg-blue-900/20 dark:hover:bg-blue-900/50 dark:hover:border-blue-300 transition-colors shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900 cursor-pointer"
                                        >
                                            <ListBulletIcon class="w-4 h-4" />
                                            {{ $t('common.manage') }}
                                        </router-link>

                                        <button
                                            @click="editPlaylist(playlist)"
                                            class="inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm font-medium border-amber-300 text-amber-700 bg-amber-50/60 hover:bg-amber-100 hover:border-amber-400 dark:border-amber-500/70 dark:text-amber-300 dark:bg-amber-900/20 dark:hover:bg-amber-900/50 dark:hover:border-amber-300 transition-colors shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900 cursor-pointer"
                                        >
                                            <PencilSquareIcon class="w-4 h-4" />
                                            {{ $t('common.edit') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="!loading && playlists.length === 0 && hasActiveSearch"
                    class="text-center py-16"
                >
                    <div class="mx-auto w-32 h-32 mb-6">
                        <MagnifyingGlassIcon class="w-32 h-32 text-gray-300 dark:text-gray-600" />
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        {{ $t('studio.noPlaylistsFound') }}
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6">
                        {{ $t('studio.tryDifferentPlaylistSearch') }}
                    </p>
                    <AnimatedButton @click="clearSearch">
                        <div class="flex">
                            <MagnifyingGlassIcon class="h-5 w-5 mr-2" />
                            {{ $t('studio.clearPlaylistSearch') }}
                        </div>
                    </AnimatedButton>
                </div>

                <div
                    v-if="!loading && playlists.length === 0 && !hasActiveSearch"
                    class="text-center py-16"
                >
                    <div class="mx-auto w-32 h-32 mb-6">
                        <QueueListIcon class="w-32 h-32 text-gray-300 dark:text-gray-600" />
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                        {{ $t('studio.noPlaylistsYet') }}
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6">
                        {{ $t('studio.createYourFirstPlaylistTo') }}
                    </p>
                    <button
                        @click="openCreateModal"
                        class="bg-red-500 hover:bg-red-600 text-white px-8 py-3 rounded-lg font-medium transition-colors inline-flex items-center"
                    >
                        <PlusIcon class="h-5 w-5 mr-2" />
                        {{ $t('studio.createFirstPlaylist') }}
                    </button>
                </div>

                <div v-if="loading" class="text-center py-16 flex flex-col gap-3">
                    <Spinner />
                    <p class="text-sm font-bold text-gray-500 dark:text-gray-400">
                        {{ $t('studio.loadingPlaylistsDotDotDot') }}
                    </p>
                </div>
            </div>

            <div
                v-if="!loading && playlists.length > 0"
                class="mt-6 flex items-center justify-between"
            >
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Showing {{ showingFrom }} to {{ showingTo }} of {{ totalCount }} results
                </div>
                <div class="flex space-x-2">
                    <button
                        @click="previousPage"
                        :disabled="!canGoPrevious"
                        class="px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                        v-html="$t('pagination.previous')"
                    ></button>
                    <button
                        @click="nextPage"
                        :disabled="!canGoNext"
                        class="px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
                        v-html="$t('pagination.next')"
                    ></button>
                </div>
            </div>

            <PlaylistModal
                :is-open="showPlaylistModal"
                :playlist="currentPlaylist"
                @close="closePlaylistModal"
                @save="handleSavePlaylist"
                @delete="handleDeletePlaylist"
            />
        </div>
    </StudioLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { usePlaylistStore } from '@/stores/playlist'
import { useUtils } from '@/composables/useUtils'
import PlaylistModal from '@/components/Studio/PlaylistModal.vue'
import {
    PencilSquareIcon,
    MagnifyingGlassIcon,
    ArrowsUpDownIcon,
    PlusIcon,
    QueueListIcon,
    ListBulletIcon
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { useAlertModal } from '@/composables/useAlertModal.js'
import AnimatedButton from '@/components/AnimatedButton.vue'

const authStore = useAuthStore()
const playlistStore = usePlaylistStore()
const { formatDate } = useUtils()
const router = useRouter()
const { alertModal, confirmModal } = useAlertModal()

const loading = ref(false)
const playlists = ref([])
const searchQuery = ref('')
const sortField = ref('created_at')
const sortDirection = ref('desc')
const showPlaylistModal = ref(false)
const currentPlaylist = ref(null)

const filters = reactive({
    search: ''
})

const pagination = reactive({
    currentPage: 1,
    perPage: 10,
    total: 0
})

const showingFrom = computed(() => {
    if (playlists.value.length === 0) return 0
    return (pagination.currentPage - 1) * pagination.perPage + 1
})

const hasActiveSearch = computed(() => {
    return filters.search.trim().length > 0
})

const showingTo = computed(() => {
    return Math.min(pagination.currentPage * pagination.perPage, pagination.total)
})

const totalCount = computed(() => pagination.total)
const canGoPrevious = computed(() => pagination.currentPage > 1)
const canGoNext = computed(() => pagination.currentPage * pagination.perPage < pagination.total)

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

const loadPlaylists = async () => {
    loading.value = true

    try {
        const response = await playlistStore.fetchPlaylists({
            search: filters.search,
            sort_field: sortField.value,
            sort_direction: sortDirection.value,
            page: pagination.currentPage,
            limit: pagination.perPage
        })

        playlists.value = response.data
        pagination.total = response.meta?.total || response.data.length
    } catch (error) {
        console.error('Error loading playlists:', error)
    } finally {
        loading.value = false
    }
}

const handleSearch = debounce(() => {
    filters.search = searchQuery.value
    pagination.currentPage = 1
    loadPlaylists()
}, 300)

const clearSearch = () => {
    searchQuery.value = ''
    filters.search = ''
    pagination.currentPage = 1
    loadPlaylists()
}

const sortBy = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortField.value = field
        sortDirection.value = 'desc'
    }
    pagination.currentPage = 1
    loadPlaylists()
}

const nextPage = () => {
    if (canGoNext.value) {
        pagination.currentPage++
        loadPlaylists()
    }
}

const previousPage = () => {
    if (canGoPrevious.value) {
        pagination.currentPage--
        loadPlaylists()
    }
}

const openCreateModal = () => {
    currentPlaylist.value = null
    showPlaylistModal.value = true
}

const editPlaylist = (playlist) => {
    currentPlaylist.value = playlist
    showPlaylistModal.value = true
}

const closePlaylistModal = () => {
    showPlaylistModal.value = false
    currentPlaylist.value = null
}

const handleSavePlaylist = async (data) => {
    try {
        if (data.id) {
            await playlistStore.updatePlaylist(data.id, data)
        } else {
            await playlistStore.createPlaylist(data)
        }
        closePlaylistModal()
        loadPlaylists()
    } catch (error) {
        await alertModal('Error', error?.response.data.error?.message)
        console.error('Error saving playlist:', error)
    }
}

const handleDeletePlaylist = async (id) => {
    try {
        await playlistStore.deletePlaylist(id)
        closePlaylistModal()
        loadPlaylists()
    } catch (error) {
        console.error('Error deleting playlist:', error)
    }
}

const getVisibilityBadgeClass = (visibility) => {
    const baseClasses = 'inline-flex px-2 py-1 text-xs font-semibold rounded-full'

    switch (visibility) {
        case 'public':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`
        case 'unlisted':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200`
        case 'followers':
            return `${baseClasses} bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200`
        case 'private':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200`
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200`
    }
}

const formatVisibility = (visibility) => {
    const labels = {
        public: 'Public',
        unlisted: 'Unlisted',
        followers: 'Followers',
        private: 'Private'
    }
    return labels[visibility] || visibility
}

onMounted(() => {
    if (authStore.getUser?.post_count < 20 || authStore.getUser?.follower_count < 100) {
        router.push('/studio')
        return
    }
    loadPlaylists()
})
</script>
