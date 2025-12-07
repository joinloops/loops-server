<template>
    <header class="bg-white dark:bg-gray-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex items-start justify-between gap-4 py-6">
                <div class="flex items-center gap-4">
                    <div
                        class="size-16 sm:size-20 rounded-xl bg-gray-100 dark:bg-gray-900 flex items-center justify-center"
                        aria-hidden="true"
                    >
                        <span
                            class="text-4xl sm:text-5xl font-medium text-gray-300 dark:text-gray-700"
                            >#</span
                        >
                    </div>

                    <div>
                        <h1
                            class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100"
                        >
                            #{{ id }}
                        </h1>
                        <p
                            v-if="totalResults > 0"
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ compactCount }}
                            {{ totalResults === 1 ? 'video' : 'videos' }}
                        </p>
                    </div>
                </div>

                <div v-if="!nsfw" class="flex items-center gap-1 sm:gap-2">
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

    <ReportModal />
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, inject } from 'vue'
import { ShareIcon, EllipsisHorizontalIcon, FlagIcon } from '@heroicons/vue/24/outline'
import { useUtils } from '@/composables/useUtils'
import { useReportModal } from '@/composables/useReportModal'

interface Props {
    id: string | number
    totalResults: number
    nsfw: boolean
}

interface AuthStore {
    isAuthenticated: boolean
    openAuthModal: (mode: string) => void
}

const props = defineProps<Props>()
const { formatNumber } = useUtils()
const { openReportModal } = useReportModal()

const authStore = inject<AuthStore>('authStore')!

const compactCount = computed(() => formatNumber(props.totalResults))

const menuOpen = ref(false)
const menuRef = ref<HTMLElement | null>(null)

function toggleMenu() {
    menuOpen.value = !menuOpen.value
}
function openMenu() {
    menuOpen.value = true
}
function closeMenu() {
    menuOpen.value = false
}

function handleClickOutside(e: MouseEvent) {
    if (!menuRef.value) return
    if (!menuRef.value.contains(e.target as Node)) {
        closeMenu()
    }
}
onMounted(() => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))

function onShare() {
    try {
        navigator.share?.({ title: `#${props.id}`, url: location.href })
    } catch {}
}

const handleReport = async () => {
    if (authStore.isAuthenticated) {
        await openReportModal('hashtag', props.id, window.location.href)
    } else {
        authStore.openAuthModal('login')
    }
    closeMenu()
}
</script>
