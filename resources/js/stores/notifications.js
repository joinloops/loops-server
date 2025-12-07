import { defineStore } from 'pinia'
import { ref, computed, inject } from 'vue'

export const useNotificationStore = defineStore('notifications', () => {
    const notifications = ref([])
    const unreadCount = ref(0)
    const hasFetchedUnreadCount = ref(false)
    const loading = ref(false)
    const hasMore = ref(true)
    const nextCursor = ref(null)
    const error = ref(null)
    const axios = inject('axios')

    const fetchNotifications = async (cursor = null) => {
        if (loading.value) return

        loading.value = true
        error.value = null

        try {
            const params = {}
            if (cursor) {
                params.cursor = cursor
            } else {
                await fetchUnreadCount()
            }

            const response = await axios.get('/api/v1/account/notifications', {
                params
            })

            if (response.status !== 200) {
                throw new Error(`Failed to fetch notifications: ${response.status}`)
            }

            const data = response.data

            if (!data.data || !Array.isArray(data.data)) {
                throw new Error('Invalid response format')
            }

            if (cursor) {
                const newNotifications = [...notifications.value, ...data.data]
                notifications.value = newNotifications
            } else {
                notifications.value = [...data.data]
            }

            nextCursor.value = data.meta?.next_cursor || null
            hasMore.value = !!data.meta?.next_cursor
        } catch (err) {
            error.value = err.message
            if (!notifications.value) {
                notifications.value = []
            }
        } finally {
            loading.value = false
        }
    }

    const loadMore = async () => {
        if (hasMore.value && nextCursor.value) {
            await fetchNotifications(nextCursor.value)
        }
    }

    const refresh = async () => {
        nextCursor.value = null
        hasMore.value = true
        await fetchNotifications()
    }

    const markAsRead = async (notificationId) => {
        try {
            await axios.post(`/api/v1/account/notifications/${notificationId}/read`)

            const notification = notifications.value?.find((n) => n.id === notificationId)
            if (notification) {
                notification.read_at = new Date().toISOString()
            }
            unreadCount.value--
        } catch (err) {
            console.error('Error marking notification as read:', err)
        }
    }

    const markAllAsRead = async (notificationId) => {
        try {
            await axios.post(`/api/v1/account/notifications/mark-all-read`).then((res) => {
                unreadCount.value = 0
                hasFetchedUnreadCount.value = false
            })
        } catch (err) {
            console.error('Error marking notification as read:', err)
        }
    }

    const fetchUnreadCount = async () => {
        if (hasFetchedUnreadCount.value) {
            return
        }
        try {
            await axios
                .get(`/api/v1/account/notifications/count`)
                .then((res) => {
                    if (res.data.data.unread_count) {
                        unreadCount.value = res.data.data.unread_count
                    }
                })
                .finally(() => {
                    hasFetchedUnreadCount.value = true
                })
        } catch (err) {
            console.error('Error marking notification as read:', err)
        }
    }

    const groupedNotifications = computed(() => {
        const groups = {}

        if (!notifications.value || !Array.isArray(notifications.value)) {
            return groups
        }

        notifications.value.forEach((notification) => {
            const date = new Date(notification.created_at).toDateString()
            if (!groups[date]) {
                groups[date] = []
            }
            groups[date].push(notification)
        })

        return groups
    })

    return {
        notifications,
        loading,
        hasMore,
        error,
        unreadCount,
        fetchUnreadCount,
        groupedNotifications,
        fetchNotifications,
        loadMore,
        refresh,
        markAsRead,
        markAllAsRead
    }
})
