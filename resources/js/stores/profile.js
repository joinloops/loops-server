import { defineStore } from 'pinia'
import { nextTick } from 'vue'
import axios from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'
import { useAlertModal } from '@/composables/useAlertModal.js'
const { confirmModal } = useAlertModal()

export const useProfileStore = defineStore('profile', {
    state: () => ({
        id: '',
        name: '',
        username: null,
        bio: '',
        avatar: '',
        post: null,
        posts: null,
        postsNextCursor: null,
        postsSort: null,
        postCount: 0,
        followerCount: 0,
        followingCount: 0,
        followers: [],
        following: [],
        followersNextCursor: null,
        followingNextCursor: null,
        isFollowing: null,
        isFollowingRequestPending: null,
        isSelf: null,
        isBlocking: null,
        relationship: {
            blocking: false
        },
        url: null,
        allLikes: 0,
        isLoadingMorePosts: false,
        hasMorePosts: true,
        isPollingFollowState: false
    }),
    actions: {
        async updateSort(type) {
            this.isLoadingMorePosts = true
            this.hasMorePosts = false
            this.postsSort = type
            await this.getProfilePosts(this.id)
        },

        async getProfile(id) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.resetUser()
                const res = await axiosInstance.get(`/api/v1/account/username/${id}?ext=1`)

                this.id = res.data.data.id
                this.username = res.data.data.username
                this.name = res.data.data.name
                this.bio = res.data.data.bio
                this.avatar = res.data.data.avatar
                this.url = res.data.data.url
                this.postCount = res.data.data.post_count
                this.followerCount = res.data.data.follower_count
                this.followingCount = res.data.data.following_count
                this.allLikes = res.data.data.likes_count
                this.posts = []
            } catch (error) {
                console.error('Error fetching profile:', error)
                throw error
            }
        },

        async getProfilePartialFollowStats(id) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.get(`/api/v1/account/info/${id}`)

                this.followerCount = res.data.data.follower_count
                this.followingCount = res.data.data.following_count
            } catch (error) {
                console.error('Error fetching profile:', error)
                throw error
            }
        },

        async getProfilePosts(id) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const userPosts = await axiosInstance.get(`/api/v1/feed/account/${id}`, {
                    params: {
                        sort: this.postsSort
                    }
                })
                this.postsNextCursor = userPosts.data.meta?.next_cursor
                this.posts = userPosts.data.data
                this.hasMorePosts = !!this.postsNextCursor
            } catch (error) {
                console.error('Error fetching profile:', error)
                throw error
            }
        },

        async loadMorePosts(id) {
            if (this.isLoadingMorePosts || !this.postsNextCursor) {
                return
            }

            const axiosInstance = axios.getAxiosInstance()
            try {
                this.isLoadingMorePosts = true

                const userPosts = await axiosInstance.get(`/api/v1/feed/account/${id}`, {
                    params: {
                        sort: this.postsSort,
                        cursor: this.postsNextCursor
                    }
                })

                this.posts = [...this.posts, ...userPosts.data.data]
                this.postsNextCursor = userPosts.data.meta?.next_cursor
                this.hasMorePosts = !!this.postsNextCursor
            } catch (error) {
                console.error('Error loading more posts:', error)
                throw error
            } finally {
                this.isLoadingMorePosts = false
            }
        },

        async getProfileState(id) {
            if (this.id !== id) {
                this.resetUser()
            }
            const axiosInstance = axios.getAxiosInstance()
            try {
                const relationship = await axiosInstance.get(`/api/v1/account/state/${id}`)
                this.id = id
                this.relationship = relationship.data.data
                this.isFollowingRequestPending = relationship.data.data.pending_follow_request
                this.isFollowing = relationship.data.data.following
                this.isBlocking = relationship.data.data.blocking
            } catch (error) {
                console.error('Error fetching profile:', error)
                throw error
            }
        },

        async getProfileAndPosts(id) {
            const axiosInstance = axios.getAxiosInstance()
            const authStore = useAuthStore()

            try {
                this.resetUser()
                const res = await axiosInstance.get(`/api/v1/account/username/${id}?ext=1`)

                if (authStore.authenticated) {
                    await this.getProfileState(res.data.data.id)
                }

                if (!this.isBlocking) {
                    await this.getProfilePosts(res.data.data.id)
                }

                this.id = res.data.data.id
                this.username = res.data.data.username
                this.name = res.data.data.name
                this.bio = res.data.data.bio
                this.avatar = res.data.data.avatar
                this.url = res.data.data.url
                this.postCount = res.data.data.post_count
                this.followerCount = res.data.data.follower_count
                this.followingCount = res.data.data.following_count
                this.allLikes = res.data.data.likes_count
                this.isSelf = res.data.data.is_owner
            } catch (error) {
                console.error('Error fetching profile:', error)
                throw error
            }
        },

        async getFollowers(id, cursor = null) {
            const authStore = useAuthStore()
            const axiosInstance = axios.getAxiosInstance()

            const baseUrl = authStore.isAuthenticated
                ? `/api/v1/account/followers/${id}`
                : `/api/v1/web/account/followers/${id}`

            try {
                const response = await axiosInstance.get(baseUrl, {
                    params: cursor ? { cursor } : {}
                })
                if (!cursor) {
                    this.followers = response.data.data
                } else {
                    this.followers = [...this.followers, ...response.data.data]
                }

                this.followersNextCursor = response.data.meta.next_cursor

                return response.data
            } catch (error) {
                console.error('Error fetching profile:', error)
                throw error
            }
        },

        async getFollowing(id, cursor = null) {
            const authStore = useAuthStore()
            const axiosInstance = axios.getAxiosInstance()

            const baseUrl = authStore.isAuthenticated
                ? `/api/v1/account/following/${id}`
                : `/api/v1/web/account/following/${id}`
            try {
                const response = await axiosInstance.get(baseUrl, {
                    params: cursor ? { cursor } : {}
                })
                if (!cursor) {
                    this.following = response.data.data
                } else {
                    this.following = [...this.following, ...response.data.data]
                }

                this.followingNextCursor = response.data.meta.next_cursor

                return response.data
            } catch (error) {
                console.error('Error fetching profile:', error)
                throw error
            }
        },

        async follow() {
            await this.toggleFollow()
        },

        async unfollow() {
            const msg = this.username ?? 'this account'
            const res = await confirmModal(
                'Confirm Unfollow',
                `Are you sure you want to unfollow ${msg}?`,
                'Unfollow',
                'Cancel'
            )

            if (!res) {
                return
            }

            await this.toggleFollow()
        },

        resetFollowLists() {
            this.followers = []
            this.following = []
            this.followersNextCursor = null
            this.followingNextCursor = null
        },

        async toggleFollow() {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const prevState = this.isFollowing
                const url = this.isFollowing
                    ? `/api/v1/account/unfollow/${this.id}`
                    : `/api/v1/account/follow/${this.id}`
                const response = await axiosInstance.post(url)
                const res = response.data.data
                this.id = res.id
                this.username = res.username
                this.name = res.name
                this.bio = res.bio
                this.avatar = res.avatar
                this.url = res.url
                this.postCount = res.post_count
                this.allLikes = res.likes_count
                this.followingCount = res.following_count
                this.followerCount = res.follower_count
                await nextTick()
                await this.getProfileState(this.id)
                if (!prevState && this.isFollowingRequestPending) {
                    this.pollFollowRequestState().catch((err) => {
                        console.error('Error polling follow request state:', err)
                    })
                }
            } catch (error) {
                console.error('Error toggling follow:', error)
            }
        },

        async undoFollowRequest() {
            const axiosInstance = axios.getAxiosInstance()

            try {
                await axiosInstance
                    .post(`/api/v1/account/undo-follow-request/${this.id}`)
                    .finally(async () => {
                        await nextTick()
                        await this.getProfileState(this.id)
                    })
            } catch (error) {
                console.error('Error toggling undoFollowRequest:', error)
            }
        },

        async blockAccount() {
            const axiosInstance = axios.getAxiosInstance()
            const pid = this.username
            try {
                await axiosInstance.post(`/api/v1/account/block/${this.id}`).finally(async () => {
                    await nextTick()
                    await this.getProfileAndPosts(pid)
                })
            } catch (error) {
                console.error('Error toggling follow:', error)
            }
        },

        async unblockAccount() {
            const axiosInstance = axios.getAxiosInstance()
            const pid = this.username
            try {
                await axiosInstance
                    .post(`/api/v1/account/unblock/${this.id}`)
                    .then(() => {
                        this.relationship.blocking = false
                        this.isBlocking = false
                    })
                    .finally(async () => {
                        await nextTick()
                        await this.getProfileAndPosts(pid)
                    })
            } catch (error) {
                console.error('Error toggling follow:', error)
            }
        },

        async pollFollowRequestState(maxAttempts = 6, initialDelay = 1000) {
            if (this.isPollingFollowState) {
                return
            }

            this.isPollingFollowState = true
            let attempt = 0

            try {
                while (attempt < maxAttempts) {
                    if (!this.isFollowingRequestPending) {
                        return
                    }

                    const delay = initialDelay * Math.pow(2, attempt)

                    console.log(
                        `Polling follow request state (attempt ${attempt + 1}/${maxAttempts}, waiting ${delay}ms)`
                    )

                    await new Promise((resolve) => setTimeout(resolve, delay))

                    await this.getProfileState(this.id)
                    await this.getProfilePartialFollowStats(this.id)

                    if (this.isFollowing && !this.isFollowingRequestPending) {
                        console.log('Follow request accepted!')
                        return
                    }

                    attempt++
                }

                console.log('Max polling attempts reached, follow request still pending')
            } finally {
                this.isPollingFollowState = false
            }
        },

        resetUser() {
            this.id = ''
            this.name = ''
            this.username = null
            this.bio = ''
            this.avatar = ''
            this.url = ''
            this.postCount = 0
            this.followingCount = 0
            this.followerCount = 0
            this.posts = null
            this.postsNextCursor = null
            this.postsSort = null
            this.followers = []
            this.following = []
            this.followersNextCursor = []
            this.followingNextCursor = []
            this.isSelf = null
            this.isFollowingRequestPending = null
            this.relationship = {
                blocking: false
            }
            this.isFollowing = null
            this.isBlocking = null
            this.allLikes = 0
            this.isLoadingMorePosts = false
            this.hasMorePosts = true
            this.isPollingFollowState = false
        }
    },
    persist: true
})
