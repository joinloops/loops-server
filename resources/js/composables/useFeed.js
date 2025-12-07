import { useInfiniteQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { fetchFeedPage } from '~/api/feed'

export const useFeed = () => {
    return useInfiniteQuery({
        queryKey: ['feed'],
        queryFn: ({ pageParam }) => fetchFeedPage({ cursor: pageParam }),
        getNextPageParam: (lastPage) => lastPage.meta?.next_cursor ?? undefined,
        initialPageParam: null,
        enabled: true,
        staleTime: 1000 * 60 * 5, // 5 minutes
        retry: 2
    })
}
