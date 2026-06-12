<template>
    <div class="min-h-screen bg-white dark:bg-slate-950">
        <!-- Global TikTok-style Sidebar (not shown on admin/auth pages) -->
        <aside
            v-if="showGlobalSidebar && !isMobileView"
            class="fixed left-0 top-0 h-full z-40 global-tiktok-sidebar"
        >
            <!-- Logo -->
            <div class="flex items-center justify-center h-16">
                <router-link to="/" class="flex items-center gap-1">
                    <img
                        src="/nav-logo-80.webp"
                        width="36"
                        height="36"
                        class="rounded-full"
                        alt="Logo"
                    />
                </router-link>
            </div>

            <!-- Nav Icons -->
            <nav class="flex flex-col items-center gap-1 px-2 mt-2">
                <router-link
                    to="/"
                    class="tiktok-nav-icon"
                    :class="isActive('/') && route.path === '/' ? 'text-white' : 'text-white/60'"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <i class="text-2xl" :class="isActive('/') && route.path === '/' ? 'bx bxs-home' : 'bx bx-home'"></i>
                        <span class="text-[10px] font-medium">Home</span>
                    </div>
                </router-link>

                <router-link
                    to="/explore"
                    class="tiktok-nav-icon"
                    :class="isActive('/explore') ? 'text-white' : 'text-white/60'"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <i class="text-2xl" :class="isActive('/explore') ? 'bx bxs-compass' : 'bx bx-compass'"></i>
                        <span class="text-[10px] font-medium">Explore</span>
                    </div>
                </router-link>

                <router-link
                    to="/feed/following"
                    class="tiktok-nav-icon"
                    :class="isActive('/feed/following') ? 'text-white' : 'text-white/60'"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <i class="text-2xl" :class="isActive('/feed/following') ? 'bx bxs-user-plus' : 'bx bx-user-plus'"></i>
                        <span class="text-[10px] font-medium">Following</span>
                    </div>
                </router-link>

                <router-link
                    to="/studio/upload"
                    class="tiktok-nav-icon"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <div class="w-9 h-9 rounded-full border-2 border-white/40 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span class="text-[10px] font-medium">Upload</span>
                    </div>
                </router-link>

                <router-link
                    to="/notifications"
                    class="tiktok-nav-icon"
                    :class="isActive('/notifications') ? 'text-white' : 'text-white/60'"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <i class="text-2xl" :class="isActive('/notifications') ? 'bx bxs-bell' : 'bx bx-bell'"></i>
                        <span class="text-[10px] font-medium">Inbox</span>
                    </div>
                </router-link>

                <router-link
                    to="/search"
                    class="tiktok-nav-icon"
                    :class="isActive('/search') ? 'text-white' : 'text-white/60'"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <i class="text-2xl bx bx-search"></i>
                        <span class="text-[10px] font-medium">Search</span>
                    </div>
                </router-link>

                <!-- 찍고 팔고 (Snap & Sell) -->
                <a
                    href="https://k-store.privseai.com"
                    target="_blank"
                    class="tiktok-nav-icon"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-[10px] font-medium">찍고팔고</span>
                    </div>
                </a>

                <div class="mt-3 w-8 h-px bg-white/10"></div>

                <router-link
                    v-if="authStore.isAuthenticated"
                    :to="`/@${authStore.user?.username}`"
                    class="tiktok-nav-icon mt-1"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <img
                            class="rounded-full w-8 h-8 border-2 border-white/30 object-cover"
                            :src="authStore.user?.avatar"
                            alt=""
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                        <span class="text-[10px] font-medium">Profile</span>
                    </div>
                </router-link>
                <router-link
                    v-else
                    to="/login"
                    class="tiktok-nav-icon text-white/60"
                >
                    <div class="flex flex-col items-center gap-0.5">
                        <i class="bx bx-user text-2xl"></i>
                        <span class="text-[10px] font-medium">Login</span>
                    </div>
                </router-link>
            </nav>
        </aside>

        <!-- Main Content with sidebar offset -->
        <div
            :class="showGlobalSidebar && !isMobileView ? 'ml-[72px] w-[calc(100%-72px)]' : 'w-full'"
            class="min-h-screen"
        >
            <router-view v-slot="{ Component }">
                <Suspense>
                    <template #default>
                        <component :is="Component" />
                    </template>
                    <template #fallback>
                        <PageSkeleton />
                    </template>
                </Suspense>
            </router-view>
        </div>

        <!-- Mobile Bottom Nav (TikTok-style) -->
        <nav
            v-if="showGlobalSidebar && isMobileView"
            class="fixed bottom-0 left-0 right-0 z-50 global-tiktok-bottom-nav"
        >
            <div class="flex items-center justify-around h-full px-2">
                <router-link
                    to="/"
                    class="flex flex-col items-center justify-center flex-1 h-full transition-colors"
                    :class="isActive('/') && route.path === '/' ? 'text-white' : 'text-white/50'"
                >
                    <i class="text-2xl" :class="isActive('/') && route.path === '/' ? 'bx bxs-home' : 'bx bx-home'"></i>
                </router-link>

                <router-link
                    to="/explore"
                    class="flex flex-col items-center justify-center flex-1 h-full transition-colors"
                    :class="isActive('/explore') ? 'text-white' : 'text-white/50'"
                >
                    <i class="text-2xl" :class="isActive('/explore') ? 'bx bxs-compass' : 'bx bx-compass'"></i>
                </router-link>

                <router-link
                    to="/studio/upload"
                    class="relative flex items-center justify-center px-5"
                >
                    <div class="w-10 h-10 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </router-link>

                <!-- 찍고 팔고 (Snap & Sell) - Mobile -->
                <a
                    href="https://k-store.privseai.com"
                    target="_blank"
                    class="flex flex-col items-center justify-center flex-1 h-full relative"
                >
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center shadow-lg">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </a>

                <router-link
                    to="/notifications"
                    class="flex flex-col items-center justify-center flex-1 h-full transition-colors"
                    :class="isActive('/notifications') ? 'text-white' : 'text-white/50'"
                >
                    <i class="text-2xl" :class="isActive('/notifications') ? 'bx bxs-bell' : 'bx bx-bell'"></i>
                </router-link>

                <router-link
                    v-if="authStore.isAuthenticated"
                    :to="`/@${authStore.user?.username}`"
                    class="flex flex-col items-center justify-center flex-1 h-full transition-colors"
                    :class="isActive(`/@${authStore.user?.username}`) ? 'text-white' : 'text-white/50'"
                >
                    <img
                        class="rounded-full w-7 h-7 border border-white/30 object-cover"
                        :src="authStore.user?.avatar"
                        alt=""
                        @error="$event.target.src = '/storage/avatars/default.jpg'"
                    />
                </router-link>
                <router-link
                    v-else
                    to="/login"
                    class="flex flex-col items-center justify-center flex-1 h-full transition-colors text-white/50"
                >
                    <i class="bx bx-user text-2xl"></i>
                </router-link>
            </div>
        </nav>

        <AuthModal v-if="isOpen" :mode="authMode" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, inject } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import AuthModal from '@/components/AuthModal.vue'
import PageSkeleton from '@/components/Layout/PageSkeleton.vue'

const route = useRoute()
const authStore = useAuthStore()
const { isOpen, authMode } = storeToRefs(authStore)

const isMobileView = ref(false)

const showGlobalSidebar = computed(() => {
    const path = route.path
    // Hide sidebar on admin pages
    if (path.startsWith('/admin')) return false
    // Hide on auth-only pages
    if (path.startsWith('/login') || path.startsWith('/register') || path.startsWith('/auth/')) return false
    if (path.startsWith('/password/')) return false
    // Hide on login/register standalone pages
    if (path === '/login' || path === '/register') return false
    // Show on all other pages
    return true
})

const isActive = (path) => {
    if (path === '/') return route.path === '/'
    return route.path.startsWith(path)
}

const checkMobileView = () => {
    isMobileView.value = window.innerWidth < 768
}

onMounted(async () => {
    if (
        localStorage.theme === 'dark' ||
        (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)
    ) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
    try {
        await authStore.hasSessionExpired()
    } catch (error) {
        console.error('Error in App setup:', error)
    }
    checkMobileView()
    window.addEventListener('resize', checkMobileView)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobileView)
})
</script>

<style>
/* Global TikTok-style Sidebar */
.global-tiktok-sidebar {
    width: 72px;
    background: rgba(0, 0, 0, 0.9);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.tiktok-nav-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    padding: 8px 0;
    border-radius: 12px;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
}

.tiktok-nav-icon:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* Mobile Bottom Nav */
.global-tiktok-bottom-nav {
    height: 64px;
    padding-bottom: env(safe-area-inset-bottom);
    background: linear-gradient(transparent, rgba(0,0,0,0.8) 30%, rgba(0,0,0,0.9));
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
</style>
