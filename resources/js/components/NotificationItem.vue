<template>
    <div
        :data-notification-item="notification.id"
        @click="handleClick"
        class="group flex items-start space-x-3 p-4 rounded-lg border transition-colors cursor-pointer"
        :class="[
            notification.read_at
                ? 'bg-white border-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-750'
                : 'bg-blue-50 border-blue-200 hover:bg-blue-100 dark:bg-blue-900/20 dark:border-blue-800 dark:hover:bg-blue-900/30',
        ]"
    >
        <div class="flex-shrink-0">
            <img
                :src="notification.actor.avatar"
                :alt="notification.actor.name"
                class="w-10 h-10 rounded-full object-cover"
                @error="handleImageError"
            />
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-sm text-gray-900 dark:text-gray-100">
                        <span class="font-medium">{{
                            notification.actor.name
                        }}</span>
                        <span class="text-gray-600 dark:text-gray-400 ml-1">{{
                            getNotificationMessage()
                        }}</span>
                    </p>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        @{{ notification.actor.username }}
                    </p>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ formatTimeAgo(notification.created_at) }}
                    </p>
                </div>

                <div class="flex-shrink-0 ml-3">
                    <div
                        class="flex items-center justify-center w-8 h-8 rounded-full"
                        :class="getIconBackgroundClass()"
                    >
                        <component
                            :is="getNotificationIcon()"
                            class="w-4 h-4"
                            :class="getIconColorClass()"
                        />
                    </div>
                </div>
            </div>

            <div
                v-if="
                    notification.type === 'video.like' &&
                    notification.video_thumbnail
                "
                class="mt-3"
            >
                <img
                    :src="notification.video_thumbnail"
                    alt="Video thumbnail"
                    class="w-16 h-16 rounded-lg object-cover border border-gray-200 dark:border-gray-600"
                    @error="handleVideoImageError"
                />
            </div>
        </div>

        <div
            v-if="!notification.read_at"
            class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full"
        ></div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRouter } from "vue-router";
import {
    HeartIcon,
    UserPlusIcon,
    ChatBubbleLeftIcon,
    ArrowPathIcon,
    BellIcon,
} from "@heroicons/vue/24/solid";
import { useHashids } from "@/composables/useHashids";
import { useI18n } from "vue-i18n";

const props = defineProps({
    notification: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["mark-as-read"]);
const router = useRouter();
const { encodeHashid } = useHashids();
const { t } = useI18n();

const notificationConfig = {
    "video.like": {
        message: t("notifications.messageTypes.videoLike"),
        icon: HeartIcon,
        iconColor: "text-red-500",
        bgColor: "bg-red-100 dark:bg-red-900/30",
    },
    new_follower: {
        message: t("notifications.messageTypes.newFollower"),
        icon: UserPlusIcon,
        iconColor: "text-blue-500",
        bgColor: "bg-blue-100 dark:bg-blue-900/30",
    },
    "video.comment": {
        message: t("notifications.messageTypes.videoComment"),
        icon: ChatBubbleLeftIcon,
        iconColor: "text-green-500",
        bgColor: "bg-green-100 dark:bg-green-900/30",
    },
    "video.share": {
        message: t("notifications.messageTypes.videoShare"),
        icon: ArrowPathIcon,
        iconColor: "text-purple-500",
        bgColor: "bg-purple-100 dark:bg-purple-900/30",
    },
};

const getNotificationMessage = () => {
    const config = notificationConfig[props.notification.type];
    return config?.message || t("notifications.messageTypes.default");
};

const getNotificationIcon = () => {
    const config = notificationConfig[props.notification.type];
    return config?.icon || BellIcon;
};

const getIconColorClass = () => {
    const config = notificationConfig[props.notification.type];
    return config?.iconColor || "text-gray-500";
};

const getIconBackgroundClass = () => {
    const config = notificationConfig[props.notification.type];
    return config?.bgColor || "bg-gray-100 dark:bg-gray-800";
};

const handleClick = () => {
    if (!props.notification.read_at) {
        emit("mark-as-read", props.notification.id);
    }

    try {
        if (
            props.notification.type === "video.like" &&
            props.notification.video_id
        ) {
            const vid = encodeHashid(props.notification.video_id);
            router.push(`/v/${vid}`);
        } else if (props.notification.type === "new_follower") {
            router.push(`/@${props.notification.actor.username}`);
        }
    } catch (error) {
        console.error("Navigation error:", error);
    }
};

const formatTimeAgo = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) {
        return "just now";
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes}m ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours}h ago`;
    } else if (diffInSeconds < 604800) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days}d ago`;
    } else {
        return date.toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
            year:
                date.getFullYear() !== now.getFullYear()
                    ? "numeric"
                    : undefined,
        });
    }
};

const handleImageError = (event) => {
    event.target.src = "/storage/avatars/default.jpg";
};

const handleVideoImageError = (event) => {
    event.target.src = "/storage/videos/video-placeholder.jpg";
};
</script>

<style scoped>
.dark .dark\:bg-gray-750:hover {
    background-color: rgb(55 65 81 / 0.7);
}

.dark .dark\:hover\:bg-blue-900\/30:hover {
    background-color: rgb(30 58 138 / 0.3);
}
</style>
