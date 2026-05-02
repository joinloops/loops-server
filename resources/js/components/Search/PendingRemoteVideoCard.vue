<template>
    <div
        class="rounded-2xl border border-blue-200/70 dark:border-blue-900/60 bg-gradient-to-b from-blue-50/80 to-blue-100/50 dark:from-blue-950/30 dark:to-blue-900/20 backdrop-blur p-6 lg:p-8"
        role="status"
        aria-live="polite"
    >
        <div class="flex flex-col items-center text-center max-w-xl mx-auto">
            <div class="relative mb-5">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 dark:from-blue-500 dark:to-blue-600 shadow-lg shadow-blue-600/20 dark:shadow-blue-500/30"
                >
                    <VideoCameraIcon class="h-7 w-7 text-white" />
                </div>
                <div
                    class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:bg-gray-900 ring-2 ring-blue-100 dark:ring-blue-900"
                >
                    <ArrowPathIcon
                        class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400 animate-spin"
                    />
                </div>
            </div>

            <h3 class="text-lg lg:text-xl font-semibold text-gray-900 dark:text-white mb-2">
                Processing remote video
            </h3>

            <p class="text-sm lg:text-base text-gray-700 dark:text-gray-300 mb-4">
                We're fetching this video from its home server. This usually takes a few seconds.
            </p>

            <div
                v-if="pending.profile"
                class="inline-flex items-center gap-2 rounded-xl bg-white dark:bg-white/5 px-3 py-2 ring-1 ring-black/5 dark:ring-white/10"
            >
                <img
                    v-if="pending.profile.avatar"
                    :src="pending.profile.avatar"
                    :alt="pending.profile.username"
                    class="h-6 w-6 rounded-full"
                />
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    by
                    <span class="font-medium text-gray-900 dark:text-white">
                        @{{ pending.profile.username }}
                    </span>
                </span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { VideoCameraIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

defineProps({
    pending: {
        type: Object,
        required: true
    }
})
</script>
