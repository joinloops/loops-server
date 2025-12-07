<template>
    <div class="flex h-screen bg-white dark:bg-slate-950">
        <StudioSidebar
            :isOpen="isMobileDrawerOpen"
            @close="closeMobileDrawer"
            class="flex-shrink-0"
        />

        <div class="flex flex-col flex-1 h-screen lg:w-[calc(100%-260px)]">
            <StudioHeader
                @toggleMobileDrawer="toggleMobileDrawer"
                @openLogin="openLoginModal"
                class="flex-shrink-0"
            />

            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue'
import { storeToRefs } from 'pinia'
import StudioHeader from '~/components/Layout/StudioHeader.vue'
import StudioSidebar from '~/components/Layout/StudioSidebar.vue'

const appStore = inject('appStore')
const isMobileDrawerOpen = ref(false)

const windowWidth = ref(window.innerWidth)

const isMobile = computed(() => windowWidth.value < 1024)

const toggleMobileDrawer = () => {
    isMobileDrawerOpen.value = !isMobileDrawerOpen.value
}

const closeMobileDrawer = () => {
    isMobileDrawerOpen.value = false
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
