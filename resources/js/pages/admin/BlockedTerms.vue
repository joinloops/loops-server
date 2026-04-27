<template>
    <div>
        <div class="mb-6 flex flex-col md:flex-row items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Blocked Terms</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    Manage the moderation word list used for search and other user input
                </p>
            </div>
            <div class="flex items-center gap-2">
                <AnimatedButton @click="openCreateModal" variant="primaryGradient" pill>
                    <div class="flex items-center gap-2">
                        <PlusIcon class="h-4 w-4" />
                        Add Term
                    </div>
                </AnimatedButton>
                <Dropdown align="right">
                    <template #trigger>
                        <AnimatedButton variant="outline" size="md" pill>
                            <div class="flex items-center gap-3">
                                More
                                <ChevronDownIcon class="h-5 w-5" />
                            </div>
                        </AnimatedButton>
                    </template>

                    <DropdownItem @click="showTestModal = true">
                        <div class="flex items-center gap-3">
                            <BeakerIcon class="h-4 w-4" />
                            Live Test
                        </div>
                    </DropdownItem>
                    <DropdownDivider class="my-1" />
                    <DropdownItem @click="showImportModal = true">
                        <div class="flex items-center gap-3">
                            <ArrowUpTrayIcon class="h-4 w-4" />
                            Import
                        </div>
                    </DropdownItem>
                    <DropdownItem @click="openExportModal">
                        <div class="flex items-center gap-3">
                            <ArrowDownTrayIcon class="h-4 w-4" />
                            Export
                        </div>
                    </DropdownItem>
                    <DropdownDivider class="my-1" />
                    <DropdownItem @click="openDeleteAllModal">
                        <div class="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                            <TrashIcon class="h-4 w-4" />
                            Delete All
                        </div>
                    </DropdownItem>
                </Dropdown>
            </div>
        </div>

        <div class="mb-4 flex items-center gap-2">
            <div
                class="flex gap-1 rounded-full bg-gray-100 p-2 dark:bg-gray-800 border border-gray-300 dark:border-gray-700"
            >
                <button
                    v-for="tab in typeTabs"
                    :key="tab.value"
                    @click="setTypeFilter(tab.value)"
                    :class="[
                        'rounded-full px-5 py-2 text-sm font-medium transition cursor-pointer',
                        typeFilter === tab.value
                            ? 'bg-red-500 text-white'
                            : 'bg-gray-200 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    ]"
                >
                    {{ tab.label }}
                    <span v-if="tab.count !== null" class="ml-1 text-xs opacity-75"
                        >({{ formatNumber(tab.count) }})</span
                    >
                </button>
            </div>
        </div>

        <DataTable
            title="Terms"
            :columns="columns"
            :data="terms"
            :loading="loading"
            :has-previous="pagination?.prev_cursor"
            :has-next="pagination?.next_cursor"
            :sort-options="sortOptions"
            @sort="handleSort"
            :initial-search-query="searchQuery"
            :has-actions="true"
            @search="handleSearch"
            @refresh="fetchTerms"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-term="{ value, item }">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-sm">{{ value }}</span>
                    <span
                        v-if="item.type === 'allow'"
                        class="inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400"
                    >
                        Allow
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-400/10 dark:text-rose-400"
                    >
                        Block
                    </span>
                </div>
            </template>

            <template #cell-note="{ value }">
                <div
                    v-if="value"
                    class="max-w-sm truncate text-sm text-gray-600 dark:text-gray-400"
                    :title="value"
                >
                    {{ value }}
                </div>
                <span v-else class="text-xs italic text-gray-400 dark:text-gray-600">—</span>
            </template>

            <template #cell-created_by="{ item }">
                <router-link
                    v-if="item.created_by_account"
                    :to="`/admin/profiles/${item.created_by_account.id}`"
                    class="text-sm"
                >
                    <Avatar :src="item.created_by_account.avatar" :width="30" />
                    @{{ item.created_by_account.username }}
                </router-link>
                <span v-else class="text-xs italic text-gray-400 dark:text-gray-600">System</span>
            </template>

            <template #cell-created_at="{ value }">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatRecentDate(value) }}
                </div>
            </template>

            <template #actions="{ item }">
                <div class="flex items-center justify-end gap-1">
                    <button
                        @click="openEditModal(item)"
                        class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        title="Edit"
                    >
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button
                        @click="confirmDelete(item)"
                        class="rounded p-1.5 text-gray-500 hover:bg-rose-50 hover:text-rose-600 dark:text-gray-400 dark:hover:bg-rose-900/20 dark:hover:text-rose-400"
                        title="Delete"
                    >
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </DataTable>

        <BlockedTermFormModal
            v-model="showFormModal"
            :term="selectedTerm"
            :loading="formLoading"
            @save="handleSave"
        />

        <BlockedTermImportModal
            v-model="showImportModal"
            :loading="importLoading"
            @import="handleImport"
        />

        <BlockedTermExportModal
            v-model="showExportModal"
            :counts="counts"
            :loading="exportLoading"
            @export="handleExport"
        />

        <BlockedTermDeleteAllModal
            v-model="showDeleteAllModal"
            :counts="counts"
            :loading="deleteAllLoading"
            @confirm="handleDeleteAll"
        />

        <BlockedTermTestModal v-model="showTestModal" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    ArrowUpTrayIcon,
    ArrowDownTrayIcon,
    BeakerIcon,
    ChevronDownIcon,
    EllipsisHorizontalIcon
} from '@heroicons/vue/24/outline'
import DataTable from '@/components/DataTable.vue'
import BlockedTermFormModal from '@/components/Admin/BlockedTermFormModal.vue'
import BlockedTermImportModal from '@/components/Admin/BlockedTermImportModal.vue'
import BlockedTermExportModal from '@/components/Admin/BlockedTermExportModal.vue'
import BlockedTermDeleteAllModal from '@/components/Admin/BlockedTermDeleteAllModal.vue'
import BlockedTermTestModal from '@/components/Admin/BlockedTermTestModal.vue'
import { blockedTermsApi } from '@/services/adminApi'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useUtils } from '@/composables/useUtils'
import AnimatedButton from '@/components/AnimatedButton.vue'
import Avatar from '@/components/Profile/Avatar.vue'
import { EllipsisVerticalIcon } from '@heroicons/vue/24/solid'

const { formatNumber, formatRecentDate } = useUtils()
const { alertModal, confirmModal } = useAlertModal()
const route = useRoute()

const terms = ref([])
const loading = ref(false)
const pagination = ref({ prev_cursor: false, next_cursor: false })
const counts = ref({ total: null, block: null, allow: null })

const searchQuery = ref(route.query.q || '')
const sortBy = ref('created_at_desc')
const typeFilter = ref('all')

const showFormModal = ref(false)
const showImportModal = ref(false)
const showExportModal = ref(false)
const showDeleteAllModal = ref(false)
const showTestModal = ref(false)
const selectedTerm = ref(null)
const formLoading = ref(false)
const importLoading = ref(false)
const exportLoading = ref(false)
const deleteAllLoading = ref(false)

const DEBOUNCE_DELAY = 300
let searchTimeout = null

const columns = [
    { key: 'term', label: 'Term' },
    { key: 'note', label: 'Note' },
    { key: 'created_by', label: 'Added By' },
    { key: 'created_at', label: 'Added' }
]

const sortOptions = [
    { name: 'Newest', value: 'newest' },
    { name: 'Oldest', value: 'oldest' },
    { name: 'A → Z', value: 'term_asc' },
    { name: 'Z → A', value: 'term_desc' }
]

const typeTabs = computed(() => [
    { label: 'All terms', value: 'all', count: counts.value.total },
    { label: 'Blocked terms', value: 'block', count: counts.value.block },
    { label: 'Allowed terms', value: 'allow', count: counts.value.allow }
])

const fetchTerms = async (cursor = null, direction = 'next') => {
    loading.value = true
    try {
        const params = {
            cursor,
            direction,
            search: searchQuery.value || undefined,
            sort: sortBy.value || undefined,
            type: typeFilter.value === 'all' ? undefined : typeFilter.value
        }

        const response = await blockedTermsApi.getTerms(params)
        terms.value = response.data
        pagination.value = response.meta
        if (response.counts) counts.value = response.counts
    } catch (error) {
        alertModal('Error fetching terms', error?.message || error)
    } finally {
        loading.value = false
        fetchCounts()
    }
}

const debouncedFetch = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchTerms(), DEBOUNCE_DELAY)
}

const fetchCounts = async () => {
    try {
        const response = await blockedTermsApi.getCounts()
        counts.value = response
    } catch (error) {}
}

watch(searchQuery, debouncedFetch)
watch(sortBy, debouncedFetch)
watch(typeFilter, () => fetchTerms())

const setTypeFilter = (value) => {
    typeFilter.value = value
}

const handleSearch = (q) => (searchQuery.value = q)
const handleSort = (s) => (sortBy.value = s)

const nextPage = () => {
    if (pagination.value.next_cursor) fetchTerms(pagination.value.next_cursor, 'next')
}

const previousPage = () => {
    if (pagination.value.prev_cursor) fetchTerms(pagination.value.prev_cursor, 'previous')
}

const openCreateModal = () => {
    selectedTerm.value = null
    showFormModal.value = true
}

const openEditModal = (item) => {
    selectedTerm.value = item
    showFormModal.value = true
}

const openExportModal = () => {
    showExportModal.value = true
    fetchCounts()
}

const openDeleteAllModal = () => {
    showDeleteAllModal.value = true
    fetchCounts()
}

const handleSave = async (payload) => {
    formLoading.value = true
    try {
        if (selectedTerm.value?.id) {
            await blockedTermsApi.updateTerm(selectedTerm.value.id, payload)
        } else {
            await blockedTermsApi.createTerm(payload)
        }
        showFormModal.value = false
        selectedTerm.value = null
        fetchTerms()
    } catch (error) {
        alertModal('Error saving term', error?.response?.data?.message || error?.message || error)
    } finally {
        formLoading.value = false
    }
}

const confirmDelete = async (item) => {
    const result = await confirmModal(
        'Delete Term',
        `Remove "${item.term}" from the ${item.type === 'allow' ? 'allow' : 'block'} list? This will take effect immediately for all users.`,
        'Delete',
        'Cancel'
    )
    if (!result) return

    try {
        await blockedTermsApi.deleteTerm(item.id)
        fetchTerms()
    } catch (error) {
        alertModal('Error deleting term', error?.message || error)
    }
}

const handleImport = async ({ terms: list, type, note }) => {
    importLoading.value = true
    try {
        const response = await blockedTermsApi.bulkImport({ terms: list, type, note })
        showImportModal.value = false
        fetchTerms()
        alertModal(
            'Import complete',
            `Added ${response.inserted} new terms${response.skipped ? `, skipped ${response.skipped} duplicates` : ''}.`
        )
    } catch (error) {
        alertModal('Import failed', error?.response?.data?.message || error?.message || error)
    } finally {
        fetchCounts()
        importLoading.value = false
    }
}

const handleExport = async ({ type, format }) => {
    exportLoading.value = true
    try {
        const response = await blockedTermsApi.exportTerms({ type, format })
        const mimeType = format === 'json' ? 'application/json' : 'text/csv'
        const payload =
            typeof response.data === 'string'
                ? response.data
                : JSON.stringify(response.data, null, 2)
        const blob =
            response.data instanceof Blob ? response.data : new Blob([payload], { type: mimeType })

        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        const stamp = new Date().toISOString().split('T')[0]
        const ext = format === 'list' ? 'txt' : format
        link.href = url
        link.download = `blocked-terms-${type}-${stamp}.${ext}`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)

        showExportModal.value = false
    } catch (error) {
        alertModal('Export failed', error?.response?.data?.message || error?.message || error)
    } finally {
        exportLoading.value = false
    }
}

const handleDeleteAll = async ({ type }) => {
    deleteAllLoading.value = true
    try {
        const response = await blockedTermsApi.deleteAllTerms({ type })
        showDeleteAllModal.value = false
        fetchTerms()
        alertModal(
            'Terms deleted',
            `Removed ${formatNumber(response.deleted ?? 0)} term${response.deleted === 1 ? '' : 's'}.`
        )
    } catch (error) {
        alertModal('Delete failed', error?.response?.data?.message || error?.message || error)
    } finally {
        deleteAllLoading.value = false
    }
}

onUnmounted(() => {
    if (searchTimeout) clearTimeout(searchTimeout)
})

onMounted(() => fetchTerms())
</script>
