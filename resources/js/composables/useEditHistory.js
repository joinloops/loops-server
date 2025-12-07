import { storeToRefs } from 'pinia'
import { computed } from 'vue'
import { useEditHistoryStore } from '@/stores/editHistory'

export function useEditHistory() {
    const store = useEditHistoryStore()
    const { isModalOpen, currentEntity } = storeToRefs(store)

    return {
        isModalOpen,
        currentEntity,
        openVideoHistory: store.openVideoHistory,
        openCommentHistory: store.openCommentHistory,
        openCommentReplyHistory: store.openCommentReplyHistory,
        closeModal: store.closeModal,
        loadMore: store.loadMore,
        getHistory: () => store.getCurrentHistory,
        isLoading: () => store.isCurrentLoading,
        hasError: () => store.getCurrentError,
        hasMore: () => store.hasMoreCurrent
    }
}
