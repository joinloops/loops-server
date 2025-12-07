import { useRouter, useRoute } from 'vue-router'
import { useHashids } from '@/composables/useHashids'

export const useCommentDeepLink = () => {
    const router = useRouter()
    const route = useRoute()
    const { encodeHashid } = useHashids()

    /**
     * Create a deep link URL for a comment
     * @param {string} videoHashId - The encoded video ID
     * @param {string|number} commentId - The comment ID (will be encoded)
     * @param {boolean} isReply - Whether this is a reply or top-level comment
     * @returns {string} The full URL with query parameters
     */
    const createCommentLink = (videoHashId, commentId, isReply = false) => {
        const encodedCommentId = encodeHashid(commentId)
        const param = isReply ? 'rid' : 'cid'

        const baseUrl = window.location.origin
        const path = `/v/${videoHashId}`
        return `${baseUrl}${path}?${param}=${encodedCommentId}`
    }

    /**
     * Navigate to a comment deep link
     * @param {string} videoHashId - The encoded video ID
     * @param {string|number} commentId - The comment ID
     * @param {boolean} isReply - Whether this is a reply
     */
    const navigateToComment = async (videoHashId, commentId, isReply = false) => {
        const encodedCommentId = encodeHashid(commentId)
        const param = isReply ? 'rid' : 'cid'

        await router.push({
            path: `/v/${videoHashId}`,
            query: { [param]: encodedCommentId }
        })
    }

    /**
     * Copy comment link to clipboard
     * @param {string} videoHashId - The encoded video ID
     * @param {string|number} commentId - The comment ID
     * @param {boolean} isReply - Whether this is a reply
     * @returns {Promise<boolean>} Success status
     */
    const copyCommentLink = async (videoHashId, commentId, isReply = false) => {
        const link = createCommentLink(videoHashId, commentId, isReply)

        try {
            await navigator.clipboard.writeText(link)
            return true
        } catch (err) {
            console.error('Failed to copy link:', err)
            // Fallback method
            const textArea = document.createElement('textarea')
            textArea.value = link
            document.body.appendChild(textArea)
            textArea.select()
            const success = document.execCommand('copy')
            document.body.removeChild(textArea)
            return success
        }
    }

    /**
     * Share comment link using Web Share API
     * @param {string} videoHashId - The encoded video ID
     * @param {string|number} commentId - The comment ID
     * @param {boolean} isReply - Whether this is a reply
     * @param {Object} options - Share options (title, text)
     */
    const shareCommentLink = async (videoHashId, commentId, isReply = false, options = {}) => {
        const link = createCommentLink(videoHashId, commentId, isReply)

        if (!navigator.canShare) {
            // Fallback to copy
            return copyCommentLink(videoHashId, commentId, isReply)
        }

        const shareData = {
            title: options.title || 'Loops Comment',
            text: options.text || 'Check out this comment',
            url: link
        }

        if (!navigator.canShare(shareData)) {
            return copyCommentLink(videoHashId, commentId, isReply)
        }

        try {
            await navigator.share(shareData)
            return true
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('Error sharing:', err)
            }
            return false
        }
    }

    /**
     * Get current comment query params
     * @returns {Object} { cid, rid, hasHighlight }
     */
    const getCurrentCommentQuery = () => {
        const { cid, rid } = route.query
        return {
            cid,
            rid,
            hasHighlight: !!(cid || rid)
        }
    }

    return {
        createCommentLink,
        navigateToComment,
        copyCommentLink,
        shareCommentLink,
        getCurrentCommentQuery
    }
}
