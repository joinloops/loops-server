<template>
    <div class="max-w-8xl mx-auto p-6 space-y-6">
        <div class="flex items-center gap-3">
            <router-link
                :to="backLink"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
                <ArrowLeftIcon class="h-5 w-5" />
            </router-link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Conversation Details
                </h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">ID: {{ route.params.id }}</p>
            </div>
        </div>

        <div
            v-if="loading"
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
        >
            <div class="animate-pulse space-y-4">
                <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>

        <AdminConversationReview v-else-if="conversation" :conversation="conversation" />

        <div
            v-else
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 text-center"
        >
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                Conversation Not Found
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                The conversation you're looking for doesn't exist or has been removed.
            </p>
            <router-link
                :to="backLink"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
            >
                Go Back
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import { conversationsApi } from '@/services/adminApi'
import AdminConversationReview from '@/components/Admin/AdminConversationReview.vue'

const route = useRoute()

const loading = ref(true)
const conversation = ref(null)

const backLink = computed(() =>
    /^\d+$/.test(String(route.query.profile || ''))
        ? `/admin/profiles/${route.query.profile}/conversations`
        : '/admin/profiles'
)

const fetchConversation = async (id) => {
    loading.value = true

    try {
        const response = await conversationsApi.getConversation(id)
        conversation.value = response.data
    } catch (error) {
        console.error('Error fetching conversation:', error)
        conversation.value = null
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchConversation(route.params.id)
})

watch(
    () => route.params.id,
    (id) => {
        if (id) {
            fetchConversation(id)
        }
    }
)
</script>
