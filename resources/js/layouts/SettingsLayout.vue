<template>
    <Header @toggleMobileDrawer="toggleMobileDrawer" />
    <Sidebar v-if="isMobile" :isOpen="isMobileDrawerOpen" @close="closeMobileDrawer" />
    <div class="pt-[70px] lg:pt-[80px] flex mx-auto h-screen max-w-[1140px]">
        <SettingsSidebar class="hidden lg:flex" />
        <main class="flex-1 overflow-y-auto no-scrollbar pb-10">
            <div class="bg-gray-50 lg:bg-gray-100 dark:bg-gray-900 rounded-xl">
                <slot></slot>
            </div>
        </main>
    </div>
</template>

<script setup>
import Header from '@/components/Layout/Header.vue'
import SettingsSidebar from '@/components/Settings/Sidebar.vue'
import Sidebar from '@/components/Layout/Sidebar.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
const isMobileDrawerOpen = ref(false)
const showLoginModal = ref(false)
const windowWidth = ref(window.innerWidth)

const isMobile = computed(() => windowWidth.value < 1024)

const toggleMobileDrawer = () => {
    isMobileDrawerOpen.value = !isMobileDrawerOpen.value
}

const closeMobileDrawer = () => {
    isMobileDrawerOpen.value = false
}

const openLoginModal = () => {
    showLoginModal.value = true
}

const closeLoginModal = () => {
    showLoginModal.value = false
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
