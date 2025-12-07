<template>
    <FullLayout>
        <div
            class="w-full flex items-center justify-center px-4"
            style="min-height: calc(100vh - 70px)"
        >
            <router-link
                to="/"
                class="text-lg text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-700 px-6 py-2 rounded-xl"
            >
                Go back home
            </router-link>
        </div>
    </FullLayout>
</template>

<script setup>
import { onMounted, watch, inject, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import FullLayout from '@/layouts/FullLayout.vue'

const authStore = inject('authStore')

const route = useRoute()
const router = useRouter()

onMounted(() => {
    if (authStore.authenticated) {
        router.push('/')
    } else {
        authStore.openAuthModal('login')
    }
})
</script>
