<template>
    <div class="max-w-lg mx-auto text-center py-12">
        <div
            class="bg-white dark:bg-gray-900 rounded-2xl p-8 border border-gray-100 dark:border-gray-900 shadow-lg"
        >
            <div
                class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4"
            >
                <i
                    class="bx bx-search text-gray-600 dark:text-gray-400"
                    style="font-size: 30px"
                ></i>
            </div>

            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                {{ $t('common.hashtagNotFound') }}
            </h3>

            <p class="text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                {{ $t('common.hashtagWeCouldntFind') }}
                <span class="font-semibold">#{{ id }}</span
                >. {{ $t('common.hashtagMayNotExist') }}
            </p>

            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <strong class="text-gray-900 dark:text-gray-200"
                        >{{ $t('common.suggestions') }}:</strong
                    >
                </p>
                <ul class="text-sm text-left text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                    <li v-for="(tip, i) in suggestions" :key="i" class="flex items-start gap-2">
                        <span class="text-blue-600 dark:text-blue-400 mt-0.5">•</span>
                        <span>{{ tip }}</span>
                    </li>
                </ul>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <AnimatedButton class="w-full" variant="primary" @click="gotoExplore">
                    <div class="flex justify-center items-center text-lg gap-1">
                        <i class="bx bx-compass m-0 p-0" style="font-size: 25px"></i>
                        {{ exploreLabel }}
                    </div>
                </AnimatedButton>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
    id: string
    exploreTo?: string | { name: string; params?: Record<string, any> }
    exploreLabel?: string
    suggestions?: string[]
}>()

const router = useRouter()
const { t } = useI18n()

const gotoExplore = () => {
    router.push(exploreTo)
}

const {
    exploreTo = '/explore',
    exploreLabel = t('common.exploreTags'),
    suggestions = [
        t('common.doubleCheckSpelling'),
        t('common.tryARelatedOrSimilarHashtag'),
        t('common.browseTrendingTagsInstead')
    ]
} = props
</script>
