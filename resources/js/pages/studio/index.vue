<template>
    <StudioLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto px-6 py-8">
                <div
                    class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 rounded-lg"
                >
                    <div class="flex items-center gap-3">
                        <img
                            :src="user.avatar"
                            class="w-20 h-20 rounded-full border border-gray-200 dark:border-gray-700 p-1"
                            @error="$event.target.src = '/storage/avatars/default.jpg'"
                        />
                        <div class="flex flex-col gap-1">
                            <div class="text-lg font-bold dark:text-white">
                                {{ user.username }}
                            </div>

                            <div class="flex gap-2">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-bold text-black dark:text-white">{{
                                        formatCount(user.post_count)
                                    }}</span>
                                    {{ $t('studio.posts') }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">·</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-bold text-black dark:text-white">{{
                                        formatCount(user.likes_count)
                                    }}</span>
                                    {{ $t('studio.likes') }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">·</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-bold text-black dark:text-white">{{
                                        formatCount(user.follower_count)
                                    }}</span>
                                    {{ $t('studio.followers') }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">·</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-bold text-black dark:text-white">{{
                                        formatCount(user.following_count)
                                    }}</span>
                                    {{ $t('studio.following') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </StudioLayout>
</template>

<script setup>
import { ref, reactive, computed, nextTick, watch, inject, onMounted } from 'vue'
import { useUtils } from '@/composables/useUtils'
const authStore = inject('authStore')
const { formatCount, formatDate } = useUtils()

const user = computed(() => authStore.getUser)
</script>
