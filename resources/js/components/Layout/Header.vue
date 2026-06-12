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

            <div
                class="flex items-center justify-end gap-2 lg:gap-3 lg:min-w-[275px] lg:max-w-[320px] w-auto"
            >
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

                <!-- Cart Button -->
                <button
                    v-if="authStore.isAuthenticated"
                    @click="openCart"
                    class="relative hidden lg:flex items-center text-gray-600 dark:text-slate-400 hover:text-[#F02C56] dark:hover:text-[#F02C56] p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                >
                    <i class="bx bx-cart text-[22px]"></i>
                    <span
                        v-if="cartCount > 0"
                        class="absolute -top-0.5 -right-0.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-[#F02C56] rounded-full"
                    >
                        {{ displayCount(cartCount) }}
                    </span>
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
    </div>

    <CartDrawer
        :show="cartOpen"
        @close="closeCart"
        @checkout-success="onCheckoutSuccess"
    />
</template>

<script setup>
import { ref, onMounted, inject, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useSearchStore } from '@/stores/search'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '~/stores/notifications'
import { useUtils } from '@/composables/useUtils'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import CartDrawer from '@/components/Commerce/CartDrawer.vue'
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
const cartOpen = ref(false)
const cartCount = ref(0)

async function fetchCartCount() {
    try {
        const { data } = await axios.get('/api/v1/commerce/cart/count')
        cartCount.value = data.count || 0
    } catch (e) {
        if (e.response?.status !== 401) {
            console.warn('Failed to fetch cart count:', e)
        }
    }
}

function openCart() {
    cartOpen.value = true
}

function closeCart() {
    cartOpen.value = false
}

function onCheckoutSuccess() {
    cartOpen.value = false
    fetchCartCount()
}

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
    fetchCartCount()
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

const handleSearch = useDebounceFn(() => {
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
