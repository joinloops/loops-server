<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-[100] overflow-y-auto" @click.self="close">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="fixed inset-0 bg-black/90 transition-opacity" @click="close"></div>

                    <div
                        class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-xl transition-all w-full max-w-md"
                    >
                        <div
                            class="absolute top-0 z-10 flex w-full items-center justify-between px-6 pt-4 border-b border-gray-200/30 dark:border-slate-700/30 bg-gray-50/70 dark:bg-slate-900/70 backdrop-blur-lg"
                        >
                            <div class="flex items-center space-x-3 relative">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.key"
                                    @click="switchTab(tab.key)"
                                    class="relative px-4 pb-[14px] pt-2 text-sm font-semibold transition-colors rounded-lg cursor-pointer"
                                    :class="
                                        activeTab === tab.key
                                            ? 'text-[#F02C56] dark:text-[#F02C56]'
                                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                                    "
                                >
                                    {{ tab.label }}
                                    <span
                                        v-if="activeTab === tab.key"
                                        class="absolute -bottom-[1px] left-0 right-0 h-[2px] bg-[#F02C56]"
                                    >
                                    </span>
                                </button>
                            </div>
                            <button
                                @click="close"
                                class="text-gray-400 pb-3 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            >
                                <XMarkIcon class="h-6 w-6" />
                            </button>
                        </div>

                        <div
                            ref="scrollContainer"
                            class="max-h-[60vh] overflow-y-auto relative pt-[70px]"
                        >
                            <div
                                v-if="initialLoading"
                                class="flex items-center justify-center py-12"
                            >
                                <Spinner class="h-8 w-8 text-[#F02C56]" />
                            </div>

                            <div
                                v-else-if="!initialLoading && users.length === 0"
                                class="flex flex-col items-center justify-center py-12 px-6"
                            >
                                <div
                                    class="w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-800 flex items-center justify-center mb-4"
                                >
                                    <HeartIcon
                                        v-if="activeTab === 'likes'"
                                        class="h-8 w-8 text-gray-400 dark:text-gray-600"
                                    />
                                    <ShareIcon
                                        v-else
                                        class="h-8 w-8 text-gray-400 dark:text-gray-600"
                                    />
                                </div>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">
                                    {{ activeTab === 'likes' ? 'No likes yet' : 'No shares yet' }}
                                </p>
                            </div>

                            <div v-else class="divide-y divide-gray-200 dark:divide-slate-800">
                                <TransitionGroup name="fade-in" tag="div">
                                    <div
                                        v-for="user in users"
                                        :key="user.id"
                                        class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                                    >
                                        <div class="flex items-center justify-between">
                                            <router-link
                                                :to="`/@${user.username}`"
                                                class="flex items-center flex-1 min-w-0"
                                                @click="close"
                                            >
                                                <img
                                                    :src="user.avatar"
                                                    :alt="user.username"
                                                    class="w-12 h-12 rounded-full flex-shrink-0"
                                                    @error="
                                                        $event.target.src =
                                                            '/storage/avatars/default.jpg'
                                                    "
                                                />
                                                <div class="ml-3 min-w-0 flex-1">
                                                    <div
                                                        class="font-semibold text-gray-900 dark:text-white truncate"
                                                    >
                                                        {{ user.username }}
                                                    </div>
                                                    <div
                                                        class="text-sm text-gray-600 dark:text-gray-400 truncate"
                                                    >
                                                        {{ user.name }}
                                                    </div>
                                                </div>
                                            </router-link>

                                            <button
                                                v-if="
                                                    authStore.isAuthenticated &&
                                                    user.id !== authStore.id
                                                "
                                                @click="toggleFollow(user)"
                                                class="ml-3 flex-shrink-0 px-4 py-1.5 text-sm font-semibold rounded-lg transition-colors"
                                                :class="
                                                    user.is_following
                                                        ? 'border border-[#F02C56] text-[#F02C56] hover:bg-red-50 dark:hover:bg-slate-800'
                                                        : 'bg-[#F02C56] text-white hover:bg-red-600'
                                                "
                                            >
                                                {{
                                                    user.is_following
                                                        ? $t('common.unfollow')
                                                        : $t('common.follow')
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </TransitionGroup>

                                <div
                                    v-if="hasMore"
                                    ref="loadMoreTrigger"
                                    class="px-6 py-4 flex items-center justify-center"
                                >
                                    <Spinner v-if="loadingMore" />
                                    <div v-else class="h-6"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useVideoStore } from '~/stores/video'
import { useAuthStore } from '~/stores/auth'
import { XMarkIcon, HeartIcon, ShareIcon } from '@heroicons/vue/24/outline'
import Spinner from '@/components/Spinner.vue'

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    videoId: {
        type: [Number, String],
        required: true
    },
    initialTab: {
        type: String,
        default: 'likes',
        validator: (value) => ['likes', 'shares'].includes(value)
    }
})

const emit = defineEmits(['close'])

const videoStore = useVideoStore()
const authStore = useAuthStore()

const activeTab = ref(props.initialTab)
const initialLoading = ref(false)
const loadingMore = ref(false)
const users = ref([])
const loadMoreTrigger = ref(null)
const scrollContainer = ref(null)
let observer = null

const tabs = [
    { key: 'likes', label: 'Likes' },
    { key: 'shares', label: 'Shares' }
]

const hasMore = computed(() => {
    return activeTab.value === 'likes' ? videoStore.likesHasMore : videoStore.sharesHasMore
})

const close = () => {
    emit('close')
}

const loadUsers = async (isLoadMore = false) => {
    if (isLoadMore) {
        loadingMore.value = true
    } else {
        initialLoading.value = true
    }

    try {
        const response =
            activeTab.value === 'likes'
                ? await videoStore.getVideoLikes(props.videoId)
                : await videoStore.getVideoShares(props.videoId)

        if (isLoadMore) {
            await nextTick()
            users.value = [...users.value, ...response.data]
        } else {
            users.value = response.data
        }
    } catch (error) {
        console.error('Error loading users:', error)
        if (!isLoadMore) {
            users.value = []
        }
    } finally {
        if (isLoadMore) {
            loadingMore.value = false
        } else {
            initialLoading.value = false
        }
    }
}

const setupIntersectionObserver = () => {
    if (observer) {
        observer.disconnect()
    }

    observer = new IntersectionObserver(
        (entries) => {
            const target = entries[0]
            if (
                target.isIntersecting &&
                hasMore.value &&
                !loadingMore.value &&
                !initialLoading.value
            ) {
                loadUsers(true)
            }
        },
        {
            root: scrollContainer.value,
            rootMargin: '100px',
            threshold: 0.1
        }
    )

    if (loadMoreTrigger.value) {
        observer.observe(loadMoreTrigger.value)
    }
}

const switchTab = (tab) => {
    if (activeTab.value === tab) return

    activeTab.value = tab
    users.value = []

    if (activeTab.value === 'likes') {
        videoStore.likesNextCursor = null
        videoStore.likesHasMore = false
    } else {
        videoStore.sharesNextCursor = null
        videoStore.sharesHasMore = false
    }

    if (scrollContainer.value) {
        scrollContainer.value.scrollTop = 0
    }

    loadUsers(false)
}

const toggleFollow = async (user) => {
    if (!authStore.isAuthenticated) {
        authStore.openAuthModal('login')
        return
    }

    try {
        if (user.is_following) {
            await videoStore.unfollowUser(user.id)
            user.is_following = false
        } else {
            await videoStore.followUser(user.id)
            user.is_following = true
        }
    } catch (error) {
        console.error('Error toggling follow:', error)
    }
}

watch(
    () => props.isOpen,
    async (newVal) => {
        if (newVal) {
            activeTab.value = props.initialTab
            videoStore.likesNextCursor = null
            videoStore.sharesNextCursor = null
            videoStore.likesHasMore = false
            videoStore.sharesHasMore = false
            users.value = []
            await loadUsers(false)

            await nextTick()
            setupIntersectionObserver()
        } else {
            users.value = []
            if (observer) {
                observer.disconnect()
            }
        }
    }
)

watch(loadMoreTrigger, (newVal) => {
    if (newVal && props.isOpen) {
        nextTick(() => {
            setupIntersectionObserver()
        })
    }
})

onBeforeUnmount(() => {
    if (observer) {
        observer.disconnect()
    }
})
</script>

<style scoped>
.fade-in-enter-active {
    transition: all 0.4s ease-out;
}

.fade-in-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.fade-in-enter-to {
    opacity: 1;
    transform: translateY(0);
}

.fade-in-move {
    transition: transform 0.4s ease;
}
</style>
