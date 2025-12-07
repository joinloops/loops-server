<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="closeModal"
            >
                <div
                    class="relative w-full max-w-2xl max-h-[90vh] bg-white dark:bg-gray-900 rounded-lg shadow-xl flex flex-col"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="currentEntity?.title"
                >
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                    >
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ currentEntity?.title || 'Edit History' }}
                        </h2>
                        <button
                            @click="closeModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            aria-label="Close modal"
                        >
                            <svg
                                class="w-6 h-6"
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

                    <div ref="scrollContainer" class="flex-1 overflow-y-auto px-6 py-4">
                        <div v-if="isInitialLoad" class="flex items-center justify-center py-12">
                            <Spinner />
                        </div>

                        <div
                            v-else-if="error"
                            class="flex flex-col items-center justify-center py-12"
                        >
                            <svg
                                class="w-12 h-12 text-red-500 mb-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <p class="text-red-600 dark:text-red-400">
                                {{ error }}
                            </p>
                        </div>

                        <div
                            v-else-if="history.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400"
                        >
                            <svg
                                class="w-12 h-12 mb-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <p>No edit history available</p>
                        </div>

                        <div v-else class="space-y-6">
                            <div
                                v-for="(entry, index) in history"
                                :key="entry.id || index"
                                class="relative"
                            >
                                <div
                                    v-if="index < history.length - 1"
                                    class="absolute left-4 top-10 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"
                                ></div>

                                <div class="flex gap-4">
                                    <div class="relative flex-shrink-0">
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center"
                                            :class="
                                                index === 0
                                                    ? 'bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400'
                                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                                            "
                                        >
                                            <svg
                                                v-if="index === 0"
                                                class="w-4 h-4"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                class="w-4 h-4"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="flex-1 pb-6">
                                        <div
                                            class="dark:bg-gray-800 rounded-lg p-2 border border-gray-200 dark:border-gray-700"
                                        >
                                            <div
                                                v-if="
                                                    index === 0 ||
                                                    (index === history.length - 1 && !hasMore)
                                                "
                                                class="flex items-center justify-between mb-3"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        v-if="index === 0"
                                                        class="px-2 py-1 text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded"
                                                    >
                                                        Current
                                                    </span>
                                                    <span
                                                        v-else-if="
                                                            index === history.length - 1 && !hasMore
                                                        "
                                                        class="px-2 py-1 text-xs font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded"
                                                    >
                                                        Original
                                                    </span>
                                                </div>
                                            </div>

                                            <div
                                                class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap break-words"
                                            >
                                                {{
                                                    entry.content ||
                                                    entry.caption ||
                                                    entry.description ||
                                                    entry.title ||
                                                    '(No content)'
                                                }}
                                            </div>
                                        </div>
                                        <div
                                            class="flex gap-1 items-center mt-1 text-xs text-gray-400 dark:text-gray-500"
                                        >
                                            <ClockIcon class="w-3 h-3" />
                                            Updated
                                            {{ formatDate(entry.updated_at) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="hasMore"
                                ref="loadMoreSection"
                                class="flex justify-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700"
                            >
                                <button
                                    @click="handleLoadMore"
                                    :disabled="isLoadingMore"
                                    class="px-6 py-2.5 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 cursor-pointer"
                                >
                                    <svg
                                        v-if="isLoadingMore"
                                        class="animate-spin h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        ></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>
                                    <span>{{ isLoadingMore ? 'Loading...' : 'Load More' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end px-6 py-4 border-t border-gray-200 dark:border-gray-700"
                    >
                        <button
                            @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, ref, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { useEditHistoryStore } from '@/stores/editHistory'
import { ClockIcon } from '@heroicons/vue/24/outline'

const store = useEditHistoryStore()
const { isModalOpen, currentEntity } = storeToRefs(store)

const scrollContainer = ref(null)
const loadMoreSection = ref(null)
const isLoadingMore = ref(false)

const closeModal = () => store.closeModal()

const history = computed(() => store.getCurrentHistory)
const loading = computed(() => store.isCurrentLoading)
const error = computed(() => store.getCurrentError)
const hasMore = computed(() => store.hasMoreCurrent)

const isInitialLoad = computed(() => loading.value && history.value.length === 0)

const handleLoadMore = async () => {
    if (isLoadingMore.value) return

    const currentScrollHeight = scrollContainer.value?.scrollHeight || 0
    const currentScrollTop = scrollContainer.value?.scrollTop || 0

    isLoadingMore.value = true

    try {
        await store.loadMore()

        await nextTick()

        if (scrollContainer.value && loadMoreSection.value) {
            const newScrollHeight = scrollContainer.value.scrollHeight
            const heightDifference = newScrollHeight - currentScrollHeight

            scrollContainer.value.scrollTo({
                top: currentScrollTop + heightDifference,
                behavior: 'smooth'
            })
        }
    } catch (err) {
        console.error('Failed to load more history:', err)
    } finally {
        isLoadingMore.value = false
    }
}

const formatDate = (dateString) => {
    if (!dateString) return ''

    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now - date
    const diffMins = Math.floor(diffMs / 60000)
    const diffHours = Math.floor(diffMs / 3600000)
    const diffDays = Math.floor(diffMs / 86400000)

    if (diffMins < 1) return 'Just now'
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}
</script>
