import { useQuery } from '@tanstack/vue-query'
import axios from '~/plugins/axios'

export function useSuggestedAccounts(options = {}) {
    return useQuery({
        queryKey: ['suggested-accounts'],
        queryFn: async () => {
            const axiosInstance = axios.getAxiosInstance()
            const response = await axiosInstance.get('/api/v1/accounts/suggested', {
                params: {
                    limit: options.limit ?? 10,
                    ...options.params
                }
            })
            return response.data
        },
        enabled: options.enabled ?? true,
        staleTime: 5 * 60 * 1000,
        cacheTime: 10 * 60 * 1000,
        retry: 2,
        ...options
    })
}
