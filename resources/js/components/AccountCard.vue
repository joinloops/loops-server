<template>
    <div
        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4 w-64 shadow-sm hover:shadow-md transition-shadow flex flex-col"
    >
        <div class="flex flex-col items-center text-center mb-4 flex-1">
            <div class="relative mb-3">
                <img
                    :src="account.avatar || '/storage/avatars/default.jpg'"
                    :alt="`${account.username}'s avatar`"
                    class="w-16 h-16 rounded-full object-cover border-2 border-gray-100"
                    @error="onImageError"
                />
            </div>

            <div class="w-full">
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm truncate mb-1">
                    {{ account.name || account.username }}
                </h3>
                <p class="text-gray-500 dark:text-gray-400 text-xs mb-2">@{{ account.username }}</p>

                <p
                    v-if="account.bio"
                    class="text-gray-600 text-xs leading-relaxed line-clamp-2 mb-3"
                >
                    {{ account.bio }}
                </p>

                <div class="flex items-center justify-center gap-4 text-xs text-gray-500 mb-3">
                    <span v-if="account.follower_count !== undefined">
                        <span class="font-medium text-gray-900 dark:text-gray-300">{{
                            formatCount(account.follower_count)
                        }}</span>
                        followers
                    </span>
                    <span v-if="account.post_count !== undefined">
                        <span class="font-medium text-gray-900 dark:text-gray-300">{{
                            formatCount(account.post_count)
                        }}</span>
                        posts
                    </span>
                </div>
            </div>
        </div>

        <button
            @click="handleToggleFollow"
            :disabled="isLoading"
            :class="[
                'w-full py-2 px-4 rounded-lg text-sm font-medium transition-colors duration-200 mt-auto',
                isFollowing
                    ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-300'
                    : 'bg-blue-500 text-white hover:bg-blue-600 shadow-sm',
                isLoading && 'opacity-50 cursor-not-allowed'
            ]"
        >
            <span v-if="isLoading" class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                Loading...
            </span>
            <span v-else>
                {{ isFollowing ? 'Following' : 'Follow' }}
            </span>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    account: {
        type: Object,
        required: true
    },
    isFollowing: {
        type: Boolean,
        default: false
    },
    isLoading: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['follow', 'unfollow'])

const handleToggleFollow = () => {
    if (props.isLoading) return

    if (props.isFollowing) {
        emit('unfollow', props.account)
    } else {
        emit('follow', props.account)
    }
}

const onImageError = (event) => {
    event.target.src = '/storage/avatars/default.jpg'
}

const formatCount = (count) => {
    if (count >= 1000000) {
        return (count / 1000000).toFixed(1) + 'M'
    } else if (count >= 1000) {
        return (count / 1000).toFixed(1) + 'K'
    }
    return count?.toString() || '0'
}
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
