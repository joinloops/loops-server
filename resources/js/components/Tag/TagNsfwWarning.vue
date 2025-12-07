<template>
    <div class="max-w-md mx-auto text-center py-12">
        <div
            class="bg-white dark:bg-gray-900 rounded-2xl p-8 border border-gray-100 dark:border-gray-700 shadow-lg"
        >
            <AlertTriangleIcon />

            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                {{ $t('common.ageRestrictedContent') }}
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                {{ $t('common.hashtagContainsAgeRestrictedContent') }}
            </p>
            <AnimatedButton @click="handleLogin" class="w-full">
                <div class="flex justify-center text-center text-lg gap-2 items-center flex-row">
                    <svg
                        class="inline-flex w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"
                        />
                    </svg>
                    {{ $t('common.loginToContinue') }}
                </div>
            </AnimatedButton>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const handleLogin = () => {
    router.push('/login')
}
</script>
