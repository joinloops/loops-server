<template>
    <div
        v-if="isOpen && isMobile"
        class="fixed inset-0 bg-black/80 z-50 lg:hidden"
        @click="closeMobileDrawer"
    ></div>

    <div
        :class="[
            'bg-white dark:bg-slate-950 lg:border-r-0 border-r dark:border-r-slate-800 overflow-auto loops-layout-sidebar no-scrollbar',
            isMobile
                ? `fixed top-0 left-0 h-screen w-[280px] transition-transform duration-300 ease-in-out z-[60] ${isOpen ? 'translate-x-0' : '-translate-x-full'}`
                : 'h-full w-[75px] lg:w-[260px]'
        ]"
    >
        <div class="w-full pt-2">
            <div v-if="!isMobile" class="p-2">
                <router-link to="/" class="flex items-center gap-2">
                    <img
                        :src="appLogoUrl()"
                        alt="Loops Logo"
                        class="rounded-full size-8 md:size-10"
                    />
                    <span
                        class="flex tracking-tight text-lg md:text-2xl font-bold text-black dark:text-white"
                    >
                        {{ appConfig.app.name }}
                    </span>
                </router-link>
            </div>
            <div
                v-if="isMobile && isOpen"
                class="flex items-center justify-between px-4 pb-2 border-b border-gray-100 dark:border-slate-800 lg:hidden"
            >
                <div class="flex items-center gap-2">
                    <img width="32" :src="appLogoUrl()" alt="Logo" class="rounded-full" />
                    <span class="text-lg font-bold text-black dark:text-white">Menu</span>
                </div>
                <button
                    @click="closeMobileDrawer"
                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800"
                >
                    <i class="bx bx-x text-xl dark:text-slate-400"></i>
                </button>
            </div>

            <div
                :class="
                    isMobile && isOpen
                        ? 'px-4 py-3'
                        : 'lg:px-3 px-0 py-3 w-[55px] lg:w-full mx-auto'
                "
            >
                <SearchInput
                    @search="handleSearch"
                    :isMobile="isMobile && isOpen"
                    :isCollapsed="!isMobile && !isLargeScreen"
                />
            </div>

            <div
                :class="
                    isMobile && isOpen ? 'px-4 py-2' : 'lg:mx-0 mx-auto pr-3 w-[55px] lg:w-full'
                "
            >
                <template v-for="mainLink in mainLinks" :key="mainLink.name">
                    <div v-if="mainLink.id === 'activity'">
                        <router-link
                            :to="mainLink.path"
                            class="dbi"
                            :class="{
                                'text-black dark:text-white': !isActive(mainLink.path),
                                'text-[#F02C56]': isActive(mainLink.path)
                            }"
                            @click="handleLinkClick"
                        >
                            <div
                                class="w-full flex items-center hover:bg-gray-100 dark:hover:bg-slate-800 p-2.5 rounded-lg mb-1 transition-colors"
                                :class="{
                                    'justify-center lg:justify-start': !isMobile,
                                    'justify-start': isMobile
                                }"
                            >
                                <div
                                    class="flex items-center"
                                    :class="{ 'lg:mx-0 mx-auto': !isMobile }"
                                >
                                    <div class="relative">
                                        <i :class="mainLink.icon" :style="`font-size: 30px;`"></i>
                                        <span
                                            v-if="unreadCount > 0"
                                            class="absolute top-1 inline-flex items-center justify-center px-1 h-4 text-[10px] font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full"
                                            :class="unreadCount > 9 ? '-right-1' : 'right-1'"
                                        >
                                            {{ displayCount(unreadCount) }}
                                        </span>
                                    </div>

                                    <span
                                        v-if="isMobile || isLargeScreen"
                                        class="font-medium text-[17px] pl-[20px] pr-4"
                                        :class="{ 'hidden lg:block': !isMobile }"
                                    >
                                        {{ mainLink.name }}
                                    </span>
                                </div>
                            </div>
                        </router-link>
                    </div>
                    <router-link
                        v-else
                        :to="mainLink.path"
                        class="dbi"
                        :class="{
                            'text-black dark:text-white': !isActive(mainLink.path),
                            'text-[#F02C56]': isActive(mainLink.path)
                        }"
                        @click="handleLinkClick"
                    >
                        <SidebarNavItem
                            :iconString="mainLink.name"
                            sizeString="30"
                            :iconClass="mainLink.icon"
                            :isMobile="isMobile && isOpen"
                        />
                    </router-link>
                </template>

                <div class="relative">
                    <button
                        @click="toggleMoreMenu"
                        class="w-full text-black dark:text-white hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
                        :class="{
                            'bg-gray-100 dark:bg-slate-800': showMoreMenu
                        }"
                    >
                        <SidebarNavItem
                            :iconString="t('nav.more')"
                            sizeString="30"
                            iconClass="bx bx-dots-vertical-rounded"
                            :isMobile="isMobile && isOpen"
                        />
                    </button>

                    <div
                        v-if="showMoreMenu"
                        :class="[
                            'absolute bg-white dark:bg-slate-900 rounded-lg shadow-xl border border-gray-200 dark:border-slate-700 z-50 min-w-[200px] overflow-hidden',
                            'divide-y divide-gray-200 dark:divide-slate-700',
                            isMobile || isLargeScreen
                                ? 'left-0 top-full mt-2'
                                : 'left-full top-0 ml-2'
                        ]"
                    >
                        <div
                            class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300"
                        >
                            <button
                                @click="handleToggleDarkMode"
                                class="flex items-center cursor-pointer"
                            >
                                <i
                                    class="bx mr-3 text-xl"
                                    :class="isDark ? 'bx-sun' : 'bx-moon'"
                                ></i>
                                <span class="text-sm font-medium">Toggle Theme</span>
                            </button>
                        </div>

                        <router-link
                            v-if="authStore.getUser"
                            to="/dashboard"
                            @click="handleMoreItemClick"
                            class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300"
                        >
                            <i class="bx bx-cog mr-3 text-xl"></i>
                            <span class="text-sm font-medium">{{ t('nav.settings') }}</span>
                        </router-link>

                        <div
                            v-if="authStore.getUser"
                            class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300"
                        >
                            <button
                                @click="handleLogout"
                                class="flex items-center w-full cursor-pointer"
                            >
                                <svg
                                    width="20"
                                    height="20"
                                    class="mr-3 text-xl"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                    transform=""
                                    id="injected-svg"
                                >
                                    <path d="M15 11H8v2h7v4l6-5-6-5z" />
                                    <path
                                        d="M5 21h7v-2H5V5h7V3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2"
                                    />
                                </svg>
                                <span class="text-sm font-medium">Logout</span>
                            </button>
                        </div>
                    </div>
                </div>

                <template v-if="!authStore.getUser">
                    <div class="my-4 space-y-2">
                        <button
                            v-if="!authStore.isAuthenticated"
                            @click="handleLoginClick"
                            class="w-full flex items-center justify-center bg-[#F02C56] text-white rounded-lg px-4 py-3 font-medium"
                        >
                            {{ t('nav.logIn') }}
                        </button>

                        <button
                            v-if="!authStore.isAuthenticated && appConfig.registration"
                            @click="handleJoinClick"
                            class="w-full flex items-center justify-center border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-slate-300 rounded-lg px-4 py-3 font-medium hover:bg-gray-50 dark:hover:bg-slate-800"
                        >
                            {{ t('nav.join') }}
                        </button>
                    </div>
                </template>
            </div>

            <div class="block border-b border-gray-100 dark:border-slate-800 my-2" />

            <div class="flex pt-1 text-[11px] text-gray-500 px-3 flex-wrap">
                <template v-for="link in footerLinks" :key="link.name">
                    <div class="pt-1 pr-4 font-medium flex">
                        <router-link
                            :to="link.path"
                            class="dfi"
                            activeClass="text-black dark:text-slate-300"
                            >{{ link.name }}</router-link
                        >
                    </div>
                </template>

                <div>
                    <button
                        class="pt-1 pr-4 font-medium flex cursor-pointer"
                        @click="openLanguagePicker"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-4"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802"
                            />
                        </svg>

                        <LanguagePicker
                            :is-open="isLanguagePickerOpen"
                            @close="closeLanguagePicker"
                        />
                    </button>
                </div>
            </div>

            <div class="flex justify-between flex-col gap-3 px-3 mt-5">
                <div class="text-[10.5px] text-gray-400 font-light dbi">
                    {{ getCopyright() }}
                </div>
                <div class="flex flex-col">
                    <a
                        class="text-[10.5px] text-gray-400 hover:text-red-400 dbi"
                        href="https://joinloops.org"
                        >{{ t('nav.poweredBy') }} Loops</a
                    >
                    <a
                        class="text-[8px] text-gray-400 hover:text-red-400 dbi"
                        href="https://github.com/joinloops/loops-server"
                        target="_blank"
                        >v{{ appVersion() }}</a
                    >
                </div>
            </div>

            <div class="pb-14"></div>
        </div>
    </div>
</template>

<script setup>
import { inject, ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import SidebarNavItem from '@/components/Layout/SidebarNavItem.vue'
import SearchInput from '@/components/Layout/SearchInput.vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '~/stores/notifications'
import LanguagePicker from '@/components/Layout/LanguagePicker.vue'
import { useLanguagePicker } from '@/composables/useLanguagePicker'
import { useAlertModal } from '@/composables/useAlertModal.js'
import AnimatedButton from '../AnimatedButton.vue'
const { t } = useI18n()
const { isLanguagePickerOpen, openLanguagePicker, closeLanguagePicker } = useLanguagePicker()

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close', 'openLogin'])

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const appConfig = inject('appConfig')
const notificationStore = useNotificationStore()
const { alertModal, confirmModal } = useAlertModal()

const showMoreMenu = ref(false)
const windowWidth = ref(window.innerWidth)

const isMobile = computed(() => windowWidth.value < 1024)
const isLargeScreen = computed(() => windowWidth.value >= 1024)

const isDark = ref(document.documentElement.classList.contains('dark'))
const unreadCount = computed(() => notificationStore.unreadCount)

const handleToggleDarkMode = () => {
    isDark.value = !isDark.value
    if (isDark.value) {
        document.documentElement.classList.add('dark')
        localStorage.setItem('theme', 'dark')
    } else {
        document.documentElement.classList.remove('dark')
        localStorage.setItem('theme', 'light')
    }
    showMoreMenu.value = false
    if (isMobile.value) {
        closeMobileDrawer()
    }
}

const getCustomNavItems = () => {
    return window._navi || []
}

const displayCount = (count) => {
    return count > 99 ? '99+' : count.toString()
}

const appLogoUrl = () => {
    return appConfig.branding.logo || `/nav-logo.png`
}

const appVersion = () => {
    return appConfig.app_version
}

const getCopyright = () => {
    return `© ${new Date().getFullYear()} ${window.location.host}`
}

const isActive = (path) => {
    return route.path === path
}

const filterNavItemsByLocation = (location) => {
    const items = getCustomNavItems()
    return items.filter((item) => {
        if (item.location === location) {
            return true
        }
        return false
    })
}

const mainLinks = computed(() => {
    let links = []

    if (authStore.getUser) {
        links = [
            { id: 'home', name: t('nav.home'), path: '/', icon: 'bx bx-home' },
            {
                id: 'following',
                name: t('common.following'),
                path: '/feed/following',
                icon: 'bx bx-user-plus'
            },
            {
                id: 'explore',
                name: t('common.explore'),
                path: '/explore',
                icon: 'bx bx-compass'
            },
            {
                id: 'activity',
                name: t('nav.activity'),
                path: `/notifications`,
                icon: 'bx bx-bell'
            },
            {
                id: 'upload',
                name: t('nav.upload'),
                path: `/studio/upload`,
                icon: 'bx bx-video-plus'
            },
            {
                id: 'profile',
                name: t('nav.profile'),
                path: `/@${authStore.getUser.username}`,
                icon: 'bx bx-user'
            }
        ]

        const userCustomPages = filterNavItemsByLocation('side_menu_user')
        const allCustomPages = filterNavItemsByLocation('side_menu_all')

        const customPages = [...userCustomPages, ...allCustomPages].map((item) => ({
            id: `custom-page-${item.slug}`,
            name: item.name,
            path: `/${item.slug}`,
            icon: 'bx bx-file'
        }))

        links.push(...customPages)

        if (authStore.isAdmin) {
            links.push({
                id: 'admin',
                name: t('nav.admin'),
                path: '/admin/dashboard',
                icon: 'bx bx-badge'
            })
        }
    } else {
        links = [
            { id: 'popular', name: t('nav.popular'), path: '/', icon: 'bx bx-trending-up' },
            {
                id: 'explore',
                name: t('common.explore'),
                path: '/explore',
                icon: 'bx bx-compass'
            }
        ]

        const guestCustomPages = filterNavItemsByLocation('side_menu_guest')
        const allCustomPages = filterNavItemsByLocation('side_menu_all')

        const customPages = [...guestCustomPages, ...allCustomPages].map((item) => ({
            id: `custom-page-${item.slug}`,
            name: item.name,
            path: `/${item.slug}`,
            icon: 'bx bx-file'
        }))

        links.push(...customPages)
    }

    return links
})

const footerLinks = computed(() => {
    const links = [
        { name: t('nav.about'), path: '/about' },
        { name: t('nav.contact'), path: '/contact' },
        { name: t('nav.community'), path: '/community-guidelines' },
        // { name: t("nav.developers"), path: "/platform/developers" },
        // { name: t("nav.federation"), path: "/federation" },
        // { name: t("nav.help"), path: "/help-center" },
        { name: t('nav.privacy'), path: '/privacy' },
        { name: t('nav.terms'), path: '/terms' }
    ]

    let customFooterItems = []

    if (authStore.getUser) {
        const userCustomPages = filterNavItemsByLocation('footer_user')
        const allCustomPages = filterNavItemsByLocation('footer_all')
        customFooterItems = [...userCustomPages, ...allCustomPages]
    } else {
        const guestCustomPages = filterNavItemsByLocation('footer_guest')
        const allCustomPages = filterNavItemsByLocation('footer_all')
        customFooterItems = [...guestCustomPages, ...allCustomPages]
    }

    const customFooterLinks = customFooterItems.map((item) => ({
        name: item.name,
        path: `/${item.slug}`
    }))

    return [...links, ...customFooterLinks]
})

const handleSearch = (query) => {
    if (query.trim()) {
        router.push({
            path: '/search',
            query: { q: query }
        })
        if (isMobile.value) {
            closeMobileDrawer()
        }
    }
}

const toggleMoreMenu = () => {
    showMoreMenu.value = !showMoreMenu.value
}

const handleMoreItemClick = () => {
    showMoreMenu.value = false
    if (isMobile.value) {
        closeMobileDrawer()
    }
}

const handleLinkClick = () => {
    showMoreMenu.value = false
    if (isMobile.value) {
        closeMobileDrawer()
    }
}

const handleLogout = async () => {
    const result = await confirmModal(
        'Confirm Log out',
        'Are you sure you want to log out?',
        t('nav.logOut'),
        t('common.cancel')
    )

    if (result) {
        performLogout()
    }
}

const logout = async () => {
    showMoreMenu.value = false
    if (isMobile.value) {
        closeMobileDrawer()
    }
    try {
        await authStore.logout()
        router.push('/')
    } catch (error) {
        console.log(error)
    }
}

const closeMobileDrawer = () => {
    showMoreMenu.value = false
    emit('close')
}

const handleUploadClick = () => {
    router.push('/studio/upload')
    closeMobileDrawer()
}

const handleLoginClick = () => {
    authStore.openAuthModal('login')
    closeMobileDrawer()
}

const handleJoinClick = () => {
    authStore.openAuthModal('register')
    closeMobileDrawer()
}

const performLogout = async () => {
    try {
        await authStore.logout()
        router.push('/')
        closeMobileDrawer()
    } catch (error) {
        console.log(error)
    }
}

const handleResize = () => {
    windowWidth.value = window.innerWidth
}

const handleClickOutside = (event) => {
    if (showMoreMenu.value && !event.target.closest('.relative')) {
        showMoreMenu.value = false
    }
}

// Lifecycle
onMounted(() => {
    window.addEventListener('resize', handleResize)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    document.removeEventListener('click', handleClickOutside)
})
</script>
