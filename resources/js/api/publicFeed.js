import axios from '~/plugins/axios'

export const fetchPublicFeedPage = async ({ cursor = null }) => {
    const axiosInstance = axios.getAxiosInstance()
    const response = await axiosInstance.get('/api/web/feed', {
        params: cursor ? { cursor } : {}
    })
    return response.data
}
