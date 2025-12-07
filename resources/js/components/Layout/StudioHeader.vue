<template>
    <div
        class="bg-white dark:bg-slate-950 flex items-center w-full h-[70px] z-10 border-b border-gray-200 dark:border-slate-800"
    >
        <div class="flex items-center justify-between w-full px-4 lg:px-6 mx-auto">
            <div class="flex items-center lg:w-[30%] w-auto">
                <button
                    @click="toggleMobileDrawer"
                    class="lg:hidden mr-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800"
                >
                    <i class="bx bx-menu text-2xl dark:text-slate-400"></i>
                </button>
            </div>

            <div
                class="flex items-center justify-end gap-2 lg:gap-3 lg:min-w-[275px] lg:max-w-[320px] w-auto"
            >
                <ThemeToggleButton class="mr-1" />
                <div v-if="authStore.isAuthenticated" class="hidden lg:flex items-center">
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
                                <span class="pl-2 font-semibold text-sm">Profile</span>
                            </router-link>
                            <div
                                @click="logout"
                                class="flex items-center justify-start py-3 px-1.5 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                            >
                                <i class="ic-outline-login text-[20px]"></i>
                                <span class="pl-2 font-semibold text-sm">Log out</span>
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
                        alt="User avatar"
                        @error="$event.target.src = '/storage/avatars/default.jpg'"
                    />
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, inject, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useSearchStore } from '@/stores/search'
import { debounce } from 'lodash'
import { useNotificationStore } from '~/stores/notifications'
import { useUtils } from '@/composables/useUtils'

const emit = defineEmits(['toggleMobileDrawer', 'openLogin'])

const { formatCount } = useUtils()
const router = useRouter()
const authStore = inject('authStore')
const searchStore = useSearchStore()
const notificationsStore = useNotificationStore()

const showMenu = ref(false)
const searchQuery = ref('')
const showDropdown = ref(false)
const showMobileSearch = ref(false)
const mobileSearchInput = ref(null)

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

// Load recent searches on component mount
onMounted(() => {
    // searchStore.loadRecentSearches()

    // Close dropdowns when clicking outside
    document.addEventListener('mouseup', (e) => {
        const searchContainer = document.querySelector('.search-container')
        const popupMenu = document.getElementById('PopupMenu')

        if (searchContainer && !searchContainer.contains(e.target)) {
            showDropdown.value = false
        }

        if (popupMenu && !popupMenu.contains(e.target)) {
            showMenu.value = false
        }

        // Close mobile search when clicking outside
        if (showMobileSearch.value && !e.target.closest('.absolute.top-full')) {
            closeMobileSearch()
        }
    })
})

// Debounced search function
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
        router.push('/upload')
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
