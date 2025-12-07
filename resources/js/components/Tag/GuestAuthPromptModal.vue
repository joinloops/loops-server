<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="handleClose"
            >
                <Transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition-all duration-200"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="show"
                        class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-2xl shadow-2xl"
                        @click.stop
                    >
                        <button
                            @click="handleClose"
                            class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors cursor-pointer"
                        >
                            <i class="bx bx-x" style="font-size: 30px"></i>
                        </button>

                        <div class="p-8">
                            <div
                                class="mx-auto w-16 h-16 bg-gradient-to-br from-red-500 to-pink-600 rounded-full flex items-center justify-center mb-6"
                            >
                                <i class="bx bx-user text-white" style="font-size: 40px" />
                            </div>

                            <h2
                                class="text-2xl font-bold text-gray-900 dark:text-white mb-3 text-center"
                            >
                                {{ $t('common.keepWatching') }}
                            </h2>

                            <p
                                class="text-gray-600 dark:text-gray-400 mb-8 text-sm leading-relaxed"
                            >
                                {{
                                    appConfig.registration
                                        ? $t('common.createAFreeAccountToContinueExploring')
                                        : $t('common.loginToContinueExploring')
                                }}
                            </p>

                            <div class="space-y-3">
                                <AnimatedButton
                                    v-if="appConfig.registration"
                                    @click="handleRegister"
                                    class="w-full mb-3 rounded-xl"
                                    variant="primary"
                                >
                                    <div class="text-lg">
                                        {{ $t('common.createAccount') }}
                                    </div>
                                </AnimatedButton>

                                <AnimatedButton
                                    @click="handleLogin"
                                    class="w-full mb-3 rounded-xl"
                                    variant="light"
                                >
                                    <div class="text-lg">
                                        {{ $t('nav.logIn') }}
                                    </div>
                                </AnimatedButton>
                            </div>

                            <div class="mt-6 text-xs text-gray-500 dark:text-gray-500">
                                {{ $t('common.byContinuingYouAgreeToOur') }}
                                <router-link
                                    to="/terms"
                                    class="text-red-600 dark:text-red-400 font-semibold"
                                    target="_blank"
                                    >{{ $t('common.termsOfService') }}</router-link
                                >,
                                <router-link
                                    to="/privacy"
                                    class="text-red-600 dark:text-red-400 font-semibold"
                                    target="_blank"
                                    >{{ $t('common.privacyPolicy') }}</router-link
                                >
                                {{ $t('common.and') }}
                                <router-link
                                    to="/community-guidelines"
                                    class="text-red-600 dark:text-red-400 font-semibold"
                                    target="_blank"
                                    >{{ $t('common.communityGuidelines') }}</router-link
                                >.
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { inject } from 'vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close'])

const authStore = inject('authStore')
const appConfig = inject('appConfig')

const handleClose = () => {
    emit('close')
}

const handleLogin = () => {
    emit('close')
    authStore.openAuthModal('login')
}

const handleRegister = () => {
    emit('close')
    authStore.openAuthModal('register')
}
</script>
