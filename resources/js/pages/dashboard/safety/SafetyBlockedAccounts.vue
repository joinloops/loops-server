<template>
    <SettingsLayout>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <button
                    class="flex items-center text-gray-500 hover:text-gray-400"
                    @click="$router.back()"
                >
                    <i class="bx bx-arrow-back text-[20px] mr-1"></i>
                </button>
                <h1 class="text-2xl font-semibold tracking-tight dark:text-gray-100">
                    Blocked accounts
                </h1>
            </div>
            <hr class="border-gray-300 dark:border-gray-700" />

            <section class="my-8">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <i class="bx bx-info-circle text-blue-500 text-[20px] mt-0.5"></i>
                        <div>
                            <h3 class="font-medium text-blue-800 mb-1">About blocking accounts</h3>
                            <p class="text-sm text-blue-700">
                                Blocked users can't follow you, view your profile, or interact with
                                your content. They won't be notified that you've blocked them.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <h2 class="tracking-tight font-light dark:text-gray-300">
                        Manage blocked accounts
                    </h2>
                    <button
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
                        @click="showAddModal = true"
                    >
                        <i class="bx bx-user-x text-[16px]"></i>
                        Block account
                    </button>
                </div>

                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm">
                    <div
                        class="p-4 border-b border-gray-200 dark:border-gray-700"
                        v-if="blockedAccounts.length > 0 || searchQuery"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="text-sm text-gray-600 dark:text-gray-300"
                                v-if="!searchQuery"
                            >
                                {{ totalBlocked }} blocked account{{
                                    totalBlocked.length !== 1 ? 's' : ''
                                }}
                                <span v-if="hasMore" class="text-gray-400"
                                    >(showing first results)</span
                                >
                            </span>
                            <span class="text-sm text-gray-600 dark:text-gray-300" v-else>
                                {{
                                    isSearching
                                        ? 'Searching...'
                                        : `${blockedAccounts.length} search result${blockedAccounts.length !== 1 ? 's' : ''}`
                                }}
                            </span>
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <input
                                        type="text"
                                        v-model="searchQuery"
                                        placeholder="Search blocked accounts..."
                                        class="w-60 px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 dark:text-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                    <i
                                        v-if="isSearching"
                                        class="bx bx-loader-alt animate-spin text-gray-400 absolute right-3 top-2.5"
                                    ></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="blockedAccounts.length > 0"
                        class="h-96 overflow-y-auto"
                        ref="scrollContainer"
                        @scroll="handleScroll"
                    >
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div v-for="item in blockedAccounts" :key="item.account.id" class="p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center"
                                        >
                                            <img
                                                v-if="item.account.avatar"
                                                :src="item.account.avatar"
                                                :alt="item.account.username"
                                                @error="
                                                    $event.target.src =
                                                        '/storage/avatars/default.jpg'
                                                "
                                                class="w-12 h-12 rounded-full object-cover"
                                            />
                                            <i
                                                v-else
                                                class="bx bx-user text-gray-400 text-[24px]"
                                            ></i>
                                        </div>
                                        <div>
                                            <div class="flex align-center gap-2">
                                                <div
                                                    class="font-medium text-gray-800 dark:text-gray-200"
                                                >
                                                    {{ item.account.name }}
                                                </div>
                                                <div class="text-gray-600 dark:text-gray-400">
                                                    @{{ item.account.username }}
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500">
                                                Blocked
                                                {{ formatDate(item.blocked_at) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-5">
                                        <router-link
                                            :to="`/@${item.account.username}`"
                                            class="text-sm text-blue-600 font-medium hover:text-blue-700 cursor-pointer"
                                        >
                                            View profile
                                        </router-link>
                                        <button
                                            class="text-sm text-red-600 font-medium hover:text-red-700 cursor-pointer"
                                            @click="unblockAccount(item.account.id)"
                                            :disabled="isUnblocking === item.account.id"
                                        >
                                            {{
                                                isUnblocking === item.account.id
                                                    ? 'Unblocking...'
                                                    : 'Unblock'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="isLoading && blockedAccounts.length > 0" class="p-4 text-center">
                            <i class="bx bx-loader-alt animate-spin text-gray-400 text-[20px]"></i>
                            <span class="text-sm text-gray-500 ml-2">Loading more...</span>
                        </div>

                        <div v-if="!hasMore && blockedAccounts.length > 0" class="p-4 text-center">
                            <span class="text-sm text-gray-500">
                                {{
                                    searchQuery
                                        ? 'No more search results'
                                        : "You've reached the end of your blocked accounts"
                                }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-else-if="isLoading && blockedAccounts.length === 0"
                        class="p-8 text-center"
                    >
                        <i class="bx bx-loader-alt animate-spin text-gray-400 text-[48px] mb-4"></i>
                        <h3 class="font-medium text-gray-600 mb-2">
                            {{
                                searchQuery
                                    ? 'Searching blocked accounts...'
                                    : 'Loading blocked accounts...'
                            }}
                        </h3>
                    </div>

                    <div v-else class="p-8 text-center">
                        <i class="bx bx-user-check text-gray-300 text-[48px] mb-4"></i>
                        <h3 class="font-medium text-gray-600 mb-2">
                            {{
                                searchQuery ? 'No matching blocked accounts' : 'No blocked accounts'
                            }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{
                                searchQuery
                                    ? 'Try adjusting your search term.'
                                    : "When you block someone, they'll appear here."
                            }}
                        </p>
                        <button
                            v-if="searchQuery"
                            @click="clearSearch"
                            class="mt-3 text-sm text-blue-600 hover:text-blue-700 font-medium"
                        >
                            Clear search
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-950 rounded-lg shadow-sm mt-6">
                    <div class="p-6">
                        <h3 class="font-medium text-gray-800 dark:text-gray-200 mb-3">
                            What happens when you block someone
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <i class="bx bx-hide text-gray-400 text-[16px] mt-0.5"></i>
                                <div>
                                    <h4
                                        class="font-medium text-gray-800 dark:text-gray-200 text-sm"
                                    >
                                        They can't see your content
                                    </h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        Your profile, videos, and comments become invisible to them.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bx bx-message-x text-gray-400 text-[16px] mt-0.5"></i>
                                <div>
                                    <h4
                                        class="font-medium text-gray-800 dark:text-gray-200 text-sm"
                                    >
                                        No interactions allowed
                                    </h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        They can't comment, like, or share your videos.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bx bx-user-minus text-gray-400 text-[16px] mt-0.5"></i>
                                <div>
                                    <h4
                                        class="font-medium text-gray-800 dark:text-gray-200 text-sm"
                                    >
                                        Automatic unfollowing
                                    </h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        If they follow you, they'll be automatically unfollowed.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="bx bx-bell-off text-gray-400 text-[16px] mt-0.5"></i>
                                <div>
                                    <h4
                                        class="font-medium text-gray-800 dark:text-gray-200 text-sm"
                                    >
                                        No notifications
                                    </h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        They won't be notified that you've blocked them.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div
            v-if="showAddModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        >
            <div class="bg-white dark:bg-slate-950 rounded-lg p-6 w-full max-w-md mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold dark:text-gray-100">Block account</h3>
                    <button class="text-gray-400 hover:text-gray-600" @click="closeAddModal">
                        <i class="bx bx-x text-[24px]"></i>
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Username
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            v-model="newBlockUsername"
                            @input="onUsernameInput"
                            placeholder="Enter username to block"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            :class="{
                                'border-red-300': newBlockUsername && !isValidUsername
                            }"
                        />

                        <div
                            v-if="showSuggestions && userSuggestions.length > 0"
                            class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-900 border border-gray-300 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                        >
                            <div
                                v-for="suggestion in userSuggestions"
                                :key="suggestion.id"
                                class="p-3 hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                                @click="selectSuggestion(suggestion)"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center"
                                    >
                                        <img
                                            v-if="suggestion.avatar"
                                            :src="suggestion.avatar"
                                            :alt="suggestion.username"
                                            @error="
                                                $event.target.src = '/storage/avatars/default.jpg'
                                            "
                                            class="w-8 h-8 rounded-full object-cover"
                                        />
                                        <i v-else class="bx bx-user text-gray-400 text-[16px]"></i>
                                    </div>
                                    <div>
                                        <h4
                                            class="font-medium text-gray-800 dark:text-gray-200 text-sm"
                                        >
                                            {{ suggestion.name }}
                                        </h4>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            @{{ suggestion.username }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="isSearchingUsers" class="absolute right-3 top-2.5">
                            <i class="bx bx-loader-alt animate-spin text-gray-400"></i>
                        </div>
                    </div>

                    <p
                        v-if="newBlockUsername && !isValidUsername"
                        class="text-sm text-red-600 mt-1"
                    >
                        Username must be 1-30 characters and contain only letters, numbers, and
                        underscores.
                    </p>
                </div>

                <div class="flex gap-3 justify-end">
                    <button
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-800 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                        @click="closeAddModal"
                    >
                        Cancel
                    </button>
                    <button
                        :disabled="!canBlock || isBlocking"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors cursor-pointer"
                        @click="blockUser"
                    >
                        {{ isBlocking ? 'Blocking...' : 'Block account' }}
                    </button>
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import SettingsLayout from '~/layouts/SettingsLayout.vue'
import axios from '~/plugins/axios'
import { useUtils } from '@/composables/useUtils'
const { formatDate } = useUtils()
import { useAlertModal } from '@/composables/useAlertModal.js'
const { alertModal, confirmModal } = useAlertModal()
const axiosInstance = axios.getAxiosInstance()

const searchQuery = ref('')
const showAddModal = ref(false)
const newBlockUsername = ref('')
const newBlockId = ref('')
const isSearchingUsers = ref(false)
const showSuggestions = ref(false)
const searchTimeout = ref(null)
const blockedAccounts = ref([])
const loadedTotal = ref(false)
const totalBlocked = ref()

const isLoading = ref(false)
const isSearching = ref(false)
const hasMore = ref(true)
const nextCursor = ref(null)
const scrollContainer = ref(null)
const searchDebounceTimeout = ref(null)
const isBlocking = ref(false)
const isUnblocking = ref(null)

const fetchTotalBlocked = async () => {
    try {
        await axiosInstance.get('/api/v1/account/settings/total-blocked-accounts').then((res) => {
            loadedTotal.value = true
            totalBlocked.value = res.data.data.count
        })
    } catch {}
}

const fetchBlockedAccounts = async (cursor = null, search = null, reset = false) => {
    if (isLoading.value) return

    isLoading.value = true

    try {
        const params = {}
        if (cursor) params.cursor = cursor
        if (search) params.q = search

        const response = await axiosInstance.get('/api/v1/account/settings/blocked-accounts', {
            params
        })

        if (reset || !cursor) {
            blockedAccounts.value = response.data.data
        } else {
            const newAccounts = response.data.data.filter(
                (newAccount) =>
                    !blockedAccounts.value.some(
                        (existing) => existing.account.id === newAccount.account.id
                    )
            )
            blockedAccounts.value.push(...newAccounts)
        }

        nextCursor.value = response.data.meta.next_cursor
        hasMore.value = !!response.data.meta.next_cursor
    } catch (err) {
        alertModal('Error', 'Failed to load blocked accounts')
    } finally {
        isLoading.value = false
    }
}

const loadMore = async () => {
    if (!hasMore.value || isLoading.value) return

    await fetchBlockedAccounts(nextCursor.value, searchQuery.value)
}

const handleScroll = () => {
    if (!scrollContainer.value || isLoading.value || !hasMore.value) return

    const { scrollTop, scrollHeight, clientHeight } = scrollContainer.value
    const threshold = 100

    if (scrollTop + clientHeight >= scrollHeight - threshold) {
        loadMore()
    }
}

const performSearch = async (query) => {
    if (searchDebounceTimeout.value) {
        clearTimeout(searchDebounceTimeout.value)
    }

    searchDebounceTimeout.value = setTimeout(async () => {
        isSearching.value = true
        hasMore.value = true
        nextCursor.value = null

        try {
            await fetchBlockedAccounts(null, query, true)
        } finally {
            isSearching.value = false
        }
    }, 300)
}

const clearSearch = () => {
    searchQuery.value = ''
}

watch(searchQuery, (newQuery) => {
    if (newQuery.trim()) {
        performSearch(newQuery.trim())
    } else {
        hasMore.value = true
        nextCursor.value = null
        fetchBlockedAccounts(null, null, true)
    }
})

const userSuggestions = ref([])

const isValidUsername = computed(() => {
    if (!newBlockUsername.value) return false
    const usernameRegex = /^[a-zA-Z0-9_\-.@]{1,30}$/
    return usernameRegex.test(newBlockUsername.value)
})

const canBlock = computed(() => {
    return isValidUsername.value && !isUserAlreadyBlocked.value
})

const isUserAlreadyBlocked = computed(() => {
    return blockedAccounts.value.some(
        (account) => account.account.username.toLowerCase() === newBlockUsername.value.toLowerCase()
    )
})

const onUsernameInput = () => {
    clearTimeout(searchTimeout.value)

    if (newBlockUsername.value.length < 1) {
        showSuggestions.value = false
        userSuggestions.value = []
        return
    }

    isSearchingUsers.value = true
    showSuggestions.value = false

    searchTimeout.value = setTimeout(() => {
        searchUsers()
    }, 300)
}

const searchUsers = async () => {
    const query = newBlockUsername.value.toLowerCase()

    try {
        const response = await axiosInstance.post(
            '/api/v1/account/settings/blocked-account-search',
            {
                q: query
            }
        )

        userSuggestions.value = response.data.data
        showSuggestions.value = response.data.data.length > 0
    } catch (err) {
        console.error('Failed to search users:', err)
        alertModal('Oops!', 'An unexpected error occurred.')
    } finally {
        isSearchingUsers.value = false
    }
}

const selectSuggestion = (suggestion) => {
    newBlockUsername.value = suggestion.username
    newBlockId.value = suggestion.id
    showSuggestions.value = false
    userSuggestions.value = []
}

const closeAddModal = () => {
    showAddModal.value = false
    newBlockUsername.value = ''
    newBlockId.value = ''
    showSuggestions.value = false
    userSuggestions.value = []
    clearTimeout(searchTimeout.value)
}

const blockUser = async () => {
    if (!canBlock.value || isBlocking.value) return

    isBlocking.value = true

    try {
        await axiosInstance.post(`/api/v1/account/block/${newBlockId.value}`).then((response) => {
            blockedAccounts.value.unshift({
                account: response.data,
                blocked_at: new Date().toISOString()
            })
        })
        await fetchTotalBlocked()

        closeAddModal()
    } catch (err) {
        console.error('Failed to block user:', err)
        alertModal('Error', 'Failed to block account')
    } finally {
        isBlocking.value = false
    }
}

const unblockAccount = async (accountId) => {
    const account = blockedAccounts.value.find((acc) => acc.account.id === accountId)
    if (!account) return

    const confirmed = await confirmModal(
        'Unblock Account',
        `Are you sure you want to unblock @${account.account.username}?`
    )

    if (confirmed) {
        isUnblocking.value = accountId

        try {
            await axiosInstance.post(`/api/v1/account/unblock/${accountId}`)
            blockedAccounts.value = blockedAccounts.value.filter(
                (acc) => acc.account.id !== accountId
            )
        } catch (err) {
            console.error('Failed to unblock account:', err)
            alertModal('Error', 'Failed to unblock account')
        } finally {
            await fetchTotalBlocked()
            isUnblocking.value = null
        }
    }
}

onMounted(async () => {
    await fetchTotalBlocked()
    await fetchBlockedAccounts()

    await nextTick()
    if (scrollContainer.value) {
        scrollContainer.value.addEventListener('scroll', handleScroll)
    }
})

onUnmounted(() => {
    if (scrollContainer.value) {
        scrollContainer.value.removeEventListener('scroll', handleScroll)
    }
    if (searchDebounceTimeout.value) {
        clearTimeout(searchDebounceTimeout.value)
    }
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value)
    }
})

watch(showAddModal, (newVal) => {
    if (!newVal) {
        showSuggestions.value = false
    }
})
</script>
