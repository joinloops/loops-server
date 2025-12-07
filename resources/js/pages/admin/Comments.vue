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

            <template #cell-v_id="{ value }">
                <router-link
                    :to="`/admin/videos/${value}`"
                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline cursor-pointer"
                >
                    {{ value }}
                </router-link>
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
    </div>
</template>

<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue'
import DataTable from '@/components/DataTable.vue'
import { commentsApi } from '@/services/adminApi'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useRoute } from 'vue-router'
import { useUtils } from '@/composables/useUtils'
const { formatDate } = useUtils()

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

const columns = [
    { key: 'v_id', label: 'Video ID' },
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
        await commentsApi
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
