<template>
    <Header v-if="isMobile" @toggleMobileDrawer="toggleMobileDrawer" @openLogin="openLoginModal" />
    <div class="flex justify-between mx-auto w-full bg-white dark:bg-slate-950 lg:px-2.5 px-0">
        <div>
            <Sidebar
                :isOpen="isMobileDrawerOpen"
                @close="closeMobileDrawer"
                @openLogin="openLoginModal"
            />
        </div>
        <div class="w-full pt-[70px] lg:pt-5 lg:w-[calc(100%-260px)]">
            <slot />
        </div>
        <MobileNav />
    </div>

    <Teleport to="body">
        <LoginModal v-if="isLoginOpen" @close="closeLoginModal" />
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { storeToRefs } from 'pinia'
import Header from '~/components/Layout/Header.vue'
import Sidebar from '~/components/Layout/Sidebar.vue'
import LoginModal from '~/components/Layout/LoginModal.vue'
import MobileNav from '@/components/Layout/MobileNav.vue'

const appStore = inject('appStore')
const isMobileDrawerOpen = ref(false)

const { isLoginOpen, toggleLoginForm } = storeToRefs(appStore)

const windowWidth = ref(window.innerWidth)

const isMobile = computed(() => windowWidth.value < 1024)

const toggleMobileDrawer = () => {
    isMobileDrawerOpen.value = !isMobileDrawerOpen.value
}

const closeMobileDrawer = () => {
    isMobileDrawerOpen.value = false
}

const openLoginModal = () => {
    toggleLoginForm()
}

const closeLoginModal = () => {
    toggleLoginForm()
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
