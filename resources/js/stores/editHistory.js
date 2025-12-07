import { defineStore } from 'pinia'
import axios from '~/plugins/axios'

export const useEditHistoryStore = defineStore('editHistory', {
    state: () => ({
        historyMap: new Map(),
        loadingStates: new Map(),
        errorStates: new Map(),
        cursorsMap: new Map(),
        hasMoreMap: new Map(),
        isModalOpen: false,
        currentEntity: null
    }),

    getters: {
        getHistory() {
            return (entityKey) => this.historyMap.get(entityKey) || []
        },

        isLoading() {
            return (entityKey) => this.loadingStates.get(entityKey) || false
        },

        hasError() {
            return (entityKey) => this.errorStates.get(entityKey) || null
        },

        hasMore() {
            return (entityKey) => this.hasMoreMap.get(entityKey) || false
        },

        getCurrentHistory() {
            if (!this.currentEntity) return []

            const { type, videoId, commentId, parentCommentId } = this.currentEntity
            const entityKey = this._makeEntityKey(type, videoId, commentId, parentCommentId)

            return this.historyMap.get(entityKey) || []
        },

        isCurrentLoading() {
            if (!this.currentEntity) return false

            const { type, videoId, commentId, parentCommentId } = this.currentEntity
            const entityKey = this._makeEntityKey(type, videoId, commentId, parentCommentId)

            return this.loadingStates.get(entityKey) || false
        },

        getCurrentError() {
            if (!this.currentEntity) return null

            const { type, videoId, commentId, parentCommentId } = this.currentEntity
            const entityKey = this._makeEntityKey(type, videoId, commentId, parentCommentId)

            return this.errorStates.get(entityKey) || null
        },

        hasMoreCurrent() {
            if (!this.currentEntity) return false

            const { type, videoId, commentId, parentCommentId } = this.currentEntity
            const entityKey = this._makeEntityKey(type, videoId, commentId, parentCommentId)

            return this.hasMoreMap.get(entityKey) || false
        }
    },

    actions: {
        _makeEntityKey(type, videoId, commentId, parentCommentId) {
            switch (type) {
                case 'video':
                    return `video:${videoId}`
                case 'comment':
                    return `comment:${videoId}:${commentId}`
                case 'comment_reply':
                    return `reply:${videoId}:${parentCommentId}:${commentId}`
                default:
                    throw new Error(`Unknown entity type: ${type}`)
            }
        },

        _getApiUrl(type, videoId, commentId, parentCommentId) {
            switch (type) {
                case 'video':
                    return `/api/v1/video/history/${videoId}`
                case 'comment':
                    return `/api/v1/comments/history/${videoId}/${commentId}`
                case 'comment_reply':
                    return `/api/v1/comments/history/${videoId}/${parentCommentId}/${commentId}`
                default:
                    throw new Error(`Unknown entity type: ${type}`)
            }
        },

        async fetchHistory(type, videoId, commentId = null, parentCommentId = null, reset = false) {
            const entityKey = this._makeEntityKey(type, videoId, commentId, parentCommentId)

            if (reset) {
                this.historyMap.delete(entityKey)
                this.cursorsMap.delete(entityKey)
                this.hasMoreMap.delete(entityKey)
                this.errorStates.delete(entityKey)
            }

            if (this.loadingStates.get(entityKey)) {
                return this.historyMap.get(entityKey) || []
            }

            if (!reset && !this.hasMoreMap.get(entityKey) && this.historyMap.has(entityKey)) {
                return this.historyMap.get(entityKey)
            }

            try {
                this.loadingStates.set(entityKey, true)
                this.errorStates.set(entityKey, null)

                const axiosInstance = axios.getAxiosInstance()
                const baseUrl = this._getApiUrl(type, videoId, commentId, parentCommentId)

                const cursor = reset ? null : this.cursorsMap.get(entityKey)
                const limit = 2

                let url = `${baseUrl}?limit=${limit}`
                if (cursor) {
                    url += `&cursor=${cursor}`
                }

                const response = await axiosInstance.get(url)
                const { data, meta } = response.data

                if (reset || !this.historyMap.has(entityKey)) {
                    this.historyMap.set(entityKey, data || [])
                } else {
                    const existingHistory = this.historyMap.get(entityKey) || []
                    this.historyMap.set(entityKey, [...existingHistory, ...(data || [])])
                }

                this.cursorsMap.set(entityKey, meta?.next_cursor || null)
                this.hasMoreMap.set(entityKey, !!meta?.next_cursor)

                return this.historyMap.get(entityKey)
            } catch (error) {
                console.error('Error fetching edit history:', error)
                this.errorStates.set(
                    entityKey,
                    error.response?.data?.message || 'Failed to load edit history'
                )
                throw error
            } finally {
                this.loadingStates.set(entityKey, false)
            }
        },

        async loadMore() {
            if (!this.currentEntity) return

            const { type, videoId, commentId, parentCommentId } = this.currentEntity
            const entityKey = this._makeEntityKey(type, videoId, commentId, parentCommentId)

            // Don't load if already loading or no more data
            if (this.loadingStates.get(entityKey) || !this.hasMoreMap.get(entityKey)) {
                return
            }

            await this.fetchHistory(type, videoId, commentId, parentCommentId, false)
        },

        async openVideoHistory(videoId) {
            this.currentEntity = {
                type: 'video',
                videoId,
                title: 'Video Edit History'
            }
            this.isModalOpen = true
            await this.fetchHistory('video', videoId, null, null, true)
        },

        async openCommentHistory(videoId, commentId) {
            this.currentEntity = {
                type: 'comment',
                videoId,
                commentId,
                title: 'Comment Edit History'
            }
            this.isModalOpen = true
            await this.fetchHistory('comment', videoId, commentId, null, true)
        },

        async openCommentReplyHistory(videoId, parentCommentId, commentId) {
            this.currentEntity = {
                type: 'comment_reply',
                videoId,
                commentId,
                parentCommentId,
                title: 'Reply Edit History'
            }
            this.isModalOpen = true
            await this.fetchHistory('comment_reply', videoId, commentId, parentCommentId, true)
        },

        closeModal() {
            this.isModalOpen = false
            this.currentEntity = null
        },

        clearHistory(type, videoId, commentId = null, parentCommentId = null) {
            const entityKey = this._makeEntityKey(type, videoId, commentId, parentCommentId)
            this.historyMap.delete(entityKey)
            this.loadingStates.delete(entityKey)
            this.errorStates.delete(entityKey)
            this.cursorsMap.delete(entityKey)
            this.hasMoreMap.delete(entityKey)
        },

        resetStore() {
            this.historyMap.clear()
            this.loadingStates.clear()
            this.errorStates.clear()
            this.cursorsMap.clear()
            this.hasMoreMap.clear()
            this.isModalOpen = false
            this.currentEntity = null
        }
    }
})
