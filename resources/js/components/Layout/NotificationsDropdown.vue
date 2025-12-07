<template>
    <div class="relative">
        <slot
            name="trigger"
            :unread-count="unreadCount"
            :is-open="isOpen"
            :toggle="() => (isOpen = !isOpen)"
        />

        <Teleport to="body">
            <div v-if="isOpen" class="fixed inset-0 bg-black/30 z-10" @click="isOpen = false" />

            <div
                v-if="isOpen"
                class="fixed right-4 top-16 w-[400px] max-h-[500px] overflow-y-auto bg-white dark:bg-slate-900 rounded-lg shadow-xl border border-gray-200 dark:border-slate-800 z-30"
            >
                <div
                    class="fixed w-[400px] p-4 mb-3 border-b border-gray-200 dark:border-slate-800 shadow dark:shadow-slate-900 rounded-lg z-30 backdrop-blur-lg bg-white/60 dark:bg-slate-950/60"
                >
                    <div class="flex justify-between">
                        <h3 class="text-lg font-semibold dark:text-slate-500">
                            {{ t('common.notifications') }}
                        </h3>

                        <button
                            v-if="unreadCount"
                            class="text-xs font-bold bg-[#F02C56] border border-[#F02C56] text-white rounded-lg px-5 py-2 hover:bg-[#F02C56]/90 hover:border-[#F02C5699] cursor-pointer"
                            @click="markAllRead"
                        >
                            {{ $t('common.markAllRead') }}
                        </button>
                    </div>
                </div>

                <div class="relative top-[60px]">
                    <div v-if="isLoading" class="p-4 text-center">
                        <div
                            class="animate-spin rounded-full h-8 w-8 border-4 border-gray-300 border-t-blue-600 mx-auto"
                        ></div>
                    </div>

                    <div v-else-if="isError" class="p-4 text-center text-red-600">
                        {{ t('notifications.errorLoadingNotifications') }}
                    </div>

                    <div v-else>
                        <div class="divide-y divide-gray-200">
                            <div v-for="page in data?.pages" :key="page.meta.next_cursor">
                                <div
                                    v-for="(notification, idx) in page.data"
                                    :key="notification.id"
                                    class="p-4 hover:bg-gray-50 dark:hover:bg-slate-700"
                                    :class="{
                                        'bg-gray-100 dark:bg-slate-800': idx % 2
                                    }"
                                >
                                    <div class="flex items-start gap-3">
                                        <button @click="gotoProfile(notification)">
                                            <img
                                                :src="notification.actor.avatar"
                                                :alt="notification.actor.name"
                                                class="w-10 h-10 rounded-full"
                                                @error="
                                                    $event.target.src =
                                                        '/storage/avatars/default.jpg'
                                                "
                                            />
                                        </button>
                                        <div>
                                            <button @click="gotoProfile(notification)">
                                                <p
                                                    class="text-sm tracking-tight font-medium dark:text-slate-200"
                                                >
                                                    {{
                                                        truncateMiddle(
                                                            notification.actor.username,
                                                            30
                                                        )
                                                    }}
                                                </p>
                                            </button>
                                            <div class="flex items-center gap-1">
                                                <span
                                                    class="text-sm tracking-tight text-gray-600 dark:text-slate-400"
                                                >
                                                    {{ getNotificationText(notification) }}
                                                </span>
                                            </div>
                                            <div v-if="notification.video_thumbnail" class="mt-2">
                                                <router-link
                                                    v-if="notification.url"
                                                    :to="notification.url"
                                                >
                                                    <img
                                                        :src="notification.video_thumbnail"
                                                        :alt="'Video thumbnail'"
                                                        class="w-16 h-16 rounded object-cover shadow-xl"
                                                        @error="
                                                            $event.target.src =
                                                                '/storage/videos/video-placeholder.jpg'
                                                        "
                                                    />
                                                </router-link>
                                                <LoopLink v-else :id="notification.video_id">
                                                    <img
                                                        :src="notification.video_thumbnail"
                                                        :alt="'Video thumbnail'"
                                                        class="w-16 h-16 rounded object-cover shadow-xl"
                                                        @error="
                                                            $event.target.src =
                                                                '/storage/videos/video-placeholder.jpg'
                                                        "
                                                    />
                                                </LoopLink>
                                            </div>
                                            <span class="text-xs tracking-tight text-gray-500">
                                                {{ formatDate(notification.created_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="hasNextPage" class="p-4 text-center">
                            <button
                                @click="fetchNextPage"
                                :disabled="isFetchingNextPage"
                                class="text-red-600 hover:text-red-700 disabled:opacity-50"
                            >
                                <span v-if="isFetchingNextPage">
                                    <LoadingSpinner />
                                </span>
                                <span v-else>{{ t('common.loadMore') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useNotificationStore } from '~/stores/notifications'
import { useNotifications } from '~/composables/useNotifications'
import { format } from 'date-fns'
import { useRouter } from 'vue-router'
import LoopLink from '../LoopLink.vue'
import { useI18n } from 'vue-i18n'
import { useAlertModal } from '@/composables/useAlertModal.js'
const { t } = useI18n()
import { useUtils } from '@/composables/useUtils'

const isOpen = ref(false)
const notificationStore = useNotificationStore()
const router = useRouter()
const { truncateMiddle, formatNumber, formatDate } = useUtils()
const { alertModal, confirmModal } = useAlertModal()

const { data, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading, isError } =
    useNotifications()

const { fetchNotifications, loadMore, refresh, markAllAsRead } = notificationStore
const unreadCount = computed(() => notificationStore.unreadCount)

const gotoProfile = (notification) => {
    isOpen.value = false
    router.push(`/@${notification.actor.username}`)
}

const markAllRead = async () => {
    const result = await confirmModal(
        t('common.markAsRead'),
        t('common.markAllAsReadConfirmMessage'),
        t('common.markAllRead'),
        t('common.cancel')
    )

    if (result) {
        await markAllAsRead().finally(async () => {
            await fetchNotifications()
        })
    }
}

const getNotificationText = (notification) => {
    switch (notification.type) {
        case 'video.like':
            return t('notifications.messageTypes.videoLike')
        case 'new_follower':
            return t('notifications.messageTypes.newFollower')
        case 'video.comment':
            return t('notifications.messageTypes.videoComment')
        case 'video.share':
            return t('notifications.messageTypes.videoShare')
        case 'video.commentReply':
            return t('notifications.messageTypes.videoCommentReply')
        case 'video.duet':
            return 'dueted your video'
        case 'comment.like':
            return t('notifications.messageTypes.videoCommentLike')
        case 'commentReply.like':
            return t('notifications.messageTypes.videoCommentReplyLike')
        default:
            return t('notifications.messageTypes.default')
    }
}
</script>
