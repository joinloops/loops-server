<template>
    <div class="flex h-dvh w-full max-w-full overflow-hidden bg-white dark:bg-slate-950">
        <StudioSidebar :isOpen="isMobileDrawerOpen" @close="closeMobileDrawer" class="shrink-0" />

        <div class="flex min-w-0 flex-1 flex-col">
            <StudioHeader
                @toggleMobileDrawer="toggleMobileDrawer"
                @openLogin="openLoginModal"
                class="w-full min-w-0 shrink-0"
            />

            <main class="min-w-0 flex-1 overflow-x-hidden overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

import StudioHeader from '~/components/Layout/StudioHeader.vue'
import StudioSidebar from '~/components/Layout/StudioSidebar.vue'

const isMobileDrawerOpen = ref(false)

const toggleMobileDrawer = () => {
    isMobileDrawerOpen.value = !isMobileDrawerOpen.value
}

const closeMobileDrawer = () => {
    isMobileDrawerOpen.value = false
}

const handleResize = () => {
    if (window.innerWidth >= 1024) {
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
