<template>
    <LoadingSpinner v-if="isLoading || !profile" />

    <div v-else class="max-w-8xl mx-auto p-6 space-y-6">
        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
        >
            <div class="bg-gradient-to-r from-blue-500 to-sky-400 h-32"></div>
            <div class="relative px-6 pb-6">
                <div
                    class="flex flex-col sm:flex-row sm:items-end sm:space-x-6"
                >
                    <div class="relative -mt-18 mb-4 sm:mb-0">
                        <img
                            :src="profile.avatar"
                            :alt="profile.username"
                            class="w-32 h-32 rounded-full border-4 border-white dark:border-gray-800 bg-white shadow-lg"
                            @error="
                                $event.target.src =
                                    '/storage/avatars/default.jpg'
                            "
                        />
                    </div>

                    <div class="flex-1">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-2"
                        >
                            <div>
                                <h1
                                    class="text-2xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{ profile.name || profile.username }}
                                </h1>
                                <router-link
                                    :to="`/@${profile.username}`"
                                    class="text-gray-600 dark:text-gray-400 font-light hover:text-gray-900 hover:dark:text-gray-500"
                                >
                                    @{{ profile.username }}
                                </router-link>
                                <div class="flex items-center mt-2 space-x-4">
                                    <span
                                        v-if="profile.is_admin"
                                        class="text-sm text-red-600 dark:text-red-400 font-bold"
                                    >
                                        Admin
                                    </span>
                                    <span
                                        :class="[
                                            'px-3 py-2 text-xs font-bold rounded-full uppercase',
                                            profile?.status === 'active'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : profile?.status ===
                                                    'suspended'
                                                  ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                  : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                                        ]"
                                    >
                                        {{ profile?.status }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="flex items-center space-x-3 mt-4 sm:mt-0"
                            >
                                <button
                                    @click="toggleVerification"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
                                        profile.verified
                                            ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                            : 'bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-300 dark:hover:bg-blue-800',
                                    ]"
                                >
                                    {{
                                        profile?.email_verified
                                            ? "Remove Email Verification"
                                            : "Verify Email"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <router-link
                :to="`/admin/videos?q=${profile.username}`"
                class="cursor-pointer"
            >
                <div class="card-info">
                    <div class="card-info-value">
                        {{ formatNumber(profile.post_count) }}
                    </div>
                    <div class="card-info-label">Videos</div>
                </div>
            </router-link>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(profile.follower_count) }}
                </div>
                <div class="card-info-label">Followers</div>
            </div>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(profile.following_count) }}
                </div>
                <div class="card-info-label">Following</div>
            </div>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(profile.likes_count) }}
                </div>
                <div class="card-info-label">Total Likes</div>
            </div>
            <router-link
                :to="`/admin/comments?q=user:${profile.username}`"
                class="cursor-pointer"
            >
                <div class="card-info">
                    <div class="card-info-value">
                        {{ formatNumber(profile.comments_count) }}
                    </div>
                    <div class="card-info-label">Comments</div>
                </div>
            </router-link>
            <div class="card-info">
                <div class="card-info-value">
                    {{ formatNumber(profile.comment_replies_count) }}
                </div>
                <div class="card-info-label">Comment Replies</div>
            </div>
            <router-link
                :to="`/admin/reports?q=reported_by:${profile.id}`"
                class="cursor-pointer"
            >
                <div class="card-info">
                    <div class="card-info-value">
                        {{ formatNumber(profile.reports_created_count) }}
                    </div>
                    <div class="card-info-label">Reports Created</div>
                </div>
            </router-link>
            <router-link
                :to="`/admin/reports?q=reported_profile_id:${profile.id}`"
                class="cursor-pointer"
            >
                <div class="card-info">
                    <div class="card-info-value">
                        {{ formatNumber(profile.reported_count) }}
                    </div>
                    <div class="card-info-label">Total Reported</div>
                </div>
            </router-link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                >
                    Account Information
                </h3>
                <div class="space-y-4">
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                            >Email</label
                        >
                        <div class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ profile.email }}
                        </div>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                            >Joined</label
                        >
                        <div class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ formatDate(profile.created_at) }}
                        </div>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 dark:text-gray-400"
                            >Bio</label
                        >
                        <div class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ profile.bio || "No bio set" }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                >
                    Permissions & Restrictions
                </h3>
                <div class="space-y-6">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Comment Permissions
                                </h4>
                                <p
                                    class="text-xs text-gray-600 dark:text-gray-400"
                                >
                                    Control user's ability to comment on videos
                                </p>
                            </div>
                            <button
                                @click="toggleCommentPermission"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    profile.can_comment
                                        ? 'bg-blue-600'
                                        : 'bg-gray-200 dark:bg-gray-600',
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        profile.can_comment
                                            ? 'translate-x-5'
                                            : 'translate-x-0',
                                    ]"
                                ></span>
                            </button>
                        </div>
                        <div
                            v-if="!profile.can_comment"
                            class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded"
                        >
                            Comments are restricted for this user
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Video Upload Permissions
                                </h4>
                                <p
                                    class="text-xs text-gray-600 dark:text-gray-400"
                                >
                                    Control user's ability to upload videos
                                </p>
                            </div>
                            <button
                                @click="toggleVideoPermission"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    profile.can_upload
                                        ? 'bg-blue-600'
                                        : 'bg-gray-200 dark:bg-gray-600',
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        profile.can_upload
                                            ? 'translate-x-5'
                                            : 'translate-x-0',
                                    ]"
                                ></span>
                            </button>
                        </div>
                        <div
                            v-if="!profile.can_upload"
                            class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded"
                        >
                            Video uploads are restricted for this user
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Follow Permissions
                                </h4>
                                <p
                                    class="text-xs text-gray-600 dark:text-gray-400"
                                >
                                    Control user's ability to follow accounts
                                </p>
                            </div>
                            <button
                                @click="toggleFollowPermission"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    profile.can_follow
                                        ? 'bg-blue-600'
                                        : 'bg-gray-200 dark:bg-gray-600',
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        profile.can_follow
                                            ? 'translate-x-5'
                                            : 'translate-x-0',
                                    ]"
                                ></span>
                            </button>
                        </div>
                        <div
                            v-if="!profile.can_follow"
                            class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded"
                        >
                            Following is restricted for this user
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Like Permissions
                                </h4>
                                <p
                                    class="text-xs text-gray-600 dark:text-gray-400"
                                >
                                    Control user's ability to like content
                                </p>
                            </div>
                            <button
                                @click="toggleLikePermission"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    profile.can_like
                                        ? 'bg-blue-600'
                                        : 'bg-gray-200 dark:bg-gray-600',
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        profile.can_like
                                            ? 'translate-x-5'
                                            : 'translate-x-0',
                                    ]"
                                ></span>
                            </button>
                        </div>
                        <div
                            v-if="!profile.can_like"
                            class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded"
                        >
                            Likes are restricted for this user
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-900 dark:text-white mb-2"
                            >Admin Notes</label
                        >
                        <textarea
                            v-model="adminNotes"
                            @blur="saveAdminNotes"
                            rows="3"
                            placeholder="Add notes about this user..."
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        ></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6"
        >
            <h3
                class="text-lg font-semibold text-red-900 dark:text-red-200 mb-2"
            >
                Danger Zone
            </h3>
            <p class="text-sm text-red-700 dark:text-red-300 mb-4">
                Once you delete this account, there is no going back. This will
                permanently delete all user data, videos, and associated
                content.
            </p>
            <button
                @click="showDeleteConfirmation = true"
                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors"
            >
                Delete Account
            </button>
        </div>

        <div
            v-if="showDeleteConfirmation"
            class="fixed inset-0 overflow-y-auto"
        >
            <div
                class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="-z-1 fixed inset-0 transition-opacity bg-gray-500/40"
                    @click="showDeleteConfirmation = false"
                ></div>

                <div
                    class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-lg"
                >
                    <div
                        class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 dark:bg-red-900/20 rounded-full"
                    >
                        <svg
                            class="w-6 h-6 text-red-600 dark:text-red-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                            ></path>
                        </svg>
                    </div>

                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white text-center mb-2"
                    >
                        Delete Account
                    </h3>

                    <p
                        class="text-sm text-gray-600 dark:text-gray-400 text-center mb-6"
                    >
                        Are you sure you want to delete
                        <strong>@{{ profile.username }}</strong
                        >? This action cannot be undone and will permanently
                        remove all associated data.
                    </p>

                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Type "DELETE" to confirm:
                        </label>
                        <input
                            v-model="deleteConfirmationText"
                            type="text"
                            placeholder="DELETE"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-transparent"
                        />
                    </div>

                    <div class="flex space-x-3">
                        <button
                            @click="showDeleteConfirmation = false"
                            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="deleteAccount"
                            :disabled="
                                deleteConfirmationText !== 'DELETE' ||
                                isDeleting
                            "
                            :class="[
                                'flex-1 px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors',
                                deleteConfirmationText === 'DELETE' &&
                                !isDeleting
                                    ? 'bg-red-600 hover:bg-red-700'
                                    : 'bg-gray-400 cursor-not-allowed',
                            ]"
                        >
                            {{ isDeleting ? "Deleting..." : "Delete Account" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { profilesApi } from "@/services/adminApi";
import { useUtils } from "@/composables/useUtils";
const { formatNumber, formatDate } = useUtils();

const router = useRouter();
const route = useRoute();
const profile = ref(false);
const adminNotes = ref();
const isLoading = ref(false);

const fetchProfile = async (id) => {
    isLoading.value = true;
    try {
        const response = await profilesApi.getProfile(id);
        profile.value = response.data;
        adminNotes.value = response.data?.admin_note;
    } catch (error) {
        console.error("Error fetching profiles:", error);
    } finally {
        isLoading.value = false;
    }
};
const showDeleteConfirmation = ref(false);
const deleteConfirmationText = ref("");
const isDeleting = ref(false);

const toggleVerification = async () => {
    try {
        profile.value.email_verified = !profile.value.email_verified;
        console.log(
            `Verification ${profile.value.email_verified ? "added" : "removed"} for user ${profile.value.username}`,
        );
    } catch (error) {
        console.error("Error updating verification:", error);
    }
};

const toggleCommentPermission = async () => {
    try {
        await profilesApi.updateProfilePermissions(profile.value.id, {
            can_comment: !profile.value.can_comment,
        });
        profile.value.can_comment = !profile.value.can_comment;
    } catch (error) {
        console.error("Error updating permission:", error);
    }
};

const toggleVideoPermission = async () => {
    try {
        await profilesApi.updateProfilePermissions(profile.value.id, {
            can_upload: !profile.value.can_upload,
        });
        profile.value.can_upload = !profile.value.can_upload;
    } catch (error) {
        console.error("Error updating permission:", error);
    }
};

const toggleFollowPermission = async () => {
    try {
        await profilesApi.updateProfilePermissions(profile.value.id, {
            can_follow: !profile.value.can_follow,
        });
        profile.value.can_follow = !profile.value.can_follow;
    } catch (error) {
        console.error("Error updating permission:", error);
    }
};

const toggleLikePermission = async () => {
    try {
        await profilesApi.updateProfilePermissions(profile.value.id, {
            can_like: !profile.value.can_like,
        });
        profile.value.can_like = !profile.value.can_like;
    } catch (error) {
        console.error("Error updating permission:", error);
    }
};

const saveAdminNotes = async () => {
    try {
        await profilesApi.updateProfileNotes(profile.value.id, {
            admin_note: adminNotes.value,
        });
        profile.value.admin_note = adminNotes.value;
        console.log("Admin notes saved for", profile.value.username);
    } catch (error) {
        console.error("Error saving admin notes:", error);
    }
};

const deleteAccount = async () => {
    if (deleteConfirmationText.value !== "DELETE" || isDeleting.value) return;

    isDeleting.value = true;

    try {
        // API call would go here
        await new Promise((resolve) => setTimeout(resolve, 2000)); // Simulate API delay

        console.log(`Account ${profile.value.username} deleted successfully`);
        showDeleteConfirmation.value = false;
    } catch (error) {
        console.error("Error deleting account:", error);
    } finally {
        isDeleting.value = false;
        deleteConfirmationText.value = "";
    }
};

onMounted(async () => {
    await fetchProfile(route.params.id);
});

watch(
    () => route.params.id,
    (newId) => {
        if (newId) {
            fetchProfile(newId);
        }
    },
);
</script>

<style scoped>
@reference "../../../sass/next.css";

.card-info {
    @apply bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6;
}
.card-info-value {
    @apply text-2xl font-bold text-gray-900 dark:text-white;
}
.card-info-label {
    @apply text-sm text-gray-600 dark:text-gray-400;
}
</style>
