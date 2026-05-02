<template>
    <div
        :data-notification-item="notification.id"
        @click="handleNotificationClick"
        class="group relative flex items-center gap-3 py-3 px-4 transition-all cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5"
        :class="[
            !notification.read_at
                ? 'bg-blue-50/40 dark:bg-blue-900/10'
                : 'bg-white dark:bg-gray-900'
        ]"
    >
        <div
            v-if="!notification.read_at"
            class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"
            aria-hidden="true"
        ></div>

        <router-link
            :to="`/@${notification.actor.username}`"
            @click.stop="markAsRead"
            class="relative flex-shrink-0 group/avatar"
        >
            <img
                :src="notification.actor.avatar"
                :alt="notification.actor.name"
                class="w-10 h-10 rounded-full object-cover ring-2 ring-white dark:ring-gray-900 transition-transform group-hover/avatar:scale-105"
                @error="handleImageError"
            />
            <div
                class="absolute -bottom-1 -right-1 flex items-center justify-center w-5 h-5 rounded-full ring-2 ring-white dark:ring-gray-900"
                :class="getIconBackgroundClass()"
                aria-hidden="true"
            >
                <component
                    :is="getNotificationIcon()"
                    class="w-3 h-3"
                    :class="getIconColorClass()"
                />
            </div>
        </router-link>

        <div class="flex-1 min-w-0 pr-2">
            <p class="text-sm leading-snug text-gray-900 dark:text-gray-100">
                <span
                    ref="nameRef"
                    @mouseenter="handleNameHover"
                    @mouseleave="handleNameLeave"
                    class="hover:underline cursor-pointer"
                    :class="!notification.read_at ? 'font-bold' : 'font-semibold'"
                    @click.stop="navigateToProfile"
                >
                    {{
                        notification.actor.local
                            ? notification.actor.name || notification.actor.username
                            : notification.actor.username
                    }}
                </span>
                <span
                    class="text-gray-600 dark:text-gray-400 font-normal"
                    v-html="' ' + getNotificationMessage()"
                ></span>
                <span
                    v-if="isStarterKitNotification && notification.kit?.title"
                    ref="kitTitleRef"
                    class="font-bold text-red-500 dark:text-red-400 cursor-pointer ml-0.5"
                    :aria-describedby="`starter-kit-hover-card-${notification.kit.id}`"
                    @mouseenter="handleKitHover"
                    @mouseleave="handleKitLeave"
                    @click.stop="handleNotificationClick"
                >
                    {{ notification.kit.title }}
                </span>
                <span v-if="isStarterKitNotification && notification.kit?.title">{{
                    ' ' + $t('common.starterKit')
                }}</span>
                <span class="text-gray-400 text-xs whitespace-nowrap ml-1.5">
                    {{ formatTimeAgo(notification.created_at) }}
                </span>
            </p>
        </div>

        <div class="flex-shrink-0">
            <div
                v-if="
                    [
                        'video.like',
                        'video.comment',
                        'video.share',
                        'video.commentReply',
                        'comment.share',
                        'commentReply.share'
                    ].includes(notification.type) && notification.video_thumbnail
                "
            >
                <img
                    :src="notification.video_thumbnail"
                    alt="Thumbnail"
                    class="w-6 h-10 sm:w-8 sm:h-12 rounded-md object-cover border border-gray-200 dark:border-gray-700"
                    @error="handleVideoImageError"
                />
            </div>

            <div
                v-else-if="
                    notification.type === 'starterKit.awaitingApproval' && notification.kit?.path
                "
            >
                <AnimatedButton
                    size="xs"
                    :aria-label="`Review Starter Kit: ${notification.kit?.title}`"
                    @click="handleNotificationClick"
                >
                    Review
                </AnimatedButton>
            </div>

            <div
                v-else-if="
                    [
                        'starterKit.accountApproved',
                        'starterKit.accountRejected',
                        'starterKit.removedFromKit'
                    ].includes(notification.type) && notification.kit?.path
                "
            >
                <AnimatedButton
                    size="xs"
                    :aria-label="`View Starter Kit: ${notification.kit?.title}`"
                    @click="handleNotificationClick()"
                >
                    View Kit
                </AnimatedButton>
            </div>

            <div
                v-else-if="
                    ['new_follower', 'starterKit.newMember'].includes(notification.type) &&
                    notification.kit?.path
                "
            >
                <AnimatedButton
                    size="xs"
                    :aria-label="`View Starter Kit: ${notification.kit?.title}`"
                    @click="handleNotificationClick()"
                >
                    View Kit
                </AnimatedButton>
            </div>

            <div
                v-else-if="!notification.read_at"
                class="w-2 h-2 bg-blue-500 rounded-full"
                aria-hidden="true"
            ></div>
        </div>

        <Teleport to="body">
            <UserHoverCard
                v-if="!isMobile && showHoverCard"
                :show="showHoverCard"
                :account="notification.actor"
                :account-id="notification.actor.id"
                :position="hoverCardPosition"
                :current-user-id="currentUserId"
                @mouseenter="handleCardEnter"
                @mouseleave="handleCardLeave"
            />
        </Teleport>

        <Teleport to="body">
            <StarterKitHoverCard
                v-if="!isMobile && isStarterKitNotification && notification.kit?.id"
                :show="showKitCard"
                :kit="notification.kit"
                :position="kitCardPosition"
                @mouseenter="handleKitCardEnter"
                @mouseleave="handleKitCardLeave"
            />
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
    HeartIcon,
    ArrowPathRoundedSquareIcon,
    UserPlusIcon,
    ChatBubbleLeftIcon,
    ArrowPathIcon,
    BellIcon
} from '@heroicons/vue/24/solid'
import { useHashids } from '@/composables/useHashids'
import { useI18n } from 'vue-i18n'
import UserHoverCard from '@/components/ProfileHoverCard.vue'
import StarterKitHoverCard from '@/components/Notification/NotificationStarterKitHoverCard.vue'
import AnimatedButton from './../AnimatedButton.vue'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    notification: {
        type: Object,
        required: true
    },
    currentUserId: {
        type: Number,
        default: null
    }
})

const emit = defineEmits(['mark-as-read'])
const router = useRouter()
const { encodeHashid } = useHashids()
const { t } = useI18n()

const isMobile = ref(false)

const showHoverCard = ref(false)
const hoverCardPosition = ref({ top: '0px', left: '0px' })
const hoverTimeout = ref(null)
const isOverCard = ref(false)
const nameRef = ref(null)

const showKitCard = ref(false)
const kitCardPosition = ref({ top: '0px', left: '0px' })
const kitHoverTimeout = ref(null)
const isOverKitCard = ref(false)
const kitTitleRef = ref(null)

const STARTER_KIT_TYPES = [
    'starterKit.awaitingApproval',
    'starterKit.accountApproved',
    'starterKit.accountRejected',
    'starterKit.removedFromKit',
    'starterKit.newMember'
]

const isStarterKitNotification = computed(() => STARTER_KIT_TYPES.includes(props.notification.type))

const newFollowerMessage = computed(() =>
    props.notification.kit?.path
        ? t('notifications.messageTypes.newFollower') + ' from a Starter Kit'
        : t('notifications.messageTypes.newFollower')
)

const newKitMemberAdded = computed(
    () =>
        `added <strong class="text-black dark:text-white">@${props.notification.new_member?.username}</strong> to the `
)

const notificationConfig = {
    'video.like': {
        message: t('notifications.messageTypes.videoLike'),
        icon: HeartIcon,
        iconColor: 'text-white',
        bgColor: 'bg-red-500'
    },
    'video.share': {
        message: t('notifications.messageTypes.videoShare'),
        icon: ArrowPathRoundedSquareIcon,
        iconColor: 'text-white',
        bgColor: 'bg-red-500'
    },
    new_follower: {
        message: newFollowerMessage.value,
        icon: UserPlusIcon,
        iconColor: 'text-white',
        bgColor: 'bg-blue-500'
    },
    'video.commentReply': {
        message: t('notifications.messageTypes.videoCommentReply'),
        icon: ChatBubbleLeftIcon,
        iconColor: 'text-white',
        bgColor: 'bg-green-500'
    },
    'video.comment': {
        message: t('notifications.messageTypes.videoComment'),
        icon: ChatBubbleLeftIcon,
        iconColor: 'text-white',
        bgColor: 'bg-green-500'
    },
    'comment.like': {
        message: t('notifications.messageTypes.videoCommentLike'),
        icon: HeartIcon,
        iconColor: 'text-white',
        bgColor: 'bg-red-500'
    },
    'comment.share': {
        message: t('notifications.messageTypes.videoCommentShare'),
        icon: ArrowPathRoundedSquareIcon,
        iconColor: 'text-white',
        bgColor: 'bg-green-500'
    },
    'commentReply.like': {
        message: t('notifications.messageTypes.videoCommentReplyLike'),
        icon: HeartIcon,
        iconColor: 'text-white',
        bgColor: 'bg-red-500'
    },
    'commentReply.share': {
        message: t('notifications.messageTypes.videoCommentReplyShare'),
        icon: ArrowPathRoundedSquareIcon,
        iconColor: 'text-white',
        bgColor: 'bg-green-500'
    },
    'video.duet': {
        message: 'dueted your video',
        icon: ArrowPathIcon,
        iconColor: 'text-white',
        bgColor: 'bg-purple-500'
    },
    'starterKit.awaitingApproval': {
        message: 'wants to add you to the ',
        icon: UserPlusIcon,
        iconColor: 'text-white',
        bgColor: 'bg-red-500'
    },
    'starterKit.accountApproved': {
        message: 'accepted to be included in the ',
        icon: PlusIcon,
        iconColor: 'text-white',
        bgColor: 'bg-green-500'
    },
    'starterKit.newMember': {
        message: newKitMemberAdded.value,
        icon: PlusIcon,
        iconColor: 'text-white',
        bgColor: 'bg-green-500'
    },
    'starterKit.accountRejected': {
        message: 'declined to be included in the ',
        icon: XMarkIcon,
        iconColor: 'text-white',
        bgColor: 'bg-red-500'
    },
    'starterKit.removedFromKit': {
        message: 'removed you from the ',
        icon: XMarkIcon,
        iconColor: 'text-white',
        bgColor: 'bg-red-500'
    }
}

const checkMobile = () => {
    isMobile.value =
        'ontouchstart' in window || navigator.maxTouchPoints > 0 || window.innerWidth < 768
}

onMounted(() => {
    checkMobile()
    window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    clearTimeout(hoverTimeout.value)
    clearTimeout(kitHoverTimeout.value)
})

const calculatePosition = () => {
    if (!nameRef.value) return { top: '0px', left: '0px' }

    const rect = nameRef.value.getBoundingClientRect()
    const cardWidth = 288
    const cardHeight = 220
    const spacing = 12
    const margin = 16

    let left = rect.left + rect.width / 2 - cardWidth / 2
    left = Math.max(margin, Math.min(left, window.innerWidth - cardWidth - margin))

    let top = rect.top - cardHeight - spacing
    if (top < margin) top = rect.bottom + spacing

    return { top: `${top}px`, left: `${left}px` }
}

const handleNameHover = () => {
    if (isMobile.value) return
    clearTimeout(hoverTimeout.value)
    hoverTimeout.value = setTimeout(() => {
        hoverCardPosition.value = calculatePosition()
        showHoverCard.value = true
    }, 500)
}

const handleNameLeave = () => {
    clearTimeout(hoverTimeout.value)
    hoverTimeout.value = setTimeout(() => {
        if (!isOverCard.value) showHoverCard.value = false
    }, 200)
}

const handleCardEnter = () => {
    clearTimeout(hoverTimeout.value)
    isOverCard.value = true
}

const handleCardLeave = () => {
    isOverCard.value = false
    showHoverCard.value = false
}

const calculateKitCardPosition = () => {
    const anchor = kitTitleRef.value
    if (!anchor) return { top: '0px', left: '0px' }

    const rect = anchor.getBoundingClientRect()
    const cardWidth = 288
    const cardHeight = 280
    const spacing = 8
    const margin = 16

    let left = rect.left
    left = Math.max(margin, Math.min(left, window.innerWidth - cardWidth - margin))

    let top = rect.top - cardHeight - spacing
    if (top < margin) top = rect.bottom + spacing

    return { top: `${top}px`, left: `${left}px` }
}

const handleKitHover = () => {
    if (isMobile.value || !props.notification.kit?.id) return
    clearTimeout(kitHoverTimeout.value)
    kitHoverTimeout.value = setTimeout(() => {
        kitCardPosition.value = calculateKitCardPosition()
        showKitCard.value = true
    }, 400)
}

const handleKitLeave = () => {
    clearTimeout(kitHoverTimeout.value)
    kitHoverTimeout.value = setTimeout(() => {
        if (!isOverKitCard.value) showKitCard.value = false
    }, 200)
}

const handleKitCardEnter = () => {
    clearTimeout(kitHoverTimeout.value)
    isOverKitCard.value = true
}

const handleKitCardLeave = () => {
    isOverKitCard.value = false
    showKitCard.value = false
}

const navigateToProfile = () => {
    markAsRead()
    router.push(`/@${props.notification.actor.username}`)
}

const getNotificationMessage = () => {
    const config = notificationConfig[props.notification.type]
    return config?.message ?? t('notifications.messageTypes.default')
}

const getNotificationIcon = () => {
    return notificationConfig[props.notification.type]?.icon ?? BellIcon
}

const getIconColorClass = () => {
    return notificationConfig[props.notification.type]?.iconColor ?? 'text-white'
}

const getIconBackgroundClass = () => {
    return notificationConfig[props.notification.type]?.bgColor ?? 'bg-gray-400'
}

const markAsRead = () => {
    if (!props.notification.read_at) {
        emit('mark-as-read', props.notification.id)
    }
}

const handleNotificationClick = () => {
    markAsRead()

    try {
        if (
            [
                'video.comment',
                'video.commentReply',
                'commentReply.like',
                'comment.like',
                'comment.share',
                'commentReply.share'
            ].includes(props.notification.type)
        ) {
            router.push(`${props.notification.url}`)
        } else if (
            ['video.like', 'video.share'].includes(props.notification.type) &&
            props.notification.video_id
        ) {
            const vid = encodeHashid(props.notification.video_id)
            router.push(`/v/${vid}`)
        } else if (props.notification.type === 'new_follower') {
            if (props.notification.kit?.path) {
                router.push(props.notification.kit.path)
            } else {
                router.push(`/@${props.notification.actor.username}`)
            }
        } else if (
            props.notification.type === 'starterKit.awaitingApproval' &&
            props.notification.kit?.path
        ) {
            router.push(`${props.notification.kit.path}/review`)
        } else if (
            [
                'starterKit.newMember',
                'starterKit.accountApproved',
                'starterKit.accountRejected',
                'starterKit.removedFromKit'
            ].includes(props.notification.type) &&
            props.notification.kit?.path
        ) {
            router.push(`${props.notification.kit.path}`)
        }
    } catch (error) {
        console.error('Navigation error:', error)
    }
}

const formatTimeAgo = (dateString) => {
    const date = new Date(dateString)
    const now = new Date()
    const diffInSeconds = Math.floor((now - date) / 1000)
    if (diffInSeconds < 60) return 'now'
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m`
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h`
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d`
    return `${date.getMonth() + 1}/${date.getDate()}`
}

const handleImageError = (event) => {
    event.target.src = '/storage/avatars/default.jpg'
}

const handleVideoImageError = (event) => {
    event.target.src = '/storage/videos/video-placeholder.jpg'
}
</script>
