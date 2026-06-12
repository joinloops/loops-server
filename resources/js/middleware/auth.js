import { useAuthStore } from '~/stores/auth'

export function authMiddleware(to, from, next) {
    const authStore = useAuthStore()

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        // Open auth modal instead of redirecting to login page
        authStore.openAuthModal('login', to.fullPath)
        next(false)
    } else if (to.meta.guestOnly && authStore.isAuthenticated) {
        next({ name: 'dashboard' })
    } else {
        next()
    }
}
