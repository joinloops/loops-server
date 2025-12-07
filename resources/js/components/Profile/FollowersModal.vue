<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black/90 transition-opacity" @click="$emit('close')"></div>

            <div class="flex min-h-screen items-center justify-center p-4">
                <div
                    class="relative w-full max-w-lg transform rounded-lg bg-white dark:bg-slate-900 shadow-xl transition-all"
                >
                    <div class="border-b px-4 py-3 border-gray-200 dark:border-slate-800">
                        <div class="flex items-center justify-center">
                            <div class="flex justify-around flex-grow">
                                <button
                                    @click="activeTab = 'followers'"
                                    :class="[
                                        'text-lg cursor-pointer',
                                        activeTab === 'followers'
                                            ? 'text-black dark:text-slate-200 font-semibold'
                                            : 'text-gray-400 dark:text-slate-700'
                                    ]"
                                >
                                    {{ t('common.followers') }}
                                </button>
                                <button
                                    @click="activeTab = 'following'"
                                    :class="[
                                        'text-lg cursor-pointer',
                                        activeTab === 'following'
                                            ? 'text-black dark:text-slate-200 font-semibold'
                                            : 'text-gray-400 dark:text-slate-700'
                                    ]"
                                >
                                    {{ t('common.following') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div ref="containerRef" class="max-h-[60vh] overflow-y-auto">
                        <div class="min-h-[350px] transition-all duration-300 ease-in-out">
                            <Transition
                                enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition-all duration-150 ease-in"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div
                                    v-if="isInitialLoading"
                                    class="flex flex-col items-center justify-center py-12 h-[350px]"
                                >
                                    <div
                                        class="h-8 w-8 animate-spin rounded-full border-4 border-gray-300 border-t-[#F02C56] mb-4"
                                    ></div>
                                    <p class="text-gray-500 dark:text-slate-400">
                                        {{ t('common.loading') }}
                                        {{ activeTab }}...
                                    </p>
                                </div>
                            </Transition>

                            <div
                                v-if="!isInitialLoading"
                                class="transition-all duration-300 ease-in-out"
                            >
                                <TransitionGroup
                                    name="list"
                                    tag="div"
                                    enter-active-class="transition-all duration-150 ease-out"
                                    enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition-all duration-100 ease-in"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-90"
                                >
                                    <template v-if="activeTab === 'followers'">
                                        <div
                                            v-for="follower in followers"
                                            :key="`follower-${follower.id}`"
                                        >
                                            <div
                                                class="flex items-center justify-between py-2 px-5 hover:bg-gray-100 dark:hover:bg-slate-800 border-b border-gray-200 dark:border-slate-800 cursor-pointer transition-colors duration-150"
                                                @click="$emit('gotoProfile', follower.username)"
                                            >
                                                <ProfileListCard :account="follower" />
                                            </div>
                                        </div>
                                    </template>

                                    <template v-if="activeTab === 'following'">
                                        <div
                                            v-for="follower in following"
                                            :key="`following-${follower.id}`"
                                        >
                                            <div
                                                class="flex items-center justify-between py-2 px-5 hover:bg-gray-100 dark:hover:bg-slate-800 border-b border-gray-200 dark:border-slate-800 cursor-pointer transition-colors duration-150"
                                                @click="$emit('gotoProfile', follower.username)"
                                            >
                                                <ProfileListCard :account="follower" />
                                            </div>
                                        </div>
                                    </template>
                                </TransitionGroup>

                                <Transition
                                    enter-active-class="transition-all duration-150 ease-out"
                                    enter-from-class="opacity-0 scale-90"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition-all duration-100 ease-in"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-90"
                                >
                                    <div
                                        v-if="isPaginationLoading"
                                        class="flex justify-center py-4"
                                    >
                                        <div
                                            class="h-6 w-6 animate-spin rounded-full border-4 border-gray-300 border-t-[#F02C56]"
                                        ></div>
                                    </div>
                                </Transition>

                                <Transition
                                    enter-active-class="transition-all duration-200 ease-out"
                                    enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition-all duration-150 ease-in"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-95"
                                >
                                    <div
                                        v-if="showEndMessage"
                                        class="py-4 text-center text-gray-500 dark:text-slate-400"
                                    >
                                        {{ t('profile.youveReachedTheEndOfThisList') }}
                                        ✨
                                    </div>
                                </Transition>

                                <Transition
                                    enter-active-class="transition-all duration-250 ease-out"
                                    enter-from-class="opacity-0 scale-90"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition-all duration-150 ease-in"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-90"
                                >
                                    <div
                                        v-if="showEmptyState"
                                        class="flex flex-col items-center justify-center text-gray-500 dark:text-slate-400 h-[350px]"
                                    >
                                        <div
                                            class="mb-6 transition-transform duration-300 hover:scale-105"
                                        >
                                            <i
                                                class="bx bx-group text-6xl text-gray-300 dark:text-slate-600"
                                            ></i>
                                        </div>
                                        <h3
                                            class="text-lg font-medium text-gray-900 dark:text-slate-200 mb-2 transition-colors duration-200"
                                        >
                                            {{ emptyStateTitle }}
                                        </h3>
                                        <p
                                            class="text-center max-w-sm text-gray-500 dark:text-slate-400 leading-relaxed transition-colors duration-200"
                                        >
                                            {{ emptyStateMessage }}
                                        </p>
                                    </div>
                                </Transition>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useInfiniteScroll } from '@vueuse/core'
import { useProfileStore } from '@/stores/profile'
import Avatar from './Avatar.vue'
import ProfileListCard from './ProfileListCard.vue'
import { useI18n } from 'vue-i18n'
const props = defineProps({
    show: {
        type: Boolean,
        required: true
    }
})

const emit = defineEmits(['close', 'gotoProfile'])

const { t } = useI18n()
const containerRef = ref(null)
const activeTab = ref('followers')
const isInitialLoading = ref(false)
const isPaginationLoading = ref(false)

const profileStore = useProfileStore()
const { followers, following } = storeToRefs(profileStore)

const page = ref({
    followers: 1,
    following: 1
})

const hasMore = ref({
    followers: true,
    following: true
})

const hasLoadedInitially = ref({
    followers: false,
    following: false
})

const currentList = computed(() => {
    return activeTab.value === 'followers' ? followers.value : following.value
})

const showEndMessage = computed(() => {
    return (
        !hasMore.value[activeTab.value] &&
        currentList.value.length > 0 &&
        hasLoadedInitially.value[activeTab.value] &&
        !isInitialLoading.value
    )
})

const showEmptyState = computed(() => {
    return (
        hasLoadedInitially.value[activeTab.value] &&
        currentList.value.length === 0 &&
        !isInitialLoading.value &&
        !isPaginationLoading.value
    )
})

const emptyStateTitle = computed(() => {
    return activeTab.value === 'followers'
        ? t('profile.noFollowersYet')
        : t('profile.notFollowingAnyone')
})

const emptyStateMessage = computed(() => {
    return activeTab.value === 'followers'
        ? t('profile.whenPeopleFollowThisAccount')
        : t('profile.whenThisAccountFollowsSomeone')
})

const loadMore = async () => {
    if (isInitialLoading.value || isPaginationLoading.value || !hasMore.value[activeTab.value])
        return

    const isInitial = !hasLoadedInitially.value[activeTab.value]

    if (isInitial) {
        isInitialLoading.value = true
    } else {
        isPaginationLoading.value = true
    }

    try {
        const nextCursor =
            activeTab.value === 'followers'
                ? profileStore.followersNextCursor
                : profileStore.followingNextCursor

        const result = await (activeTab.value === 'followers'
            ? profileStore.getFollowers(profileStore.id, nextCursor)
            : profileStore.getFollowing(profileStore.id, nextCursor))

        hasLoadedInitially.value[activeTab.value] = true

        if (!nextCursor) {
            hasMore.value[activeTab.value] = false
        } else {
            page.value[activeTab.value]++
        }
    } catch (error) {
        hasLoadedInitially.value[activeTab.value] = true
        hasMore.value[activeTab.value] = false
        console.error(`Error loading ${activeTab.value}:`, error)
    } finally {
        isInitialLoading.value = false
        isPaginationLoading.value = false
    }
}

watch(activeTab, (newTab) => {
    containerRef.value?.scrollTo({ top: 0 })

    if (!hasLoadedInitially.value[newTab]) {
        loadMore()
    }
})

watch(
    () => props.show,
    (isShown) => {
        if (isShown && !hasLoadedInitially.value[activeTab.value]) {
            loadMore()
        }
    }
)

useInfiniteScroll(
    containerRef,
    async () => {
        if (
            !isInitialLoading.value &&
            !isPaginationLoading.value &&
            hasMore.value[activeTab.value]
        ) {
            await loadMore()
        }
    },
    { distance: 10 }
)
</script>
