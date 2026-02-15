import { defineStore } from 'pinia'
import { ref, computed, inject } from 'vue'

export const useNotificationStore = defineStore('notifications', () => {
    const notifications = ref({
        activity: [],
        followers: [],
        system: []
    })

    const unreadCount = ref(0)
    const hasFetchedUnreadCount = ref(false)

    const unreadCounts = ref({
        activity: 0,
        followers: 0,
        system: 0
    })

    const loading = ref({
        activity: false,
        followers: false,
        system: false
    })

    const hasMore = ref({
        activity: true,
        followers: true,
        system: true
    })

    const nextCursor = ref({
        activity: null,
        followers: null,
        system: null
    })

    const error = ref({
        activity: null,
        followers: null,
        system: null
    })

    const activityFilter = ref('all')

    const axios = inject('axios')

    const setActivityFilter = (filter) => {
        activityFilter.value = filter
    }

    const fetchNotifications = async (type = 'activity', cursor = null) => {
        if (loading.value[type]) return

        loading.value[type] = true
        error.value[type] = null

        try {
            const params = { type }

            if (type === 'activity' && activityFilter.value !== 'all') {
                params.type = activityFilter.value
            }

            if (cursor) {
                params.cursor = cursor
            } else {
                await fetchUnreadCount()
            }

            const response = await axios.get('/api/v1/account/notifications', { params })

            if (response.status !== 200) {
                throw new Error(`Failed to fetch notifications: ${response.status}`)
            }

            const data = response.data

            if (!data.data || !Array.isArray(data.data)) {
                throw new Error('Invalid response format')
            }

            if (cursor) {
                notifications.value[type] = [...notifications.value[type], ...data.data]
            } else {
                notifications.value[type] = [...data.data]
                if (data.meta.unread_counts) {
                    unreadCounts.value = data.meta.unread_counts
                }
            }

            nextCursor.value[type] = data.meta?.next_cursor || null
            hasMore.value[type] = !!data.meta?.next_cursor
        } catch (err) {
            error.value[type] = err.message
            if (!notifications.value[type]) {
                notifications.value[type] = []
            }
        } finally {
            loading.value[type] = false
        }
    }

    const loadMore = async (type = 'activity') => {
        if (hasMore.value[type] && nextCursor.value[type]) {
            await fetchNotifications(type, nextCursor.value[type])
        }
    }

    const refresh = async (type = 'activity') => {
        nextCursor.value[type] = null
        hasMore.value[type] = true
        hasFetchedUnreadCount.value = false
        await fetchNotifications(type)
    }

    const markAsRead = async (notificationId, type = 'activity') => {
        try {
            await axios.post(`/api/v1/account/notifications/${notificationId}/read`)

            const notification = notifications.value[type]?.find((n) => n.id === notificationId)
            if (notification) {
                notification.read_at = new Date().toISOString()
            }
            if (unreadCount.value[type] > 0) {
                unreadCount.value[type]--
            }
        } catch (err) {
            console.error('Error marking notification as read:', err)
        }
    }

    const markAllAsRead = async (type = 'activity') => {
        try {
            const params = { type: type }

            if (type === 'activity' && activityFilter.value !== 'all') {
                params.type = activityFilter.value
            }

            await axios.post('/api/v1/account/notifications/mark-all-read', params)
            unreadCounts.value[type] = 0
            unreadCount.value = 0
            hasFetchedUnreadCount.value = false
        } catch (err) {
            console.error('Error marking all notifications as read:', err)
        }
    }

    const fetchUnreadCount = async () => {
        if (hasFetchedUnreadCount.value) {
            return
        }
        try {
            const res = await axios.get('/api/v1/account/notifications/count')
            if (res.data.data.unread_count !== undefined) {
                unreadCount.value = res.data.data.unread_count
            }
            hasFetchedUnreadCount.value = true
        } catch (err) {
            console.error('Error fetching unread count:', err)
        }
    }

    const groupedNotifications = computed(() => {
        return (type = 'activity') => {
            const groups = {}

            if (!notifications.value[type] || !Array.isArray(notifications.value[type])) {
                return groups
            }

            notifications.value[type].forEach((notification) => {
                const date = new Date(notification.created_at).toDateString()
                if (!groups[date]) {
                    groups[date] = []
                }
                groups[date].push(notification)
            })

            return groups
        }
    })

    const activityNotifications = computed(() => notifications.value.activity)
    const followersNotifications = computed(() => notifications.value.followers)
    const systemNotifications = computed(() => notifications.value.system)

    const activityLoading = computed(() => loading.value.activity)
    const followersLoading = computed(() => loading.value.followers)
    const systemLoading = computed(() => loading.value.system)

    const activityHasMore = computed(() => hasMore.value.activity)
    const followersHasMore = computed(() => hasMore.value.followers)
    const systemHasMore = computed(() => hasMore.value.system)

    const activityError = computed(() => error.value.activity)
    const followersError = computed(() => error.value.followers)
    const systemError = computed(() => error.value.system)

    const activityUnreadCount = computed(() => unreadCount.value.activity)
    const followersUnreadCount = computed(() => unreadCount.value.followers)
    const systemUnreadCount = computed(() => unreadCount.value.system)

    const totalUnreadCount = computed(() => {
        return unreadCount.value
    })

    return {
        notifications,
        loading,
        hasMore,
        error,
        unreadCount,
        unreadCounts,
        activityFilter,

        groupedNotifications,
        activityNotifications,
        followersNotifications,
        systemNotifications,
        activityLoading,
        followersLoading,
        systemLoading,
        activityHasMore,
        followersHasMore,
        systemHasMore,
        activityError,
        followersError,
        systemError,
        activityUnreadCount,
        followersUnreadCount,
        systemUnreadCount,
        totalUnreadCount,

        fetchNotifications,
        loadMore,
        refresh,
        markAsRead,
        markAllAsRead,
        fetchUnreadCount,
        setActivityFilter
    }
})
