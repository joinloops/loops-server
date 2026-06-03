<template>
    <div class="">
        <div v-if="loading" class="space-y-6">
            <div
                class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <div class="animate-pulse space-y-4">
                    <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded w-2/3"></div>
                    <div class="h-64 bg-gray-200 dark:bg-gray-700 rounded-xl"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                </div>
            </div>
        </div>

        <div v-else-if="video" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <router-link
                        to="/admin/videos"
                        class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </router-link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                Video Details
                            </h1>
                            <span
                                :class="statusBadgeClasses"
                                class="px-2.5 py-0.5 text-xs font-semibold rounded-full capitalize"
                            >
                                {{ video.status }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                                {{ video.hid }}
                            </span>
                            <button
                                @click="copyHid"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors cursor-pointer"
                                :title="copied ? 'Copied!' : 'Copy ID'"
                            >
                                <component
                                    :is="copied ? CheckIcon : ClipboardDocumentIcon"
                                    class="h-4 w-4"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <router-link
                        v-if="video.status === 'published'"
                        :to="`/v/${video.hid}`"
                        target="_blank"
                        class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                        View Live
                    </router-link>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                        <div class="relative bg-black aspect-video">
                            <video ref="videoEl" class="w-full h-full object-contain" controls>
                                <source :src="video.media.src_url" type="video/mp4" />
                            </video>
                        </div>
                        <div
                            v-if="
                                video.is_sensitive ||
                                video.meta.contains_ai ||
                                video.meta.contains_ad ||
                                video.pinned
                            "
                            class="flex flex-wrap gap-2 px-4 py-3 border-t border-gray-100 dark:border-gray-700"
                        >
                            <span
                                v-if="video.is_sensitive"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20"
                            >
                                <ExclamationTriangleIcon class="h-3.5 w-3.5" />
                                Sensitive Content
                            </span>
                            <span
                                v-if="video.meta.contains_ai"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20"
                            >
                                <SparklesIcon class="h-3.5 w-3.5" />
                                AI Generated
                            </span>
                            <span
                                v-if="video.meta.contains_ad"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400 border border-purple-100 dark:border-purple-500/20"
                            >
                                <MegaphoneIcon class="h-3.5 w-3.5" />
                                Sponsored
                            </span>
                            <span
                                v-if="video.pinned"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20"
                            >
                                <BookmarkIcon class="h-3.5 w-3.5" />
                                Pinned
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-gray-400 dark:text-gray-500 mb-2"
                            >
                                <HeartIcon class="h-4 w-4" />
                                <span class="text-xs font-medium uppercase tracking-wide"
                                    >Likes</span
                                >
                            </div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(video.likes) }}
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-gray-400 dark:text-gray-500 mb-2"
                            >
                                <ChatBubbleOvalLeftIcon class="h-4 w-4" />
                                <span class="text-xs font-medium uppercase tracking-wide"
                                    >Comments</span
                                >
                            </div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(video.comments) }}
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-gray-400 dark:text-gray-500 mb-2"
                            >
                                <ShareIcon class="h-4 w-4" />
                                <span class="text-xs font-medium uppercase tracking-wide"
                                    >Shares</span
                                >
                            </div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(video.shares) }}
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-gray-400 dark:text-gray-500 mb-2"
                            >
                                <BookmarkIcon class="h-4 w-4" />
                                <span class="text-xs font-medium uppercase tracking-wide"
                                    >Bookmarks</span
                                >
                            </div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(video.bookmarks) }}
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-gray-400 dark:text-gray-500 mb-2"
                            >
                                <EyeIcon class="h-4 w-4" />
                                <span class="text-xs font-medium uppercase tracking-wide"
                                    >FYP Views</span
                                >
                            </div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(video.views) }}
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-gray-400 dark:text-gray-500 mb-2"
                            >
                                <LanguageIcon class="h-4 w-4" />
                                <span class="text-xs font-medium uppercase tracking-wide"
                                    >Language</span
                                >
                            </div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ video.lang ?? '-' }}
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-gray-400 dark:text-gray-500 mb-2"
                            >
                                <ClockIcon class="h-4 w-4" />
                                <span class="text-xs font-medium uppercase tracking-wide"
                                    >Duration</span
                                >
                            </div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ formatDuration(video.media?.duration) }}
                            </div>
                        </div>
                        <component
                            :is="(video.reported_count || 0) > 0 ? 'router-link' : 'div'"
                            :to="
                                (video.reported_count || 0) > 0
                                    ? `/admin/reports?q=video_id:${video.id}`
                                    : undefined
                            "
                            class="bg-white dark:bg-gray-800 rounded-xl border p-4 transition-colors"
                            :class="
                                (video.reported_count || 0) > 0
                                    ? 'border-red-200 dark:border-red-500/30 hover:border-red-300 dark:hover:border-red-500/50 cursor-pointer'
                                    : 'border-gray-200 dark:border-gray-700'
                            "
                        >
                            <div
                                class="flex items-center gap-2 mb-2"
                                :class="
                                    (video.reported_count || 0) > 0
                                        ? 'text-red-500 dark:text-red-400'
                                        : 'text-gray-400 dark:text-gray-500'
                                "
                            >
                                <FlagIcon class="h-4 w-4" />
                                <span class="text-xs font-medium uppercase tracking-wide"
                                    >Reports</span
                                >
                            </div>
                            <div
                                class="text-2xl font-bold"
                                :class="
                                    (video.reported_count || 0) > 0
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-gray-900 dark:text-white'
                                "
                            >
                                {{ formatNumber(video.reported_count || 0) }}
                            </div>
                        </component>
                    </div>

                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3
                                class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2"
                            >
                                <DocumentTextIcon class="h-5 w-5 text-gray-400" />
                                Caption
                            </h3>
                        </div>
                        <div class="p-6">
                            <p
                                v-if="video.caption"
                                class="text-xs lg:text-base text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed"
                            >
                                {{ video.caption }}
                            </p>
                            <p v-else class="text-gray-400 dark:text-gray-500 italic">
                                No caption provided.
                            </p>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <div
                                class="flex flex-col lg:flex-row items-center justify-between gap-3"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2"
                                >
                                    <ChatBubbleOvalLeftIcon class="h-5 w-5 text-gray-400" />
                                    Comments
                                    <span
                                        class="text-sm font-normal text-gray-500 dark:text-gray-400"
                                    >
                                        ({{ formatNumber(video.comments) }})
                                    </span>
                                </h3>
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="commentsSortBy"
                                        @change="fetchComments()"
                                        class="text-sm border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
                                    >
                                        <option value="newest">Newest</option>
                                        <option value="oldest">Oldest</option>
                                        <option value="most_liked">Most Liked</option>
                                        <option value="most_comments">Most Comments</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2">
                                    <AnimatedButton
                                        variant="light"
                                        pill
                                        size="sm"
                                        @click="fetchComments()"
                                        :disabled="commentsLoading"
                                    >
                                        <ArrowPathIcon
                                            class="size-5"
                                            :class="commentsLoading ? 'animate-spin' : ''"
                                        />
                                    </AnimatedButton>
                                    <AnimatedButton
                                        variant="light"
                                        pill
                                        size="sm"
                                        @click="commentsSettings"
                                    >
                                        <Cog8ToothIcon class="size-5" />
                                    </AnimatedButton>
                                    <AnimatedButton
                                        variant="light"
                                        pill
                                        size="sm"
                                        @click="toggleCommentsExpand"
                                    >
                                        <ArrowsPointingOutIcon class="size-5" />
                                    </AnimatedButton>
                                </div>
                            </div>
                        </div>

                        <div :class="[commentsExpand ? '' : 'max-h-[28rem] overflow-y-auto']">
                            <div v-if="commentsLoading" class="p-6">
                                <div v-for="i in 3" :key="i" class="flex gap-3 mb-5 animate-pulse">
                                    <div
                                        class="w-9 h-9 bg-gray-200 dark:bg-gray-700 rounded-full flex-shrink-0"
                                    ></div>
                                    <div class="flex-1 space-y-2">
                                        <div
                                            class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/4"
                                        ></div>
                                        <div
                                            class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3/4"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="comments.length === 0" class="p-12 text-center">
                                <ChatBubbleOvalLeftIcon
                                    class="h-10 w-10 text-gray-300 dark:text-gray-600 mx-auto mb-3"
                                />
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    No comments yet.
                                </p>
                            </div>

                            <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                                <div
                                    v-for="comment in comments"
                                    :key="comment.id"
                                    class="group px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <div class="flex gap-3">
                                        <router-link :to="`/admin/profiles/${comment.account.id}`">
                                            <img
                                                :src="comment.account.avatar"
                                                :alt="comment.account.username"
                                                class="w-9 h-9 rounded-full flex-shrink-0 ring-2 ring-gray-100 dark:ring-gray-700"
                                                @error="
                                                    $event.target.src =
                                                        '/storage/avatars/default.jpg'
                                                "
                                            />
                                        </router-link>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5 mb-1">
                                                <span
                                                    class="font-medium text-gray-900 dark:text-white text-sm"
                                                >
                                                    {{ comment.account.username }}
                                                </span>
                                                <CheckBadgeIcon
                                                    v-if="comment.account.verified"
                                                    class="h-4 w-4 text-blue-500"
                                                />
                                                <span class="text-gray-300 dark:text-gray-600"
                                                    >·</span
                                                >
                                                <a
                                                    :href="comment.url"
                                                    class="text-xs text-gray-500 dark:text-gray-400 hover:text-blue-500 hover:underline"
                                                    target="_blank"
                                                >
                                                    {{ formatTimeAgo(comment.created_at, 'long') }}
                                                </a>
                                            </div>
                                            <template v-if="comment.media?.length">
                                                <CommentMediaAttachment
                                                    v-for="m in comment.media"
                                                    :key="m.id"
                                                    :media="m"
                                                />
                                            </template>
                                            <p
                                                class="text-gray-700 dark:text-gray-300 text-sm mb-2 leading-relaxed"
                                                :class="{
                                                    italic: comment.tombstone,
                                                    'opacity-60': comment.tombstone
                                                }"
                                            >
                                                {{ comment.caption }}
                                            </p>
                                            <div
                                                v-if="!comment.tombstone"
                                                class="flex items-center gap-4"
                                            >
                                                <div
                                                    class="flex items-center gap-1 text-sm text-gray-400"
                                                >
                                                    <HeartIcon class="w-4 h-4" />
                                                    {{ formatNumber(comment.likes) }}
                                                </div>
                                                <div
                                                    class="flex items-center gap-1 text-sm text-gray-400"
                                                >
                                                    <ShareIcon class="w-4 h-4" />
                                                    {{ formatNumber(comment.shares) }}
                                                </div>
                                                <div
                                                    class="flex items-center gap-1 text-sm text-gray-400"
                                                >
                                                    <ChatBubbleLeftRightIcon class="w-4 h-4" />
                                                    {{ formatNumber(comment.replies) }}
                                                </div>
                                                <button
                                                    @click="deleteComment(comment)"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 lg:opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                                                >
                                                    <TrashIcon class="h-3.5 w-3.5" />
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="comment.replies > 0" class="ml-4 mt-2">
                                        <button
                                            @click="toggleReplies(comment)"
                                            :disabled="
                                                replyStates[comment.id]?.loading &&
                                                !replyStates[comment.id]?.loaded
                                            "
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 disabled:opacity-50 cursor-pointer"
                                        >
                                            <ArrowPathIcon
                                                v-if="
                                                    replyStates[comment.id]?.loading &&
                                                    !replyStates[comment.id]?.loaded
                                                "
                                                class="h-3.5 w-3.5 animate-spin"
                                            />
                                            <component
                                                v-else
                                                :is="
                                                    replyStates[comment.id]?.expanded
                                                        ? ChevronUpIcon
                                                        : ChevronDownIcon
                                                "
                                                class="h-3.5 w-3.5"
                                            />
                                            <span v-if="replyStates[comment.id]?.expanded">
                                                Hide replies
                                            </span>
                                            <span v-else>
                                                View {{ formatNumber(comment.replies) }}
                                                {{ comment.replies === 1 ? 'reply' : 'replies' }}
                                            </span>
                                        </button>
                                    </div>

                                    <div
                                        v-if="replyStates[comment.id]?.expanded"
                                        class="ml-4 mt-3 border-l-2 border-gray-100 dark:border-gray-700 pl-4 space-y-4"
                                    >
                                        <div
                                            v-for="reply in replyStates[comment.id].replies"
                                            :key="reply.id"
                                            class="group/reply flex gap-3"
                                        >
                                            <router-link
                                                :to="`/admin/profiles/${reply.account.id}`"
                                            >
                                                <img
                                                    :src="reply.account.avatar"
                                                    :alt="reply.account.username"
                                                    class="w-8 h-8 rounded-full flex-shrink-0 ring-2 ring-gray-100 dark:ring-gray-700"
                                                    @error="
                                                        $event.target.src =
                                                            '/storage/avatars/default.jpg'
                                                    "
                                                />
                                            </router-link>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-1.5 mb-1">
                                                    <span
                                                        class="font-medium text-gray-900 dark:text-white text-sm"
                                                    >
                                                        {{ reply.account.username }}
                                                    </span>
                                                    <CheckBadgeIcon
                                                        v-if="reply.account.verified"
                                                        class="h-4 w-4 text-blue-500"
                                                    />
                                                    <span class="text-gray-300 dark:text-gray-600"
                                                        >·</span
                                                    >
                                                    <a
                                                        :href="reply.url"
                                                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-blue-500 hover:underline"
                                                        target="_blank"
                                                    >
                                                        {{
                                                            formatTimeAgo(reply.created_at, 'long')
                                                        }}
                                                    </a>
                                                </div>
                                                <template v-if="reply.media?.length">
                                                    <CommentMediaAttachment
                                                        v-for="m in reply.media"
                                                        :key="m.id"
                                                        :media="m"
                                                    />
                                                </template>
                                                <p
                                                    class="text-gray-700 dark:text-gray-300 text-sm mb-2 leading-relaxed"
                                                >
                                                    {{ reply.caption }}
                                                </p>
                                                <div class="flex items-center gap-4">
                                                    <div
                                                        class="flex items-center gap-1 text-sm text-gray-400"
                                                    >
                                                        <HeartIcon class="w-4 h-4" />
                                                        {{ formatNumber(reply.likes) }}
                                                    </div>
                                                    <div
                                                        class="flex items-center gap-1 text-sm text-gray-400"
                                                    >
                                                        <ShareIcon class="w-4 h-4" />
                                                        {{ formatNumber(reply.shares) }}
                                                    </div>
                                                    <button
                                                        @click="deleteCommentReply(reply, comment)"
                                                        class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 lg:opacity-0 group-hover/reply:opacity-100 transition-opacity cursor-pointer"
                                                    >
                                                        <TrashIcon class="h-3.5 w-3.5" />
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="replyStates[comment.id]?.hasNext" class="pt-1">
                                            <button
                                                @click="loadMoreReplies(comment.id)"
                                                :disabled="replyStates[comment.id]?.loading"
                                                class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50 cursor-pointer"
                                            >
                                                <ArrowPathIcon
                                                    v-if="replyStates[comment.id]?.loading"
                                                    class="h-3.5 w-3.5 animate-spin"
                                                />
                                                {{
                                                    replyStates[comment.id]?.loading
                                                        ? 'Loading...'
                                                        : 'Load more replies'
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="commentsPagination.hasNext"
                                    class="p-4 text-center border-t border-gray-100 dark:border-gray-700"
                                >
                                    <button
                                        @click="loadMoreComments"
                                        :disabled="loadingMoreComments"
                                        class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium disabled:opacity-50 cursor-pointer"
                                    >
                                        {{
                                            loadingMoreComments
                                                ? 'Loading...'
                                                : 'Load More Comments'
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        <section>
                            <PanelCard>
                                <template #header>
                                    <div
                                        class="border-b border-gray-200 px-4 py-4 dark:border-gray-800 sm:px-6"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800"
                                                >
                                                    <ClockIcon
                                                        class="h-5 w-5 text-gray-500 dark:text-gray-400"
                                                    />
                                                </div>
                                                <div class="min-w-0">
                                                    <h3
                                                        class="text-base font-semibold tracking-tight text-gray-950 dark:text-white"
                                                    >
                                                        Audit Log
                                                    </h3>
                                                    <p
                                                        class="text-xs text-gray-500 dark:text-gray-400"
                                                    >
                                                        Admin actions taken on this video
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <AnimatedButton
                                                    variant="light"
                                                    pill
                                                    size="sm"
                                                    @click="auditLogSettings"
                                                >
                                                    <Cog8ToothIcon class="size-5" />
                                                </AnimatedButton>
                                                <AnimatedButton
                                                    variant="light"
                                                    pill
                                                    size="sm"
                                                    @click="toggleAuditLogExpand"
                                                >
                                                    <ArrowsPointingOutIcon class="size-5" />
                                                </AnimatedButton>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div
                                    class="p-4 sm:p-6"
                                    :class="[auditLogExpand ? '' : 'max-h-[28rem] overflow-y-auto']"
                                >
                                    <div
                                        v-if="!loadAuditLogs"
                                        class="py-10 flex items-center justify-center"
                                    >
                                        <AnimatedButton
                                            variant="primaryGradient"
                                            pill
                                            @click="handleLoadAuditLogs"
                                        >
                                            Load audit logs
                                        </AnimatedButton>
                                    </div>
                                    <TimelineEmpty
                                        v-if="
                                            !auditLogs.length && !isLoadingAudits && loadAuditLogs
                                        "
                                        title="No audit entries yet"
                                        description="Admin actions on this video will show up here."
                                    />

                                    <div v-else class="relative">
                                        <div
                                            class="absolute bottom-2 left-5 top-2 w-px bg-gray-200 dark:bg-gray-800"
                                        />

                                        <ul class="space-y-6">
                                            <li
                                                v-for="entry in auditLogs"
                                                :key="entry.id"
                                                class="relative flex gap-4"
                                            >
                                                <img
                                                    :src="entry.auditor.avatar"
                                                    :alt="entry.auditor.username"
                                                    class="relative z-10 h-10 w-10 shrink-0 rounded-full border-2 border-white bg-gray-100 object-cover dark:border-gray-900"
                                                    @error="handleAvatarError"
                                                />

                                                <div
                                                    class="min-w-0 flex-1 rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-gray-800/40"
                                                >
                                                    <div
                                                        class="flex flex-wrap items-center gap-2 text-sm"
                                                    >
                                                        <span
                                                            class="font-semibold text-gray-900 dark:text-white"
                                                        >
                                                            {{
                                                                entry.auditor.name ||
                                                                entry.auditor.username
                                                            }}
                                                        </span>
                                                        <span
                                                            class="text-gray-500 dark:text-gray-400"
                                                        >
                                                            @{{ entry.auditor.username }}
                                                        </span>
                                                        <span
                                                            class="text-gray-300 dark:text-gray-700"
                                                            >·</span
                                                        >
                                                        <span
                                                            v-if="entry.created_at"
                                                            class="text-xs text-gray-500 dark:text-gray-400"
                                                        >
                                                            {{ formatTimeAgo(entry.created_at) }}
                                                        </span>
                                                    </div>

                                                    <div class="mt-2">
                                                        <span
                                                            :class="[
                                                                'inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium',
                                                                auditTypeStyle(entry.type)
                                                            ]"
                                                        >
                                                            <component
                                                                :is="auditTypeIcon(entry.type)"
                                                                class="h-3.5 w-3.5"
                                                            />
                                                            {{ formatAuditType(entry.type) }}
                                                        </span>
                                                    </div>

                                                    <div
                                                        v-if="
                                                            [
                                                                'report:delete_comment_reply',
                                                                'video:delete_comment'
                                                            ].includes(entry.type) && entry.value
                                                        "
                                                        class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/60"
                                                    >
                                                        <div class="space-y-2.5 px-3.5 py-3">
                                                            <div
                                                                v-if="
                                                                    entry.actor &&
                                                                    entry.actor?.avatar
                                                                "
                                                                class="flex items-center gap-1"
                                                            >
                                                                <img
                                                                    :src="entry.actor?.avatar"
                                                                    :alt="entry.actor?.username"
                                                                    class="relative z-10 h-6 w-6 shrink-0 rounded-full border-2 border-white bg-gray-100 object-cover dark:border-gray-900"
                                                                    @error="handleAvatarError"
                                                                />
                                                                <span
                                                                    class="break-words text-xs font-medium text-gray-900 dark:text-white"
                                                                >
                                                                    {{ entry.actor.username }}
                                                                </span>
                                                            </div>

                                                            <div
                                                                v-else
                                                                class="flex items-center gap-1"
                                                            >
                                                                <img
                                                                    :src="DEFAULT_AVATAR"
                                                                    class="relative z-10 h-6 w-6 shrink-0 rounded-full border-2 border-white bg-gray-100 object-cover dark:border-gray-900"
                                                                />
                                                                <span
                                                                    class="break-words text-xs font-medium text-gray-900 dark:text-white italic opacity-50"
                                                                >
                                                                    DELETED USER
                                                                </span>
                                                            </div>

                                                            <p
                                                                class="line-clamp-2 break-words text-xs leading-5 text-gray-600 dark:text-gray-400"
                                                            >
                                                                {{ entry.value.comment_caption }}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div
                                                        v-if="getAuditDiff(entry).length"
                                                        class="mt-4 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60"
                                                    >
                                                        <div
                                                            v-for="change in getAuditDiff(entry)"
                                                            :key="change.key"
                                                            class="flex flex-wrap items-center gap-2 py-1 text-xs"
                                                        >
                                                            <span
                                                                class="font-mono text-gray-500 dark:text-gray-400"
                                                            >
                                                                {{ change.key }}:
                                                            </span>
                                                            <span :class="valueBadge(change.old)">
                                                                {{ formatValue(change.old) }}
                                                            </span>
                                                            <ArrowRightIcon
                                                                class="h-3 w-3 text-gray-400"
                                                            />
                                                            <span :class="valueBadge(change.new)">
                                                                {{ formatValue(change.new) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>

                                        <LoadMoreButton
                                            v-if="hasMoreAudits"
                                            :loading="isLoadingMoreAudits"
                                            @click="loadMoreAudits"
                                        />
                                    </div>
                                </div>
                            </PanelCard>
                        </section>
                    </div>
                </div>

                <div class="space-y-6">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5"
                    >
                        <div
                            class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3"
                        >
                            <UserCircleIcon class="h-4 w-4" />
                            Posted By
                        </div>
                        <router-link
                            :to="`/admin/profiles/${video.account.id}`"
                            class="flex items-center gap-3 -m-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <img
                                :src="video.account.avatar"
                                :alt="video.account.username"
                                class="w-12 h-12 rounded-full ring-2 ring-gray-100 dark:ring-gray-700"
                                @error="$event.target.src = '/storage/avatars/default.jpg'"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-900 dark:text-white truncate">
                                    {{ video.account.display_name || video.account.username }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                    @{{ video.account.username }}
                                </div>
                            </div>
                            <ChevronRightIcon class="h-5 w-5 text-gray-400 flex-shrink-0" />
                        </router-link>
                    </div>

                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Actions
                            </h3>
                        </div>
                        <div class="p-5 space-y-2">
                            <button
                                v-if="video.status !== 'published'"
                                @click="moderateVideo('publish')"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-colors cursor-pointer"
                            >
                                <CheckCircleIcon class="h-5 w-5" />
                                Approve Video
                            </button>
                            <button
                                v-if="video.status === 'published'"
                                @click="moderateVideo('unpublished')"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-500 text-white text-sm font-medium rounded-lg hover:bg-amber-600 transition-colors cursor-pointer"
                            >
                                <NoSymbolIcon class="h-5 w-5" />
                                Unlist Video
                            </button>

                            <Dropdown align="right" class="w-full">
                                <template #trigger>
                                    <button
                                        class="w-full inline-flex items-center justify-between gap-2 px-4 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors cursor-pointer"
                                    >
                                        <span class="flex items-center gap-4">
                                            <ShieldCheckIcon
                                                class="h-5 w-5 text-gray-500 dark:text-gray-400"
                                            />
                                            Moderation Flags
                                        </span>
                                        <ChevronDownIcon class="h-4 w-4" />
                                    </button>
                                </template>

                                <DropdownItem @click="handleToggleMarkAsAi">
                                    <div class="flex items-center gap-2.5">
                                        <SparklesIcon class="h-4 w-4 text-indigo-500" />
                                        {{
                                            video.meta.contains_ai
                                                ? 'Remove AI Badge'
                                                : 'Mark as AI'
                                        }}
                                    </div>
                                </DropdownItem>
                                <DropdownDivider class="my-1" />
                                <DropdownItem @click="handleToggleMarkAsAd">
                                    <div class="flex items-center gap-2.5">
                                        <MegaphoneIcon class="h-4 w-4 text-purple-500" />
                                        {{
                                            video.meta.contains_ad
                                                ? 'Remove Ad Badge'
                                                : 'Mark as Ad'
                                        }}
                                    </div>
                                </DropdownItem>
                                <DropdownDivider class="my-1" />
                                <DropdownItem @click="handleToggleNsfw">
                                    <div class="flex items-center gap-2.5">
                                        <component
                                            :is="video.is_sensitive ? EyeIcon : EyeSlashIcon"
                                            class="h-4 w-4 text-red-500"
                                        />
                                        {{
                                            video.is_sensitive
                                                ? 'Remove Sensitive'
                                                : 'Mark as Sensitive'
                                        }}
                                    </div>
                                </DropdownItem>
                                <DropdownDivider v-if="video.is_local" class="my-1" />
                                <DropdownItem v-if="video.is_local" @click="handleToggleEmbed">
                                    <div class="flex items-center gap-2.5">
                                        <component
                                            :is="
                                                video.permissions.can_embed
                                                    ? EyeSlashIcon
                                                    : CodeBracketIcon
                                            "
                                            class="h-4 w-4 text-red-500"
                                        />
                                        {{
                                            video.permissions.can_embed
                                                ? 'Disallow Embeds'
                                                : 'Allow Embeds'
                                        }}
                                    </div>
                                </DropdownItem>
                            </Dropdown>

                            <div
                                v-if="video.status !== 'archived'"
                                class="pt-2 mt-2 border-t border-gray-100 dark:border-gray-700"
                            >
                                <button
                                    @click="handleDeleteVideo()"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-500/30 text-sm font-medium rounded-lg hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors cursor-pointer"
                                >
                                    <TrashIcon class="h-5 w-5" />
                                    Delete Video
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Information
                            </h3>
                        </div>
                        <dl class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <CalendarIcon class="h-4 w-4" />
                                    Uploaded
                                </dt>
                                <dd
                                    class="text-xs font-medium text-gray-900 dark:text-white text-right"
                                >
                                    {{ formatDateTime(video.created_at) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <CircleStackIcon class="h-4 w-4" />
                                    File Size
                                </dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatBytes(video.media.size || 0) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <GlobeAltIcon class="h-4 w-4" />
                                    Privacy
                                </dt>
                                <dd
                                    class="text-sm font-medium text-gray-900 dark:text-white capitalize"
                                >
                                    {{ video.privacy || 'Public' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <BookmarkIcon class="h-4 w-4" />
                                    Pinned
                                </dt>
                                <dd
                                    class="text-sm font-medium"
                                    :class="
                                        video.pinned
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-gray-900 dark:text-white'
                                    "
                                >
                                    {{ video.pinned ? 'Yes' : 'No' }}
                                </dd>
                            </div>
                            <div
                                v-if="video.permissions"
                                class="flex items-center justify-between gap-3 px-5 py-3"
                            >
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <ChatBubbleOvalLeftIcon class="h-4 w-4" />
                                    Comments
                                </dt>
                                <dd
                                    class="text-sm font-medium"
                                    :class="
                                        video.permissions?.can_comment
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{ video.permissions?.can_comment ? 'Open' : 'Closed' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <ArrowDownTrayIcon class="h-4 w-4" />
                                    Downloads
                                </dt>
                                <dd
                                    class="text-sm font-medium"
                                    :class="
                                        video.permissions?.can_download
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{ video.permissions?.can_download ? 'Allowed' : 'Disabled' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <UsersIcon class="h-4 w-4" />
                                    Duets
                                </dt>
                                <dd
                                    class="text-sm font-medium"
                                    :class="
                                        video.permissions?.can_duet
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{ video.permissions?.can_duet ? 'Allowed' : 'Disabled' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <ScissorsIcon class="h-4 w-4" />
                                    Stitches
                                </dt>
                                <dd
                                    class="text-sm font-medium"
                                    :class="
                                        video.permissions?.can_stitch
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{ video.permissions?.can_stitch ? 'Allowed' : 'Disabled' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3">
                                <dt
                                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    <CodeBracketIcon class="h-4 w-4" />
                                    Embeds
                                </dt>
                                <dd
                                    class="text-sm font-medium"
                                    :class="
                                        video.permissions?.can_embed
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{ video.permissions?.can_embed ? 'Allowed' : 'Disabled' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div
                        v-if="video.media?.thumbnail"
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Thumbnail
                            </h3>
                        </div>
                        <div class="flex justify-center items-center bg-black">
                            <a
                                :href="video.media?.thumbnail"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <img
                                    :src="video.media?.thumbnail"
                                    alt="Thumbnail"
                                    class="w-40 h-40 object-scale-down"
                                />
                            </a>
                        </div>
                    </div>

                    <div
                        v-if="video.mentions?.length"
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Mentions
                            </h3>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div
                                v-for="tag in video.mentions"
                                :key="tag.profile_id"
                                class="flex items-center justify-between gap-3 px-5 py-3"
                            >
                                <router-link
                                    :to="`/admin/profiles/${tag.profile_id}`"
                                    class="flex items-center gap-2 font-medium text-blue-500 hover:text-blue-400 cursor-pointer"
                                >
                                    <UserCircleIcon class="h-4 w-4 dark:text-gray-400" />
                                    {{ tag.username }}
                                </router-link>
                                <div>
                                    <ChevronRightIcon class="h-4 w-4 dark:text-gray-400" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="video.tags?.length"
                        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Hashtags
                            </h3>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div
                                v-for="tag in video.tags"
                                :key="tag"
                                class="flex items-center justify-between gap-3 px-5 py-2"
                            >
                                <router-link
                                    :to="`/admin/hashtags?q=${tag}`"
                                    class="text-xs font-medium text-blue-500 hover:text-blue-400 cursor-pointer"
                                >
                                    #{{ tag }}
                                </router-link>
                                <div>
                                    <ChevronRightIcon class="h-4 w-4 dark:text-gray-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center"
        >
            <FilmIcon class="h-12 w-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                Video Not Found
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-sm mx-auto">
                The video you're looking for doesn't exist or has been removed.
            </p>
            <router-link
                to="/admin/videos"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
            >
                <ArrowLeftIcon class="h-4 w-4" />
                Back to Videos
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { videosApi } from '@/services/adminApi'
import { useAlertModal } from '@/composables/useAlertModal.js'
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    ArrowPathIcon,
    ArrowTopRightOnSquareIcon,
    BookmarkIcon,
    CalendarIcon,
    ChatBubbleLeftRightIcon,
    ChatBubbleOvalLeftIcon,
    CheckBadgeIcon,
    CheckCircleIcon,
    CheckIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronUpIcon,
    CircleStackIcon,
    ClipboardDocumentIcon,
    ClockIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    EnvelopeIcon,
    EyeIcon,
    EyeSlashIcon,
    FilmIcon,
    FlagIcon,
    GlobeAltIcon,
    HeartIcon,
    KeyIcon,
    LanguageIcon,
    LockClosedIcon,
    MegaphoneIcon,
    NoSymbolIcon,
    PencilSquareIcon,
    ScissorsIcon,
    ShareIcon,
    ShieldCheckIcon,
    SparklesIcon,
    TrashIcon,
    UserCircleIcon,
    UserIcon,
    UsersIcon,
    ArrowsPointingOutIcon,
    Cog8ToothIcon,
    CodeBracketIcon
} from '@heroicons/vue/24/outline'
import { useUtils } from '@/composables/useUtils'

const DEFAULT_AVATAR = '/storage/avatars/default.jpg'
const { formatNumber, formatTimeAgo, formatDateTime, formatBytes, formatDuration } = useUtils()
const { confirmModal, alertModal } = useAlertModal()

const route = useRoute()
const router = useRouter()

const videoId = computed(() => route.params.id)
const loading = ref(true)
const video = ref(null)
const commentsExpand = ref(localStorage.getItem('loops_admin_video_commentExpand') || false)
const comments = ref([])
const commentsLoading = ref(false)
const loadingMoreComments = ref(false)
const commentsSortBy = ref(localStorage.getItem('loops_admin_video_commentSortBy') || 'newest')
const copied = ref(false)
const commentsPagination = ref({
    cursor: null,
    hasNext: false
})
const videoEl = ref(null)

const loadAuditLogs = ref(localStorage.getItem('loops_admin_video_auditLogAutoLoad') || false)
const auditLogs = ref([])
const auditCursor = ref(null)
const auditLogExpand = ref(localStorage.getItem('loops_admin_video_auditLogAutoLoad') || false)
const hasMoreAudits = ref(false)
const isLoadingAudits = ref(false)
const isLoadingMoreAudits = ref(false)

const replyStates = reactive({})

const statusBadgeStyles = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    disabled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    deleted: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    suspended: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
}
const defaultBadge = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'

function valueBadge(val) {
    if (val === true)
        return 'rounded-lg bg-green-100 px-2 py-1 font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300'
    if (val === false)
        return 'rounded-lg bg-red-100 px-2 py-1 font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300'
    return 'rounded-lg bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300'
}

const formatValue = (val) => {
    if (val === true) return 'Enabled'
    if (val === false) return 'Disabled'
    if (val === null || val === undefined || val === '') return '—'
    return String(val)
}

const statusBadge = computed(() => statusBadgeStyles[video.value?.status] || defaultBadge)

const statusBadgeClasses = computed(() => {
    const status = video.value?.status
    const map = {
        published: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400',
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400',
        unpublished: 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400',
        archived: 'bg-gray-100 text-gray-800 dark:bg-gray-500/10 dark:text-gray-400'
    }
    return map[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-500/10 dark:text-gray-400'
})

const copyHid = async () => {
    try {
        await navigator.clipboard.writeText(video.value.hid)
        copied.value = true
        setTimeout(() => {
            copied.value = false
        }, 1500)
    } catch (e) {
        console.error('Failed to copy:', e)
    }
}

const ensureReplyState = (commentId) => {
    if (!replyStates[commentId]) {
        replyStates[commentId] = {
            replies: [],
            cursor: null,
            hasNext: false,
            loading: false,
            expanded: false,
            loaded: false
        }
    }
    return replyStates[commentId]
}

const fetchVideo = async () => {
    loading.value = true
    try {
        const response = await videosApi.getVideo(videoId.value)
        video.value = response.data
        await fetchComments()
        await fetchAdminAuditLogs()
    } catch (error) {
        console.error('Error fetching video:', error)
        video.value = null
    } finally {
        loading.value = false
    }
}

const fetchAdminAuditLogs = async (cursor = null) => {
    if (!loadAuditLogs.value) {
        return
    }

    try {
        if (!cursor) {
            isLoadingAudits.value = true
        }
        const res = await videosApi.getVideoAdminAuditLogs(videoId.value, cursor)
        const data = res.data || []
        auditLogs.value = cursor ? [...auditLogs.value, ...data] : data
        auditCursor.value = res.meta?.next_cursor || null
        hasMoreAudits.value = !!res.meta?.next_cursor
    } catch (error) {
        console.error('Error fetching audit logs:', error)
    } finally {
        isLoadingAudits.value = false
    }
}

const fetchComments = async (cursor = null) => {
    if (!cursor) {
        commentsLoading.value = true
        comments.value = []
    } else {
        loadingMoreComments.value = true
    }

    try {
        const response = await videosApi.getVideoComments(videoId.value, {
            cursor,
            sort: commentsSortBy.value
        })

        const newComments = response.data
        const pagination = response.meta

        if (cursor) {
            comments.value.push(...newComments)
        } else {
            comments.value = newComments
        }

        commentsPagination.value = {
            cursor: pagination.next_cursor,
            hasNext: pagination.next_cursor != null
        }
    } catch (error) {
        console.error('Error fetching comments:', error)
    } finally {
        commentsLoading.value = false
        loadingMoreComments.value = false
    }
}

const loadMoreComments = () => {
    if (commentsPagination.value.hasNext && !loadingMoreComments.value) {
        fetchComments(commentsPagination.value.cursor)
    }
}

const fetchReplies = async (commentId, cursor = null) => {
    const state = ensureReplyState(commentId)

    try {
        const response = await videosApi.getCommentReplies(commentId, {
            cursor,
            sort: commentsSortBy.value
        })

        const newReplies = response.data
        const pagination = response.meta

        if (cursor) {
            state.replies.push(...newReplies)
        } else {
            state.replies = newReplies
        }

        state.cursor = pagination.next_cursor
        state.hasNext = pagination.next_cursor != null
        state.loaded = true
    } catch (error) {
        console.error('Error fetching replies:', error)
    } finally {
        state.loading = false
    }
}

const toggleReplies = async (comment) => {
    const state = ensureReplyState(comment.id)
    if (state.expanded) {
        state.expanded = false
        return
    }
    if (!state.loaded) {
        await fetchReplies(comment.id)
    }
    state.expanded = true
}

const loadMoreReplies = (commentId) => {
    const state = replyStates[commentId]
    if (state?.hasNext && !state.loading) {
        fetchReplies(commentId, state.cursor)
    }
}

const loadMoreAudits = async () => {
    if (!hasMoreAudits.value || isLoadingMoreAudits.value) return
    isLoadingMoreAudits.value = true
    try {
        await fetchAdminAuditLogs(auditCursor.value)
    } finally {
        isLoadingMoreAudits.value = false
    }
}

const handleLoadAuditLogs = async () => {
    loadAuditLogs.value = true

    try {
        await fetchAdminAuditLogs()
    } catch (err) {
        console.log(err)
    }
}

const toggleAuditLogExpand = async () => {
    if (!loadAuditLogs.value) {
        await handleLoadAuditLogs()
    }

    auditLogExpand.value = !auditLogExpand.value
}

const commentsSettings = async () => {
    const res = await confirmModal(
        'Comments Auto Expand',
        'Do you want to automatically expand the comments list or keep the scrollable fixed height?',
        'Auto Expand',
        'Keep fixed height'
    )

    if (res) {
        localStorage.setItem('loops_admin_video_commentExpand', 1)
    } else {
        localStorage.removeItem('loops_admin_video_commentExpand')
    }
}

const toggleCommentsExpand = async () => {
    commentsExpand.value = !commentsExpand.value
}

const auditLogSettings = async () => {
    const res = await confirmModal(
        'Audit Log Auto Load',
        'Do you want to automatically load audit logs when viewing videos?',
        'Yes',
        'No'
    )

    if (res) {
        localStorage.setItem('loops_admin_video_auditLogAutoLoad', 1)
        await auditLogSettingsAutoExpand()
    } else {
        localStorage.removeItem('loops_admin_video_auditLogAutoLoad')
    }
}

const auditLogSettingsAutoExpand = async () => {
    const res = await confirmModal(
        'Audit Log Auto Expand',
        'Do you want to automatically expand the audit log list or keep the scrollable fixed height?',
        'Auto Expand',
        'Keep fixed height'
    )

    if (res) {
        localStorage.setItem('loops_admin_video_auditLogExpand', 1)
        await toggleAuditLogExpand()
    } else {
        localStorage.removeItem('loops_admin_video_auditLogExpand')
        await handleLoadAuditLogs()
    }
}

const moderateVideo = async (status) => {
    loading.value = true
    try {
        const response = await videosApi.moderateVideo(video.value.id, {
            action: status
        })
        await fetchVideo()
    } catch (error) {
        console.error('Error moderating video:', error)
    } finally {
        loading.value = false
    }
}

const handleToggleNsfw = async () => {
    await moderateVideo('nsfw')
}

const handleToggleMarkAsAd = async () => {
    await moderateVideo('ad')
}

const handleToggleMarkAsAi = async () => {
    await moderateVideo('ai')
}

const handleToggleEmbed = async () => {
    await moderateVideo('embed')
}

const handleDeleteVideo = async () => {
    const result = await confirmModal(
        'Delete Video?',
        'Are you sure you want to delete this video?',
        'Delete',
        'Cancel'
    )

    if (result) {
        await moderateVideo('delete')
        router.push('/admin/videos')
    }
}

const deleteComment = async (comment, parent = null) => {
    const result = await confirmModal(
        'Delete Video Comment',
        `Are you sure you want to delete this comment by ${comment.account.username}? This action cannot be undone.`,
        'Delete',
        'Cancel'
    )

    if (result) {
        try {
            await videosApi.deleteVideoComment(comment.id)
            if (parent) {
                const state = replyStates[parent.id]
                if (state) {
                    state.replies = state.replies.filter((r) => r.id !== comment.id)
                }
                parent.replies = Math.max(0, (parent.replies || 1) - 1)
            } else {
                comments.value = comments.value.filter((c) => c.id !== comment.id)
            }
            video.value.comments--
            await fetchVideo()
        } catch (error) {
            console.error('Error deleting comment:', error)
        }
    }
}

const handleAvatarError = (event) => {
    const img = event.target
    if (img.dataset.fallbackApplied) return
    img.dataset.fallbackApplied = '1'
    img.src = DEFAULT_AVATAR
}

const deleteCommentReply = async (comment, parent = null) => {
    const result = await confirmModal(
        'Delete Video Comment Reply',
        `Are you sure you want to delete this comment reply by ${comment.account.username}? This action cannot be undone.`,
        'Delete',
        'Cancel'
    )

    if (result) {
        try {
            await videosApi.deleteVideoCommentReply(comment.id)
            if (parent) {
                const state = replyStates[parent.id]
                if (state) {
                    state.replies = state.replies.filter((r) => r.id !== comment.id)
                }
                parent.replies = Math.max(0, (parent.replies || 1) - 1)
            } else {
                comments.value = comments.value.filter((c) => c.id !== comment.id)
            }
            await fetchVideo()
        } catch (error) {
            console.error('Error deleting comment:', error)
        }
    }
}

onMounted(() => {
    fetchVideo()
})

onBeforeUnmount(() => {
    videoEl.value?.pause()
})

const auditTypeLabels = {
    'video:delete_comment': 'Comment deleted',
    'video:delete': 'Video deleted',
    'video:unpublish': 'Video unpublished',
    'video:publish': 'Video published!',
    'report:delete_comment_reply': 'Comment reply deleted',
    'video:moderate': 'Video moderated'
}

const auditTypeStyles = {
    'video:publish': 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'profile:delete_all_comments': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'profile:send_email': 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    'video:unpublish': 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    'video:delete_comment': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'video:delete': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    'report:delete_comment_reply': 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
}

const auditTypeIcons = {
    'video:delete_comment': TrashIcon,
    'video:delete': TrashIcon,
    'video:unpublish': EyeSlashIcon,
    'report:delete_comment_reply': TrashIcon,
    'video:moderate': CheckBadgeIcon,
    'video:publish': CheckBadgeIcon
}

const getAuditDiff = (entry) => {
    if (!entry.value || typeof entry.value !== 'object') return []
    const newVals = entry.value.new || {}
    const oldVals = entry.value.old || {}
    return Object.keys(newVals).map((key) => ({
        key: key.replace(/_/g, ' '),
        old: oldVals[key],
        new: newVals[key]
    }))
}

const formatAuditType = (type) => auditTypeLabels[type] || type
const auditTypeStyle = (type) => auditTypeStyles[type] || defaultBadge
const auditTypeIcon = (type) => auditTypeIcons[type] || ClockIcon

const PanelCard = {
    setup(_, { slots }) {
        return () =>
            h(
                'div',
                {
                    class: 'overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900'
                },
                [slots.header?.(), slots.default?.()]
            )
    }
}

const TimelineEmpty = {
    props: ['title', 'description'],
    setup(props) {
        return () =>
            h(
                'div',
                {
                    class: 'rounded-2xl border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700'
                },
                [
                    h(ClockIcon, { class: 'mx-auto h-12 w-12 text-gray-300 dark:text-gray-700' }),
                    h(
                        'p',
                        { class: 'mt-3 text-sm font-medium text-gray-700 dark:text-gray-300' },
                        props.title
                    ),
                    props.description
                        ? h(
                              'p',
                              { class: 'mt-1 text-sm text-gray-500 dark:text-gray-400' },
                              props.description
                          )
                        : null
                ]
            )
    }
}

const LoadMoreButton = {
    emits: ['click'],
    props: ['loading'],
    setup(props, { emit }) {
        return () =>
            h('div', { class: 'mt-6 text-center' }, [
                h(
                    'button',
                    {
                        type: 'button',
                        disabled: props.loading,
                        onClick: () => emit('click'),
                        class: 'inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    },
                    [
                        props.loading
                            ? h(ArrowPathIcon, { class: 'h-4 w-4 animate-spin' })
                            : h(ChevronDownIcon, { class: 'h-4 w-4' }),
                        props.loading ? 'Loading...' : 'Load more'
                    ]
                )
            ])
    }
}

const diffRowClass =
    'mt-4 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60'

watch(commentsSortBy, (newQuery) => {
    if (newQuery === 'newest') {
        localStorage.removeItem('loops_admin_video_commentSortBy')
    } else {
        localStorage.setItem('loops_admin_video_commentSortBy', newQuery)
    }
})
</script>
