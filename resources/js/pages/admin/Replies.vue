<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Comment Replies Management
            </h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                View and moderate user comment replies
            </p>
        </div>

        <DataTable
            title="Comment Replies"
            :columns="columns"
            :data="comments"
            :loading="loading"
            :has-previous="pagination.prev_cursor"
            :has-next="pagination.next_cursor"
            :sort-options="sortOptions"
            @sort="handleSort"
            :initial-search-query="searchQuery"
            :show-local-filter="true"
            @local-change="handleLocalChange"
            @search="handleSearch"
            @refresh="refreshComments"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-caption="{ value }">
                <div class="max-w-xs truncate" :title="value">{{ value }}</div>
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

            <template #cell-id="{ value, item }">
                <button
                    @click="openCommentModal(item)"
                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline cursor-pointer"
                >
                    {{ truncateMiddle(value, 12) }}
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
                    <div class="text-xs font-light">Shares: {{ formatNumber(item.shares) }}</div>
                </div>
            </template>

            <template #cell-created_at="{ value }">
                {{ formatDate(value) }}
            </template>

            <template #actions="{ item }">
                <button
                    class="cursor-pointer text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                    @click="showConfirmDelete(item)"
                >
                    Delete
                </button>
            </template>
        </DataTable>

        <CommentDetailModal
            v-model="showModal"
            :comment="selectedComment"
            :parent-comment="parentComment"
            :loading-parent="loadingParent"
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
import { repliesApi } from '@/services/adminApi'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useRoute } from 'vue-router'
import { useUtils } from '@/composables/useUtils'
const { formatNumber, formatDate, formatRecentDate, goBack, formatCount, truncateMiddle } =
    useUtils()

const { alertModal, confirmModal } = useAlertModal()
const route = useRoute()

const comments = ref([])
const loading = ref(false)
const pagination = ref({
    prev_cursor: false,
    next_cursor: false
})

const searchQuery = ref(route.query.q || '')
const localOnly = ref(false)
const DEBOUNCE_DELAY = 300
let searchTimeout = null
const sortBy = ref('')

const showModal = ref(false)
const selectedComment = ref(null)
const parentComment = ref(null)
const loadingParent = ref(false)
const modalActionLoading = ref(false)

const columns = [
    { key: 'id', label: 'Reply ID' },
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

        await repliesApi
            .getComments(params)
            .then((response) => {
                comments.value = response.data
                pagination.value = response.meta
            })
            .catch((error) => alertModal('Error fetching comments:', error))
    } catch (error) {
        alertModal('Error fetching comments:', error)
    } finally {
        loading.value = false
    }
}

const fetchParentComment = async (parentId) => {
    loadingParent.value = true
    try {
        await repliesApi
            .getParentComment(parentId)
            .then((response) => {
                parentComment.value = response.data
            })
            .catch((error) => {
                alertModal('Error fetching parent comment:', error)
                parentComment.value = null
            })
    } catch (error) {
        console.error('Error fetching parent comment:', error)
        parentComment.value = null
    } finally {
        loadingParent.value = false
    }
}

const openCommentModal = async (comment) => {
    selectedComment.value = comment
    parentComment.value = null
    showModal.value = true

    if (comment.p_id) {
        await fetchParentComment(comment.p_id)
    }
}

const updateCommentInList = (id, patch) => {
    const idx = comments.value.findIndex((c) => c.id === id)
    if (idx !== -1) comments.value[idx] = { ...comments.value[idx], ...patch }
    if (selectedComment.value?.id === id) {
        selectedComment.value = { ...selectedComment.value, ...patch }
    }
}

const handleHideComment = async (item) => {
    modalActionLoading.value = true
    try {
        await repliesApi.hideComment(item.id)
        updateCommentInList(item.id, { is_hidden: true })
    } catch (error) {
        alertModal('Error hiding comment', error)
    } finally {
        modalActionLoading.value = false
    }
}

const handleUnhideComment = async (item) => {
    modalActionLoading.value = true
    try {
        await repliesApi.unhideComment(item.id)
        updateCommentInList(item.id, { is_hidden: false })
    } catch (error) {
        alertModal('Error unhiding comment', error)
    } finally {
        modalActionLoading.value = false
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
        await repliesApi
            .deleteComment(item.id)
            .then(() => {
                showModal.value = false
                selectedComment.value = null
                refreshComments()
            })
            .catch((error) => alertModal('Error deleting comment:', error))
            .finally(() => {
                modalActionLoading.value = false
            })
    }
}

const handleSort = (sortValue) => {
    sortBy.value = sortValue
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
const refreshComments = () => fetchComments()

const nextPage = () => {
    if (pagination.value.next_cursor) fetchComments(pagination.value.next_cursor, 'next')
}
const previousPage = () => {
    if (pagination.value.prev_cursor) fetchComments(pagination.value.prev_cursor, 'previous')
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
        await repliesApi
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
