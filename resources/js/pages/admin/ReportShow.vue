<template>
    <div class="max-w-8xl mx-auto p-6 space-y-6">
        <div v-if="loading" class="space-y-6">
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <div class="animate-pulse space-y-4">
                    <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                    <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                </div>
            </div>
        </div>

        <div v-else-if="report" class="space-y-6">
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                Report #{{ report.id }}
                            </h1>
                            <span
                                :class="[
                                    'px-3 py-1 text-sm font-medium rounded-full uppercase',
                                    report.status === 'resolved'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : report.status === 'pending'
                                          ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                          : report.status === 'dismissed'
                                            ? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                ]"
                            >
                                {{ report.status }}
                            </span>
                            <span
                                class="px-3 py-1 text-sm font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 rounded-full"
                            >
                                {{ report.reason.replace('_', ' ') }}
                            </span>
                        </div>

                        <p class="text-gray-600 dark:text-gray-400 mb-0">
                            Reported {{ formatDate(report.created_at) }}
                        </p>
                    </div>

                    <div v-if="report.status === 'pending'" class="flex flex-col gap-3">
                        <button
                            @click="dismissReport"
                            class="px-4 py-2 bg-gray-100 text-black text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors cursor-pointer"
                        >
                            Dismiss Report
                        </button>
                        <button
                            @click="dismissAllReportsByAccount"
                            class="px-4 py-2 bg-gray-100 text-black text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors cursor-pointer truncate"
                        >
                            Dismiss all by {{ report.reporter.username }}
                        </button>
                        <button
                            v-if="report.content_type === 'video'"
                            @click="markAsNsfw"
                            class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors cursor-pointer"
                        >
                            Mark as NSFW
                        </button>
                        <button
                            v-if="report.content_type === 'video'"
                            @click="deleteVideo"
                            class="px-4 py-2 text-red-600 border border-red-600 text-sm font-medium rounded-lg hover:bg-red-500 hover:text-white transition-colors cursor-pointer"
                        >
                            Delete Video
                        </button>

                        <button
                            v-if="report.content_type === 'comment'"
                            @click="deleteComment"
                            class="px-4 py-2 text-red-600 border border-red-600 text-sm font-medium rounded-lg hover:bg-red-500 hover:text-white transition-colors cursor-pointer"
                        >
                            Delete Comment
                        </button>

                        <button
                            v-if="report.content_type === 'reply'"
                            @click="deleteCommentReply"
                            class="px-4 py-2 text-red-600 border border-red-600 text-sm font-medium rounded-lg hover:bg-red-500 hover:text-white transition-colors cursor-pointer"
                        >
                            Delete Comment Reply
                        </button>

                        <button
                            v-if="report.content_type === 'hashtag'"
                            @click="dismissAndManageHashtag"
                            class="px-4 py-2 text-red-600 border border-red-600 text-sm font-medium rounded-lg hover:bg-red-500 hover:text-white transition-colors cursor-pointer"
                        >
                            Dismiss & Manage Hashtag
                        </button>

                        <button
                            v-if="report.content_type === 'hashtag'"
                            @click="manageHashtag"
                            class="px-4 py-2 text-red-600 border border-red-600 text-sm font-medium rounded-lg hover:bg-red-500 hover:text-white transition-colors cursor-pointer"
                        >
                            Manage Hashtag
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 flex flex-col"
                >
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Reporter Information
                    </h3>

                    <div class="flex-1 flex flex-col mb-3">
                        <router-link
                            :to="`/admin/profiles/${report.reporter.id}`"
                            class="flex items-center gap-4 cursor-pointer"
                        >
                            <img
                                :src="report.reporter.avatar"
                                :alt="report.reporter.username"
                                class="w-12 h-12 rounded-full border-2 border-gray-200 dark:border-gray-600 flex-shrink-0"
                                @error="$event.target.src = '/storage/avatars/default.jpg'"
                            />

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1">
                                    <h4 class="font-medium text-gray-900 dark:text-white">
                                        {{ report.reporter.name || report.reporter.username }}
                                    </h4>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 break-words">
                                    @{{ report.reporter.username }}
                                </p>
                            </div>
                        </router-link>
                    </div>

                    <div v-if="report.user_message" class="flex-1 flex flex-col gap-4 my-3">
                        <p class="text-sm italic dark:text-gray-500">Message from Reporter:</p>
                        <div
                            class="border border-gray-200 bg-gray-100 dark:bg-gray-900 dark:border-gray-700 p-4 rounded text-left tracking-tight text-gray-700 dark:text-gray-300"
                        >
                            <p>{{ report.user_message }}</p>
                        </div>
                    </div>

                    <div class="mt-auto pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="grid grid-cols-4 gap-4 text-center">
                            <div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ report.reporter.follower_count || 0 }}
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    Followers
                                </div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ report.reporter.post_count || 0 }}
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Videos</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ report.reporter.following_count || 0 }}
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    Following
                                </div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ formatDate(report.reporter.created_at) }}
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Joined</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 flex flex-col"
                >
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Reported Content
                    </h3>

                    <div class="flex-1 flex flex-col mb-3">
                        <div class="flex items-center gap-2 mb-4">
                            <span
                                class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full"
                            >
                                {{ report.content_type }}
                            </span>
                            <span class="text-sm text-gray-600 dark:text-gray-400"
                                >ID: {{ report.content_preview?.id }}</span
                            >
                        </div>
                    </div>
                    <div class="mt-auto pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div v-if="report.content_type === 'video'" class="space-y-3">
                            <router-link
                                :to="`/admin/videos/${report.content_preview.id}`"
                                class="cursor-pointer flex gap-3 items-center"
                            >
                                <img
                                    :src="report.content_preview.media.thumbnail"
                                    class="w-20 h-15 rounded object-cover flex-shrink-0"
                                    @error="
                                        $event.target.src = '/storage/videos/video-placeholder.jpg'
                                    "
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white truncate">
                                        {{ report.content_preview.caption }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        by @{{ report.content_preview.account.username }}
                                    </p>
                                </div>
                            </router-link>
                        </div>

                        <div
                            v-else-if="report.content_type === 'comment' && report.content_preview"
                            class="space-y-3"
                        >
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-900 dark:text-white">
                                    {{ report.content_preview?.caption }}
                                </p>
                                <div
                                    class="flex items-center gap-2 mt-2 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <router-link
                                        :to="`/@${report.content_preview.account.username}`"
                                        >by @{{
                                            report.content_preview.account.username
                                        }}</router-link
                                    >
                                    <span>•</span>
                                    <router-link :to="report.content_preview.url">{{
                                        formatDate(report.content_preview.created_at)
                                    }}</router-link>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="report.content_type === 'reply' && report.content_preview"
                            class="space-y-4"
                        >
                            <div v-if="report.content_preview?.parent" class="relative">
                                <div class="flex items-center justify-center mb-3">
                                    <div
                                        class="bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-full text-xs font-medium border border-blue-200 dark:border-blue-800"
                                    >
                                        Parent Comment
                                    </div>
                                </div>

                                <div
                                    class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-950 rounded-xl p-5 border border-blue-200 dark:border-blue-600/50 shadow-sm"
                                >
                                    <p
                                        class="text-gray-900 dark:text-gray-100 leading-relaxed mb-3"
                                    >
                                        {{ report.content_preview?.parent?.caption }}
                                    </p>

                                    <div class="flex items-center justify-between">
                                        <div
                                            class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-300"
                                        >
                                            <router-link
                                                :to="`/@${report.content_preview.parent.account.username}`"
                                                class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200"
                                            >
                                                @{{
                                                    report.content_preview.parent.account.username
                                                }}
                                            </router-link>
                                            <span class="text-gray-400 dark:text-gray-500">•</span>
                                            <router-link
                                                :to="report.content_preview.parent.url"
                                                class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200"
                                            >
                                                {{
                                                    formatDate(
                                                        report.content_preview.parent.created_at
                                                    )
                                                }}
                                            </router-link>
                                        </div>

                                        <div
                                            class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            <div
                                                class="flex items-center gap-1"
                                                v-if="report.content_preview.parent.likes"
                                            >
                                                <i class="bx bx-heart" />
                                                <span>{{
                                                    report.content_preview.parent.likes
                                                }}</span>
                                            </div>
                                            <div
                                                class="flex items-center gap-1"
                                                v-if="report.content_preview.parent.replies"
                                            >
                                                <i class="bx bx-reply" />
                                                <span>{{
                                                    report.content_preview.parent.replies
                                                }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-center my-2">
                                    <div
                                        class="w-px h-6 bg-gradient-to-b from-blue-400 to-red-400 dark:from-blue-500 dark:to-red-500"
                                    ></div>
                                </div>
                            </div>
                            <div v-else class="relative">
                                <div class="flex items-center justify-center mb-3">
                                    <div
                                        class="bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-full text-xs font-medium border border-blue-200 dark:border-blue-800"
                                    >
                                        Parent Comment
                                    </div>
                                </div>

                                <div
                                    class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-950 rounded-xl p-5 border border-blue-200 dark:border-blue-600/50 shadow-sm"
                                >
                                    <p
                                        class="text-sm text-center italic text-gray-600 dark:text-gray-400 leading-relaxed"
                                    >
                                        Deleted
                                    </p>
                                </div>
                            </div>

                            <div class="relative">
                                <div class="flex items-center justify-center mb-3">
                                    <div
                                        class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 px-3 py-1 rounded-full text-xs font-medium border border-red-200 dark:border-red-800"
                                    >
                                        Reply (Reported Content)
                                    </div>
                                </div>

                                <div
                                    class="bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 rounded-xl p-5 border border-red-400 dark:border-red-500 shadow-sm"
                                >
                                    <p
                                        class="text-lg text-gray-900 dark:text-gray-100 leading-relaxed mb-3"
                                    >
                                        {{ report.content_preview?.caption }}
                                    </p>

                                    <div class="flex items-center justify-between">
                                        <div
                                            class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-300"
                                        >
                                            <router-link
                                                :to="`/@${report.content_preview.account.username}`"
                                                class="font-medium hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200"
                                            >
                                                @{{ report.content_preview.account.username }}
                                            </router-link>
                                            <span class="text-gray-400 dark:text-gray-500">•</span>
                                            <router-link
                                                :to="report.content_preview.url"
                                                class="hover:text-red-600 dark:hover:text-red-400 transition-colors duration-200"
                                            >
                                                {{ formatDate(report.content_preview.created_at) }}
                                            </router-link>
                                        </div>

                                        <div
                                            class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            <div
                                                class="flex items-center gap-1"
                                                v-if="report.content_preview.likes"
                                            >
                                                <i class="bx bx-heart" />
                                                <span>{{ report.content_preview.likes }}</span>
                                            </div>
                                            <div
                                                class="flex items-center gap-1"
                                                v-if="report.content_preview.replies"
                                            >
                                                <i class="bx bx-reply" />
                                                <span>{{ report.content_preview.replies }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="report.content_type === 'profile'" class="space-y-3">
                            <router-link
                                :to="`/admin/profiles/${report.content_preview.id}`"
                                class="cursor-pointer flex items-center gap-3"
                            >
                                <img
                                    :src="report.content_preview.avatar"
                                    :alt="report.content_preview.username"
                                    class="w-12 h-12 rounded-full flex-shrink-0"
                                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                                />
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 dark:text-white">
                                        {{
                                            report.content_preview.name ||
                                            report.content_preview.username
                                        }}
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        @{{ report.content_preview.username }}
                                    </p>
                                    <p class="mt-2 text-sm text-gray-500">
                                        {{ report.content_preview.bio || 'No bio set' }}
                                    </p>
                                </div>
                            </router-link>
                            <div class="mt-auto pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="grid grid-cols-4 gap-4 text-center">
                                    <div>
                                        <div
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ report.content_preview.follower_count || 0 }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            Followers
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ report.content_preview.post_count || 0 }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            Videos
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ report.content_preview.following_count || 0 }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            Following
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ formatDate(report.content_preview.created_at) }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            Joined
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="report.content_type === 'hashtag' && report.content_preview"
                            class="space-y-3"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center flex-shrink-0"
                                >
                                    <span class="text-white text-xl font-bold">#</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 dark:text-white text-lg">
                                        #{{ report.content_preview.name }}
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ report.content_preview.count || 0 }}
                                        posts
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-if="report.content_preview.is_banned"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400"
                                >
                                    Banned
                                </span>
                                <span
                                    v-if="report.content_preview.is_nsfw"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400"
                                >
                                    NSFW
                                </span>
                                <span
                                    v-if="report.content_preview.can_trend"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    Can Trend
                                </span>
                                <span
                                    v-if="report.content_preview.can_search"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
                                >
                                    Searchable
                                </span>
                                <span
                                    v-if="report.content_preview.can_autolink"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400"
                                >
                                    Auto-linkable
                                </span>
                            </div>

                            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ report.content_preview.count || 0 }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            Total Posts
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ formatDate(report.content_preview.created_at) }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            Created
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Admin Actions
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2"
                            >Admin Notes</label
                        >
                        <textarea
                            v-model="adminNotes"
                            @blur="saveAdminNotes"
                            rows="4"
                            :maxlength="1500"
                            placeholder="Add notes about this report, your investigation, or actions taken..."
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        ></textarea>
                        <p class="text-sm text-gray-400">{{ adminNotes?.length ?? 0 }}/1500</p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center"
        >
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Report Not Found</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                The report you're looking for doesn't exist or has been removed.
            </p>
            <router-link
                to="/admin/reports"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
            >
                Back to Reports
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { reportsApi } from '@/services/adminApi'
import { useRoute, useRouter } from 'vue-router'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useUtils } from '@/composables/useUtils'
const { formatDate, formatNumber } = useUtils()
import { useAdminStore } from '~/stores/admin'

const { alertModal, confirmModal } = useAlertModal()

const adminStore = useAdminStore()

const route = useRoute()
const router = useRouter()

// Reactive data
const loading = ref(true)
const report = ref(null)
const adminNotes = ref('')

const fetchReport = async (id) => {
    loading.value = true

    try {
        const response = await reportsApi.getReport(id)
        report.value = response.data
        adminNotes.value = response.data.admin_notes
    } catch (error) {
        console.error('Error fetching report:', error)
        report.value = null
    } finally {
        loading.value = false
    }
}

const dismissAllReportsByAccount = async () => {
    const result = await confirmModal(
        'Dismiss All Reports',
        `Are you sure you want to dismiss all reports made by <strong>${report.value.reporter?.username}</strong>?`,
        'Dismiss All',
        'Cancel'
    )

    if (result) {
        const response = await reportsApi
            .dismissAllReportsByAccount(report?.value.id)
            .finally(async () => {
                await adminStore.fetchReportsCount()
                router.push('/admin/reports')
            })
    } else {
        loading.value = false
    }
}

const dismissReport = async () => {
    const result = await confirmModal(
        'Dismiss Report',
        `Are you sure you want to dismiss this report?`,
        'Mark as Resolved',
        'Cancel'
    )

    if (result) {
        const response = await reportsApi.dismissReport(report?.value.id).finally(async () => {
            await adminStore.fetchReportsCount()
            router.push('/admin/reports')
        })
    } else {
        loading.value = false
    }
}

const dismissAndManageHashtag = async () => {
    const result = await confirmModal(
        'Dismiss Report',
        `Are you sure you want to dismiss this hashtag report?`,
        'Mark as Resolved',
        'Cancel'
    )

    if (result) {
        const response = await reportsApi.dismissReport(report?.value.id).finally(async () => {
            await adminStore.fetchReportsCount()

            router.push(
                `/admin/hashtags?q=${report?.value.content_preview?.name}&id=${report?.value.content_preview?.id}`
            )
        })
    } else {
        loading.value = false
    }
}

const manageHashtag = async () => {
    router.push(
        `/admin/hashtags?q=${report?.value.content_preview?.name}&id=${report?.value.content_preview?.id}`
    )
}

const deleteVideo = async () => {
    const result = await confirmModal(
        'Confirm Delete',
        `Are you sure you want to delete this video by <span class="font-bold">${report.value.content_preview?.account?.username}</span>? This action cannot be undone.`,
        'Delete Video',
        'Cancel'
    )

    if (result) {
        const id = report.value.id
        const response = await reportsApi.deleteVideo(id).finally(async () => {
            await adminStore.fetchReportsCount()
            router.push('/admin/reports')
        })
    }
}

const deleteComment = async () => {
    const result = await confirmModal(
        'Confirm Delete',
        `Are you sure you want to delete this comment by ${report.value.content_preview?.account?.username}? This action cannot be undone.`,
        'Delete Comment',
        'Cancel'
    )

    if (result) {
        const id = report.value.id
        const response = await reportsApi.deleteComment(id).finally(async () => {
            await adminStore.fetchReportsCount()
            router.push('/admin/reports')
        })
    }
}

const deleteCommentReply = async () => {
    const result = await confirmModal(
        'Confirm Delete',
        `Are you sure you want to delete this comment by ${report.value.content_preview?.account?.username}? This action cannot be undone.`,
        'Delete Comment',
        'Cancel'
    )

    if (result) {
        const id = report.value.id
        const response = await reportsApi.deleteCommentReply(id).finally(async () => {
            await adminStore.fetchReportsCount()
            router.push('/admin/reports')
        })
    }
}

const markAsNsfw = async () => {
    const result = await confirmModal(
        'Mark as NSFW',
        `Are you sure you want to mark this post as NSFW? This action cannot be undone.`,
        'Mark as NSFW',
        'Cancel'
    )

    if (result) {
        const id = report.value.id
        const response = await reportsApi.updateReportMarkAsNsfw(id).finally(async () => {
            await adminStore.fetchReportsCount()
            router.push('/admin/reports')
        })
    }
}

const resolveReport = () => updateStatus('resolved')

const saveAdminNotes = async () => {
    try {
        const response = await reportsApi.updateReportNotes(report.value.id, {
            content: adminNotes.value
        })
        report.value.admin_notes = adminNotes.value
        console.log('Admin notes saved for report', report.value.id)
    } catch (error) {
        console.error('Error saving admin notes:', error)
    }
}

const viewContent = () => {
    // Navigate to the original content
    console.log(`Viewing ${report.value.content_type} ${report.value.content_id}`)
}

const moderateContent = () => {
    // Open content moderation interface
    console.log(`Moderating ${report.value.content_type} ${report.value.content_id}`)
}

onMounted(async () => {
    await fetchReport(route.params.id)
})

// Watch for route parameter changes
watch(
    () => route.params.id,
    (newId) => {
        if (newId) {
            fetchReport(newId)
        }
    }
)
</script>
