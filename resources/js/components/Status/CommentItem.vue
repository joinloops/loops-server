<template>
    <div
        class="group/comment-root pb-3"
        :class="[
            'comment-item',
            {
                'highlighted-comment': isHighlighted && !highlightedReplyId,
                'highlighted-parent': isHighlighted && highlightedReplyId
            }
        ]"
    >
        <div class="flex space-x-3 px-4 pt-3">
            <div v-if="comment.tombstone">
                <img
                    :src="comment.account.avatar"
                    :alt="comment.account.username"
                    class="w-10 h-10 rounded-full flex-shrink-0"
                />
            </div>
            <router-link v-else :to="`/@${comment.account.username}`">
                <img
                    :src="comment.account.avatar"
                    :alt="comment.account.username"
                    class="w-10 h-10 rounded-full flex-shrink-0"
                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                />
            </router-link>
            <div class="flex-1 min-w-0">
                <div v-if="comment.tombstone" class="mb-1">
                    <div class="flex items-center space-x-1">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{
                            comment.account.name
                        }}</span>
                    </div>
                </div>
                <div v-else class="mb-1">
                    <router-link
                        :to="`/@${comment.account.username}`"
                        class="flex items-center space-x-1"
                    >
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >@{{ comment.account.username }}</span
                        >
                        <span
                            v-if="comment.account.id == activePost.account.id"
                            class="text-sm font-medium text-[#fe2c55]"
                            >·</span
                        >
                        <span
                            v-if="comment.account.id == activePost.account.id"
                            class="text-sm font-medium text-[#fe2c55]"
                            >{{ $t('post.creator') }}</span
                        >
                    </router-link>
                </div>

                <div v-if="isEditing" class="mb-2">
                    <MentionHashtagInput
                        ref="replyEditInputRef"
                        v-model="editedCaption"
                        :placeholder="$t('post.editYourComment')"
                        :disabled="isSavingEdit"
                        :fetch-mentions="fetchMentions"
                        :fetch-hashtags="fetchHashtags"
                        :validate-mentions="true"
                        :validate-hashtags="true"
                        :initial-validated-mentions="initialValidatedMentions"
                        :initial-validated-hashtags="initialValidatedHashtags"
                        min-height="80px"
                        max-height="400px"
                    />
                    <div class="flex items-center justify-between mt-2">
                        <div
                            class="text-xs"
                            :class="[
                                editedCaption.length > MAX_EDIT_CHAR_LIMIT
                                    ? 'text-red-500'
                                    : 'text-gray-400 dark:text-gray-500'
                            ]"
                        >
                            {{ editedCaption.length }} /
                            {{ MAX_EDIT_CHAR_LIMIT }}
                        </div>
                        <div class="flex space-x-2">
                            <button
                                @click="cancelEdit"
                                class="px-4 py-1.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors cursor-pointer"
                                :disabled="isSavingEdit"
                            >
                                {{ $t('common.cancel') }}
                            </button>
                            <button
                                @click="saveEdit"
                                class="px-4 py-1.5 bg-[#F02C56] hover:bg-[#F02C56]/70 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors cursor-pointer flex items-center space-x-2 disabled:pointer-events-none"
                                :disabled="
                                    isSavingEdit ||
                                    !editedCaption.trim() ||
                                    editedCaption.length > MAX_EDIT_CHAR_LIMIT
                                "
                            >
                                <Spinner v-if="isSavingEdit" size="xs" theme="brand" />
                                <span v-else>{{ $t('common.save') }}</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="isSavingEdit" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('common.savingDotDotDot') }}
                    </div>
                </div>

                <div v-else class="mb-2">
                    <p
                        class="text-[16px] leading-relaxed break-all"
                        :class="[
                            comment.tombstone
                                ? 'italic text-gray-500 dark:text-gray-500'
                                : 'text-[#161823] dark:text-gray-100'
                        ]"
                    >
                        <AutolinkedText
                            :caption="comment?.caption"
                            :mentions="comment?.mentions"
                            :tags="comment?.tags"
                            text-size="text-[16px]"
                            :root-class="
                                comment.tombstone
                                    ? 'text-gray-500'
                                    : 'text-gray-800 dark:text-slate-300 whitespace-pre-wrap leading-relaxed'
                            "
                            :max-char-limit="80"
                        />
                    </p>
                </div>

                <div
                    v-if="!comment.tombstone && !isEditing"
                    class="flex items-center justify-between"
                >
                    <div class="flex items-center space-x-4 text-gray-400 text-sm">
                        <span>{{ formatContentDate(comment.created_at) }}</span>
                        <button
                            v-if="comment.is_edited"
                            class="cursor-pointer text-gray-400 hover:text-gray-500 flex items-center gap-1"
                            @click="openCommentHistory(comment.v_id, comment.id)"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="size-4"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                />
                            </svg>

                            {{ $t('common.edited') }}
                        </button>
                        <button
                            v-if="authStore.isAuthenticated"
                            @click="startReply"
                            class="cursor-pointer text-gray-400 hover:text-gray-500 flex items-center gap-1"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="size-4"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z"
                                />
                            </svg>

                            {{ $t('post.reply') }}
                        </button>
                    </div>
                </div>

                <div v-if="showReplyInput && authStore.isAuthenticated" class="mt-3">
                    <div class="flex space-x-3">
                        <img
                            :src="authStore.user?.avatar"
                            :alt="authStore.user?.username"
                            class="w-8 h-8 rounded-full flex-shrink-0"
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                        <div class="flex-1">
                            <MentionHashtagInput
                                ref="replyInputRef"
                                v-model="replyText"
                                :placeholder="$t('post.writeAReplyDotDotDot')"
                                :disabled="isSavingEdit"
                                :fetch-mentions="fetchMentions"
                                :fetch-hashtags="fetchHashtags"
                                :validate-mentions="true"
                                :validate-hashtags="true"
                                :initial-validated-mentions="initialValidatedMentions"
                                :initial-validated-hashtags="initialValidatedHashtags"
                                min-height="80px"
                                max-height="400px"
                            />

                            <div class="flex justify-end mt-2">
                                <button
                                    @click="handleReply"
                                    class="px-4 py-1.5 bg-[#F02C56] hover:bg-[#F02C56]/70 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors cursor-pointer"
                                    :disabled="isSubmittingReply || !replyText.trim()"
                                >
                                    {{
                                        isSubmittingReply
                                            ? $t('post.postingDotDotDot')
                                            : $t('post.reply')
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="shouldShowInitialLoadButton"
                    :class="[comment.tombstone ? 'pt-0' : 'pt-4']"
                >
                    <button
                        @click="loadReplies"
                        class="text-sm text-gray-400 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium transition-colors flex items-center space-x-2 cursor-pointer"
                    >
                        <div class="flex w-8 bg-gray-300 dark:bg-gray-700 h-[1px]"></div>
                        {{ $t('post.view') }} {{ totalRepliesCount }}
                        {{ totalRepliesCount === 1 ? $t('post.reply') : $t('post.replies') }}
                        <i class="bx bx-chevron-down text-lg" />
                    </button>

                    <div
                        v-if="isLoadingReplies"
                        class="text-sm text-gray-500 dark:text-gray-400 mt-2"
                    >
                        <Spinner />
                    </div>
                </div>
            </div>

            <div class="relative flex flex-col">
                <div
                    v-if="!comment.tombstone"
                    class="relative flex opacity-0 group-hover/comment-root:opacity-100 transition-opacity duration-200"
                >
                    <button
                        @click.stop="toggleDropdown"
                        type="button"
                        aria-haspopup="menu"
                        :aria-expanded="showDropdown"
                        class="p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-500/70 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-950"
                    >
                        <EllipsisHorizontalIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    </button>

                    <transition
                        enter-active-class="transition ease-out duration-120"
                        enter-from-class="opacity-0 translate-y-1 scale-95"
                        enter-to-class="opacity-100 translate-y-0 scale-100"
                        leave-active-class="transition ease-in duration-90"
                        leave-from-class="opacity-100 translate-y-0 scale-100"
                        leave-to-class="opacity-0 translate-y-1 scale-95"
                    >
                        <div
                            v-show="showDropdown"
                            @click.stop
                            role="menu"
                            class="absolute right-0 mt-2 w-56 origin-top-right z-50"
                        >
                            <div
                                class="rounded-xl bg-white/95 dark:bg-gray-900/95 backdrop-blur shadow-xl shadow-black/5 dark:shadow-black/30 ring-1 ring-black/5 dark:ring-white/10 border border-gray-200/70 dark:border-gray-800/70 p-1"
                            >
                                <a
                                    v-if="comment.remote_url"
                                    :href="comment.remote_url"
                                    target="_blank"
                                    rel="noopener"
                                    role="menuitem"
                                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100/80 dark:hover:bg-gray-800/70"
                                >
                                    <ArrowTopRightOnSquareIcon
                                        class="w-4 h-4 text-gray-500 dark:text-gray-400"
                                    />
                                    <span class="flex-1">{{ $t('common.openOriginalPage') }}</span>
                                </a>

                                <a
                                    :href="comment.url"
                                    role="menuitem"
                                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100/80 dark:hover:bg-gray-800/70"
                                >
                                    <LinkIcon class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                    <span class="flex-1">{{ $t('post.permalink') }}</span>
                                </a>

                                <div class="my-1 h-px bg-gray-100 dark:bg-gray-800"></div>

                                <button
                                    v-if="comment.is_owner"
                                    @click="startEdit"
                                    type="button"
                                    role="menuitem"
                                    class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100/80 dark:hover:bg-gray-800/70"
                                >
                                    <PencilSquareIcon
                                        class="w-4 h-4 text-gray-500 dark:text-gray-400"
                                    />
                                    <span class="flex-1">{{ $t('common.edit') }}</span>
                                </button>

                                <template v-if="isVideoOwner">
                                    <button
                                        v-if="!comment.is_hidden"
                                        @click="handleHideComment"
                                        type="button"
                                        role="menuitem"
                                        class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/10"
                                    >
                                        <EyeSlashIcon
                                            class="w-4 h-4 text-red-600/80 dark:text-red-300/80"
                                        />
                                        <span class="flex-1">{{ $t('post.hideComment') }}</span>
                                    </button>

                                    <button
                                        v-else
                                        @click="handleUnhideComment"
                                        type="button"
                                        role="menuitem"
                                        class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/10"
                                    >
                                        <EyeIcon
                                            class="w-4 h-4 text-red-600/80 dark:text-red-300/80"
                                        />
                                        <span class="flex-1">{{ $t('post.unhideComment') }}</span>
                                    </button>
                                </template>

                                <div
                                    v-if="comment.is_owner || isVideoOwner"
                                    class="my-1 h-px bg-gray-100 dark:bg-gray-800"
                                ></div>

                                <button
                                    v-if="comment.is_owner"
                                    @click="handleDelete"
                                    type="button"
                                    role="menuitem"
                                    :disabled="isDeletingComment"
                                    class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/10 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <TrashIcon
                                        class="w-4 h-4 text-red-600/80 dark:text-red-300/80"
                                    />
                                    <span class="flex-1">
                                        {{
                                            isDeletingComment
                                                ? $t('post.deletingDotDotDot')
                                                : $t('post.delete')
                                        }}
                                    </span>
                                </button>

                                <button
                                    v-else
                                    @click="handleReport"
                                    type="button"
                                    role="menuitem"
                                    class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100/80 dark:hover:bg-gray-800/70"
                                >
                                    <FlagIcon class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                                    <span class="flex-1">{{ $t('common.report') }}</span>
                                </button>
                            </div>
                        </div>
                    </transition>
                </div>

                <div
                    v-if="!comment.tombstone && !isEditing"
                    class="relative flex justify-center items-center space-x-1"
                >
                    <button
                        @click="handleLike"
                        class="flex flex-col justify-center items-center space-y-1 hover:text-red-500 transition-colors"
                        :disabled="isLikeLoading"
                    >
                        <i :class="heartIconClass" class="text-xl transition-colors"></i>
                        <span class="text-gray-400 dark:text-gray-100">{{
                            comment.likes || 0
                        }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="shouldShowToggleButton" class="mt-2 ml-[52px] pl-3">
            <button
                @click="toggleReplies"
                class="text-sm text-gray-400 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium transition-colors flex items-center space-x-2 cursor-pointer"
            >
                <template v-if="showReplies">
                    <div class="flex mb-3 items-center space-x-2">
                        <div class="flex w-8 bg-gray-300 dark:bg-gray-700 h-[1px]"></div>
                        {{ $t('post.hide') }}
                        <i class="bx bx-chevron-up text-lg" />
                    </div>
                </template>

                <template v-else>
                    <div class="flex w-8 bg-gray-300 dark:bg-gray-700 h-[1px]"></div>
                    {{ $t('post.view') }} {{ comment.children?.length || 0 }}
                    {{ comment.children?.length === 1 ? $t('post.reply') : $t('post.replies') }}
                    <i class="bx bx-chevron-down text-lg" />
                </template>
            </button>
        </div>

        <div v-if="shouldShowReplies" class="relative px-4 pb-2 ml-[52px] pl-3">
            <div v-if="highlightedReplyId" class="mt-3"></div>

            <div class="space-y-5">
                <NestedCommentItem
                    v-for="reply in visibleReplies"
                    :key="reply.id"
                    :comment="reply"
                    :video-id="videoId"
                    :parent-comment-id="comment.id"
                />
            </div>

            <div v-if="!highlightedReplyId && hasMoreReplies" class="mt-3">
                <button
                    @click="loadMoreReplies"
                    class="text-sm text-[#F02C56] hover:text-[#F02C56]/70 font-medium cursor-pointer"
                    :disabled="isLoadingMoreReplies"
                >
                    {{ isLoadingMoreReplies ? $t('common.loading') : $t('post.loadMoreReplies') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, inject, nextTick } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCommentStore } from '@/stores/comments'
import { useReportModal } from '@/composables/useReportModal'
import NestedCommentItem from './NestedCommentItem.vue'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useUtils } from '@/composables/useUtils'
import { useI18n } from 'vue-i18n'
import { useEditHistory } from '@/composables/useEditHistory'
import MentionHashtagInput from '@/components/Status/MentionHashtagInput.vue'
import { useHideCommentModal } from '@/composables/useHideCommentModal'
import {
    EllipsisHorizontalIcon,
    ArrowTopRightOnSquareIcon,
    LinkIcon,
    PencilSquareIcon,
    EyeSlashIcon,
    EyeIcon,
    TrashIcon,
    FlagIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    comment: {
        type: Object,
        required: true
    },
    videoId: {
        type: String,
        required: true
    },
    parentCommentId: {
        type: String,
        default: null
    },
    isHighlighted: {
        type: Boolean,
        default: false
    },
    highlightedReplyId: {
        type: [String, Number],
        default: null
    }
})

const MAX_CHAR_LIMIT = 150
const MAX_EDIT_CHAR_LIMIT = 500

const appStore = inject('appStore')
const authStore = useAuthStore()
const videoStore = inject('videoStore')
const commentStore = useCommentStore()
const { formatContentDate } = useUtils()
const { alertModal, confirmModal } = useAlertModal()
const { openReportModal } = useReportModal()
const { t } = useI18n()
const { openCommentHistory } = useEditHistory()

const showReplyInput = ref(false)
const replyText = ref('')
const showDropdown = ref(false)
const isSubmittingReply = ref(false)
const isDeletingComment = ref(false)
const replyInputRef = ref()
const replyEditInputRef = ref()

const { openHideCommentModal, openUnhideCommentModal } = useHideCommentModal()

// Edit state
const isEditing = ref(false)
const editedCaption = ref('')
const isSavingEdit = ref(false)
const initialValidatedMentions = ref([])
const initialValidatedHashtags = ref([])
const showReplies = ref(false)

const handleHideComment = async () => {
    showDropdown.value = false

    const success = await openHideCommentModal(
        props.videoId,
        props.comment.id,
        props.parentCommentId
    )

    if (success) {
        await videoStore.decrementCommentCount()
    }
}

const handleUnhideComment = async () => {
    showDropdown.value = false

    const success = await openUnhideCommentModal(
        props.videoId,
        props.comment.id,
        props.parentCommentId
    )

    if (success) {
        await videoStore.incrementCommentCount()
    }
}

const activePost = computed(() => {
    return videoStore.currentVideo
})

const isVideoOwner = computed(() => {
    return videoStore.currentVideo.account.id === authStore.id
})

const isLoadingReplies = computed(() => {
    return commentStore.isLoadingReplies(props.videoId, props.comment.id)
})

const isLoadingMoreReplies = computed(() => {
    return commentStore.isLoadingMoreReplies(props.videoId, props.comment.id)
})

const hasMoreReplies = computed(() => {
    return commentStore.hasMoreReplies(props.videoId, props.comment.id)
})

const repliesLoaded = computed(() => {
    return commentStore.areRepliesLoaded(props.videoId, props.comment.id)
})

const totalRepliesCount = computed(() => {
    return props.comment.replies || props.comment.children?.length || 0
})

const isLikeLoading = computed(() => {
    return commentStore.isLikeLoading(props.videoId, props.comment.id)
})

const heartIconClass = computed(() => {
    if (isLikeLoading.value) {
        return 'bx bx-loader-alt bx-spin text-gray-400'
    }

    return props.comment.liked
        ? 'bx bxs-heart text-red-500'
        : 'bx bx-heart text-gray-400 hover:text-red-500'
})

const startReply = async () => {
    if (showReplyInput.value) {
        showReplyInput.value = false
        return
    }
    showReplyInput.value = true

    const username = props.comment.account.username
    replyText.value = `@${username} `

    await nextTick()

    setTimeout(() => {
        if (replyInputRef.value) {
            console.log('Focusing input...', replyInputRef.value)
            replyInputRef.value.focus()
            replyInputRef.value.moveCursorToEnd()
        } else {
            console.log('replyInputRef is null')
        }
    }, 50)
}

const shouldShowInitialLoadButton = computed(() => {
    // Don't show if we're highlighting a specific reply
    if (props.highlightedReplyId) {
        return false
    }

    // Show only if there are replies and they haven't been loaded yet
    return totalRepliesCount.value > 0 && !repliesLoaded.value && !isLoadingReplies.value
})

const shouldShowToggleButton = computed(() => {
    if (props.highlightedReplyId) {
        return false
    }

    // Show if replies have been loaded and there are children
    return repliesLoaded.value && props.comment.children && props.comment.children.length > 0
})

const shouldShowReplies = computed(() => {
    // Always show if we're highlighting a specific reply
    if (props.highlightedReplyId) {
        return true
    }

    // Otherwise show based on toggle state
    return showReplies.value
})

const visibleReplies = computed(() => {
    if (!props.comment.children || props.comment.children.length === 0) {
        return []
    }

    // If highlighting a specific reply, only show that reply
    if (props.highlightedReplyId) {
        return props.comment.children.filter(
            (reply) => reply.id.toString() === props.highlightedReplyId.toString()
        )
    }

    // Otherwise show all loaded replies
    return props.comment.children
})

const toggleReplies = () => {
    showReplies.value = !showReplies.value
}

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value
}

const handleClickOutside = (event) => {
    if (showDropdown.value) {
        showDropdown.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const startEdit = async () => {
    isEditing.value = true
    editedCaption.value = props.comment.caption || ''
    showDropdown.value = false
    showReplyInput.value = false

    const mentionRegex = /@([\w._-]+(?:@[\w.-]+\.\w+)?)/g
    const mentions = [...props.comment.caption.matchAll(mentionRegex)].map((m) => m[1])
    initialValidatedMentions.value = mentions

    const hashtagRegex = /#([\w_]+)/g
    const hashtags = [...props.comment.caption.matchAll(hashtagRegex)].map((m) => m[1])
    initialValidatedHashtags.value = hashtags

    await nextTick()

    setTimeout(() => {
        if (replyEditInputRef.value) {
            console.log('Focusing input...', replyEditInputRef.value)
            replyEditInputRef.value.focus()
            replyEditInputRef.value.moveCursorToEnd()
        } else {
            console.log('replyInputRef is null')
        }
    }, 50)
}

const cancelEdit = async () => {
    const result = await confirmModal(
        t('common.confirmCancel'),
        t('post.confirmCancelEdit'),
        t('common.yes'),
        t('common.no')
    )

    if (!result) {
        return
    }

    isEditing.value = false
    editedCaption.value = ''
}

const saveEdit = async () => {
    if (!editedCaption.value.trim() || isSavingEdit.value) return
    if (editedCaption.value.length > MAX_EDIT_CHAR_LIMIT) return

    try {
        isSavingEdit.value = true

        await handleCaptionUpdate(props.videoId, props.comment.id, editedCaption.value)

        isEditing.value = false
    } catch (error) {
        await alertModal(
            '⚠️ ' + t('common.somethingWentWrong'),
            error || t('common.unexpectedError')
        )
        console.error('Failed to update comment:', error)
    } finally {
        isSavingEdit.value = false
    }
}

const handleCaptionUpdate = async (videoId, commentId, newCaption) => {
    await commentStore.updateComment(videoId, commentId, newCaption)
}

const handleReply = async () => {
    if (!replyText.value.trim() || isSubmittingReply.value) return

    try {
        isSubmittingReply.value = true

        await commentStore.addCommentReply(props.videoId, props.comment.id, replyText.value)

        await videoStore.incrementCommentCount()

        replyText.value = ''
        showReplyInput.value = false
    } catch (error) {
        await alertModal(
            t('common.error'),
            error?.response?.data?.message || t('common.unexpectedError')
        )
        console.error('Failed to add reply:', error)
    } finally {
        isSubmittingReply.value = false
    }
}

const loadReplies = async () => {
    if (isLoadingReplies.value) return

    showReplies.value = true

    try {
        await commentStore.fetchReplies(props.videoId, props.comment.id, true)
    } catch (error) {
        console.error('Failed to load replies:', error)
    }
}

const loadMoreReplies = async () => {
    if (isLoadingReplies.value || !hasMoreReplies.value) return

    try {
        await commentStore.fetchReplies(props.videoId, props.comment.id, false)
    } catch (error) {
        console.error('Failed to load more replies:', error)
    }
}

const handleDelete = async () => {
    if (isDeletingComment.value) return

    const result = await confirmModal(
        t('common.confirm'),
        t('post.confirmDeleteComment'),
        t('post.delete'),
        t('common.cancel')
    )

    if (!result) {
        showDropdown.value = false
        return
    }

    try {
        isDeletingComment.value = true
        showDropdown.value = false

        if (props.parentCommentId) {
            await commentStore.deleteCommentReply(
                props.videoId,
                props.parentCommentId,
                props.comment.id
            )
        } else {
            await commentStore.deleteComment(props.videoId, props.comment.id)
        }

        await videoStore.decrementCommentCount()
    } catch (error) {
        console.error('Failed to delete comment:', error)
    } finally {
        isDeletingComment.value = false
    }
}

const handleReport = () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    openReportModal('comment', props.comment.id, window.location.href)
    showDropdown.value = false
}

const handleLike = async () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }

    if (isLikeLoading.value) return

    try {
        if (props.comment.liked) {
            await commentStore.unlikeComment(props.videoId, props.comment.id)
        } else {
            await commentStore.likeComment(props.videoId, props.comment.id)
        }
    } catch (error) {
        console.error('Failed to toggle like:', error)
    }
}

const fetchMentions = async (query) => {
    try {
        const response = await videoStore.autocompleteAccount(encodeURIComponent(query))
        return response.data
    } catch (error) {
        console.error('Error fetching mentions:', error)
        return []
    }
}

const fetchHashtags = async (query) => {
    try {
        const response = await videoStore.autocompleteHashtag(encodeURIComponent(query))
        return response.data
    } catch (error) {
        console.error('Error fetching hashtags:', error)
        return []
    }
}
</script>

<style scoped>
@keyframes highlight-pulse {
    0% {
        background-color: rgba(240, 44, 86, 0.15);
        box-shadow: 0 0 0 0 rgba(240, 44, 86, 0.4);
    }

    50% {
        background-color: rgba(240, 44, 86, 0.25);
        box-shadow: 0 0 0 8px rgba(240, 44, 86, 0);
    }

    100% {
        background-color: rgba(240, 44, 86, 0.1);
        box-shadow: 0 0 0 0 rgba(240, 44, 86, 0);
    }
}

@keyframes highlight-fade {
    0% {
        background-color: rgba(240, 44, 86, 0.1);
    }

    100% {
        background-color: transparent;
    }
}

.highlighted-comment,
.highlighted-reply {
    animation:
        highlight-pulse 1.5s ease-out,
        highlight-fade 2s ease-out 1.5s forwards;
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin: -0.75rem;
    position: relative;
}

.highlighted-parent {
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin: -0.75rem;
    background-color: rgba(240, 44, 86, 0.05);
    border: 2px solid rgba(240, 44, 86, 0.2);
    position: relative;
}

/* Dark mode adjustments */
.dark .highlighted-comment,
.dark .highlighted-reply {
    animation:
        highlight-pulse-dark 1.5s ease-out,
        highlight-fade-dark 2s ease-out 1.5s forwards;
}

@keyframes highlight-pulse-dark {
    0% {
        background-color: rgba(240, 44, 86, 0.25);
        box-shadow: 0 0 0 0 rgba(240, 44, 86, 0.5);
    }

    50% {
        background-color: rgba(240, 44, 86, 0.35);
        box-shadow: 0 0 0 8px rgba(240, 44, 86, 0);
    }

    100% {
        background-color: rgba(240, 44, 86, 0.15);
        box-shadow: 0 0 0 0 rgba(240, 44, 86, 0);
    }
}

@keyframes highlight-fade-dark {
    0% {
        background-color: rgba(240, 44, 86, 0.15);
    }

    100% {
        background-color: transparent;
    }
}

.dark .highlighted-parent {
    background-color: rgba(240, 44, 86, 0.1);
    border-color: rgba(240, 44, 86, 0.3);
}

.highlighted-comment,
.highlighted-reply,
.highlighted-parent {
    scroll-margin-top: 1rem;
}
</style>
