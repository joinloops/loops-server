<template>
    <MainLayout>
        <div
            v-if="!loaded"
            class="container mx-auto min-h-screen flex h-full bg-white dark:bg-gray-900 transition-colors"
        >
            <div class="flex flex-1 h-full justify-center items-center">
                <Spinner />
            </div>
        </div>

        <div
            v-else
            class="container mx-auto min-h-screen bg-white dark:bg-gray-900 transition-colors"
        >
            <header class="relative overflow-hidden py-20">
                <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
                <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mt-16">
                        <div class="text-center">
                            <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-6">
                                About
                            </h2>
                            <div
                                class="w-24 h-1 bg-gradient-to-r from-purple-500 to-blue-500 mx-auto rounded-full"
                            ></div>
                        </div>
                    </div>
                </div>
            </header>

            <section class="pb-20 bg-white dark:bg-gray-900">
                <div class="max-w-4xl mx-auto px-10 sm:px-6 lg:px-8">
                    <div
                        class="dyncon prose prose-lg max-w-none dark:prose-invert prose-p:text-xl prose-p:leading-relaxed prose-p:text-gray-600 dark:prose-p:text-gray-300 prose-p:mb-8"
                        v-html="pageContent"
                    ></div>
                </div>
            </section>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue'
import MainLayout from '@/layouts/FeedLayout.vue'

const loaded = ref(false)
const pageContent = ref('')

const axios = inject('axios')

const loadContent = async () => {
    await axios
        .get('/api/v1/page/content?slug=platform/about')
        .then((res) => {
            pageContent.value = res.data.data
        })
        .finally(() => {
            loaded.value = true
        })
}

onMounted(() => {
    loadContent()
})
</script>
