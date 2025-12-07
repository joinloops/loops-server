<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reports Management</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Review and handle user reports</p>
        </div>

        <DataTable
            title="Reports"
            :columns="columns"
            :data="reports"
            :loading="loading"
            :has-previous="pagination.prev_cursor"
            :has-next="pagination.next_cursor"
            :sort-options="sortOptions"
            :has-actions="false"
            :initial-search-query="searchQuery"
            @sort="handleSort"
            @search="handleSearch"
            @refresh="fetchReports"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-id="{ value }">
                <router-link
                    :to="`/admin/reports/${value}`"
                    :title="value"
                    class="text-xs text-blue-500 hover:text-blue-300"
                >
                    {{ value }}
                </router-link>
            </template>

            <template #cell-reporter="{ item }">
                <router-link
                    :to="`/admin/profiles/${item.reporter.id}`"
                    class="truncate cursor-pointer"
                >
                    <div class="flex items-center">
                        <img
                            :src="item.reporter.avatar"
                            :alt="item.reporter.username"
                            class="w-8 h-8 rounded-full mr-2"
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                        <span class="font-bold">{{ item.reporter.username }}</span>
                    </div>
                </router-link>
            </template>

            <template #cell-reported_content="{ item }">
                <div class="flex items-center">
                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded mr-2">
                        {{ item.content_type }}
                    </span>
                </div>
            </template>

            <template #cell-reason="{ value }">
                <span
                    class="px-2 py-1 text-xs bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 rounded-full"
                >
                    {{ value }}
                </span>
            </template>

            <template #cell-status="{ value }">
                <span
                    :class="[
                        'px-2 py-1 text-xs rounded-full',
                        value === 'resolved'
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : value === 'pending'
                              ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                              : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                    ]"
                >
                    {{ value }}
                </span>
            </template>

            <template #cell-created_at="{ value }">
                {{ formatDate(value) }}
            </template>

            <template #actions="{ item }">
                <button
                    @click="reviewReport(item)"
                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3"
                >
                    Review
                </button>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import DataTable from '@/components/DataTable.vue'
import { reportsApi } from '@/services/adminApi'
import { useRoute, useRouter } from 'vue-router'
import { useUtils } from '@/composables/useUtils'
const { formatDate } = useUtils()

const route = useRoute()
const router = useRouter()

const reports = ref([])
const loading = ref(false)
const pagination = ref({
    cursor: null,
    hasPrevious: false,
    hasNext: false
})

const searchQuery = ref(route.query.q || '')
const sortBy = ref('')
const DEBOUNCE_DELAY = 300
let searchTimeout = null

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'reporter', label: 'Reporter' },
    { key: 'reported_content', label: 'Content' },
    { key: 'reason', label: 'Reason' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Reported' }
]

const sortOptions = [
    { name: 'Open', value: 'open' },
    { name: 'Closed', value: 'closed' },
    { name: 'All', value: 'all' }
]

const fetchReports = async (cursor = null, direction = 'next') => {
    loading.value = true
    try {
        const params = { cursor, direction }

        if (searchQuery.value) {
            params.search = searchQuery.value
        }

        if (sortBy.value) {
            params.sort = sortBy.value
        }
        const response = await reportsApi.getReports(params)
        reports.value = response.data
        pagination.value = response.meta
    } catch (error) {
        console.error('Error fetching reports:', error)
    } finally {
        loading.value = false
    }
}

watch(searchQuery, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchReports()
    }, DEBOUNCE_DELAY)
})

watch(sortBy, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchReports()
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
        fetchReports(pagination.value.next_cursor, 'next')
    }
}

const previousPage = () => {
    if (pagination.value.prev_cursor) {
        fetchReports(pagination.value.prev_cursor, 'previous')
    }
}

onMounted(() => {
    fetchReports()
})
</script>
