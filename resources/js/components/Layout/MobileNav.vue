<template>
    <nav
        v-if="isMobileView"
        class="fixed bottom-0 left-0 right-0 z-50 h-[80px] border-t bg-white dark:bg-black border-gray-200 dark:border-gray-800 safe-area-bottom"
    >
        <div class="flex items-center justify-around h-full px-2">
            <router-link
                to="/"
                class="flex flex-col items-center justify-center flex-1 h-full transition-colors"
                :class="
                    isActive('/')
                        ? 'text-gray-900 dark:text-white'
                        : 'text-gray-500 dark:text-gray-400'
                "
            >
                <i class="text-2xl" :class="isActive('/') ? 'bx bxs-home' : 'bx bx-home'"></i>
                <span class="text-xs mt-0.5 font-medium">{{ $t('nav.home') }}</span>
            </router-link>

            <router-link
                to="/explore"
                class="flex flex-col items-center justify-center flex-1 h-full transition-colors"
                :class="
                    isActive('/explore')
                        ? 'text-gray-900 dark:text-white'
                        : 'text-gray-500 dark:text-gray-400'
                "
            >
                <i
                    class="text-2xl"
                    :class="isActive('/explore') ? 'bx bxs-compass' : 'bx bx-compass'"
                ></i>
                <span class="text-xs mt-0.5 font-medium">{{ $t('common.explore') }}</span>
            </router-link>

            <router-link
                to="/studio/upload"
                class="relative flex items-center justify-center -mt-2 px-5"
            >
                <div
                    class="relative w-12 h-10 rounded-lg overflow-hidden bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 shadow-md"
                >
                    <div class="absolute inset-0 flex items-center justify-center">
                        <svg
                            class="w-7 h-7 text-gray-700 dark:text-gray-200"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                    </div>
                </div>
            </router-link>

            <router-link
                to="/notifications"
                class="relative flex flex-col items-center justify-center flex-1 h-full transition-colors"
                :class="
                    isActive('/notifications')
                        ? 'text-gray-900 dark:text-white'
                        : 'text-gray-500 dark:text-gray-400'
                "
            >
                <div class="relative">
                    <i
                        class="text-2xl"
                        :class="isActive('/notifications') ? 'bx bxs-bell' : 'bx bx-bell'"
                    ></i>

                    <span
                        v-if="unreadCount > 0"
                        class="absolute -top-1 -right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-[#F02C56] rounded-full"
                    >
                        {{ unreadCount > 99 ? '99+' : unreadCount }}
                    </span>
                </div>
                <span class="text-xs mt-0 font-medium">{{ $t('nav.activity') }}</span>
            </router-link>

            <router-link
                v-if="authStore.isAuthenticated"
                :to="`/@${authStore.user.username}`"
                class="flex flex-col items-center justify-center flex-1 h-full transition-colors"
                :class="
                    isActive(`/@${authStore.user.username}`)
                        ? 'text-gray-900 dark:text-white'
                        : 'text-gray-500 dark:text-gray-400'
                "
            >
                <img
                    class="rounded-full"
                    width="30"
                    :src="authStore.user.avatar"
                    alt="User avatar"
                    @error="$event.target.src = '/storage/avatars/default.jpg'"
                />
                <span class="text-xs mt-0.5 font-medium">{{ $t('nav.profile') }}</span>
            </router-link>
            <router-link
                v-else
                to="/login"
                class="flex flex-col items-center justify-center flex-1 h-full transition-colors"
                :class="
                    isActive('/profile')
                        ? 'text-gray-900 dark:text-white'
                        : 'text-gray-500 dark:text-gray-400'
                "
            >
                <i
                    class="text-2xl"
                    :class="isActive('/profile') ? 'bx bxs-user' : 'bx bx-user'"
                ></i>
                <span class="text-xs mt-0.5 font-medium">{{ $t('nav.profile') }}</span>
            </router-link>
        </div>
    </nav>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotificationStore } from '@/stores/notifications.js'
import { useAuthStore } from '@/stores/auth.js'

const route = useRoute()
const router = useRouter()
const notificationStore = useNotificationStore()
const authStore = useAuthStore()

const isMobileView = ref(false)
const unreadCount = computed(() => notificationStore.unreadCount)

const isActive = (path) => {
    if (path === '/') {
        return route.path === '/'
    }
    return route.path.startsWith(path)
}

const handleCompose = () => {
    router.push('/studio/upload')
}

const checkMobileView = () => {
    isMobileView.value = window.innerWidth < 768
}

onMounted(() => {
    checkMobileView()
    window.addEventListener('resize', checkMobileView)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobileView)
})
</script>

<style scoped>
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom);
}
</style>
