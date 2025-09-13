<template>
    <MainLayout>
        <div class="max-w-2xl mx-auto px-5">
            <div
                class="flex flex-col lg:flex-row items-center justify-between mb-6 mt-3"
            >
                <div class="flex items-center">
                    <h1
                        class="text-2xl font-semibold tracking-tight dark:text-gray-100"
                    >
                        {{ $t("common.notifications") }}
                    </h1>
                    <span
                        v-if="unreadCount > 0"
                        class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"
                    >
                        {{ formatNumber(unreadCount) }}
                        {{ $t("common.unread") }}
                    </span>
                </div>

                <div class="hidden lg:flex items-center space-x-3">
                    <button
                        v-if="unreadCount"
                        class="text-xs font-bold bg-[#F02C56] border border-[#F02C56] text-white rounded-lg px-5 py-2 hover:bg-[#F02C56]/90 hover:border-[#F02C5699] cursor-pointer"
                        @click="markAllRead"
                    >
                        {{ $t("common.markAllRead") }}
                    </button>

                    <button
                        @click="refresh"
                        :disabled="loading && safeNotifications.length === 0"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                    >
                        <ArrowPathIcon
                            class="w-4 h-4"
                            :class="{
                                'animate-spin':
                                    loading && safeNotifications.length === 0,
                            }"
                        />
                    </button>
                </div>
            </div>

            <hr class="border-gray-300 dark:border-gray-700 mb-6" />

            <div
                v-if="error"
                class="p-4 mb-6 bg-red-50 border border-red-200 rounded-md dark:bg-red-900/20 dark:border-red-800"
            >
                <div class="flex">
                    <ExclamationTriangleIcon class="h-5 w-5 text-red-400" />
                    <div class="ml-3">
                        <h3
                            class="text-sm font-medium text-red-800 dark:text-red-200"
                        >
                            {{ t("notifications.errorLoadingNotifications") }}
                        </h3>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                            {{ error }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="loading && safeNotifications.length === 0"
                class="space-y-4"
            >
                <div v-for="i in 5" :key="i" class="animate-pulse">
                    <div
                        class="flex items-start space-x-3 p-4 bg-white rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700"
                    >
                        <div
                            class="w-10 h-10 bg-gray-300 rounded-full dark:bg-gray-600"
                        ></div>
                        <div class="flex-1 space-y-2">
                            <div
                                class="h-4 bg-gray-300 rounded w-3/4 dark:bg-gray-600"
                            ></div>
                            <div
                                class="h-3 bg-gray-300 rounded w-1/2 dark:bg-gray-600"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="safeNotifications.length > 0" class="space-y-6">
                <div
                    v-for="(groupNotifications, date) in groupedNotifications"
                    :key="date"
                >
                    <h3
                        class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3 sticky top-17 bg-gray-50 dark:bg-gray-900 py-2 z-1 p-3 rounded"
                    >
                        {{ formatDate(date) }} ({{ groupNotifications.length }})
                    </h3>

                    <div class="space-y-2">
                        <NotificationItem
                            v-for="notification in groupNotifications"
                            :key="notification.id"
                            :notification="notification"
                            @mark-as-read="markAsRead"
                        />
                    </div>
                </div>

                <div class="flex justify-center py-8">
                    <button
                        v-if="hasMore && !loading"
                        @click="loadMore"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 cursor-pointer"
                    >
                        {{ t("common.loadMore") }}
                    </button>

                    <div
                        v-else-if="loading"
                        class="flex items-center text-gray-500 dark:text-gray-400"
                    >
                        <Spinner />
                    </div>

                    <p
                        v-else-if="!hasMore"
                        class="text-gray-500 dark:text-gray-400 text-sm"
                    >
                        {{ t("notifications.allCaughtUp") }}
                    </p>
                </div>
            </div>

            <div v-else class="text-center py-12">
                <BellIcon class="mx-auto h-12 w-12 text-gray-400" />
                <h3
                    class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ t("notifications.noNotifications") }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t("notifications.allCaughtUp") }}
                </p>
            </div>
        </div>
    </MainLayout>
</template>

<script setup lang="ts">
import { inject, onMounted, watch, computed } from "vue";
import { useNotificationStore } from "~/stores/notifications";
import {
    BellIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon,
} from "@heroicons/vue/24/outline";
import MainLayout from "~/layouts/MainLayout.vue";
import NotificationItem from "~/components/NotificationItem.vue";
import { useAlertModal } from "@/composables/useAlertModal.js";
import { useUtils } from "@/composables/useUtils";
import { useI18n } from "vue-i18n";

const authStore = inject("authStore");

const notificationStore = useNotificationStore();
const { alertModal, confirmModal } = useAlertModal();
const { formatNumber, formatCount } = useUtils();
const { t } = useI18n();

const notifications = computed(() => notificationStore.notifications);
const loading = computed(() => notificationStore.loading);
const hasMore = computed(() => notificationStore.hasMore);
const error = computed(() => notificationStore.error);
const unreadCount = computed(() => notificationStore.unreadCount);
const groupedNotifications = computed(
    () => notificationStore.groupedNotifications,
);

const { fetchNotifications, loadMore, refresh, markAsRead, markAllAsRead } =
    notificationStore;

const safeNotifications = computed(() => {
    return notifications.value || [];
});

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return t("common.today");
    } else if (date.toDateString() === yesterday.toDateString()) {
        return t("common.yesterday");
    } else {
        return date.toLocaleDateString("en-US", {
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "numeric",
        });
    }
};

const markAllRead = async () => {
    const result = await confirmModal(
        t("common.markAsRead"),
        t("common.markAllAsReadConfirmMessage"),
        t("common.markAllRead"),
        t("common.cancel"),
    );

    if (result) {
        await markAllAsRead().finally(async () => {
            await fetchNotifications();
        });
    }
};

onMounted(async () => {
    await fetchNotifications();
});
</script>
