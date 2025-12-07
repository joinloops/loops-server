import { useInfiniteQuery } from '@tanstack/vue-query'
import { computed } from 'vue'
import { fetchPublicFeedPage } from '~/api/publicFeed'

export const usePublicFeed = () => {
    return useInfiniteQuery({
        queryKey: ['publicFeed'],
        queryFn: ({ pageParam }) => fetchPublicFeedPage({ cursor: pageParam }),
        getNextPageParam: (lastPage) => lastPage.meta?.next_cursor ?? undefined,
        initialPageParam: null,
        enabled: true,
        staleTime: 1000 * 60 * 5, // 5 minutes
        retry: 2
    })
}
