import { defineStore } from 'pinia'
import axios from '~/plugins/axios'
import { useNotificationStore } from '~/stores/notifications'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        id: '',
        name: '',
        username: '',
        bio: '',
        avatar: '',
        user: null,
        authenticated: false,
        loading: false,
        error: null,
        authModalOpen: false,
        authModalMode: 'login',
        needsTwoFactor: false
    }),

    getters: {
        isAdmin: (state) => state.user?.is_admin,
        isAuthenticated: (state) => state.authenticated,
        getUser: (state) => state.user,
        getError: (state) => state.error,
        isOpen: (state) => state.authModalOpen,
        authMode: (state) => state.authModalMode
    },

    actions: {
        openAuthModal(mode = 'login') {
            this.authModalMode = mode
            this.authModalOpen = true
        },

        closeAuthModal() {
            this.authModalMode = 'login'
            this.authModalOpen = false
        },

        resetUser() {
            this.id = ''
            this.name = ''
            this.email = ''
            this.bio = ''
            this.avatar = ''
            this.user = null
            this.authenticated = false
            this.loading = false
            this.error = null
        },

        async resetMyPassword(data) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                return await axiosInstance.post('/password/email', data)
            } catch (error) {
                console.log(error)
                this.error = error.response?.data?.message || 'An error occurred.'
                throw error
            } finally {
                this.loading = false
            }
        },

        async storeMyResetPassword(data) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                return await axiosInstance.post('/password/reset', data)
            } catch (error) {
                console.log(error)
                this.error = error.response?.data?.message || 'An error occurred.'
                throw error
            } finally {
                this.loading = false
            }
        },

        async login(credentials) {
            const appConfig = window.appConfig
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                await axiosInstance.get('/sanctum/csrf-cookie')
                const response = await axiosInstance.post(
                    appConfig.app.url + '/login',
                    credentials,
                    {
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                )
                if (response.data.has_2fa) {
                    this.needsTwoFactor = true
                    return response
                }
                const userData = await axiosInstance.get('/api/v1/account/info/self')
                this.user = userData.data.data
                this.authenticated = true
                window.location.reload()
                return response
            } catch (error) {
                console.log(error)
                this.error = error.response?.data?.message || 'An error occurred during login'
                throw error
            } finally {
                this.loading = false
            }
        },

        async forceRegisterLogin() {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                await axiosInstance.get('/sanctum/csrf-cookie')
                const userData = await axiosInstance.get('/api/v1/account/info/self')
                this.user = userData.data.data
                this.authenticated = true

                return
            } catch (error) {
                console.log(error)
                this.error = error.response?.data?.message || 'An error occurred during login'
                throw error
            } finally {
                this.loading = false
            }
        },

        async validateOTP(code) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                const response = await axiosInstance.post('/api/v1/auth/2fa/verify', {
                    otp_code: code
                })

                return response
            } catch (error) {
                this.error = error.response?.data?.message || 'An error occurred during login'
                throw error
            } finally {
                this.loading = false
            }
        },

        async registerEmailVerification(data) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                await axiosInstance.get('/sanctum/csrf-cookie')
                const response = await axiosInstance.post('/api/v1/auth/register/email', data)

                return response
            } catch (error) {
                this.error =
                    error.response?.data?.message || 'An error occurred during registration'
                throw error
            } finally {
                this.loading = false
            }
        },

        async registerResendEmailVerification(data) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                await axiosInstance.get('/sanctum/csrf-cookie')
                const response = await axiosInstance.post(
                    '/api/v1/auth/register/email/resend',
                    data
                )

                return response
            } catch (error) {
                this.error =
                    error.response?.data?.message || 'An error occurred during registration'
                throw error
            } finally {
                this.loading = false
            }
        },

        async verifyEmailVerification(email, code) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                const response = await axiosInstance.post('/api/v1/auth/register/email/verify', {
                    email: email,
                    code: code
                })

                return response
            } catch (error) {
                this.error =
                    error.response?.data?.message || 'An error occurred during registration'
                throw error
            } finally {
                this.loading = false
            }
        },

        async registerUsername(username, password, passwordConfirmation, birthDate) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                const response = await axiosInstance.post('/api/v1/auth/register/username', {
                    username: username,
                    password: password,
                    password_confirmation: passwordConfirmation,
                    birth_date: birthDate
                })

                return response
            } catch (error) {
                this.error =
                    error.response?.data?.error?.message || 'An error occurred during registration'
                throw error
            } finally {
                this.loading = false
            }
        },

        async register(userData) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                await axiosInstance.get('/sanctum/csrf-cookie')
                const response = await axiosInstance.post('/api/register', userData)

                this.user = response.data.user
                this.authenticated = true

                return response
            } catch (error) {
                this.error =
                    error.response?.data?.message || 'An error occurred during registration'
                throw error
            } finally {
                this.loading = false
            }
        },

        async logout() {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                await axiosInstance
                    .post('/logout')
                    .then(() => {
                        this.resetUser()
                    })
                    .finally(() => {
                        window.location.reload()
                    })
            } catch (error) {
                this.error = error.response?.data?.message || 'An error occurred during logout'
                throw error
            } finally {
                this.loading = false
            }
        },

        async checkAuth() {
            const axiosInstance = axios.getAxiosInstance()
            try {
                this.loading = true
                this.error = null

                const response = await axiosInstance.get('/api/v1/account/info/self')
                this.user = response.data.data
                this.id = response.data.data.id
                this.name = response.data.data.name
                this.avatar = response.data.data.avatar
                this.username = response.data.data.username
                this.bio = response.data.data.bio
                this.authenticated = true

                const notifyStore = useNotificationStore()
                await notifyStore.fetchNotifications()

                return response
            } catch (error) {
                this.user = null
                this.authenticated = false
                this.error = error.response?.data?.message || 'Authentication check failed'
                throw error
            } finally {
                this.loading = false
            }
        },

        async updateBio(name, bio) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                return await axiosInstance.post('/api/v1/account/settings/bio', {
                    name: name,
                    bio: bio
                })
            } catch (error) {
                console.error('Error updating user:', error)
                throw error
            }
        },

        async deleteAvatar() {
            const axiosInstance = axios.getAxiosInstance()
            try {
                return await axiosInstance.post('/api/v1/account/settings/delete-avatar')
            } catch (error) {
                console.error('Error deleting user avatar:', error)
                throw error
            }
        },

        async updateAvatar(data) {
            const axiosInstance = axios.getAxiosInstance()
            try {
                return await axiosInstance.post('/api/v1/account/settings/update-avatar', data)
            } catch (error) {
                console.error('Error updating user avatar:', error)
                throw error
            }
        },

        async hasSessionExpired() {
            // Get the axios instance
            const axiosInstance = axios.getAxiosInstance()

            axiosInstance.interceptors.response.use(
                (response) => {
                    return response
                },
                (error) => {
                    switch (error.response?.status) {
                        case 401: // Not logged in
                        case 419: // Session expired
                        case 503: // Down for maintenance
                            //   window.location.href = '/'
                            break
                        case 500:
                            // alert('Oops, something went wrong! The team has been notified.')
                            break
                        default:
                            // Allow individual requests to handle other errors
                            return Promise.reject(error)
                    }
                }
            )
        },

        clearError() {
            this.error = null
        }
    }
})
