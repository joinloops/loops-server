<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Comments Management</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">View and moderate user comments</p>
        </div>

        <DataTable
            title="Comments"
            :columns="columns"
            :data="comments"
            :loading="loading"
            :has-previous="pagination.prev_cursor"
            :has-next="pagination.next_cursor"
            :sort-options="sortOptions"
            @sort="handleSort"
            :initial-search-query="searchQuery"
            :has-actions="false"
            :show-local-filter="true"
            @local-change="handleLocalChange"
            @search="handleSearch"
            @refresh="refreshComments"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-caption="{ item, value }">
                <div class="flex gap-3">
                    <span
                        v-if="item.is_hidden"
                        class="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400"
                    >
                        Hidden
                    </span>
                    <div class="max-w-xs truncate" :title="value">{{ value }}</div>
                </div>
            </template>

            <template #cell-user="{ item }">
                <router-link
                    :to="`/admin/profiles/${item.account.id}`"
                    class="cursor-pointer [white-space:normal]"
                >
                    <div class="flex items-center min-w-0">
                        <img
                            :src="item.account.avatar"
                            :alt="item.account.username"
                            class="w-8 h-8 rounded-full mr-2 flex-shrink-0"
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                        <div class="min-w-0 max-w-[180px]">
                            <span
                                class="font-bold truncate block"
                                :class="[item.account.username.length > 30 ? 'text-xs' : 'text-sm']"
                                >{{ item.account.username }}</span
                            >
                        </div>
                    </div>
                </router-link>
            </template>

            <template #cell-id="{ item }">
                <button
                    @click="openCommentModal(item)"
                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline cursor-pointer font-mono text-sm"
                >
                    {{ truncateMiddle(item.id, 12) }}
                </button>
            </template>

            <template #cell-stats="{ item }">
                <span
                    v-if="item.is_hidden"
                    class="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-400"
                >
                    Hidden
                </span>
                <div v-else class="space-y-1 text-gray-500 dark:text-gray-400">
                    <div class="text-xs font-light">Likes: {{ formatNumber(item.likes) }}</div>
                    <div class="text-xs font-light">Replies: {{ formatNumber(item.replies) }}</div>
                </div>
            </template>

            <template #cell-created_at="{ value }">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatRecentDate(value) }}
                </div>
            </template>
        </DataTable>

        <CommentDetailModal
            v-model="showModal"
            :comment="selectedComment"
            :action-loading="modalActionLoading"
            @hide="handleHideComment"
            @unhide="handleUnhideComment"
            @delete="handleModalDelete"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue'
import DataTable from '@/components/DataTable.vue'
import { commentsApi } from '@/services/adminApi'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useRoute } from 'vue-router'
import { useUtils } from '@/composables/useUtils'
const { formatDate, truncateMiddle, formatNumber, formatRecentDate } = useUtils()

const { alertModal, confirmModal } = useAlertModal()

const route = useRoute()

const comments = ref([])
const loading = ref(false)
const pagination = ref({
    prev_cursor: false,
    next_cursor: false
})

const showModal = ref(false)
const selectedComment = ref(null)
const modalActionLoading = ref(false)

const searchQuery = ref(route.query.q || '')
const localOnly = ref(false)
const DEBOUNCE_DELAY = 300
let searchTimeout = null
const sortBy = ref('')

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'user', label: 'User' },
    { key: 'caption', label: 'Content' },
    { key: 'stats', label: 'Engagement' },
    { key: 'created_at', label: 'Created' }
]

const sortOptions = [
    { name: 'Most Likes', value: 'likes_desc' },
    { name: 'Most Replies', value: 'replies_desc' },
    { name: 'Newest', value: 'created_at_desc' },
    { name: 'Oldest', value: 'created_at_asc' },
    { name: 'Hidden', value: 'is_hidden' },
    { name: 'Last Updated', value: 'updated_at_desc' }
]

const openCommentModal = (item) => {
    selectedComment.value = item
    showModal.value = true
}

const fetchComments = async (cursor = null, direction = 'next') => {
    loading.value = true
    try {
        const params = { cursor, direction }

        if (searchQuery.value) {
            params.search = searchQuery.value
        }

        if (localOnly.value) {
            params.local = true
        }

        if (sortBy.value) {
            params.sort = sortBy.value
        }

        await commentsApi
            .getComments(params)
            .then((response) => {
                comments.value = response.data
                pagination.value = response.meta
            })
            .catch((error) => {
                alertModal('Error fetching comments:', error)
            })
    } catch (error) {
        alertModal('Error fetching comments:', error)
    } finally {
        loading.value = false
    }
}

watch(localOnly, () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchComments(), DEBOUNCE_DELAY)
})

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchComments(), DEBOUNCE_DELAY)
})

watch(sortBy, () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchComments(), DEBOUNCE_DELAY)
})

const handleSearch = (query) => {
    searchQuery.value = query
}
const handleLocalChange = (value) => {
    localOnly.value = value
}
const handleSort = (sortValue) => {
    sortBy.value = sortValue
}
const refreshComments = () => {
    fetchComments()
}

const nextPage = () => {
    if (pagination.value.next_cursor) fetchComments(pagination.value.next_cursor, 'next')
}

const previousPage = () => {
    if (pagination.value.prev_cursor) fetchComments(pagination.value.prev_cursor, 'previous')
}

const handleHideComment = async (item) => {
    const result = await confirmModal(
        'Hide Comment',
        `Are you sure you want to hide this comment from ${item.account.username}? This action can be easily undone.`,
        'Hide',
        'Cancel'
    )
    if (result) {
        modalActionLoading.value = true
        try {
            await commentsApi.hideComment(item.id)
            selectedComment.value = { ...selectedComment.value, is_hidden: true }
            const idx = comments.value.findIndex((c) => c.id === item.id)
            if (idx !== -1) comments.value[idx] = { ...comments.value[idx], is_hidden: true }
        } catch (error) {
            alertModal('Error hiding comment', error)
        } finally {
            modalActionLoading.value = false
        }
    }
}

const handleUnhideComment = async (item) => {
    const result = await confirmModal(
        'Unhide Comment',
        `Are you sure you want to unhide this comment from ${item.account.username}? This action can be easily undone.`,
        'Unhide',
        'Cancel'
    )
    if (result) {
        modalActionLoading.value = true
        try {
            await commentsApi.unhideComment(item.id)
            selectedComment.value = { ...selectedComment.value, is_hidden: false }
            const idx = comments.value.findIndex((c) => c.id === item.id)
            if (idx !== -1) comments.value[idx] = { ...comments.value[idx], is_hidden: false }
        } catch (error) {
            alertModal('Error unhiding comment', error)
        } finally {
            modalActionLoading.value = false
        }
    }
}

const handleModalDelete = async (item) => {
    const result = await confirmModal(
        'Delete Comment',
        `Are you sure you want to delete this comment from ${item.account.username}? This action cannot be undone.`,
        'Delete',
        'Cancel'
    )
    if (result) {
        modalActionLoading.value = true
        await commentsApi
            .deleteComment(item.id)
            .then(() => {
                showModal.value = false
                selectedComment.value = null
                refreshComments()
            })
            .catch((error) => {
                alertModal('Error deleting comment', error)
            })
            .finally(() => {
                modalActionLoading.value = false
            })
    }
}

const showConfirmDelete = async (item) => {
    loading.value = true
    const result = await confirmModal(
        'Delete Comment',
        `Are you sure you want to delete this comment from ${item.account.username}? This action cannot be undone.`,
        'Delete',
        'Cancel'
    )
    if (result) {
        await commentsApi
            .deleteComment(item.id)
            .then(() => refreshComments())
            .finally(() => {
                loading.value = false
            })
    } else {
        loading.value = false
    }
}

onUnmounted(() => {
    if (searchTimeout) clearTimeout(searchTimeout)
})

onMounted(() => {
    fetchComments()
})
</script>
