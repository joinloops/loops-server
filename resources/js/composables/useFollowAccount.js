import { useMutation, useQueryClient } from '@tanstack/vue-query'
import axios from '~/plugins/axios'

export function useFollowAccount() {
    const queryClient = useQueryClient()
    const axiosInstance = axios.getAxiosInstance()

    const followMutation = useMutation({
        mutationFn: async (accountId) => {
            const response = await axiosInstance.post(`/api/v1/account/follow/${accountId}`)
            return response.data
        },
        onSuccess: (data, accountId) => {
            queryClient.setQueryData(['suggested-accounts'], (oldData) => {
                if (!oldData) return oldData

                return {
                    ...oldData,
                    data: oldData.data.map((account) =>
                        account.id === accountId
                            ? {
                                  ...account,
                                  is_following: true,
                                  follower_count: (account.follower_count || 0) + 1
                              }
                            : account
                    )
                }
            })

            // Invalidate following feed so it includes new follows
            queryClient.invalidateQueries({ queryKey: ['following-feed'] })
            // Optionally update user's following count
            queryClient.invalidateQueries({ queryKey: ['user-profile'] })
        }
    })

    const unfollowMutation = useMutation({
        mutationFn: async (accountId) => {
            const response = await axiosInstance.post(`/api/v1/account/unfollow/${accountId}`)
            return response.data
        },
        onSuccess: (data, accountId) => {
            queryClient.setQueryData(['suggested-accounts'], (oldData) => {
                if (!oldData) return oldData

                return {
                    ...oldData,
                    data: oldData.data.map((account) =>
                        account.id === accountId
                            ? {
                                  ...account,
                                  is_following: false,
                                  followers_count: Math.max((account.followers_count || 1) - 1, 0)
                              }
                            : account
                    )
                }
            })

            // Invalidate following feed so it excludes unfollowed accounts
            queryClient.invalidateQueries({ queryKey: ['following-feed'] })
            // Optionally update user's following count
            queryClient.invalidateQueries({ queryKey: ['user-profile'] })
        }
    })

    const followAccount = async (accountId) => {
        return followMutation.mutateAsync(accountId)
    }

    const unfollowAccount = async (accountId) => {
        return unfollowMutation.mutateAsync(accountId)
    }

    return {
        followAccount,
        unfollowAccount,
        isFollowLoading: followMutation.isPending,
        isUnfollowLoading: unfollowMutation.isPending,
        followError: followMutation.error,
        unfollowError: unfollowMutation.error
    }
}
