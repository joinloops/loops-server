import { defineStore } from 'pinia'
import axios from '~/plugins/axios'
import { useQueryClient } from '@tanstack/vue-query'

export const useVideoStore = defineStore('video', {
    state: () => ({
        currentVideo: null,
        likesNextCursor: null,
        likesHasMore: false,
        sharesNextCursor: null,
        sharesHasMore: false,
        error: null
    }),

    actions: {
        async setVideo(data) {
            this.currentVideo = data
        },

        async getVideoById(id) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.get(`/api/v1/video/${id}`)
                this.currentVideo = res.data.data
            } catch (error) {
                console.error('Error fetching video:', error)
                throw error
            }
        },

        async deleteVideoById(id) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.post(`/api/v1/video/delete/${id}`)
                this.currentVideo = null
            } catch (error) {
                console.error('Error fetching video:', error)
                throw error
            }
        },

        async updateVideoStore({
            caption,
            can_download,
            can_comment,
            is_pinned,
            alt_text,
            can_duet,
            can_stitch,
            is_sensitive,
            contains_ad,
            contains_ai,
            lang
        }) {
            const axiosInstance = axios.getAxiosInstance()

            const res = await axiosInstance.post('/api/v1/video/edit/' + this.currentVideo.id, {
                caption,
                is_pinned,
                can_download,
                can_comment,
                alt_text,
                can_duet,
                can_stitch,
                is_sensitive,
                contains_ad,
                contains_ai,
                lang
            })

            this.currentVideo = res.data
        },

        async decrementCommentCount() {
            this.currentVideo = {
                ...this.currentVideo,
                comments: Math.max((this.currentVideo.comments || 0) - 1, 0)
            }
        },

        async incrementCommentCount() {
            this.currentVideo = {
                ...this.currentVideo,
                comments: (this.currentVideo.comments || 0) + 1
            }
        },

        async likeVideo(videoId) {
            const axiosInstance = axios.getAxiosInstance()

            try {
                const uri = `/api/v1/video/like/${videoId}`
                return await axiosInstance.post(uri).then((res) => {
                    this.currentVideo.has_liked = res.data.has_liked
                    this.currentVideo.likes = res.data.likes
                    return res
                })
            } catch (error) {
                console.error('Error liking post:', error)
                throw error
            }
        },

        async unlikeVideo(videoId) {
            const axiosInstance = axios.getAxiosInstance()

            try {
                const uri = `/api/v1/video/unlike/${videoId}`
                return await axiosInstance.post(uri).then((res) => {
                    this.currentVideo.has_liked = res.data.has_liked
                    this.currentVideo.likes = res.data.likes
                    return res
                })
            } catch (error) {
                console.error('Error liking post:', error)
                throw error
            }
        },

        async bookmarkVideo(videoId) {
            const axiosInstance = axios.getAxiosInstance()

            try {
                const uri = `/api/v1/video/bookmark/${videoId}`
                return await axiosInstance.post(uri).then((res) => {
                    this.currentVideo.has_bookmarked = res.data.has_bookmarked
                    this.currentVideo.bookmarks = res.data.bookmarks
                    return res
                })
            } catch (error) {
                console.error('Error bookmarking post:', error)
                throw error
            }
        },

        async unbookmarkVideo(videoId) {
            const axiosInstance = axios.getAxiosInstance()

            try {
                const uri = `/api/v1/video/unbookmark/${videoId}`
                return await axiosInstance.post(uri).then((res) => {
                    this.currentVideo.has_bookmarked = res.data.has_bookmarked
                    this.currentVideo.bookmarks = res.data.bookmarks
                    return res
                })
            } catch (error) {
                console.error('Error bookmarking post:', error)
                throw error
            }
        },

        async autocompleteHashtag(tag) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.get(`/api/v1/autocomplete/tags?q=${tag}`)
                return res.data
            } catch (error) {
                console.error('Error fetching autocomplete:', error)
                throw error
            }
        },

        async autocompleteAccount(tag) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.get(`/api/v1/autocomplete/accounts?q=${tag}`)
                return res.data
            } catch (error) {
                console.error('Error fetching autocomplete:', error)
                throw error
            }
        },

        async getVideoLikes(videoId, cursor = null) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.get(`/api/v1/video/likes/${videoId}`, {
                    params: this.likesNextCursor ? { cursor: this.likesNextCursor } : {}
                })
                this.likesHasMore = res?.data?.meta?.next_cursor != null
                this.likesNextCursor = res?.data?.meta?.next_cursor
                return res.data
            } catch (error) {
                console.error('Error fetching video likes:', error)
                throw error
            }
        },

        async getVideoShares(videoId, page = 1) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.get(`/api/v1/video/shares/${videoId}`, {
                    params: this.sharesNextCursor ? { cursor: this.sharesNextCursor } : {}
                })
                this.sharesHasMore = res?.data?.meta?.next_cursor != null
                this.sharesNextCursor = res?.data?.meta?.next_cursor
                return res.data
            } catch (error) {
                console.error('Error fetching video shares:', error)
                throw error
            }
        },

        async followUser(userId) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.post(`/api/v1/account/follow/${userId}`)
                try {
                    const queryClient = useQueryClient()
                    queryClient.invalidateQueries({ queryKey: ['following-feed'] })
                } catch (e) {}
                return res.data
            } catch (error) {
                console.error('Error following user:', error)
                throw error
            }
        },

        async unfollowUser(userId) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                const res = await axiosInstance.post(`/api/v1/account/unfollow/${userId}`)
                try {
                    const queryClient = useQueryClient()
                    queryClient.invalidateQueries({ queryKey: ['following-feed'] })
                } catch (e) {}
                return res.data
            } catch (error) {
                console.error('Error unfollowing user:', error)
                throw error
            }
        }
    },

    getters: {
        video: (state) => state.currentVideo
    }
})
