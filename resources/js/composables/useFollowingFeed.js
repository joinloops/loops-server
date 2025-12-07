import { useInfiniteQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { fetchFollowingFeedPage } from '~/api/feed'

export function useFollowingFeed() {
    const query = useInfiniteQuery({
        queryKey: ['following-feed'],
        queryFn: async ({ pageParam }) => {
            try {
                const result = await fetchFollowingFeedPage({
                    cursor: pageParam
                })
                return result
            } catch (error) {
                console.error('Error fetching following feed:', error)
                throw error
            }
        },
        getNextPageParam: (lastPage) => {
            const nextCursor = lastPage?.meta?.next_cursor ?? undefined
            return nextCursor
        },
        initialPageParam: null,
        staleTime: 2 * 60 * 1000, // 2 minutes
        cacheTime: 5 * 60 * 1000, // 5 minutes
        retry: 2,
        refetchOnWindowFocus: true
    })

    const feedData = computed(() => query)

    const allPosts = computed(() => {
        const posts = query.data.value?.pages?.flatMap((page) => page?.data || []) || []
        return posts
    })

    const isEmpty = computed(() => {
        const hasData = query.data.value?.pages && query.data.value.pages.length > 0
        const hasNoPosts = allPosts.value.length === 0
        const isNotLoading = !query.isLoading.value && !query.isFetching.value
        const isSuccess = query.status.value === 'success'

        return hasData && hasNoPosts && isNotLoading && isSuccess
    })

    const isLoading = computed(() => {
        return query.isLoading.value
    })

    const isLoadingMore = computed(() => {
        return query.isFetchingNextPage.value
    })

    const hasNextPage = computed(() => {
        return query.hasNextPage.value
    })

    const error = computed(() => {
        return query.error.value
    })

    const loadMore = () => {
        if (hasNextPage.value && !isLoadingMore.value) {
            query.fetchNextPage()
        }
    }

    const refresh = () => {
        return query.refetch()
    }

    const reset = () => {
        query.remove()
    }

    return {
        data: feedData,
        pages: computed(() => query.data.value?.pages || []),
        allPosts,
        isEmpty,
        isLoading,
        isLoadingMore,
        hasNextPage,
        error,
        loadMore,
        refresh,
        reset,
        refetch: refresh
    }
}
