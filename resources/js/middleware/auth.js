import { useAuthStore } from '~/stores/auth'

export function authMiddleware(to, from, next) {
    const authStore = useAuthStore()

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        next({ name: 'login', query: { redirect: to.fullPath } })
    } else if (to.meta.guestOnly && authStore.isAuthenticated) {
        next({ name: 'dashboard' })
    } else {
        next()
    }
}
