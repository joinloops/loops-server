<template>
    <div class="w-full">
        <div class="mb-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-300 mb-1">
                Suggested for you
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">Discover accounts you might like</p>
        </div>

        <div v-if="isLoading" class="flex justify-center py-8">
            <Spinner />
        </div>

        <div v-else-if="error" class="text-center py-8 text-gray-500">
            Failed to load suggestions
        </div>

        <div v-else-if="accounts.length > 0" class="relative">
            <!-- Carousel container -->
            <div
                ref="carouselRef"
                class="flex gap-4 overflow-x-auto scrollbar-hide snap-x snap-mandatory pb-4"
                style="scroll-behavior: smooth"
            >
                <AccountCard
                    v-for="account in accounts"
                    :key="account.id"
                    :account="account"
                    :is-following="followingStates[account.id]"
                    :is-loading="loadingStates[account.id]"
                    @follow="handleFollow"
                    @unfollow="handleUnfollow"
                    class="snap-start flex-shrink-0"
                />
            </div>

            <button
                v-if="showNavigation && canScrollLeft"
                @click="scrollLeft"
                class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-2 bg-white rounded-full p-2 shadow-lg border hover:bg-gray-50 transition-colors z-10"
                aria-label="Scroll left"
            >
                <svg
                    class="w-4 h-4 text-gray-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 19l-7-7 7-7"
                    />
                </svg>
            </button>

            <button
                v-if="showNavigation && canScrollRight"
                @click="scrollRight"
                class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-2 bg-white rounded-full p-2 shadow-lg border hover:bg-gray-50 transition-colors z-10"
                aria-label="Scroll right"
            >
                <svg
                    class="w-4 h-4 text-gray-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5l7 7-7 7"
                    />
                </svg>
            </button>
        </div>

        <div v-else class="text-center py-8 text-gray-500">No suggestions available</div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useSuggestedAccounts } from '~/composables/useSuggestedAccounts'
import { useFollowAccount } from '~/composables/useFollowAccount'
import AccountCard from './AccountCard.vue'

const emit = defineEmits(['account-followed', 'account-unfollowed'])

// Composables
const { data: suggestedData, isLoading, error, refetch } = useSuggestedAccounts()
const { followAccount, unfollowAccount } = useFollowAccount()

// Reactive data
const carouselRef = ref(null)
const followingStates = reactive({})
const loadingStates = reactive({})
const canScrollLeft = ref(false)
const canScrollRight = ref(false)
const showNavigation = ref(false)

// Computed
const accounts = computed(() => suggestedData.value?.data || [])

const handleFollow = async (account) => {
    if (loadingStates[account.id]) return

    loadingStates[account.id] = true

    try {
        await followAccount(account.id)
        followingStates[account.id] = true
        emit('account-followed', account.id)
    } catch (error) {
        console.error('Failed to follow account:', error)
    } finally {
        loadingStates[account.id] = false
    }
}

const handleUnfollow = async (account) => {
    if (loadingStates[account.id]) return

    loadingStates[account.id] = true

    try {
        await unfollowAccount(account.id)
        followingStates[account.id] = false
        emit('account-unfollowed', account.id)
    } catch (error) {
        console.error('Failed to unfollow account:', error)
    } finally {
        loadingStates[account.id] = false
    }
}

const scrollLeft = () => {
    if (carouselRef.value) {
        carouselRef.value.scrollBy({ left: -300, behavior: 'smooth' })
    }
}

const scrollRight = () => {
    if (carouselRef.value) {
        carouselRef.value.scrollBy({ left: 300, behavior: 'smooth' })
    }
}

const updateScrollButtons = () => {
    if (!carouselRef.value) return

    const { scrollLeft, scrollWidth, clientWidth } = carouselRef.value
    canScrollLeft.value = scrollLeft > 0
    canScrollRight.value = scrollLeft < scrollWidth - clientWidth - 1
}

const handleResize = () => {
    if (!carouselRef.value) return

    const { scrollWidth, clientWidth } = carouselRef.value
    showNavigation.value = scrollWidth > clientWidth
    updateScrollButtons()
}

// Lifecycle
onMounted(() => {
    // Initialize following states
    accounts.value.forEach((account) => {
        followingStates[account.id] = account.is_following || false
        loadingStates[account.id] = false
    })

    // Setup scroll event listener
    if (carouselRef.value) {
        carouselRef.value.addEventListener('scroll', updateScrollButtons)
        window.addEventListener('resize', handleResize)

        // Initial check
        setTimeout(handleResize, 100)
    }
})

onUnmounted(() => {
    if (carouselRef.value) {
        carouselRef.value.removeEventListener('scroll', updateScrollButtons)
    }
    window.removeEventListener('resize', handleResize)
})
</script>

<style scoped>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
