<template>
    <div
        class="fixed bg-white dark:bg-slate-950 flex items-center w-full h-[70px] z-10 border-b border-gray-200 dark:border-slate-800 loops-layout-header"
    >
        <div class="flex items-center justify-between w-full px-4 lg:px-6 mx-auto">
            <div class="flex items-center lg:w-[30%] w-auto">
                <button
                    @click="toggleMobileDrawer"
                    class="lg:hidden mr-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800"
                >
                    <i class="bx bx-menu text-2xl dark:text-slate-400"></i>
                </button>

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

            <!-- <div
                v-if="authStore.isAuthenticated"
                class="hidden md:flex items-center bg-[#F1F1F2] dark:bg-slate-900 p-1 rounded-lg max-w-[380px] w-full relative search-container"
            >
                <div
                    class="px-3 py-1 flex items-center border-r border-r-gray-300 dark:border-r-slate-800"
                >
                    <i class="ri-search-line text-[#A1A2A7] text-[22px]"></i>
                </div>
                <input
                    type="text"
                    v-model="searchQuery"
                    @input="handleSearch"
                    @focus="handleFocus"
                    class="w-full pl-3 my-2 bg-transparent dark:text-white placeholder-[#838383] text-[15px] focus:outline-none"
                    :placeholder="t('nav.search')"
                />

                <div
                    v-if="searchStore.isLoading"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2"
                >
                    <div
                        class="w-4 h-4 border-2 border-gray-300 border-t-[#F02C56] rounded-full animate-spin"
                    ></div>
                </div>

                <div
                    v-if="showDropdown"
                    class="absolute top-full left-0 w-full mt-1 bg-white dark:bg-slate-900 rounded-lg shadow-lg border border-gray-100 dark:border-slate-800 max-h-[400px] overflow-y-auto z-50"
                >
                    <div
                        v-if="searchStore.recentSearches.length && !searchQuery"
                        class="border-b border-gray-200 dark:border-b-gray-800"
                    >
                        <div
                            class="flex items-center justify-between px-4 py-2 border-b border-gray-100 dark:border-b-gray-800"
                        >
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-400">
                                {{ t('nav.recentSearches') }}
                            </h3>
                            <button
                                @click="searchStore.clearRecentSearches"
                                class="text-xs text-[#F02C56] hover:underline"
                            >
                                {{ t('nav.clearAll') }}
                            </button>
                        </div>
                        <div
                            v-for="query in searchStore.recentSearches"
                            :key="query"
                            @click="handleRecentSearchClick(query)"
                            class="flex items-center px-4 py-2 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer"
                        >
                            <i class="ri-history-line mr-2 text-gray-400"></i>
                            <span class="text-sm dark:text-slate-200">{{ query }}</span>
                        </div>
                    </div>

                    <div
                        v-if="searchStore.searchResults.length"
                        class="divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <div
                            v-for="result in searchStore.searchResults"
                            :key="result.id"
                            @click="handleResultClick(result)"
                            class="flex items-center px-4 py-2 hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer"
                        >
                            <img
                                v-if="result.avatar"
                                :src="result.avatar"
                                class="w-12 h-12 rounded-full mr-3"
                                :alt="result.title"
                                @error="$event.target.src = '/storage/avatars/default.jpg'"
                            />
                            <div class="flex flex-col space-y-0.5">
                                <div class="font-bold text-[15px] text-black dark:text-slate-300">
                                    @{{ result.username }}
                                </div>
                                <div class="flex items-center space-x-1">
                                    <div
                                        class="text-xs text-gray-500 dark:text-slate-500 truncate max-w-[40%]"
                                    >
                                        {{ result.name }}
                                    </div>
                                    <div
                                        class="font-medium text-xs text-gray-400 dark:text-slate-600"
                                    >
                                        ·
                                    </div>
                                    <div
                                        class="font-medium text-xs text-gray-400 dark:text-slate-600"
                                    >
                                        {{ formatCount(result.post_count) }}
                                        {{ t('common.videos') }}
                                    </div>
                                    <div
                                        class="font-medium text-xs text-gray-400 dark:text-slate-600"
                                    >
                                        ·
                                    </div>
                                    <div
                                        class="font-medium text-xs text-gray-400 dark:text-slate-600"
                                    >
                                        {{ formatCount(result.follower_count) }}
                                        {{ t('common.followers') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            searchQuery &&
                            !searchStore.searchResults.length &&
                            !searchStore.isLoading
                        "
                        class="px-4 py-3 text-sm text-gray-500 text-center"
                    >
                        {{ t('nav.noResultsFound') }}
                    </div>
                </div>
            </div> -->

            <div
                class="flex items-center justify-end gap-2 lg:gap-3 lg:min-w-[275px] lg:max-w-[320px] w-auto"
            >
                <!-- <button @click="toggleMobileSearch"
                    class="md:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800">
                    <i class="ri-search-line text-xl dark:text-slate-400"></i>
                </button> -->

                <button
                    v-if="authStore.isAuthenticated"
                    @click="isLoggedIn"
                    class="hidden lg:flex items-center bg-[#F02C56] border border-[#F02C56] text-white rounded-lg px-5 py-2 hover:bg-[#F02C56]/80 hover:border-[#F02C5699] cursor-pointer"
                >
                    <i class="bx bx-upload text-white text-[22px]"></i>
                    <span class="px-2 font-medium tracking-tight text-[15px]">{{
                        t('nav.upload')
                    }}</span>
                </button>

                <div v-if="!authStore.isAuthenticated" class="hidden lg:flex items-center">
                    <ThemeToggleButton class="mr-1" />

                    <button
                        v-if="!authStore.isAuthenticated && appConfig.registration"
                        @click="authStore.openAuthModal('register')"
                        class="flex items-center border-[#F02C56] text-[#F02C56] border dark:border-red-400 rounded-md px-3 py-[6px] cursor-pointer mr-3"
                    >
                        <span class="mx-4 font-medium text-[15px]">{{ t('nav.join') }}</span>
                    </button>

                    <button
                        v-if="!authStore.isAuthenticated"
                        @click="authStore.openAuthModal('login')"
                        class="flex items-center bg-[#F02C56] text-white border dark:border-red-400 rounded-md px-3 py-[6px] cursor-pointer"
                    >
                        <span class="mx-4 font-medium text-[15px]">{{ t('nav.logIn') }}</span>
                    </button>
                </div>

                <div v-if="authStore.isAuthenticated" class="hidden lg:flex items-center">
                    <ThemeToggleButton class="mr-1" />

                    <NotificationsDropdown>
                        <template #trigger="{ unreadCount, toggle }">
                            <button
                                @click="toggle"
                                class="relative rounded-full p-2 mr-5 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.1"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                    />
                                </svg>
                                <span
                                    v-if="unreadCount > 0"
                                    class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full"
                                >
                                    {{ displayCount(unreadCount) }}
                                </span>
                            </button>
                        </template>
                    </NotificationsDropdown>

                    <div class="relative">
                        <button
                            class="mt-1 cursor-pointer hover:opacity-90"
                            @click="showMenu = !showMenu"
                        >
                            <img
                                class="rounded-full"
                                width="33"
                                :src="authStore.user.avatar"
                                alt="User avatar"
                                @error="$event.target.src = '/storage/avatars/default.jpg'"
                            />
                        </button>

                        <div
                            v-if="showMenu"
                            class="absolute bg-white dark:bg-slate-900 rounded-lg w-[200px] shadow-xl overflow-hidden border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700 ark:border-slate-700 top-[43px] -right-2"
                        >
                            <router-link
                                :to="`/@${authStore.user.username}`"
                                @click="showMenu = false"
                                class="flex items-center justify-start py-3 px-2 hover:bg-gray-100 dark:hover:bg-slate-800 cursor-pointer dark:text-slate-200"
                            >
                                <i class="ph-user text-[20px]"></i>
                                <span class="pl-2 font-semibold text-sm">{{
                                    t('nav.profile')
                                }}</span>
                            </router-link>
                            <div
                                @click="logout"
                                class="flex items-center justify-start py-3 px-1.5 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                            >
                                <i class="ic-outline-login text-[20px]"></i>
                                <span class="pl-2 font-semibold text-sm">{{
                                    t('nav.logOut')
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    v-if="authStore.isAuthenticated"
                    @click="toggleMobileDrawer"
                    class="lg:hidden"
                >
                    <img
                        class="rounded-full"
                        width="32"
                        :src="authStore.user.avatar"
                        @error="$event.target.src = '/storage/avatars/default.jpg'"
                        alt="User avatar"
                    />
                </button>

                <button
                    v-if="!authStore.isAuthenticated"
                    @click="authStore.openAuthModal('login')"
                    class="lg:hidden bg-[#F02C56] text-white rounded-md px-3 py-2 text-sm font-medium"
                >
                    {{ t('nav.loginOrRegister') }}
                </button>
            </div>
        </div>

        <!-- <div v-if="showMobileSearch"
            class="md:hidden absolute top-full left-0 w-full bg-white dark:bg-slate-950 border-b border-gray-100 dark:border-slate-800 p-4 z-50">
            <div class="flex items-center bg-[#F1F1F2] dark:bg-slate-900 p-1 rounded-lg relative">
                <div class="px-3 py-1 flex items-center border-r border-r-gray-300 dark:border-r-slate-800">
                    <i class="ri-search-line text-[#A1A2A7] text-[22px]"></i>
                </div>
                <input ref="mobileSearchInput" type="text" v-model="searchQuery" @input="handleSearch"
                    @focus="handleFocus"
                    class="w-full pl-3 my-2 bg-transparent dark:text-white placeholder-[#838383] text-[15px] focus:outline-none"
                    placeholder="Search" />
                <button @click="closeMobileSearch"
                    class="px-3 py-1 text-gray-500 hover:text-gray-700 dark:text-slate-400">
                    <i class="bx bx-x text-xl"></i>
                </button>
            </div>

            <div v-if="
                showDropdown &&
                (searchStore.recentSearches.length || searchStore.searchResults.length)
            "
                class="mt-2 bg-white dark:bg-slate-900 rounded-lg shadow-lg border border-gray-200 dark:border-slate-800 max-h-[300px] overflow-y-auto">
                <div v-if="searchStore.recentSearches.length && !searchQuery"
                    class="border-b border-gray-200 dark:border-gray-700">
                    <div
                        class="flex items-center justify-between px-4 py-2 border-b border-gray-100 dark:border-b-gray-800">
                        <h3 class="text-sm font-medium text-gray-700">
                            {{ t('nav.recentSearches') }}
                        </h3>
                        <button @mousedown="searchStore.clearRecentSearches"
                            class="text-xs text-[#F02C56] hover:underline cursor-pointer">
                            {{ t('nav.clearAll') }}
                        </button>
                    </div>
                    <div v-for="query in searchStore.recentSearches" :key="query"
                        @mousedown="handleRecentSearchClick(query)"
                        class="flex items-center px-4 py-2 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer">
                        <i class="ri-history-line mr-2 text-gray-400"></i>
                        <span class="text-sm dark:text-slate-200">{{ query }}</span>
                    </div>
                </div>

                <div v-if="searchStore.searchResults.length" class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div v-for="result in searchStore.searchResults" :key="result.id"
                        @mousedown="handleResultClick(result)"
                        class="flex items-center px-4 py-2 hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer">
                        <img v-if="result.avatar" :src="result.avatar" class="w-12 h-12 rounded-full mr-3"
                            :alt="result.title" @error="$event.target.src = '/storage/avatars/default.jpg'" />
                        <div class="flex flex-col space-y-0.5">
                            <div class="font-bold text-[15px] text-black dark:text-slate-300">
                                @{{ result.username }}
                            </div>
                            <div class="flex items-center space-x-1">
                                <div class="text-xs text-gray-500 dark:text-slate-500 truncate max-w-[40%]">
                                    {{ result.name }}
                                </div>
                                <div class="font-medium text-xs text-gray-400 dark:text-slate-600">
                                    ·
                                </div>
                                <div class="font-medium text-xs text-gray-400 dark:text-slate-600">
                                    {{ formatCount(result.post_count) }}
                                    {{ t('common.videos') }}
                                </div>
                                <div class="font-medium text-xs text-gray-400 dark:text-slate-600">
                                    ·
                                </div>
                                <div class="font-medium text-xs text-gray-400 dark:text-slate-600">
                                    {{ formatCount(result.follower_count) }}
                                    {{ t('common.followers') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> -->
    </div>
</template>

<script setup>
import { ref, onMounted, inject, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useSearchStore } from '@/stores/search'
import { useAuthStore } from '@/stores/auth'
import { debounce } from 'lodash'
import { useNotificationStore } from '~/stores/notifications'
import { useUtils } from '@/composables/useUtils'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const emit = defineEmits(['toggleMobileDrawer', 'openLogin'])

const { formatCount } = useUtils()
const router = useRouter()
const authStore = useAuthStore()
const searchStore = useSearchStore()
const notificationsStore = useNotificationStore()
const appConfig = inject('appConfig')

const showMenu = ref(false)
const searchQuery = ref('')
const showDropdown = ref(false)
const showMobileSearch = ref(false)
const mobileSearchInput = ref(null)

const appLogoUrl = () => {
    return appConfig.branding.logo || `/nav-logo.png`
}

const toggleMobileDrawer = () => {
    emit('toggleMobileDrawer')
}

const toggleMobileSearch = async () => {
    searchStore.clearSearchResults()
    showMobileSearch.value = !showMobileSearch.value
    if (showMobileSearch.value) {
        await nextTick()
        mobileSearchInput.value?.focus()
    }
}

const closeMobileSearch = () => {
    searchStore.clearSearchResults()
    showMobileSearch.value = false
    searchQuery.value = ''
    showDropdown.value = false
}

onMounted(() => {
    // searchStore.loadRecentSearches()

    document.addEventListener('mouseup', (e) => {
        const searchContainer = document.querySelector('.search-container')
        const popupMenu = document.getElementById('PopupMenu')

        if (searchContainer && !searchContainer.contains(e.target)) {
            showDropdown.value = false
        }

        if (popupMenu && !popupMenu.contains(e.target)) {
            showMenu.value = false
        }

        if (showMobileSearch.value && !e.target.closest('.absolute.top-full')) {
            closeMobileSearch()
        }
    })
})

const handleSearch = debounce(() => {
    searchStore.searchQuery(searchQuery.value)
}, 300)

const handleFocus = () => {
    showDropdown.value = true
}

const handleRecentSearchClick = (query) => {
    searchQuery.value = query
    searchStore.searchQuery(query)
    if (showMobileSearch.value) {
        closeMobileSearch()
    }
}

const handleResultClick = (result) => {
    console.log('result', result)
    searchQuery.value = result.title
    showDropdown.value = false
    router.push(`/@${result.username}`)
    if (showMobileSearch.value) {
        closeMobileSearch()
    }
}

const displayCount = (count) => {
    return count > 99 ? '99+' : count.toString()
}

const isLoggedIn = () => {
    if (authStore.isAuthenticated) {
        router.push('/studio/upload')
    } else {
        router.push('/login?redirect=/login')
    }
}

const logout = async () => {
    try {
        await authStore.logout()
        router.push('/')
        showMenu.value = false
    } catch (error) {
        console.log(error)
    }
}
</script>
