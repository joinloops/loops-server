import axios from '~/plugins/axios'

// Dashboard API
export const dashboardApi = {
    getDashboardStats: async (period = '30d', shouldRefresh = false) => {
        const params = shouldRefresh
            ? {
                  period: period,
                  refresh: true
              }
            : { period }
        const response = await apiClient.get('/api/v1/admin/dashboard/stats', params)
        return response
    },

    getVersionCheck: async () => {
        return await apiClient.get('/api/v1/admin/version-check')
    }
}

// Pages API
export const pagesApi = {
    getPages: async (params = {}) => {
        const queryString = new URLSearchParams(params).toString()
        const url = `/api/v1/admin/pages${queryString ? `?${queryString}` : ''}`

        const response = await apiClient.get(url)
        return response
    },

    getPage: async (pageId) => {
        const response = await apiClient.get(`/api/v1/admin/pages/${pageId}`)
        return response
    },

    createPage: async (pageData) => {
        const response = await apiClient.post('/api/v1/admin/pages', pageData)
        return response
    },

    updatePage: async (pageId, pageData) => {
        const response = await apiClient.put(`/api/v1/admin/pages/${pageId}`, pageData)
        return response
    },

    deletePage: async (pageId) => {
        const response = await apiClient.delete(`/api/v1/admin/pages/${pageId}`)
        return response
    }
}

// Comments API
export const commentsApi = {
    async getComments({
        cursor = null,
        direction = 'next',
        search = '',
        limit = 15,
        local = false,
        sort = null
    } = {}) {
        const params = {
            cursor: cursor,
            limit: limit,
            q: search
        }

        if (local) {
            params.local = 1
        }

        if (sort) {
            params.sort = sort
        }
        return await apiClient.get('/api/v1/admin/comments', params)
    },

    async getComment(id) {
        return await apiClient.get(`/api/v1/admin/comment/${id}`)
    },

    async hideComment(id) {
        return await apiClient.post(`/api/v1/admin/comments/${id}/hide`)
    },

    async unhideComment(id) {
        return await apiClient.post(`/api/v1/admin/comments/${id}/unhide`)
    },

    async deleteComment(id) {
        return await apiClient.post(`/api/v1/admin/comments/${id}/delete`)
    }
}

// Comment Replies API
export const repliesApi = {
    async getComments({
        cursor = null,
        direction = 'next',
        search = '',
        limit = 15,
        local = false,
        sort = null
    } = {}) {
        const params = {
            cursor: cursor,
            limit: limit,
            q: search
        }

        if (local) {
            params.local = 1
        }

        if (sort) {
            params.sort = sort
        }
        return await apiClient.get('/api/v1/admin/replies', params)
    },

    async getParentComment(id) {
        return await apiClient.get(`/api/v1/admin/comment/${id}`)
    },

    async hideComment(id) {
        return await apiClient.post(`/api/v1/admin/replies/${id}/hide`)
    },

    async unhideComment(id) {
        return await apiClient.post(`/api/v1/admin/replies/${id}/unhide`)
    },

    async deleteComment(id) {
        return await apiClient.post(`/api/v1/admin/replies/${id}/delete`)
    }
}

export const hashtagsApi = {
    async getHashtags({
        cursor = null,
        direction = 'next',
        search = '',
        limit = 15,
        sort = null
    } = {}) {
        return await apiClient.get('/api/v1/admin/hashtags', {
            cursor: cursor,
            limit: limit,
            q: search,
            sort: sort
        })
    },

    async getHashtag(id) {
        return await apiClient.get(`/api/v1/admin/hashtag/${id}`)
    },

    async updateHashtag(id, params = {}) {
        return await apiClient.post(`/api/v1/admin/hashtags/${id}/update`, params)
    }
}

// Reports API
export const reportsApi = {
    async getReports({
        cursor = null,
        direction = 'next',
        search = '',
        limit = 15,
        sort = null
    } = {}) {
        return await apiClient.get('/api/v1/admin/reports', {
            cursor: cursor,
            limit: limit,
            q: search,
            sort: sort
        })
    },

    async getReport(id) {
        return await apiClient.get('/api/v1/admin/reports/' + id)
    },

    async updateReportNotes(id, data) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/update-admin-notes`, data)
    },

    async updateReportMarkAsNsfw(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/mark-nsfw`)
    },

    async deleteComment(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/comment-delete`)
    },

    async deleteCommentReply(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/comment-reply-delete`)
    },

    async markAsAi(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/mark-as-ai`)
    },

    async markAsAd(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/mark-as-ad`)
    },

    async markAsAiAndAd(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/mark-as-ai-and-ad`)
    },

    async deleteVideo(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/video-delete`)
    },

    async dismissAllReportsByAccount(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/dismiss-all-by-account`)
    },

    async dismissReport(id) {
        return await apiClient.post(`/api/v1/admin/reports/${id}/dismiss`)
    }
}

// Profiles API
export const profilesApi = {
    async getProfiles({
        cursor = null,
        direction = 'next',
        search = '',
        limit = 15,
        sort = null,
        local = false
    } = {}) {
        const params = {
            cursor: cursor,
            limit: limit,
            q: search,
            sort: sort
        }

        if (local) {
            params.local = 1
        }

        return await apiClient.get('/api/v1/admin/profiles', params)
    },

    async getProfile(id) {
        return await apiClient.get('/api/v1/admin/profiles/' + id)
    },

    async updateProfile(id, data) {
        return { success: true, data: { id, ...data } }
    },

    async updateProfilePermissions(id, data) {
        return await apiClient.post(`/api/v1/admin/profiles/${id}/permissions`, data)
    },

    async updateProfileNotes(id, data) {
        return await apiClient.post(`/api/v1/admin/profiles/${id}/admin_note`, data)
    },

    async updateProfileSuspend(id) {
        return await apiClient.post(`/api/v1/admin/profiles/${id}/suspend`)
    },

    async updateProfileUnsuspend(id) {
        return await apiClient.post(`/api/v1/admin/profiles/${id}/unsuspend`)
    },

    async deleteProfile(id) {
        return { success: true }
    }
}

// Admin Invites API
export const invitesApi = {
    async getInvites({
        cursor = null,
        direction = 'next',
        search = '',
        limit = 15,
        sort = null
    } = {}) {
        return await apiClient.get('/api/v1/admin/invites', {
            cursor: cursor,
            limit: limit,
            q: search,
            sort: sort
        })
    },

    async searchProfiles(query) {
        const params = {
            limit: 6,
            local: 1,
            q: query
        }

        return await apiClient.get('/api/v1/admin/profiles', params)
    },

    async getAccount(id) {
        return await apiClient.get(`/api/v1/account/info/${id}`)
    },

    async getInvite(id) {
        return await apiClient.get(`/api/v1/admin/invites/show/${id}`)
    },

    async deleteInvite(id) {
        return await apiClient.post(`/api/v1/admin/invites/delete/${id}`)
    },

    async updateInvite(id, data) {
        return await apiClient.post(`/api/v1/admin/invites/update/${id}`, data)
    },

    async createInvite(data) {
        return await apiClient.post(`/api/v1/admin/invites/create`, data)
    }
}

// Videos API
export const videosApi = {
    async getVideos({
        cursor = null,
        direction = 'next',
        search = '',
        limit = 15,
        sort = null
    } = {}) {
        return await apiClient.get('/api/v1/admin/videos', {
            cursor: cursor,
            limit: limit,
            q: search,
            sort: sort
        })
    },

    async getVideo(id) {
        return await apiClient.get(`/api/v1/admin/video/${id}`)
    },

    async getVideoComments(id, { cursor = null, direction = 'next', search = '', limit = 15 }) {
        return await apiClient.get(`/api/v1/admin/videos/${id}/comments`, {
            cursor: cursor,
            limit: limit
        })
    },

    async deleteVideoComment(id) {
        return await apiClient.post(`/api/v1/admin/comments/${id}/delete`)
    },

    async moderateVideo(id, data) {
        return await apiClient.post(`/api/v1/admin/videos/${id}/moderate`, data)
    },

    async deleteVideo(id) {
        return { success: true }
    }
}

export const settingsApi = {
    async getSettings() {
        return await apiClient.get('/api/v1/admin/settings')
    },

    async updateSettings(data) {
        const res = await apiClient.put('/api/v1/admin/settings', data)
        return res.data
    },

    async updateLogo(data) {
        return await apiClient.post('/api/v1/admin/settings/update-logo', data)
    },

    async deleteLogo() {
        return await apiClient.post('/api/v1/admin/settings/delete-logo')
    },

    async recheckRedisBfSupport() {
        return await apiClient.post('/api/v1/admin/settings/recheck-redis-bf-support')
    }
}

export const relaysApi = {
    async getRelays() {
        return await apiClient.get('/api/v1/admin/relays')
    },

    async createRelay(data) {
        return await apiClient.post('/api/v1/admin/relays', data)
    },

    async updateRelay(id, data) {
        return await apiClient.put(`/api/v1/admin/relays/${id}`, data)
    },

    async deleteRelay(id) {
        return await apiClient.delete(`/api/v1/admin/relays/${id}`)
    },

    async enableRelay(id) {
        return await apiClient.post(`/api/v1/admin/relays/${id}/enable`)
    },

    async disableRelay(id) {
        return await apiClient.post(`/api/v1/admin/relays/${id}/disable`)
    },

    async getStats() {
        return await apiClient.get('/api/v1/admin/relays/stats')
    }
}

export const instancesApi = {
    async getInstances({
        cursor = null,
        direction = 'next',
        search = '',
        limit = 15,
        sort = null
    } = {}) {
        return await apiClient.get('/api/v1/admin/instances', {
            cursor: cursor,
            limit: limit,
            q: search,
            sort: sort
        })
    },

    async getInstanceStats() {
        return await apiClient.get(`/api/v1/admin/instances/stats`)
    },

    async getInstance(id) {
        return await apiClient.get(`/api/v1/admin/instances/${id}`)
    },

    async getInstanceUsers(id, params) {
        return await apiClient.get(`/api/v1/admin/instances/${id}/users`, params)
    },

    async getInstanceVideos(id, params) {
        return await apiClient.get(`/api/v1/admin/instances/${id}/videos`, params)
    },

    async getInstanceComments(id, params) {
        return await apiClient.get(`/api/v1/admin/instances/${id}/comments`, params)
    },

    async getInstanceReports(id, params) {
        return await apiClient.get(`/api/v1/admin/instances/${id}/reports`, params)
    },

    async updateInstanceNotes(id, params) {
        return await apiClient.post(`/api/v1/admin/instances/${id}/update-admin-notes`, params)
    },

    async updateInstanceSettings(id, params) {
        return await apiClient.post(`/api/v1/admin/instances/${id}/settings`, params)
    },

    async suspendInstance(id) {
        return await apiClient.post(`/api/v1/admin/instances/${id}/suspend`)
    },

    async activateInstance(id) {
        return await apiClient.post(`/api/v1/admin/instances/${id}/activate`)
    },

    async refreshInstanceData(id) {
        return await apiClient.post(`/api/v1/admin/instances/${id}/refresh`)
    },

    async createInstance(data) {
        return await apiClient.post(`/api/v1/admin/instances/create`, data)
    },

    async createInstances(data) {
        return await apiClient.post(`/api/v1/admin/instances/bulk-create`, data)
    },

    async getManageStats() {
        return await apiClient.get('/api/v1/admin/instances/manage/stats')
    },

    async toggleBySoftware(data) {
        return await apiClient.post('/api/v1/admin/instances/manage/toggle-by-software', data)
    },

    async toggleByDomains(data) {
        return await apiClient.post('/api/v1/admin/instances/manage/toggle-by-domains', data)
    }
}

// API utility
export const apiClient = {
    async get(endpoint, params = {}) {
        const axiosInstance = axios.getAxiosInstance()

        const response = await axiosInstance.get(endpoint, {
            params: params
        })

        if (!response) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        return response.data
    },

    async post(endpoint, data = {}) {
        const axiosInstance = axios.getAxiosInstance()

        const response = await axiosInstance.post(endpoint, data)

        if (!response) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        return response.data
    },

    async put(endpoint, data = {}) {
        const axiosInstance = axios.getAxiosInstance()

        const response = await axiosInstance.put(endpoint, data)

        if (!response) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        return response
    },

    async delete(endpoint) {
        const axiosInstance = axios.getAxiosInstance()
        const response = await axiosInstance.delete(endpoint)
        if (!response) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }
        return response
    }
}
