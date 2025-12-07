import axios from '~/plugins/axios'

export const fetchFeedPage = async ({ cursor = null }) => {
    const axiosInstance = axios.getAxiosInstance()
    const response = await axiosInstance.get('/api/v0/feed/for-you', {
        params: cursor ? { cursor } : {}
    })
    return response.data
}

export const fetchFollowingFeedPage = async ({ cursor = null }) => {
    const axiosInstance = axios.getAxiosInstance()
    const response = await axiosInstance.get('/api/v1/feed/following', {
        params: cursor ? { cursor } : {}
    })
    return response.data
}
