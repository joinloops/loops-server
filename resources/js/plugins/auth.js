import { useAuthStore } from '@/stores/auth'

export async function initializeAuth() {
    const authStore = useAuthStore()

    try {
        await authStore.checkAuth()
    } catch (error) {
        // Handle failed auth check silently - user might not be logged in
        console.debug('Initial auth check failed:', error)
    }
}
