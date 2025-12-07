<template>
    <div class="space-y-3">
        <router-link
            v-for="user in users"
            :key="user.id"
            :to="`/@${user.username}`"
            class="flex items-center justify-between p-4 bg-white dark:bg-gray-900 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
        >
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <Avatar :src="user.avatar" :width="48" class="flex-shrink-0" />

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <h3 class="font-semibold text-gray-900 dark:text-white truncate">
                            {{ user.username }}
                        </h3>
                    </div>
                    <p v-if="user.name" class="text-sm text-gray-500 dark:text-gray-400 truncate">
                        {{ user.name }}
                    </p>
                    <div
                        v-if="user.follower_count !== undefined"
                        class="flex items-center gap-4 mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                        <span>
                            <strong class="text-gray-900 dark:text-white">{{
                                formatNumber(user.follower_count)
                            }}</strong>
                            {{ $t('common.followers') }}
                        </span>
                        <span v-if="user.post_count !== undefined">
                            <strong class="text-gray-900 dark:text-white">{{
                                formatNumber(user.post_count)
                            }}</strong>
                            {{ $t('common.videos') }}
                        </span>
                    </div>
                </div>
            </div>

            <AnimatedButton
                v-if="!user.is_following && user.id !== currentUserId"
                @click.prevent="handleFollow(user)"
                :disabled="followLoading[user.id]"
                variant="primary"
                size="sm"
                class="px-6"
            >
                {{ followLoading[user.id] ? $t('common.loading') : $t('common.follow') }}
            </AnimatedButton>

            <AnimatedButton
                v-else-if="user.is_following"
                @click.prevent="handleUnfollow(user)"
                :disabled="followLoading[user.id]"
                variant="primaryOutline"
                size="sm"
            >
                {{ followLoading[user.id] ? $t('common.loading') : $t('common.unfollow') }}
            </AnimatedButton>
        </router-link>
    </div>
</template>

<script setup>
import { inject, ref } from 'vue'
import { CheckBadgeIcon } from '@heroicons/vue/24/solid'
import { useUtils } from '@/composables/useUtils'
import { useSearchStore } from '@/stores/search'
import AnimatedButton from '../AnimatedButton.vue'

const props = defineProps({
    users: {
        type: Array,
        required: true
    }
})

const authStore = inject('authStore')
const searchStore = useSearchStore()
const { formatNumber } = useUtils()

const currentUserId = authStore.user?.id
const followLoading = ref({})

const handleFollow = async (user) => {
    followLoading.value[user.id] = true

    const result = await searchStore.followUser(user.id)

    if (!result.success) {
        console.error('Failed to follow user:', result.error)
    }

    followLoading.value[user.id] = false
}

const handleUnfollow = async (user) => {
    followLoading.value[user.id] = true

    const result = await searchStore.unfollowUser(user.id)

    if (!result.success) {
        console.error('Failed to unfollow user:', result.error)
    }

    followLoading.value[user.id] = false
}
</script>
