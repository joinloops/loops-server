import axios from '~/plugins/axios'

const axiosInstance = axios.getAxiosInstance()

export default {
    suggestedRecipients: () => axiosInstance.get('/api/v1/dm/suggested-recipients'),
    conversations: (params) => axiosInstance.get('/api/v1/dm/conversations', { params }),
    conversation: (id) => axiosInstance.get(`/api/v1/dm/conversations/${id}`),
    markRead: (id, messageId) =>
        axiosInstance.post(
            `/api/v1/dm/conversations/${id}/read`,
            messageId ? { message_id: messageId } : {}
        ),
    accept: (id) => axiosInstance.post(`/api/v1/dm/conversations/${id}/accept`),
    decline: (id) => axiosInstance.post(`/api/v1/dm/conversations/${id}/decline`),
    mute: (id) => axiosInstance.post(`/api/v1/dm/conversations/${id}/mute`),
    unmute: (id) => axiosInstance.post(`/api/v1/dm/conversations/${id}/unmute`),
    hide: (id) => axiosInstance.post(`/api/v1/dm/conversations/${id}/hide`),
    unhide: (id) => axiosInstance.post(`/api/v1/dm/conversations/${id}/unhide`),
    messages: (id, params) =>
        axiosInstance.get(`/api/v1/dm/conversations/${id}/messages`, { params }),
    send: (payload) => axiosInstance.post('/api/v1/dm/messages', payload),
    sendMedia: (payload) => axiosInstance.post('/api/v1/dm/messages/media', payload),
    deleteMessage: (id) => axiosInstance.delete(`/api/v1/dm/messages/${id}`),
    searchAccounts: (q) => axiosInstance.post('/api/v1/dm/search', { q }),
    createGroup: (profileIds) =>
        axiosInstance.post('/api/v1/dm/groups', { profile_ids: profileIds }),
    addParticipants: (id, profileIds) =>
        axiosInstance.post(`/api/v1/dm/conversations/${id}/participants`, {
            profile_ids: profileIds
        }),
    leaveGroup: (id) => axiosInstance.post(`/api/v1/dm/conversations/${id}/leave`)
}
