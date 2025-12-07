import { defineStore } from 'pinia'
import axios from 'axios'

export const usePlaylistStore = defineStore('playlist', {
    state: () => ({
        playlists: [],
        playlist: null,
        playlistVideos: [],
        videosPagination: {
            nextCursor: null,
            hasMore: false
        },
        availableVideos: [],
        availableVideosPagination: {
            nextCursor: null,
            hasMore: false
        },
        loading: false,
        error: null
    }),

    actions: {
        async fetchPlaylists(params = {}) {
            this.loading = true
            this.error = null

            try {
                const response = await axios.get('/api/v1/studio/playlists', { params })
                this.playlists = response.data.data
                return response.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch playlists'
                throw error
            } finally {
                this.loading = false
            }
        },

        async getPlaylistById(id) {
            this.loading = true
            this.error = null

            try {
                const response = await axios.get(`/api/v1/studio/playlists/${id}`)
                this.playlist = response.data.data
                return response.data.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch playlist'
                throw error
            } finally {
                this.loading = false
            }
        },

        async fetchPlaylistVideos(playlistId, cursor = null, limit = 10) {
            this.loading = true
            this.error = null

            try {
                const response = await axios.get(`/api/v1/studio/playlists/${playlistId}/videos`, {
                    params: { cursor, limit }
                })

                if (cursor) {
                    // Append to existing videos (load more)
                    this.playlistVideos = [...this.playlistVideos, ...response.data.data]
                } else {
                    // Replace videos (initial load)
                    this.playlistVideos = response.data.data
                }

                this.videosPagination = {
                    nextCursor: response.data.meta?.next_cursor,
                    hasMore: !!response.data.meta?.next_cursor
                }

                return response.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch playlist videos'
                throw error
            } finally {
                this.loading = false
            }
        },

        async createPlaylist(data) {
            this.loading = true
            this.error = null

            try {
                const response = await axios.post('/api/v1/studio/playlists', data)
                this.playlists.unshift(response.data.data)
                return response.data.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to create playlist'
                throw error
            } finally {
                this.loading = false
            }
        },

        async updatePlaylist(id, data) {
            this.loading = true
            this.error = null

            try {
                const response = await axios.put(`/api/v1/studio/playlists/${id}`, data)
                const index = this.playlists.findIndex((p) => p.id === id)
                if (index !== -1) {
                    this.playlists[index] = response.data.data
                }
                this.playlist = response.data.data
                return response.data.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to update playlist'
                throw error
            } finally {
                this.loading = false
            }
        },

        async deletePlaylist(id) {
            this.loading = true
            this.error = null

            try {
                await axios.delete(`/api/v1/studio/playlists/${id}`)
                this.playlists = this.playlists.filter((p) => p.id !== id)
                if (this.playlist?.id === id) {
                    this.playlist = null
                    this.playlistVideos = []
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to delete playlist'
                throw error
            } finally {
                this.loading = false
            }
        },

        async addVideoToPlaylist(playlistId, videoId, position = null) {
            this.error = null

            try {
                const response = await axios.post(`/api/v1/studio/playlists/${playlistId}/videos`, {
                    video_id: videoId,
                    position
                })
                return response.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to add video to playlist'
                throw error
            }
        },

        async removeVideoFromPlaylist(playlistId, videoId) {
            this.error = null

            try {
                await axios.delete(`/api/v1/studio/playlists/${playlistId}/videos/${videoId}`)
                this.playlistVideos = this.playlistVideos.filter((v) => v.id !== videoId)
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to remove video from playlist'
                throw error
            }
        },

        async reorderPlaylist(playlistId, videoIds) {
            this.error = null

            try {
                const response = await axios.put(`/api/v1/studio/playlists/${playlistId}/reorder`, {
                    video_ids: videoIds
                })
                return response.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to reorder playlist'
                throw error
            }
        },

        async fetchAvailableVideos(params = {}) {
            const { search = '', cursor = null, limit = 10, excludeIds = [] } = params

            this.loading = true
            this.error = null

            try {
                const response = await axios.get('/api/v1/studio/playlist-posts', {
                    params: {
                        search,
                        cursor,
                        limit,
                        sort_field: 'created_at',
                        sort_direction: 'desc'
                    }
                })

                const filteredVideos = response.data.data.filter(
                    (video) => !excludeIds.includes(video.id) && video.status === 'published'
                )

                if (cursor) {
                    this.availableVideos = [...this.availableVideos, ...filteredVideos]
                } else {
                    this.availableVideos = filteredVideos
                }

                this.availableVideosPagination = {
                    nextCursor: response.data.meta?.next_cursor || null,
                    hasMore: !!response.data.meta?.next_cursor
                }

                return response.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch available videos'
                throw error
            } finally {
                this.loading = false
            }
        },

        clearAvailableVideos() {
            this.availableVideos = []
            this.availableVideosPagination = {
                nextCursor: null,
                hasMore: false
            }
        },

        clearPlaylist() {
            this.playlist = null
            this.playlistVideos = []
            this.videosPagination = {
                nextCursor: null,
                hasMore: false
            }
        },

        clearError() {
            this.error = null
        }
    }
})
