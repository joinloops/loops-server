import axios from 'axios'

let axiosInstance = null

const createAxiosInstance = () => {
    const appConfig = window.appConfig
    axiosInstance = axios.create({
        baseURL: appConfig.app.url,
        headers: {
            common: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        },
        withCredentials: true
    })
    return axiosInstance
}

export default {
    install: (app) => {
        const instance = createAxiosInstance()
        app.config.globalProperties.$axios = instance
        app.provide('axios', instance)
    },
    getAxiosInstance: () => {
        if (!axiosInstance) {
            return createAxiosInstance()
        }
        return axiosInstance
    }
}
