<template>
    <header
        v-if="isMobileView"
        class="fixed top-0 left-0 right-0 z-50"
        :class="{
            'bg-gradient-to-b from-black/60 via-black/30 to-transparent safe-area-top':
                showTabsAndSearch
        }"
    >
        <div class="flex items-center justify-between px-4 h-14">
            <button
                @click="toggleMenu"
                class="flex items-center justify-center w-10 h-10 text-white transition-colors active:opacity-70"
                aria-label="Menu"
            >
                <i class="bx bx-menu text-3xl"></i>
            </button>

            <div v-if="showTabsAndSearch" class="flex items-center gap-6">
                <button
                    @click="setActiveTab('following')"
                    class="relative px-3 py-2 text-base font-semibold transition-colors"
                    :class="activeTab === 'following' ? 'text-white' : 'text-white/60'"
                >
                    {{ $t('common.following') }}
                    <div
                        v-if="activeTab === 'following'"
                        class="absolute bottom-0 left-0 right-0 h-0.5 bg-white rounded-full"
                    ></div>
                </button>

                <button
                    @click="setActiveTab('foryou')"
                    class="relative px-3 py-2 text-base font-semibold transition-colors"
                    :class="activeTab === 'foryou' ? 'text-white' : 'text-white/60'"
                >
                    {{ $t('nav.forYou') }}
                    <div
                        v-if="activeTab === 'foryou'"
                        class="absolute bottom-0 left-0 right-0 h-0.5 bg-white rounded-full"
                    ></div>
                </button>
            </div>

            <button
                v-if="showTabsAndSearch"
                @click="openSearch"
                class="flex items-center justify-center w-10 h-10 text-white transition-colors active:opacity-70"
                aria-label="Search"
            >
                <i class="bx bx-search text-2xl"></i>
            </button>
        </div>
    </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const isMobileView = ref(false)
const activeTab = ref(route.path == '/' ? 'foryou' : 'following')

const emit = defineEmits(['toggleMobileDrawer', 'openLogin'])

const showTabsAndSearch = computed(() => {
    return route.path === '/' || route.path === '/feed/following'
})

const toggleMenu = () => {
    emit('toggleMobileDrawer')
}

const closeMenu = () => {
    isMenuOpen.value = false
    document.body.style.overflow = ''
}

const setActiveTab = (tab) => {
    activeTab.value = tab
    router.push(tab === 'foryou' ? '/' : '/feed/following')
}

const openSearch = () => {
    router.push('/search')
}

const checkMobileView = () => {
    isMobileView.value = window.innerWidth < 768
}

onMounted(() => {
    checkMobileView()
    window.addEventListener('resize', checkMobileView)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobileView)
    document.body.style.overflow = ''
})
</script>

<style scoped>
.safe-area-top {
    padding-top: env(safe-area-inset-top);
}

.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom);
}

.menu-enter-active,
.menu-leave-active {
    transition: opacity 0.3s ease;
}

.menu-enter-from,
.menu-leave-to {
    opacity: 0;
}

.menu-enter-active .absolute.left-0,
.menu-leave-active .absolute.left-0 {
    transition: transform 0.3s ease;
}

.menu-enter-from .absolute.left-0,
.menu-leave-to .absolute.left-0 {
    transform: translateX(-100%);
}
</style>
