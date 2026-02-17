<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="$emit('update:modelValue', false)"
            >
                <div
                    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                    @click="$emit('update:modelValue', false)"
                />

                <div
                    class="modal-panel relative w-full max-w-lg bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700 flex flex-col max-h-[85vh]"
                >
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex-shrink-0"
                    >
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Comment Details
                            </h2>
                            <span
                                v-if="comment?.is_hidden"
                                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400"
                            >
                                <EyeSlashIcon class="w-3 h-3" />
                                Hidden
                            </span>
                            <span
                                v-if="comment?.tombstone"
                                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                            >
                                Tombstoned
                            </span>
                        </div>
                        <button
                            @click="$emit('update:modelValue', false)"
                            class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-300 dark:hover:bg-gray-800 transition-colors"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                        <div class="flex items-center gap-3">
                            <router-link
                                :to="`/admin/profiles/${comment?.account?.id}`"
                                @click="$emit('update:modelValue', false)"
                            >
                                <img
                                    :src="comment?.account?.avatar"
                                    :alt="comment?.account?.username"
                                    class="w-12 h-12 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-800 flex-shrink-0"
                                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                                />
                            </router-link>
                            <div class="min-w-0">
                                <router-link
                                    :to="`/admin/profiles/${comment?.account?.id}`"
                                    class="font-semibold text-gray-900 dark:text-white hover:underline block truncate"
                                    @click="$emit('update:modelValue', false)"
                                >
                                    {{ comment?.account?.name }}
                                </router-link>
                                <span
                                    class="text-sm text-gray-500 dark:text-gray-400 truncate block"
                                    >@{{ comment?.account?.username }}</span
                                >
                            </div>
                            <div class="ml-auto text-right flex-shrink-0">
                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ formatRecentDate(comment?.created_at) }}
                                </div>
                                <div
                                    v-if="comment?.is_edited"
                                    class="text-xs text-gray-400 dark:text-gray-500 mt-0.5"
                                >
                                    Edited
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-3"
                        >
                            <p
                                class="text-gray-800 dark:text-gray-200 text-sm leading-relaxed whitespace-pre-wrap break-words"
                                :class="{ 'italic opacity-60': comment.tombstone }"
                            >
                                {{ comment?.caption || '(no content)' }}
                            </p>
                        </div>

                        <div
                            class="grid gap-3"
                            :class="[parentComment ? 'grid-cols-2' : 'grid-cols-3']"
                        >
                            <div
                                class="rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700 px-3 py-2.5 text-center"
                            >
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ formatNumber(comment?.likes) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Likes
                                </div>
                            </div>
                            <div
                                v-if="!parentComment"
                                class="rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700 px-3 py-2.5 text-center"
                            >
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ comment.tombstone ? 0 : formatNumber(comment?.replies) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Replies
                                </div>
                            </div>
                            <router-link
                                :to="`/admin/videos/${comment?.v_id}`"
                                @click="$emit('update:modelValue', false)"
                                class="flex justify-center items-center rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 px-3 py-2.5 text-center hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
                            >
                                <div class="text-xs text-blue-500 dark:text-blue-500 mt-0.5">
                                    View Video →
                                </div>
                            </router-link>
                        </div>

                        <div v-if="comment?.url || comment?.remote_url" class="space-y-1.5">
                            <a
                                v-if="comment?.url"
                                :href="comment.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-2 text-xs text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 truncate group"
                            >
                                <LinkIcon class="w-3.5 h-3.5 flex-shrink-0" />
                                <span class="truncate group-hover:underline">{{
                                    comment.url
                                }}</span>
                            </a>
                            <a
                                v-if="comment?.remote_url"
                                :href="comment.remote_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-2 text-xs text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 truncate group"
                            >
                                <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5 flex-shrink-0" />
                                <span class="truncate group-hover:underline"
                                    >Remote: {{ comment.remote_url }}</span
                                >
                            </a>
                        </div>

                        <template v-if="loadingParent || parentComment">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 h-px bg-gray-100 dark:bg-gray-800"></div>
                                <span
                                    class="text-[11px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500"
                                >
                                    In reply to
                                </span>
                                <div class="flex-1 h-px bg-gray-100 dark:bg-gray-800"></div>
                            </div>

                            <div
                                v-if="loadingParent"
                                class="rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700 px-4 py-3 animate-pulse"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex-shrink-0"
                                    ></div>
                                    <div class="flex-1 space-y-2">
                                        <div
                                            class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/3"
                                        ></div>
                                        <div
                                            class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3/4"
                                        ></div>
                                        <div
                                            class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else-if="parentComment"
                                class="rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700 px-4 py-3"
                            >
                                <div class="flex items-start gap-3">
                                    <router-link
                                        :to="`/admin/profiles/${parentComment.account.id}`"
                                        @click="$emit('update:modelValue', false)"
                                        class="flex-shrink-0"
                                    >
                                        <img
                                            :src="parentComment.account.avatar"
                                            :alt="parentComment.account.username"
                                            class="w-8 h-8 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-700"
                                            @error="
                                                $event.target.src = '/storage/avatars/default.jpg'
                                            "
                                        />
                                    </router-link>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <router-link
                                                :to="`/admin/profiles/${parentComment.account.id}`"
                                                class="text-sm font-semibold text-gray-900 dark:text-white hover:underline truncate"
                                                @click="$emit('update:modelValue', false)"
                                            >
                                                {{ parentComment.account.name }}
                                            </router-link>
                                            <span
                                                class="text-xs text-gray-500 dark:text-gray-400 truncate"
                                            >
                                                @{{ parentComment.account.username }}
                                            </span>
                                        </div>
                                        <p
                                            class="mt-1.5 text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap break-words line-clamp-3"
                                        >
                                            {{ parentComment.caption || '(no content)' }}
                                        </p>
                                        <div
                                            class="mt-2 flex items-center gap-3 text-xs text-gray-400 dark:text-gray-500"
                                        >
                                            <a
                                                :href="parentComment.url"
                                                target="_blank"
                                                class="hover:text-blue-500 transition-colors"
                                            >
                                                {{ formatRecentDate(parentComment.created_at) }}
                                            </a>
                                            <span class="flex items-center gap-1">
                                                <HeartIcon class="w-3.5 h-3.5" />
                                                {{ formatNumber(parentComment.likes) }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <ChatBubbleOvalLeftIcon class="w-3.5 h-3.5" />
                                                {{ formatNumber(parentComment.replies) }}
                                            </span>
                                            <router-link
                                                :to="`/admin/videos/${parentComment.v_id}`"
                                                class="ml-auto text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 hover:underline"
                                                @click="$emit('update:modelValue', false)"
                                            >
                                                View Video →
                                            </router-link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div
                        class="flex items-center justify-between gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800 flex-shrink-0"
                    >
                        <button
                            @click="$emit('update:modelValue', false)"
                            class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            Close
                        </button>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="comment?.is_hidden"
                                @click="$emit('unhide', comment)"
                                :disabled="actionLoading"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50 border border-green-200 dark:border-green-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <EyeIcon v-if="!actionLoading" class="w-4 h-4" />
                                <Spinner v-else size="xs" />
                                Unhide
                            </button>

                            <button
                                v-else
                                @click="$emit('hide', comment)"
                                :disabled="actionLoading"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50 border border-amber-200 dark:border-amber-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <EyeSlashIcon v-if="!actionLoading" class="w-4 h-4" />
                                <Spinner v-else size="xs" />
                                Hide
                            </button>

                            <button
                                @click="$emit('delete', comment)"
                                :disabled="actionLoading"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 border border-red-200 dark:border-red-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <TrashIcon v-if="!actionLoading" class="w-4 h-4" />
                                <Spinner v-else size="xs" />
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { useUtils } from '@/composables/useUtils'
import {
    EyeIcon,
    EyeSlashIcon,
    HeartIcon,
    TrashIcon,
    ChatBubbleOvalLeftIcon,
    LinkIcon,
    ArrowTopRightOnSquareIcon
} from '@heroicons/vue/24/outline'
const { truncateMiddle, formatNumber, formatRecentDate } = useUtils()

defineProps({
    modelValue: Boolean,
    comment: Object,
    parentComment: Object,
    loadingParent: Boolean,
    actionLoading: Boolean
})

defineEmits(['update:modelValue', 'hide', 'unhide', 'delete'])
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-active .modal-panel,
.modal-leave-active .modal-panel {
    transition:
        transform 0.2s ease,
        opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .modal-panel {
    transform: scale(0.95) translateY(8px);
    opacity: 0;
}

.modal-leave-to .modal-panel {
    transform: scale(0.95) translateY(8px);
    opacity: 0;
}
</style>
