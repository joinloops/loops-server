<template>
    <div
        class="flex justify-between mx-auto w-full bg-white dark:bg-slate-950 px-0 loops-feed-layout"
    >
        <div class="lg:px-2.5">
            <Sidebar
                :isOpen="isMobileDrawerOpen"
                @close="closeMobileDrawer"
                @openLogin="openLoginModal"
            />
        </div>
        <div class="w-full lg:w-[calc(100%-260px)]">
            <slot />
        </div>
        <MobileHeader @toggleMobileDrawer="toggleMobileDrawer" />
        <MobileNav />
    </div>

    <ReportModal />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { storeToRefs } from 'pinia'
import Header from '~/components/Layout/Header.vue'
import Sidebar from '~/components/Layout/Sidebar.vue'
import LoginModal from '~/components/Layout/LoginModal.vue'
import MobileNav from '@/components/Layout/MobileNav.vue'
import MobileHeader from '@/components/Layout/MobileHeader.vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const appStore = inject('appStore')
const isMobileDrawerOpen = ref(false)
const showLoginModal = ref(false)
const windowWidth = ref(window.innerWidth)
const { isLoginOpen } = storeToRefs(appStore)

const isMobile = computed(() => windowWidth.value < 1024)

const toggleMobileDrawer = () => {
    isMobileDrawerOpen.value = !isMobileDrawerOpen.value
}

const closeMobileDrawer = () => {
    isMobileDrawerOpen.value = false
}

const openLoginModal = () => {
    authStore.openAuthModal()
}

const closeLoginModal = () => {
    appStore.toggleLoginForm()
}

const handleResize = () => {
    windowWidth.value = window.innerWidth
    if (windowWidth.value >= 1024) {
        isMobileDrawerOpen.value = false
    }
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
})
</script>
