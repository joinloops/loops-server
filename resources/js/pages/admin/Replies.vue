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
                    class="truncate cursor-pointer"
                >
                    <div class="flex items-center">
                        <img
                            :src="item.account.avatar"
                            :alt="item.account.username"
                            class="w-8 h-8 rounded-full mr-2"
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                        <span class="font-bold">{{ item.account.username }}</span>
                    </div>
                </router-link>
            </template>

            <template #cell-id="{ value, item }">
                <button
                    @click="showCommentModal(item)"
                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline cursor-pointer"
                >
                    {{ value }}
                </button>
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

        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="isModalOpen"
                    class="fixed inset-0 z-50 overflow-y-auto"
                    @click.self="closeModal"
                >
                    <div class="flex min-h-screen items-center justify-center p-4">
                        <div
                            class="fixed inset-0 bg-black/90 transition-opacity"
                            @click="closeModal"
                        ></div>

                        <div
                            class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[85vh] flex flex-col"
                        >
                            <div
                                class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700"
                            >
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                        Comment Thread
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Video ID:
                                        <a
                                            :href="selectedComment?.url"
                                            class="text-blue-400"
                                            target="_blank"
                                        >
                                            {{ selectedComment?.v_id }}
                                        </a>
                                    </p>
                                </div>
                                <button
                                    @click="closeModal"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                >
                                    <span class="text-[30px] bx bx-x"></span>
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                                <div v-if="loadingParent" class="animate-pulse space-y-3">
                                    <div
                                        class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"
                                    ></div>
                                    <div
                                        class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"
                                    ></div>
                                </div>

                                <div
                                    v-else-if="parentComment"
                                    class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border-1 border-gray-200 dark:border-gray-950"
                                >
                                    <div class="flex items-start space-x-3">
                                        <img
                                            :src="parentComment.account.avatar"
                                            :alt="parentComment.account.username"
                                            class="w-10 h-10 rounded-full flex-shrink-0"
                                            @error="
                                                $event.target.src = '/storage/avatars/default.jpg'
                                            "
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-2">
                                                <span
                                                    class="font-semibold text-gray-900 dark:text-white"
                                                >
                                                    {{ parentComment.account.name }}
                                                </span>
                                                <span
                                                    class="text-sm text-gray-500 dark:text-gray-400"
                                                >
                                                    @{{ parentComment.account.username }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-2 text-gray-700 dark:text-gray-300 whitespace-pre-wrap"
                                            >
                                                <AutolinkedText
                                                    :caption="parentComment.caption"
                                                    :mentions="parentComment?.mentions"
                                                    :tags="parentComment?.tags"
                                                    :max-char-limit="120"
                                                />
                                            </p>
                                            <div
                                                class="mt-2 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                <a
                                                    :href="parentComment?.url"
                                                    class="text-blue-500"
                                                    target="_blank"
                                                >
                                                    {{ formatRecentDate(parentComment.created_at) }}
                                                </a>
                                                <span class="flex items-center space-x-1">
                                                    <span class="text-lg bx bx-heart"></span>

                                                    <span>
                                                        {{ formatCount(parentComment.likes) }}
                                                    </span>
                                                </span>

                                                <span class="flex items-center space-x-1">
                                                    <span class="text-lg bx bx-message"></span>

                                                    <span>
                                                        {{ formatCount(parentComment.replies) }}
                                                    </span>
                                                </span>

                                                <router-link
                                                    :to="`/admin/videos/${parentComment?.v_id}`"
                                                    class="text-blue-500"
                                                >
                                                    Manage
                                                </router-link>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="parentComment" class="flex justify-center">
                                    <div class="w-0.5 h-6 bg-gray-300 dark:bg-gray-600"></div>
                                </div>

                                <div
                                    v-if="selectedComment"
                                    class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700"
                                >
                                    <div class="flex items-start space-x-3">
                                        <img
                                            :src="selectedComment.account.avatar"
                                            :alt="selectedComment.account.username"
                                            class="w-10 h-10 rounded-full flex-shrink-0"
                                            @error="
                                                $event.target.src = '/storage/avatars/default.jpg'
                                            "
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-2">
                                                <span
                                                    class="font-semibold text-gray-900 dark:text-white"
                                                >
                                                    {{ selectedComment.account.name }}
                                                </span>
                                                <span
                                                    class="text-sm text-gray-500 dark:text-gray-400"
                                                >
                                                    @{{ selectedComment.account.username }}
                                                </span>
                                                <span
                                                    v-if="selectedComment.is_edited"
                                                    class="text-xs text-gray-400 dark:text-gray-500"
                                                >
                                                    (edited)
                                                </span>
                                            </div>
                                            <p
                                                class="mt-2 text-gray-700 dark:text-gray-300 whitespace-pre-wrap"
                                            >
                                                <AutolinkedText
                                                    :caption="selectedComment.caption"
                                                    :mentions="selectedComment?.mentions"
                                                    :tags="selectedComment?.tags"
                                                    textSize="text-md"
                                                    :max-char-limit="280"
                                                />
                                            </p>
                                            <div
                                                class="mt-2 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                <span>
                                                    {{
                                                        formatRecentDate(selectedComment.created_at)
                                                    }}
                                                </span>
                                                <span class="flex items-center space-x-1">
                                                    <span class="text-[15px] bx bx-heart"></span>
                                                    <span>
                                                        {{ formatCount(selectedComment.likes) }}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-end space-x-3 p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900"
                            >
                                <button
                                    @click="closeModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                >
                                    Close
                                </button>
                                <button
                                    @click="deleteFromModal"
                                    :disabled="deletingFromModal"
                                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
                                >
                                    <Spinner v-if="deletingFromModal" size="xs" />
                                    <span>Delete Comment</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue'
import DataTable from '@/components/DataTable.vue'
import { repliesApi } from '@/services/adminApi'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useRoute } from 'vue-router'
import { useUtils } from '@/composables/useUtils'
const { formatNumber, formatDate, formatRecentDate, goBack, formatCount } = useUtils()

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

const isModalOpen = ref(false)
const selectedComment = ref(null)
const parentComment = ref(null)
const loadingParent = ref(false)
const deletingFromModal = ref(false)

const columns = [
    { key: 'id', label: 'Reply ID' },
    { key: 'user', label: 'User' },
    { key: 'caption', label: 'Content' },
    { key: 'created_at', label: 'Created' }
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

        await repliesApi
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
            })
    } catch (error) {
        console.error('Error fetching parent comment:', error)
        parentComment.value = null
    } finally {
        loadingParent.value = false
    }
}

const showCommentModal = async (comment) => {
    selectedComment.value = comment
    isModalOpen.value = true
    parentComment.value = null

    if (comment.p_id) {
        await fetchParentComment(comment.p_id)
    }
}

const closeModal = () => {
    isModalOpen.value = false
    selectedComment.value = null
    parentComment.value = null
}

const deleteFromModal = async () => {
    if (!selectedComment.value) return

    deletingFromModal.value = true

    const result = await confirmModal(
        'Delete Comment',
        `Are you sure you want to delete this comment from ${selectedComment.value.account.username}? This action cannot be undone.`,
        'Delete',
        'Cancel'
    )

    if (result) {
        await repliesApi
            .deleteComment(selectedComment.value.id)
            .then(() => {
                closeModal()
                refreshComments()
            })
            .catch((error) => {
                alertModal('Error deleting comment:', error)
            })
            .finally(() => {
                deletingFromModal.value = false
            })
    } else {
        deletingFromModal.value = false
    }
}

watch(localOnly, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchComments()
    }, DEBOUNCE_DELAY)
})

watch(searchQuery, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchComments()
    }, DEBOUNCE_DELAY)
})

const handleSearch = (query) => {
    searchQuery.value = query
}

const handleLocalChange = (value) => {
    localOnly.value = value
}

const refreshComments = () => {
    fetchComments()
}

const nextPage = () => {
    if (pagination.value.next_cursor) {
        fetchComments(pagination.value.next_cursor, 'next')
    }
}

const previousPage = () => {
    if (pagination.value.prev_cursor) {
        fetchComments(pagination.value.prev_cursor, 'previous')
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
        await repliesApi
            .deleteComment(item.id)
            .then(() => {
                refreshComments()
            })
            .finally(() => {
                loading.value = false
            })
    } else {
        loading.value = false
    }
}

onUnmounted(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
})

onMounted(() => {
    fetchComments()
})
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .bg-white,
.modal-leave-active .bg-white,
.modal-enter-active .dark\:bg-gray-800,
.modal-leave-active .dark\:bg-gray-800 {
    transition: transform 0.3s ease;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white,
.modal-enter-from .dark\:bg-gray-800,
.modal-leave-to .dark\:bg-gray-800 {
    transform: scale(0.95);
}
</style>
