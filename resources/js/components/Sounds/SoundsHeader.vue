<template>
    <header class="bg-white dark:bg-gray-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex items-start justify-between gap-4 py-6">
                <div class="flex items-center gap-4">
                    <div
                        class="size-16 sm:size-20 lg:size-24 rounded-xl flex items-center justify-center"
                        style="background-color: #f02c56"
                        aria-hidden="true"
                    >
                        <svg
                            class="w-8 h-8 sm:w-10 sm:h-10 text-white"
                            fill="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"
                            />
                        </svg>
                    </div>

                    <div>
                        <h1
                            class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="originalVideo"
                            class="mt-1 text-sm lg:text-base text-gray-500 dark:text-gray-400"
                        >
                            <router-link
                                :to="`/@${originalVideo.account.username}`"
                                class="font-bold hover:underline"
                            >
                                @{{ originalVideo.account.username }}
                            </router-link>
                            <span v-if="totalResults > 0">
                                · {{ compactCount }}
                                {{ totalResults === 1 ? 'video' : 'videos' }}</span
                            >
                            <span v-if="duration"> · {{ duration }}s</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1 sm:gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-full p-2 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-300 dark:focus-visible:ring-gray-700 dark:focus-visible:ring-offset-gray-950"
                        aria-label="Share"
                        @click="onShare"
                    >
                        <ShareIcon class="w-6 h-6 text-gray-600 dark:text-gray-500" />
                    </button>

                    <div class="relative" ref="menuRef">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-full p-2 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-300 dark:focus-visible:ring-gray-700 dark:focus-visible:ring-offset-gray-950"
                            aria-haspopup="menu"
                            :aria-expanded="menuOpen"
                            aria-label="More"
                            @click="toggleMenu"
                            @keydown.down.prevent="openMenu"
                            @keydown.escape.prevent="closeMenu"
                        >
                            <EllipsisHorizontalIcon
                                class="w-8 h-8 text-gray-600 dark:text-gray-500"
                            />
                        </button>

                        <transition
                            enter-active-class="transition ease-out duration-100"
                            enter-from-class="opacity-0 translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 translate-y-1"
                        >
                            <div
                                v-if="menuOpen"
                                class="absolute right-0 mt-2 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg ring-1 ring-black/5 dark:bg-gray-950 dark:border-gray-800"
                                role="menu"
                                tabindex="-1"
                            >
                                <button
                                    @click="handleReport"
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                                >
                                    <FlagIcon class="w-4 h-4 mr-3" />
                                    {{ $t('common.report') }}
                                </button>
                            </div>
                        </transition>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, inject } from 'vue'
import { ShareIcon, EllipsisHorizontalIcon, FlagIcon } from '@heroicons/vue/24/outline'
import { useUtils } from '@/composables/useUtils'
import { useReportModal } from '@/composables/useReportModal'

const props = defineProps({
    id: [String, Number],
    totalResults: Number,
    duration: Number,
    originalVideo: Object
})

const { formatNumber } = useUtils()
const { openReportModal } = useReportModal()
const authStore = inject('authStore')

const compactCount = computed(() => formatNumber(props.totalResults))

const title = computed(() => {
    if (props.originalVideo?.account?.username) {
        return `Original Sound - ${props.originalVideo.account.username}`
    }
    return 'Original Sound'
})

const menuOpen = ref(false)
const menuRef = ref(null)

function toggleMenu() {
    menuOpen.value = !menuOpen.value
}
function openMenu() {
    menuOpen.value = true
}
function closeMenu() {
    menuOpen.value = false
}

function handleClickOutside(e) {
    if (!menuRef.value) return
    if (!menuRef.value.contains(e.target)) {
        closeMenu()
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))

function onShare() {
    try {
        navigator.share?.({
            title: title.value,
            url: location.href
        })
    } catch {}
}

const handleReport = async () => {
    if (authStore.isAuthenticated) {
        await openReportModal('sound', props.id, window.location.href)
    } else {
        authStore.openAuthModal('login')
    }
    closeMenu()
}
</script>
