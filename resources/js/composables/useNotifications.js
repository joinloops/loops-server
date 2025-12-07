import { useInfiniteQuery } from '@tanstack/vue-query'
import { ref } from 'vue'

export function useNotifications() {
    const fetchNotifications = async ({ pageParam = null }) => {
        const url = pageParam || '/api/v1/account/notifications'
        const response = await fetch(url)
        if (!response.ok) throw new Error('Network response was not ok')
        return response.json()
    }

    const { data, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading, isError, error } =
        useInfiniteQuery({
            queryKey: ['notifications'],
            queryFn: fetchNotifications,
            getNextPageParam: (lastPage) => lastPage.links.next || undefined,
            staleTime: 1000 * 60 // 1 minute
        })

    return {
        data,
        fetchNextPage,
        hasNextPage,
        isFetchingNextPage,
        isLoading,
        isError,
        error
    }
}
