<template>
    <MainLayout>
        <div class="max-w-2xl mx-auto md:px-5">
            <div class="flex items-center justify-between px-3 md:px-0 py-6 gap-3">
                <div class="flex items-center min-w-0 flex-1">
                    <h1 class="text-2xl font-semibold tracking-tight dark:text-gray-100 truncate">
                        {{ $t('common.notifications') }}
                    </h1>
                    <span
                        v-if="totalUnreadCount > 0"
                        class="ml-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-500 text-red-100 dark:bg-red-600 dark:text-white flex-shrink-0"
                    >
                        {{ formatNumber(totalUnreadCount) }}
                        <span class="hidden sm:inline ml-1">{{ $t('common.unread') }}</span>
                    </span>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <button
                        v-if="showMarkAllRead"
                        class="inline-flex items-center justify-center font-bold bg-[#F02C56] border border-[#F02C56] text-white rounded-lg hover:bg-[#F02C56]/90 hover:border-[#F02C5699] cursor-pointer transition-colors px-3 py-2 text-xs sm:px-5 sm:py-2 sm:text-sm"
                        @click="handleMarkAllRead"
                    >
                        <span>{{ $t('common.markAllRead') }}</span>
                    </button>

                    <button
                        @click="handleRefresh"
                        :disabled="isCurrentTabLoading"
                        class="hidden md:inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transition-colors"
                        :aria-label="$t('common.refresh')"
                    >
                        <ArrowPathIcon
                            class="w-4 h-4"
                            :class="{ 'animate-spin': isCurrentTabLoading }"
                        />
                    </button>
                </div>
            </div>

            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav
                    class="flex -mb-px overflow-x-auto scrollbar-hide px-3 md:px-0"
                    aria-label="Tabs"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'whitespace-nowrap py-3 px-8 border-b-2 font-medium text-sm transition-colors flex-shrink-0 cursor-pointer',
                            activeTab === tab.id
                                ? 'border-[#F02C56] text-[#F02C56] dark:text-[#F02C56]'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                        ]"
                    >
                        <span class="flex items-center gap-2">
                            {{ tab.label }}
                            <span
                                v-if="tab.unreadCount > 0"
                                class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold rounded-full bg-red-500 text-white min-w-[20px]"
                            >
                                {{ formatNumber(tab.unreadCount) }}
                            </span>
                        </span>
                    </button>
                </nav>
            </div>

            <div v-if="activeTab === 'activity'">
                <div class="px-3 md:px-0 mb-4">
                    <div class="relative inline-block" ref="dropdownRef">
                        <button
                            @click="toggleFilterDropdown"
                            class="inline-flex items-center justify-between px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#F02C56] dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 cursor-pointer transition-colors min-w-[140px]"
                        >
                            <span>{{ activeFilterLabel }}</span>
                            <ChevronDownIcon
                                class="ml-2 h-4 w-4 transition-transform"
                                :class="{ 'rotate-180': showFilterDropdown }"
                            />
                        </button>

                        <transition
                            enter-active-class="transition ease-out duration-100"
                            enter-from-class="transform opacity-0 scale-95"
                            enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95"
                        >
                            <div
                                v-if="showFilterDropdown"
                                class="absolute left-0 mt-2 w-48 rounded-lg shadow-lg bg-white dark:bg-gray-800 ring-1 ring-gray-300 dark:ring-gray-700 z-10"
                            >
                                <div class="py-1" role="menu">
                                    <button
                                        v-for="filter in activityFilters"
                                        :key="filter.value"
                                        @click="selectFilter(filter.value)"
                                        :class="[
                                            'w-full text-left px-4 py-2 text-sm transition-colors',
                                            activityFilter === filter.value
                                                ? 'bg-[#F02C56]/10 text-[#F02C56] dark:bg-[#F02C56]/20'
                                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                                        ]"
                                        role="menuitem"
                                    >
                                        <span class="flex items-center justify-between">
                                            <span>{{ filter.label }}</span>
                                            <CheckIcon
                                                v-if="activityFilter === filter.value"
                                                class="h-4 w-4 text-[#F02C56]"
                                            />
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>

                <div
                    v-if="currentError"
                    class="p-4 mb-6 bg-red-50 border border-red-200 rounded-md dark:bg-red-900/20 dark:border-red-800"
                >
                    <div class="flex">
                        <ExclamationTriangleIcon class="h-5 w-5 text-red-400" />
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                {{ t('notifications.errorLoadingNotifications') }}
                            </h3>
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                                {{ currentError }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="isCurrentTabLoading && currentNotifications.length === 0"
                    class="space-y-4"
                >
                    <div v-for="i in 5" :key="i" class="animate-pulse">
                        <div
                            class="flex items-start space-x-3 p-4 bg-white rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700"
                        >
                            <div class="w-10 h-10 bg-gray-300 rounded-full dark:bg-gray-600"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-gray-300 rounded w-3/4 dark:bg-gray-600"></div>
                                <div class="h-3 bg-gray-300 rounded w-1/2 dark:bg-gray-600"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="currentNotifications.length > 0" class="space-y-6">
                    <div
                        v-for="(groupNotifications, date) in currentGroupedNotifications"
                        :key="date"
                    >
                        <h3
                            class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3 sticky top-17 lg:top-0 bg-gray-50 dark:bg-gray-900 py-2 z-1 px-3"
                        >
                            {{ formatDate(date) }} ({{ groupNotifications.length }})
                        </h3>

                        <div class="space-y-2">
                            <NotificationItem
                                v-for="notification in groupNotifications"
                                :key="notification.id"
                                :notification="notification"
                                :current-user-id="authStore?.user?.id"
                                @mark-as-read="(id) => markAsRead(id, activeTab)"
                            />
                        </div>
                    </div>

                    <div class="flex justify-center py-8">
                        <button
                            v-if="currentHasMore && !isCurrentTabLoading"
                            @click="loadMore(activeTab)"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 cursor-pointer"
                        >
                            {{ t('common.loadMore') }}
                        </button>

                        <div
                            v-else-if="isCurrentTabLoading"
                            class="flex items-center text-gray-500 dark:text-gray-400"
                        >
                            <ArrowPathIcon class="w-5 h-5 animate-spin" />
                        </div>

                        <p
                            v-else-if="!currentHasMore"
                            class="text-gray-500 dark:text-gray-400 text-sm"
                        >
                            {{ t('notifications.allCaughtUp') }}
                        </p>
                    </div>
                </div>

                <div v-else class="text-center py-12">
                    <BellIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ t('notifications.noNotifications') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('notifications.allCaughtUp') }}
                    </p>
                </div>
            </div>

            <div v-else-if="activeTab === 'followers'">
                <div
                    v-if="isCurrentTabLoading && currentNotifications.length === 0"
                    class="space-y-4"
                >
                    <div v-for="i in 5" :key="i" class="animate-pulse">
                        <div
                            class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700"
                        >
                            <div class="flex items-center space-x-3">
                                <div
                                    class="w-12 h-12 bg-gray-300 rounded-full dark:bg-gray-600"
                                ></div>
                                <div class="space-y-2">
                                    <div
                                        class="h-4 bg-gray-300 rounded w-32 dark:bg-gray-600"
                                    ></div>
                                    <div
                                        class="h-3 bg-gray-300 rounded w-24 dark:bg-gray-600"
                                    ></div>
                                </div>
                            </div>
                            <div class="h-8 w-20 bg-gray-300 rounded-lg dark:bg-gray-600"></div>
                        </div>
                    </div>
                </div>

                <div v-else-if="currentNotifications.length > 0" class="space-y-2">
                    <NotificationItem
                        v-for="notification in currentNotifications"
                        :key="notification.id"
                        :notification="notification"
                        :current-user-id="authStore?.user?.id"
                        @mark-as-read="(id) => markAsRead(id, activeTab)"
                    />

                    <div class="flex justify-center py-8">
                        <button
                            v-if="currentHasMore && !isCurrentTabLoading"
                            @click="loadMore(activeTab)"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 cursor-pointer"
                        >
                            {{ t('common.loadMore') }}
                        </button>

                        <div
                            v-else-if="isCurrentTabLoading"
                            class="flex items-center text-gray-500 dark:text-gray-400"
                        >
                            <ArrowPathIcon class="w-5 h-5 animate-spin" />
                        </div>

                        <p
                            v-else-if="!currentHasMore"
                            class="text-gray-500 dark:text-gray-400 text-sm"
                        >
                            {{ t('notifications.allCaughtUp') }}
                        </p>
                    </div>
                </div>

                <div v-else class="text-center py-12">
                    <UserPlusIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ t('notifications.noNewFollowers') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('notifications.newFollowersWillAppearHere') }}
                    </p>
                </div>
            </div>

            <div v-else-if="activeTab === 'system'">
                <div
                    v-if="isCurrentTabLoading && currentNotifications.length === 0"
                    class="space-y-4"
                >
                    <div v-for="i in 3" :key="i" class="animate-pulse">
                        <div
                            class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700"
                        >
                            <div class="flex items-start space-x-3">
                                <div
                                    class="w-10 h-10 bg-gray-300 rounded-lg dark:bg-gray-600"
                                ></div>
                                <div class="flex-1 space-y-2">
                                    <div
                                        class="h-4 bg-gray-300 rounded w-3/4 dark:bg-gray-600"
                                    ></div>
                                    <div
                                        class="h-3 bg-gray-300 rounded w-full dark:bg-gray-600"
                                    ></div>
                                    <div
                                        class="h-3 bg-gray-300 rounded w-1/2 dark:bg-gray-600"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="currentNotifications.length > 0" class="space-y-2">
                    <NotificationSystemItem
                        v-for="notification in currentNotifications"
                        :key="notification.id"
                        :notification="notification"
                        @mark-as-read="(id) => markAsRead(id, activeTab)"
                    />

                    <div class="flex justify-center py-8">
                        <button
                            v-if="currentHasMore && !isCurrentTabLoading"
                            @click="loadMore(activeTab)"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 cursor-pointer"
                        >
                            {{ t('common.loadMore') }}
                        </button>

                        <div
                            v-else-if="isCurrentTabLoading"
                            class="flex items-center text-gray-500 dark:text-gray-400"
                        >
                            <ArrowPathIcon class="w-5 h-5 animate-spin" />
                        </div>

                        <p
                            v-else-if="!currentHasMore"
                            class="text-gray-500 dark:text-gray-400 text-sm"
                        >
                            {{ t('notifications.allCaughtUp') }}
                        </p>
                    </div>
                </div>

                <div v-else class="text-center py-12">
                    <BellAlertIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ t('notifications.noSystemNotifications') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('notifications.systemNotificationsWillAppearHere') }}
                    </p>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { inject, onMounted, computed, ref, watch, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotificationStore } from '~/stores/notifications'
import {
    BellIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon,
    UserPlusIcon,
    BellAlertIcon,
    ChevronDownIcon,
    CheckIcon
} from '@heroicons/vue/24/outline'
import MainLayout from '~/layouts/MainLayout.vue'
import NotificationItem from '~/components/NotificationItem.vue'
import NotificationSystemItem from '~/components/Notification/NotificationSystemItem.vue'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useUtils } from '@/composables/useUtils'
import { useI18n } from 'vue-i18n'

const authStore = inject('authStore')
const notificationStore = useNotificationStore()
const { alertModal, confirmModal } = useAlertModal()
const { formatNumber, formatCount } = useUtils()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const validTabs = ['activity', 'followers', 'system']

const getInitialTab = () => {
    const tabParam = route.query.tab
    return validTabs.includes(tabParam) ? tabParam : 'activity'
}

const activeTab = ref(getInitialTab())
const showFilterDropdown = ref(false)
const dropdownRef = ref(null)

const activityFilters = [
    { value: 'all', label: 'All' },
    { value: 'videoLike', label: 'Video Likes' },
    { value: 'videoShare', label: 'Video Shares' },
    { value: 'comments', label: 'Comments' },
    { value: 'commentLike', label: 'Comment Likes' },
    { value: 'commentShare', label: 'Comment Shares' }
]

const activityFilter = computed({
    get: () => notificationStore.activityFilter,
    set: (value) => notificationStore.setActivityFilter(value)
})

const activeFilterLabel = computed(() => {
    const filter = activityFilters.find((f) => f.value === activityFilter.value)
    return filter ? filter.label : 'All'
})

const activityNotifications = computed(() => notificationStore.activityNotifications)
const followersNotifications = computed(() => notificationStore.followersNotifications)
const systemNotifications = computed(() => notificationStore.systemNotifications)

const activityLoading = computed(() => notificationStore.activityLoading)
const followersLoading = computed(() => notificationStore.followersLoading)
const systemLoading = computed(() => notificationStore.systemLoading)

const activityHasMore = computed(() => notificationStore.activityHasMore)
const followersHasMore = computed(() => notificationStore.followersHasMore)
const systemHasMore = computed(() => notificationStore.systemHasMore)

const activityError = computed(() => notificationStore.activityError)
const followersError = computed(() => notificationStore.followersError)
const systemError = computed(() => notificationStore.systemError)

const activityUnreadCount = computed(() => notificationStore.unreadCounts['activity'])
const followersUnreadCount = computed(() => notificationStore.unreadCounts['followers'])
const systemUnreadCount = computed(() => notificationStore.unreadCounts['system'])
const totalUnreadCount = computed(() => notificationStore.totalUnreadCount)

const { fetchNotifications, loadMore, refresh, markAsRead, markAllAsRead } = notificationStore

const currentNotifications = computed(() => {
    switch (activeTab.value) {
        case 'activity':
            return activityNotifications.value || []
        case 'followers':
            return followersNotifications.value || []
        case 'system':
            return systemNotifications.value || []
        default:
            return []
    }
})

const isCurrentTabLoading = computed(() => {
    switch (activeTab.value) {
        case 'activity':
            return activityLoading.value
        case 'followers':
            return followersLoading.value
        case 'system':
            return systemLoading.value
        default:
            return false
    }
})

const currentHasMore = computed(() => {
    switch (activeTab.value) {
        case 'activity':
            return activityHasMore.value
        case 'followers':
            return followersHasMore.value
        case 'system':
            return systemHasMore.value
        default:
            return false
    }
})

const currentError = computed(() => {
    switch (activeTab.value) {
        case 'activity':
            return activityError.value
        case 'followers':
            return followersError.value
        case 'system':
            return systemError.value
        default:
            return null
    }
})

const currentGroupedNotifications = computed(() => {
    if (activeTab.value === 'activity' && notificationStore.groupedNotifications) {
        return notificationStore.groupedNotifications(activeTab.value)
    }
    return {}
})

const showMarkAllRead = computed(() => {
    switch (activeTab.value) {
        case 'activity':
            return activityUnreadCount.value > 0
        case 'followers':
            return followersUnreadCount.value > 0
        case 'system':
            return systemUnreadCount.value > 0
        default:
            return false
    }
})

const tabs = computed(() => [
    {
        id: 'activity',
        label: t('notifications.activity'),
        unreadCount: activityUnreadCount.value
    },
    {
        id: 'followers',
        label: t('notifications.newFollowers'),
        unreadCount: followersUnreadCount.value
    },
    {
        id: 'system',
        label: t('notifications.system'),
        unreadCount: systemUnreadCount.value
    }
])

const formatDate = (dateString) => {
    const date = new Date(dateString)
    const today = new Date()
    const yesterday = new Date(today)
    yesterday.setDate(yesterday.getDate() - 1)

    if (date.toDateString() === today.toDateString()) {
        return t('common.today')
    } else if (date.toDateString() === yesterday.toDateString()) {
        return t('common.yesterday')
    } else {
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        })
    }
}

const toggleFilterDropdown = () => {
    showFilterDropdown.value = !showFilterDropdown.value
}

const selectFilter = async (filterValue) => {
    activityFilter.value = filterValue
    showFilterDropdown.value = false
    await refresh('activity')
}

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        showFilterDropdown.value = false
    }
}

const handleMarkAllRead = async () => {
    const result = await confirmModal(
        t('common.markAsRead'),
        t('common.markAllAsReadConfirmMessage'),
        t('common.markAllRead'),
        t('common.cancel')
    )

    if (result) {
        await markAllAsRead(activeTab.value)
        await fetchNotifications(activeTab.value)
    }
}

const handleRefresh = async () => {
    await refresh(activeTab.value)
}

watch(activeTab, async (newTab) => {
    if (route.query.tab !== newTab) {
        await router.replace({
            query: { ...route.query, tab: newTab }
        })
    }

    const notifications = currentNotifications.value
    if (notifications.length === 0 && !isCurrentTabLoading.value) {
        await fetchNotifications(newTab)
    }
})

watch(
    () => route.query.tab,
    (newTabParam) => {
        if (newTabParam && validTabs.includes(newTabParam) && activeTab.value !== newTabParam) {
            activeTab.value = newTabParam
        }
    }
)

onMounted(async () => {
    await fetchNotifications(activeTab.value)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
