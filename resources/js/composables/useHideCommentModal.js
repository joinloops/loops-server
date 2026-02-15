import { ref } from 'vue'
import { useCommentStore } from '@/stores/comments'

const isOpen = ref(false)
const modeHide = ref(true)
const currentVideoId = ref(null)
const currentCommentId = ref(null)
const currentParentId = ref(null)
const resolvePromise = ref(null)

export function useHideCommentModal() {
    const commentStore = useCommentStore()

    const openHideCommentModal = (videoId, commentId, parentCommentId = null) => {
        return new Promise((resolve) => {
            isOpen.value = true
            modeHide.value = true
            currentVideoId.value = videoId
            currentCommentId.value = commentId
            currentParentId.value = parentCommentId
            resolvePromise.value = resolve
        })
    }

    const openUnhideCommentModal = (videoId, commentId, parentCommentId = null) => {
        return new Promise((resolve) => {
            isOpen.value = true
            modeHide.value = false
            currentVideoId.value = videoId
            currentCommentId.value = commentId
            currentParentId.value = parentCommentId
            resolvePromise.value = resolve
        })
    }

    const confirmHide = async () => {
        try {
            if (currentParentId.value) {
                await commentStore.hideCommentReply(
                    currentVideoId.value,
                    currentParentId.value,
                    currentCommentId.value
                )
            } else {
                await commentStore.hideComment(currentVideoId.value, currentCommentId.value)
            }

            closeModal()
            resolvePromise.value?.(true)
        } catch (error) {
            console.error('Error hiding comment:', error)
            closeModal()
            resolvePromise.value?.(false)
        }
    }

    const confirmUnhide = async () => {
        try {
            if (currentParentId.value) {
                await commentStore.unhideCommentReply(
                    currentVideoId.value,
                    currentParentId.value,
                    currentCommentId.value
                )
            } else {
                await commentStore.unhideComment(currentVideoId.value, currentCommentId.value)
            }

            closeModal()
            resolvePromise.value?.(true)
        } catch (error) {
            console.error('Error hiding comment:', error)
            closeModal()
            resolvePromise.value?.(false)
        }
    }

    const closeModal = () => {
        isOpen.value = false
        modeHide.value = true
        resolvePromise.value?.(false)

        setTimeout(() => {
            currentVideoId.value = null
            currentCommentId.value = null
            currentParentId.value = null
            resolvePromise.value = null
        }, 300)
    }

    return {
        openHideCommentModal,
        openUnhideCommentModal,
        confirmHide,
        confirmUnhide,
        closeModal,
        isOpen,
        modeHide,
        currentVideoId,
        currentCommentId,
        currentParentId
    }
}
