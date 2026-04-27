<template>
    <div class="flex h-dvh min-h-dvh bg-gray-50 dark:bg-gray-900">
        <CommandPalette ref="commandPalette" />

        <div
            v-if="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 bg-black/90 transition-opacity lg:hidden z-40"
        ></div>

        <div
            :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full']"
            class="fixed inset-y-0 left-0 z-50 w-72 sm:w-80 lg:w-72 flex flex-col min-h-dvh min-h-0 overflow-hidden bg-white dark:bg-gray-900/80 backdrop-blur shadow-xl lg:shadow-none transform transition-transform duration-200 ease-out lg:translate-x-0 lg:relative lg:sticky lg:top-0"
        >
            <div
                class="flex items-center justify-between h-16 px-5 flex-shrink-0 bg-gradient-to-r from-[#F02C56] to-[#FF4D74] dark:from-[#8B1E3A] dark:to-[#A52F47] border-b border-white/10"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-9 w-9 rounded-xl bg-white/15 ring-1 ring-white/20 flex items-center justify-center"
                    >
                        <img src="/img/logo-light.svg" alt="Loops logo" class="p-1.5" />
                    </div>
                    <div class="leading-tight">
                        <h1 class="text-[15px] font-semibold text-white">Loops</h1>
                        <p class="text-[11px] text-white/70">Admin Dashboard</p>
                    </div>
                </div>

                <button
                    @click="sidebarOpen = false"
                    class="lg:hidden text-white/90 hover:text-white p-2 rounded-lg hover:bg-white/10 transition"
                    aria-label="Close sidebar"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <nav
                class="flex-1 min-h-0 px-3 py-4 space-y-6 overflow-y-auto overscroll-contain [-webkit-overflow-scrolling:touch]"
            >
                <template v-for="group in navigation" :key="group.title">
                    <div>
                        <div class="flex items-center justify-between px-3 mb-2">
                            <h3
                                class="text-[11px] font-semibold tracking-[0.18em] uppercase text-gray-400 dark:text-gray-500"
                            >
                                {{ group.title }}
                            </h3>
                            <span class="h-px flex-1 ml-3 bg-gray-200/70 dark:bg-gray-800"></span>
                        </div>

                        <div class="space-y-1">
                            <template v-for="item in group.items" :key="item.name">
                                <div v-if="item.subItems" class="space-y-1">
                                    <button
                                        @click="toggleGroup(item.name)"
                                        :aria-expanded="expandedGroups.includes(item.name)"
                                        :aria-controls="`submenu-${item.name}`"
                                        :class="[
                                            'w-full group relative flex items-center justify-between gap-3',
                                            'px-3 py-2.5 rounded-xl text-sm font-medium',
                                            'transition focus:outline-none focus:ring-2 focus:ring-[#F02C56]/30',
                                            'ring-1 ring-transparent hover:ring-gray-200/80 dark:hover:ring-gray-800',
                                            isParentActive(item)
                                                ? 'bg-[#F02C56]/10 text-gray-900 dark:text-white ring-[#F02C56]/20'
                                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50/80 dark:hover:bg-gray-800/60'
                                        ]"
                                    >
                                        <div class="flex items-center min-w-0">
                                            <component
                                                :is="item.icon"
                                                :class="[
                                                    'mr-3 h-5 w-5 flex-shrink-0 transition',
                                                    isParentActive(item)
                                                        ? 'text-[#F02C56] dark:text-[#FF6B8C]'
                                                        : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300'
                                                ]"
                                            />
                                            <span class="truncate">{{ item.name }}</span>
                                        </div>

                                        <ChevronDownIcon
                                            :class="[
                                                'h-4 w-4 flex-shrink-0 transition-transform',
                                                expandedGroups.includes(item.name)
                                                    ? 'rotate-180'
                                                    : '',
                                                isParentActive(item)
                                                    ? 'text-[#F02C56] dark:text-[#FF6B8C]'
                                                    : 'text-gray-400 dark:text-gray-500'
                                            ]"
                                        />
                                    </button>

                                    <div
                                        :id="`submenu-${item.name}`"
                                        :class="[
                                            'ml-4 pl-3 border-l border-gray-200/70 dark:border-gray-800',
                                            'space-y-1',
                                            expandedGroups.includes(item.name) ? 'block' : 'hidden'
                                        ]"
                                    >
                                        <router-link
                                            v-for="subItem in item.subItems"
                                            :key="subItem.name"
                                            :to="subItem.href"
                                            @click="sidebarOpen = false"
                                            :class="[
                                                'group flex items-center gap-2 rounded-lg',
                                                'px-3 py-2 text-[13px] leading-tight',
                                                'transition focus:outline-none focus:ring-2 focus:ring-[#F02C56]/25',
                                                route.path === subItem.href
                                                    ? 'bg-[#F02C56]/10 text-[#F02C56] dark:text-[#FF6B8C] font-semibold'
                                                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50/80 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800/60'
                                            ]"
                                        >
                                            <span
                                                :class="[
                                                    'h-1.5 w-1.5 rounded-full transition',
                                                    route.path === subItem.href
                                                        ? 'bg-[#F02C56]'
                                                        : 'bg-gray-300 group-hover:bg-gray-400 dark:bg-gray-700 dark:group-hover:bg-gray-600'
                                                ]"
                                            ></span>
                                            <span class="truncate">{{ subItem.name }}</span>
                                        </router-link>
                                    </div>
                                </div>

                                <router-link
                                    v-else
                                    :to="item.href"
                                    @click="sidebarOpen = false"
                                    :class="[
                                        'group relative flex items-center gap-3 rounded-xl',
                                        'px-3 py-2.5 text-sm font-medium',
                                        'transition focus:outline-none focus:ring-2 focus:ring-[#F02C56]/30',
                                        'ring-1 ring-transparent hover:ring-gray-200/80 dark:hover:ring-gray-800',
                                        isRouteActive(item.href)
                                            ? 'bg-[#F02C56]/10 text-gray-900 dark:text-white ring-[#F02C56]/20'
                                            : 'text-gray-700 hover:bg-gray-50/80 dark:text-gray-300 dark:hover:bg-gray-800/60'
                                    ]"
                                >
                                    <component
                                        :is="item.icon"
                                        :class="[
                                            'h-5 w-5 flex-shrink-0 transition',
                                            isRouteActive(item.href)
                                                ? 'text-[#F02C56] dark:text-[#FF6B8C]'
                                                : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300'
                                        ]"
                                    />
                                    <span class="flex-1 min-w-0 truncate">{{ item.name }}</span>

                                    <template v-if="item.href === '/admin/reports'">
                                        <span
                                            v-if="reportsCount > 0"
                                            class="ml-auto inline-flex items-center justify-center min-w-[1.6rem] h-6 px-2 rounded-full text-[11px] font-semibold bg-[#F02C56] text-white shadow-sm ring-1 ring-black/5"
                                        >
                                            {{ displayReportsCount }}
                                        </span>
                                        <span
                                            v-else-if="isLoading"
                                            class="ml-auto inline-flex h-4 w-10 rounded-full bg-gray-200 dark:bg-gray-800 animate-pulse"
                                        ></span>
                                    </template>

                                    <template v-if="item.href === '/admin/starter-kits-review'">
                                        <span
                                            v-if="starterKitsUpdates > 0"
                                            class="ml-auto inline-flex items-center justify-center min-w-[1.6rem] h-6 px-2 rounded-full text-[11px] font-semibold bg-[#F02C56] text-white shadow-sm ring-1 ring-black/5"
                                        >
                                            {{ displayUpdatedStarterKitsCount }}
                                        </span>
                                        <span
                                            v-else-if="isLoading"
                                            class="ml-auto inline-flex h-4 w-10 rounded-full bg-gray-200 dark:bg-gray-800 animate-pulse"
                                        ></span>
                                    </template>

                                    <template v-if="item.href === '/admin/starterkits'">
                                        <span
                                            v-if="starterKitsAwaitingApproval > 0"
                                            class="ml-auto inline-flex items-center justify-center min-w-[1.6rem] h-6 px-2 rounded-full text-[11px] font-semibold bg-[#F02C56] text-white shadow-sm ring-1 ring-black/5"
                                        >
                                            {{ displayAwaitingStarterKitsCount }}
                                        </span>
                                        <span
                                            v-else-if="isLoading"
                                            class="ml-auto inline-flex h-4 w-10 rounded-full bg-gray-200 dark:bg-gray-800 animate-pulse"
                                        ></span>
                                    </template>

                                    <template v-if="item.href === '/admin/curated-onboarding'">
                                        <span
                                            v-if="onboardingAwaitingApproval > 0"
                                            class="ml-auto inline-flex items-center justify-center min-w-[1.6rem] h-6 px-2 rounded-full text-[11px] font-semibold bg-[#F02C56] text-white shadow-sm ring-1 ring-black/5"
                                        >
                                            {{ displayAwaitingOnboardingCount }}
                                        </span>
                                        <span
                                            v-else-if="isLoading"
                                            class="ml-auto inline-flex h-4 w-10 rounded-full bg-gray-200 dark:bg-gray-800 animate-pulse"
                                        ></span>
                                    </template>
                                </router-link>
                            </template>
                        </div>
                    </div>
                </template>
            </nav>

            <div class="flex-shrink-0 p-3 border-t border-gray-200/70 dark:border-gray-800">
                <button
                    @click="handleToggleDarkMode"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100/80 dark:hover:bg-gray-800/60 transition focus:outline-none focus:ring-2 focus:ring-[#F02C56]/30"
                >
                    <component
                        :is="isDark ? SunIcon : MoonIcon"
                        class="h-5 w-5 text-gray-400 dark:text-gray-500"
                    />
                    <span class="flex-1 text-left">{{ isDark ? 'Light Mode' : 'Dark Mode' }}</span>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500">Theme</span>
                </button>
            </div>
        </div>

        <div class="flex flex-col flex-1 overflow-hidden">
            <header
                class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
            >
                <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-2 lg:gap-4 lg:flex-1">
                        <button
                            @click="sidebarOpen = true"
                            class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <svg
                                class="w-6 h-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                ></path>
                            </svg>
                        </button>

                        <button
                            @click="commandPalette?.open()"
                            class="lg:hidden p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            <MagnifyingGlassIcon class="w-5 h-5" />
                        </button>

                        <router-link
                            to="/admin/reports"
                            class="lg:hidden relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            <ExclamationTriangleIcon class="w-5 h-5" />
                            <span
                                v-if="reportsCount > 0"
                                class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full text-[10px] font-bold leading-none bg-[#F02C56] text-white shadow-sm ring-2 ring-white dark:ring-gray-800"
                            >
                                {{ displayReportsCount }}
                            </span>
                            <span
                                v-else-if="isLoading"
                                class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse"
                            >
                            </span>
                        </router-link>

                        <button
                            @click="commandPalette?.open()"
                            class="hidden lg:flex items-center gap-3 flex-1 max-w-md px-3 py-2 text-sm text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700/50 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors border border-transparent hover:border-gray-300 dark:hover:border-gray-600 group"
                        >
                            <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                            <span class="flex-1 text-left">Search or jump to...</span>
                            <kbd
                                class="hidden sm:inline-flex items-center gap-0.5 px-2 py-1 text-xs font-semibold text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded border border-gray-300 dark:border-gray-600 group-hover:border-gray-400 dark:group-hover:border-gray-500 transition-colors"
                            >
                                <span class="text-xs">⌘</span>K
                            </kbd>
                        </button>
                    </div>

                    <div
                        class="flex lg:hidden absolute left-1/2 -translate-x-1/2 px-4 py-1.5 rounded bg-gradient-to-r from-[#F02C56] to-[#FF4D74] dark:from-[#8B1E3A] dark:to-[#A52F47]"
                    >
                        <div class="flex flex-col justify-center items-center">
                            <h1 class="text-[13px] font-semibold text-white leading-tight">
                                Loops
                            </h1>
                            <p class="text-[10px] text-white/70 leading-tight">Admin</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end lg:flex-1">
                        <span
                            class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-[100px] sm:max-w-[150px] lg:max-w-none"
                        >
                            Hello {{ authStore.getUser.username }}!
                        </span>
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
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import {
    ChartBarSquareIcon,
    Cog6ToothIcon,
    ChatBubbleOvalLeftIcon,
    ChatBubbleLeftRightIcon,
    HashtagIcon,
    ExclamationTriangleIcon,
    UserGroupIcon,
    UserPlusIcon,
    VideoCameraIcon,
    ServerStackIcon,
    HomeIcon,
    SunIcon,
    MoonIcon,
    ArrowPathIcon,
    MagnifyingGlassIcon,
    ChevronDownIcon,
    WalletIcon,
    ChatBubbleBottomCenterIcon
} from '@heroicons/vue/24/outline'
import { useAdminStore } from '~/stores/admin'

const route = useRoute()
const sidebarOpen = ref(false)
const commandPalette = ref(null)
const expandedGroups = ref([])

const authStore = useAuthStore()

const navigation = computed(() => [
    {
        title: 'Overview',
        items: [
            { name: 'Go back home', href: '/', icon: HomeIcon },
            { name: 'Dashboard', href: '/admin/dashboard', icon: ChartBarSquareIcon }
        ]
    },
    {
        title: 'Content',
        items: [
            { name: 'Hashtags', href: '/admin/hashtags', icon: HashtagIcon },
            { name: 'Comments', href: '/admin/comments', icon: ChatBubbleOvalLeftIcon },
            { name: 'Replies', href: '/admin/replies', icon: ChatBubbleLeftRightIcon },
            { name: 'Videos', href: '/admin/videos', icon: VideoCameraIcon }
        ]
    },
    {
        title: 'Moderation',
        items: [
            {
                name: 'Blocked Terms',
                href: '/admin/blocked-terms',
                icon: ChatBubbleBottomCenterIcon
            },
            { name: 'Reports', href: '/admin/reports', icon: ExclamationTriangleIcon },
            { name: 'Kit Updates', href: '/admin/starter-kits-review', icon: WalletIcon },
            ...(config.value?.curated_onboarding_enabled
                ? [
                      {
                          name: 'User Onboarding',
                          href: '/admin/curated-onboarding',
                          icon: UserPlusIcon
                      }
                  ]
                : [])
        ]
    },
    {
        title: 'Users',
        items: [
            { name: 'Profiles', href: '/admin/profiles', icon: UserGroupIcon },
            { name: 'Starter Kits', href: '/admin/starterkits', icon: WalletIcon },
            {
                name: 'Invites',
                icon: UserPlusIcon,
                subItems: [
                    { name: 'Overview', href: '/admin/invites' },
                    { name: 'Create Invite', href: '/admin/invites/create' }
                ]
            }
        ]
    },
    {
        title: 'Federation',
        items: [
            {
                name: 'Instances',
                icon: ServerStackIcon,
                subItems: [
                    { name: 'Overview', href: '/admin/instances' },
                    { name: 'Manage', href: '/admin/instances/manage' }
                ]
            },
            { name: 'Relays', href: '/admin/relays', icon: ArrowPathIcon }
        ]
    },
    {
        title: 'System',
        items: [{ name: 'Settings', href: '/admin/settings', icon: Cog6ToothIcon }]
    }
])

const isRouteActive = (navPath) => {
    const currentPath = route.path
    if (currentPath === navPath) return true
    if (currentPath.startsWith(navPath + '/')) return true
    return false
}

const isParentActive = (item) => {
    if (!item.subItems) return false
    return item.subItems.some(
        (subItem) => route.path === subItem.href || route.path.startsWith(subItem.href + '/')
    )
}

const toggleGroup = (groupName) => {
    const index = expandedGroups.value.indexOf(groupName)
    if (index > -1) {
        expandedGroups.value.splice(index, 1)
    } else {
        expandedGroups.value.push(groupName)
    }
}

const initializeExpandedGroups = () => {
    const activeGroups = []

    navigation.value.forEach((group) => {
        group.items.forEach((item) => {
            if (item.subItems) {
                const hasActiveSubItem = item.subItems.some((subItem) => {
                    const currentPath = route.path
                    return (
                        currentPath === subItem.href || currentPath.startsWith(subItem.href + '/')
                    )
                })
                if (hasActiveSubItem && !activeGroups.includes(item.name)) {
                    activeGroups.push(item.name)
                }
            }
        })
    })

    expandedGroups.value = activeGroups
}

watch(
    () => route.path,
    () => {
        initializeExpandedGroups()
    }
)

const adminStore = useAdminStore()
const {
    isDarkMode,
    reportsCount,
    config,
    starterKitsAwaitingApproval,
    starterKitsUpdates,
    isLoading,
    displayReportsCount,
    onboardingAwaitingApproval,
    displayAwaitingOnboardingCount,
    displayAwaitingStarterKitsCount,
    displayUpdatedStarterKitsCount
} = storeToRefs(adminStore)
const isDark = isDarkMode

const handleToggleDarkMode = () => {
    adminStore.toggleDarkMode()
    sidebarOpen.value = false
}

onMounted(() => {
    adminStore.initTheme()
    adminStore.fetchConfig()
    adminStore.fetchReportsCount()
    initializeExpandedGroups()
})
</script>
