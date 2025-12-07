<template>
    <div
        v-if="isOpen && isMobile"
        class="fixed inset-0 bg-black/80 z-50 lg:hidden"
        @click="closeMobile"
    />

    <aside
        :class="[
            'bg-white dark:bg-slate-950 border-r px-2 border-gray-200 dark:border-slate-800 overflow-auto flex flex-col',
            isMobile
                ? `fixed top-0 left-0 h-screen w-[280px] transition-transform duration-300 ease-in-out z-[60] ${isOpen ? 'translate-x-0' : '-translate-x-full'}`
                : 'h-full w-[280px] hidden lg:flex'
        ]"
    >
        <div
            class="flex items-center justify-between px-4 border-b border-gray-100 dark:border-slate-800 h-[70px]"
        >
            <div class="flex items-center gap-2">
                <img width="32" src="/nav-logo.png" alt="Loops Logo" class="rounded-full" />
                <span class="text-lg font-bold text-black dark:text-white">Loops</span>
                <span class="text-lg font-light text-black dark:text-white">Studio</span>
            </div>
            <button
                v-if="isMobile && isOpen"
                @click="closeMobile"
                class="rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800"
            >
                <i class="bx bx-x text-xl dark:text-slate-400"></i>
            </button>
        </div>
        <button
            @click="goUpload"
            class="mt-6 mx-4 bg-[#F02C56] hover:bg-[#d8234c] text-white rounded-lg py-3 flex items-center justify-center font-medium disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="route.path === '/studio/upload'"
        >
            <i class="bx bx-upload mr-2 text-xl"></i>
            Upload
        </button>

        <template v-for="section in sections" :key="section.title">
            <h3 class="mt-8 px-4 text-xs font-semibold text-gray-400 dark:text-slate-600 uppercase">
                {{ section.title }}
            </h3>
            <ul class="mt-2 space-y-1">
                <li v-for="link in section.links" :key="link.name">
                    <router-link
                        :to="link.path || '#'"
                        class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg"
                        active-class="bg-gray-100 dark:bg-slate-800"
                        @click="closeMobile"
                    >
                        <i :class="[link.icon, 'mr-3 text-xl text-gray-600 dark:text-slate-400']" />
                        {{ link.name }}
                    </router-link>
                </li>
            </ul>
        </template>

        <div class="flex-grow" />

        <button
            @click="goBack"
            class="mb-6 mx-4 flex items-center text-sm text-gray-600 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white cursor-pointer"
        >
            <i class="bx bx-arrow-back mr-2"></i>
            Back to Loops
        </button>
    </aside>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false
    }
})
const emit = defineEmits(['close'])

const windowWidth = ref(window.innerWidth)
const isMobile = computed(() => windowWidth.value < 1024)

const sections = computed(() => {
    const manageLinks = [
        { name: t('nav.home'), path: '/studio/', icon: 'bx bx-home' },
        { name: t('studio.posts'), path: '/studio/posts', icon: 'bx bx-video' }
    ]

    if (authStore.getUser?.follower_count > 100 && authStore.getUser?.post_count > 20) {
        manageLinks.push({
            name: t('studio.playlists'),
            path: '/studio/playlists',
            icon: 'bx bx-list-ul'
        })
    }

    return [
        {
            title: 'Manage',
            links: manageLinks
        }
    ]
})

const goUpload = () => {
    router.push('/studio/upload')
    closeMobile()
}

const goBack = () => {
    router.push('/')
    closeMobile()
}

const closeMobile = () => {
    if (isMobile.value) {
        emit('close')
    }
}

const onResize = () => {
    windowWidth.value = window.innerWidth
}

onMounted(() => {
    window.addEventListener('resize', onResize)
})

onUnmounted(() => {
    window.removeEventListener('resize', onResize)
})
</script>
