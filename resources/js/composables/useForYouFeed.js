import { useInfiniteQuery } from '@tanstack/vue-query'
import { fetchForYouFeedPage } from '~/api/feed'

export const useForYouFeed = () => {
    return useInfiniteQuery({
        queryKey: ['fyfeed'],
        queryFn: ({ pageParam }) => fetchForYouFeedPage({ cursor: pageParam }),
        getNextPageParam: (lastPage) => lastPage.meta?.next_cursor ?? undefined,
        initialPageParam: null,
        enabled: true,
        staleTime: 0,
        retry: 2
    })
}
