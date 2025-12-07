import { defineStore } from 'pinia'
import axios from '~/plugins/axios'

export const useAppStore = defineStore('app', {
    state: () => ({
        isLoginOpen: false,
        languages: []
    }),
    getters: {
        getLangs: (state) => state.languages,
        hasLanguages: (state) => state.languages.length > 0
    },
    actions: {
        async fetchLanguages() {
            const axiosInstance = axios.getAxiosInstance()
            const langs = await axiosInstance.get('/api/v1/i18n/list')
            this.languages = langs.data.data
        },

        async ensureLanguages() {
            if (this.languages.length === 0) {
                await this.fetchLanguages()
            }
            return this.languages
        },

        toggleLoginForm() {
            this.isLoginOpen = !this.isLoginOpen
        }
    },
    persist: true
})
