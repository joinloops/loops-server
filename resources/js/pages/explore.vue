<template>
    <MainLayout>
        <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
            <div
                class="bg-white dark:bg-gray-950 border-b border-l border-gray-200 dark:border-gray-800 -mt-2"
            >
                <div class="max-w-7xl mx-auto px-4 sm:px-6">
                    <div class="py-4">
                        <h1
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ $t("common.explore") }}
                        </h1>
                    </div>
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="mb-8">
                    <div
                        class="flex space-x-2 scrollbar-hide overflow-auto py-3"
                    >
                        <button
                            v-for="hashtag in hashtags"
                            :key="hashtag.id"
                            @click="setActiveHashtag(hashtag)"
                            :class="[
                                'flex-shrink-0 px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-300 ease-out cursor-pointer shadow-sm',
                                'hover:shadow-md hover:scale-105 active:scale-95',
                                'disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100',
                                activeHashtag?.id === hashtag.id
                                    ? 'bg-gradient-to-r from-blue-500 to-sky-600 text-white shadow-lg hover:from-blue-600 hover:to-sky-700 dark:from-blue-600 dark:to-sky-700 dark:hover:from-blue-700 dark:hover:to-sky-800'
                                    : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 hover:border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700 dark:hover:border-gray-600',
                            ]"
                            :disabled="loading"
                        >
                            <span class="flex items-center space-x-2">
                                <span>{{ hashtag.name }}</span>
                            </span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="loading"
                    class="flex justify-center items-center py-12"
                >
                    <div
                        class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"
                    ></div>
                </div>

                <div v-else-if="error" class="text-center py-12">
                    <div class="text-red-600 mb-4">{{ error }}</div>
                    <button
                        @click="fetchHashtags"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        {{ $t("common.retry") }}
                    </button>
                </div>

                <div
                    v-else-if="currentVideos.length > 0"
                    class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4"
                >
                    <router-link
                        v-for="video in currentVideos"
                        :to="`/v/${video.hid}`"
                        :key="video.id"
                        class="group cursor-pointer bg-white rounded-xl overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1"
                    >
                        <div class="relative aspect-[9/11] overflow-hidden">
                            <img
                                :src="video.media.thumbnail"
                                :alt="video.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            />

                            <div
                                class="absolute inset-0 bg-black/25 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center"
                            ></div>

                            <div class="absolute bottom-2 left-2 right-2">
                                <div class="flex justify-between items-end">
                                    <div
                                        class="flex items-center space-x-2 text-white text-xs"
                                    >
                                        <span
                                            class="text-white bg-opacity-50 px-2 py-1 rounded flex gap-1 items-center"
                                        >
                                            <HeartIcon class="h-5 w-5" />
                                            {{ video.likes }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </router-link>
                </div>

                <div v-else class="text-center py-12">
                    <div class="text-gray-500 mb-4">
                        {{ $t("explore.noVideosFoundForThisHashtag") }}
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup lang="ts">
import { onMounted, inject } from "vue";
import { storeToRefs } from "pinia";
import { HeartIcon } from "@heroicons/vue/24/outline";

const exploreStore = inject("exploreStore");

const { hashtags, activeHashtag, currentVideos, loading, error } =
    storeToRefs(exploreStore);

const { fetchHashtags, setActiveHashtag } = exploreStore;

onMounted(() => {
    fetchHashtags();
});
</script>
