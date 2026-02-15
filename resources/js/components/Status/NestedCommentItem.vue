<template>
    <div
        :class="[
            'flex space-x-3 pl-4 group/nested-comment',
            {
                'highlighted-reply': isHighlighted
            }
        ]"
    >
        <router-link :to="`/@${comment.account.username}`">
            <img
                :src="comment.account.avatar"
                :alt="comment.account.username"
                class="w-6 h-6 rounded-full flex-shrink-0"
                @error="$event.target.src = '/storage/avatars/default.jpg'"
            />
        </router-link>
        <div class="flex-1 min-w-0">
            <div class="mb-1">
                <router-link
                    :to="`/@${comment.account.username}`"
                    class="flex items-center space-x-1"
                >
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{
                        comment.account.name
                    }}</span>
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

            <div v-if="!comment.tombstone && isEditing" class="mb-2">
                <MentionHashtagInput
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
                        {{ editedCaption.length }} / {{ MAX_EDIT_CHAR_LIMIT }}
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
                            class="px-4 py-1.5 bg-[#F02C56] hover:bg-[#F02C56]/70 text-white text-sm font-medium rounded-lg disabled:opacity-50 transition-colors cursor-pointer flex items-center space-x-2"
                            :disabled="
                                isSavingEdit ||
                                !editedCaption.trim() ||
                                editedCaption.length > MAX_EDIT_CHAR_LIMIT
                            "
                        >
                            <Spinner v-if="isSavingEdit" size="xs" />
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
                        :root-class="
                            comment.tombstone
                                ? 'text-gray-500'
                                : 'text-gray-800 dark:text-slate-300 whitespace-pre-wrap leading-relaxed'
                        "
                        text-size="text-[16px]"
                        :max-char-limit="80"
                    />
                </p>
            </div>

            <div v-if="!comment.tombstone && !isEditing" class="flex items-center justify-between">
                <div class="flex items-center space-x-4 text-gray-500 text-sm">
                    <span class="flex items-center gap-1">{{
                        formatContentDate(comment.created_at)
                    }}</span>

                    <button
                        v-if="comment.is_edited"
                        class="cursor-pointer text-gray-400 hover:text-gray-500 flex items-center gap-1"
                        @click="openCommentReplyHistory(comment.v_id, comment.p_id, comment.id)"
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

                        {{ $t('common.edited') }}*
                    </button>
                </div>
            </div>
        </div>

        <div v-if="!comment.tombstone" class="relative flex flex-col">
            <div
                class="relative flex opacity-0 group-hover/nested-comment:opacity-100 transition-opacity duration-200"
            >
                <button
                    @click.stop="toggleDropdown"
                    class="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded"
                >
                    <i class="bx bx-dots-horizontal text-gray-400 text-lg"></i>
                </button>

                <div
                    v-show="showDropdown"
                    class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-900 rounded-lg shadow-lg z-10 border border-gray-200 dark:border-gray-700"
                    @click.stop
                >
                    <a
                        :href="comment.url"
                        class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                        target="_blank"
                    >
                        {{ $t('post.permalink') }}
                    </a>

                    <button
                        v-if="comment.is_owner"
                        @click="startEdit"
                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        {{ $t('common.edit') }}
                    </button>

                    <button
                        v-if="isVideoOwner"
                        class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-800"
                        @click="handleHideComment"
                    >
                        {{ $t('post.hideComment') }}
                    </button>

                    <button
                        v-if="comment.is_owner"
                        @click="handleDelete"
                        class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-800"
                        :disabled="isDeletingComment"
                    >
                        {{ isDeletingComment ? $t('post.deletingDotDotDot') : $t('post.delete') }}
                    </button>
                    <button
                        v-else
                        @click="handleReport"
                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        {{ $t('common.report') }}
                    </button>
                </div>
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
                    <i :class="heartIconClass" class="text-lg transition-colors"></i>
                    <span class="text-gray-400 dark:text-gray-100 text-xs">{{
                        comment.likes || 0
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, inject } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useCommentStore } from '@/stores/comments'
import { useUtils } from '@/composables/useUtils'
import { useAlertModal } from '@/composables/useAlertModal.js'
import { useReportModal } from '@/composables/useReportModal'
import { useEditHistory } from '@/composables/useEditHistory'
import MentionHashtagInput from '@/components/Status/MentionHashtagInput.vue'
import { useHideCommentModal } from '@/composables/useHideCommentModal'

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
        required: true
    },
    isHighlighted: {
        type: Boolean,
        default: false
    }
})

const MAX_CHAR_LIMIT = 100
const MAX_EDIT_CHAR_LIMIT = 500

const authStore = useAuthStore()
const commentStore = useCommentStore()
const appStore = inject('appStore')
const videoStore = inject('videoStore')
const { formatContentDate } = useUtils()
const { alertModal, confirmModal } = useAlertModal()
const { openReportModal } = useReportModal()
const { openCommentReplyHistory } = useEditHistory()
const { openHideCommentModal } = useHideCommentModal()

const showDropdown = ref(false)
const isDeletingComment = ref(false)

const isEditing = ref(false)
const editedCaption = ref('')
const isSavingEdit = ref(false)
const initialValidatedMentions = ref([])
const initialValidatedHashtags = ref([])

const activePost = computed(() => {
    return videoStore.currentVideo
})

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

const isVideoOwner = computed(() => {
    return videoStore.currentVideo.account.id === authStore.id
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

const startEdit = () => {
    isEditing.value = true
    editedCaption.value = props.comment.caption || ''
    showDropdown.value = false
    const mentionRegex = /@([\w._-]+(?:@[\w.-]+\.\w+)?)/g
    const mentions = [...props.comment.caption.matchAll(mentionRegex)].map((m) => m[1])
    initialValidatedMentions.value = mentions

    const hashtagRegex = /#([\w_]+)/g
    const hashtags = [...props.comment.caption.matchAll(hashtagRegex)].map((m) => m[1])
    initialValidatedHashtags.value = hashtags
}

const cancelEdit = async () => {
    const result = await confirmModal(
        'Confirm Cancel',
        `Are you sure you want to cancel editing this comment?`,
        'Yes',
        'No'
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

        await handleCaptionUpdate(
            props.videoId,
            props.parentCommentId,
            props.comment.id,
            editedCaption.value
        )

        isEditing.value = false
    } catch (error) {
        await alertModal(
            '⚠️ ' + t('common.somethingWentWrong'),
            error || `An unexpected error occured, please try again later.`
        )
        console.error('Failed to update reply:', error)
    } finally {
        isSavingEdit.value = false
    }
}

const handleCaptionUpdate = async (videoId, parentCommentId, commentId, newCaption) => {
    await commentStore.updateCommentReply(videoId, parentCommentId, commentId, newCaption)
    console.log('Update reply:', {
        videoId,
        parentCommentId,
        commentId,
        newCaption
    })
}

const handleDelete = async () => {
    if (isDeletingComment.value) return

    const result = await confirmModal(
        'Confirm',
        `Are you sure you want to delete this comment?`,
        'Delete',
        'Cancel'
    )

    if (!result) {
        showDropdown.value = false
        return
    }

    try {
        isDeletingComment.value = true
        showDropdown.value = false

        await commentStore.deleteCommentReply(
            props.videoId,
            props.parentCommentId,
            props.comment.id
        )
        await videoStore.decrementCommentCount()
    } catch (error) {
        console.error('Failed to delete reply:', error)
    } finally {
        isDeletingComment.value = false
    }
}

const handleReport = () => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }
    openReportModal('reply', props.comment.id, window.location.href)
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
            await commentStore.unlikeNestedComment(
                props.videoId,
                props.parentCommentId,
                props.comment.id
            )
        } else {
            await commentStore.likeNestedComment(
                props.videoId,
                props.parentCommentId,
                props.comment.id
            )
        }
    } catch (error) {
        console.error('Failed to toggle like on nested comment:', error)
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
/* Highlight Animation for Nested Reply */
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

.highlighted-reply {
    animation:
        highlight-pulse 1.5s ease-out,
        highlight-fade 2s ease-out 1.5s forwards;
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin: -0.75rem;
    margin-left: 0;
    position: relative;
}

/* Dark mode adjustments */
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

.highlighted-reply {
    scroll-margin-top: 1rem;
}
</style>
