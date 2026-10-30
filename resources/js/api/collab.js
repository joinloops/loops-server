import axios from '~/plugins/axios'

const axiosInstance = axios.getAxiosInstance()

export default {
    requests: (params) => axiosInstance.get('/api/v1/collabs/requests', { params }),
    request: (id) => axiosInstance.get(`/api/v1/collabs/requests/${id}`),
    accept: (id, trust = false) =>
        axiosInstance.post(`/api/v1/collabs/requests/${id}/accept`, { trust }),
    decline: (id) => axiosInstance.post(`/api/v1/collabs/requests/${id}/decline`),
    leave: (id) => axiosInstance.post(`/api/v1/collabs/requests/${id}/leave`),

    search: (q) => axiosInstance.get('/api/v1/collabs/search', { params: { q } }),

    removeFromVideo: (videoId) => axiosInstance.post(`/api/v1/collabs/video/${videoId}/remove`),
    discard: (videoId) => axiosInstance.post(`/api/v1/collabs/video/${videoId}/discard`),

    trusted: (params) => axiosInstance.get('/api/v1/collabs/trusted', { params }),
    trust: (profileId) => axiosInstance.post(`/api/v1/collabs/trusted/${profileId}`),
    untrust: (profileId) => axiosInstance.post(`/api/v1/collabs/trusted/${profileId}/delete`),

    settings: () => axiosInstance.get('/api/v1/account/settings/collab/status'),
    updateSettings: (state) =>
        axiosInstance.post('/api/v1/account/settings/collab/update', { state })
}
