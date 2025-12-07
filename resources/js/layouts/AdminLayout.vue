<template>
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <div
            v-if="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 bg-gray-600 bg-opacity-75 transition-opacity lg:hidden z-40"
        ></div>

        <div
            :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full']"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 shadow-lg transform transition-transform lg:translate-x-0 lg:relative lg:flex lg:flex-col"
        >
            <div class="flex items-center justify-between h-16 px-6 bg-sky-500 flex-shrink-0">
                <h1 class="text-xl font-bold text-white">Loops Admin</h1>
                <button
                    @click="sidebarOpen = false"
                    class="lg:hidden text-white hover:text-gray-200"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        ></path>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 mt-8 px-4 space-y-2 overflow-y-auto">
                <router-link
                    v-for="item in navigation"
                    :key="item.name"
                    :to="item.href"
                    @click="sidebarOpen = false"
                    :class="[
                        'group flex items-center px-2 py-2 text-sm font-medium rounded-md transition-colors',
                        isRouteActive(item.href)
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'
                    ]"
                >
                    <component :is="item.icon" class="mr-3 h-5 w-5 flex-shrink-0" />
                    <span class="truncate">
                        {{ item.name }}
                    </span>

                    <span
                        v-if="item.href === '/admin/reports' && reportsCount > 0"
                        class="ml-auto inline-flex items-center justify-center w-6 h-6 rounded-full text-[11px] font-semibold leading-none bg-red-500 text-white shadow-sm"
                    >
                        {{ displayReportsCount }}
                    </span>

                    <span
                        v-else-if="item.href === '/admin/reports' && isLoading"
                        class="ml-auto inline-flex h-4 w-8 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse"
                    ></span>
                </router-link>
            </nav>

            <div class="flex-shrink-0 p-4 border-t border-gray-200 dark:border-gray-700">
                <button
                    @click="handleToggleDarkMode"
                    class="w-full flex items-center px-2 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md transition-colors"
                >
                    <component :is="isDark ? SunIcon : MoonIcon" class="mr-3 h-5 w-5" />
                    {{ isDark ? 'Light Mode' : 'Dark Mode' }}
                </button>
            </div>
        </div>

        <div class="flex flex-col flex-1 overflow-hidden">
            <header
                class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
            >
                <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                    <button
                        @click="sidebarOpen = true"
                        class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            ></path>
                        </svg>
                    </button>

                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Hello Admin!</span>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <router-view />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import {
    ChartBarSquareIcon,
    Cog6ToothIcon,
    ChatBubbleOvalLeftIcon,
    ChatBubbleLeftRightIcon,
    HashtagIcon,
    ExclamationTriangleIcon,
    UserGroupIcon,
    VideoCameraIcon,
    ServerStackIcon,
    HomeIcon,
    SunIcon,
    MoonIcon
} from '@heroicons/vue/24/outline'
import { useAdminStore } from '~/stores/admin'

const route = useRoute()
const sidebarOpen = ref(false)

const navigation = [
    { name: 'Dashboard', href: '/admin/dashboard', icon: ChartBarSquareIcon },
    { name: 'Comments', href: '/admin/comments', icon: ChatBubbleOvalLeftIcon },
    { name: 'Replies', href: '/admin/replies', icon: ChatBubbleLeftRightIcon },
    { name: 'Hashtags', href: '/admin/hashtags', icon: HashtagIcon },
    { name: 'Instances', href: '/admin/instances', icon: ServerStackIcon },
    { name: 'Reports', href: '/admin/reports', icon: ExclamationTriangleIcon },
    { name: 'Profiles', href: '/admin/profiles', icon: UserGroupIcon },
    { name: 'Videos', href: '/admin/videos', icon: VideoCameraIcon },
    { name: 'Settings', href: '/admin/settings', icon: Cog6ToothIcon },
    { name: 'Go back home', href: '/', icon: HomeIcon }
]

const isRouteActive = (navPath) => {
    const currentPath = route.path
    if (currentPath === navPath) return true
    if (currentPath.startsWith(navPath + '/')) return true
    return false
}

const adminStore = useAdminStore()
const { isDarkMode, reportsCount, isLoading, displayReportsCount } = storeToRefs(adminStore)

const handleToggleDarkMode = () => {
    adminStore.toggleDarkMode()
}

onMounted(() => {
    adminStore.initTheme()
    adminStore.fetchReportsCount()
})
</script>
