<template>
    <div>
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Instances</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Manage fediverse instances</p>
            </div>
            <div
                class="grid grid-cols-1 gap-px bg-gradient-to-b from-gray-100/50 to-gray-400/80 dark:from-gray-600/50 dark:to-gray-800/70 sm:grid-cols-2 lg:grid-cols-4 rounded-lg border border-gray-300 dark:border-gray-600/70 overflow-hidden transition-all duration-300 ease-in-out"
            >
                <div
                    v-for="stat in instanceStats"
                    :key="stat.name"
                    class="relative bg-gradient-to-b from-white to-gray-50/80 dark:from-gray-900 dark:to-gray-800/90 px-4 py-3 sm:px-6 hover:from-gray-50/50 hover:to-gray-100/60 dark:hover:from-gray-800/90 dark:hover:to-gray-700/80 transition-all duration-200 ease-in-out overflow-hidden"
                >
                    <p class="text-sm/5 font-medium text-gray-600 dark:text-gray-300">
                        {{ stat.name }}
                    </p>
                    <p class="mt-1 flex items-baseline gap-x-2">
                        <span
                            class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white"
                            >{{ stat.value }}</span
                        >
                        <span v-if="stat.unit" class="text-sm text-gray-500 dark:text-gray-400">{{
                            stat.unit
                        }}</span>
                    </p>
                </div>
            </div>
            <AnimatedButton @click="openAddModal"> Add Instance </AnimatedButton>
        </div>
        <DataTable
            title="Instances"
            :columns="columns"
            :data="instances"
            :loading="loading"
            :has-previous="pagination.prev_cursor"
            :has-next="pagination.next_cursor"
            :has-actions="false"
            :sort-options="sortOptions"
            :initialSearchQuery="searchQuery"
            @sort="handleSort"
            @search="handleSearch"
            @refresh="fetchInstances"
            @previous="previousPage"
            @next="nextPage"
        >
            <template #cell-domain="{ value, item }">
                <div class="flex items-center gap-2">
                    <div class="font-medium truncate max-w-xs" :title="value">
                        <router-link
                            :to="`/admin/instances/${item.id}`"
                            class="text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                        >
                            {{ value }}
                        </router-link>
                    </div>
                </div>
            </template>

            <template #cell-user_count="{ value }">
                <div class="flex justify-center font-mono text-sm font-semibold">
                    {{ formatNumber(value) }}
                </div>
            </template>

            <template #cell-video_count="{ value, item }">
                <div class="flex justify-center gap-4 items-center">
                    <div class="flex flex-col items-end">
                        <div class="font-mono text-sm font-semibold">{{ formatNumber(value) }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">videos</div>
                    </div>
                    <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>
                    <div class="flex flex-col items-start">
                        <div class="font-mono text-sm font-semibold">
                            {{ formatNumber(item.comment_count + item.reply_count) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">comments</div>
                    </div>
                </div>
            </template>

            <template #cell-follower_count="{ value, item }">
                <div class="flex justify-center gap-4 items-center">
                    <div class="flex flex-col items-end">
                        <div class="font-mono text-sm font-semibold">{{ formatNumber(value) }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">followers</div>
                    </div>
                    <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>
                    <div class="flex flex-col items-start">
                        <div class="font-mono text-sm font-semibold">
                            {{ formatNumber(item.following_count) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">following</div>
                    </div>
                </div>
            </template>

            <template #cell-software="{ value }">
                <button
                    v-if="value"
                    @click="toggleSoftwareSearch(value)"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/50 dark:hover:bg-blue-950 rounded-md transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    {{ value }}
                </button>
                <span v-else class="text-xs text-gray-400 dark:text-gray-500 italic">unknown</span>
            </template>

            <template #cell-version="{ value }">
                <span v-if="value" class="font-mono text-xs text-gray-700 dark:text-gray-300">
                    {{ value }}
                </span>
                <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
            </template>

            <template #cell-status="{ value }">
                <div class="inline-flex items-center gap-1.5">
                    <span class="relative flex h-2 w-2">
                        <span
                            v-if="value === 'active'"
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"
                        ></span>
                        <span
                            :class="[
                                'relative inline-flex rounded-full h-2 w-2',
                                value === 'active' ? 'bg-green-500' : 'bg-red-500'
                            ]"
                        ></span>
                    </span>
                    <span
                        :class="[
                            'text-sm font-medium capitalize',
                            value === 'active'
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-600 dark:text-red-400'
                        ]"
                    >
                        {{ value }}
                    </span>
                </div>
            </template>

            <template #cell-federation_state="{ value }">
                <span
                    :class="[
                        'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium',
                        value === 5
                            ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-400'
                            : value === 3
                              ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950/50 dark:text-yellow-400'
                              : value === 1
                                ? 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-400'
                                : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                    ]"
                >
                    {{ getFederationStateLabel(value) }}
                </span>
            </template>

            <template #cell-is_blocked="{ value }">
                <span
                    v-if="value"
                    class="inline-flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400"
                >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    Blocked
                </span>
                <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
            </template>

            <template #cell-is_silenced="{ value }">
                <span
                    v-if="value"
                    class="inline-flex items-center gap-1 text-xs font-medium text-orange-600 dark:text-orange-400"
                >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z"
                            clip-rule="evenodd"
                        />
                        <path
                            d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"
                        />
                    </svg>
                    Silenced
                </span>
                <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
            </template>

            <template #cell-created_at="{ value }">
                <div class="flex flex-col items-start">
                    <span class="text-sm text-gray-900 dark:text-gray-100">{{
                        formatDate(value)
                    }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{
                        formatRelativeTime(value)
                    }}</span>
                </div>
            </template>
        </DataTable>

        <div
            v-if="showAddModal"
            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50"
        >
            <div
                class="bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto"
            >
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            Add Instance{{ addMode === 'mass' ? 's' : '' }}
                        </h2>
                        <div class="flex items-center">
                            <div class="flex bg-gray-100 dark:bg-slate-900 rounded-lg p-1">
                                <button
                                    @click="addMode = 'single'"
                                    :class="[
                                        'px-3 py-1.5 text-[10px] lg:text-[14px] font-medium rounded-md transition-all duration-200 relative cursor-pointer',
                                        addMode === 'single'
                                            ? 'bg-white dark:bg-slate-700 text-black dark:text-white shadow-sm'
                                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'
                                    ]"
                                >
                                    Single Instance
                                </button>
                                <button
                                    @click="addMode = 'mass'"
                                    :class="[
                                        'px-3 py-1.5 text-[10px] lg:text-[14px] font-medium rounded-md transition-all duration-200 relative cursor-pointer',
                                        addMode === 'mass'
                                            ? 'bg-white dark:bg-slate-700 text-black dark:text-white shadow-sm'
                                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'
                                    ]"
                                >
                                    Bulk Add
                                </button>
                            </div>
                        </div>
                        <div class="w-[120px] flex justify-end">
                            <button
                                @click="closeAddModal"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            >
                                <svg
                                    class="w-6 h-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    ></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="handleSubmit">
                        <div v-if="addMode === 'single'" class="space-y-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    Domain
                                </label>
                                <input
                                    v-model="form.domain"
                                    type="text"
                                    placeholder="example.com"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                />
                            </div>
                        </div>

                        <div v-if="addMode === 'mass'" class="space-y-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    Domains (comma-separated)
                                </label>
                                <textarea
                                    v-model="form.domains"
                                    rows="6"
                                    :disabled="submitting"
                                    placeholder="example1.com, example2.com, example3.com"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50"
                                    required
                                ></textarea>
                                <div class="flex justify-between items-center">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        Enter domains separated by commas, spaces, or new lines
                                        <span
                                            v-if="bulkDomainCount > MAX_BULK_DOMAIN_LIMIT"
                                            class="block text-amber-600 mt-1"
                                        >
                                            ⚠️ Large lists will be processed in batches of
                                            {{ MAX_BULK_DOMAIN_LIMIT }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <span
                                            :class="[
                                                bulkDomainCount > MAX_BULK_DOMAIN_LIMIT
                                                    ? 'text-amber-600 font-medium'
                                                    : ''
                                            ]"
                                        >
                                            {{ bulkDomainCount }}
                                        </span>
                                        <span class="text-sm opacity-75 pl-1">unique</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 mt-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Options
                            </h3>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <label
                                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            Suspended
                                        </label>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            Block federation and discovery
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        :disabled="submitting"
                                        @click="form.is_blocked = !form.is_blocked"
                                        :class="[
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50',
                                            form.is_blocked
                                                ? 'bg-red-600'
                                                : 'bg-gray-200 dark:bg-gray-700'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                                form.is_blocked ? 'translate-x-6' : 'translate-x-1'
                                            ]"
                                        ></span>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <label
                                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            Allow Video Posts
                                        </label>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            Accept and process incoming top-level video posts that
                                            can appear in feeds
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        :disabled="submitting"
                                        @click="form.allow_video_posts = !form.allow_video_posts"
                                        :class="[
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50',
                                            form.allow_video_posts
                                                ? 'bg-green-600'
                                                : 'bg-gray-200 dark:bg-gray-700'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                                form.allow_video_posts
                                                    ? 'translate-x-6'
                                                    : 'translate-x-1'
                                            ]"
                                        ></span>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <label
                                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            Allow Videos in FYF
                                        </label>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            Process videos for use in the For You Feed algorithm
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        :disabled="submitting"
                                        @click="
                                            form.allow_videos_in_fyf = !form.allow_videos_in_fyf
                                        "
                                        :class="[
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50',
                                            form.allow_videos_in_fyf
                                                ? 'bg-green-600'
                                                : 'bg-gray-200 dark:bg-gray-700'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                                form.allow_videos_in_fyf
                                                    ? 'translate-x-6'
                                                    : 'translate-x-1'
                                            ]"
                                        ></span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    Admin Notes
                                </label>
                                <textarea
                                    v-model="form.admin_notes"
                                    rows="3"
                                    :disabled="submitting"
                                    placeholder="Add any admin notes about this instance..."
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50"
                                ></textarea>
                            </div>
                        </div>

                        <div
                            class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700"
                        >
                            <button
                                type="button"
                                @click="closeAddModal"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                :disabled="submitting"
                            >
                                Cancel
                            </button>
                            <AnimatedButton
                                type="submit"
                                :disabled="submitting || canSubmitAddInstances"
                            >
                                <span v-if="!submitting">
                                    Add
                                    {{
                                        addMode === 'mass'
                                            ? `${bulkDomainCount} Instance${bulkDomainCount !== 1 ? 's' : ''}`
                                            : 'Instance'
                                    }}
                                </span>
                                <span v-else class="flex items-center">
                                    <svg
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
                                    {{ addMode === 'mass' ? 'Processing...' : 'Adding...' }}
                                </span>
                            </AnimatedButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, onUnmounted, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import { instancesApi } from '@/services/adminApi'
import { useUtils } from '@/composables/useUtils'
import { useAlertModal } from '@/composables/useAlertModal.js'

const { alertModal, confirmModal } = useAlertModal()
const { truncateMiddle, formatDate, formatNumber, normalizeDomain, isValidDomain } = useUtils()

const router = useRouter()
const profiles = ref([])
const instances = ref([])
const instanceStats = ref([])
const loading = ref(false)
const pagination = ref({
    cursor: null,
    hasPrevious: false,
    hasNext: false
})

const searchQuery = ref('')
const sortBy = ref('')
const DEBOUNCE_DELAY = 300
const MAX_BULK_DOMAIN_LIMIT = 100
let searchTimeout = null

const showAddModal = ref(false)
const addMode = ref('single')
const submitting = ref(false)
const submitError = ref(false)
const submitErrorMessage = ref('')

const form = ref({
    domain: '',
    domains: '',
    is_blocked: false,
    allow_video_posts: false,
    allow_videos_in_fyf: false,
    admin_notes: ''
})

const columns = [
    { key: 'domain', label: 'Domain' },
    { key: 'user_count', label: 'Users' },
    { key: 'video_count', label: 'Videos / Comments' },
    { key: 'follower_count', label: 'Followers / Following' },
    { key: 'software', label: 'Software' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'First Seen' }
]

const sortOptions = [
    { name: 'Domain A-Z', value: 'domain_asc' },
    { name: 'Domain Z-A', value: 'domain_desc' },
    { name: 'Most Followers', value: 'follower_count_desc' },
    { name: 'Most Videos', value: 'video_count_desc' },
    { name: 'Newest', value: 'created_at_desc' },
    { name: 'Oldest', value: 'created_at_asc' },
    { name: 'Suspended', value: 'is_blocked' },
    { name: 'Last Updated', value: 'updated_at_desc' }
]

const fetchInstanceStats = async () => {
    const stats = await instancesApi.getInstanceStats()
    instanceStats.value = stats.data
}

const fetchInstances = async (cursor = null, direction = 'next') => {
    loading.value = true
    try {
        const params = { cursor, direction }

        if (searchQuery.value) {
            params.search = searchQuery.value
        }

        if (sortBy.value) {
            params.sort = sortBy.value
        }

        const response = await instancesApi.getInstances(params)
        instances.value = response.data
        pagination.value = response.meta
    } catch (error) {
        console.error('Error fetching profiles:', error)
    } finally {
        loading.value = false
    }
}

// Modal functions
const openAddModal = () => {
    showAddModal.value = true
    resetForm()
}

const closeAddModal = () => {
    showAddModal.value = false
    resetForm()
}

const resetForm = () => {
    form.value = {
        domain: '',
        domains: '',
        is_blocked: false,
        allow_video_posts: false,
        allow_videos_in_fyf: false,
        admin_notes: ''
    }
    addMode.value = 'single'
}

const addSingleInstance = async () => {
    const instanceData = {
        domain: 'https://' + form.value.domain.trim(),
        is_blocked: form.value.is_blocked,
        allow_video_posts: form.value.allow_video_posts,
        allow_videos_in_fyf: form.value.allow_videos_in_fyf,
        admin_notes: form.value.admin_notes.trim()
    }

    await instancesApi.createInstance(instanceData)
    await fetchInstances()
}

const handleSubmit = async () => {
    submitting.value = true
    submitError.value = false
    submitErrorMessage.value = ''

    try {
        if (addMode.value === 'single') {
            await addSingleInstance()
        } else {
            await addMassInstancesInChunks()
        }

        closeAddModal()
        await fetchInstances()
    } catch (error) {
        submitError.value = true
        submitErrorMessage.value = error?.response?.data?.message || 'Something went wrong!'
        alertModal('Error', submitErrorMessage.value)
        console.error('Error adding instance(s):', error)
    } finally {
        submitting.value = false
    }
}

const addMassInstancesInChunks = async () => {
    const raw = Array.isArray(form.value.domains)
        ? form.value.domains.join(',')
        : String(form.value.domains ?? '')

    const domains = raw
        .split(/[\s,;\n]+/)
        .map(normalizeDomain)
        .filter(Boolean)

    if (domains.length === 0) {
        throw new Error('No valid domains provided')
    }

    const uniqueDomains = [...new Set(domains)]

    const invalidDomains = uniqueDomains.filter((domain) => !isValidDomain(domain))
    if (invalidDomains.length > 0) {
        throw new Error(
            `Invalid domains found: ${invalidDomains.slice(0, 5).join(', ')}${invalidDomains.length > 5 ? ` and ${invalidDomains.length - 5} more` : ''}`
        )
    }

    const chunks = []
    for (let i = 0; i < uniqueDomains.length; i += MAX_BULK_DOMAIN_LIMIT) {
        chunks.push(uniqueDomains.slice(i, i + MAX_BULK_DOMAIN_LIMIT))
    }

    console.log(`Processing ${uniqueDomains.length} domains in ${chunks.length} chunk(s)`)

    let processedCount = 0
    let successCount = 0
    let errorCount = 0
    const errors = []

    for (let i = 0; i < chunks.length; i++) {
        const chunk = chunks[i]

        try {
            submitting.value = true

            console.log(`Processing chunk ${i + 1}/${chunks.length} with ${chunk.length} domains`)

            const domainMap = chunk.map((domain) => 'https://' + domain)

            const instancesData = {
                domains: domainMap,
                is_blocked: form.value.is_blocked,
                allow_video_posts: form.value.allow_video_posts,
                allow_videos_in_fyf: form.value.allow_videos_in_fyf,
                admin_notes: form.value.admin_notes.trim()
            }

            await instancesApi.createInstances(instancesData)
            successCount += chunk.length
        } catch (error) {
            console.error(`Error processing chunk ${i + 1}:`, error)
            errorCount += chunk.length
            errors.push({
                chunk: i + 1,
                domains: chunk,
                error: error?.response?.data?.message || error.message
            })
        }

        processedCount += chunk.length

        if (i < chunks.length - 1) {
            await new Promise((resolve) => setTimeout(resolve, 1000))
        }
    }

    if (errors.length > 0) {
        const errorMessage = `
            <div class="text-left">
                <p class="mb-2"><strong>Bulk operation completed:</strong></p>
                <ul class="text-sm space-y-1">
                    <li class="text-green-600">✓ ${successCount} domains added successfully</li>
                    <li class="text-red-600">✗ ${errorCount} domains failed</li>
                </ul>
                <p class="mt-3 text-sm"><strong>First few errors:</strong></p>
                <ul class="text-xs text-gray-600 mt-1">
                    ${errors
                        .slice(0, 3)
                        .map((e) => `<li>Chunk ${e.chunk}: ${e.error}</li>`)
                        .join('')}
                    ${errors.length > 3 ? `<li>... and ${errors.length - 3} more error(s)</li>` : ''}
                </ul>
            </div>
        `

        alertModal('Bulk Operation Results', errorMessage)
    } else {
        alertModal(
            'Success',
            `All ${successCount} domains were added successfully!<br /><br /><span class="text-sm text-gray-400 dark:text-gray-500">It may take a few minutes for the domains to appear.</span>`
        )
    }
}

const canSubmitAddInstances = computed(() => {
    if (addMode.value === 'single') {
        const domain = form.value.domain?.trim()
        if (!domain) return true
        const normalized = normalizeDomain(domain)
        return !isValidDomain(normalized)
    }

    const raw = Array.isArray(form.value.domains)
        ? form.value.domains.join(',')
        : String(form.value.domains ?? '')

    if (!raw.trim()) return true

    const domains = raw
        .split(/[\s,;\n]+/)
        .map((domain) => domain.trim())
        .map(normalizeDomain)
        .filter(Boolean)

    if (domains.length === 0) return true

    const unique = [...new Set(domains)]

    return !unique.every(isValidDomain)
})

const bulkDomainCount = computed(() => {
    const raw = Array.isArray(form.value.domains)
        ? form.value.domains.join(',')
        : String(form.value.domains ?? '')

    const domains = raw
        .split(/[\s,;\n]+/)
        .map((domain) => domain.trim())
        .filter((domain) => domain.length > 0)

    const unique = [...new Set(domains.map(normalizeDomain))]
    return unique.length
})

watch(searchQuery, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchInstances()
    }, DEBOUNCE_DELAY)
})

watch(sortBy, (newQuery) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        fetchInstances()
    }, DEBOUNCE_DELAY)
})

const handleSort = (sortValue) => {
    sortBy.value = sortValue
}

const handleSearch = (query) => {
    searchQuery.value = query
}

const toggleSoftwareSearch = (query) => {
    searchQuery.value = 'software:' + query
}

const nextPage = () => {
    if (pagination.value.next_cursor) {
        fetchInstances(pagination.value.next_cursor, 'next')
    }
}

const previousPage = () => {
    if (pagination.value.prev_cursor) {
        fetchInstances(pagination.value.prev_cursor, 'previous')
    }
}

const getFederationStateLabel = (state) => {
    const labels = {
        1: 'Blocked',
        2: 'Suspended',
        3: 'Limited',
        4: 'Pending',
        5: 'Active',
        6: 'Silenced'
    }
    return labels[state] || 'Unknown'
}

const formatRelativeTime = (date) => {
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })
    const diffInDays = Math.floor((new Date(date) - new Date()) / (1000 * 60 * 60 * 24))

    if (Math.abs(diffInDays) < 1) return 'today'
    if (Math.abs(diffInDays) < 7) return rtf.format(diffInDays, 'day')
    if (Math.abs(diffInDays) < 30) return rtf.format(Math.floor(diffInDays / 7), 'week')
    if (Math.abs(diffInDays) < 365) return rtf.format(Math.floor(diffInDays / 30), 'month')
    return rtf.format(Math.floor(diffInDays / 365), 'year')
}

onUnmounted(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
})

onMounted(() => {
    fetchInstances()
    fetchInstanceStats()
})
</script>
