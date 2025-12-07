<template>
    <MainLayout>
        <div
            v-if="loading"
            class="container mx-auto min-h-screen flex h-full bg-white dark:bg-gray-900 transition-colors"
        >
            <div class="flex flex-1 h-full justify-center items-center">
                <Spinner />
            </div>
        </div>

        <div
            v-else-if="error"
            class="relative min-h-full w-full bg-white dark:bg-slate-950 px-6 py-24 sm:py-32 lg:px-8 overflow-hidden"
        >
            <div class="absolute inset-0 -z-10">
                <div
                    class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-gradient-to-r from-[#F02C56]/10 to-purple-500/10 rounded-full blur-3xl"
                ></div>
                <div
                    class="absolute bottom-0 right-1/4 w-64 h-64 bg-gradient-to-r from-blue-500/10 to-[#F02C56]/10 rounded-full blur-3xl"
                ></div>
            </div>

            <div class="flex min-h-[70vh] flex-col items-center justify-center text-center">
                <div class="mb-8 relative">
                    <div>
                        <ExclamationTriangleIcon class="h-24 w-24 text-[#F02C56] mx-auto" />
                    </div>
                </div>

                <div class="relative mb-6">
                    <h2
                        class="text-8xl sm:text-9xl font-bold bg-gradient-to-r from-[#F02C56] to-purple-600 bg-clip-text text-transparent opacity-90 animate-pulse"
                    >
                        404
                    </h2>
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-[#F02C56] to-purple-600 bg-clip-text text-transparent opacity-20 blur-sm text-8xl sm:text-9xl font-bold"
                    >
                        404
                    </div>
                </div>

                <h1
                    class="mt-4 text-balance text-4xl font-bold tracking-tight text-gray-900 dark:text-slate-50 sm:text-6xl mb-6 animate-fade-in"
                >
                    Oops! Page not found
                </h1>

                <p
                    class="text-pretty text-lg font-medium text-gray-600 dark:text-slate-400 sm:text-xl max-w-2xl leading-relaxed mb-10 animate-fade-in-delay"
                >
                    The page you're looking for seems to have wandered off into the digital void.
                    Don't worry, even the best explorers sometimes take a wrong turn!
                </p>

                <div
                    class="flex flex-col sm:flex-row items-center justify-center gap-4 w-full max-w-md"
                >
                    <router-link
                        to="/"
                        class="group relative w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-[#F02C56] px-6 py-3 text-sm font-semibold text-white shadow-lg hover:bg-red-500 hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#F02C56]"
                    >
                        <HomeIcon
                            class="h-4 w-4 group-hover:scale-110 transition-transform duration-200"
                        />
                        Go back home
                        <div
                            class="absolute inset-0 rounded-xl bg-white/20 scale-0 group-hover:scale-100 transition-transform duration-200"
                        ></div>
                    </router-link>

                    <button
                        @click="goBack"
                        class="group relative w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border-2 border-gray-300 dark:border-slate-600 px-6 py-3 text-sm font-semibold text-gray-700 dark:text-slate-300 hover:border-[#F02C56] hover:text-[#F02C56] dark:hover:text-[#F02C56] transform hover:-translate-y-0.5 transition-all duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500"
                    >
                        <ArrowLeftIcon
                            class="h-4 w-4 group-hover:scale-110 transition-transform duration-200"
                        />
                        Go back
                    </button>
                </div>

                <div class="mt-12 text-center">
                    <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">
                        Still lost? Here are some helpful links:
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-6 text-sm">
                        <router-link
                            to="/about"
                            class="text-[#F02C56] hover:text-red-500 transition-colors duration-200 hover:underline"
                        >
                            About Us
                        </router-link>
                        <router-link
                            to="/contact"
                            class="text-[#F02C56] hover:text-red-500 transition-colors duration-200 hover:underline"
                        >
                            Contact Support
                        </router-link>
                        <router-link
                            to="/help-center"
                            class="text-[#F02C56] hover:text-red-500 transition-colors duration-200 hover:underline"
                        >
                            Help Center
                        </router-link>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="container mx-auto min-h-screen bg-white dark:bg-gray-900 transition-colors"
        >
            <header class="relative overflow-hidden pb-20">
                <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
                <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mt-16">
                        <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-6">
                            {{ pageTitle }}
                        </h1>
                        <div
                            class="w-24 h-1 bg-gradient-to-r from-purple-500 to-blue-500 mx-auto rounded-full"
                        ></div>
                    </div>
                </div>
            </header>

            <section class="pb-20 bg-white dark:bg-gray-900">
                <div class="max-w-4xl mx-auto px-10 sm:px-6 lg:px-8">
                    <article
                        class="dyncon prose prose-lg max-w-none dark:prose-invert prose-p:text-xl prose-p:leading-relaxed prose-p:text-gray-600 dark:prose-p:text-gray-300 prose-p:mb-8"
                        v-html="pageData.content"
                    ></article>

                    <div
                        v-if="pageData.updated_at"
                        class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                            {{ $t('common.lastUpdated') }}:
                            {{ formatDate(pageData.updated_at) }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </MainLayout>
</template>

<script setup>
import { onMounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MainLayout from '@/layouts/FeedLayout.vue'
import { useHead } from '@unhead/vue'
import { useDynamicPage } from '@/composables/useDynamicPage'
import { ExclamationTriangleIcon, HomeIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()

const { loading, error, pageData, pageTitle, currentSlug, loadPage, retry, formatDate } =
    useDynamicPage()

const pageDescription = computed(() => {
    if (pageData.value.meta_description) {
        return pageData.value.meta_description
    }

    if (!pageData.value.content) {
        return `Learn more about ${pageTitle.value} on Loops`
    }

    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = pageData.value.content

    const text = tempDiv.textContent || tempDiv.innerText || ''

    const cleanText = text.replace(/\s+/g, ' ').trim()
    return cleanText.length > 160 ? cleanText.substring(0, 157) + '...' : cleanText
})

const pageUrl = computed(() => {
    return window.location.origin + route.fullPath
})

useHead({
    title: computed(() => `${pageTitle.value} | Loops`),
    meta: computed(() => [
        { name: 'description', content: pageDescription.value },
        { property: 'og:title', content: pageTitle.value },
        { property: 'og:description', content: pageDescription.value },
        { property: 'og:type', content: 'article' },
        { property: 'og:url', content: pageUrl.value },
        { name: 'twitter:card', content: 'summary' },
        { name: 'twitter:title', content: pageTitle.value },
        { name: 'twitter:description', content: pageDescription.value }
    ])
})

watch(
    () => currentSlug.value,
    (newSlug, oldSlug) => {
        if (newSlug !== oldSlug && newSlug) {
            loadPage()
        }
    },
    { immediate: false }
)

watch(
    () => route.fullPath,
    () => {
        if (route.matched.some((record) => record.meta?.isDynamicPage)) {
            loadPage()
        }
    },
    { immediate: false }
)

const goBack = () => {
    if (window.history.length > 1) {
        router.go(-1)
    } else {
        router.push('/')
    }
}

onMounted(() => {
    loadPage()
})
</script>
